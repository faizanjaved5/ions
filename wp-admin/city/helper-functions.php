<?php
/**
 * Helper functions to replace WordPress dependencies
 * For use with standalone ION city pages
 * Enhanced with video handling utilities
 */

// WordPress replacement functions - only declare if they don't exist
if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($str) {
        if (is_array($str)) {
            return array_map('sanitize_text_field', $str);
        }
        return trim(strip_tags($str));
    }
}

if (!function_exists('wp_send_json')) {
    function wp_send_json($data) {
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
}

if (!function_exists('wp_send_json_success')) {
    function wp_send_json_success($data = null) {
        $response = ['success' => true];
        if ($data !== null) {
            $response['data'] = $data;
        }
        wp_send_json($response);
    }
}

if (!function_exists('wp_send_json_error')) {
    function wp_send_json_error($data = null) {
        $response = ['success' => false];
        if ($data !== null) {
            $response['data'] = $data;
        }
        wp_send_json($response);
    }
}

if (!function_exists('current_time')) {
    function current_time($format) {
        if ($format === 'mysql') {
            return date('Y-m-d H:i:s');
        }
        return date($format);
    }
}

if (!function_exists('wp_remote_get')) {
    function wp_remote_get($url, $args = []) {
        $context_options = [
            'http' => [
                'method' => 'GET',
                'header' => [
                    'User-Agent: ION City Bot/1.0',
                    'Accept: application/json, text/html, */*'
                ],
                'timeout' => $args['timeout'] ?? 30,
                'ignore_errors' => true
            ]
        ];
        
        if (isset($args['headers'])) {
            foreach ($args['headers'] as $key => $value) {
                $context_options['http']['header'][] = "$key: $value";
            }
        }
        
        $context = stream_context_create($context_options);
        $response = @file_get_contents($url, false, $context);
        
        if ($response === false) {
            return new WP_Error('http_request_failed', 'Request failed');
        }
        
        // Get HTTP response code
        $http_code = 200;
        if (isset($http_response_header)) {
            foreach ($http_response_header as $header) {
                if (preg_match('/^HTTP\/\d\.\d\s+(\d+)/', $header, $matches)) {
                    $http_code = intval($matches[1]);
                    break;
                }
            }
        }
        
        return [
            'response' => ['code' => $http_code],
            'body' => $response
        ];
    }
}

if (!function_exists('wp_remote_retrieve_body')) {
    function wp_remote_retrieve_body($response) {
        if (is_wp_error($response)) {
            return '';
        }
        return $response['body'] ?? '';
    }
}

if (!function_exists('wp_remote_retrieve_response_code')) {
    function wp_remote_retrieve_response_code($response) {
        if (is_wp_error($response)) {
            return 0;
        }
        return $response['response']['code'] ?? 0;
    }
}

if (!function_exists('is_wp_error')) {
    function is_wp_error($thing) {
        return $thing instanceof WP_Error;
    }
}

// Simple transient replacement using file cache
if (!function_exists('get_transient')) {
    function get_transient($key) {
        $cache_dir = __DIR__ . '/../cache/';
        $file = $cache_dir . md5($key) . '.cache';
        
        if (file_exists($file)) {
            $data = unserialize(file_get_contents($file));
            if (is_array($data) && isset($data['expires']) && $data['expires'] > time()) {
                return $data['value'];
            } else {
                @unlink($file);
            }
        }
        return false;
    }
}

if (!function_exists('set_transient')) {
    function set_transient($key, $value, $expiration) {
        $cache_dir = __DIR__ . '/../cache/';
        if (!is_dir($cache_dir)) {
            mkdir($cache_dir, 0755, true);
        }
        
        $file = $cache_dir . md5($key) . '.cache';
        $data = [
            'value' => $value,
            'expires' => time() + $expiration
        ];
        return file_put_contents($file, serialize($data)) !== false;
    }
}

if (!function_exists('delete_transient')) {
    function delete_transient($key) {
        $cache_dir = __DIR__ . '/../cache/';
        $file = $cache_dir . md5($key) . '.cache';
        if (file_exists($file)) {
            return @unlink($file);
        }
        return false;
    }
}

// Template functions
if (!function_exists('get_template_directory')) {
    function get_template_directory() {
        return __DIR__;
    }
}

if (!function_exists('get_template_directory_uri')) {
    function get_template_directory_uri() {
        return get_site_url() . '/city';
    }
}

// Feed functions (simplified)
if (!function_exists('fetch_feed')) {
    function fetch_feed($url) {
        return new SimplePie_Feed($url);
    }
}

// Simplified SimplePie replacement for RSS feeds
if (!class_exists('SimplePie_Feed')) {
    class SimplePie_Feed {
        private $url;
        private $items = [];
        private $error = null;
        
        public function __construct($url) {
            $this->url = $url;
            $this->fetch();
        }
        
        private function fetch() {
            $response = wp_remote_get($this->url);
            if (is_wp_error($response)) {
                $this->error = $response;
                return;
            }
            
            $xml_string = wp_remote_retrieve_body($response);
            if (empty($xml_string)) {
                $this->error = new WP_Error('empty_feed', 'Empty feed response');
                return;
            }
            
            $xml = @simplexml_load_string($xml_string);
            if ($xml === false) {
                $this->error = new WP_Error('invalid_xml', 'Invalid XML in feed');
                return;
            }
            
            // Parse RSS items
            if (isset($xml->channel->item)) {
                foreach ($xml->channel->item as $item) {
                    $this->items[] = new SimplePie_Item($item);
                }
            }
        }
        
        public function get_item_quantity($limit = 0) {
            return $limit ? min($limit, count($this->items)) : count($this->items);
        }
        
        public function get_items($start = 0, $limit = 0) {
            if ($limit) {
                return array_slice($this->items, $start, $limit);
            }
            return array_slice($this->items, $start);
        }
        
        public function __destruct() {
            // Cleanup
        }
    }
}

if (!class_exists('SimplePie_Item')) {
    class SimplePie_Item {
        private $data;
        
        public function __construct($xml_item) {
            $this->data = $xml_item;
        }
        
        public function get_title() {
            return (string) $this->data->title;
        }
        
        public function get_permalink() {
            return (string) $this->data->link;
        }
        
        public function get_description() {
            return (string) $this->data->description;
        }
        
        public function get_date($format = 'U') {
            $date = (string) $this->data->pubDate;
            $timestamp = strtotime($date);
            
            if ($format === 'U') {
                return $timestamp;
            }
            return date($format, $timestamp);
        }
        
        public function get_source() {
            // Simplified - would need more complex parsing for real RSS
            return null;
        }
        
        public function get_enclosure() {
            // Simplified - would need to parse enclosures
            return null;
        }
    }
}

// WordPress Error class replacement
if (!class_exists('WP_Error')) {
    class WP_Error {
        private $errors = [];
        private $error_data = [];
        
        public function __construct($code = '', $message = '', $data = '') {
            if (!empty($code)) {
                $this->errors[$code][] = $message;
                if (!empty($data)) {
                    $this->error_data[$code] = $data;
                }
            }
        }
        
        public function get_error_message($code = '') {
            if (empty($code)) {
                $code = $this->get_error_code();
            }
            
            if (isset($this->errors[$code])) {
                return $this->errors[$code][0];
            }
            
            return '';
        }
        
        public function get_error_code() {
            if (empty($this->errors)) {
                return '';
            }
            
            return array_keys($this->errors)[0];
        }
        
        public function add($code, $message, $data = '') {
            $this->errors[$code][] = $message;
            if (!empty($data)) {
                $this->error_data[$code] = $data;
            }
        }
    }
}

// Constants that might be used
if (!defined('HOUR_IN_SECONDS')) {
    define('HOUR_IN_SECONDS', 3600);
}

if (!defined('DAY_IN_SECONDS')) {
    define('DAY_IN_SECONDS', 86400);
}

if (!defined('WEEK_IN_SECONDS')) {
    define('WEEK_IN_SECONDS', 604800);
}

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/../');
}

if (!defined('WPINC')) {
    define('WPINC', '/wp-includes');
}

// Additional helper functions that may be needed
if (!function_exists('sanitize_title')) {
    function sanitize_title($title) {
        $title = strip_tags($title);
        $title = preg_replace('/[^a-zA-Z0-9\s\-_]/', '', $title);
        $title = preg_replace('/[\s_]+/', '-', $title);
        $title = trim($title, '-');
        return strtolower($title);
    }
}

if (!function_exists('get_site_url')) {
    function get_site_url() {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $protocol . '://' . $host;
    }
}

if (!function_exists('home_url')) {
    function home_url($path = '') {
        return get_site_url() . '/' . ltrim($path, '/');
    }
}

if (!function_exists('esc_html')) {
    function esc_html($text) {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_attr')) {
    function esc_attr($text) {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_url')) {
    function esc_url($url) {
        return filter_var($url, FILTER_SANITIZE_URL);
    }
}

if (!function_exists('get_query_var')) {
    function get_query_var($var) {
        return $GLOBALS[$var] ?? '';
    }
}

if (!function_exists('set_query_var')) {
    function set_query_var($var, $value) {
        $GLOBALS[$var] = $value;
    }
}

// Permalink functions
if (!function_exists('get_permalink')) {
    function get_permalink($id = null) {
        // For city pages, return the current URL or construct from city data
        global $city;
        if ($city && isset($city->slug)) {
            return home_url('/' . $city->slug);
        }
        return home_url();
    }
}

// Error logging
if (!function_exists('error_log_custom')) {
    function error_log_custom($message, $file = null) {
        $log_file = $file ?: __DIR__ . '/../error.log';
        $timestamp = date('Y-m-d H:i:s');
        file_put_contents($log_file, "[$timestamp] $message\n", FILE_APPEND);
    }
}

// JSON response headers
if (!function_exists('send_json_headers')) {
    function send_json_headers() {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-cache, must-revalidate');
        header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
    }
}

// Get footer (simplified)
if (!function_exists('get_footer')) {
    function get_footer() {
        $footer_file = __DIR__ . '/footer.php';
        if (file_exists($footer_file)) {
            include $footer_file;
        }
    }
}

// No-cache headers
if (!function_exists('nocache_headers')) {
    function nocache_headers() {
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
    }
}

// Get 404 template
if (!function_exists('get_404_template')) {
    function get_404_template() {
        return __DIR__ . '/404.php';
    }
}

// Video helper functions
if (!function_exists('get_video_mime_type')) {
    function get_video_mime_type($extension) {
        $mime_types = [
            'mp4' => 'video/mp4',
            'webm' => 'video/webm',
            'ogg' => 'video/ogg',
            'ogv' => 'video/ogg',
            'mov' => 'video/quicktime',
            'avi' => 'video/x-msvideo',
            'm4v' => 'video/x-m4v',
            'mkv' => 'video/x-matroska',
            'wmv' => 'video/x-ms-wmv',
            'flv' => 'video/x-flv'
        ];
        
        $ext = strtolower($extension);
        return $mime_types[$ext] ?? 'video/mp4';
    }
}

if (!function_exists('is_video_url')) {
    function is_video_url($url) {
        $video_extensions = ['mp4', 'webm', 'ogg', 'ogv', 'mov', 'avi', 'm4v', 'mkv', 'wmv', 'flv'];
        $ext = strtolower(pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
        return in_array($ext, $video_extensions);
    }
}

if (!function_exists('get_video_platform')) {
    function get_video_platform($url) {
        if (strpos($url, 'youtube.com') !== false || strpos($url, 'youtu.be') !== false) {
            return 'youtube';
        } elseif (strpos($url, 'vimeo.com') !== false) {
            return 'vimeo';
        } elseif (strpos($url, 'muvi.com') !== false) {
            return 'muvi';
        } elseif (strpos($url, 'wistia.com') !== false || strpos($url, 'wi.st') !== false) {
            return 'wistia';
        } elseif (strpos($url, 'rumble.com') !== false) {
            return 'rumble';
        } elseif (is_video_url($url)) {
            return 'local';
        }
        return 'unknown';
    }
}

if (!function_exists('extract_youtube_id')) {
    function extract_youtube_id($url) {
        $pattern = '/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/';
        preg_match($pattern, $url, $matches);
        return $matches[1] ?? '';
    }
}

if (!function_exists('extract_vimeo_id')) {
    function extract_vimeo_id($url) {
        $parts = explode('/', trim(parse_url($url, PHP_URL_PATH), '/'));
        return end($parts);
    }
}

if (!function_exists('extract_wistia_id')) {
    function extract_wistia_id($url) {
        // Wistia URLs: https://company.wistia.com/medias/abc123xyz
        // Short URLs: https://wi.st/abc123xyz
        // Embed URLs: https://fast.wistia.net/embed/iframe/abc123xyz
        if (preg_match('/(?:wistia\.com\/medias\/|wi\.st\/|wistia\.net\/embed\/iframe\/)([a-zA-Z0-9]+)/', $url, $matches)) {
            return $matches[1];
        }
        return '';
    }
}

if (!function_exists('extract_rumble_id')) {
    function extract_rumble_id($url) {
        // Rumble URLs: https://rumble.com/v123abc-title.html
        // Embed URLs: https://rumble.com/embed/v123abc/
        if (preg_match('/rumble\.com\/(?:embed\/)?v([a-zA-Z0-9]+)/', $url, $matches)) {
            return $matches[1];
        }
        return '';
    }
}

// YouTube API keys (add your keys here)
if (!defined('YOUTUBE_API_KEYS')) {
    $youtube_api_keys = [
        'AIzaSyBYkfMlAW_a0Xhln7TQaZb8sGWwA5UxSqc',
        'AIzaSyCg8FjrD_XV4GjKS1KQUyIsD6t0U9hNLBg',
        'AIzaSyB1cJdGdB0vgbJN2e-SZ_R0aKGaNI0vLzI'
    ];
    define('YOUTUBE_API_KEYS', $youtube_api_keys);
}

// Make YouTube API keys available globally
if (!isset($youtube_api_keys)) {
    $youtube_api_keys = YOUTUBE_API_KEYS;
}

// Video thumbnail generation
if (!function_exists('get_video_thumbnail')) {
    function get_video_thumbnail($url, $type = null) {
        if (!$type) {
            $type = get_video_platform($url);
        }
        
        switch ($type) {
            case 'youtube':
                $video_id = extract_youtube_id($url);
                if ($video_id) {
                    return "https://img.youtube.com/vi/{$video_id}/mqdefault.jpg";
                }
                break;
                
            case 'vimeo':
                $video_id = extract_vimeo_id($url);
                if ($video_id) {
                    // Note: This requires an API call in real implementation
                    return "https://vumbnail.com/{$video_id}.jpg";
                }
                break;
        }
        
        // Default thumbnail
        return 'https://iblog.bz/assets/ionthumbnail.png';
    }
}

// Clean video URL for embedding
if (!function_exists('clean_video_url')) {
    function clean_video_url($url) {
        // Remove query parameters that might interfere with embedding
        $url = strtok($url, '?');
        
        // Add back necessary parameters for specific platforms
        $platform = get_video_platform($url);
        
        if ($platform === 'youtube') {
            $video_id = extract_youtube_id($url);
            if ($video_id) {
                return "https://www.youtube.com/embed/{$video_id}";
            }
        } elseif ($platform === 'vimeo') {
            $video_id = extract_vimeo_id($url);
            if ($video_id) {
                return "https://player.vimeo.com/video/{$video_id}";
            }
        }
        
        return $url;
    }
}

// Generate Video.js compatible source tags
if (!function_exists('generate_video_sources')) {
    function generate_video_sources($url) {
        $ext = strtolower(pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
        $mime_type = get_video_mime_type($ext);
        
        $sources = "<source src=\"" . esc_url($url) . "\" type=\"" . esc_attr($mime_type) . "\">\n";
        
        // Add alternative formats if they exist (you'd need to check for these)
        $base_url = preg_replace('/\.[^.]+$/', '', $url);
        $alt_formats = ['mp4', 'webm', 'ogg'];
        
        foreach ($alt_formats as $format) {
            if ($format !== $ext) {
                $alt_url = $base_url . '.' . $format;
                // In a real implementation, you'd check if this file exists
                // $sources .= "<source src=\"" . esc_url($alt_url) . "\" type=\"" . esc_attr(get_video_mime_type($format)) . "\">\n";
            }
        }
        
        return $sources;
    }
}

// Validate video URL
if (!function_exists('validate_video_url')) {
    function validate_video_url($url) {
        // Basic URL validation
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }
        
        // Check if it's a supported platform or video file
        $platform = get_video_platform($url);
        return $platform !== 'unknown';
    }
}

// Get video embed URL
if (!function_exists('get_video_embed_url')) {
    function get_video_embed_url($url, $autoplay = false, $muted = false, $loop = false) {
        $platform = get_video_platform($url);
        $params = [];
        
        switch ($platform) {
            case 'youtube':
                $video_id = extract_youtube_id($url);
                if (!$video_id) return $url;
                
                $embed_url = "https://www.youtube.com/embed/{$video_id}";
                if ($autoplay) $params[] = 'autoplay=1';
                if ($muted) $params[] = 'mute=1';
                if ($loop) $params[] = "loop=1&playlist={$video_id}";
                $params[] = 'rel=0';
                $params[] = 'modestbranding=1';
                break;
                
            case 'vimeo':
                $video_id = extract_vimeo_id($url);
                if (!$video_id) return $url;
                
                $embed_url = "https://player.vimeo.com/video/{$video_id}";
                if ($autoplay) $params[] = 'autoplay=1';
                if ($muted) $params[] = 'muted=1';
                if ($loop) $params[] = 'loop=1';
                $params[] = 'title=0';
                $params[] = 'byline=0';
                $params[] = 'portrait=0';
                break;
                
            case 'muvi':
                // Extract Muvi ID from URL
                $path_parts = explode('/', trim(parse_url($url, PHP_URL_PATH), '/'));
                $embed_pos = array_search('embed', $path_parts);
                if ($embed_pos !== false && isset($path_parts[$embed_pos + 1])) {
                    $video_id = $path_parts[$embed_pos + 1];
                } else {
                    $video_id = end($path_parts);
                }
                $embed_url = "https://embed.muvi.com/embed/{$video_id}";
                break;
                
            case 'wistia':
                $video_id = extract_wistia_id($url);
                if (!$video_id) return $url;
                
                $embed_url = "https://fast.wistia.net/embed/iframe/{$video_id}";
                if ($autoplay) $params[] = 'autoplay=1';
                if ($muted) $params[] = 'muted=1';
                $params[] = 'controls=1';
                break;
                
            case 'rumble':
                $video_id = extract_rumble_id($url);
                if (!$video_id) return $url;
                
                $embed_url = "https://rumble.com/embed/v{$video_id}/";
                if ($autoplay) $params[] = 'autoplay=1';
                break;
                
            default:
                return $url;
        }
        
        if (!empty($params)) {
            $embed_url .= '?' . implode('&', $params);
        }
        
        return $embed_url;
    }
}
?>