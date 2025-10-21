<?php
/**
 * Unsplash Image Loader - by Sperse
 * Returns array with 'image', 'alt', 'credit', and 'is_fallback'.
 * Last updated: 2025-04-21 07:30 UTC
 */

if (!defined('UNSPLASH_ACCESS_KEY')) {
    define('UNSPLASH_ACCESS_KEY', 't_LAGUeAFFVes0rDyk1Vf0WdHqEk2SXw-yLFR8c5CWI');
}
if (!defined('UNSPLASH_FALLBACK_IMAGE_ID')) {
    define('UNSPLASH_FALLBACK_IMAGE_ID', 'photo-1570577984924-88e3c5f14dab');
}

if (!function_exists('fetchUnsplashBackgroundImage')) {
    function fetchUnsplashBackgroundImage(string $city, ?string $state = null, ?string $country = null, bool $log = false): ?array {
        if (!defined('UNSPLASH_ACCESS_KEY') || empty(UNSPLASH_ACCESS_KEY)) {
            return fallbackImage('NoAccessKey', $log);
        }

        $accessKey = UNSPLASH_ACCESS_KEY;
        $normalize = fn($v) => (!empty($v) && !in_array(strtolower(trim($v)), ['n/a', 'none'])) ? trim($v) : null;

        $city = $normalize($city);
        $state = $normalize($state);
        $country = $normalize($country);
        $query = trim(implode(' ', array_filter([$city, $state, $country])));
        if (empty($query)) return fallbackImage('EmptyQuery', $log);

        $apiUrl = "https://api.unsplash.com/search/photos?query=" . urlencode($query) . "&per_page=1&orientation=landscape&client_id={$accessKey}";

        $context = stream_context_create(['http' => ['timeout' => 6]]);
        $response = @file_get_contents($apiUrl, false, $context);

        $ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
        $logTime = date('Y-m-d H:i:s');

        if ($log) {
            file_put_contents(__DIR__ . '/unsplash.log', "[{$logTime}] [{$ip}] Query: {$query}
URL: {$apiUrl}
Response: " . ($response ?: 'NULL') . "

", FILE_APPEND);
        }

        if (!$response) return fallbackImage($query, $log);

        $data = json_decode($response, true);
        if (!isset($data['results'][0]['urls']['full'])) return fallbackImage($query, $log);

        $photo = $data['results'][0];
        $imageUrl = $photo['urls']['full'] . "&w=2000&q=80&auto=format";
        $photographer = htmlspecialchars($photo['user']['name'] ?? 'Unknown');
        $photographerLink = htmlspecialchars($photo['user']['links']['html'] ?? 'https://unsplash.com');
        $photoPage = htmlspecialchars($photo['links']['html'] ?? 'https://unsplash.com');
        $alt = htmlspecialchars($photo['alt_description'] ?? "{$city} city view");

        if (!empty($photo['links']['download_location'])) {
            @file_get_contents($photo['links']['download_location'] . "?client_id={$accessKey}");
        }

        return [
            'image' => $imageUrl,
            'alt' => $alt,
            'credit' => 'Photo by <a href="' . $photographerLink . '" target="_blank" rel="noopener noreferrer">' . $photographer . '</a> on <a href="' . $photoPage . '" target="_blank" rel="noopener noreferrer">Unsplash</a>',
            'is_fallback' => false,
        ];
    }
}

if (!function_exists('fallbackImage')) {
    function fallbackImage(string $query, bool $log = false): array {
        if ($log) {
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
            $logTime = date('Y-m-d H:i:s');
            file_put_contents(__DIR__ . '/unsplash.log', "[{$logTime}] [{$ip}] FALLBACK triggered for query: {$query}
", FILE_APPEND);
        }

        $url = 'https://images.unsplash.com/' . UNSPLASH_FALLBACK_IMAGE_ID . '?q=80&w=2637&auto=format&fit=crop&w=2000&q=80';
        return [
            'image' => $url,
            'alt' => 'Fallback: ' . htmlspecialchars($query),
            'credit' => 'Photo by <a href="https://unsplash.com/photos/' . UNSPLASH_FALLBACK_IMAGE_ID . '" target="_blank" rel="noopener noreferrer">Default</a> on <a href="https://unsplash.com" target="_blank" rel="noopener noreferrer">Unsplash</a>',
            'is_fallback' => true,
        ];
    }
}
?>
