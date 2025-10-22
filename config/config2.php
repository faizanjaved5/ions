<?php

$muvi_app_id                      = '032bc829cc8a4467a1985ff0681a530c';
$muvi_secret_key                  = '023af75fd23d48ccb7737b9f1443f114';
$muvi_product_key                 = '';
$muvi_store_key                   = '';

return [
    'charset'                     => 'utf8mb4',

  // Database Settings
    'host'                        => 'localhost',
    'dbname'                      => 'u185424179_WtONJ',  
    'username'                    => 'u185424179_S4e4w',   
    'password'                    => '04JE8wHMrl', 
  
  // SMTP Settings Zoho
    'smtpHost'                    => 'smtppro.zoho.com',     
    'smtpFrom'                    => 'admin@ionmail.us',
    'smtpUser'                    => 'admin@ionmail.us',        
    'smtpPass'                    => 'I&BcSDpnR@sn@XJw4BqFveIT6rvQiN',     
    'siteName'                    => 'ION Local Network',    

  // SMTP Settings Backup
  // 'smtpHost'                    => 'smtp.migadu.com',     
  // 'smtpFrom'                    => 'ion@sperse.net',
  // 'smtpUser'                    => 'ion@sperse.net',        
  // 'smtpPass'                    => 'fc8Xo$98@UD&CRNfz4E3joe#W',     
  // 'siteName'                    => 'ION Local Network',    
  
  // Media upload settings
    'mediaUploadPrefix'          => 'https://ions.com/assets/headers/',
    'mediaUploadPath'            => '/assets/headers/',
    
  // Muvi settings
    'muvi_app_id'                => '032bc829cc8a4467a1985ff0681a530c',  // Actual Muvi app ID
    'muvi_secret_key'            => '023af75fd23d48ccb7737b9f1443f114',  // Actual Muvi secret key
    'muvi_product_key'           => '', // Optional product key
    'muvi_store_key'             => '', // Optional store key    
  
  // Cloudflare Setting
    'cloudflare_api_token'       => [
        'Wwz2ZEWyH_ZzXY1PcbFU_5J1nbnD9zh033c31Kox',   // Cloudflare API token (must have "Zone:Create" permission)
    //  '49ElopZAj2X0KVIyEwT4GfjzVNlaskA96O83AI_j',   // Cloudflare API token (must have "Zone:Create" permission)
    //  'aLSSXoYl57Jf-5hQ9uO1QtttqXq67nzHhr3yD1Rz',   // Cloudflare API token (must have "Zone:Create" permission)
    //  'aDivNOJnIwlZWGfyIP24Bk5BDLeI81xzYlrcnqGX',   // Cloudflare API token (must have "Zone:Create" permission)
    ],
    
  // Video Upload Mode Configuration
    // Options: 'r2_basic', 'r2_optimized', 'cloudflare_stream'
    'video_upload_mode'          => 'r2_basic',  // Default: R2 with no optimization
    
  // Cloudflare R2 Setting
    'cloudflare_r2_api'          => [
        'account_id' 		     => 'c23b07106a871873e82bdc7865f33be1',
        'access_key_id' 	     => 'e90bc21642a1b7d931a0f823b40c7dd1',
        'secret_access_key'      => 'f0c09664d09d62bd26c524a3608893a502461493929bad4651e74f4af5231b57',
        'bucket_name' 		     => 'ion',
        'region' 	        	 => 'auto',
        'endpoint' 		         => 'https://c23b07106a871873e82bdc7865f33be1.r2.cloudflarestorage.com',   // Cloudflare R2 S3 API
        'public_url_base'        => 'https://vid.ions.com',
        'max_file_size'      	 => 20 * 1024 * 1024 * 1024, // 20GB
        'fail_to_local'          => false,  // If true, falls back to local storage on R2 failure        
    ],
    
  // Video Optimization Settings (for r2_optimized mode)
    'video_optimization'         => [
        'enabled'                => false,  // Enable when ready for optimization
        'method'                 => 'local',  // Options: 'local', 'coconut', 'mux', 'api2video'
        'ffmpeg_path'            => '/usr/bin/ffmpeg',  // Path to FFmpeg binary
        'queue_enabled'          => false,  // Use background queue (Redis/RabbitMQ)
        'resolutions'            => ['1080p', '720p', '480p', '360p'],
        'formats'                => ['hls', 'dash'],  // Streaming formats to generate
        
        // Third-party service configs (if using external optimization)
        'coconut_api_key'        => '',
        'mux_token_id'           => '',
        'mux_token_secret'       => '',
        'api2video_key'          => '',
    ],
    
  // Cloudflare Stream API configuration for thumbnail generation
    'cloudflare_stream_api'      => [
        'account_id' 		     => 'c23b07106a871873e82bdc7865f33be1',
        'api_token' 		     => 'G44BKUR7vuvyspqQVUWul5SxuKxjKCgdO7MKXxnz',     // Add your Stream API token here - needs Stream:Read permissions
        'webhook_secret'    	 => '',                                             // Optional: for webhook notifications
        'default_thumbnail_time' => '5s',                                           // Default timestamp for thumbnail generation
    ],    

  // Google oAuth Seeting (Sandbox iblog.bz)
    'google_client_id'           => '694862036192-9k8lfbnnkrjii3121sshnapaio61aa1b.apps.googleusercontent.com',
    'google_client_secret'       => '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',
    'google_redirect_uri'        => 'https://iblog.bz/login/google-oauth.php',
  
  // Google Drive Seeting
    'google_drive_clientid'      => '694862036192-6tbl549jacvhiinenn2t7n0i23ul02jv.apps.googleusercontent.com',
    'google_drive_secretid'      => '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',
    'google_drive_api_key'       => 'AIzaSyCON3htHV0uNIbFMBM49Svge5Xa52v3Cbg',
  
  // Youtube Setting
    'youtube_api_keys'           => [
        'AIzaSyAEAHHmCxF0X217xVCuEgArLu7vRmGhr4w',  //BC API Key
        'AIzaSyAEAHHmCxF0X217xVCuEgArLu7vRmGhr4w',  //BC API Key
        'AIzaSyBSIQ6Aa9IWiAJfjVKTgUwhXQtBstAG5ls',  //TG API Key
        'AIzaSyDKtnhdV6CNLLepyz4uvf1HZWo_qAP7B-s',  //S2 API Key
        'AIzaSyDQS7d7r1QylzHVgTuhWbQ3QZsmy2RTQ5s',  //SL API Key
        'AIzaSyDh8BWCD01ZGmTMMMV752JJLazy-LojT5o',  //IM API Key 
        'AIzaSyB3bjmRLNjnO5PGz9KCbpuDFsX6ecd5iwg',  //SP API Key
        'AIzaSyAmq7nOt3-_H9dYUb4zsEeVrv1XYx30b2M',  //UCaAPI Key
        'AIzaSyC2giPnALfhEEmcVBmkvxqsRtPFV174pTU',  //UCtAPI Key
        'AIzaSyBlM7NRPE_dDgDLS12h7ecaGmnbIswoY2E',  //KP API Key
        'AIzaSyCkU2zHidTnJHs6BfwsNK9xGdCNaHH20q0',  //IV API Key
        'AIzaSyC4h47OC0cx92M9dp4Pl68lFWM8Cq-Uvz0',  //Linka Admin
        'AIzaSyDAqixuhEp0IEOgq_6MWXp3LAQCHCLX-G4',  //ION ioninsights
        'AIzaSyAFSTKaPVPEEzFnAxncMVeoHO4fXA5vZ0E',  //SX API Key 
        'AIzaSyDiJuffGhA5rqnOR_Ys6uo0ICr5TEZHUpk',  //AZ Sensor
        'AIzaSyB5kCfts0ZtguqZTtwuNp-E8RpCltq0Ga4',  //IM Shop
    ]
];