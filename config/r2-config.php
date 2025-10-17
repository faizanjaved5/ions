<?php
/**
 * Cloudflare R2 Configuration
 * 
 * This file contains the configuration settings for Cloudflare R2 storage.
 * Make sure to set these environment variables in your server environment.
 */

return [
    // Cloudflare R2 Credentials
    'account_id' => getenv('CLOUDFLARE_ACCOUNT_ID') ?: '',
    'access_key_id' => getenv('CLOUDFLARE_R2_ACCESS_KEY_ID') ?: '',
    'secret_access_key' => getenv('CLOUDFLARE_R2_SECRET_ACCESS_KEY') ?: '',
    
    // R2 Bucket Configuration
    'bucket_name' => getenv('CLOUDFLARE_R2_BUCKET_NAME') ?: 'ion-videos',
    'region' => getenv('CLOUDFLARE_R2_REGION') ?: 'auto',
    'endpoint' => getenv('CLOUDFLARE_R2_ENDPOINT') ?: '',
    
    // Video Configuration
    'max_file_size' => 20 * 1024 * 1024 * 1024, // 20GB in bytes
    'allowed_extensions' => ['mp4', 'webm', 'avi', 'mov', 'mkv'],
    'allowed_mime_types' => [
        'video/mp4',
        'video/webm',
        'video/avi',
        'video/quicktime',
        'video/x-msvideo',
        'video/x-matroska'
    ],
    
    // CDN Configuration (Optional)
    'cdn_url' => getenv('CLOUDFLARE_R2_CDN_URL') ?: '',
    'public_url_base' => getenv('CLOUDFLARE_R2_PUBLIC_URL') ?: '',
    
    // Upload Configuration
    'upload_timeout' => 300, // 5 minutes
    'chunk_size' => 5 * 1024 * 1024, // 5MB chunks for large files
    
    // Video Processing
    'generate_thumbnails' => true,
    'thumbnail_times' => [1, 5, 10], // Generate thumbnails at these seconds
];
?>