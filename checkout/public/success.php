<?php

require_once __DIR__ . '/../classes/CheckoutManager.php';

// Get URL parameters
$tenantId = $_GET['tenant'] ?? '';
$invoiceId = $_GET['invoice'] ?? '';
$isMock = isset($_GET['mock']);
$gateway = $_GET['gateway'] ?? '';

// Detect payment method from URL parameters
if (isset($_GET['direct_stripe'])) {
    $gateway = 'Stripe';
} elseif (isset($_GET['direct_paypal'])) {
    $gateway = 'PayPal';
    $paypalOrderId = $_GET['order_id'] ?? '';
}

// Initialize checkout manager
$checkout = new CheckoutManager();

// Clear the checkout session since payment was successful
$checkout->clearSession();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Success - PHP Checkout MVP</title>
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
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }
        
        .success-container {
            background: white;
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            text-align: center;
            max-width: 500px;
            width: 90%;
        }
        
        .success-icon {
            width: 80px;
            height: 80px;
            background: #28a745;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            color: white;
            font-size: 40px;
        }
        
        h1 {
            color: #28a745;
            margin-bottom: 15px;
        }
        
        .success-message {
            margin-bottom: 30px;
            color: #666;
        }
        
        .order-details {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 4px;
            margin-bottom: 30px;
            text-align: left;
        }
        
        .detail-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }
        
        .btn {
            background: #007bff;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 4px;
            text-decoration: none;
            display: inline-block;
            font-size: 14px;
            font-weight: 500;
        }
        
        .btn:hover {
            background: #0056b3;
        }
        
        .btn-secondary {
            background: #6c757d;
            margin-left: 10px;
        }
    </style>
</head>
<body>
    <div class="success-container">
        <div class="success-icon">
            ✓
        </div>
        
        <h1>Payment Successful!</h1>
        
        <?php if ($isMock): ?>
            <div style="background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 4px; margin-bottom: 20px;">
                <strong>⚡ Mock Payment Mode</strong><br>
                This is a simulated payment for testing purposes. No actual payment was processed.
            </div>
        <?php endif; ?>
        
        <div class="success-message">
            <p>Thank you for your purchase. Your payment has been processed successfully<?php echo $isMock ? ' (simulated)' : ''; ?>.</p>
        </div>
        
        <?php if ($tenantId && $invoiceId): ?>
            <div class="order-details">
                <h3>Order Details</h3>
                <div class="detail-row">
                    <strong>Tenant ID:</strong>
                    <span><?php echo htmlspecialchars($tenantId); ?></span>
                </div>
                <div class="detail-row">
                    <strong>Invoice ID:</strong>
                    <span><?php echo htmlspecialchars($invoiceId); ?></span>
                </div>
                <div class="detail-row">
                    <strong>Date:</strong>
                    <span><?php echo date('M j, Y \a\t g:i A'); ?></span>
                </div>
                <?php if ($gateway): ?>
                    <div class="detail-row">
                        <strong>Payment Method:</strong>
                        <span><?php echo htmlspecialchars($gateway) . ($isMock ? ' (Mock)' : ''); ?></span>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <div class="success-message">
            <p>You will receive a confirmation email shortly with your order details and receipt.</p>
        </div>
        
        <a href="checkout.php" class="btn">Start New Order</a>
        <a href="../" class="btn btn-secondary">Return to Home</a>
    </div>
</body>
</html>