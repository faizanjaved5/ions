<?php
// This file just calls the original sendotp.php with a flag to bypass role checking for join flow

// Set a flag to indicate this is from join flow
$_POST['from_join'] = true;

// Include the original sendotp.php
require_once __DIR__ . '/../login/sendotp.php';
?>