<?php

require_once __DIR__ . '/../config/config.php';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PHP Checkout MVP - Home</title>
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .container {
            background: white;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
            text-align: center;
            max-width: 600px;
            width: 90%;
        }
        
        .logo {
            width: 80px;
            height: 80px;
            background: #007bff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            color: white;
            font-size: 40px;
            font-weight: bold;
        }
        
        h1 {
            color: #2c3e50;
            margin-bottom: 15px;
            font-size: 2.5em;
        }
        
        .subtitle {
            color: #666;
            margin-bottom: 30px;
            font-size: 1.2em;
        }
        
        .features {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 30px 0;
            text-align: left;
        }
        
        .feature {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            border-left: 4px solid #007bff;
        }
        
        .feature h3 {
            color: #2c3e50;
            margin-bottom: 10px;
        }
        
        .feature p {
            color: #666;
            font-size: 14px;
        }
        
        .btn {
            background: #007bff;
            color: white;
            padding: 15px 30px;
            border: none;
            border-radius: 6px;
            text-decoration: none;
            display: inline-block;
            font-size: 16px;
            font-weight: 500;
            margin: 10px;
            transition: background 0.3s;
        }
        
        .btn:hover {
            background: #0056b3;
            transform: translateY(-2px);
        }
        
        .btn-secondary {
            background: #6c757d;
        }
        
        .status-info {
            background: #e3f2fd;
            border: 1px solid #2196f3;
            border-radius: 6px;
            padding: 15px;
            margin-top: 30px;
        }
        
        .status-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }
        
        .status-row:last-child {
            margin-bottom: 0;
        }
        
        .status-badge {
            background: #4caf50;
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 30px 20px;
            }
            
            h1 {
                font-size: 2em;
            }
            
            .features {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">
            PHP
        </div>
        
        <h1>PHP Checkout MVP</h1>
        <p class="subtitle">Extracted from Next.js Landing Page Project</p>
        
        <div class="features">
            <div class="feature">
                <h3>🛒 Cart Management</h3>
                <p>Add products, manage quantities, and calculate totals with session-based cart storage.</p>
            </div>
            
            <div class="feature">
                <h3>👤 Customer Forms</h3>
                <p>Collect and validate customer information with comprehensive form validation.</p>
            </div>
            
            <div class="feature">
                <h3>📍 Address Handling</h3>
                <p>Billing and shipping address collection with validation and formatting.</p>
            </div>
            
            <div class="feature">
                <h3>🎫 Coupon System</h3>
                <p>Apply and validate coupon codes with real-time discount calculation.</p>
            </div>
            
            <div class="feature">
                <h3>💳 Payment Gateway</h3>
                <p>Stripe and PayPal integration with secure payment processing.</p>
            </div>
            
            <div class="feature">
                <h3>🔒 Security</h3>
                <p>CSRF protection, input validation, and secure session management.</p>
            </div>
        </div>
        
        <div>
            <a href="checkout.php" class="btn">Start Checkout Demo</a>
            <a href="../README.md" class="btn btn-secondary">View Documentation</a>
        </div>
        
        <div class="status-info">
            <h3 style="margin-bottom: 15px; color: #1976d2;">System Status</h3>
            
            <div class="status-row">
                <span>PHP Version:</span>
                <span class="status-badge"><?php echo PHP_VERSION; ?></span>
            </div>
            
            <div class="status-row">
                <span>Environment:</span>
                <span class="status-badge"><?php echo Config::isDevelopment() ? 'Development' : 'Production'; ?></span>
            </div>
            
            <div class="status-row">
                <span>API Endpoint:</span>
                <span style="font-family: monospace; font-size: 12px;"><?php echo Config::API_URL; ?></span>
            </div>
            
            <div class="status-row">
                <span>Debug Mode:</span>
                <span class="status-badge" style="background: <?php echo Config::isDebugMode() ? '#ff9800' : '#4caf50'; ?>">
                    <?php echo Config::isDebugMode() ? 'Enabled' : 'Disabled'; ?>
                </span>
            </div>
            
            <div class="status-row">
                <span>Session Support:</span>
                <span class="status-badge"><?php echo session_status() !== PHP_SESSION_DISABLED ? 'Available' : 'Disabled'; ?></span>
            </div>
            
            <div class="status-row">
                <span>cURL Extension:</span>
                <span class="status-badge"><?php echo extension_loaded('curl') ? 'Loaded' : 'Missing'; ?></span>
            </div>
        </div>
    </div>
</body>
</html>
