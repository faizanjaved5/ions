<?php
require_once __DIR__ . '/../config/cart.php';
require_once __DIR__ . '/classes/CheckoutOrchestrator.php';

// Initialize session
session_start();

$orchestrator = new CheckoutOrchestrator();
$errors = [];
$productData = null;
$selectedOption = null;
$availablePaymentMethods = [];

// Get parameters from URL
$tenantId = $_GET['tenantId'] ?? $_POST['tenantId'] ?? Config::get('DEFAULT_TENANT_ID');
$publicName = $_GET['publicName'] ?? $_POST['publicName'] ?? 'dnt';
$optionId = $_GET['optionId'] ?? $_POST['optionId'] ?? null;

// Handle product initialization
if ($tenantId && $publicName) {
    $result = $orchestrator->initializeProduct($tenantId, $publicName, $optionId);
    
    if ($result['success']) {
        $productData = $result['productData'];
        $selectedOption = $result['selectedOption'];
        $availablePaymentMethods = $result['availablePaymentMethods'];
    } else {
        $errors[] = $result['error'];
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $productData ? htmlspecialchars($productData['name']) : 'Product Selection'; ?> - Sperse Checkout</title>
    <link rel="stylesheet" href="templates/styles.css">
</head>
<body>
    <div class="container">
        <header class="checkout-header">
            <h1><?php echo $productData ? htmlspecialchars($productData['name']) : 'Product Selection'; ?></h1>
            <p class="subtitle">Powered by Sperse Multi-Tenant Platform</p>
        </header>
        
        <main class="checkout-main">
            <?php if (!empty($errors)): ?>
                <div class="error-container">
                    <h3>Error</h3>
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                    
                    <div class="help-section">
                        <h4>📝 Test with Example URLs:</h4>
                        <p><a href="?tenantId=36&publicName=gsm&optionId=370">• Tenant 36, Product GSM, Option 370</a></p>
                        <p><a href="?tenantId=<?php echo Config::get('DEFAULT_TENANT_ID'); ?>&publicName=dnt">• Default Tenant, Product DNT</a></p>
                        <p><strong>URL Structure:</strong> <code>?tenantId={id}&publicName={name}&optionId={option}</code></p>
                    </div>
                </div>
            <?php else: ?>
                <div class="product-container">
                    <div class="product-info">
                        <?php if (!empty($productData['imageUrl'])): ?>
                            <img src="<?php echo htmlspecialchars($productData['imageUrl']); ?>" 
                                 alt="<?php echo htmlspecialchars($productData['name']); ?>" 
                                 class="product-image">
                        <?php endif; ?>
                        
                        <h2><?php echo htmlspecialchars($productData['name']); ?></h2>
                        
                        <?php if (!empty($productData['description'])): ?>
                            <p class="product-description"><?php echo htmlspecialchars($productData['description']); ?></p>
                        <?php endif; ?>
                        
                        <div class="product-details">
                            <h4>Product Details:</h4>
                            <ul>
                                <li><strong>Type:</strong> <?php echo htmlspecialchars($productData['type']); ?></li>
                                <li><strong>Currency:</strong> <?php echo htmlspecialchars($productData['currencyId']); ?></li>
                                <li><strong>Tenant ID:</strong> <?php echo htmlspecialchars($tenantId); ?></li>
                                <li><strong>Public Name:</strong> <?php echo htmlspecialchars($publicName); ?></li>
                                <?php if ($optionId): ?>
                                    <li><strong>Option ID:</strong> <?php echo htmlspecialchars($optionId); ?></li>
                                <?php endif; ?>
                            </ul>
                        </div>
                    </div>
                    
                    <div class="product-options">
                        <h3>Price Options</h3>
                        
                        <form method="GET" action="" class="option-form">
                            <input type="hidden" name="tenantId" value="<?php echo htmlspecialchars($tenantId); ?>">
                            <input type="hidden" name="publicName" value="<?php echo htmlspecialchars($publicName); ?>">
                            
                            <?php foreach ($productData['priceOptions'] as $option): ?>
                                <div class="price-option <?php echo ($selectedOption && $selectedOption['id'] == $option['id']) ? 'selected' : ''; ?>"
                                     onclick="selectOption(<?php echo $option['id']; ?>)">
                                    <input type="radio" name="optionId" value="<?php echo $option['id']; ?>" 
                                           <?php echo ($selectedOption && $selectedOption['id'] == $option['id']) ? 'checked' : ''; ?>
                                           id="option_<?php echo $option['id']; ?>">
                                    
                                    <label for="option_<?php echo $option['id']; ?>" class="option-label">
                                        <div class="option-price">
                                            <?php echo $this->formatPrice($option['fee'], $productData['currencyId']); ?>
                                        </div>
                                        
                                        <?php if ($option['type'] === 'Subscription'): ?>
                                            <div class="option-frequency">
                                                / <?php echo htmlspecialchars($option['unit']); ?>
                                                <small>(<?php echo htmlspecialchars($option['frequency']); ?>)</small>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if ($option['signupFee'] > 0): ?>
                                            <div class="option-setup-fee">
                                                Setup Fee: <?php echo $this->formatPrice($option['signupFee'], $productData['currencyId']); ?>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if ($option['trialDayCount'] > 0): ?>
                                            <div class="option-trial">
                                                <?php echo $option['trialDayCount']; ?> day trial
                                            </div>
                                        <?php endif; ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                            
                            <button type="submit" class="btn btn-primary">Update Selection</button>
                        </form>
                        
                        <?php if ($selectedOption && !empty($availablePaymentMethods)): ?>
                            <div class="payment-methods">
                                <h3>Payment Methods</h3>
                                
                                <form method="POST" action="checkout.php" class="payment-form">
                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                    <input type="hidden" name="tenantId" value="<?php echo htmlspecialchars($tenantId); ?>">
                                    <input type="hidden" name="publicName" value="<?php echo htmlspecialchars($publicName); ?>">
                                    <input type="hidden" name="optionId" value="<?php echo htmlspecialchars($selectedOption['id']); ?>">
                                    
                                    <?php foreach ($availablePaymentMethods as $method): ?>
                                        <button type="submit" name="payment_gateway" value="<?php echo $method; ?>" 
                                                class="btn btn-payment btn-<?php echo strtolower($method); ?>">
                                            <?php
                                            switch ($method) {
                                                case 'Stripe':
                                                    echo '💳 Pay with Stripe';
                                                    break;
                                                case 'PayPal':
                                                    echo '🅿️ Pay with PayPal';
                                                    break;
                                                case 'Spreedly':
                                                    echo '💎 Pay with Spreedly';
                                                    break;
                                                default:
                                                    echo "Pay with {$method}";
                                            }
                                            ?>
                                        </button>
                                    <?php endforeach; ?>
                                </form>
                            </div>
                        <?php elseif ($selectedOption): ?>
                            <div class="no-payment-methods">
                                <h3>Payment Methods</h3>
                                <div class="error">
                                    <strong>No payment methods configured</strong><br>
                                    Please configure Stripe, PayPal, or Spreedly for this tenant.
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if (Config::isDebugMode() && $productData): ?>
                <div class="debug-info">
                    <h4>🔧 Debug Information</h4>
                    <div class="debug-section">
                        <strong>Available Payment Methods:</strong>
                        <pre><?php echo json_encode($availablePaymentMethods, JSON_PRETTY_PRINT); ?></pre>
                    </div>
                    <div class="debug-section">
                        <strong>Selected Option:</strong>
                        <pre><?php echo json_encode($selectedOption, JSON_PRETTY_PRINT); ?></pre>
                    </div>
                </div>
            <?php endif; ?>
        </main>
        
        <footer class="checkout-footer">
            <p>&copy; <?php echo date('Y'); ?> Powered by Sperse Platform</p>
        </footer>
    </div>
    
    <script>
        function selectOption(optionId) {
            const radio = document.querySelector(`input[value="${optionId}"]`);
            if (radio) {
                radio.checked = true;
                // Remove selected class from all options
                document.querySelectorAll('.price-option').forEach(el => el.classList.remove('selected'));
                // Add selected class to clicked option
                radio.closest('.price-option').classList.add('selected');
            }
        }
        
        // Auto-submit form when option changes
        document.addEventListener('change', function(e) {
            if (e.target.name === 'optionId') {
                e.target.closest('form').submit();
            }
        });
    </script>
</body>
</html>

<?php
// Helper function for price formatting
function formatPrice($price, $currencyId = 'USD') {
    $symbol = $currencyId === 'USD' ? '$' : $currencyId . ' ';
    return $symbol . number_format($price, 2);
}
?>
