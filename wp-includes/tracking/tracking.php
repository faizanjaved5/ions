<?php
/**
 * Video Tracking System
 * Tracks impressions and clicks for videos across multiple platforms
 * 
 * Features:
 * - Lightweight JavaScript for client-side tracking
 * - Batched requests for performance
 * - Redis caching for high-speed operations
 * - Intersection Observer for accurate impression tracking
 * - Platform-agnostic design
 */

class VideoTracker {
    private $pdo;
    private $redis;
    private $useRedis = false;
    private $batchSize = 50;
    private $flushInterval = 30; // seconds
    
    public function __construct($pdo, $redis = null) {
        $this->pdo = $pdo;
        if ($redis !== null && $redis->ping()) {
            $this->redis = $redis;
            $this->useRedis = true;
        }
        $this->initializeDatabase();
    }
    
    /**
     * Initialize tracking tables if they don't exist
     */
    private function initializeDatabase() {
        $sql = "
        CREATE TABLE IF NOT EXISTS video_tracking (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            video_id VARCHAR(255) NOT NULL,
            video_type VARCHAR(50) NOT NULL,
            page_slug VARCHAR(255) NOT NULL,
            city_slug VARCHAR(255) DEFAULT NULL,
            impressions INT UNSIGNED DEFAULT 0,
            clicks INT UNSIGNED DEFAULT 0,
            unique_impressions INT UNSIGNED DEFAULT 0,
            unique_clicks INT UNSIGNED DEFAULT 0,
            last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_video (video_id, video_type),
            INDEX idx_page (page_slug),
            INDEX idx_city (city_slug),
            INDEX idx_updated (last_updated),
            UNIQUE KEY unique_video_page (video_id, video_type, page_slug)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        
        CREATE TABLE IF NOT EXISTS video_tracking_daily (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            video_id VARCHAR(255) NOT NULL,
            video_type VARCHAR(50) NOT NULL,
            page_slug VARCHAR(255) NOT NULL,
            city_slug VARCHAR(255) DEFAULT NULL,
            track_date DATE NOT NULL,
            impressions INT UNSIGNED DEFAULT 0,
            clicks INT UNSIGNED DEFAULT 0,
            unique_impressions INT UNSIGNED DEFAULT 0,
            unique_clicks INT UNSIGNED DEFAULT 0,
            INDEX idx_date (track_date),
            INDEX idx_video_date (video_id, video_type, track_date),
            UNIQUE KEY unique_video_date (video_id, video_type, page_slug, track_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        
        CREATE TABLE IF NOT EXISTS video_tracking_queue (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            batch_data JSON NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            processed TINYINT(1) DEFAULT 0,
            INDEX idx_processed (processed, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";
        
        try {
            $this->pdo->exec($sql);
        } catch (PDOException $e) {
            error_log("Video tracking table creation failed: " . $e->getMessage());
        }
    }
    
    /**
     * Process tracking batch from AJAX request
     */
    public function processBatch($batchData) {
        if ($this->useRedis) {
            // Store in Redis for later processing
            $key = 'video_tracking_batch:' . time() . ':' . uniqid();
            $this->redis->setex($key, 3600, json_encode($batchData));
            $this->redis->sadd('video_tracking_batches', $key);
            return ['status' => 'queued'];
        } else {
            // Store in database queue for processing
            $stmt = $this->pdo->prepare("INSERT INTO video_tracking_queue (batch_data) VALUES (:batch_data)");
            $stmt->execute([':batch_data' => json_encode($batchData)]);
            return ['status' => 'queued'];
        }
    }
    
    /**
     * Process queued tracking data (run via cron)
     */
    public function processQueue() {
        if ($this->useRedis) {
            $this->processRedisQueue();
        } else {
            $this->processDatabaseQueue();
        }
    }
    
    private function processRedisQueue() {
        $batches = $this->redis->smembers('video_tracking_batches');
        foreach ($batches as $key) {
            $data = $this->redis->get($key);
            if ($data) {
                $batchData = json_decode($data, true);
                $this->processBatchData($batchData);
                $this->redis->del($key);
                $this->redis->srem('video_tracking_batches', $key);
            }
        }
    }
    
    private function processDatabaseQueue() {
        $stmt = $this->pdo->prepare("SELECT id, batch_data FROM video_tracking_queue WHERE processed = 0 ORDER BY created_at LIMIT 100");
        $stmt->execute();
        $batches = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($batches as $batch) {
            $batchData = json_decode($batch['batch_data'], true);
            $this->processBatchData($batchData);
            
            $updateStmt = $this->pdo->prepare("UPDATE video_tracking_queue SET processed = 1 WHERE id = :id");
            $updateStmt->execute([':id' => $batch['id']]);
        }
        
        // Clean old processed records
        $this->pdo->exec("DELETE FROM video_tracking_queue WHERE processed = 1 AND created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)");
    }
    
    private function processBatchData($batchData) {
        $today = date('Y-m-d');
        
        foreach ($batchData as $event) {
            $videoId = $event['videoId'];
            $videoType = $event['videoType'];
            $pageSlug = $event['pageSlug'];
            $citySlug = $event['citySlug'] ?? null;
            $eventType = $event['event'];
            $sessionId = $event['sessionId'];
            
            // Update main tracking table
            $sql = "INSERT INTO video_tracking (video_id, video_type, page_slug, city_slug, impressions, clicks) 
                    VALUES (:video_id, :video_type, :page_slug, :city_slug, :impressions, :clicks)
                    ON DUPLICATE KEY UPDATE 
                    impressions = impressions + :impressions_update,
                    clicks = clicks + :clicks_update";
            
            $params = [
                ':video_id' => $videoId,
                ':video_type' => $videoType,
                ':page_slug' => $pageSlug,
                ':city_slug' => $citySlug,
                ':impressions' => $eventType === 'impression' ? 1 : 0,
                ':clicks' => $eventType === 'click' ? 1 : 0,
                ':impressions_update' => $eventType === 'impression' ? 1 : 0,
                ':clicks_update' => $eventType === 'click' ? 1 : 0
            ];
            
            try {
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute($params);
            } catch (PDOException $e) {
                error_log("Video tracking update failed: " . $e->getMessage());
            }
            
            // Update daily tracking table
            $sql = "INSERT INTO video_tracking_daily (video_id, video_type, page_slug, city_slug, track_date, impressions, clicks) 
                    VALUES (:video_id, :video_type, :page_slug, :city_slug, :track_date, :impressions, :clicks)
                    ON DUPLICATE KEY UPDATE 
                    impressions = impressions + :impressions_update,
                    clicks = clicks + :clicks_update";
            
            $params[':track_date'] = $today;
            
            try {
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute($params);
            } catch (PDOException $e) {
                error_log("Video daily tracking update failed: " . $e->getMessage());
            }
            
            // Handle unique tracking using Redis or database
            if ($this->useRedis) {
                $this->updateUniqueMetricsRedis($videoId, $videoType, $pageSlug, $eventType, $sessionId, $today);
            } else {
                $this->updateUniqueMetricsDatabase($videoId, $videoType, $pageSlug, $eventType, $sessionId, $today);
            }
        }
    }
    
    private function updateUniqueMetricsRedis($videoId, $videoType, $pageSlug, $eventType, $sessionId, $date) {
        $key = "video_unique:{$date}:{$videoId}:{$videoType}:{$pageSlug}:{$eventType}";
        $isNew = $this->redis->sadd($key, $sessionId);
        $this->redis->expire($key, 86400 * 7); // 7 days
        
        if ($isNew) {
            $columnName = $eventType === 'impression' ? 'unique_impressions' : 'unique_clicks';
            
            // Update main table
            $sql = "UPDATE video_tracking SET {$columnName} = {$columnName} + 1 
                    WHERE video_id = :video_id AND video_type = :video_type AND page_slug = :page_slug";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':video_id' => $videoId,
                ':video_type' => $videoType,
                ':page_slug' => $pageSlug
            ]);
            
            // Update daily table
            $sql = "UPDATE video_tracking_daily SET {$columnName} = {$columnName} + 1 
                    WHERE video_id = :video_id AND video_type = :video_type AND page_slug = :page_slug AND track_date = :track_date";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':video_id' => $videoId,
                ':video_type' => $videoType,
                ':page_slug' => $pageSlug,
                ':track_date' => $date
            ]);
        }
    }
    
    private function updateUniqueMetricsDatabase($videoId, $videoType, $pageSlug, $eventType, $sessionId, $date) {
        // For database-only implementation, we'll use a simple session-based approach
        // In production, you might want a separate table for tracking unique sessions
        $sessionKey = md5($sessionId . $videoId . $videoType . $pageSlug . $eventType . $date);
        $cacheKey = 'video_unique_' . $sessionKey;
        
        if (!isset($_SESSION[$cacheKey])) {
            $_SESSION[$cacheKey] = true;
            
            $columnName = $eventType === 'impression' ? 'unique_impressions' : 'unique_clicks';
            
            // Update both tables as in Redis version
            $sql = "UPDATE video_tracking SET {$columnName} = {$columnName} + 1 
                    WHERE video_id = :video_id AND video_type = :video_type AND page_slug = :page_slug";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':video_id' => $videoId,
                ':video_type' => $videoType,
                ':page_slug' => $pageSlug
            ]);
        }
    }
    
    /**
     * Get tracking statistics for videos
     */
    public function getStats($videoId = null, $videoType = null, $pageSlug = null, $dateFrom = null, $dateTo = null) {
        $where = [];
        $params = [];
        
        if ($videoId) {
            $where[] = "video_id = :video_id";
            $params[':video_id'] = $videoId;
        }
        
        if ($videoType) {
            $where[] = "video_type = :video_type";
            $params[':video_type'] = $videoType;
        }
        
        if ($pageSlug) {
            $where[] = "page_slug = :page_slug";
            $params[':page_slug'] = $pageSlug;
        }
        
        $whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";
        
        if ($dateFrom || $dateTo) {
            // Use daily table for date range queries
            $sql = "SELECT 
                        video_id,
                        video_type,
                        page_slug,
                        city_slug,
                        SUM(impressions) as total_impressions,
                        SUM(clicks) as total_clicks,
                        SUM(unique_impressions) as total_unique_impressions,
                        SUM(unique_clicks) as total_unique_clicks,
                        ROUND(SUM(clicks) * 100.0 / NULLIF(SUM(impressions), 0), 2) as ctr
                    FROM video_tracking_daily";
            
            if ($dateFrom) {
                $where[] = "track_date >= :date_from";
                $params[':date_from'] = $dateFrom;
            }
            
            if ($dateTo) {
                $where[] = "track_date <= :date_to";
                $params[':date_to'] = $dateTo;
            }
            
            $whereClause = !empty($where) ? " WHERE " . implode(" AND ", $where) : "";
            $sql .= $whereClause . " GROUP BY video_id, video_type, page_slug, city_slug";
        } else {
            // Use main table for overall stats
            $sql = "SELECT 
                        video_id,
                        video_type,
                        page_slug,
                        city_slug,
                        impressions as total_impressions,
                        clicks as total_clicks,
                        unique_impressions as total_unique_impressions,
                        unique_clicks as total_unique_clicks,
                        ROUND(clicks * 100.0 / NULLIF(impressions, 0), 2) as ctr
                    FROM video_tracking " . $whereClause;
        }
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Generate JavaScript tracking code
     */
    public function getTrackingScript($pageSlug, $citySlug = null) {
        $trackingEndpoint = '/api/video-track.php';
        return <<<JS
<script>
(function() {
    const VideoTracker = {
        queue: [],
        batchSize: 10,
        flushInterval: 5000,
        sessionId: this.getSessionId(),
        pageSlug: '{$pageSlug}',
        citySlug: '{$citySlug}',
        endpoint: '{$trackingEndpoint}',
        trackedImpressions: new Set(),
        
        init() {
            this.setupIntersectionObserver();
            this.setupClickTracking();
            this.startBatchTimer();
            
            // Flush on page unload
            window.addEventListener('beforeunload', () => this.flush());
        },
        
        getSessionId() {
            let sessionId = sessionStorage.getItem('video_tracker_session');
            if (!sessionId) {
                sessionId = 'sess_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
                sessionStorage.setItem('video_tracker_session', sessionId);
            }
            return sessionId;
        },
        
        setupIntersectionObserver() {
            const options = {
                root: null,
                rootMargin: '0px',
                threshold: 0.5 // Video is 50% visible
            };
            
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const element = entry.target;
                        const videoId = element.getAttribute('data-video-id');
                        const videoType = element.getAttribute('data-video-type');
                        
                        if (videoId && !this.trackedImpressions.has(videoId)) {
                            this.trackedImpressions.add(videoId);
                            this.track('impression', videoId, videoType);
                        }
                    }
                });
            }, options);
            
            // Observe all video elements
            document.querySelectorAll('.video-thumb, .carousel-item a[data-video-id]').forEach(el => {
                observer.observe(el);
            });
        },
        
        setupClickTracking() {
            document.addEventListener('click', (e) => {
                const videoElement = e.target.closest('[data-video-id]');
                if (videoElement) {
                    const videoId = videoElement.getAttribute('data-video-id');
                    const videoType = videoElement.getAttribute('data-video-type');
                    if (videoId) {
                        this.track('click', videoId, videoType);
                    }
                }
            });
        },
        
        track(eventType, videoId, videoType = 'youtube') {
            this.queue.push({
                event: eventType,
                videoId: videoId,
                videoType: videoType,
                pageSlug: this.pageSlug,
                citySlug: this.citySlug,
                sessionId: this.sessionId,
                timestamp: Date.now()
            });
            
            if (this.queue.length >= this.batchSize) {
                this.flush();
            }
        },
        
        startBatchTimer() {
            setInterval(() => {
                if (this.queue.length > 0) {
                    this.flush();
                }
            }, this.flushInterval);
        },
        
        flush() {
            if (this.queue.length === 0) return;
            
            const batch = [...this.queue];
            this.queue = [];
            
            // Use sendBeacon for reliability on page unload
            if (navigator.sendBeacon) {
                const blob = new Blob([JSON.stringify(batch)], { type: 'application/json' });
                navigator.sendBeacon(this.endpoint, blob);
            } else {
                // Fallback to fetch
                fetch(this.endpoint, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(batch),
                    keepalive: true
                }).catch(err => {
                    // Re-queue failed items
                    this.queue.unshift(...batch);
                });
            }
        }
    };
    
    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => VideoTracker.init());
    } else {
        VideoTracker.init();
    }
})();
</script>
JS;
    }
}

// Usage example for including in your page:
/*
// Include this file
require_once '/tracking/tracking-api.php'';

// Initialize tracker (with or without Redis)
try {
    $redis = new Redis();
    $redis->connect('127.0.0.1', 6379);
} catch (Exception $e) {
    $redis = null;
}

$tracker = new VideoTracker($pdo, $redis);

// Output tracking script in your HTML
echo $tracker->getTrackingScript($slug, $city->city_name);
*/
?>