<?php
declare(strict_types=1);

/**
 * ION Full Footer Component
 * Complete footer with Find Yourself section, advertising, and licensing
 * All CSS is namespaced to prevent conflicts with page styles
 */

// Get root URL for footer link
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443 ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$rootUrl = $protocol . '://' . $host . '/';

// Ensure we have city data available (fallback if not set)
if (!isset($city)) {
    $city = (object)['city_name' => 'LOCAL NETWORK'];
}
if (!isset($image_credit)) {
    $image_credit = '';
}
?>

<style>
/* ============================================
   ION FOOTER STYLES - NAMESPACED TO PREVENT CONFLICTS
   All selectors start with .ion-footer-wrapper
   ============================================ */

.ion-footer-wrapper .find-yourself-section {
    background: linear-gradient(135deg, #2a2a2a 0%, #1a1a1a 100%);
    min-height: 100vh;
    padding: 60px 20px;
    color: white;
}

.ion-footer-wrapper .footer-container-inner {
    max-width: 1200px;
    margin: 0 auto;
}

.ion-footer-wrapper .section-header {
    text-align: center;
    margin-bottom: 60px;
}

.ion-footer-wrapper .section-title {
    font-size: 3.5rem;
    font-weight: 700;
    letter-spacing: 3px;
    margin-bottom: 1px;
    color: #e0e0e0;
}

.ion-footer-wrapper .section-subtitle {
    font-size: 1.2rem;
    color: #b0b0b0;
    max-width: 600px;
    margin: 0 auto;
    line-height: 1.5;
}

.ion-footer-wrapper .footer-cards-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
    gap: 30px;
    margin-top: 60px;
}

.ion-footer-wrapper .footer-card {
    border-radius: 20px;
    padding: 40px 30px;
    position: relative;
    overflow: hidden;
    min-height: 500px;
    display: flex;
    flex-direction: column;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.ion-footer-wrapper .footer-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
}

.ion-footer-wrapper .footer-card-orange {
    background: linear-gradient(135deg, #4285f4 0%, #1a73e8 50%, #1557b0 100%);
    color: white;
}

.ion-footer-wrapper .footer-card-purple {
    background: linear-gradient(135deg, #ea4335 0%, #d93025 50%, #b31412 100%);
    color: white;
}

.ion-footer-wrapper .footer-card-dark {
    background: linear-gradient(135deg, #34a853 0%, #137333 50%, #0f5132 100%);
    color: white;
}

.ion-footer-wrapper .footer-card-icon {
    width: 60px;
    height: 60px;
    margin-bottom: 25px;
    opacity: 0.9;
    display: none;
}

.ion-footer-wrapper .footer-card-icon svg {
    width: 100%;
    height: 100%;
    display: none;
}

.ion-footer-wrapper .footer-card-title {
    font-size: 1.5rem;
    font-weight: bold;
    margin-block-start: 1em;
    margin-block-end: 1em;
    margin-inline-start: 0px;
    margin-inline-end: 0px;
    unicode-bidi: isolate;    
    letter-spacing: 0px;
    display: block;
}

.ion-footer-wrapper .footer-card-description {
    font-size: 1rem;
    line-height: 1.6;
    margin-bottom: 30px;
    opacity: 0.95;
    flex-grow: 1;
}

.ion-footer-wrapper .footer-card-features {
    list-style: none;
    margin-bottom: 30px;
    padding: 0;
}

.ion-footer-wrapper .footer-card-features li {
    position: relative;
    padding-left: 25px;
    margin-bottom: 10px;
    font-size: 0.95rem;
    opacity: 0.9;
}

.ion-footer-wrapper .footer-card-features li::before {
    content: '●';
    position: absolute;
    left: 0;
    top: 0;
    color: rgba(255, 255, 255, 0.7);
}

.ion-footer-wrapper .footer-card-orange .footer-card-features li::before {
    color: #fff3cd;
}

.ion-footer-wrapper .footer-card-purple .footer-card-features li::before {
    color: #ffa726;
}

.ion-footer-wrapper .footer-card-dark .footer-card-features li::before {
    color: #90a4ae;
}

.ion-footer-wrapper .footer-card-button {
    background: rgba(0, 0, 0, 0.3);
    border: none;
    color: white;
    padding: 15px 30px;
    border-radius: 8px;
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    letter-spacing: 0.5px;
    align-self: flex-start;
}

.ion-footer-wrapper .footer-card-button:hover {
    background: rgba(0, 0, 0, 0.5);
    transform: translateY(-2px);
}

/* Main Footer Section */
.ion-footer-wrapper .ion-main-footer {
    background: #1a1a1a;
    color: #e0e0e0;
    padding: 60px 20px 30px;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.ion-footer-wrapper .footer-container {
    max-width: 1200px;
    margin: 0 auto;
}

.ion-footer-wrapper .footer-top {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 40px;
    margin-bottom: 40px;
}

.ion-footer-wrapper .footer-branding {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.ion-footer-wrapper .footer-logo img {
    max-width: 200px;
    height: auto;
}

.ion-footer-wrapper .footer-branding h3 {
    font-size: 1.5rem;
    margin: 0;
    color: #f0f0f0;
}

.ion-footer-wrapper .footer-widget {
    padding: 20px;
    background: rgba(255, 255, 255, 0.05);
    border-radius: 12px;
}

.ion-footer-wrapper .footer-widget h4 {
    font-size: 1.3rem;
    margin: 0 0 15px 0;
    color: #f0f0f0;
}

.ion-footer-wrapper .footer-widget p {
    font-size: 0.95rem;
    line-height: 1.6;
    margin-bottom: 20px;
    color: #b0b0b0;
}

.ion-footer-wrapper .footer-widget ul {
    list-style: none;
    padding: 0;
    margin: 0 0 20px 0;
}

.ion-footer-wrapper .footer-widget ul li {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 8px;
    font-size: 0.9rem;
    color: #b0b0b0;
}

.ion-footer-wrapper .footer-widget ul li svg {
    width: 16px;
    height: 16px;
    fill: currentColor;
    flex-shrink: 0;
}

.ion-footer-wrapper .btn {
    display: inline-block;
    padding: 12px 24px;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s ease;
    font-size: 0.95rem;
}

.ion-footer-wrapper .btn-inquire {
    background: #4285f4;
    color: white;
}

.ion-footer-wrapper .btn-inquire:hover {
    background: #1a73e8;
    transform: translateY(-2px);
}

.ion-footer-wrapper .btn-license {
    background: #ea4335;
    color: white;
}

.ion-footer-wrapper .btn-license:hover {
    background: #d93025;
    transform: translateY(-2px);
}

.ion-footer-wrapper .footer-bottom {
    text-align: center;
    padding: 30px 20px;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
    margin-top: 40px;
}

.ion-footer-wrapper .footer-bottom p {
    font-family: 'Bebas Neue', sans-serif;
    font-size: 1rem;
    color: #94a3b8;
    margin: 10px 0;
    letter-spacing: 0.5px;
}

.ion-footer-wrapper .footer-bottom a {
    color: #94a3b8;
    text-decoration: none;
    transition: color 0.3s ease;
}

.ion-footer-wrapper .footer-bottom a:hover {
    color: #cbd5e1;
}

.ion-footer-wrapper .footer-links {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: 20px;
    margin: 15px 0;
}

.ion-footer-wrapper .footer-links a {
    font-size: 0.9rem;
}

.ion-footer-wrapper .image-credit-credits {
    font-size: 0.85rem;
    color: #6b7280;
    margin-top: 15px;
}

/* Responsive */
@media (max-width: 768px) {
    .ion-footer-wrapper .section-title {
        font-size: 2rem;
        letter-spacing: 1px;
    }
    
    .ion-footer-wrapper .footer-card {
        padding: 25px 20px;
    }
    
    .ion-footer-wrapper .footer-card-title {
        font-size: 1.6rem;
    }
    
    .ion-footer-wrapper .footer-card-description {
        font-size: 0.95rem;
    }
    
    .ion-footer-wrapper .footer-top {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="ion-footer-wrapper">
    <section class="find-yourself-section">
        <div class="footer-container-inner">
            <div class="section-header">
                <h1 class="section-title">FIND YOURSELF</h1>
                <p class="section-subtitle">Discover powerful tools and services from our trusted partners to elevate your business</p>
            </div>
            
            <div class="footer-cards-grid">
                <!-- Avenue I Card -->
                <div class="footer-card footer-card-orange">
                    <h2 class="footer-card-title">AVENUE I</h2>
                    <p class="footer-card-description">
                        Distribute your shows and content to the audiences you desire through y(our) innovative multi-platform streaming and broadcasting solution.
                    </p>
                    <ul class="footer-card-features">
                        <li>Dozens of Networks, 1-25,000+ cities</li>
                        <li>Stream live and "positioned" content</li>
                        <li>(Internet, Internet, Mobile, OTT)</li>
                        <li>Analytics and insights</li>
                    </ul>
                    <button class="footer-card-button">GET STARTED</button>
                </div>

                <!-- Mall of Champions Card -->
                <div class="footer-card footer-card-purple">
                    <h2 class="footer-card-title">MALL OF CHAMPIONS</h2>
                    <p class="footer-card-description">
                        Mall of Champions is the leading Hometown and Global Destination for authentic lifestyle merch and services, featuring Officially Licensed products from Pro and Collegiate Leagues and the ION Studio Store. Fundraise here!
                    </p>
                    <ul class="footer-card-features">
                        <li>Unique Hometown Goods</li>
                        <li>Family Famous & Talkos</li>
                        <li>Exclusive Everyday Lowest Prices</li>
                    </ul>
                    <button class="footer-card-button">SHOP NOW</button>
                </div>

                <!-- Connect.ions Card -->
                <div class="footer-card footer-card-dark">
                    <h2 class="footer-card-title">CONNECT.IONS</h2>
                    <p class="footer-card-description">
                        Use connect.ions to create, platform, manage, and grow your business and personal life
                    </p>
                    <ul class="footer-card-features">
                        <li>Life Management System</li>
                        <li>Achieve your Personal and Business Goals</li>
                    </ul>
                    <button class="footer-card-button">START GROWING</button>
                </div>
            </div>
        </div>
    </section>

    <footer class="ion-main-footer">
        <div class="footer-container">
            <div class="footer-top">
                <div class="footer-branding">
                    <a href="https://ions.com/" target="_blank" rel="noopener noreferrer" class="footer-logo">
                        <img src="https://ions.com/assets/logos/ion-logo-gold-collab.png" alt="ION Logo Golden Age">
                    </a>
                    <h3>ION <?= htmlspecialchars(strtoupper($city->city_name ?? 'LOCAL NETWORK')) ?></h3>
                </div>
                <div class="footer-widget">
                    <h4>Inquire About Advertising</h4>
                    <p>Advertise on the ION Local Network and reach millions of highly engaged, locally targeted viewers across our growing network of city-based channels.</p>
                    <ul>
                        <li><svg><use href="#icon-checkmark"/></svg>Targeted local advertising</li>
                        <li><svg><use href="#icon-checkmark"/></svg>High-visibility ad placements</li>
                        <li><svg><use href="#icon-checkmark"/></svg>Custom campaign options</li>
                    </ul>
                    <a href="mailto:advertising@ions.com" class="btn btn-inquire">Inquire About Advertising</a>
                </div>
                <div class="footer-widget">
                    <h4>Channel Licensing Opportunity</h4>
                    <p>Our licensing model is a strategic partnership that empowers content creators and businesses to generate revenue through content creation and targeted advertising.</p>
                    <ul>
                        <li><svg><use href="#icon-checkmark"/></svg>Generate revenue from content</li>
                        <li><svg><use href="#icon-checkmark"/></svg>Expand channel reach</li>
                        <li><svg><use href="#icon-checkmark"/></svg>Flexible partnership terms</li>
                    </ul>
                    <a href="mailto:licensing@ions.com" class="btn btn-license">Inquire About Licensing</a>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; <?= date('Y') ?> <a href="<?= htmlspecialchars($rootUrl) ?>">ION Local Network</a>. All rights reserved.</p>
                <div class="footer-links">
                    <a href="https://ions.com/about/" target="_blank" rel="noopener noreferrer">About Us</a>
                    <a href="https://ions.com/contact-us/" target="_blank" rel="noopener noreferrer">Contact Us</a>
                    <a href="https://ions.com/ion-licensing/" target="_blank" rel="noopener noreferrer">Licensing</a>
                    <a href="https://ions.com/privacy-policy/" target="_blank" rel="noopener noreferrer">Privacy Policy</a>
                    <a href="https://ions.com/terms-of-service/" target="_blank" rel="noopener noreferrer">Terms of Service</a>
                </div>
                <?php if (!empty($image_credit)) : ?>
                    <p class="image-credit-credits"><?= htmlspecialchars($image_credit) ?></p>
                <?php endif; ?>
            </div>
        </div>
    </footer>
</div>

<!-- SVG Icon Definitions -->
<svg width="0" height="0" style="position:absolute">
  <defs>
    <symbol id="icon-checkmark" viewBox="0 0 16 16">
        <path fill="currentColor" d="M13.854 3.646a.5.5 0 0 1 0 .708l-7 7a.5.5 0 0 1-.708 0l-3.5-3.5a.5.5 0 1 1 .708-.708L6.5 10.293l6.646-6.647a.5.5 0 0 1 .708 0z"/>
    </symbol>
  </defs>
</svg>