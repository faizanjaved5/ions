<?php
/**
 * Render a WordPress page inside the PHP site
 *
 * Routes:
 *   /app/render_wp_page.php?slug=about
 *   or wire this into your router:
 *       e.g., /pages/{slug} -> include render_wp_page.php with $_GET['slug'] = {slug}
 */

declare(strict_types=1);
require_once __DIR__ . '/wp_bridge.php';

$slug = isset($_GET['slug']) ? trim($_GET['slug'], " /") : '';
if ($slug === '') { $slug = 'home'; } // adjust your front page slug

$page = wpb_get_page_by_slug($slug);
if (!$page) {
    http_response_code(404);
    wpb_render_html_document('Not Found', '<h1>404 - Page not found</h1>');
    exit;
}

// Wrap with your site chrome if you like
$header = '<header style="padding:16px;border-bottom:1px solid #e5e7eb;"><a href="/">Home</a></header>';
$footer = '<footer style="padding:16px;border-top:1px solid #e5e7eb;">© ' . date('Y') . ' Your Company</footer>';

$body = $header .
        '<main style="max-width:920px;margin:24px auto;padding:0 16px">' .
        '<h1 style="margin:0 0 16px 0;">' . htmlspecialchars($page['title'] ?? '') . '</h1>' .
        ($page['html'] ?? '') .
        '</main>' .
        $footer;

wpb_render_html_document($page['title'] ?? 'Page', $body, ['base_href' => '/']);
