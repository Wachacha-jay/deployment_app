<?php
/**
 * Input Validation Helper
 */

class Validator {
    private $errors = [];

    public function validate($data, $rules) {
        foreach ($rules as $field => $fieldRules) {
            if (!isset($data[$field]) || empty($data[$field])) {
                if (in_array('required', $fieldRules)) {
                    $this->errors[$field] = ucfirst($field) . ' is required';
                }
                continue;
            }

            $value = $data[$field];

            foreach ($fieldRules as $rule) {
                if ($rule === 'required') continue;
                
                if (strpos($rule, 'min:') === 0) {
                    $min = (int)substr($rule, 4);
                    if (strlen($value) < $min) {
                        $this->errors[$field] = ucfirst($field) . ' must be at least ' . $min . ' characters';
                    }
                }
                
                if (strpos($rule, 'max:') === 0) {
                    $max = (int)substr($rule, 4);
                    if (strlen($value) > $max) {
                        $this->errors[$field] = ucfirst($field) . ' must not exceed ' . $max . ' characters';
                    }
                }
                
                if ($rule === 'email') {
                    if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                        $this->errors[$field] = 'Invalid email address';
                    }
                }
                
                if ($rule === 'numeric') {
                    if (!is_numeric($value)) {
                        $this->errors[$field] = ucfirst($field) . ' must be numeric';
                    }
                }

                if ($rule === 'phone') {
                    if (!preg_match('/^(\+254|0)[0-9]{9}$/', $value)) {
                        $this->errors[$field] = 'Invalid phone number';
                    }
                }
            }
        }

        return empty($this->errors);
    }

    public function getErrors() {
        return $this->errors;
    }
}

?>
