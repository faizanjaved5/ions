<?php
// test-sendotp-join.php - Put in /join/test-sendotp-join.php
session_start();

echo "<h1>Test SendOTP with Join Parameter</h1>";

// Test calling sendotp.php with from_join parameter
if (isset($_POST['email'])) {
    echo "<h2>Calling sendotp.php...</h2>";
    
    $_POST['from_join'] = true;
    
    ob_start();
    include __DIR__ . '/../login/sendotp.php';
    $response = ob_get_clean();
    
    echo "<h3>Response:</h3>";
    echo "<pre>" . htmlspecialchars($response) . "</pre>";
    
    $result = json_decode($response, true);
    if ($result) {
        echo "<h3>Parsed Result:</h3>";
        echo "<pre>" . print_r($result, true) . "</pre>";
    }
}
?>

<form method="POST">
    <p>Test email (use existing Creator/Member account): 
        <input type="email" name="email" value="smxchange@gmail.com" required>
        <button type="submit">Test SendOTP</button>
    </p>
</form>

<p><a href="/join/">Back to Join</a></p>