<?php
/**
 * M-Pesa Payment Controller
 */

require_once __DIR__ . '/../utils/Response.php';

class PaymentController {
    private $mysqli;
    private $consumerKey;
    private $consumerSecret;
    private $businessShortCode;
    private $passKey;
    private $environment;

    public function __construct($mysqli) {
        $this->mysqli = $mysqli;
        $this->consumerKey = $_ENV['MPESA_CONSUMER_KEY'] ?? '';
        $this->consumerSecret = $_ENV['MPESA_CONSUMER_SECRET'] ?? '';
        $this->businessShortCode = $_ENV['MPESA_BUSINESS_SHORT_CODE'] ?? '';
        $this->passKey = $_ENV['MPESA_PASSKEY'] ?? '';
        $this->environment = $_ENV['MPESA_ENVIRONMENT'] ?? 'sandbox';
    }

    /**
     * POST /api/payment/initiate - Initiate M-Pesa payment
     */
    public function initiatePayment() {
        try {
            $input = json_decode(file_get_contents('php://input'), true);

            if (!isset($input['order_id'], $input['phone_number'], $input['amount'])) {
                Response::error('Missing required fields', null, 400);
            }

            $orderId = (int)$input['order_id'];
            $phoneNumber = preg_replace('/[^0-9]/', '', $input['phone_number']);
            $amount = (int)$input['amount'];

            // Validate phone number format
            if (!preg_match('/^254\d{9}$/', $phoneNumber) && !preg_match('/^0\d{9}$/', $phoneNumber)) {
                Response::error('Invalid phone number format', null, 400);
            }

            // Convert to international format if needed
            if (strpos($phoneNumber, '0') === 0) {
                $phoneNumber = '254' . substr($phoneNumber, 1);
            }

            // Check if order exists
            $query = "SELECT * FROM orders WHERE id = ?";
            $stmt = $this->mysqli->prepare($query);
            $stmt->bind_param("i", $orderId);
            $stmt->execute();
            $result = $stmt->get_result();

            if (!$result->fetch_assoc()) {
                Response::error('Order not found', null, 404);
            }

            // Get access token
            $accessToken = $this->getAccessToken();
            if (!$accessToken) {
                Response::error('Failed to get M-Pesa access token', null, 500);
            }

            // Initiate STK Push
            $response = $this->stkPush($phoneNumber, $amount, $orderId, $accessToken);

            if (!$response) {
                Response::error('Failed to initiate payment', null, 500);
            }

            // Save payment request
            $this->savePaymentRequest($orderId, $amount, $response);

            Response::success('Payment initiated successfully', [
                'order_id' => $orderId,
                'request_id' => $response['RequestID'] ?? null,
                'message' => $response['ResponseDescription'] ?? 'Payment prompt sent to your phone'
            ]);

        } catch (Exception $e) {
            Response::error('Payment initiation failed: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * POST /api/payment/callback - M-Pesa payment callback
     */
    public function handleCallback() {
        try {
            $input = json_decode(file_get_contents('php://input'), true);

            // Log callback for debugging
            $this->logCallback($input);

            if (!isset($input['Body']['stkCallback'])) {
                Response::error('Invalid callback structure', null, 400);
            }

            $callback = $input['Body']['stkCallback'];
            $resultCode = $callback['ResultCode'] ?? null;
            $resultDesc = $callback['ResultDesc'] ?? '';
            $merchantRequestID = $callback['MerchantRequestID'] ?? null;
            $checkoutRequestID = $callback['CheckoutRequestID'] ?? null;

            // Find payment by merchant request ID
            $query = "SELECT * FROM payments WHERE mpesa_request_id = ?";
            $stmt = $this->mysqli->prepare($query);
            $stmt->bind_param("s", $merchantRequestID);
            $stmt->execute();
            $result = $stmt->get_result();
            $payment = $result->fetch_assoc();

            if (!$payment) {
                Response::error('Payment not found', null, 404);
            }

            if ($resultCode == 0) {
                // Payment successful
                $callbackMetadata = $callback['CallbackMetadata']['Item'] ?? [];
                $mpesaRef = $this->extractMetadata($callbackMetadata, 'MpesaReceiptNumber');
                $amount = $this->extractMetadata($callbackMetadata, 'Amount');
                $transactionDate = $this->extractMetadata($callbackMetadata, 'TransactionDate');
                $phoneNumber = $this->extractMetadata($callbackMetadata, 'PhoneNumber');

                // Update payment record
                $updateQuery = "UPDATE payments SET status = 'completed', mpesa_reference = ?, response_data = ? WHERE id = ?";
                $updateStmt = $this->mysqli->prepare($updateQuery);
                $responseData = json_encode($callback);
                $updateStmt->bind_param("ssi", $mpesaRef, $responseData, $payment['id']);
                $updateStmt->execute();

                // Update order payment status
                $orderQuery = "UPDATE orders SET payment_status = 'completed', mpesa_reference = ? WHERE id = ?";
                $orderStmt = $this->mysqli->prepare($orderQuery);
                $orderStmt->bind_param("si", $mpesaRef, $payment['order_id']);
                $orderStmt->execute();

                // Update order status to processing
                $statusQuery = "UPDATE orders SET status = 'processing' WHERE id = ?";
                $statusStmt = $this->mysqli->prepare($statusQuery);
                $statusStmt->bind_param("i", $payment['order_id']);
                $statusStmt->execute();

            } else {
                // Payment failed
                $updateQuery = "UPDATE payments SET status = 'failed', response_data = ? WHERE id = ?";
                $updateStmt = $this->mysqli->prepare($updateQuery);
                $responseData = json_encode($callback);
                $updateStmt->bind_param("si", $responseData, $payment['id']);
                $updateStmt->execute();

                // Update order payment status
                $orderQuery = "UPDATE orders SET payment_status = 'failed' WHERE id = ?";
                $orderStmt = $this->mysqli->prepare($orderQuery);
                $orderStmt->bind_param("i", $payment['order_id']);
                $orderStmt->execute();
            }

            Response::success('Callback processed successfully');

        } catch (Exception $e) {
            Response::error('Callback processing failed: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * GET /api/payment/status/:order_id - Check payment status
     */
    public function checkPaymentStatus($orderId) {
        try {
            $orderId = (int)$orderId;

            $query = "SELECT * FROM payments WHERE order_id = ? ORDER BY created_at DESC LIMIT 1";
            $stmt = $this->mysqli->prepare($query);
            $stmt->bind_param("i", $orderId);
            $stmt->execute();
            $result = $stmt->get_result();

            if (!$payment = $result->fetch_assoc()) {
                Response::error('No payment found for this order', null, 404);
            }

            Response::success('Payment status retrieved', $payment);

        } catch (Exception $e) {
            Response::error('Failed to check payment status: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Get M-Pesa access token
     */
    private function getAccessToken() {
        $url = $this->environment === 'production'
            ? 'https://api.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials'
            : 'https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials';

        $credentials = base64_encode($this->consumerKey . ':' . $this->consumerSecret);

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($curl, CURLOPT_HTTPHEADER, [
            'Authorization: Basic ' . $credentials,
            'Content-Type: application/json',
        ]);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($curl);
        curl_close($curl);

        $result = json_decode($response, true);
        return $result['access_token'] ?? null;
    }

    /**
     * Initiate STK Push
     */
    private function stkPush($phoneNumber, $amount, $orderId, $accessToken) {
        $timestamp = date('YmdHis');
        $password = base64_encode($this->businessShortCode . $this->passKey . $timestamp);

        $url = $this->environment === 'production'
            ? 'https://api.safaricom.co.ke/mpesa/stkpush/v1/processrequest'
            : 'https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest';

        $payload = [
            'BusinessShortCode' => $this->businessShortCode,
            'Password' => $password,
            'Timestamp' => $timestamp,
            'TransactionType' => 'CustomerPayBillOnline',
            'Amount' => $amount,
            'PartyA' => $phoneNumber,
            'PartyB' => $this->businessShortCode,
            'PhoneNumber' => $phoneNumber,
            'CallBackURL' => $_ENV['PAYMENT_CALLBACK_URL'] ?? '',
            'AccountReference' => 'Order' . $orderId,
            'TransactionDesc' => 'Payment for Order #' . $orderId,
        ];

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json',
        ]);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($curl);
        curl_close($curl);

        return json_decode($response, true);
    }

    /**
     * Save payment request to database
     */
    private function savePaymentRequest($orderId, $amount, $response) {
        $query = "INSERT INTO payments (order_id, amount, currency, payment_method, mpesa_request_id, status, response_data)
                  VALUES (?, ?, 'KES', 'M-Pesa', ?, 'initiated', ?)";
        
        $stmt = $this->mysqli->prepare($query);
        $requestId = $response['RequestID'] ?? null;
        $responseData = json_encode($response);
        
        $stmt->bind_param("idss", $orderId, $amount, $requestId, $responseData);
        $stmt->execute();

        return $this->mysqli->insert_id;
    }

    /**
     * Extract metadata from callback
     */
    private function extractMetadata($items, $name) {
        foreach ($items as $item) {
            if (isset($item['Name']) && $item['Name'] == $name) {
                return $item['Value'] ?? null;
            }
        }
        return null;
    }

    /**
     * Log callback for debugging
     */
    private function logCallback($data) {
        $logFile = dirname(__DIR__) . '/logs/mpesa_callbacks.log';
        $dir = dirname($logFile);
        
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $log = date('Y-m-d H:i:s') . " - " . json_encode($data) . "\n";
        file_put_contents($logFile, $log, FILE_APPEND);
    }
}

?>
