<?php
session_start();

// Include authentication and role management
require_once '../config/config.php';
require_once '../login/roles.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role'])) {
    header('Location: /login/');
    exit();
}

$user_role = $_SESSION['user_role'];
$user_email = $_SESSION['user_email'];
$user_id = $_SESSION['user_id'];

// Check if user can access uploader (all roles except Viewer)
if (!IONRoles::canAccessSection($user_role, 'ION_VIDS')) {
    header('Location: /login/?error=unauthorized');
    exit();
}

// Get user data for preferences
$user_unique_id = $_SESSION['user_unique_id'] ?? $user_id;
$user_data = $wpdb->get_row($wpdb->prepare(
    "SELECT fullname, preferences, photo_url FROM IONEERS WHERE email = %s",
    $user_email
));

$user_fullname = $user_data->fullname ?? 'User';

// Parse user preferences for theming
$default_preferences = [
    'Theme' => 'dark',
    'Background' => ['#6366f1', '#7c3aed'],
    'ButtonColor' => '#4f46e5'
];

$user_preferences = $default_preferences;
if (!empty($user_data->preferences)) {
    $parsed_preferences = json_decode($user_data->preferences, true);
    if (is_array($parsed_preferences)) {
        $user_preferences = array_merge($default_preferences, $parsed_preferences);
    }
}

error_log("UPLOADER ACCESS: User {$user_email} with role {$user_role} accessing video uploader");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ION Video Upload - <?= htmlspecialchars($user_fullname) ?></title>
    <style>
        :root {
            /* Light mode colors */
            --bg-primary: #ffffff;
            --bg-secondary: #f5f5f5;
            --bg-tertiary: #f9f9f9;
            --text-primary: #1a1a1a;
            --text-secondary: #666666;
            --text-muted: #999999;
            --border-color: #e0e0e0;
            --input-bg: #ffffff;
            --input-border: #ddd;
            --success-color: #4ade80;
            --success-bg: #dcfce7;
            --error-color: #ef4444;
            --accent-green: #22c55e;
            --modal-overlay: rgba(0, 0, 0, 0.5);
            --shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            --tag-bg: #f0f0f0;
        }

        [data-theme="dark"] {
            /* Dark mode colors */
            --bg-primary: #1a1a1a;
            --bg-secondary: #242424;
            --bg-tertiary: #2a2a2a;
            --text-primary: #ffffff;
            --text-secondary: #b0b0b0;
            --text-muted: #808080;
            --border-color: #3a3a3a;
            --input-bg: #2a2a2a;
            --input-border: #3a3a3a;
            --success-color: #22c55e;
            --success-bg: #064e3b;
            --error-color: #ef4444;
            --modal-overlay: rgba(0, 0, 0, 0.8);
            --shadow: 0 2px 20px rgba(0, 0, 0, 0.5);
            --tag-bg: #3a3a3a;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background-color: var(--bg-primary);
            color: var(--text-primary);
            line-height: 1.6;
        }

        /* Modal Overlay */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: var(--modal-overlay);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            padding: 20px;
        }

        /* Modal Container */
        .modal-container {
            background: var(--bg-primary);
            border-radius: 12px;
            box-shadow: var(--shadow);
            width: 100%;
            max-width: 800px;
            max-height: 90vh;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            animation: modalGlow 2s ease-in-out infinite alternate;
        }

        @keyframes modalGlow {
            0% {
                box-shadow: 
                    0 8px 32px rgba(0, 0, 0, 0.4),
                    0 16px 64px rgba(0, 0, 0, 0.3),
                    0 20px 40px rgba(137, 105, 72, 0.3),
                    0 40px 80px rgba(137, 105, 72, 0.2),
                    0 60px 120px rgba(137, 105, 72, 0.1);
            }
            100% {
                box-shadow: 
                    0 8px 32px rgba(0, 0, 0, 0.4),
                    0 16px 64px rgba(0, 0, 0, 0.3),
                    0 30px 60px rgba(137, 105, 72, 0.5),
                    0 60px 120px rgba(137, 105, 72, 0.3),
                    0 90px 180px rgba(137, 105, 72, 0.2);
            }
        }

        /* Modal Header */
        .modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 20px 24px;
            border-bottom: 1px solid var(--border-color);
            position: relative;
        }

        .modal-header-content {
            flex: 1;
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .modal-title {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 20px;
            font-weight: 600;
        }

        .modal-title svg {
            width: 24px;
            height: 24px;
            flex-shrink: 0;
        }

        .modal-subtitle {
            font-size: 14px;
            color: var(--text-secondary);
            margin-left: auto;
            display: flex;
            align-items: center;
            gap: 8px;
            max-width: 300px;
        }

        .modal-subtitle svg {
            width: 16px;
            height: 16px;
            flex-shrink: 0;
        }

        .file-reference {
            font-weight: 500;
            color: var(--text-primary);
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .video-id-badge {
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-family: monospace;
            color: var(--accent-green);
        }

        .close-btn {
            background: none;
            border: none;
            color: var(--text-secondary);
            cursor: pointer;
            padding: 8px;
            border-radius: 6px;
            transition: all 0.2s;
        }

        .close-btn:hover {
            background: var(--bg-secondary);
            color: var(--text-primary);
        }

        /* Progress Bar */
        .progress-bar-container {
            height: 4px;
            background: var(--bg-secondary);
            position: absolute;
            bottom: -4px;
            left: 0;
            right: 0;
            overflow: hidden;
            display: none;
        }

        .progress-bar-container.active {
            display: block;
        }

        .progress-bar {
            height: 100%;
            background: var(--accent-green);
            width: 0;
            transition: width 0.3s ease;
        }

        .loading-progress {
            height: 100%;
            background: linear-gradient(90deg, transparent 0%, var(--accent-green) 50%, transparent 100%);
            width: 30%;
            animation: loading 1.5s linear infinite;
        }

        @keyframes loading {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(400%); }
        }

        /* Modal Body */
        .modal-body {
            flex: 1;
            overflow-y: auto;
            padding: 32px 24px;
        }

        /* Step 1: Upload Options */
        .upload-options {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            align-items: stretch;
        }

        .upload-option {
            text-align: center;
            display: flex;
            flex-direction: column;
        }

        .upload-option h3 {
            font-size: 18px;
            margin-bottom: 16px;
            color: var(--text-primary);
        }

        .upload-zone {
            border: 2px dashed var(--border-color);
            border-radius: 12px;
            padding: 32px 24px;
            background: var(--bg-secondary);
            cursor: pointer;
            transition: all 0.3s;
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            min-height: 350px;
        }

        .upload-zone:hover {
            border-color: var(--accent-green);
            background: var(--bg-tertiary);
        }

        .upload-zone.dragover {
            border-color: var(--accent-green);
            background: var(--success-bg);
        }

        .upload-icon {
            width: 48px;
            height: 48px;
            margin: 0 auto 16px;
            color: var(--text-secondary);
        }

        .upload-text {
            font-size: 16px;
            color: var(--text-primary);
            margin-bottom: 8px;
            font-weight: 500;
        }

        .upload-subtext {
            font-size: 14px;
            color: var(--text-secondary);
            margin-bottom: 24px;
        }

        .upload-requirements {
            text-align: left;
            margin: 0 auto;
            max-width: 280px;
        }

        .upload-requirements h4 {
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 12px;
            color: var(--text-secondary);
            text-align: center;
        }

        .upload-requirements ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .upload-requirements li {
            font-size: 13px;
            color: var(--text-secondary);
            margin-bottom: 8px;
            padding-left: 20px;
            position: relative;
            line-height: 1.4;
        }

        .upload-requirements li:before {
            content: "•";
            position: absolute;
            left: 0;
            color: var(--accent-green);
        }

        .file-input {
            display: none;
        }

        /* Import Options */
        .import-section {
            padding: 32px 24px;
            background: var(--bg-secondary);
            border: 2px dashed var(--border-color);
            border-radius: 12px;
            text-align: center;
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            min-height: 350px;
            transition: all 0.3s;
        }

        .import-section:hover {
            border-color: var(--accent-green);
            background: var(--bg-tertiary);
        }

        .import-platforms {
            display: flex;
            justify-content: center;
            gap: 16px;
            margin: 24px 0;
        }

        .platform-btn {
            padding: 8px 16px;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            background: var(--bg-primary);
            color: var(--text-primary);
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .platform-btn:hover {
            border-color: var(--accent-green);
            background: var(--bg-tertiary);
        }

        .platform-btn.youtube { color: #FF0000; }
        .platform-btn.vimeo { color: #1AB7EA; }
        .platform-btn.muvi { color: #FF6B35; }

        .url-input-group {
            margin-top: 24px;
        }

        .url-input {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid var(--input-border);
            border-radius: 8px;
            background: var(--input-bg);
            color: var(--text-primary);
            font-size: 16px;
            transition: all 0.2s;
        }

        .url-input:focus {
            outline: none;
            border-color: var(--accent-green);
        }

        /* Requirements */
        .requirements {
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 16px;
            margin-top: 24px;
        }

        .requirements h4 {
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 12px;
            color: var(--text-primary);
        }

        .requirements ul {
            list-style: none;
            padding: 0;
        }

        .requirements li {
            font-size: 14px;
            color: var(--text-secondary);
            margin-bottom: 8px;
            padding-left: 20px;
            position: relative;
        }

        .requirements li:before {
            content: "•";
            position: absolute;
            left: 0;
            color: var(--accent-green);
        }

        /* Step 2: Video Details */
        .video-preview-section {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 24px;
            margin-bottom: 24px;
            align-items: start;
        }

        .video-preview-container {
            width: 100%;
        }

        .video-preview {
            background: #000;
            border-radius: 8px;
            overflow: hidden;
            position: relative;
            width: 100%;
            max-width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .video-preview.horizontal {
            aspect-ratio: 16/9;
        }

        .video-preview.vertical {
            aspect-ratio: 9/16;
            max-height: 400px;
            margin: 0 auto;
        }

        .video-preview video,
        .video-preview iframe {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .video-preview.vertical video,
        .video-preview.vertical iframe {
            max-height: 400px;
        }

        .video-controls {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: rgba(0, 0, 0, 0.8);
            padding: 12px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .play-btn {
            background: none;
            border: none;
            color: white;
            cursor: pointer;
            padding: 4px;
        }

        .video-time {
            color: white;
            font-size: 14px;
            margin-left: auto;
        }

        .video-details-sidebar {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .video-info {
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 16px;
        }

        .video-info h4 {
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 12px;
            color: var(--text-primary);
        }

        .video-info-item {
            font-size: 13px;
            color: var(--text-secondary);
            margin-bottom: 8px;
            display: flex;
            justify-content: space-between;
        }

        .video-info-item strong {
            color: var(--text-primary);
        }

        .thumbnail-section {
            background: var(--bg-secondary);
            border: 2px dashed var(--border-color);
            border-radius: 8px;
            padding: 16px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s;
        }

        .thumbnail-section:hover {
            border-color: var(--accent-green);
        }

        .thumbnail-section h4 {
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 12px;
            color: var(--text-primary);
        }

        .thumbnail-preview {
            width: 100%;
            height: auto;
            border-radius: 6px;
            margin-bottom: 8px;
            max-height: 120px;
            object-fit: cover;
        }

        /* Form Fields */
        .form-section {
            margin-bottom: 24px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 8px;
            color: var(--text-primary);
        }

        .char-count {
            font-size: 12px;
            color: var(--text-muted);
            font-weight: normal;
        }

        .form-input,
        .form-textarea,
        .form-select {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid var(--input-border);
            border-radius: 8px;
            background: var(--input-bg);
            color: var(--text-primary);
            font-size: 16px;
            font-family: inherit;
            transition: all 0.2s;
        }

        .form-input:focus,
        .form-textarea:focus,
        .form-select:focus {
            outline: none;
            border-color: var(--accent-green);
        }

        .form-textarea {
            resize: vertical;
            min-height: 100px;
        }

        /* Tags Input */
        .tags-input {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            padding: 8px;
            border: 1px solid var(--input-border);
            border-radius: 8px;
            background: var(--input-bg);
            cursor: text;
        }

        .tag {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            background: var(--tag-bg);
            border-radius: 4px;
            font-size: 14px;
            color: var(--text-primary);
        }

        .tag-remove {
            cursor: pointer;
            color: var(--text-secondary);
            font-size: 16px;
            line-height: 1;
        }

        .tag-input {
            flex: 1;
            min-width: 120px;
            border: none;
            background: none;
            outline: none;
            font-size: 14px;
            color: var(--text-primary);
        }

        /* Modal Footer */
        .modal-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 24px;
            border-top: 1px solid var(--border-color);
            gap: 12px;
        }

        .modal-footer.single-button {
            justify-content: center;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            white-space: nowrap;
        }

        .btn-primary {
            background: var(--accent-green);
            color: white;
            min-width: 200px;
            padding: 12px 40px;
        }

        .btn-primary:hover {
            background: #16a34a;
            transform: translateY(-1px);
        }

        .btn-primary:disabled {
            background: var(--text-muted);
            cursor: not-allowed;
            transform: none;
        }

        .btn-secondary {
            background: var(--bg-secondary);
            color: var(--text-primary);
            border: 1px solid var(--border-color);
            min-width: 100px;
        }

        .btn-secondary:hover {
            background: var(--bg-tertiary);
        }

        /* Success State */
        .success-message {
            text-align: center;
            padding: 48px;
        }

        .success-icon {
            width: 64px;
            height: 64px;
            color: var(--success-color);
            margin: 0 auto 24px;
        }

        .success-title {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 12px;
            color: var(--text-primary);
        }

        .success-text {
            font-size: 16px;
            color: var(--text-secondary);
            margin-bottom: 32px;
        }

        /* Loading State */
        .loading-spinner {
            width: 40px;
            height: 40px;
            border: 3px solid var(--bg-secondary);
            border-top-color: var(--accent-green);
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Responsive */
        @media (max-width: 768px) {
            .modal-container {
                max-height: 100vh;
                max-height: 100dvh;
                border-radius: 0;
                width: 100%;
                margin: 0;
            }

            .modal-overlay {
                padding: 0;
            }

            .modal-header {
                padding: 16px 20px;
            }

            .modal-body {
                padding: 20px;
            }

            .upload-options {
                grid-template-columns: 1fr;
                gap: 16px;
            }

            .upload-zone, .import-section {
                min-height: 280px;
                padding: 24px 16px;
            }

            .video-preview-section {
                grid-template-columns: 1fr;
                gap: 20px;
            }

            .video-preview.vertical {
                max-height: 300px;
            }

            .video-details-sidebar {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 16px;
            }

            .video-preview {
                aspect-ratio: 16/9;
                max-height: 200px;
            }

            .form-section h3 {
                font-size: 20px;
                margin-bottom: 20px;
            }

            .modal-footer {
                padding: 16px 20px;
                flex-wrap: wrap;
            }

            .btn {
                font-size: 15px;
                padding: 10px 20px;
            }

            .btn-primary {
                min-width: 150px;
                flex: 1;
            }

            .btn-secondary {
                min-width: 80px;
            }

            /* When both buttons are visible on mobile */
            .modal-footer:not(.single-button) .btn-primary {
                order: 1;
                width: 100%;
                margin-bottom: 12px;
            }

            .modal-footer:not(.single-button) .btn-secondary {
                order: 2;
                width: 100%;
            }
        }

        @media (max-width: 480px) {
            .modal-title {
                font-size: 18px;
            }

            .modal-title svg {
                width: 20px;
                height: 20px;
            }

            .upload-zone h3, .import-section h3 {
                font-size: 16px;
            }

            .upload-icon {
                width: 40px;
                height: 40px;
            }

            .upload-text {
                font-size: 15px;
            }

            .upload-subtext {
                font-size: 13px;
            }

            .upload-requirements h4 {
                font-size: 12px;
            }

            .upload-requirements li {
                font-size: 12px;
                margin-bottom: 6px;
            }

            .platform-btn {
                padding: 6px 12px;
                font-size: 13px;
            }

            .form-label {
                font-size: 13px;
            }

            .form-input, .form-textarea, .form-select {
                font-size: 15px;
                padding: 10px 14px;
            }

            .progress-text {
                font-size: 12px;
                right: 16px;
            }
        }

        /* Tablet specific adjustments */
        @media (min-width: 481px) and (max-width: 768px) {
            .modal-container {
                max-width: 90%;
                margin: 20px;
                border-radius: 12px;
            }

            .upload-options {
                gap: 20px;
            }

            .btn-primary {
                min-width: 180px;
            }
        }

        /* Landscape phone adjustments */
        @media (max-height: 600px) and (orientation: landscape) {
            .modal-container {
                max-height: 100vh;
                max-height: 100dvh;
            }

            .modal-body {
                padding: 16px 20px;
            }

            .upload-zone, .import-section {
                min-height: 220px;
                padding: 20px 16px;
            }

            .video-preview {
                max-height: 150px;
            }
        }
    </style>
</head>
<body data-theme="<?= strtolower($user_preferences['Theme']) ?>">
    <div class="modal-overlay">
        <div class="modal-container">
            <!-- Modal Header -->
            <div class="modal-header">
                <div class="modal-header-content">
                    <div class="modal-title">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                            <path d="M14 2v6h6"></path>
                            <path d="M10 12l-1 1 3 3 5-5-1-1"></path>
                        </svg>
                        <span id="modalTitle">Upload Video</span>
                    </div>
                    <div class="modal-subtitle" id="fileReference" style="display: none;">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"></path>
                            <polyline points="13 2 13 9 20 9"></polyline>
                        </svg>
                        <span class="file-reference" id="fileReferenceName"></span>
                        <span class="video-id-badge" id="videoIdBadge" style="display: none;"></span>
                    </div>
                </div>
                <button class="close-btn" onclick="closeModal()">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M18 6L6 18M6 6l12 12"></path>
                    </svg>
                </button>
                
                <!-- Progress Bar -->
                <div class="progress-bar-container" id="progressContainer">
                    <div class="progress-bar" id="progressBar" style="display: none;"></div>
                    <div class="loading-progress" id="loadingProgress"></div>
                </div>
            </div>

            <!-- Modal Body -->
            <div class="modal-body">
                <!-- Step 1: Upload/Import Selection -->
                <div id="step1" class="step-content">
                    <div class="upload-options">
                        <!-- Upload Video Option -->
                        <div class="upload-option">
                            <h3>Upload Video</h3>
                            <div class="upload-zone" id="uploadZone" onclick="document.getElementById('fileInput').click()">
                                <svg class="upload-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M12 2v20M2 12h20"></path>
                                    <path d="M7 7l5-5 5 5M7 17l5 5 5-5"></path>
                                </svg>
                                <div class="upload-text">Drop your video here</div>
                                <div class="upload-subtext">or browse files</div>
                                
                                <div class="upload-requirements">
                                    <h4>Video Requirements</h4>
                                    <ul>
                                        <li>Maximum file size: 20GB</li>
                                        <li>Supported formats: MP4, WebM, MOV, OGG, AVI</li>
                                        <li>Recommended: H.264 codec for best compatibility</li>
                                        <li>Videos will be automatically optimized for streaming</li>
                                    </ul>
                                </div>
                            </div>
                            <input type="file" id="fileInput" class="file-input" accept="video/mp4,video/webm,video/quicktime,video/ogg,video/x-msvideo" />
                        </div>

                        <!-- Import Video Option -->
                        <div class="upload-option">
                            <h3>Import Video</h3>
                            <div class="import-section">
                                <svg class="upload-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="12" r="10"></circle>
                                    <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path>
                                    <path d="M2 12h20"></path>
                                </svg>
                                <div class="upload-text">Import from URL</div>
                                <div class="upload-subtext">Import videos from platforms</div>
                                
                                <div class="import-platforms">
                                    <button class="platform-btn youtube" onclick="selectPlatform('youtube')">
                                        <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
                                            <path d="M8.051 1.999h.089c.822.003 4.987.033 6.11.335a2.01 2.01 0 0 1 1.415 1.42c.101.38.172.883.22 1.402l.01.104.022.26.008.042c.065.52.073 1.585.073 1.585s-.008 1.05-.073 1.585l-.008.042-.022.26-.01.104c-.048.519-.119 1.023-.22 1.402a2.007 2.007 0 0 1-1.415 1.42c-1.16.312-5.569.334-6.18.335h-.089c-.611-.001-5.02-.023-6.18-.335a2.007 2.007 0 0 1-1.415-1.42c-.101-.38-.172-.883-.22-1.402l-.01-.104-.022-.26-.008-.042C.025 7.908.025 7.506.025 7.104s0-.856.058-1.585l.008-.042.022-.26.01-.104c.048-.519.119-1.023.22-1.402a2.007 2.007 0 0 1 1.415-1.42C2.918 1.999 7.927 1.966 8.04 1.965h.089zm-.36 2.984L5.456 8.146v2.329l3.64-2.159 3.64-2.159-3.64-2.174z"/>
                                        </svg>
                                        YouTube
                                    </button>
                                    <button class="platform-btn vimeo" onclick="selectPlatform('vimeo')">
                                        <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
                                            <path d="M15.992 4.204c-.071 1.556-1.158 3.687-3.262 6.393C10.576 13.297 8.756 14.645 7.191 14.645c-.932 0-1.722-.865-2.37-2.594L3.474 6.982c-.499-1.796-.519-1.796-2.31-.5L0 5.016c1.86-1.643 3.699-3.464 4.871-3.515 1.285-.124 2.075.755 2.37 2.636.319 2.037.539 3.305.661 3.804.367 1.669.77 2.503 1.211 2.503.342 0 .856-.542 1.54-1.627.685-1.083 1.052-1.909 1.1-2.477.098-.935-.269-1.403-1.1-1.403-.393 0-.797.09-1.214.269.806-2.641 2.345-3.924 4.616-3.85 1.684.05 2.478 1.14 2.382 3.27z"/>
                                        </svg>
                                        Vimeo
                                    </button>
                                    <button class="platform-btn muvi" onclick="selectPlatform('muvi')">
                                        <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
                                            <circle cx="8" cy="8" r="8"/>
                                        </svg>
                                        Muvi
                                    </button>
                                </div>

                                <div class="url-input-group">
                                    <input type="url" class="url-input" id="videoUrl" placeholder="Enter video URL">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Step 2: Video Details -->
                <div id="step2" class="step-content" style="display: none;">
                    <div class="video-preview-section">
                        <!-- Video Preview -->
                        <div class="video-preview-container">
                            <div id="videoPreviewWrapper" class="video-preview horizontal">
                                <video id="videoPreview" controls style="display: none;">
                                    <source src="" type="video/mp4">
                                </video>
                                <iframe id="videoPreviewIframe" style="display: none;" frameborder="0" allowfullscreen></iframe>
                            </div>
                        </div>

                        <!-- Sidebar with Info and Thumbnail -->
                        <div class="video-details-sidebar">
                            <!-- Video Information -->
                            <div class="video-info">
                                <h4>Video Details</h4>
                                <div class="video-info-item">
                                    <span>Duration:</span>
                                    <strong id="videoDuration">0:00</strong>
                                </div>
                                <div class="video-info-item">
                                    <span>Resolution:</span>
                                    <strong id="videoResolution">--</strong>
                                </div>
                                <div class="video-info-item">
                                    <span>Size:</span>
                                    <strong id="videoSize">--</strong>
                                </div>
                                <div class="video-info-item" id="formatInfo" style="display: none;">
                                    <span>Format:</span>
                                    <strong id="videoFormat">Horizontal</strong>
                                </div>
                            </div>

                            <!-- Thumbnail Selection -->
                            <div class="thumbnail-section" onclick="document.getElementById('thumbnailInput').click()">
                                <h4>Thumbnail</h4>
                                <img id="thumbnailPreview" class="thumbnail-preview" style="display: none;">
                                <div id="thumbnailPlaceholder">
                                    <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin: 0 auto 8px;">
                                        <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                                        <circle cx="8.5" cy="8.5" r="1.5"></circle>
                                        <path d="M21 15l-5-5L5 21"></path>
                                    </svg>
                                    <div style="font-size: 13px;">Click to upload</div>
                                    <div style="font-size: 11px; color: var(--text-muted); margin-top: 4px;">or auto-generated</div>
                                </div>
                            </div>
                            <input type="file" id="thumbnailInput" accept="image/*" style="display: none;">
                        </div>
                    </div>

                    <!-- Video Details Form -->
                    <div class="form-section">
                        <h3 style="margin-bottom: 24px;">Video Details</h3>
                        
                        <div class="form-group">
                            <label class="form-label">
                                Title *
                                <span class="char-count"><span id="titleCount">0</span>/100 characters</span>
                            </label>
                            <input type="text" class="form-input" id="videoTitle" placeholder="Enter video title" maxlength="100" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                Description
                                <span class="char-count"><span id="descCount">0</span>/500 characters</span>
                            </label>
                            <textarea class="form-textarea" id="videoDescription" placeholder="Describe your video" maxlength="500"></textarea>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Tags</label>
                            <div class="tags-input" id="tagsContainer" onclick="document.getElementById('tagInput').focus()">
                                <input type="text" class="tag-input" id="tagInput" placeholder="tag1, tag2, tag3">
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">ION City</label>
                            <select class="form-select" id="citySelect" required>
                                <option value="">Select city</option>
                                <?php
                                // Get available cities from IONChannels table
                                $cities = $wpdb->get_results("SELECT DISTINCT slug, city FROM IONChannels WHERE status = 'active' ORDER BY city");
                                foreach ($cities as $city):
                                ?>
                                    <option value="<?= htmlspecialchars($city->slug) ?>"><?= htmlspecialchars($city->city) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">ION Category</label>
                            <select class="form-select" id="categorySelect" required>
                                <option value="">Select category</option>
                                <option value="Sports">Sports</option>
                                <option value="Entertainment">Entertainment</option>
                                <option value="Business">Business</option>
                                <option value="Events">Events</option>
                                <option value="News">News</option>
                                <option value="Kids">Kids</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Step 3: Success -->
                <div id="step3" class="step-content" style="display: none;">
                    <div class="success-message">
                        <svg class="success-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                            <path d="M22 4L12 14.01l-3-3"></path>
                        </svg>
                        <h2 class="success-title">Video Uploaded Successfully!</h2>
                        <p class="success-text">Your video has been uploaded and will be available on the ION channel shortly.</p>
                        <button class="btn btn-primary" onclick="resetUpload()">
                            Upload Another Video
                        </button>
                    </div>
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="modal-footer single-button" id="modalFooter">
                <button class="btn btn-secondary" id="backBtn" onclick="goBack()" style="display: none;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M19 12H5M12 19l-7-7 7-7"></path>
                    </svg>
                    Back
                </button>
                <button class="btn btn-primary" id="nextBtn" onclick="goNext()">
                    Next
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M5 12h14M12 5l7 7-7 7"></path>
                    </svg>
                </button>
                <button class="btn btn-primary" id="uploadBtn" onclick="uploadVideo()" style="display: none;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                        <path d="M17 8l-5-5-5 5"></path>
                        <path d="M12 3v12"></path>
                    </svg>
                    <span id="uploadBtnText">Upload Video</span>
                </button>
            </div>
        </div>
    </div>

    <script>
        // State management
        let currentStep = 1;
        let selectedFile = null;
        let selectedPlatform = null;
        let selectedThumbnail = null;
        let tags = [];
        let uploadType = null; // 'file' or 'url'
        let currentVideoId = null;
        let currentVideoUrl = null;

        // DOM Elements
        const step1 = document.getElementById('step1');
        const step2 = document.getElementById('step2');
        const step3 = document.getElementById('step3');
        const nextBtn = document.getElementById('nextBtn');
        const backBtn = document.getElementById('backBtn');
        const uploadBtn = document.getElementById('uploadBtn');
        const progressContainer = document.getElementById('progressContainer');
        const progressBar = document.getElementById('progressBar');
        const progressText = document.getElementById('progressText');

        // File Input Handler
        document.getElementById('fileInput').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                // Validate file
                const maxSize = 20 * 1024 * 1024 * 1024; // 20GB
                if (file.size > maxSize) {
                    alert('File size exceeds 20GB limit');
                    return;
                }

                selectedFile = file;
                uploadType = 'file';
                
                // Update modal title
                document.getElementById('modalTitle').textContent = 'Upload Video';
                document.getElementById('uploadBtnText').textContent = 'Upload Video';
                
                document.getElementById('uploadZone').innerHTML = `
                    <svg class="upload-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M9 13l3 3 8-8"></path>
                        <path d="M21 10v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-9"></path>
                    </svg>
                    <div class="upload-text">${file.name}</div>
                    <div class="upload-subtext">${formatFileSize(file.size)}</div>
                    <div class="upload-requirements">
                        <h4>Ready to upload</h4>
                        <ul>
                            <li>File type: ${file.type}</li>
                            <li>Click "Next" to continue</li>
                        </ul>
                    </div>
                `;
            }
        });

        // Drag and Drop
        const uploadZone = document.getElementById('uploadZone');
        
        uploadZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadZone.classList.add('dragover');
        });

        uploadZone.addEventListener('dragleave', () => {
            uploadZone.classList.remove('dragover');
        });

        uploadZone.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadZone.classList.remove('dragover');
            
            const file = e.dataTransfer.files[0];
            if (file && file.type.startsWith('video/')) {
                document.getElementById('fileInput').files = e.dataTransfer.files;
                const event = new Event('change', { bubbles: true });
                document.getElementById('fileInput').dispatchEvent(event);
            }
        });

        // URL Input Handler
        document.getElementById('videoUrl').addEventListener('input', function(e) {
            if (e.target.value) {
                uploadType = 'url';
                currentVideoUrl = e.target.value;
                
                // Update modal title for import
                document.getElementById('modalTitle').textContent = 'Import Video';
                document.getElementById('uploadBtnText').textContent = 'Import Video';
            }
        });

        // Platform Selection
        function selectPlatform(platform) {
            selectedPlatform = platform;
            document.querySelectorAll('.platform-btn').forEach(btn => {
                btn.style.border = btn.classList.contains(platform) ? '2px solid var(--accent-green)' : '1px solid var(--border-color)';
            });
        }

        // Character Counters
        document.getElementById('videoTitle').addEventListener('input', function(e) {
            document.getElementById('titleCount').textContent = e.target.value.length;
        });

        document.getElementById('videoDescription').addEventListener('input', function(e) {
            document.getElementById('descCount').textContent = e.target.value.length;
        });

        // Tags Management
        const tagInput = document.getElementById('tagInput');
        const tagsContainer = document.getElementById('tagsContainer');

        tagInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ',') {
                e.preventDefault();
                const tag = e.target.value.trim();
                if (tag && !tags.includes(tag)) {
                    addTag(tag);
                    e.target.value = '';
                }
            }
        });

        function addTag(tag) {
            tags.push(tag);
            const tagEl = document.createElement('div');
            tagEl.className = 'tag';
            tagEl.innerHTML = `
                ${tag}
                <span class="tag-remove" onclick="removeTag('${tag}')">&times;</span>
            `;
            tagsContainer.insertBefore(tagEl, tagInput);
        }

        function removeTag(tag) {
            tags = tags.filter(t => t !== tag);
            renderTags();
        }

        function renderTags() {
            const existingTags = tagsContainer.querySelectorAll('.tag');
            existingTags.forEach(tag => tag.remove());
            tags.forEach(tag => addTag(tag));
        }

        // Thumbnail Handler
        document.getElementById('thumbnailInput').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file && file.type.startsWith('image/')) {
                selectedThumbnail = file;
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('thumbnailPreview').src = e.target.result;
                    document.getElementById('thumbnailPreview').style.display = 'block';
                    document.getElementById('thumbnailPlaceholder').style.display = 'none';
                };
                reader.readAsDataURL(file);
            }
        });

        // Navigation
        function goNext() {
            if (currentStep === 1) {
                // Validate step 1
                if (uploadType === 'file' && !selectedFile) {
                    alert('Please select a video file');
                    return;
                }
                if (uploadType === 'url' && !document.getElementById('videoUrl').value) {
                    alert('Please enter a video URL');
                    return;
                }

                // Show loading progress
                document.getElementById('progressContainer').classList.add('active');
                document.getElementById('loadingProgress').style.display = 'block';
                document.getElementById('progressBar').style.display = 'none';

                // Load video preview
                if (uploadType === 'file') {
                    // Show file reference
                    document.getElementById('fileReference').style.display = 'flex';
                    document.getElementById('fileReferenceName').textContent = selectedFile.name;
                    
                    loadVideoPreview(selectedFile);
                } else {
                    // Show URL reference
                    const url = document.getElementById('videoUrl').value;
                    document.getElementById('fileReference').style.display = 'flex';
                    document.getElementById('fileReferenceName').textContent = new URL(url).hostname;
                    
                    loadUrlPreview(url);
                }

                // Show step 2 after a brief delay to show loading
                setTimeout(() => {
                    document.getElementById('progressContainer').classList.remove('active');
                    step1.style.display = 'none';
                    step2.style.display = 'block';
                    nextBtn.style.display = 'none';
                    uploadBtn.style.display = 'flex';
                    backBtn.style.display = 'block';
                    document.getElementById('modalFooter').classList.remove('single-button');
                    currentStep = 2;
                }, 800);
            }
        }

        function goBack() {
            if (currentStep === 2) {
                step2.style.display = 'none';
                step1.style.display = 'block';
                nextBtn.style.display = 'flex';
                uploadBtn.style.display = 'none';
                backBtn.style.display = 'none';
                document.getElementById('modalFooter').classList.add('single-button');
                currentStep = 1;
            }
        }

        function loadVideoPreview(file) {
            const videoPreview = document.getElementById('videoPreview');
            const videoIframe = document.getElementById('videoPreviewIframe');
            const url = URL.createObjectURL(file);
            
            videoPreview.src = url;
            videoPreview.style.display = 'block';
            videoIframe.style.display = 'none';
            
            videoPreview.addEventListener('loadedmetadata', function() {
                // Set video info
                const duration = formatDuration(videoPreview.duration);
                document.getElementById('videoDuration').textContent = duration;
                document.getElementById('videoResolution').textContent = `${videoPreview.videoWidth}x${videoPreview.videoHeight}`;
                document.getElementById('videoSize').textContent = formatFileSize(file.size);
                
                // Check if vertical video
                if (videoPreview.videoHeight > videoPreview.videoWidth) {
                    document.getElementById('videoPreviewWrapper').className = 'video-preview vertical';
                    document.getElementById('videoFormat').textContent = 'Vertical/Shorts';
                } else {
                    document.getElementById('videoPreviewWrapper').className = 'video-preview horizontal';
                    document.getElementById('videoFormat').textContent = 'Horizontal';
                }
                document.getElementById('formatInfo').style.display = 'flex';
                
                // Generate thumbnail
                generateThumbnail(videoPreview);
            });
        }

        function loadUrlPreview(url) {
            const videoPreview = document.getElementById('videoPreview');
            const videoIframe = document.getElementById('videoPreviewIframe');
            
            // Handle different platforms
            if (selectedPlatform === 'youtube') {
                const videoId = extractYouTubeId(url);
                if (videoId) {
                    currentVideoId = videoId;
                    
                    // Show video ID in header
                    document.getElementById('videoIdBadge').textContent = `ID: ${videoId}`;
                    document.getElementById('videoIdBadge').style.display = 'inline-block';
                    
                    videoIframe.src = `https://www.youtube.com/embed/${videoId}`;
                    videoIframe.style.display = 'block';
                    videoPreview.style.display = 'none';
                    
                    // Get thumbnail
                    const thumbnailUrl = `https://img.youtube.com/vi/${videoId}/maxresdefault.jpg`;
                    checkAndLoadThumbnail(thumbnailUrl, videoId);
                    
                    // Set info for YouTube
                    document.getElementById('videoDuration').textContent = 'YouTube Video';
                    document.getElementById('videoResolution').textContent = 'HD';
                    document.getElementById('videoSize').textContent = 'Streaming';
                    
                    // Try to detect if it's a YouTube Short
                    if (url.includes('/shorts/')) {
                        document.getElementById('videoPreviewWrapper').className = 'video-preview vertical';
                        document.getElementById('videoFormat').textContent = 'YouTube Shorts';
                        document.getElementById('formatInfo').style.display = 'flex';
                    }
                }
            } else if (selectedPlatform === 'vimeo') {
                const vimeoId = extractVimeoId(url);
                if (vimeoId) {
                    currentVideoId = vimeoId;
                    
                    // Show video ID in header
                    document.getElementById('videoIdBadge').textContent = `ID: ${vimeoId}`;
                    document.getElementById('videoIdBadge').style.display = 'inline-block';
                    
                    videoIframe.src = `https://player.vimeo.com/video/${vimeoId}`;
                    videoIframe.style.display = 'block';
                    videoPreview.style.display = 'none';
                    
                    // Get Vimeo thumbnail via API
                    fetchVimeoThumbnail(vimeoId);
                    
                    document.getElementById('videoDuration').textContent = 'Vimeo Video';
                    document.getElementById('videoResolution').textContent = 'HD';
                    document.getElementById('videoSize').textContent = 'Streaming';
                }
            } else if (selectedPlatform === 'muvi') {
                // Handle Muvi embed
                const muviId = extractMuviId(url);
                if (muviId) {
                    currentVideoId = muviId;
                    
                    // Show video ID in header
                    document.getElementById('videoIdBadge').textContent = `ID: ${muviId}`;
                    document.getElementById('videoIdBadge').style.display = 'inline-block';
                    
                    videoIframe.src = `https://embed.muvi.com/embed/${muviId}`;
                    videoIframe.style.display = 'block';
                    videoPreview.style.display = 'none';
                    
                    document.getElementById('videoDuration').textContent = 'Muvi Video';
                    document.getElementById('videoResolution').textContent = 'HD';
                    document.getElementById('videoSize').textContent = 'Streaming';
                    
                    // Show placeholder for thumbnail
                    document.getElementById('thumbnailPlaceholder').innerHTML = `
                        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin: 0 auto 8px;">
                            <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                            <circle cx="8.5" cy="8.5" r="1.5"></circle>
                            <path d="M21 15l-5-5L5 21"></path>
                        </svg>
                        <div style="font-size: 13px; color: var(--accent-green);">Please upload thumbnail</div>
                        <div style="font-size: 11px; color: var(--text-muted); margin-top: 4px;">Required for Muvi videos</div>
                    `;
                }
            } else if (url.includes('drive.google.com')) {
                // Handle Google Drive videos
                const driveId = extractGoogleDriveId(url);
                if (driveId) {
                    currentVideoId = driveId;
                    
                    // Show video ID in header
                    document.getElementById('videoIdBadge').textContent = `ID: ${driveId}`;
                    document.getElementById('videoIdBadge').style.display = 'inline-block';
                    
                    videoIframe.src = `https://drive.google.com/file/d/${driveId}/preview`;
                    videoIframe.style.display = 'block';
                    videoPreview.style.display = 'none';
                    
                    document.getElementById('videoDuration').textContent = 'Google Drive Video';
                    document.getElementById('videoResolution').textContent = 'Variable';
                    document.getElementById('videoSize').textContent = 'Streaming';
                    
                    // Show placeholder for thumbnail
                    document.getElementById('thumbnailPlaceholder').innerHTML = `
                        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin: 0 auto 8px;">
                            <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                            <circle cx="8.5" cy="8.5" r="1.5"></circle>
                            <path d="M21 15l-5-5L5 21"></path>
                        </svg>
                        <div style="font-size: 13px; color: var(--accent-green);">Please upload thumbnail</div>
                        <div style="font-size: 11px; color: var(--text-muted); margin-top: 4px;">Required for Drive videos</div>
                    `;
                }
            }
        }

        function checkAndLoadThumbnail(thumbnailUrl, videoId) {
            const img = new Image();
            img.onload = function() {
                if (img.width > 120) { // maxresdefault exists
                    setThumbnail(thumbnailUrl);
                } else {
                    // Fallback to hqdefault
                    setThumbnail(`https://img.youtube.com/vi/${videoId}/hqdefault.jpg`);
                }
            };
            img.onerror = function() {
                // Fallback to hqdefault
                setThumbnail(`https://img.youtube.com/vi/${videoId}/hqdefault.jpg`);
            };
            img.src = thumbnailUrl;
        }

        function setThumbnail(url) {
            document.getElementById('thumbnailPreview').src = url;
            document.getElementById('thumbnailPreview').style.display = 'block';
            document.getElementById('thumbnailPlaceholder').style.display = 'none';
        }

        function fetchVimeoThumbnail(videoId) {
            fetch(`https://vimeo.com/api/v2/video/${videoId}.json`)
                .then(response => response.json())
                .then(data => {
                    if (data[0] && data[0].thumbnail_large) {
                        setThumbnail(data[0].thumbnail_large);
                    }
                })
                .catch(() => {
                    console.log('Could not fetch Vimeo thumbnail');
                });
        }

        function generateThumbnail(video) {
            const canvas = document.createElement('canvas');
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            const ctx = canvas.getContext('2d');
            
            // Seek to 5 seconds or 10% of video
            video.currentTime = Math.min(5, video.duration * 0.1);
            video.onseeked = function() {
                ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
                canvas.toBlob(function(blob) {
                    const url = URL.createObjectURL(blob);
                    setThumbnail(url);
                    
                    // Store as default thumbnail
                    if (!selectedThumbnail) {
                        selectedThumbnail = blob;
                    }
                }, 'image/jpeg', 0.85);
            };
        }

        // Upload Handler
        async function uploadVideo() {
            // Validate form
            const title = document.getElementById('videoTitle').value;
            const city = document.getElementById('citySelect').value;
            const category = document.getElementById('categorySelect').value;

            if (!title || !city || !category) {
                alert('Please fill in all required fields');
                return;
            }

            // Check if thumbnail is required but missing
            if (uploadType === 'url' && (selectedPlatform === 'muvi' || currentVideoUrl.includes('drive.google.com')) && !selectedThumbnail) {
                alert('Please upload a thumbnail for this video');
                return;
            }

            // Show progress
            progressContainer.classList.add('active');
            document.getElementById('loadingProgress').style.display = 'none';
            document.getElementById('progressBar').style.display = 'block';
            uploadBtn.disabled = true;

            const formData = new FormData();
            formData.append('title', title);
            formData.append('description', document.getElementById('videoDescription').value);
            formData.append('city_slug', city);
            formData.append('category', category);
            formData.append('tags', tags.join(','));

            if (uploadType === 'file') {
                formData.append('video', selectedFile);
                formData.append('upload_type', 'file');
            } else {
                formData.append('video_url', document.getElementById('videoUrl').value);
                formData.append('platform', selectedPlatform);
                formData.append('upload_type', 'url');
                formData.append('video_id', currentVideoId);
            }

            if (selectedThumbnail) {
                formData.append('thumbnail', selectedThumbnail);
            }

            try {
                const xhr = new XMLHttpRequest();

                xhr.upload.addEventListener('progress', (e) => {
                    if (e.lengthComputable) {
                        const percentComplete = Math.round((e.loaded / e.total) * 100);
                        progressBar.style.width = percentComplete + '%';
                    }
                });

                xhr.addEventListener('load', function() {
                    if (xhr.status === 200) {
                        const response = JSON.parse(xhr.responseText);
                        if (response.success) {
                            showSuccess();
                        } else {
                            alert('Upload failed: ' + (response.data || 'Unknown error'));
                            uploadBtn.disabled = false;
                            progressContainer.classList.remove('active');
                        }
                    }
                });

                xhr.addEventListener('error', function() {
                    alert('Upload failed: Network error');
                    uploadBtn.disabled = false;
                    progressContainer.classList.remove('active');
                });

                xhr.open('POST', 'upload-video-handler.php');
                xhr.send(formData);

            } catch (error) {
                alert('Upload failed: ' + error.message);
                uploadBtn.disabled = false;
                progressContainer.classList.remove('active');
            }
        }

        function showSuccess() {
            step2.style.display = 'none';
            step3.style.display = 'block';
            uploadBtn.style.display = 'none';
            backBtn.style.display = 'none';
            progressContainer.classList.remove('active');
        }

        function resetUpload() {
            // Reset all state
            currentStep = 1;
            selectedFile = null;
            selectedPlatform = null;
            selectedThumbnail = null;
            tags = [];
            uploadType = null;

            // Reset UI
            document.getElementById('videoUploadForm')?.reset();
            step3.style.display = 'none';
            step1.style.display = 'block';
            nextBtn.style.display = 'flex';
            uploadBtn.style.display = 'none';
            backBtn.style.display = 'none';
            progressBar.style.width = '0%';
            progressText.textContent = '0%';

            // Reset file input
            document.getElementById('fileInput').value = '';
            uploadZone.innerHTML = `
                <svg class="upload-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M12 2v20M2 12h20"></path>
                    <path d="M7 7l5-5 5 5M7 17l5 5 5-5"></path>
                </svg>
                <div class="upload-text">Drop your video here</div>
                <div class="upload-subtext">or browse files</div>
            `;
        }

        function closeModal() {
            if (confirm('Are you sure you want to close? Any unsaved changes will be lost.')) {
                // Redirect to the ION VIDS section
                window.location.href = 'creators.php';
            }
        }

        // Utility Functions
        function formatFileSize(bytes) {
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            if (bytes === 0) return '0 Bytes';
            const i = parseInt(Math.floor(Math.log(bytes) / Math.log(1024)));
            return Math.round(bytes / Math.pow(1024, i) * 100) / 100 + ' ' + sizes[i];
        }

        function formatDuration(seconds) {
            const h = Math.floor(seconds / 3600);
            const m = Math.floor((seconds % 3600) / 60);
            const s = Math.floor(seconds % 60);
            
            if (h > 0) {
                return `${h}:${m.toString().padStart(2, '0')}:${s.toString().padStart(2, '0')}`;
            }
            return `${m}:${s.toString().padStart(2, '0')}`;
        }

        function extractYouTubeId(url) {
            const match = url.match(/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/|youtube\.com\/shorts\/)([^"&?\/\s]{11})/);
            return match ? match[1] : null;
        }

        function extractVimeoId(url) {
            const match = url.match(/vimeo\.com\/(\d+)/);
            return match ? match[1] : null;
        }

        function extractMuviId(url) {
            const match = url.match(/(?:embed\.muvi\.com\/embed\/|muvi\.com\/.*\/)([^\/\?]+)/);
            return match ? match[1] : null;
        }

        function extractGoogleDriveId(url) {
            const match = url.match(/\/d\/([a-zA-Z0-9-_]+)/);
            return match ? match[1] : null;
        }

        // Theme Toggle (optional)
        function toggleTheme() {
            const currentTheme = document.body.getAttribute('data-theme');
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            document.body.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);
        }

        // Load saved theme
        const savedTheme = localStorage.getItem('theme') || 'dark';
        document.body.setAttribute('data-theme', savedTheme);
    </script>
</body>
</html>