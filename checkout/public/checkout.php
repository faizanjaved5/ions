<?php

require_once __DIR__ . '/../classes/CheckoutManager.php';

// Initialize checkout manager
$checkout = new CheckoutManager();

// Handle form submissions
$message = '';
$messageType = '';

if ($_POST) {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'add_to_cart':
            $productData = [
                'id' => $_POST['product_id'] ?? '',
                'name' => $_POST['product_name'] ?? '',
                'price' => $_POST['product_price'] ?? 0,
                'type' => $_POST['product_type'] ?? 'General',
                'quantity' => $_POST['quantity'] ?? 1,
                'optionId' => $_POST['option_id'] ?? null,
                'unit' => $_POST['unit'] ?? 'piece',
                'currencyId' => $_POST['currency'] ?? 'USD'
            ];
            
            if ($checkout->addToCart($productData)) {
                $message = 'Product added to cart successfully!';
                $messageType = 'success';
            } else {
                $message = 'Error adding product to cart: ' . implode(', ', $checkout->getErrors());
                $messageType = 'error';
            }
            break;
            
        case 'update_customer':
            if ($checkout->validateCsrfToken($_POST['csrf_token'] ?? '')) {
                $customerData = [
                    'firstName' => $_POST['first_name'] ?? '',
                    'lastName' => $_POST['last_name'] ?? '',
                    'email' => $_POST['email'] ?? '',
                    'phone' => $_POST['phone'] ?? '',
                    'companyName' => $_POST['company_name'] ?? ''
                ];
                
                if ($checkout->updateCustomerInfo($customerData)) {
                    $message = 'Customer information updated successfully!';
                    $messageType = 'success';
                } else {
                    $message = 'Error updating customer information: ' . implode(', ', $checkout->getErrors());
                    $messageType = 'error';
                }
            } else {
                $message = 'Invalid security token. Please refresh the page.';
                $messageType = 'error';
            }
            break;
            
        case 'update_billing':
            if ($checkout->validateCsrfToken($_POST['csrf_token'] ?? '')) {
                $addressData = [
                    'street' => $_POST['billing_street'] ?? '',
                    'city' => $_POST['billing_city'] ?? '',
                    'state' => $_POST['billing_state'] ?? '',
                    'zip' => $_POST['billing_zip'] ?? '',
                    'country' => $_POST['billing_country'] ?? ''
                ];
                
                if ($checkout->updateBillingAddress($addressData)) {
                    $message = 'Billing address updated successfully!';
                    $messageType = 'success';
                } else {
                    $message = 'Error updating billing address: ' . implode(', ', $checkout->getErrors());
                    $messageType = 'error';
                }
            }
            break;
            
        case 'apply_coupon':
            if ($checkout->validateCsrfToken($_POST['csrf_token'] ?? '')) {
                $couponCode = $_POST['coupon_code'] ?? '';
                $tenantId = $_POST['tenant_id'] ?? Config::get('DEFAULT_TENANT_ID');
                
                if ($checkout->applyCoupon($couponCode, $tenantId)) {
                    $message = 'Coupon applied successfully!';
                    $messageType = 'success';
                } else {
                    $message = 'Error applying coupon: ' . implode(', ', $checkout->getErrors());
                    $messageType = 'error';
                }
            }
            break;
            
        case 'submit_payment':
            if ($checkout->validateCsrfToken($_POST['csrf_token'] ?? '')) {
                $paymentGateway = $_POST['payment_gateway'] ?? 'Stripe';
                $tenantId = $_POST['tenant_id'] ?? Config::get('DEFAULT_TENANT_ID');
                
                $result = $checkout->submitPaymentRequest($paymentGateway, $tenantId);
                
                if ($result && $result['success']) {
                    // Redirect to payment gateway
                    header('Location: ' . $result['paymentUrl']);
                    exit;
                } else {
                    $message = 'Payment submission failed: ' . implode(', ', $checkout->getErrors());
                    $messageType = 'error';
                }
            }
            break;
    }
}

// Get current session data
$sessionData = $checkout->getSessionData();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PHP Checkout MVP</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f5f5f5;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .header {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .checkout-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
        }
        
        .checkout-main {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .checkout-sidebar {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            height: fit-content;
        }
        
        .section {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }
        
        .section:last-child {
            border-bottom: none;
        }
        
        .section h2 {
            margin-bottom: 15px;
            color: #2c3e50;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }
        
        input, select, textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        
        .btn {
            background: #007bff;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
        }
        
        .btn:hover {
            background: #0056b3;
        }
        
        .btn-secondary {
            background: #6c757d;
        }
        
        .btn-paypal {
            background: #ffc439;
            color: #003087;
        }
        
        .message {
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        
        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .cart-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }
        
        .cart-total {
            font-size: 18px;
            font-weight: bold;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 2px solid #007bff;
        }
        
        .payment-methods {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-top: 20px;
        }
        
        @media (max-width: 768px) {
            .checkout-grid {
                grid-template-columns: 1fr;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .payment-methods {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>PHP Checkout MVP</h1>
            <p>Extracted from Next.js Landing Page Project</p>
            <?php if (Config::get('MOCK_PAYMENT_MODE')): ?>
        <div style="background: #d1ecf1; border: 1px solid #bee5eb; padding: 10px; border-radius: 4px; margin-top: 10px;">
            <strong>🧪 Mock Payment Mode Enabled</strong> - Payments will be simulated for testing
        </div>
    <?php elseif (Config::get('USE_DIRECT_STRIPE') || Config::get('USE_DIRECT_PAYPAL')): ?>
        <div style="background: #e7f1ff; border: 1px solid #b3d9ff; padding: 10px; border-radius: 4px; margin-top: 10px;">
            <strong>⚡ Direct Payment Mode Active</strong> - Payments will be processed directly through 
            <?php 
            $modes = [];
            if (Config::get('USE_DIRECT_STRIPE')) $modes[] = 'Stripe';
            if (Config::get('USE_DIRECT_PAYPAL')) $modes[] = 'PayPal';
            echo implode(' & ', $modes);
            ?> (bypasses Sperse)
            <?php if (Config::isDebugMode()): ?>
                <br><small>Debug mode is ON - detailed error information will be displayed</small>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div style="background: #d4edda; border: 1px solid #c3e6cb; padding: 10px; border-radius: 4px; margin-top: 10px;">
            <strong>🚀 Real Payment Mode Active</strong> - Payments will be processed through Sperse API
            <?php if (Config::isDebugMode()): ?>
                <br><small>Debug mode is ON - detailed error information will be displayed</small>
            <?php endif; ?>
        </div>
    <?php endif; ?>
        </div>
        
        <?php if ($message): ?>
            <div class="message <?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <div class="checkout-grid">
            <div class="checkout-main">
                <!-- Add Product Section (Demo) -->
                <div class="section">
                    <h2>Add Demo Product</h2>
                    <form method="POST">
                        <input type="hidden" name="action" value="add_to_cart">
                        <div class="form-row">
                            <div class="form-group">
                                <label>Product Name</label>
                                <input type="text" name="product_name" value="Demo Product" required>
                            </div>
                            <div class="form-group">
                                <label>Price</label>
                                <input type="number" name="product_price" value="29.99" step="0.01" required>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Product ID</label>
                                <input type="number" name="product_id" value="606" required>
                            </div>
                            <div class="form-group">
                                <label>Quantity</label>
                                <input type="number" name="quantity" value="1" min="1" required>
                            </div>
                        </div>
                        <button type="submit" class="btn">Add to Cart</button>
                    </form>
                </div>
                
                <!-- Customer Information -->
                <div class="section">
                    <h2>Customer Information</h2>
                    <form method="POST">
                        <input type="hidden" name="action" value="update_customer">
                        <input type="hidden" name="csrf_token" value="<?php echo $sessionData['csrf_token']; ?>">
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>First Name *</label>
                                <input type="text" name="first_name" value="<?php echo htmlspecialchars($sessionData['customer_info']['firstName'] ?? ''); ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Last Name *</label>
                                <input type="text" name="last_name" value="<?php echo htmlspecialchars($sessionData['customer_info']['lastName'] ?? ''); ?>" required>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Email *</label>
                            <input type="email" name="email" value="<?php echo htmlspecialchars($sessionData['customer_info']['email'] ?? ''); ?>" required>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>Phone</label>
                                <input type="tel" name="phone" value="<?php echo htmlspecialchars($sessionData['customer_info']['phone'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label>Company</label>
                                <input type="text" name="company_name" value="<?php echo htmlspecialchars($sessionData['customer_info']['companyName'] ?? ''); ?>">
                            </div>
                        </div>
                        
                        <button type="submit" class="btn">Update Customer Info</button>
                    </form>
                </div>
                
                <!-- Billing Address -->
                <div class="section">
                    <h2>Billing Address</h2>
                    <form method="POST">
                        <input type="hidden" name="action" value="update_billing">
                        <input type="hidden" name="csrf_token" value="<?php echo $sessionData['csrf_token']; ?>">
                        
                        <div class="form-group">
                            <label>Street Address *</label>
                            <input type="text" name="billing_street" value="<?php echo htmlspecialchars($sessionData['billing_address']['streetAddress'] ?? ''); ?>" required>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>City *</label>
                                <input type="text" name="billing_city" value="<?php echo htmlspecialchars($sessionData['billing_address']['city'] ?? ''); ?>" required>
                            </div>
                            <div class="form-group">
                                <label>State *</label>
                                <input type="text" name="billing_state" value="<?php echo htmlspecialchars($sessionData['billing_address']['stateName'] ?? ''); ?>" required>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>ZIP Code *</label>
                                <input type="text" name="billing_zip" value="<?php echo htmlspecialchars($sessionData['billing_address']['zip'] ?? ''); ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Country *</label>
                                <select name="billing_country" required>
                                    <option value="">Select Country</option>
                                    <option value="US" <?php echo ($sessionData['billing_address']['countryId'] ?? '') === 'US' ? 'selected' : ''; ?>>United States</option>
                                    <option value="CA" <?php echo ($sessionData['billing_address']['countryId'] ?? '') === 'CA' ? 'selected' : ''; ?>>Canada</option>
                                    <option value="GB" <?php echo ($sessionData['billing_address']['countryId'] ?? '') === 'GB' ? 'selected' : ''; ?>>United Kingdom</option>
                                </select>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn">Update Billing Address</button>
                    </form>
                </div>
                
                <!-- Coupon Code -->
                <div class="section">
                    <h2>Coupon Code</h2>
                    <form method="POST">
                        <input type="hidden" name="action" value="apply_coupon">
                        <input type="hidden" name="csrf_token" value="<?php echo $sessionData['csrf_token']; ?>">
                        <input type="hidden" name="tenant_id" value="<?php echo Config::get('DEFAULT_TENANT_ID'); ?>">
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>Coupon Code</label>
                                <input type="text" name="coupon_code" value="<?php echo htmlspecialchars($sessionData['coupon_code'] ?? ''); ?>" placeholder="Enter coupon code">
                            </div>
                            <div class="form-group" style="display: flex; align-items: end;">
                                <button type="submit" class="btn btn-secondary">Apply Coupon</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Sidebar -->
            <div class="checkout-sidebar">
                <h2>Order Summary</h2>
                
                <!-- Cart Items -->
                <div class="cart-items">
                    <?php if (empty($sessionData['cart_items'])): ?>
                        <p>Your cart is empty</p>
                    <?php else: ?>
                        <?php foreach ($sessionData['cart_items'] as $item): ?>
                            <div class="cart-item">
                                <div>
                                    <strong><?php echo htmlspecialchars($item['name']); ?></strong><br>
                                    <small>Qty: <?php echo $item['quantity']; ?> × $<?php echo number_format($item['price'], 2); ?></small>
                                </div>
                                <div>
                                    $<?php echo number_format($item['price'] * $item['quantity'], 2); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <!-- Order Totals -->
                <?php if (!empty($sessionData['cart_items'])): ?>
                    <div class="order-totals">
                        <div class="cart-item">
                            <span>Subtotal:</span>
                            <span>$<?php echo number_format($sessionData['subtotal'] ?? 0, 2); ?></span>
                        </div>
                        
                        <?php if (($sessionData['discount'] ?? 0) > 0): ?>
                            <div class="cart-item">
                                <span>Discount:</span>
                                <span>-$<?php echo number_format($sessionData['discount'], 2); ?></span>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (($sessionData['tax_amount'] ?? 0) > 0): ?>
                            <div class="cart-item">
                                <span>Tax:</span>
                                <span>$<?php echo number_format($sessionData['tax_amount'], 2); ?></span>
                            </div>
                        <?php endif; ?>
                        
                        <div class="cart-total">
                            Total: $<?php echo number_format($sessionData['total_amount'] ?? 0, 2); ?>
                        </div>
                    </div>
                    
                    <!-- Payment Buttons -->
                    <div class="payment-methods">
                        <form method="POST" style="margin: 0;">
                            <input type="hidden" name="action" value="submit_payment">
                            <input type="hidden" name="csrf_token" value="<?php echo $sessionData['csrf_token']; ?>">
                            <input type="hidden" name="payment_gateway" value="Stripe">
                            <input type="hidden" name="tenant_id" value="<?php echo Config::get('DEFAULT_TENANT_ID'); ?>">
                            <button type="submit" class="btn" style="width: 100%;">Pay with Stripe</button>
                        </form>
                        
                        <form method="POST" style="margin: 0;">
                            <input type="hidden" name="action" value="submit_payment">
                            <input type="hidden" name="csrf_token" value="<?php echo $sessionData['csrf_token']; ?>">
                            <input type="hidden" name="payment_gateway" value="PayPal">
                            <input type="hidden" name="tenant_id" value="<?php echo Config::get('DEFAULT_TENANT_ID'); ?>">
                            <button type="submit" class="btn btn-paypal" style="width: 100%;">Pay with PayPal</button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>