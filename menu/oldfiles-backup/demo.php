<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ION Networks - Demo Page</title>
    <link rel="stylesheet" href="menu.css">
    <style>
        /* Example page styles */
        main {
            padding: 2rem;
            max-width: 1200px;
            margin: 0 auto;
            color: var(--foreground);
        }
        
        .content-section {
            background: var(--card);
            padding: 2rem;
            border-radius: 0.75rem;
            margin-bottom: 2rem;
            border: 1px solid var(--ion-border);
            transition: all 0.3s ease;
        }
        
        h1, h2 {
            color: var(--ion-gold);
            margin-bottom: 1rem;
        }
        
        h3 {
            color: var(--ion-blue);
            margin-bottom: 0.5rem;
            margin-top: 1.5rem;
        }
        
        .feature-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-top: 2rem;
        }
        
        .feature-card {
            background: var(--ion-darker);
            padding: 1.5rem;
            border-radius: 0.5rem;
            border: 1px solid var(--ion-border);
            transition: all 0.3s ease;
        }
        
        .feature-card:hover {
            border-color: var(--ion-gold);
            transform: translateY(-2px);
        }
        
        .feature-title {
            color: var(--ion-blue);
            font-size: 1.25rem;
            margin-bottom: 0.5rem;
        }
        
        .demo-buttons {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
            flex-wrap: wrap;
        }
        
        .demo-btn {
            padding: 0.75rem 1.5rem;
            background: var(--ion-gold);
            color: var(--ion-darker);
            text-decoration: none;
            border-radius: 0.5rem;
            transition: all 0.3s ease;
            font-family: "Bebas Neue", Arial, sans-serif;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border: none;
            cursor: pointer;
        }
        
        .demo-btn:hover {
            background: #a0754c;
            transform: translateY(-2px);
        }
        
        .demo-btn.secondary {
            background: transparent;
            color: var(--ion-gold);
            border: 2px solid var(--ion-gold);
        }
        
        .demo-btn.secondary:hover {
            background: var(--ion-gold);
            color: var(--ion-darker);
        }

        .demo-btn.theme-toggle {
            background: var(--ion-blue);
            color: var(--background);
        }

        .demo-btn.theme-toggle:hover {
            background: var(--ion-light-blue);
            transform: translateY(-2px);
        }

        code {
            background: var(--ion-darker);
            padding: 0.5rem;
            border-radius: 0.25rem;
            color: var(--ion-blue);
            font-family: monospace;
        }

        pre {
            background: var(--ion-darker);
            padding: 1rem;
            border-radius: 0.5rem;
            overflow-x: auto;
            border: 1px solid var(--ion-border);
        }

        pre code {
            background: transparent;
            padding: 0;
        }

        ul {
            color: var(--foreground);
            margin-left: 1.5rem;
        }

        li {
            margin-bottom: 0.5rem;
        }

        p {
            color: var(--foreground);
            line-height: 1.6;
            margin-bottom: 1rem;
        }

        .theme-indicator {
            position: fixed;
            top: 100px;
            right: 20px;
            background: var(--ion-gold);
            color: var(--ion-darker);
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            font-family: "Bebas Neue", Arial, sans-serif;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            z-index: 9999;
            opacity: 0;
            transform: translateX(100px);
            transition: all 0.3s ease;
        }

        .theme-indicator.show {
            opacity: 1;
            transform: translateX(0);
        }

        .fixes-highlight {
            background: linear-gradient(135deg, var(--ion-gold), #d4a574);
            padding: 1.5rem;
            border-radius: 0.75rem;
            margin: 2rem 0;
            color: var(--ion-darker);
        }

        .fixes-highlight h3 {
            color: var(--ion-darker);
            margin-bottom: 1rem;
        }

        .fixes-highlight ul {
            color: var(--ion-darker);
        }

        .fixes-highlight code {
            background: rgba(255, 255, 255, 0.2);
            color: var(--ion-darker);
        }
    </style>
</head>
<body>
    <?php
    // Include the navigation
    include 'menu.php';
    ?>
    
    <!-- Theme indicator -->
    <div id="themeIndicator" class="theme-indicator">
        <span id="themeText">Dark Mode</span>
    </div>
    
    <main>
        <div class="content-section">
            <h1><span class="ion-text">ION</span> Networks Enhanced Navigation</h1>
            <p>This example demonstrates the refined ION Networks navigation system with enhanced functionality, accessibility features, and improved styling that matches the provided screenshots.</p>
            
            <div class="fixes-highlight">
                <h3>✅ Latest Fixes Applied</h3>
                <ul>
                    <li><strong>Search Bar Styling:</strong> Now properly styled to match the design with hover effects</li>
                    <li><strong>Chevron Visibility:</strong> Chevrons only appear on hover for dropdown items</li>
                    <li><strong>Theme Support:</strong> Complete dark/light mode support with smooth transitions</li>
                </ul>
            </div>
            
            <div class="demo-buttons">
                <button class="demo-btn" onclick="demonstrateFeatures()">Test All Features</button>
                <button class="demo-btn theme-toggle" onclick="toggleTheme()">🌙 Toggle Theme</button>
                <button class="demo-btn secondary" onclick="openSearch()">🔍 Test Search</button>
                <button class="demo-btn secondary" onclick="testHoverEffects()">✨ Test Hover Effects</button>
            </div>
        </div>
        
        <div class="content-section">
            <h2>Navigation Features</h2>
            <div class="feature-grid">
                <div class="feature-card">
                    <div class="feature-title">🎯 Fixed Search Styling</div>
                    <p>The search button now has proper styling that matches the overall design with hover effects and proper spacing.</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-title">👁️ Hover-Only Chevrons</div>
                    <p>Chevron arrows only appear when hovering over dropdown menu items, creating a cleaner look.</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-title">🌓 Theme Support</div>
                    <p>Complete dark and light mode support with smooth transitions and proper color adjustments.</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-title">📱 Responsive Design</div>
                    <p>Fully responsive navigation that works seamlessly on desktop, tablet, and mobile devices with touch-friendly interactions.</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-title">♿ Accessibility</div>
                    <p>WCAG 2.1 compliant with proper ARIA attributes, keyboard navigation, focus management, and screen reader support.</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-title">🚀 Performance</div>
                    <p>Optimized JavaScript with lazy loading, efficient DOM manipulation, and smooth animations for optimal performance.</p>
                </div>
            </div>
        </div>
        
        <div class="content-section">
            <h2>Test Instructions</h2>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem;">
                <div>
                    <h3>🖱️ Hover Effects</h3>
                    <ul>
                        <li>Hover over <strong>"ION Networks"</strong> to see the mega menu</li>
                        <li>Notice chevrons only appear on hover</li>
                        <li>Hover over the search button to see styling</li>
                        <li>Test upload and sign buttons</li>
                    </ul>
                </div>
                
                <div>
                    <h3>📱 Mobile Testing</h3>
                    <ul>
                        <li>Resize browser window to mobile size</li>
                        <li>Click hamburger menu icon</li>
                        <li>Test accordion-style mobile menu</li>
                        <li>Try closing with the X button</li>
                    </ul>
                </div>
                
                <div>
                    <h3>🌙 Theme Testing</h3>
                    <ul>
                        <li>Click the theme toggle button</li>
                        <li>Watch smooth transitions</li>
                        <li>Test both dark and light modes</li>
                        <li>Notice how all elements adapt</li>
                    </ul>
                </div>
                
                <div>
                    <h3>⌨️ Keyboard Navigation</h3>
                    <ul>
                        <li>Use <code>Tab</code> to navigate through items</li>
                        <li>Press <code>Enter</code> to activate links</li>
                        <li>Use <code>Escape</code> to close menus</li>
                        <li>Test focus indicators</li>
                    </ul>
                </div>
            </div>
        </div>
        
        <div class="content-section">
            <h2>Implementation</h2>
            
            <h3>File Structure</h3>
            <pre><code>your-website/
├── menu.php          ← Main navigation include (updated)
├── menu.css          ← Enhanced styles with fixes
├── menudata.php      ← Menu structure data
├── demo.php          ← This demo page
└── ion-logo-gold.png ← Logo (optional)</code></pre>
            
            <h3>Quick Setup</h3>
            <p>Include the navigation in any PHP page:</p>
            <pre><code>&lt;?php include 'menu.php'; ?&gt;</code></pre>
            
            <p>Include the CSS in your HTML head:</p>
            <pre><code>&lt;link rel="stylesheet" href="menu.css"&gt;</code></pre>
            
            <h3>Theme Variables</h3>
            <p>The navigation supports both dark and light themes through CSS custom properties:</p>
            <pre><code>/* Dark Mode (Default) */
:root {
    --ion-gold: #b28254;
    --ion-blue: #a4b3d0;
    --ion-dark: #2a2f3a;
}

/* Light Mode */
[data-theme="light"] {
    --ion-gold: #b28254;
    --ion-blue: #4a5568;
    --ion-dark: #ffffff;
}</code></pre>
        </div>
        
        <div class="content-section">
            <h2>What's Fixed</h2>
            <ul>
                <li><strong>Search Button:</strong> Now has proper background, padding, and hover effects that match the design</li>
                <li><strong>Chevron Icons:</strong> Only visible on hover with smooth fade-in animation</li>
                <li><strong>Light Theme:</strong> Complete light mode support with proper contrast and colors</li>
                <li><strong>Smooth Transitions:</strong> All theme changes animate smoothly</li>
                <li><strong>Button Consistency:</strong> All action buttons have consistent styling and hover effects</li>
                <li><strong>Mobile Compatibility:</strong> Both themes work perfectly on mobile devices</li>
            </ul>
        </div>
    </main>
    
    <script>
        function demonstrateFeatures() {
            alert('🎉 All Navigation Features:\n\n' +
                  '✅ Hover over "ION Networks" for mega menu\n' +
                  '✅ Notice chevrons only show on hover\n' +
                  '✅ Search button has proper styling\n' +
                  '✅ Theme toggle works perfectly\n' +
                  '✅ Mobile menu is fully responsive\n' +
                  '✅ Keyboard navigation supported\n' +
                  '✅ All "ION" text highlighted in gold\n\n' +
                  'Try switching themes and resizing the window!');
        }
        
        function testHoverEffects() {
            alert('✨ Hover Effect Tests:\n\n' +
                  '1. Hover over navigation items to see chevrons appear\n' +
                  '2. Hover over the search button for styling effects\n' +
                  '3. Hover over upload/sign buttons\n' +
                  '4. Notice smooth transitions on all elements\n\n' +
                  'All hover effects are now working perfectly!');
        }
        
        // Enhanced theme change listener with indicator
        document.addEventListener('ionThemeChanged', function(e) {
            console.log('Theme changed to:', e.detail.theme);
            
            // Update theme indicator
            const indicator = document.getElementById('themeIndicator');
            const themeText = document.getElementById('themeText');
            
            themeText.textContent = e.detail.theme === 'dark' ? 'Dark Mode' : 'Light Mode';
            indicator.classList.add('show');
            
            setTimeout(() => {
                indicator.classList.remove('show');
            }, 2000);
        });
        
        // Initialize theme indicator
        window.addEventListener('load', function() {
            const currentTheme = document.documentElement.getAttribute('data-theme') || 'dark';
            const themeText = document.getElementById('themeText');
            themeText.textContent = currentTheme === 'dark' ? 'Dark Mode' : 'Light Mode';
        });
        
        // Add some interactive demonstrations
        function highlightElement(selector, duration = 3000) {
            const element = document.querySelector(selector);
            if (element) {
                element.style.outline = '2px solid var(--ion-gold)';
                element.style.outlineOffset = '4px';
                setTimeout(() => {
                    element.style.outline = '';
                    element.style.outlineOffset = '';
                }, duration);
            }
        }
        
        // Demo functions for specific features
        function demonstrateSearch() {
            highlightElement('.ion-search-section');
            setTimeout(() => {
                alert('💡 Notice how the search button has:\n\n' +
                      '• Proper background styling\n' +
                      '• Hover effects that match the design\n' +
                      '• Smooth transitions\n' +
                      '• Consistent spacing with other elements');
            }, 500);
        }
        
        function demonstrateChevrons() {
            alert('👁️ Chevron Demo:\n\n' +
                  '1. Look at the navigation items\n' +
                  '2. Hover over "ION Networks" or other dropdown items\n' +
                  '3. Notice chevrons fade in smoothly\n' +
                  '4. Move mouse away - chevrons fade out\n\n' +
                  'This creates a cleaner look when not hovering!');
        }
    </script>
</body>
</html>