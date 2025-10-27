<?php
/**
 * Video Preview Helper
 * Generates preview URLs for hover autoplay across all video types
 * Used consistently across all pages (search, profile, ioncity, iontopic, creators, etc.)
 */

function generateVideoPreviewUrl($videoType, $videoId, $videoUrl = '', $optimizedUrl = '', $hlsUrl = '') {
    $videoType = strtolower(trim($videoType));
    $videoId = trim($videoId);
    
    // YouTube
    if ($videoType === 'youtube' && !empty($videoId)) {
        return 'https://www.youtube.com/embed/' . htmlspecialchars($videoId) . '?autoplay=1&mute=1&controls=0&loop=1&playlist=' . htmlspecialchars($videoId);
    }
    
    // Vimeo
    if ($videoType === 'vimeo' && !empty($videoId)) {
        return 'https://player.vimeo.com/video/' . htmlspecialchars($videoId) . '?autoplay=1&muted=1&background=1';
    }
    
    // Wistia
    if ($videoType === 'wistia' && !empty($videoId)) {
        return 'https://fast.wistia.net/embed/iframe/' . htmlspecialchars($videoId) . '?autoplay=1&muted=1&controls=0';
    }
    
    // Rumble
    if ($videoType === 'rumble' && !empty($videoId)) {
        return 'https://rumble.com/embed/v' . htmlspecialchars($videoId) . '/?autoplay=1&muted=1';
    }
    
    // Muvi
    if ($videoType === 'muvi' && !empty($videoId)) {
        return 'https://embed.muvi.com/embed/' . htmlspecialchars($videoId) . '?autoplay=1&muted=1';
    }
    
    // Loom
    if ($videoType === 'loom' && !empty($videoId)) {
        return 'https://www.loom.com/embed/' . htmlspecialchars($videoId) . '?autoplay=1&muted=1&hide_owner=true&hide_share=true&hide_title=true&hideEmbedTopBar=true';
    }
    
    // Local/Uploaded/R2 videos
    if (in_array($videoType, ['local', 'upload', 'r2', 'self-hosted'])) {
        // Priority order: optimized_url, video_link, hls_manifest_url
        if (!empty($optimizedUrl)) {
            return 'local:' . htmlspecialchars($optimizedUrl);
        } elseif (!empty($videoUrl)) {
            return 'local:' . htmlspecialchars($videoUrl);
        } elseif (!empty($hlsUrl)) {
            return 'local:' . htmlspecialchars($hlsUrl);
        }
    }
    
    // If videoId is empty but we have a URL, try to extract ID
    if (empty($videoId) && !empty($videoUrl)) {
        // YouTube ID extraction
        if (strpos($videoUrl, 'youtube.com') !== false || strpos($videoUrl, 'youtu.be') !== false) {
            if (preg_match('/(?:youtube\.com\/watch\?.*v=|youtu\.be\/)([a-zA-Z0-9_-]{11})/', $videoUrl, $m)) {
                $videoId = $m[1];
                return 'https://www.youtube.com/embed/' . htmlspecialchars($videoId) . '?autoplay=1&mute=1&controls=0&loop=1&playlist=' . htmlspecialchars($videoId);
            }
        }
        
        // Vimeo ID extraction
        if (strpos($videoUrl, 'vimeo.com') !== false) {
            if (preg_match('/vimeo\.com\/(?:video\/)?(\d+)/', $videoUrl, $m)) {
                $videoId = $m[1];
                return 'https://player.vimeo.com/video/' . htmlspecialchars($videoId) . '?autoplay=1&muted=1&background=1';
            }
        }
        
        // For other cases, check if it's a direct video file
        if (preg_match('/\.(mp4|webm|ogg|mov)(\?|$)/i', $videoUrl)) {
            return 'local:' . htmlspecialchars($videoUrl);
        }
    }
    
    return ''; // No preview URL available
}

/**
 * Extract video ID from URL if not provided
 */
function extractVideoIdFromUrl($videoUrl) {
    if (empty($videoUrl)) return '';
    
    $videoUrl = trim($videoUrl);
    
    // YouTube
    if (preg_match('/(?:youtube\.com\/watch\?.*v=|youtu\.be\/)([a-zA-Z0-9_-]{11})/', $videoUrl, $m)) {
        return $m[1];
    }
    
    // Vimeo
    if (preg_match('/vimeo\.com\/(?:video\/)?(\d+)/', $videoUrl, $m)) {
        return $m[1];
    }
    
    // Wistia
    if (preg_match('/(?:wistia\.com|wi\.st)\/(?:medias|embed\/iframe)\/([a-z0-9]+)/', $videoUrl, $m)) {
        return $m[1];
    }
    
    // Rumble
    if (preg_match('/rumble\.com\/(?:embed\/)?v?([a-z0-9]+)(?:-|\.html|$|\/|\?)/', $videoUrl, $m)) {
        return $m[1];
    }
    
    // Muvi
    if (preg_match('/muvi\.com\/(?:embed\/|player\/|video\/)?([a-z0-9]+)/', $videoUrl, $m)) {
        return $m[1];
    }
    
    // Loom
    if (preg_match('/loom\.com\/(?:share|embed)\/([a-f0-9]+)/', $videoUrl, $m)) {
        return $m[1];
    }
    
    return '';
}

/**
 * Detect video type from URL
 */
function detectVideoType($videoUrl) {
    if (empty($videoUrl)) return 'local';
    
    $videoUrl = strtolower(trim($videoUrl));
    
    if (strpos($videoUrl, 'youtube.com') !== false || strpos($videoUrl, 'youtu.be') !== false) {
        return 'youtube';
    }
    if (strpos($videoUrl, 'vimeo.com') !== false) {
        return 'vimeo';
    }
    if (strpos($videoUrl, 'wistia.com') !== false || strpos($videoUrl, 'wi.st') !== false) {
        return 'wistia';
    }
    if (strpos($videoUrl, 'rumble.com') !== false) {
        return 'rumble';
    }
    if (strpos($videoUrl, 'muvi.com') !== false) {
        return 'muvi';
    }
    if (strpos($videoUrl, 'loom.com') !== false) {
        return 'loom';
    }
    if (preg_match('/\.(mp4|webm|ogg|mov|avi|m4v|mkv)(\?|$)/i', $videoUrl)) {
        return 'local';
    }
    if (strpos($videoUrl, 'r2.cloudflarestorage.com') !== false || strpos($videoUrl, '.r2.dev') !== false || strpos($videoUrl, 'vid.ions.com') !== false) {
        return 'r2';
    }
    
    return 'local';
}
