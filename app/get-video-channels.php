<?php
/**
 * Get Video Channels API
 * Returns the primary channel and distributed channels for a video
 */

session_start();

// Set JSON content type
header('Content-Type: application/json');

// Include database connection
require_once '../config/database.php';

// Check authentication
if (!isset($_SESSION['user_email']) || empty($_SESSION['user_email'])) {
    echo json_encode(['success' => false, 'error' => 'User not authenticated']);
    exit();
}

try {
    $video_id = intval($_GET['video_id'] ?? 0);
    error_log('📺 get-video-channels.php - Fetching channels for video ID: ' . $video_id);
    
    if ($video_id <= 0) {
        error_log('❌ Invalid video ID: ' . $video_id);
        echo json_encode(['success' => false, 'error' => 'Invalid video ID']);
        exit();
    }
    
    // Get video's primary channel (from slug field)
    $video = $db->get_row(
        "SELECT slug, id FROM IONLocalVideos WHERE id = ?",
        $video_id
    );
    
    if (!$video) {
        error_log('❌ Video not found for ID: ' . $video_id);
        echo json_encode(['success' => false, 'error' => 'Video not found']);
        exit();
    }
    
    error_log('📺 Video found - Slug: ' . ($video->slug ?? 'NULL') . ' (Video ID: ' . $video->id . ')');
    
    // DEBUG: Check what channels exist in IONLocalBlast for this video
    $blastCheck = $db->get_results("SELECT * FROM IONLocalBlast WHERE video_id = ?", $video_id);
    error_log('🔍 DEBUG: IONLocalBlast entries for video ' . $video_id . ': ' . ($blastCheck ? count($blastCheck) : 0));
    if ($blastCheck) {
        foreach ($blastCheck as $entry) {
            error_log('  - Channel: ' . $entry->channel_slug . ', Status: ' . $entry->status);
        }
    }
    
    $channels = [];
    
    // Add primary channel if it exists
    if (!empty($video->slug)) {
        error_log('🔍 Looking up primary channel in IONLocalNetwork with slug: ' . $video->slug);
        $primaryChannel = $db->get_row(
            "SELECT slug, city_name, state_code, state_name FROM IONLocalNetwork WHERE slug = ?",
            $video->slug
        );
        
        if ($primaryChannel) {
            $state_display = $primaryChannel->state_code ?? $primaryChannel->state_name ?? '';
            $display_name = $primaryChannel->city_name;
            if ($state_display) {
                $display_name .= ', ' . $state_display;
            }
            
            $channels[] = [
                'slug' => $primaryChannel->slug,
                'name' => $primaryChannel->city_name,
                'state' => $state_display,
                'display' => $display_name
            ];
            
            error_log('✅ Added primary channel: ' . $primaryChannel->slug . ' (' . $display_name . ')');
        } else {
            error_log('⚠️ Primary channel not found in IONLocalNetwork for slug: ' . $video->slug);
        }
    }
    
    // Get distributed channels from IONLocalBlast
    $distributedChannels = $db->get_results(
        "SELECT DISTINCT b.channel_slug 
         FROM IONLocalBlast b
         WHERE b.video_id = ? AND b.status = 'active'",
        $video_id
    );
    
    error_log('📺 Found ' . ($distributedChannels ? count($distributedChannels) : 0) . ' distributed channels in IONLocalBlast');
    
    if ($distributedChannels) {
        foreach ($distributedChannels as $dist) {
            $channel = $db->get_row(
                "SELECT slug, city_name, state_code, state_name FROM IONLocalNetwork WHERE slug = ?",
                $dist->channel_slug
            );
            
            if ($channel) {
                $state_display = $channel->state_code ?? $channel->state_name ?? '';
                $display_name = $channel->city_name;
                if ($state_display) {
                    $display_name .= ', ' . $state_display;
                }
                
                $channels[] = [
                    'slug' => $channel->slug,
                    'name' => $channel->city_name,
                    'state' => $state_display,
                    'display' => $display_name
                ];
                
                error_log('✅ Added distributed channel: ' . $channel->slug . ' (' . $display_name . ')');
            }
        }
    }
    
    error_log('📺 Returning ' . count($channels) . ' total channels');
    
    echo json_encode([
        'success' => true,
        'channels' => $channels,
        'count' => count($channels)
    ]);
    
} catch (Exception $e) {
    error_log('Get video channels error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Failed to load channels']);
}
?>

