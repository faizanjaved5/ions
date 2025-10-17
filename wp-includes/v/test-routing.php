<?php
/**
 * Test script to verify .htaccess routing is working
 * Access via: /v/test123 or /v/test123-some-description
 */

echo "<h2>🧪 Shortlink Routing Test</h2>\n";
echo "<pre>\n";

echo "Request URI: " . ($_SERVER['REQUEST_URI'] ?? 'not set') . "\n";
echo "Query String: " . ($_SERVER['QUERY_STRING'] ?? 'empty') . "\n";
echo "GET Parameters:\n";
print_r($_GET);

echo "\nURL Analysis:\n";
$request_path = $_SERVER['REQUEST_URI'];
echo "Full path: $request_path\n";

// Test regex pattern from .htaccess
if (preg_match('/^\/v\/([a-zA-Z0-9]+)(?:-.*)?/', $request_path, $matches)) {
    echo "✅ Regex match found: " . $matches[1] . "\n";
} else {
    echo "❌ No regex match\n";
}

// Test what resolver would get
$slug = $_GET['link'] ?? '';
if (empty($slug)) {
    if (preg_match('#/v/([^/?]+)#', $request_path, $matches)) {
        $slug = $matches[1];
    }
}

echo "\nResolver would get: '$slug'\n";

// Test shortcode extraction
if (!empty($slug)) {
    if (preg_match('/^([a-zA-Z0-9]{6,8})(?:-.*)?$/', $slug, $matches)) {
        echo "✅ Shortcode extracted: " . $matches[1] . "\n";
    } else {
        echo "❌ Invalid shortcode format\n";
    }
} else {
    echo "❌ No slug found\n";
}

echo "\n</pre>\n";

echo "<p><strong>Test URLs (case-insensitive):</strong></p>\n";
echo "<ul>\n";
echo "<li><a href='/v/abc123'>/v/abc123</a> (lowercase)</li>\n";
echo "<li><a href='/v/ABC123'>/v/ABC123</a> (uppercase)</li>\n";
echo "<li><a href='/v/AbC123'>/v/AbC123</a> (mixed case)</li>\n";
echo "<li><a href='/v/abc123-test-video'>/v/abc123-test-video</a> (with suffix)</li>\n";
echo "<li><a href='/v/XyZ789-another-video-title'>/v/XyZ789-another-video-title</a> (mixed case with suffix)</li>\n";
echo "</ul>\n";
echo "<p><em>Note: All variations should resolve to the same shortlink due to case-insensitive handling.</em></p>\n";
?>
