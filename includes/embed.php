// Embed Code for Video Modal Player 

<script>
    // Set your custom preferences (overrides defaults in video-modal.js)
    window.VideoModalConfig = {
        autoplay       : true,     // true: video starts playing automatically when modal opens (with fallback to muted if needed); false: user must manually start playback
        muted          : false,    // false: video plays with sound by default; true: video starts muted, useful for autoplay compliance in browsers
        theme          : 'dark',   // 'dark': dark background and white close button; 'light': light background and black close button, applies corresponding CSS classes to modal
        playerTheme    : 'flat',   // 'default': modal has border, box-shadow, and sharp corners (12px radius on container/iframe/video);
                                   // 'flat': no border, no shadow, rounded corners (20px radius on content, container, iframe/video)
        modalSize      : 'medium', // 'medium': 90% width/80% height (default);
                                   // 'small' : 60% width/50% height;
                                   // 'large' : 95% width/90% height; can also be an object like {width: '80%', maxWidth: '1000px', height: '70%', maxHeight: '700px'} for custom dimensions
        videoFormat    : 'wide',   // 'wide': landscape orientation (default); 'vertical': portrait orientation (taller and narrower modal)
        showControls   : true,     // true: displays video controls (play/pause, volume, etc.); false: hides controls, depending on platform support (e.g., passed as params to embeds)
        requireAuth    : false,    // false: no authentication check; true: requires checkAuth() to return true before opening modal, otherwise modal doesn't open
        onPlay         : null,     // null: no callback; function: custom function called when video plays, receives object with {type, url}
        onError        : null,     // null: no callback; function: custom function called on error, receives error message string
        loadingSpinner : true,     // true: shows "Loading..." spinner while video loads; false: no spinner, modal appears blank until loaded
        trapFocus      : true,     // true: traps keyboard focus within modal (Tab cycles through focusable elements); false: no focus trapping, allows Tab to escape modal
        customPlayers  : {},       // {}: no custom players; object: keys as video types (e.g., 'youtube'), values as objects with 'embed' function to override default player creation
        cssVariables   : {
           '--modal-bg': 'rgba(20, 20, 20, 0.95)', // Custom background color for modal content (rgba or other CSS color); affects .video-modal-content background
        '--close-color': '#fff'    // Custom color for close button (any CSS color); affects .video-close color
        }
    };
</script>
<script src="https://ions.com/includes/video-modal.js" data-auto-init="true"></script>

<!-- Video Thumbnail/Link (click opens in modal) -->
<a class="video-thumb" 
   href                = "https://vid.ions.com/your-video.mp4" 
   data-video-type     = "local" 
   data-video-format   = "mp4" 
   data-player-theme   = "flat"
   data-title          = "Your Video Title Here"  <!-- Add this for the title -->
   data-published-date = "2025-09-03">   <!-- Add this for the date (format as YYYY-MM-DD or any string) -->
    <img src="https://ions.com/thumbnail.jpg" alt="Video Title" style="width: 320px; height: 180px; border-radius: 8px;">
    <p>Click to Play Video</p>
</a>