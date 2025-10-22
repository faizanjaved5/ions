// video-modal.js 
(function() {
    console.log('🎥 Video Modal: Script loaded');
    // Configuration Options
    const defaultConfig = {
        autoplay      : true,       // true: video starts playing automatically when modal opens (with fallback to muted if needed); false: user must manually start playback
        muted         : false,      // false: video plays with sound by default; true: video starts muted, useful for autoplay compliance in browsers
        theme         : 'dark',     // 'dark': dark background and white close button; 'light': light background and black close button, applies corresponding CSS classes to modal
        playerTheme   : 'flat',     // 'default': modal has border, box-shadow, and sharp corners (12px radius on container/iframe/video); 'flat': no border, no shadow, rounded corners (20px radius on content, container, iframe/video)
        modalSize     : 'medium',   // 'medium': 90% width/80% height (default); 'small': 60% width/50% height; 'large': 95% width/90% height; can also be an object like {width: '80%', maxWidth: '1000px', height: '70%', maxHeight: '700px'} for custom dimensions
        videoFormat   : 'wide',     // 'wide': landscape orientation (default); 'vertical': portrait orientation (taller and narrower modal)
        showControls  : true,       // true: displays video controls (play/pause, volume, etc.); false: hides controls, depending on platform support (e.g., passed as params to embeds)
        requireAuth   : false,      // false: no authentication check; true: requires checkAuth() to return true before opening modal, otherwise modal doesn't open
        onPlay        : null,       // null: no callback; function: custom function called when video plays, receives object with {type, url}
        onError       : null,       // null: no callback; function: custom function called on error, receives error message string
        loadingSpinner: true,       // true: shows "Loading..." spinner while video loads; false: no spinner, modal appears blank until loaded
        trapFocus     : true,       // true: traps keyboard focus within modal (Tab cycles through focusable elements); false: no focus trapping, allows Tab to escape modal
        customPlayers : {},         // {}: no custom players; object: keys as video types (e.g., 'youtube'), values as objects with 'embed' function to override default player creation
        cssVariables  : {
            '--modal-bg': 'rgba(20, 20, 20, 0.95)', // Custom background color for modal content (rgba or other CSS color); affects .video-modal-content background
            '--close-color': '#fff' // Custom color for close button (any CSS color); affects .video-close color
        }
    };
    
    const config = Object.assign(defaultConfig, window.VideoModalConfig || {});
    console.log('🎥 Video Modal: Config loaded', config);
    // Video Type Detection
    function getVideoType(url) {
        console.log('🔍 Detecting video type for:', url);
       
        if (!url) {
            console.warn('❌ No URL provided');
            return { type: 'unknown' };
        }
       
        try {
            const parsedUrl = new URL(url);
            const path = parsedUrl.pathname;
            const ext = path.split('.').pop().toLowerCase().split('?')[0];
            const localVideoExtensions = ['mp4', 'webm', 'ogg', 'mov', 'avi', 'm4v', 'mkv'];
            // Platform-specific checks
            if (url.includes('youtube.com') || url.includes('youtu.be')) {
                console.log('✅ Detected: YouTube');
                return { type: 'youtube' };
            }
            if (url.includes('vimeo.com')) {
                console.log('✅ Detected: Vimeo');
                return { type: 'vimeo' };
            }
            if (url.includes('muvi.com')) {
                console.log('✅ Detected: Muvi');
                return { type: 'muvi' };
            }
            if (url.includes('wistia.com') || url.includes('wi.st')) {
                console.log('✅ Detected: Wistia');
                return { type: 'wistia' };
            }
            if (url.includes('rumble.com')) {
                console.log('✅ Detected: Rumble');
                return { type: 'rumble' };
            }
            if (url.includes('loom.com')) {
                console.log('✅ Detected: Loom');
                return { type: 'loom' };
            }
            // Local video by extension
            if (localVideoExtensions.includes(ext)) {
                console.log('✅ Detected: Local video -', ext);
                return { type: 'local', format: ext };
            }
            // Known hosting sources
            if (url.includes('/uploads/') || url.includes('ions.com') || url.includes('r2.cloudflarestorage.com') ||
                url.includes('.r2.dev') || url.includes('vid.ions.com') || url.includes('cloudflare')) {
                const filename = path.split('/').pop();
                const fileExt = filename.split('.').pop().toLowerCase().split('?')[0];
                if (localVideoExtensions.includes(fileExt)) {
                    console.log('✅ Detected: Hosted video -', fileExt);
                    return { type: 'local', format: fileExt };
                }
                console.log('✅ Detected: Hosted video (fallback to mp4)');
                return { type: 'local', format: 'mp4' };
            }
            console.log('⚠️ Unknown type, defaulting to local mp4');
            return { type: 'local', format: 'mp4' };
        } catch (e) {
            console.error('❌ Invalid URL provided:', url, e);
            return { type: 'unknown' };
        }
    }
    // Helper: Extract ID from URL for platforms
    function extractIdFromUrl(type, url) {
        console.log('🔍 Extracting ID for type:', type, 'from:', url);
        let match;
        switch (type) {
            case 'youtube':
                match = url.match(/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/i);
                break;
            case 'vimeo':
                match = url.match(/vimeo\.com\/(?:channels\/(?:\w+\/)?|groups\/(?:[^\/]+\/videos\/)|album\/(?:\d+\/video\/)|video\/|)(\d+)(?:$|\/|\?)/i);
                break;
            case 'muvi':
                match = url.match(/muvi\.com\/(?:embed\/|player\/|video\/)?([a-z0-9]+)/i);
                break;
            case 'wistia':
                match = url.match(/(?:wistia\.com|wi\.st)\/(?:medias|embed\/iframe)\/([a-z0-9]+)/i);
                break;
            case 'rumble':
                match = url.match(/rumble\.com\/(?:embed\/)?v?([a-z0-9]+)(?:-|\.html|$|\/|\?)/i);
                break;
            case 'loom':
                match = url.match(/loom\.com\/(?:share|embed)\/([a-f0-9]+)/i);
                break;
            default:
                console.log('❌ No ID extraction for type:', type);
                return '';
        }
        const id = match ? match[1] : '';
        console.log('🆔 Extracted ID:', id);
        return id;
    }
    // Inject Modal HTML
    function injectModalHTML() {
        if (document.getElementById('videoModal')) {
            console.log('ℹ️ Modal HTML already exists');
            return;
        }
        console.log('📝 Injecting modal HTML');
        const modalHTML = `
            <div id="videoModal" class="video-modal" role="dialog" aria-modal="true" aria-labelledby="videoModalTitle" tabindex="-1">
                <div class="video-modal-content">
                    <span class="video-close" aria-label="Close modal" role="button" tabindex="0">&times;</span>
                    <div id="videoMetadata" class="video-metadata" style="display: none;"></div> <!-- New metadata container -->
                    <div id="videoContainer"></div>
                    <div id="videoLoading" class="video-loading" style="display: none;">Loading...</div>
                    <div id="videoError" class="video-error" style="display: none;">Error loading video. Please try again.</div>
                </div>
            </div>
        `;
        document.body.insertAdjacentHTML('beforeend', modalHTML);
        console.log('✅ Modal HTML injected');
    }
    // Inject Styles
    function injectStyles() {
        if (document.getElementById('videoModalStyles')) {
            console.log('ℹ️ Modal styles already exist');
            return;
        }
        console.log('🎨 Injecting modal styles');
        const style = document.createElement('style');
        style.id = 'videoModalStyles';
        style.textContent = `
            /* Video Modal Styles */
            .video-modal {
                display: none;
                position: fixed;
                z-index: 10000;
                left: 0;
                top: 0;
                width: 100%;
                height: 100%;
                overflow: auto;
                background-color: rgba(0, 0, 0, 0.8);
                backdrop-filter: blur(10px);
                opacity: 0;
                transition: opacity 0.3s ease, visibility 0.3s ease;
                visibility: hidden;
            }
            .video-modal.is-visible {
                display: flex !important;
                align-items: center;
                justify-content: center;
                opacity: 1;
                visibility: visible;
            }
            .video-modal-content {
                position: relative;
                width: var(--modal-width, 90%);
                max-width: var(--modal-max-width, 1200px);
                height: var(--modal-height, 80%);
                max-height: var(--modal-max-height, 800px);
                background: var(--modal-bg, rgba(20, 20, 20, 0.95));
                border-radius: 16px;
                padding: 20px;
                box-shadow: 0 10px 50px rgba(0, 0, 0, 0.5);
                border: 1px solid rgba(255, 255, 255, 0.1);
                backdrop-filter: blur(20px);
            }
            .video-close {
                position: absolute;
                top: 15px;
                right: 25px;
                color: var(--close-color, #fff);
                font-size: 35px;
                font-weight: bold;
                cursor: pointer;
                z-index: 10001;
                transition: color 0.2s ease;
                background: rgba(0, 0, 0, 0.5);
                border-radius: 50%;
                width: 45px;
                height: 45px;
                display: flex;
                align-items: center;
                justify-content: center;
                line-height: 1;
            }
            .video-close:hover, .video-close:focus {
                color: #ff4444;
                background: rgba(0, 0, 0, 0.8);
            }
            #videoMetadata {  /* New styles for metadata */
                padding: 10px 0;
                color: #fff;
                text-align: center;
                font-family: Arial, sans-serif;
            }
            #videoMetadata h2 {
                margin: 0 0 5px 0;
                font-size: 1.5em;
            }
            #videoMetadata p {
                margin: 0;
                font-size: 1em;
                color: #ccc;
            }
            #videoContainer {
                width: 100%;
                height: calc(100% - 60px);  /* Adjust height to make room for metadata */
                border-radius: 12px;
                overflow: hidden;
                position: relative;
            }
            #videoContainer iframe, #videoContainer video {
                width: 100%;
                height: 100%;
                border: none;
                border-radius: 12px;
            }
            .video-loading, .video-error {
                position: absolute;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                color: #fff;
                font-size: 1.2em;
            }
            .video-error {
                color: #ff4444;
            }
            .video-js {
                width: 100%;
                height: 100%;
            }
            .vjs-big-play-button {
                font-size: 3em;
                line-height: 1.5em;
                height: 1.5em;
                width: 3em;
                position: absolute;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                padding: 0;
                cursor: pointer;
                opacity: 1;
                border: 0.06666em solid #fff;
                background-color: rgba(43, 51, 63, 0.7);
                border-radius: 0.3em;
                transition: all 0.4s;
            }
            .vjs-big-play-button:hover {
                background-color: rgba(43, 51, 63, 0.9);
            }
            .video-js .vjs-control-bar {
                background-color: rgba(43, 51, 63, 0.7);
            }
            .video-js:hover .vjs-control-bar {
                background-color: rgba(43, 51, 63, 0.9);
            }
            body.modal-open {
                overflow: hidden;
            }
            @media (max-width: 768px) {
                .video-modal-content {
                    width: var(--modal-width-mobile, 95%);
                    height: var(--modal-height-mobile, 90%);
                    padding: 15px;
                }
                #videoContainer iframe, .video-js {
                    border-radius: 8px;
                }
                .video-close {
                    top: 10px;
                    right: 15px;
                    font-size: 28px;
                    width: 35px;
                    height: 35px;
                }
            }
            @media (max-width: 480px) {
                .video-modal-content {
                    width: var(--modal-width-small, 98%);
                    height: var(--modal-height-small, 95%);
                    padding: 10px;
                }
            }
            @media (orientation: landscape) {
                .video-modal-content {
                    height: var(--modal-height-landscape, 95%);
                }
            }
            .video-modal.light .video-modal-content {
                background: rgba(255, 255, 255, 0.95);
                border: 1px solid rgba(0, 0, 0, 0.1);
            }
            .video-modal.light .video-close {
                color: #000;
                background: rgba(255, 255, 255, 0.5);
            }
            .video-modal.light .video-close:hover {
                color: #ff4444;
                background: rgba(255, 255, 255, 0.8);
            }
            .video-modal.player-flat .video-modal-content {
                border: none;
                box-shadow: none;
                border-radius: 20px;
            }
            .video-modal.player-flat #videoContainer {
                border-radius: 20px;
            }
            .video-modal.player-flat #videoContainer iframe,
            .video-modal.player-flat #videoContainer video {
                border-radius: 20px;
            }
            .video-modal.video-vertical .video-modal-content {
                width: var(--modal-width, 50%);
                max-width: var(--modal-max-width, 600px);
                height: var(--modal-height, 90%);
                max-height: var(--modal-max-height, 1000px);
            }
            .video-modal.video-vertical #videoContainer,
            .video-modal.video-vertical #videoContainer iframe,
            .video-modal.video-vertical #videoContainer video,
            .video-modal.video-vertical .video-js {
                object-fit: contain;
            }
        `;
        document.head.appendChild(style);
        console.log('✅ Modal styles injected');
        // Apply custom CSS variables
        Object.entries(config.cssVariables).forEach(([key, value]) => {
            document.documentElement.style.setProperty(key, value);
        });
    }
    // Load Video.js dynamically
    let videojsLoaded = false;
    function loadVideoJS(callback) {
        if (videojsLoaded || window.videojs) {
            console.log('ℹ️ Video.js already loaded');
            callback();
            return;
        }
        console.log('📥 Loading Video.js');
        const link = document.createElement('link');
        link.href = 'https://vjs.zencdn.net/8.6.1/video-js.css';
        link.rel = 'stylesheet';
        document.head.appendChild(link);
        const script = document.createElement('script');
        script.src = 'https://vjs.zencdn.net/8.6.1/video.min.js';
        script.onload = () => {
            videojsLoaded = true;
            console.log('✅ Video.js loaded successfully');
            callback();
        };
        script.onerror = () => {
            console.error('❌ Failed to load Video.js');
            callback();
        };
        document.head.appendChild(script);
    }
    // Modal Elements
    let modal, videoContainer, closeBtn, loading, errorDiv, metadataDiv, currentPlayer = null;  // Added metadataDiv
    // Initialize Modal
    function initModal() {
        console.log('🚀 Initializing video modal');
       
        injectModalHTML();
        injectStyles();
        modal = document.getElementById('videoModal');
        videoContainer = document.getElementById('videoContainer');
        closeBtn = document.querySelector('.video-close');
        loading = document.getElementById('videoLoading');
        errorDiv = document.getElementById('videoError');
        metadataDiv = document.getElementById('videoMetadata');  // New: Get metadata div
        if (config.theme === 'light') {
            modal.classList.add('light');
        }
        // Event Listeners
        closeBtn.addEventListener('click', closeVideoModal);
        closeBtn.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') closeVideoModal();
        });
        modal.addEventListener('click', (e) => {
            if (e.target === modal) closeVideoModal();
        });
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && modal.classList.contains('is-visible')) {
                closeVideoModal();
            }
        });
        // Focus Trap
        if (config.trapFocus) {
            modal.addEventListener('keydown', trapFocus);
        }
        // Setup Thumbnails
        setupVideoThumbnails();
       
        // Watch for new elements added to DOM
        const observer = new MutationObserver(() => {
            console.log('🔄 DOM changed, re-scanning for video thumbnails');
            setupVideoThumbnails();
        });
        observer.observe(document.body, { childList: true, subtree: true });
       
        console.log('✅ Video modal initialized');
    }
    // Focus Trap Function
    function trapFocus(e) {
        if (e.key !== 'Tab') return;
        const focusable = modal.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
        const first = focusable[0];
        const last = focusable[focusable.length - 1];
        if (e.shiftKey && document.activeElement === first) {
            last.focus();
            e.preventDefault();
        } else if (!e.shiftKey && document.activeElement === last) {
            first.focus();
            e.preventDefault();
        }
    }
    // Get per-video options from thumbnail data attributes or global config
    function getVideoOptions(thumb) {
        const options = { ...config };
        if (thumb) {
            options.autoplay = thumb.getAttribute('data-autoplay') !== null ? thumb.getAttribute('data-autoplay') === 'true' : config.autoplay;
            options.muted = thumb.getAttribute('data-muted') !== null ? thumb.getAttribute('data-muted') === 'true' : config.muted;
            options.showControls = thumb.getAttribute('data-show-controls') !== null ? thumb.getAttribute('data-show-controls') === 'true' : config.showControls;
            const size = thumb.getAttribute('data-modal-size');
            if (size) options.modalSize = size;
            options.playerTheme = thumb.getAttribute('data-player-theme') || config.playerTheme;
            options.videoFormat = thumb.getAttribute('data-video-format') || config.videoFormat;
            // New: Read metadata from data attributes
            options.title = thumb.getAttribute('data-title') || 'Untitled Video';
            options.publishedDate = thumb.getAttribute('data-published-date') || '';
        }
        return options;
    }
    // Apply modal size
    function applyModalSize(size, format) {
        let width = '90%', maxWidth = '1200px', height = '80%', maxHeight = '800px';
        if (typeof size === 'string') {
            switch (size) {
                case 'small':
                    width = '60%'; maxWidth = '800px'; height = '50%'; maxHeight = '600px';
                    break;
                case 'large':
                    width = '95%'; maxWidth = 'none'; height = '90%'; maxHeight = 'none';
                    break;
            }
        } else if (typeof size === 'object') {
            width = size.width || width;
            maxWidth = size.maxWidth || maxWidth;
            height = size.height || height;
            maxHeight = size.maxHeight || maxHeight;
        }
        // Adjust based on video format
        if (format === 'vertical') {
            width = '50%';
            maxWidth = '600px';
            height = '90%';
            maxHeight = '1000px';
        }
        modal.style.setProperty('--modal-width', width);
        modal.style.setProperty('--modal-max-width', maxWidth);
        modal.style.setProperty('--modal-height', height);
        modal.style.setProperty('--modal-max-height', maxHeight);
    }
    // Open Modal
    function openVideoModal(videoType, videoId, videoUrl, videoFormat, options = {}) {
        console.log('🎬 Opening video modal:', { videoType, videoId, videoUrl, videoFormat, options });
       
        const mergedOptions = { ...config, ...options };
        if (mergedOptions.requireAuth && !checkAuth()) {
            console.log('❌ Authentication required but not authenticated');
            return;
        }
        applyModalSize(mergedOptions.modalSize, mergedOptions.videoFormat);
        // Apply player theme
        const playerTheme = mergedOptions.playerTheme || 'default';
        Array.from(modal.classList).forEach(cls => {
            if (cls.startsWith('player-')) modal.classList.remove(cls);
        });
        if (playerTheme !== 'default') {
            modal.classList.add('player-' + playerTheme);
        }
        // Apply video format class
        Array.from(modal.classList).forEach(cls => {
            if (cls.startsWith('video-')) modal.classList.remove(cls);
        });
        if (mergedOptions.videoFormat === 'vertical') {
            modal.classList.add('video-vertical');
        }
        // New: Populate metadata
        metadataDiv.innerHTML = '';
        if (mergedOptions.title) {
            const titleEl = document.createElement('h2');
            titleEl.textContent = mergedOptions.title;
            metadataDiv.appendChild(titleEl);
        }
        if (mergedOptions.publishedDate) {
            const dateEl = document.createElement('p');
            dateEl.textContent = `Published: ${mergedOptions.publishedDate}`;
            metadataDiv.appendChild(dateEl);
        }
        metadataDiv.style.display = (mergedOptions.title || mergedOptions.publishedDate) ? 'block' : 'none';
        // Normalize type
        const normalizedType = (videoType === 'self-hosted' || videoType === 'local') ? 'local' : videoType;
        console.log('📝 Normalized type:', normalizedType);
        // Clear previous
        videoContainer.innerHTML = '';
        hideError();
        if (mergedOptions.loadingSpinner) showLoading();
        // Custom players
        if (mergedOptions.customPlayers[normalizedType]) {
            console.log('🎮 Using custom player for:', normalizedType);
            const custom = mergedOptions.customPlayers[normalizedType];
            if (custom.embed) {
                custom.embed(videoContainer, videoId, videoUrl, videoFormat);
                modal.classList.add('is-visible');
                document.body.classList.add('modal-open');
                closeBtn.focus();
                return;
            }
        }
        if (normalizedType === 'local') {
            console.log('📹 Creating local video player');
            loadVideoJS(() => {
                createLocalPlayer(videoUrl, videoFormat, mergedOptions);
            });
        } else {
            console.log('🖼️ Creating iframe player');
            createIframePlayer(normalizedType, videoId, videoUrl, mergedOptions);
        }
        modal.classList.add('is-visible');
        document.body.classList.add('modal-open');
        closeBtn.focus();
        console.log('✅ Modal opened');
        if (typeof mergedOptions.onPlay === 'function') {
            mergedOptions.onPlay({ type: normalizedType, url: videoUrl });
        }
    }
    // Create Local Player with Video.js
    function createLocalPlayer(videoUrl, videoFormat, options) {
        console.log('🎥 Creating local player for:', videoUrl, 'format:', videoFormat);
       
        const videoElement = document.createElement('video');
        videoElement.className = 'video-js vjs-default-skin vjs-big-play-centered';
        videoElement.controls = options.showControls;
        videoElement.preload = 'auto';
        videoElement.style.width = '100%';
        videoElement.style.height = '100%';
        const source = document.createElement('source');
        source.src = encodeURI(videoUrl);
        source.type = `video/${videoFormat || 'mp4'}`;
        videoElement.appendChild(source);
        videoContainer.appendChild(videoElement);
        let initAttempts = 0;
        const initVideoJS = () => {
            if (window.videojs && initAttempts < 10) {
                console.log('▶️ Initializing Video.js player');
                currentPlayer = videojs(videoElement, {
                    controls: options.showControls,
                    autoplay: false,
                    preload: 'auto',
                    fluid: true,
                    responsive: true,
                    muted: options.muted
                });
                currentPlayer.ready(() => {
                    console.log('✅ Video.js player ready');
                    hideLoading();
                    if (options.autoplay) attemptAutoplay(currentPlayer);
                });
                currentPlayer.on('error', (err) => {
                    console.error('❌ Video.js player error:', err);
                    showError('Failed to load video. Please check the video URL.');
                });
            } else if (initAttempts < 10) {
                initAttempts++;
                console.log(`⏳ Waiting for Video.js (attempt ${initAttempts}/10)`);
                setTimeout(initVideoJS, 100);
            } else {
                console.error('❌ Failed to initialize Video.js after 10 attempts');
                hideLoading();
                showError('Failed to initialize video player.');
            }
        };
        initVideoJS();
    }
    // Create Iframe Player
    function createIframePlayer(videoType, videoId, videoUrl, options) {
        console.log('🖼️ Creating iframe player:', { videoType, videoId, videoUrl });
       
        const iframe = document.createElement('iframe');
        iframe.width = '100%';
        iframe.height = '100%';
        iframe.frameBorder = '0';
        iframe.allowFullscreen = true;
        iframe.allow = 'autoplay; fullscreen; picture-in-picture; encrypted-media';
        videoId = videoId || extractIdFromUrl(videoType, videoUrl) || '';
        let src = '';
        const autoplayParam = options.autoplay ? '1' : '0';
        const muteParam = options.muted ? '1' : '0';
        const controlsParam = options.showControls ? '1' : '0';
        switch (videoType) {
            case 'youtube':
                if (!videoId) {
                    console.error('❌ No YouTube video ID found');
                    showError('Invalid YouTube video URL or ID');
                    return;
                }
                src = `https://www.youtube.com/embed/${videoId}?autoplay=${autoplayParam}&rel=0&modestbranding=1&mute=${muteParam}&controls=${controlsParam}`;
                break;
            case 'vimeo':
                let vimeoId = videoId;
                if (!vimeoId) vimeoId = videoUrl.split('/').pop();
                if (!vimeoId) {
                    console.error('❌ No Vimeo video ID found');
                    showError('Invalid Vimeo video URL or ID');
                    return;
                }
                src = `https://player.vimeo.com/video/${vimeoId}?autoplay=${autoplayParam}&title=0&byline=0&portrait=0&muted=${muteParam}&controls=${controlsParam}`;
                break;
            case 'muvi':
                if (!videoId) {
                    console.error('❌ No Muvi video ID found');
                    showError('Invalid Muvi video URL or ID');
                    return;
                }
                src = `https://embed.muvi.com/embed/${videoId}?autoplay=${autoplayParam}&muted=${muteParam}`;
                break;
            case 'wistia':
                if (!videoId) {
                    console.error('❌ No Wistia video ID found');
                    showError('Invalid Wistia video URL or ID');
                    return;
                }
                src = `https://fast.wistia.net/embed/iframe/${videoId}?autoplay=${autoplayParam}&controls=${controlsParam}&muted=${muteParam}`;
                break;
            case 'rumble':
                if (!videoId) {
                    console.error('❌ No Rumble video ID found');
                    showError('Invalid Rumble video URL or ID');
                    return;
                }
                src = `https://rumble.com/embed/v${videoId}/?autoplay=${autoplayParam}&muted=${muteParam}`;
                break;
            case 'loom':
                if (!videoId) {
                    console.error('❌ No Loom video ID found');
                    showError('Invalid Loom video URL or ID');
                    return;
                }
                src = `https://www.loom.com/embed/${videoId}?autoplay=${autoplayParam}&hide_owner=true&hide_share=true&hide_title=true&hideEmbedTopBar=true&mute=${muteParam}`;
                break;
            default:
                console.error('❌ Unsupported video type:', videoType);
                hideLoading();
                showError(`Unsupported video type: ${videoType}`);
                return;
        }
        console.log('🔗 Iframe source:', src);
        iframe.src = src;
        iframe.onerror = () => {
            console.error('❌ Iframe failed to load');
            showError('Failed to load video iframe');
        };
        videoContainer.appendChild(iframe);
        hideLoading();
    }
    // Attempt Autoplay with Fallback
    function attemptAutoplay(player) {
        setTimeout(() => {
            const playPromise = player.play();
            if (playPromise) {
                playPromise.then(() => {
                    console.log('▶️ Autoplay successful');
                }).catch((err) => {
                    console.log('🔇 Autoplay failed, trying muted:', err);
                    player.muted(true);
                    player.play().then(() => {
                        console.log('🔇 Muted autoplay successful');
                    }).catch((e) => {
                        console.log('❌ Autoplay failed entirely:', e);
                    });
                });
            }
        }, 500);
    }
    // Close Modal
    function closeVideoModal() {
        console.log('❌ Closing video modal');
        modal.classList.remove('is-visible');
        document.body.classList.remove('modal-open');
        if (currentPlayer) {
            currentPlayer.dispose();
            currentPlayer = null;
        }
        videoContainer.innerHTML = '';
        metadataDiv.innerHTML = '';  // New: Clear metadata on close
        metadataDiv.style.display = 'none';
        hideLoading();
        hideError();
    }
    // Show/Hide Loading/Error
    function showLoading() {
        loading.style.display = 'block';
    }
    function hideLoading() {
        loading.style.display = 'none';
    }
    function showError(message) {
        errorDiv.textContent = message || 'Error loading video';
        errorDiv.style.display = 'block';
        hideLoading();
        if (typeof config.onError === 'function') {
            config.onError(message);
        }
    }
    function hideError() {
        errorDiv.style.display = 'none';
    }
    // Setup Thumbnail Clicks (ENHANCED WITH DEBUGGING)
    function setupVideoThumbnails() {
        // Look for multiple possible selectors
        const selectors = [
            '.video-thumb', // Primary selector
            'a[data-video-url]', // Any link with video URL
            'a[href*="youtube.com"]', // YouTube links
            'a[href*="youtu.be"]', // YouTube short links
            'a[href*="vimeo.com"]', // Vimeo links
            'a[href*="muvi.com"]', // Muvi links
            'a[href*="rumble.com"]', // Rumble links
            'a[href*="loom.com"]', // Loom links
            'a[href$=".mp4"]', // Direct video files
            'a[href$=".webm"]',
            'a[href$=".ogg"]'
        ];
        const allElements = document.querySelectorAll(selectors.join(', '));
        console.log(`🔍 Found ${allElements.length} potential video elements with selectors:`, selectors);
        allElements.forEach((element, index) => {
            if (element.classList.contains('video-modal-bound')) {
                return; // Already bound
            }
            console.log(`🎯 Processing element ${index + 1}:`, element);
            console.log(' - href:', element.href);
            console.log(' - classes:', Array.from(element.classList));
            console.log(' - data-video-url:', element.getAttribute('data-video-url'));
            console.log(' - data-video-type:', element.getAttribute('data-video-type'));
            element.classList.add('video-modal-bound');
           
            // Add strong event prevention
            element.addEventListener('click', (e) => {
                console.log('🖱️ Video element clicked!', element);
               
                // CRITICAL: Prevent default behavior
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();
                let url = element.getAttribute('data-video-url') || element.href;
                let type = element.getAttribute('data-video-type');
                let id = element.getAttribute('data-video-id');
                let format = element.getAttribute('data-video-format');
                console.log('📋 Video data extracted:', { url, type, id, format });
                // If no URL, can't do anything
                if (!url) {
                    console.warn('❌ No video URL found on element');
                    return false; // Explicitly return false
                }
                // If type not specified, try to detect it
                if (!type) {
                    const detected = getVideoType(url);
                    type = detected.type;
                    format = detected.format;
                    console.log('🔍 Auto-detected type:', type, 'format:', format);
                }
                // Try to extract ID if not provided and type is not local
                if (type !== 'local' && type !== 'unknown' && !id) {
                    id = extractIdFromUrl(type, url);
                    console.log('🆔 Auto-extracted ID:', id);
                }
                // Get per-video options
                const options = getVideoOptions(element);
                console.log('⚙️ Video options:', options);
                // Tracking
                if (window.trackVideoInteraction) {
                    window.trackVideoInteraction('play', { type, id, url, format });
                }
                // Always attempt to open in modal
                console.log('🚀 Attempting to open video in modal');
                openVideoModal(type, id, url, format, options);
               
                return false; // Prevent any default action
            }, true); // Use capture phase
            console.log(`✅ Bound click handler to element ${index + 1}`);
        });
        console.log(`🎯 Total elements processed: ${allElements.length}`);
       
        // Also check for common video thumbnail patterns
        const commonPatterns = document.querySelectorAll('a[href*="video"], .video-link, .play-button, [data-video]');
        if (commonPatterns.length > 0) {
            console.log(`🔍 Found ${commonPatterns.length} additional potential video elements with common patterns`);
            commonPatterns.forEach((el, i) => {
                if (!el.classList.contains('video-modal-bound')) {
                    console.log(`🎯 Additional element ${i + 1}:`, el);
                }
            });
        }
    }
    // Auth Check Placeholder
    function checkAuth() {
        return true;
    }
    // Expose API
    window.VideoModal = {
        init: initModal,
        open: openVideoModal,
        close: closeVideoModal,
        getType: getVideoType,
        extractId: extractIdFromUrl,
        addCustomPlayer: (type, options) => {
            config.customPlayers[type] = options;
        },
        debug: {
            findVideoElements: () => {
                console.log('🔍 Debug: Manually scanning for video elements...');
                setupVideoThumbnails();
            },
            testModal: () => {
                console.log('🧪 Debug: Testing modal with sample video');
                openVideoModal('youtube', 'dQw4w9WgXcQ', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', null);
            }
        }
    };
    
    // Legacy compatibility: expose openVideoModal as global function
    window.openVideoModal = openVideoModal;
    console.log('✅ Legacy openVideoModal function exposed globally');
    
    // Auto-init if data-auto-init on script
    const script = document.currentScript;
    if (script && script.getAttribute('data-auto-init') === 'true') {
        console.log('🔄 Auto-initializing video modal');
       
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initModal);
        } else {
            initModal();
        }
    } else {
        console.log('ℹ️ Auto-init not enabled, call VideoModal.init() manually');
    }
})();