<?php
/**
 * Pull Page - Proxy/Wrapper for External URLs
 * Fetches content from a URL parameter and displays it with ION navbar
 */

// Enable error reporting for debugging (but suppress Simple HTML DOM library warnings)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL & ~E_WARNING);

// Include Simple HTML DOM Parser
require_once __DIR__ . '/simple-html-dom/simple_html_dom.php';

// Simple HTML DOM functions are available globally
// No namespace imports needed for this version

// Get the URL from the query parameter
$targetUrl = isset($_GET['url']) ? $_GET['url'] : '';

// Validate the URL
if (empty($targetUrl)) {
    die('Error: No URL provided. Please specify a URL using the ?url= parameter.');
}

// Validate URL format
if (!filter_var($targetUrl, FILTER_VALIDATE_URL)) {
    die('Error: Invalid URL format.');
}

// Parse the URL to check the scheme
$urlParts = parse_url($targetUrl);
if (!isset($urlParts['scheme']) || !in_array(strtolower($urlParts['scheme']), ['http', 'https'])) {
    die('Error: Only HTTP and HTTPS protocols are allowed.');
}

// Initialize cURL session
$ch = curl_init();

// Set cURL options
curl_setopt_array($ch, [
    CURLOPT_URL => $targetUrl,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_MAXREDIRS => 5,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_SSL_VERIFYPEER => false, // Disable SSL verification for easier testing
    CURLOPT_SSL_VERIFYHOST => 0,
    CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
    CURLOPT_HEADER => true,
    CURLOPT_ENCODING => '', // Handle all encodings
]);

// Execute the request
$response = curl_exec($ch);

// Check for cURL errors
if (curl_errno($ch)) {
    $error = curl_error($ch);
    curl_close($ch);
    die('Error fetching URL: ' . htmlspecialchars($error));
}

// Get response info
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);

curl_close($ch);

// Check HTTP status code
if ($httpCode >= 400) {
    die('Error: The requested page returned HTTP ' . $httpCode);
}

// Separate headers and body
$headers = substr($response, 0, $headerSize);
$body = substr($response, $headerSize);


// Process the content based on content type
if (strpos($contentType, 'text/html') !== false) {
    // It's HTML content - modify it
    
    // Add base tag to handle relative URLs
    $baseUrl = $urlParts['scheme'] . '://' . $urlParts['host'];
    if (isset($urlParts['port'])) {
        $baseUrl .= ':' . $urlParts['port'];
    }
    
    // ============================================
    // STEP 1: AGGRESSIVE REMOVAL - Clean HTML BEFORE parsing
    // ============================================
    // Using regex is more reliable than DOM methods for complete removal
    
    // 1. Remove ALL ION navigation tags (we'll inject our own later)
    // Run multiple times to catch all instances (sometimes nested or multiple)
    do {
        $before = $body;
        $body = preg_replace('/<nav\s+[^>]*class="[^"]*ion-navigation[^"]*"[^>]*>.*?<\/nav>/is', '', $body);
    } while ($before !== $body);
    
    // 2. Remove mobile menu (complete div block with ALL nested content)
    // Mobile menu has deeply nested divs, so we need to properly balance tags
    $mobileMenuStart = stripos($body, '<div id="ionMobileMenu"');
    if ($mobileMenuStart !== false) {
        // Find the start of the opening tag
        $openTagEnd = strpos($body, '>', $mobileMenuStart);
        if ($openTagEnd !== false) {
            // Count divs to find the matching closing tag
            $depth = 1;
            $pos = $openTagEnd + 1;
            $len = strlen($body);
            
            while ($depth > 0 && $pos < $len) {
                // Find next <div or </div
                $nextOpen = stripos($body, '<div', $pos);
                $nextClose = stripos($body, '</div>', $pos);
                
                if ($nextClose === false) break; // No more closing tags
                
                if ($nextOpen !== false && $nextOpen < $nextClose) {
                    // Found opening tag first
                    $depth++;
                    $pos = $nextOpen + 4;
                } else {
                    // Found closing tag first
                    $depth--;
                    $pos = $nextClose + 6;
                    
                    if ($depth === 0) {
                        // Found the matching closing tag
                        $mobileMenuEnd = $pos;
                        // Remove the entire mobile menu block
                        $body = substr($body, 0, $mobileMenuStart) . substr($body, $mobileMenuEnd);
                        break;
                    }
                }
            }
        }
    }
    
    // 3. Remove mega menu
    $body = preg_replace('/<div\s+[^>]*id="ionMegaMenu"[^>]*>.*?<\/div>/is', '', $body);
    
    // 4. Remove ALL remaining <nav> tags
    $body = preg_replace('/<nav[^>]*>.*?<\/nav>/is', '', $body);
    
    // 5. Remove scripts containing ION menu functions (must be aggressive)
    $body = preg_replace('/<script[^>]*>.*?(?:showMegaMenu|toggleMobileMenu|ionMenuData|openSearch|closeSearch).*?<\/script>/is', '', $body);
    
    // 6. Remove menu.css link from external site
    $body = preg_replace('/<link[^>]*href="[^"]*menu\.css"[^>]*>/is', '', $body);
    
    // 7. Remove any header tags that might contain navigation
    $body = preg_replace('/<header[^>]*>.*?<\/header>/is', '', $body);
    
    // ============================================
    // STEP 2: Generate ION Navbar Components and Inject (BEFORE parsing)
    // ============================================
    // Using regex for injection is more reliable than DOM manipulation
    
    // Generate navbar HTML and scripts separately
    ob_start();
    require __DIR__ . '/includes/navbar.php';
    $ionNavbarHTML = ob_get_clean();
    
    // Generate head injection (CSS and meta tags)
    $headInjection = '
    <link rel="stylesheet" href="/ion/css/menu.css">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta charset="UTF-8">
    ';
    
    // Inject into HEAD using regex (right after <head> tag)
    $body = preg_replace('/(<head[^>]*>)/is', '$1' . $headInjection, $body, 1);
    
    // Inject navbar HTML into BODY (right after <body> tag)
    $body = preg_replace('/(<body[^>]*>)/is', '$1' . $ionNavbarHTML, $body, 1);
    
    // Parse HTML with Simple HTML DOM after cleaning and injecting navbar
    $html = str_get_html($body);
    
    if ($html) {
        // ============================================
        // STEP 3: Final DOM-based cleanup (catch anything regex missed)
        // ============================================
        
        // Final pass to remove any stragglers using DOM methods
        // EXCEPT our navbar (has 'our-own-navbar' class)
        // Remove only external site navigation <nav> elements while preserving
        // our injected navbar which has class 'ourownnavigation'.
        foreach ($html->find('nav') as $element) {
            $classes = $element->class ?? '';
            if (strpos($classes, 'ourownnavigation') === false) {
                $element->outertext = '';
            }
        }
        
        // Remove any remaining scripts with ION functions (except our navbar scripts)
        foreach($html->find('script') as $script) {
            $scriptContent = $script->innertext;
            // Skip if it's inside our navbar
            $parent = $script->parent();
            $isOurNavbarScript = false;
            while ($parent) {
                if (strpos($parent->class ?? '', 'our-own-navbar') !== false) {
                    $isOurNavbarScript = true;
                    break;
                }
                $parent = $parent->parent();
            }
            
            if (!$isOurNavbarScript && 
                (strpos($scriptContent, 'showMegaMenu') !== false || 
                 strpos($scriptContent, 'toggleMobileMenu') !== false ||
                 strpos($scriptContent, 'ionMenuData') !== false ||
                 strpos($scriptContent, 'openSearch') !== false)) {
                $script->outertext = '';
            }
        }
        
        // ============================================
        // STEP 3: Final cleanup using DOM (head already injected via regex)
        // ============================================
        // No need for head manipulation - already done via regex above
        
        // Get the modified HTML
        $body = $html->save();
        $html->clear();
        unset($html);
        
        // Ensure navbar scripts are properly included at the end of body
        $navbarScriptPath = __DIR__ . '/includes/navbar_scripts.php';
        if (file_exists($navbarScriptPath)) {
            // Capture the output of the PHP script instead of reading file contents
            ob_start();
            include $navbarScriptPath;
            $navbarScripts = ob_get_clean();
            // Inject scripts before closing </body> tag
            $body = preg_replace('/(<\/body>)/is', $navbarScripts . '$1', $body, 1);
        }
        
    } else {
        // Fallback if parsing fails
        die('Error: Unable to parse HTML content');
    }
    
    // Set proper content type
    header('Content-Type: text/html; charset=utf-8');
    
    // Output the modified HTML
    echo $body;
    
} else {
    // For non-HTML content, just pass it through
    if ($contentType) {
        header('Content-Type: ' . $contentType);
    }
    echo $body;
}