<?php
/**
 * Video Shortlink Manager
 * Handles generation and resolution of friendly video URLs
 * Simple approach using columns in IONLocalVideos table
 */

class VideoShortlinkManager {
    private $db;
    private $base_url;
    
    public function __construct($db, $base_url = null) {
        $this->db = $db;
        $this->base_url = $base_url ?: 'https://ions.com/v/';
    }
    
    /**
     * Generate a friendly shortlink for a video
     */
    public function generateShortlink($video_id, $title = null, $force_regenerate = false) {
        try {
            // Get video details
            $video = $this->db->get_row($this->db->prepare(
                "SELECT id, title, short_link FROM IONLocalVideos WHERE id = %d", 
                $video_id
            ));
            
            if (!$video) {
                throw new Exception('Video not found');
            }
            
            $title = $title ?: $video->title ?: 'Video';
            
            // Check if short_link already exists
            if (!$force_regenerate && !empty($video->short_link)) {
                return [
                    'shortlink' => $video->short_link,
                    'url' => $this->base_url . $video->short_link,
                    'existing' => true
                ];
            }
            
            // Generate short code (6 characters, alphanumeric)
            $shortcode = $this->generateUniqueShortcode();
            
            // Use just the shortcode for cleaner, shorter URLs
            $shortlink = $shortcode;
            
            // Ensure shortlink is unique
            $shortlink = $this->ensureUniqueShortlink($shortlink);
            
            // Update video with short_link
            $result = $this->db->update('IONLocalVideos', [
                'short_link' => $shortlink,
                'clicks' => 0
            ], ['id' => $video_id]);
            
            if ($result === false) {
                throw new Exception('Failed to save shortlink: ' . $this->db->last_error);
            }
            
            return [
                'shortlink' => $shortlink,
                'url' => $this->base_url . $shortlink,
                'existing' => false
            ];
            
        } catch (Exception $e) {
            error_log("Shortlink generation error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Resolve a shortlink to video
     */
    public function resolveShortlink($shortlink) {
        try {
            // Normalize input to lowercase for case-insensitive lookup
            $shortlink = strtolower(trim($shortlink));
            
            // Try exact match on shortcode (case-insensitive)
            $video = $this->db->get_row($this->db->prepare(
                "SELECT * FROM IONLocalVideos WHERE LOWER(short_link) = %s",
                $shortlink
            ));
            
            // For backward compatibility: also try matching old format (shortcode-title)
            if (!$video && preg_match('/^[a-z0-9]{6,8}$/', $shortlink)) {
                $video = $this->db->get_row($this->db->prepare(
                    "SELECT * FROM IONLocalVideos WHERE LOWER(short_link) LIKE %s",
                    $shortlink . '-%'
                ));
            }
            
            if (!$video) {
                return false;
            }
            
            // Increment click count
            $this->db->update('IONLocalVideos', 
                ['clicks' => $video->clicks + 1],
                ['id' => $video->id]
            );
            
            return [
                'video_id' => $video->id,
                'video' => $video,
                'shortlink' => $video->short_link, // Return the actual stored shortlink
                'clicks' => $video->clicks + 1
            ];
            
        } catch (Exception $e) {
            error_log("Shortlink resolution error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get shortlink for existing video
     */
    public function getVideoShortlink($video_id) {
        $video = $this->db->get_row($this->db->prepare(
            "SELECT short_link, clicks FROM IONLocalVideos WHERE id = %d",
            $video_id
        ));
        
        if ($video && !empty($video->short_link)) {
            return [
                'shortlink' => $video->short_link,
                'url' => $this->base_url . $video->short_link,
                'clicks' => $video->clicks
            ];
        }
        
        return false;
    }
    
    /**
     * Create a SEO-friendly title slug
     */
    private function createTitleSlug($title) {
        $slug = strtolower(trim($title));
        
        // Remove special characters, keep alphanumeric and spaces/hyphens
        $slug = preg_replace('/[^a-z0-9\s\-]/', '', $slug);
        
        // Replace multiple spaces/hyphens with single hyphen
        $slug = preg_replace('/[\s\-]+/', '-', $slug);
        
        // Remove leading/trailing hyphens
        $slug = trim($slug, '-');
        
        // Limit length to 50 characters
        if (strlen($slug) > 50) {
            $slug = substr($slug, 0, 50);
            $slug = rtrim($slug, '-');
        }
        
        return $slug ?: 'video';
    }
    
    /**
     * Generate a unique shortcode with auto-scaling length (case-insensitive)
     */
    private function generateUniqueShortcode($length = 6) {
        // Use only lowercase letters and numbers for consistency
        $characters = 'abcdefghijklmnopqrstuvwxyz0123456789';
        $max_attempts = 50;
        $collision_threshold = 40; // Auto-scale after this many collisions
        
        for ($attempt = 0; $attempt < $max_attempts; $attempt++) {
            $shortcode = '';
            for ($i = 0; $i < $length; $i++) {
                $shortcode .= $characters[random_int(0, strlen($characters) - 1)];
            }
            
            // Check if unique in short_link column (case-insensitive)
            $existing = $this->db->get_row($this->db->prepare(
                "SELECT id FROM IONLocalVideos WHERE LOWER(short_link) LIKE LOWER(%s)",
                $shortcode . '%'
            ));
            
            if (!$existing) {
                // Log auto-scaling events for monitoring
                if ($length > 6) {
                    error_log("SHORTLINK AUTO-SCALE: Generated {$length}-char shortcode '{$shortcode}' after {$attempt} attempts");
                }
                return $shortcode;
            }
            
            // Auto-scale: increase length if too many collisions
            if ($attempt >= $collision_threshold && $length < 8) {
                $old_length = $length;
                $length++;
                error_log("SHORTLINK AUTO-SCALE: Increased length from {$old_length} to {$length} chars due to collisions");
                
                // Reset attempt counter for new length
                $attempt = 0;
            }
        }
        
        // Final fallback: timestamp-based code with current length (lowercase only)
        $fallback = substr(base_convert(time() . random_int(1000, 9999), 10, 36), 0, max($length, 8));
        $fallback = strtolower($fallback); // Ensure lowercase
        error_log("SHORTLINK FALLBACK: Used timestamp-based code '{$fallback}' after {$max_attempts} attempts");
        return $fallback;
    }
    
    /**
     * Ensure shortlink is unique
     */
    private function ensureUniqueShortlink($shortlink) {
        $original_shortlink = $shortlink;
        $counter = 1;
        
        while (true) {
            $existing = $this->db->get_row($this->db->prepare(
                "SELECT id FROM IONLocalVideos WHERE short_link = %s",
                $shortlink
            ));
            
            if (!$existing) {
                return $shortlink;
            }
            
            $counter++;
            $shortlink = $original_shortlink . '-' . $counter;
        }
    }
    
    /**
     * Update shortlink when video title changes
     */
    public function updateShortlink($video_id, $new_title) {
        // Simply regenerate the shortlink with new title
        return $this->generateShortlink($video_id, $new_title, true);
    }
    
    /**
     * Get shortlink system statistics and collision rates
     */
    public function getSystemStats() {
        try {
            // Get basic counts
            $stats = $this->db->get_row(
                "SELECT 
                    COUNT(*) as total_videos,
                    COUNT(short_link) as videos_with_links,
                    SUM(clicks) as total_clicks,
                    AVG(LENGTH(short_link)) as avg_link_length
                 FROM IONLocalVideos 
                 WHERE status != 'deleted'"
            );
            
            // Get length distribution
            $length_dist = $this->db->get_results(
                "SELECT 
                    LENGTH(short_link) as link_length,
                    COUNT(*) as count
                 FROM IONLocalVideos 
                 WHERE short_link IS NOT NULL 
                 GROUP BY LENGTH(short_link)
                 ORDER BY link_length"
            );
            
            // Calculate collision rate estimate
            $collision_rate = $this->estimateCollisionRate($stats->videos_with_links);
            
            return [
                'total_videos' => $stats->total_videos,
                'videos_with_links' => $stats->videos_with_links,
                'videos_without_links' => $stats->total_videos - $stats->videos_with_links,
                'total_clicks' => $stats->total_clicks ?: 0,
                'avg_link_length' => round($stats->avg_link_length, 1),
                'length_distribution' => $length_dist,
                'collision_rate' => $collision_rate,
                'capacity_used' => $this->calculateCapacityUsed($stats->videos_with_links),
                'auto_scaling_active' => $this->isAutoScalingActive($length_dist)
            ];
            
        } catch (Exception $e) {
            error_log("System stats error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Estimate collision rate based on current usage
     */
    private function estimateCollisionRate($video_count) {
        $chars = 62; // a-z, A-Z, 0-9
        
        // Calculate collision probability for different lengths
        $rates = [];
        for ($length = 6; $length <= 8; $length++) {
            $total_combinations = pow($chars, $length);
            $probability = ($video_count * ($video_count - 1)) / (2 * $total_combinations);
            $rates[$length] = [
                'length' => $length,
                'total_combinations' => $total_combinations,
                'collision_probability' => round($probability * 100, 4),
                'safe_capacity' => round($total_combinations * 0.1) // 10% safe usage
            ];
        }
        
        return $rates;
    }
    
    /**
     * Calculate what percentage of capacity is being used
     */
    private function calculateCapacityUsed($video_count) {
        $length_6_capacity = pow(62, 6); // 56+ billion
        $percentage_used = ($video_count / $length_6_capacity) * 100;
        
        return [
            'percentage' => round($percentage_used, 8),
            'videos_used' => $video_count,
            'total_capacity' => $length_6_capacity,
            'remaining' => $length_6_capacity - $video_count
        ];
    }
    
    /**
     * Check if auto-scaling has been activated
     */
    private function isAutoScalingActive($length_distribution) {
        foreach ($length_distribution as $dist) {
            if ($dist->link_length > 6) {
                return true;
            }
        }
        return false;
    }
}
?>
