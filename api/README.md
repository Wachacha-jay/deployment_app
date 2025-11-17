# Turi-A-Mumbi Arts Shop - API Documentation

## Project Structure

```
turi-a-mumbi-arts/
├── src/                          # React frontend
├── api/                          # PHP backend
│   ├── config/
│   │   └── db.php               # Database configuration
│   ├── controllers/
│   │   ├── ProductController.php # Product endpoints
│   │   ├── OrderController.php   # Order endpoints
│   │   ├── PaymentController.php # M-Pesa payment endpoints
│   │   └── CartController.php    # Cart validation endpoints
│   ├── models/
│   │   ├── Product.php          # Product model
│   │   └── Order.php            # Order model
│   ├── utils/
│   │   ├── Response.php         # API response helper
│   │   ├── CORS.php             # CORS handler
│   │   └── Validator.php        # Input validator
│   ├── logs/                     # Payment callback logs
│   ├── index.php                # Main API router
│   ├── database_schema.sql      # Database schema
│   └── .env.example             # Environment variables template
└── dist/                        # Built React app (deployed to public_html)
```

## Setup Instructions

### 1. Database Setup

1. **Create `.env` file** in `/api` directory:
```bash
cp api/.env.example api/.env
```

2. **Edit `.env`** with your Hostinger credentials:
```
DB_HOST=localhost
DB_USER=your_hostinger_db_username
DB_PASS=your_hostinger_db_password
DB_NAME=turi_arts_shop
DB_PORT=3306

MPESA_CONSUMER_KEY=your_mpesa_key
MPESA_CONSUMER_SECRET=your_mpesa_secret
MPESA_PASSKEY=your_mpesa_passkey
MPESA_BUSINESS_SHORT_CODE=your_short_code
MPESA_ENVIRONMENT=sandbox

API_URL=https://yourdomain.com/api
FRONTEND_URL=https://yourdomain.com
PAYMENT_CALLBACK_URL=https://yourdomain.com/api/payment/callback
JWT_SECRET=your_random_secret_key
```

3. **Import database schema**:
   - Go to your Hostinger cPanel → MySQL/phpMyAdmin
   - Create a new database named `turi_arts_shop`
   - Import `api/database_schema.sql` file

### 2. PHP Backend Deployment

1. **Upload to Hostinger**:
   - Upload entire `api/` folder to your public_html (or root of your hosting)
   - Ensure `.env` file is in the `api/` directory
   - Make sure `api/logs/` directory is writable

2. **Test API**:
   ```
   https://yourdomain.com/api/health
   ```
   Should return: `{"success":true,"message":"API is running"}`

### 3. Frontend React Build

1. **Build React app**:
   ```bash
   npm run build
   ```

2. **Deploy to Hostinger**:
   - The `dist/` folder contains static files
   - Upload all files from `dist/` to your `public_html` folder
   - This is what visitors see when they visit your domain

### 4. Configure Frontend API URLs

Update your React API calls to point to the backend. In your React components, use:

```javascript
const API_URL = process.env.REACT_APP_API_URL || 'https://yourdomain.com/api';

// Example fetch call
fetch(`${API_URL}/products`)
  .then(res => res.json())
  .then(data => console.log(data));
```

Add to `.env` in your React project root:
```
VITE_API_URL=https://yourdomain.com/api
```

## API Endpoints

### Products

#### Get All Products
```
GET /api/products
Query Parameters:
  - limit: number (default: 20, max: 100)
  - offset: number (default: 0)

Response:
{
  "success": true,
  "message": "Products retrieved successfully",
  "data": {
    "products": [...],
    "limit": 20,
    "offset": 0
  }
}
```

#### Get Single Product
```
GET /api/products/:id

Response:
{
  "success": true,
  "message": "Product retrieved successfully",
  "data": {
    "id": 1,
    "name": "Traditional Drum",
    "description": "...",
    "base_price": 5000,
    "category": "Instruments",
    "in_stock": true,
    "featured": true,
    "variants": [...]
  }
}
```

#### Get Products by Category
```
GET /api/products/category/:category
Query Parameters:
  - limit: number
  - offset: number
```

#### Get Featured Products
```
GET /api/products/featured
Query Parameters:
  - limit: number (default: 6)
```

#### Search Products
```
GET /api/products/search?q=search_term

Query Parameters:
  - q: search term (min 2 characters)
```

#### Create Product (Admin)
```
POST /api/products
Content-Type: application/json

{
  "name": "Product Name",
  "description": "Product description",
  "base_price": 5000,
  "category": "Category",
  "in_stock": 1,
  "featured": 1
}
```

#### Update Product (Admin)
```
PUT /api/products/:id
Content-Type: application/json

{
  "name": "Updated Name",
  "base_price": 6000,
  ...
}
```

#### Delete Product (Admin)
```
DELETE /api/products/:id
```

### Orders

#### Create Order
```
POST /api/orders
Content-Type: application/json

{
  "customer_name": "John Doe",
  "customer_email": "john@example.com",
  "customer_phone": "+254712345678",
  "shipping_address": "123 Main St, Nairobi",
  "items": [
    {
      "product_id": 1,
      "variant_id": 1,
      "quantity": 2
    }
  ]
}

Response:
{
  "success": true,
  "message": "Order created successfully",
  "data": {
    "id": 1,
    "customer_name": "John Doe",
    "customer_email": "john@example.com",
    "total": 10000,
    "status": "pending",
    "items": [...]
  }
}
```

#### Get Order by ID
```
GET /api/orders/:id
```

#### Get Orders by Email
```
GET /api/orders/email/:email
```

#### Get All Orders (Admin)
```
GET /api/orders
Query Parameters:
  - limit: number
  - offset: number
```

#### Search Orders (Admin)
```
GET /api/orders/search?q=search_term
```

#### Update Order Status (Admin)
```
PUT /api/orders/:id/status
Content-Type: application/json

{
  "status": "processing"  // pending, processing, shipped, delivered, cancelled
}
```

### Payments (M-Pesa)

#### Initiate Payment
```
POST /api/payment/initiate
Content-Type: application/json

{
  "order_id": 1,
  "phone_number": "+254712345678",  // or 0712345678
  "amount": 10000
}

Response:
{
  "success": true,
  "message": "Payment initiated successfully",
  "data": {
    "order_id": 1,
    "request_id": "...",
    "message": "Payment prompt sent to your phone"
  }
}
```

#### Check Payment Status
```
GET /api/payment/status/:order_id

Response:
{
  "success": true,
  "message": "Payment status retrieved",
  "data": {
    "id": 1,
    "order_id": 1,
    "amount": 10000,
    "status": "completed",  // initiated, pending, completed, failed
    "mpesa_reference": "LK431...",
    "created_at": "2024-01-15 10:30:00"
  }
}
```

#### M-Pesa Callback (Webhook)
```
POST /api/payment/callback
(M-Pesa will send this automatically)

This endpoint processes M-Pesa payment responses.
```

### Cart Validation

#### Validate Cart
```
POST /api/cart/validate
Content-Type: application/json

{
  "items": [
    {
      "variant_id": 1,
      "quantity": 2
    },
    {
      "variant_id": 3,
      "quantity": 1
    }
  ]
}

Response:
{
  "success": true,
  "message": "Cart validated",
  "data": {
    "items": [
      {
        "variant_id": 1,
        "product_id": 1,
        "product_name": "Traditional Drum",
        "variant_name": "Medium",
        "quantity": 2,
        "price": 7500,
        "item_total": 15000,
        "stock_available": 5
      }
    ],
    "total": 15000,
    "errors": []
  }
}
```

#### Check Stock
```
POST /api/cart/check-stock
Content-Type: application/json

{
  "variant_ids": [1, 2, 3]
}

Response:
{
  "success": true,
  "message": "Stock information retrieved",
  "data": [
    {
      "variant_id": 1,
      "stock": 10,
      "price": 5000,
      "in_stock": true
    }
  ]
}
```

### Health Check

```
GET /api/health

Response:
{
  "success": true,
  "message": "API is running"
}
```

## Error Responses

All error responses follow this format:

```json
{
  "success": false,
  "message": "Error description",
  "data": null
}
```

HTTP Status Codes:
- `200`: Success
- `201`: Created
- `400`: Bad Request (validation errors)
- `401`: Unauthorized
- `403`: Forbidden
- `404`: Not Found
- `405`: Method Not Allowed
- `422`: Unprocessable Entity (validation errors)
- `500`: Server Error

## Validation Rules

### Phone Number
Format: `+254XXXXXXXXX` or `0XXXXXXXXX` (Kenya only)

### Email
Standard email validation

### Required Fields
- Products: name, description, base_price, category
- Orders: customer_name, customer_email, customer_phone, shipping_address, items
- Payments: order_id, phone_number, amount

## M-Pesa Integration

### Sandbox Testing
1. Go to [Safaricom Daraja Portal](https://developer.safaricom.co.ke)
2. Register an app and get credentials
3. Use test phone numbers for STK Push testing
4. Set environment to `sandbox`

### Production Setup
1. Register business with Safaricom
2. Get production credentials
3. Update `.env` with production credentials
4. Change `MPESA_ENVIRONMENT=production`
5. Update callback URL to production domain

### Test Credentials Example
```
Consumer Key: VtjhN...
Consumer Secret: yGXF...
Pass Key: bfb27...
Business Short Code: 174379
Test Phone: 254712345678
```

## Database Schema

### Products Table
- id (INT)
- name (VARCHAR 255)
- description (TEXT)
- base_price (DECIMAL 10,2)
- category (VARCHAR 100)
- in_stock (TINYINT)
- featured (TINYINT)
- created_at, updated_at (TIMESTAMP)

### Product Variants Table
- id (INT)
- product_id (INT FK)
- name (VARCHAR 100)
- price (DECIMAL 10,2)
- stock (INT)
- sku (VARCHAR 100 UNIQUE)
- created_at, updated_at (TIMESTAMP)

### Orders Table
- id (INT)
- customer_name (VARCHAR 255)
- customer_email (VARCHAR 255)
- customer_phone (VARCHAR 20)
- shipping_address (TEXT)
- total (DECIMAL 10,2)
- status (ENUM)
- payment_status (ENUM)
- mpesa_reference (VARCHAR 255)
- created_at, updated_at (TIMESTAMP)

### Order Items Table
- id (INT)
- order_id (INT FK)
- product_id (INT FK)
- variant_id (INT FK)
- quantity (INT)
- price (DECIMAL 10,2)
- created_at (TIMESTAMP)

### Payments Table
- id (INT)
- order_id (INT FK)
- amount (DECIMAL 10,2)
- payment_method (VARCHAR 50)
- mpesa_request_id (VARCHAR 255)
- mpesa_reference (VARCHAR 255)
- status (ENUM)
- response_data (JSON)
- created_at, updated_at (TIMESTAMP)

## React Integration Example

```javascript
// api/client.ts
const API_URL = import.meta.env.VITE_API_URL || 'https://yourdomain.com/api';

export const apiClient = {
  // Products
  getProducts: async (limit = 20, offset = 0) => {
    const response = await fetch(`${API_URL}/products?limit=${limit}&offset=${offset}`);
    return response.json();
  },

  getProduct: async (id) => {
    const response = await fetch(`${API_URL}/products/${id}`);
    return response.json();
  },

  // Orders
  createOrder: async (orderData) => {
    const response = await fetch(`${API_URL}/orders`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(orderData),
    });
    return response.json();
  },

  getOrder: async (id) => {
    const response = await fetch(`${API_URL}/orders/${id}`);
    return response.json();
  },

  // Payments
  initiatePayment: async (orderId, phoneNumber, amount) => {
    const response = await fetch(`${API_URL}/payment/initiate`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ order_id: orderId, phone_number: phoneNumber, amount }),
    });
    return response.json();
  },

  checkPaymentStatus: async (orderId) => {
    const response = await fetch(`${API_URL}/payment/status/${orderId}`);
    return response.json();
  },

  // Cart
  validateCart: async (items) => {
    const response = await fetch(`${API_URL}/cart/validate`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ items }),
    });
    return response.json();
  },
};
```

## Deployment Checklist

- [ ] Database created and schema imported
- [ ] `.env` file created with correct credentials
- [ ] `api/` folder uploaded to hosting
- [ ] `api/logs/` folder created and writable
- [ ] `/api/health` endpoint responds
- [ ] React app built with `npm run build`
- [ ] `dist/` folder deployed to `public_html`
- [ ] VITE_API_URL environment variable set
- [ ] M-Pesa credentials configured
- [ ] CORS origins updated in `api/utils/CORS.php`
- [ ] SSL certificate installed (HTTPS)
- [ ] M-Pesa callback URL configured in Safaricom Daraja

## Troubleshooting

### Database Connection Issues
- Check credentials in `.env`
- Verify database name exists
- Check user permissions in cPanel
- Review database host (usually localhost on shared hosting)

### API Not Responding
- Verify `.env` file exists
- Check PHP error logs in cPanel
- Ensure CORS headers are being sent
- Test with `https://yourdomain.com/api/health`

### M-Pesa Payment Issues
- Verify callback URL is accessible
- Check M-Pesa credentials in `.env`
- Review payment logs in `api/logs/mpesa_callbacks.log`
- Test with sandbox credentials first

### React API Calls Failing
- Check CORS settings in `api/utils/CORS.php`
- Verify API_URL in React environment
- Check browser console for actual error
- Ensure `Content-Type: application/json` header

## Support

For issues or questions:
1. Check the troubleshooting section above
2. Review error logs in `api/logs/`
3. Verify `.env` file configuration
4. Test endpoints with Postman/curl

---

**Last Updated**: November 13, 2024
**Version**: 1.0
