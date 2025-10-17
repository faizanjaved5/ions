<?php
/**
 * ION Upload Optimizer
 * Handles ALL optimization and background processing
 * Consolidates: OptimizedUploadHandler.php + process-optimization-queue.php + VideoOptimizer.php
 */

class IONUploadOptimizer {
    private $db;
    private $config;
    
    public function __construct() {
        global $wpdb, $db;
        
        // Ensure database connection
        if (!isset($wpdb)) {
            require_once __DIR__ . '/../config/database.php';
            $wpdb = $db;
        }
        
        $this->db = $wpdb;
        $this->config = include(__DIR__ . '/../config/config.php');
    }
    
    /**
     * Queue video for optimization
     */
    public function queueOptimization($videoId, $r2Url, $metadata = []) {
        try {
            // Update video status to processing
            $this->updateVideoStatus($videoId, 'processing');
            
            // Add to optimization queue
            $this->db->insert('IONVideoQueue', [
                'video_id' => $videoId,
                'r2_url' => $r2Url,
                'metadata' => json_encode($metadata),
                'status' => 'queued',
                'created_at' => current_time('mysql'),
                'priority' => $this->calculatePriority($metadata)
            ]);
            
            error_log("[ION Optimizer] Queued video $videoId for optimization");
            
            // Try immediate processing if not too busy
            if ($this->canProcessImmediately()) {
                $this->processVideo($videoId, $r2Url, $metadata);
            }
            
        } catch (Exception $e) {
            error_log('[ION Optimizer] Queue error: ' . $e->getMessage());
            $this->updateVideoStatus($videoId, 'error', ['error' => $e->getMessage()]);
        }
    }
    
    /**
     * Process queued videos (called by cron or manually)
     */
    public static function processQueue() {
        $optimizer = new self();
        
        echo "🚀 Starting ION optimization queue processor at " . date('Y-m-d H:i:s') . "\n";
        
        try {
            // Get queued videos
            $queuedVideos = $optimizer->getQueuedVideos();
            
            if (empty($queuedVideos)) {
                echo "✅ No videos in optimization queue\n";
                return;
            }
            
            echo "📦 Processing " . count($queuedVideos) . " queued videos\n";
            
            foreach ($queuedVideos as $item) {
                try {
                    echo "🎬 Processing video ID: {$item->video_id}\n";
                    
                    $metadata = json_decode($item->metadata, true) ?: [];
                    $result = $optimizer->processVideo($item->video_id, $item->r2_url, $metadata);
                    
                    if ($result['success']) {
                        echo "✅ Video {$item->video_id} processed successfully\n";
                        $optimizer->removeFromQueue($item->id);
                    } else {
                        echo "❌ Video {$item->video_id} processing failed: {$result['error']}\n";
                        $optimizer->markQueueItemFailed($item->id, $result['error']);
                    }
                    
                } catch (Exception $e) {
                    echo "❌ Error processing video {$item->video_id}: " . $e->getMessage() . "\n";
                    $optimizer->markQueueItemFailed($item->id, $e->getMessage());
                }
            }
            
            // Check Stream status for processing videos
            $optimizer->checkStreamStatus();
            
            echo "✅ Optimization queue processing completed at " . date('Y-m-d H:i:s') . "\n";
            
        } catch (Exception $e) {
            echo "❌ Queue processor error: " . $e->getMessage() . "\n";
            error_log('[ION Optimizer] Queue processor error: ' . $e->getMessage());
        }
    }
    
    /**
     * Process individual video
     */
    public function processVideo($videoId, $r2Url, $metadata = []) {
        try {
            // Update status to processing
            $this->updateVideoStatus($videoId, 'processing');
            
            // Extract video metadata
            $videoMetadata = $this->extractVideoMetadata($r2Url);
            
            // Determine optimization strategy
            $strategy = $this->determineOptimizationStrategy($videoMetadata, $metadata);
            
            $result = [];
            
            switch ($strategy) {
                case 'cloudflare_stream':
                    $result = $this->processWithCloudflareStream($videoId, $r2Url, $metadata);
                    break;
                    
                case 'server_ffmpeg':
                    $result = $this->processWithServerFFmpeg($videoId, $r2Url, $metadata);
                    break;
                    
                case 'no_optimization':
                    $result = $this->processWithoutOptimization($videoId, $r2Url, $metadata);
                    break;
                    
                default:
                    throw new Exception('Unknown optimization strategy: ' . $strategy);
            }
            
            if ($result['success']) {
                $this->updateVideoStatus($videoId, 'completed', $result);
                error_log("[ION Optimizer] Video $videoId optimization completed");
            } else {
                $this->updateVideoStatus($videoId, 'error', $result);
                error_log("[ION Optimizer] Video $videoId optimization failed: " . $result['error']);
            }
            
            return $result;
            
        } catch (Exception $e) {
            error_log('[ION Optimizer] Processing error: ' . $e->getMessage());
            $this->updateVideoStatus($videoId, 'error', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Process with Cloudflare Stream (preferred method)
     */
    private function processWithCloudflareStream($videoId, $r2Url, $metadata) {
        try {
            $streamConfig = $this->config['cloudflare_stream'] ?? [];
            
            if (empty($streamConfig['api_token']) || empty($streamConfig['account_id'])) {
                throw new Exception('Cloudflare Stream not configured');
            }
            
            // Upload to Cloudflare Stream
            $streamResult = $this->uploadToCloudflareStream($r2Url, $streamConfig, $metadata);
            
            if ($streamResult['success']) {
                // Update video with Stream URL
                $this->db->update('IONLocalVideos', [
                    'stream_id' => $streamResult['stream_id'],
                    'stream_url' => $streamResult['stream_url'],
                    'optimization_method' => 'cloudflare_stream',
                    'optimization_completed_at' => current_time('mysql')
                ], ['id' => $videoId]);
                
                return [
                    'success' => true,
                    'method' => 'cloudflare_stream',
                    'stream_id' => $streamResult['stream_id'],
                    'stream_url' => $streamResult['stream_url']
                ];
            } else {
                throw new Exception('Stream upload failed: ' . $streamResult['error']);
            }
            
        } catch (Exception $e) {
            error_log('[ION Optimizer] Cloudflare Stream error: ' . $e->getMessage());
            
            // Fallback to server FFmpeg
            return $this->processWithServerFFmpeg($videoId, $r2Url, $metadata);
        }
    }
    
    /**
     * Process with server FFmpeg (fallback method)
     */
    private function processWithServerFFmpeg($videoId, $r2Url, $metadata) {
        try {
            // Download video from R2
            $tempFile = $this->downloadFromR2($r2Url);
            
            // Process with FFmpeg
            $ffmpegResult = $this->processWithFFmpeg($tempFile, $metadata);
            
            if ($ffmpegResult['success']) {
                // Upload processed video back to R2
                $processedR2Url = $this->uploadProcessedToR2($ffmpegResult['output_file'], $videoId);
                
                // Update video record
                $this->db->update('IONLocalVideos', [
                    'video_link' => $processedR2Url,
                    'optimization_method' => 'server_ffmpeg',
                    'optimization_completed_at' => current_time('mysql')
                ], ['id' => $videoId]);
                
                // Clean up temp files
                unlink($tempFile);
                unlink($ffmpegResult['output_file']);
                
                return [
                    'success' => true,
                    'method' => 'server_ffmpeg',
                    'optimized_url' => $processedR2Url
                ];
            } else {
                throw new Exception('FFmpeg processing failed: ' . $ffmpegResult['error']);
            }
            
        } catch (Exception $e) {
            error_log('[ION Optimizer] FFmpeg error: ' . $e->getMessage());
            
            // Fallback to no optimization
            return $this->processWithoutOptimization($videoId, $r2Url, $metadata);
        }
    }
    
    /**
     * Process without optimization (final fallback)
     */
    private function processWithoutOptimization($videoId, $r2Url, $metadata) {
        // Just mark as completed without optimization
        $this->db->update('IONLocalVideos', [
            'optimization_method' => 'none',
            'optimization_completed_at' => current_time('mysql')
        ], ['id' => $videoId]);
        
        return [
            'success' => true,
            'method' => 'none',
            'message' => 'Video processed without optimization'
        ];
    }
    
    /**
     * Check Cloudflare Stream status for processing videos
     */
    private function checkStreamStatus() {
        // Find videos that are processing in Stream
        $processingVideos = $this->db->get_results(
            "SELECT id, stream_id, title 
             FROM IONLocalVideos 
             WHERE optimization_status = 'processing' 
             AND stream_id IS NOT NULL 
             AND stream_id != ''
             LIMIT 50"
        );
        
        if (empty($processingVideos)) {
            return;
        }
        
        echo "🔍 Checking Stream status for " . count($processingVideos) . " videos\n";
        
        foreach ($processingVideos as $video) {
            try {
                $streamStatus = $this->getCloudflareStreamStatus($video->stream_id);
                
                if ($streamStatus['ready']) {
                    echo "✅ Stream ready for video {$video->id}: {$video->title}\n";
                    $this->updateVideoStatus($video->id, 'completed', [
                        'stream_ready' => true,
                        'stream_url' => $streamStatus['url']
                    ]);
                } elseif ($streamStatus['error']) {
                    echo "❌ Stream error for video {$video->id}: {$streamStatus['error']}\n";
                    $this->updateVideoStatus($video->id, 'error', [
                        'stream_error' => $streamStatus['error']
                    ]);
                }
                
            } catch (Exception $e) {
                error_log("[ION Optimizer] Stream status check failed for video {$video->id}: " . $e->getMessage());
            }
        }
    }
    
    /**
     * Determine optimization strategy based on file and configuration
     */
    private function determineOptimizationStrategy($videoMetadata, $uploadMetadata) {
        // Check if Cloudflare Stream is available and configured
        if ($this->isCloudflareStreamAvailable()) {
            return 'cloudflare_stream';
        }
        
        // Check if server has FFmpeg
        if ($this->isFFmpegAvailable()) {
            return 'server_ffmpeg';
        }
        
        // No optimization available
        return 'no_optimization';
    }
    
    /**
     * Get queued videos for processing
     */
    private function getQueuedVideos($limit = 50) {
        return $this->db->get_results(
            "SELECT * FROM IONVideoQueue 
             WHERE status = 'queued' 
             ORDER BY priority DESC, created_at ASC 
             LIMIT $limit"
        );
    }
    
    /**
     * Update video optimization status
     */
    private function updateVideoStatus($videoId, $status, $data = []) {
        $updateData = ['optimization_status' => $status];
        
        if (!empty($data)) {
            $updateData['optimization_data'] = json_encode($data);
        }
        
        if ($status === 'completed') {
            $updateData['optimization_completed_at'] = current_time('mysql');
        }
        
        $this->db->update('IONLocalVideos', $updateData, ['id' => $videoId]);
    }
    
    /**
     * Calculate processing priority
     */
    private function calculatePriority($metadata) {
        $priority = 5; // Default priority
        
        // Higher priority for smaller files (process faster)
        if (isset($metadata['file_size'])) {
            $sizeMB = $metadata['file_size'] / (1024 * 1024);
            if ($sizeMB < 100) $priority += 2;
            elseif ($sizeMB < 500) $priority += 1;
        }
        
        // Higher priority for public videos
        if (isset($metadata['visibility']) && $metadata['visibility'] === 'public') {
            $priority += 1;
        }
        
        return $priority;
    }
    
    /**
     * Check if we can process immediately (not too busy)
     */
    private function canProcessImmediately() {
        $processingCount = $this->db->get_var(
            "SELECT COUNT(*) FROM IONLocalVideos WHERE optimization_status = 'processing'"
        );
        
        return $processingCount < 3; // Don't process more than 3 simultaneously
    }
    
    /**
     * Extract video metadata from R2 URL
     */
    private function extractVideoMetadata($r2Url) {
        // Basic metadata extraction - can be enhanced
        return [
            'url' => $r2Url,
            'size' => null, // Would need to fetch from R2
            'duration' => null, // Would need FFprobe
            'resolution' => null // Would need FFprobe
        ];
    }
    
    /**
     * Check if Cloudflare Stream is available and configured
     */
    private function isCloudflareStreamAvailable() {
        $streamConfig = $this->config['cloudflare_stream'] ?? [];
        return !empty($streamConfig['api_token']) && !empty($streamConfig['account_id']);
    }
    
    /**
     * Check if FFmpeg is available on server
     */
    private function isFFmpegAvailable() {
        $output = [];
        $returnCode = 0;
        exec('ffmpeg -version 2>&1', $output, $returnCode);
        return $returnCode === 0;
    }
    
    /**
     * Upload video to Cloudflare Stream
     */
    private function uploadToCloudflareStream($r2Url, $streamConfig, $metadata) {
        try {
            $apiToken = $streamConfig['api_token'];
            $accountId = $streamConfig['account_id'];
            
            $postData = [
                'url' => $r2Url,
                'meta' => [
                    'name' => $metadata['title'] ?? 'Untitled Video'
                ]
            ];
            
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => "https://api.cloudflare.com/client/v4/accounts/{$accountId}/stream/copy",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($postData),
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $apiToken,
                    'Content-Type: application/json'
                ]
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode !== 200) {
                throw new Exception("Stream API returned HTTP $httpCode");
            }
            
            $result = json_decode($response, true);
            
            if (!$result['success']) {
                throw new Exception('Stream upload failed: ' . json_encode($result['errors']));
            }
            
            return [
                'success' => true,
                'stream_id' => $result['result']['uid'],
                'stream_url' => "https://customer-{$streamConfig['customer_subdomain']}.cloudflarestream.com/{$result['result']['uid']}/manifest/video.m3u8"
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get Cloudflare Stream status
     */
    private function getCloudflareStreamStatus($streamId) {
        try {
            $streamConfig = $this->config['cloudflare_stream'] ?? [];
            $apiToken = $streamConfig['api_token'];
            $accountId = $streamConfig['account_id'];
            
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => "https://api.cloudflare.com/client/v4/accounts/{$accountId}/stream/{$streamId}",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $apiToken,
                    'Content-Type: application/json'
                ]
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode !== 200) {
                return ['ready' => false, 'error' => "HTTP $httpCode"];
            }
            
            $result = json_decode($response, true);
            
            if (!$result['success']) {
                return ['ready' => false, 'error' => 'API error'];
            }
            
            $status = $result['result']['status']['state'] ?? 'unknown';
            
            return [
                'ready' => $status === 'ready',
                'error' => $status === 'error' ? 'Stream processing failed' : null,
                'url' => $status === 'ready' ? $result['result']['playback']['hls'] : null
            ];
            
        } catch (Exception $e) {
            return ['ready' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Download video from R2 for local processing
     */
    private function downloadFromR2($r2Url) {
        $tempFile = tempnam(sys_get_temp_dir(), 'ion_video_');
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $r2Url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_FILE => fopen($tempFile, 'w')
        ]);
        
        $success = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if (!$success || $httpCode !== 200) {
            unlink($tempFile);
            throw new Exception("Failed to download from R2: HTTP $httpCode");
        }
        
        return $tempFile;
    }
    
    /**
     * Process video with FFmpeg
     */
    private function processWithFFmpeg($inputFile, $metadata) {
        try {
            $outputFile = tempnam(sys_get_temp_dir(), 'ion_processed_') . '.mp4';
            
            // Basic FFmpeg command for optimization
            $cmd = sprintf(
                'ffmpeg -i %s -c:v libx264 -preset medium -crf 23 -c:a aac -b:a 128k %s 2>&1',
                escapeshellarg($inputFile),
                escapeshellarg($outputFile)
            );
            
            exec($cmd, $output, $returnCode);
            
            if ($returnCode !== 0) {
                throw new Exception('FFmpeg processing failed: ' . implode("\n", $output));
            }
            
            return [
                'success' => true,
                'output_file' => $outputFile
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Upload processed video back to R2
     */
    private function uploadProcessedToR2($processedFile, $videoId) {
        // This would integrate with R2 upload logic
        // For now, return placeholder URL
        return "https://r2.ions.com/processed/video_{$videoId}_optimized.mp4";
    }
    
    /**
     * Remove item from optimization queue
     */
    private function removeFromQueue($queueId) {
        $this->db->delete('IONVideoQueue', ['id' => $queueId]);
    }
    
    /**
     * Mark queue item as failed
     */
    private function markQueueItemFailed($queueId, $error) {
        $this->db->update('IONVideoQueue', [
            'status' => 'failed',
            'error_message' => $error,
            'failed_at' => current_time('mysql')
        ], ['id' => $queueId]);
    }
}

// CLI usage for cron jobs
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    IONUploadOptimizer::processQueue();
}

?>
