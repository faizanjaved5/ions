<?php
/**
 * PressPass.ION - AI-Powered Media Relations Platform
 */

// Start session for authentication checks
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Dynamic User Menu Configuration
$ION_USER_MENU = [
    "isLoggedIn" => ((isset($_SESSION['logged_in']) && $_SESSION['logged_in']) || (isset($_SESSION['authenticated']) && $_SESSION['authenticated'])) ? true : false,
    "user" => [
        "name" => isset($_SESSION['first_name']) ? $_SESSION['first_name'] : '',
        "email" => isset($_SESSION['user_email']) ? $_SESSION['user_email'] : '',
        "avatar" => isset($_SESSION['photo_url']) ? $_SESSION['photo_url'] : '',
        "notifications" => [
            [
                "id" => 1,
                "message" => "Your video 'Introduction to ION' has reached 1,000 views",
                "time" => "2 hours ago",
                "read" => false
            ],
            [
                "id" => 2,
                "message" => "New comment on your post",
                "time" => "5 hours ago",
                "read" => false
            ],
            [
                "id" => 3,
                "message" => "Your profile was viewed 25 times today",
                "time" => "1 day ago",
                "read" => true
            ]
        ],
        "menuItems" => [
            [
                "label" => "View Profile",
                "link" => "/@" . (isset($_SESSION['handle']) ? $_SESSION['handle'] : ''),
                "icon" => "User"
            ],
            [
                "label" => "Update Profile",
                "link" => "/profile/edit",
                "icon" => "UserCog"
            ],
            [
                "label" => "Creator Dashboard",
                "link" => "/app/creators.php",
                "icon" => "LayoutDashboard"
            ],
            [
                "label" => "My Videos",
                "link" => "/my-videos",
                "icon" => "Video"
            ],
            [
                "label" => "Preferences",
                "link" => "/preferences",
                "icon" => "Settings"
            ],
            [
                "label" => "Log Out",
                "link" => "/login/logout.php",
                "icon" => "LogOut"
            ]
        ]
    ],
    "headerButtons" => [
        "upload" => [
            "label" => "Upload",
            "link" => "/upload",
            "visible" => true
        ],
        "signIn" => [
            "label" => "Sign In",
            "link" => "/signin",
            "visible" => true
        ]
    ]
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PressPass.ION - AI-Powered Media Relations Platform</title>
    <meta name="description" content="Transform your media strategy with AI-powered press release writing, journalist matching, and automated outreach. Access 2M+ media contacts.">
    <meta name="author" content="PressPass.ION">
    
    <meta property="og:title" content="PressPass.ION - Media Coverage For Champions">
    <meta property="og:description" content="AI-powered media relations platform with access to 2M+ journalists, podcasters, and publishers. Get media coverage without expensive agencies.">
    <meta property="og:type" content="website">
    <meta property="og:image" content="https://lovable.dev/opengraph-image-p98pqg.png">
    
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:site" content="@lovable_dev">
    <meta name="twitter:image" content="https://lovable.dev/opengraph-image-p98pqg.png">
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&display=swap" rel="stylesheet">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        :root {
            --background: hsl(215, 25%, 12%);
            --foreground: hsl(40, 100%, 95%);
            --card: hsl(215, 20%, 15%);
            --card-foreground: hsl(40, 100%, 95%);
            --primary: hsl(29, 36%, 46%);
            --primary-foreground: hsl(0, 0%, 100%);
            --secondary: hsl(215, 20%, 20%);
            --secondary-foreground: hsl(29, 25%, 75%);
            --muted: hsl(215, 20%, 18%);
            --muted-foreground: hsl(215, 10%, 65%);
            --accent: hsl(29, 40%, 55%);
            --border: hsl(215, 20%, 25%);
            --gold-glow: hsl(29, 36%, 46%);
            --gold-bright: hsl(29, 40%, 60%);
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: var(--background);
            color: var(--foreground);
            line-height: 1.6;
        }
        
        .bebas {
            font-family: "Bebas Neue", Arial, sans-serif;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 1rem;
        }
        
        /* Button Styles */
        .btn {
            padding: 0.5rem 1.5rem;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            border: none;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-ghost {
            background: transparent;
            color: var(--foreground);
        }
        
        .btn-ghost:hover {
            background: var(--muted);
        }
        
        .btn-primary {
            background: var(--primary);
            color: var(--primary-foreground);
            box-shadow: 0 0 30px hsla(29, 36%, 46%, 0.3);
        }
        
        .btn-primary:hover {
            background: hsl(29, 36%, 42%);
            box-shadow: 0 0 50px hsla(29, 36%, 46%, 0.4);
        }
        
        .btn-lg {
            padding: 0.75rem 2rem;
            font-size: 1.125rem;
        }
        
        .btn-outline {
            background: transparent;
            border: 1px solid hsla(29, 36%, 46%, 0.5);
            color: var(--foreground);
        }
        
        .btn-outline:hover {
            background: hsla(29, 36%, 46%, 0.1);
            border-color: var(--primary);
        }
        
        /* Hero Section */
        .hero {
            position: relative;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            background: linear-gradient(135deg, hsl(215, 25%, 12%) 0%, hsl(215, 30%, 15%) 50%, hsl(215, 25%, 10%) 100%);
            padding-top: 2.5rem;
        }
        
        .hero-glow {
            position: absolute;
            width: 384px;
            height: 384px;
            background: hsla(29, 36%, 46%, 0.1);
            border-radius: 50%;
            filter: blur(80px);
            animation: glow-pulse 3s ease-in-out infinite;
        }
        
        .hero-glow-1 {
            top: 25%;
            left: 25%;
        }
        
        .hero-glow-2 {
            bottom: 25%;
            right: 25%;
            animation-delay: 1.5s;
        }
        
        @keyframes glow-pulse {
            0%, 100% {
                opacity: 0.3;
                transform: scale(1);
            }
            50% {
                opacity: 0.5;
                transform: scale(1.1);
            }
        }
        
        .hero-content {
            position: relative;
            text-align: center;
            padding: 5rem 1rem;
            animation: slide-in 0.5s ease-out;
        }
        
        @keyframes slide-in {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .hero-badge {
            display: inline-block;
            margin-bottom: 1.5rem;
            padding: 0.5rem 1rem;
            border-radius: 9999px;
            border: 1px solid hsla(29, 36%, 46%, 0.3);
            background: hsla(29, 36%, 46%, 0.05);
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--primary);
        }
        
        .hero h1 {
            font-size: clamp(2.5rem, 8vw, 5rem);
            font-weight: 700;
            letter-spacing: -0.02em;
            margin-bottom: 1rem;
        }
        
        .hero h1 .space {
            display: inline-block;
            width: 0.5rem;
        }
        
        .gradient-text {
            background: linear-gradient(135deg, hsl(29, 36%, 46%), hsl(29, 40%, 60%));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .hero-tagline {
            font-size: clamp(1.5rem, 4vw, 2.5rem);
            font-weight: 600;
            color: var(--muted-foreground);
            margin-bottom: 1.5rem;
        }
        
        .hero-description {
            font-size: 1.125rem;
            color: var(--muted-foreground);
            max-width: 42rem;
            margin: 0 auto 2rem;
            line-height: 1.8;
        }
        
        .hero-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            justify-content: center;
            margin-bottom: 3rem;
        }
        
        .hero-features {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 2rem;
            font-size: 0.875rem;
            color: var(--muted-foreground);
        }
        
        .hero-features div {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .hero-features .dot {
            width: 0.5rem;
            height: 0.5rem;
            border-radius: 50%;
            background: var(--primary);
        }
        
        /* Section Styles */
        section {
            padding: 5rem 1rem;
        }
        
        .section-header {
            text-align: center;
            margin-bottom: 4rem;
        }
        
        .section-badge {
            display: inline-block;
            margin-bottom: 1rem;
            padding: 0.5rem 1rem;
            border-radius: 9999px;
            border: 1px solid hsla(29, 36%, 46%, 0.3);
            background: hsla(29, 36%, 46%, 0.05);
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--primary);
        }
        
        .section-title {
            font-size: clamp(2rem, 5vw, 3.5rem);
            font-weight: 700;
            margin-bottom: 1rem;
            line-height: 1.2;
        }
        
        .section-description {
            font-size: 1.125rem;
            color: var(--muted-foreground);
            max-width: 48rem;
            margin: 0 auto;
        }
        
        /* Cards Grid */
        .cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            max-width: 90rem;
            margin: 0 auto;
        }
        
        .card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 0.5rem;
            padding: 2rem;
            transition: all 0.3s;
        }
        
        .card:hover {
            border-color: hsla(29, 36%, 46%, 0.5);
            box-shadow: 0 0 30px hsla(29, 36%, 46%, 0.3);
        }
        
        .card-icon {
            display: inline-flex;
            padding: 1rem;
            border-radius: 0.5rem;
            background: hsla(29, 36%, 46%, 0.1);
            color: var(--primary);
            margin-bottom: 1.5rem;
            transition: transform 0.3s;
        }
        
        .card:hover .card-icon {
            transform: scale(1.1);
        }
        
        .card-icon svg {
            width: 2rem;
            height: 2rem;
        }
        
        .card h3 {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.75rem;
            color: var(--foreground);
        }
        
        .card p {
            color: var(--muted-foreground);
            line-height: 1.7;
        }
        
        /* Benefits Section */
        .benefits {
            background: var(--background);
        }
        
        /* Features Section */
        .features {
            background: var(--background);
        }
        
        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 2rem;
            max-width: 90rem;
            margin: 0 auto 4rem;
        }
        
        .features-footer {
            text-align: center;
            margin-top: 4rem;
        }
        
        .features-footer p {
            font-size: 1.25rem;
            margin-bottom: 0.5rem;
        }
        
        .features-footer .emphasis {
            color: var(--foreground);
            font-weight: 600;
        }
        
        /* Media Databases Section */
        .media-databases {
            background: hsla(215, 20%, 18%, 0.3);
        }
        
        .database-card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 0.5rem;
            padding: 1.5rem;
            transition: all 0.3s;
        }
        
        .database-card:hover {
            border-color: hsla(29, 36%, 46%, 0.5);
        }
        
        .database-count {
            font-size: 2.5rem;
            font-weight: 700;
            background: linear-gradient(135deg, hsl(29, 36%, 46%), hsl(29, 40%, 60%));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin: 1rem 0;
        }
        
        .database-features {
            list-style: none;
        }
        
        .database-features li {
            display: flex;
            align-items: start;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
            color: var(--muted-foreground);
        }
        
        .database-features li::before {
            content: "";
            display: block;
            width: 0.375rem;
            height: 0.375rem;
            border-radius: 50%;
            background: var(--primary);
            margin-top: 0.5rem;
            flex-shrink: 0;
        }
        
        /* Pricing Section */
        .pricing {
            background: var(--background);
        }
        
        .pricing-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            max-width: 90rem;
            margin: 0 auto 4rem;
        }
        
        .pricing-card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 0.5rem;
            padding: 2rem;
            position: relative;
            transition: all 0.3s;
        }
        
        .pricing-card.popular {
            border-color: var(--primary);
            box-shadow: 0 0 50px hsla(29, 36%, 46%, 0.4);
            transform: scale(1.05);
        }
        
        .pricing-card:not(.popular):hover {
            border-color: hsla(29, 36%, 46%, 0.5);
        }
        
        .popular-badge {
            position: absolute;
            top: -1rem;
            left: 50%;
            transform: translateX(-50%);
            background: var(--primary);
            color: var(--primary-foreground);
            padding: 0.25rem 1rem;
            border-radius: 9999px;
            font-size: 0.875rem;
            font-weight: 500;
        }
        
        .pricing-card h3 {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .pricing-price {
            display: flex;
            align-items: baseline;
            gap: 0.25rem;
            margin-bottom: 0.75rem;
        }
        
        .pricing-price .amount {
            font-size: 2.5rem;
            font-weight: 700;
        }
        
        .pricing-price .period {
            color: var(--muted-foreground);
        }
        
        .pricing-description {
            font-size: 0.875rem;
            color: var(--muted-foreground);
            margin-bottom: 1.5rem;
        }
        
        .pricing-features {
            list-style: none;
            margin-top: 1.5rem;
        }
        
        .pricing-features li {
            display: flex;
            align-items: start;
            gap: 0.75rem;
            margin-bottom: 0.75rem;
            font-size: 0.875rem;
            color: var(--muted-foreground);
        }
        
        .pricing-features svg {
            width: 1.25rem;
            height: 1.25rem;
            color: var(--primary);
            flex-shrink: 0;
            margin-top: 0.125rem;
        }
        
        .pricing-cta {
            background: hsla(29, 36%, 46%, 0.05);
            border: 1px solid hsla(29, 36%, 46%, 0.3);
            border-radius: 0.5rem;
            padding: 2rem;
            text-align: center;
            max-width: 48rem;
            margin: 0 auto;
        }
        
        .pricing-cta h3 {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.75rem;
        }
        
        .pricing-cta p {
            color: var(--muted-foreground);
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .hero-content {
                padding: 3rem 1rem;
            }
            
            section {
                padding: 3rem 1rem;
            }
            
            .cards-grid,
            .features-grid,
            .pricing-grid {
                grid-template-columns: 1fr;
            }
            
            .pricing-card.popular {
                transform: scale(1);
            }
        }
    </style>
</head>
<body>
    <?php
    // Include dynamic navigation menu
    if (!isset($root)) {
        $root = dirname(__DIR__);
    }
    $ION_NAVBAR_BASE_URL = '/menu/';
    require_once $root . '/menu/ion-navbar-embed.php';
    ?>

    <!-- Hero Section -->
    <section class="hero">
        <div class="hero-glow hero-glow-1"></div>
        <div class="hero-glow hero-glow-2"></div>
        
        <div class="container">
            <div class="hero-content">
                <div class="hero-badge">
                    AI Enhanced Media Relations Platform
                </div>
                
                <h1 class="bebas">
                    PRESS<span class="space"></span>PASS<span class="gradient-text">ION</span>
                </h1>
                
                <p class="hero-tagline">
                    Media Coverage For Champions
                </p>
                
                <p class="hero-description">
                    For too long, media coverage has been controlled by expensive agencies and outdated systems, but a new age has dawned, bringing the power of the Press to you like never before.
                </p>
                
                <div class="hero-buttons">
                    <button class="btn btn-primary btn-lg">
                        Start Free Today
                        <svg style="display: inline-block; width: 1.25rem; height: 1.25rem; margin-left: 0.5rem; vertical-align: middle;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                        </svg>
                    </button>
                    <button class="btn btn-outline btn-lg">
                        See How It Works
                    </button>
                </div>
                
                <div class="hero-features">
                    <div>
                        <div class="dot"></div>
                        <span>No Credit Card Required</span>
                    </div>
                    <div>
                        <div class="dot"></div>
                        <span>2M+ Media Contacts</span>
                    </div>
                    <div>
                        <div class="dot"></div>
                        <span>Cancel Anytime</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Benefits Section -->
    <section class="benefits">
        <div class="container">
            <div class="cards-grid" style="grid-template-columns: repeat(3, 1fr);">
                <div class="card">
                    <div class="card-icon">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/>
                        </svg>
                    </div>
                    <h3>AI Enhanced Platform</h3>
                    <p>PressPass.ION leverages advanced AI to put you in complete control of your media strategy.</p>
                </div>
                
                <div class="card">
                    <div class="card-icon">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                        </svg>
                    </div>
                    <h3>Direct Access</h3>
                    <p>No intermediaries. No overpriced agencies. No wasted budget.</p>
                </div>
                
                <div class="card">
                    <div class="card-icon">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                        </svg>
                    </div>
                    <h3>Universal Coverage</h3>
                    <p>Media coverage isn't reserved for corporations anymore. With AI, it's available to everyone.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="features">
        <div class="container">
            <div class="section-header">
                <div class="section-badge">FEATURES</div>
                <h2 class="section-title bebas">
                    Press Pass<span class="gradient-text">ion</span> is Revolut<span class="gradient-text">ion</span>izing Media Relat<span class="gradient-text">ion</span>s and Reach. <span class="gradient-text">Here's How.</span>
                </h2>
                <p class="section-description">
                    Traditional media relations is broken. Agencies charge thousands without guarantees. Media databases are expensive and outdated. Writing releases takes forever. PressPass.ION transforms all of that.
                </p>
            </div>

            <div class="features-grid">
                <div class="card">
                    <div class="card-icon">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                        </svg>
                    </div>
                    <h3>Intelligent Content Creation</h3>
                    <p>Your story deserves to be heard. Crafting the perfect pitch used to take hours. PressPass.ION's AI understands what resonates with media professionals and generates custom-tailored press releases and outreach emails in seconds.</p>
                </div>
                
                <div class="card">
                    <div class="card-icon">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                        </svg>
                    </div>
                    <h3>Precision Media Matching</h3>
                    <p>Stop sending pitches into the void. PressPass.ION's AI connects you with over 2 million journalists, podcasters, and publishers who are actively interested in covering stories like yours.</p>
                </div>
                
                <div class="card">
                    <div class="card-icon">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                        </svg>
                    </div>
                    <h3>Intelligent Follow-Up Automation</h3>
                    <p>Media professionals receive hundreds of pitches daily. Stand out with PressPass.ION's smart follow-up sequences that boost your response rate by 40%. All managed by AI while you focus on your business.</p>
                </div>
                
                <div class="card">
                    <div class="card-icon">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                        </svg>
                    </div>
                    <h3>Real-Time Media Opportunities</h3>
                    <p>Media professionals are seeking stories right now. PressPass.ION's AI monitors and aggregates these opportunities in real-time from platforms like HARO, SourceBottle, and social channels so you can respond before competitors.</p>
                </div>
            </div>

            <div class="features-footer">
                <p class="emphasis">
                    Media success is no longer about connections—it's about intelligent strategy.
                </p>
                <p class="section-description">
                    With AI, PressPass.ION keeps you ahead of the competition.
                </p>
            </div>
        </div>
    </section>

    <!-- Media Databases Section -->
    <section class="media-databases">
        <div class="container">
            <div class="section-header">
                <h2 class="section-title bebas">
                    Access The World's Largest
                    <span class="gradient-text" style="display: block; margin-top: 0.5rem;">Media Contact Databases</span>
                </h2>
                <p class="section-description">
                    PressPass.ION gives you instant access to millions of media contacts across all industries
                </p>
            </div>

            <div class="cards-grid">
                <div class="database-card">
                    <div class="card-icon">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                    </div>
                    <h3>Journalist Database</h3>
                    <p>Connect directly with reporters, bloggers, editors and more to pitch your stories.</p>
                    <div class="database-count">500,000+</div>
                    <ul class="database-features">
                        <li>Individual reporters and editors</li>
                        <li>Complete contact information</li>
                        <li>Social media profiles</li>
                        <li>Recent articles and coverage areas</li>
                    </ul>
                </div>
                
                <div class="database-card">
                    <div class="card-icon">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z"/>
                        </svg>
                    </div>
                    <h3>Podcast Database</h3>
                    <p>Discover podcasts in your industry to secure guest appearances and expand your reach.</p>
                    <div class="database-count">200,000+</div>
                    <ul class="database-features">
                        <li>Active podcasts across all industries</li>
                        <li>Host contact information</li>
                        <li>Listener analytics and insights</li>
                        <li>Social profiles and websites</li>
                    </ul>
                </div>
                
                <div class="database-card">
                    <div class="card-icon">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z"/>
                        </svg>
                    </div>
                    <h3>Publisher Database</h3>
                    <p>Access media outlet profiles for broader distribution of your announcements.</p>
                    <div class="database-count">160,000+</div>
                    <ul class="database-features">
                        <li>Online, print, radio outlets</li>
                        <li>Editorial contacts</li>
                        <li>Domain authority metrics</li>
                        <li>Audience size and demographics</li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <!-- Pricing Section -->
    <section id="pricing" class="pricing">
        <div class="container">
            <div class="section-header">
                <div class="section-badge">PRICING</div>
                <h2 class="section-title bebas">
                    Transparent Pricing
                    <span class="gradient-text" style="display: block; margin-top: 0.5rem;">For Every Stage</span>
                </h2>
                <p class="section-description">
                    Traditional PR databases cost thousands. Agencies charge even more. PressPass.ION delivers AI-powered media relations tools at prices that work for businesses of all sizes.
                </p>
            </div>

            <div class="pricing-grid">
                <div class="pricing-card">
                    <h3>Starter</h3>
                    <div class="pricing-price">
                        <span class="amount">Free</span>
                    </div>
                    <p class="pricing-description">For entrepreneurs and startups ready to secure their first major media coverage.</p>
                    <button class="btn btn-primary" style="width: 100%; margin-bottom: 1.5rem;">Start Free Trial</button>
                    <ul class="pricing-features">
                        <li>
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            <span>Access to AI press release writer</span>
                        </li>
                        <li>
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            <span>100 journalist contacts per month</span>
                        </li>
                        <li>
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            <span>Basic media outreach tools</span>
                        </li>
                        <li>
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            <span>Email templates and customization</span>
                        </li>
                        <li>
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            <span>5 PR campaigns per month</span>
                        </li>
                        <li>
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            <span>Basic analytics and reporting</span>
                        </li>
                        <li>
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            <span>Direct response to 10 journalist requests</span>
                        </li>
                    </ul>
                </div>

                <div class="pricing-card popular">
                    <div class="popular-badge">Most Popular</div>
                    <h3>Professional</h3>
                    <div class="pricing-price">
                        <span class="amount">$79</span>
                        <span class="period">/month</span>
                    </div>
                    <p class="pricing-description">For growing businesses that need comprehensive PR capabilities to scale visibility.</p>
                    <button class="btn btn-primary" style="width: 100%; margin-bottom: 1.5rem;">Start Free Trial</button>
                    <ul class="pricing-features">
                        <li>
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            <span>Everything in Starter, plus:</span>
                        </li>
                        <li>
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            <span>Unlimited AI-generated press releases</span>
                        </li>
                        <li>
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            <span>500 journalist contacts per month</span>
                        </li>
                        <li>
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            <span>Advanced media matching algorithm</span>
                        </li>
                        <li>
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            <span>20 PR campaigns per month</span>
                        </li>
                        <li>
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            <span>Automated follow-up sequences</span>
                        </li>
                        <li>
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            <span>Priority distribution to media outlets</span>
                        </li>
                        <li>
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            <span>Advanced analytics dashboard</span>
                        </li>
                        <li>
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            <span>Direct response to unlimited journalist requests</span>
                        </li>
                    </ul>
                </div>

                <div class="pricing-card">
                    <h3>Enterprise</h3>
                    <div class="pricing-price">
                        <span class="amount">$149</span>
                        <span class="period">/month</span>
                    </div>
                    <p class="pricing-description">Custom solutions for large organizations with complex PR needs across multiple brands.</p>
                    <button class="btn btn-primary" style="width: 100%; margin-bottom: 1.5rem;">Start Free Trial</button>
                    <ul class="pricing-features">
                        <li>
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            <span>Everything in Professional, plus:</span>
                        </li>
                        <li>
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            <span>Unlimited journalist contacts</span>
                        </li>
                        <li>
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            <span>Custom AI training for your brand voice</span>
                        </li>
                        <li>
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            <span>Dedicated PR success manager</span>
                        </li>
                        <li>
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            <span>Unlimited PR campaigns</span>
                        </li>
                        <li>
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            <span>White-label reporting options</span>
                        </li>
                        <li>
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            <span>API access for custom integrations</span>
                        </li>
                        <li>
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            <span>Crisis PR support and training</span>
                        </li>
                        <li>
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            <span>Premium media placements at wholesale prices</span>
                        </li>
                    </ul>
                </div>
            </div>

            <div class="pricing-cta">
                <h3>Why invest $250,000 in PR that delivers no results?</h3>
                <p>
                    That's what countless businesses waste before discovering traditional PR is fundamentally broken. 
                    With PressPass.ION, you get superior tools, better outcomes, and transparent pricing. 
                    Start controlling your own narrative today.
                </p>
            </div>
        </div>
    </section>
</body>
</html>