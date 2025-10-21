<?php
/**
 * ION Footer Component
 * Reusable footer for ION city pages
 */

// Ensure we have city data available
global $city, $image_credit;
?>

<style>
.find-yourself-section {
    background: linear-gradient(135deg, #2a2a2a 0%, #1a1a1a 100%);
    min-height: 100vh;
    padding: 60px 20px;
    color: white;
}

.container {
    max-width: 1200px;
    margin: 0 auto;
}

.header {
    text-align: center;
    margin-bottom: 60px;
}

.title {
    font-size: 3.5rem;
    font-weight: 700;
    letter-spacing: 3px;
    margin-bottom: 1px;
    color: #e0e0e0;
}

.subtitle {
    font-size: 1.2rem;
    color: #b0b0b0;
    max-width: 600px;
    margin: 0 auto;
    line-height: 1.5;
}

.cards-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
    gap: 30px;
    margin-top: 60px;
}

.card {
    border-radius: 20px;
    padding: 40px 30px;
    position: relative;
    overflow: hidden;
    min-height: 500px;
    display: flex;
    flex-direction: column;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.card:hover {
    transform: translateY(-5px);
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
}

.card-orange {
    background: linear-gradient(135deg, #4285f4 0%, #1a73e8 50%, #1557b0 100%);
    color: white;
}

.card-purple {
    background: linear-gradient(135deg, #ea4335 0%, #d93025 50%, #b31412 100%);
    color: white;
}

.card-dark {
    background: linear-gradient(135deg, #34a853 0%, #137333 50%, #0f5132 100%);
    color: white;
}

.card-icon {
    width: 60px;
    height: 60px;
    margin-bottom: 25px;
    opacity: 0.9;
    display: none;
}

.card-icon svg {
    width: 100%;
    height: 100%;
    display: none;
}

.card-title {
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

.card-description {
    font-size: 1rem;
    line-height: 1.6;
    margin-bottom: 30px;
    opacity: 0.95;
    flex-grow: 1;
}

.card-features {
    list-style: none;
    margin-bottom: 30px;
}

.card-features li {
    position: relative;
    padding-left: 25px;
    margin-bottom: 10px;
    font-size: 0.95rem;
    opacity: 0.9;
}

.card-features li::before {
    content: '●';
    position: absolute;
    left: 0;
    top: 0;
    color: rgba(255, 255, 255, 0.7);
}

.card-orange .card-features li::before {
    color: #fff3cd;
}

.card-purple .card-features li::before {
    color: #ffa726;
}

.card-dark .card-features li::before {
    color: #90a4ae;
}

.card-button {
    background: rgba(0, 0, 0, 0.3);
    border: none;
    color: white;
    padding: 15px 30px;
    border-radius: 8px;
    font-size: 1rem;
    font-weight: 600;
    letter-spacing: 1px;
    cursor: pointer;
    transition: all 0.3s ease;
    text-transform: uppercase;
    margin-top: auto;
}

.card-orange .card-button {
    background: rgba(0, 0, 0, 0.2);
    border: 2px solid rgba(255, 255, 255, 0.3);
}

.card-orange .card-button:hover {
    background: rgba(0, 0, 0, 0.4);
    border-color: rgba(255, 255, 255, 0.5);
    transform: translateY(-2px);
}

.card-purple .card-button {
    background: rgba(0, 0, 0, 0.2);
    border: 2px solid rgba(255, 255, 255, 0.3);
    color: white;
}

.card-purple .card-button:hover {
    background: rgba(0, 0, 0, 0.4);
    border-color: rgba(255, 255, 255, 0.5);
    transform: translateY(-2px);
}

.card-dark .card-button {
    background: rgba(255, 255, 255, 0.1);
    border: 2px solid rgba(255, 255, 255, 0.2);
}

.card-dark .card-button:hover {
    background: rgba(255, 255, 255, 0.2);
    border-color: rgba(255, 255, 255, 0.3);
    transform: translateY(-2px);
}

/* Responsive Design */
@media (max-width: 1024px) {
    .cards-grid {
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 25px;
    }
    
    .title {
        font-size: 3rem;
    }
}

@media (max-width: 768px) {
    .find-yourself-section {
        padding: 60px 15px;
    }
    
    .title {
        font-size: 2.5rem;
        letter-spacing: 2px;
    }
    
    .subtitle {
        font-size: 1.1rem;
    }
    
    .cards-grid {
        grid-template-columns: 1fr;
        gap: 20px;
        margin-top: 40px;
    }
    
    .card {
        padding: 30px 25px;
        min-height: auto;
    }
    
    .card-title {
        font-size: 1.8rem;
    }
}

@media (max-width: 480px) {
    .title {
        font-size: 2rem;
        letter-spacing: 1px;
    }
    
    .card {
        padding: 25px 20px;
    }
    
    .card-title {
        font-size: 1.6rem;
    }
    
    .card-description {
        font-size: 0.95rem;
    }
}
</style>

    <section class="find-yourself-section">
        <div class="container">
            <div class="header">
                <h1 class="title">FIND YOURSELF</h1>
                <p class="subtitle">Discover powerful tools and services from our trusted partners to elevate your business</p>
            </div>
            
            <div class="cards-grid">
                <!-- Avenue I Card -->
                <div class="card card-orange">
                    <div class="card-icon">
                        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M12 19V5M5 12l7-7 7 7"/>
                        </svg>
                    </div>
                    <h2 class="card-title">AVENUE I</h2>
                    <p class="card-description">
                        Distribute your shows and content to the audiences you desire through y(our) innovative multi-platform streaming and broadcasting solution.
                    </p>
                    <ul class="card-features">
                        <li>Dozens of Networks, 1-25,000+ cities</li>
                        <li>Stream live and "positioned" content</li>
                        <li>(Internet, Internet, Mobile, OTT)</li>
                        <li>Analytics and insights</li>
                    </ul>
                    <button class="card-button">GET STARTED</button>
                </div>

                <!-- Mall of Champions Card -->
                <div class="card card-purple">
                    <div class="card-icon">
                        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M6 2L3 6v14c0 1.1.9 2 2 2h14c0-1.1-.9-2-2-2V6l-3-4H6zM6 6h12v12H6V6z"/>
                            <path d="M9 6v6h6V6"/>
                        </svg>
                    </div>
                    <h2 class="card-title">MALL OF CHAMPIONS</h2>
                    <p class="card-description">
                        Mall of Champions is the leading Hometown and Global Destination for authentic lifestyle merch and services, featuring Officially Licensed products from Pro and Collegiate Leagues and the ION Studio Store. Fundraise here!
                    </p>
                    <ul class="card-features">
                        <li>Unique Hometown Goods</li>
                        <li>Family Famous & Talkos</li>
                        <li>Exclusive Everyday Lowest Prices</li>
                    </ul>
                    <button class="card-button">SHOP NOW</button>
                </div>

                <!-- Connect.ions Card -->
                <div class="card card-dark">
                    <div class="card-icon">
                        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M3 3v18h18"/>
                            <path d="M18.7 8l-5.1 5.2-2.8-2.7L7 14.3"/>
                        </svg>
                    </div>
                    <h2 class="card-title">CONNECT.IONS</h2>
                    <p class="card-description">
                        Use connect.ions to create, platform, manage, and grow your business and personal life
                    </p>
                    <ul class="card-features">
                        <li>Life Management System</li>
                        <li>Achieve your Personal and Business Goals</li>
                    </ul>
                    <button class="card-button">START GROWING</button>
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
                <h3>ION <?= esc_html(strtoupper($city->city_name ?? 'LOCAL NETWORK')) ?></h3>
                <?php include __DIR__ . '/ionsocials.php'; ?>
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
            <p>&copy; <?php echo date('Y'); ?> ION Local Network. All rights reserved.</p>
            <div class="footer-links">
                <a href="https://ions.com/about/" target="_blank" rel="noopener noreferrer">About Us</a>
                <a href="https://ions.com/contact-us/" target="_blank" rel="noopener noreferrer">Contact Us</a>
                <a href="https://ions.com/ion-licensing/" target="_blank" rel="noopener noreferrer">Licensing</a>
                <a href="https://ions.com/privacy-policy/" target="_blank" rel="noopener noreferrer">Privacy Policy</a>
                <a href="https://ions.com/terms-of-service/" target="_blank" rel="noopener noreferrer">Terms of Service</a>
            </div>
            <?php if (!empty($image_credit)) : ?>
                <p class="image-credit-credits"><?= esc_html($image_credit) ?></p>
            <?php endif; ?>
        </div>
    </div>
</footer>

<!-- SVG Icon Definitions -->
<svg width="0" height="0" style="position:absolute">
  <defs>
    <symbol id="icon-checkmark" viewBox="0 0 16 16">
        <path fill="currentColor" d="M13.854 3.646a.5.5 0 0 1 0 .708l-7 7a.5.5 0 0 1-.708 0l-3.5-3.5a.5.5 0 1 1 .708-.708L6.5 10.293l6.646-6.647a.5.5 0 0 1 .708 0z"/>
    </symbol>
    <symbol id="icon-facebook" viewBox="0 0 16 16">
        <path fill="currentColor" d="M16 8.049c0-4.446-3.582-8.05-8-8.05C3.58 0-.002 3.603-.002 8.05c0 4.017 2.926 7.347 6.75 7.951v-5.625h-2.03V8.05H6.75V6.275c0-2.017 1.195-3.131 3.022-3.131.876 0 1.791.157 1.791.157v1.98h-1.009c-.993 0-1.303.621-1.303 1.258v1.51h2.218l-.354 2.326H9.25V16c3.824-.604 6.75-3.934 6.75-7.951z"/>
    </symbol>
    <symbol id="icon-x" viewBox="0 0 16 16">
        <path fill="currentColor" d="M9.294 6.928L14.357 1h-1.2L8.762 6.147 5.25 1H1.2l5.299 7.599L1.2 15h1.2l4.638-5.372L10.05 15h4.05L9.294 6.928zM7.479 13.208l-1.077-1.542-4.246-6.085h1.844l.853 1.223 3.984 5.71 1.077 1.542 4.247 6.086h-1.844l-.853-1.223-3.985-5.71z"/>
    </symbol>
    <symbol id="icon-linkedin" viewBox="0 0 16 16">
        <path fill="currentColor" d="M0 1.146C0 .513.526 0 1.175 0h13.65C15.474 0 16 .513 16 1.146v13.708c0 .633-.526 1.146-1.175 1.146H1.175C.526 16 0 15.487 0 14.854V1.146zm4.943 12.248V6.169H2.542v7.225h2.401zm-1.2-8.212c.837 0 1.358-.554 1.358-1.248-.015-.709-.52-1.248-1.342-1.248-.822 0-1.359.54-1.359 1.248 0 .694.521 1.248 1.327 1.248h.016zm4.908 8.212V9.359c0-.216.016-.432.08-.586.173-.431.568-.878 1.232-.878.869 0 1.216.662 1.216 1.634v3.865h2.401V9.25c0-2.22-1.184-3.252-2.764-3.252-1.274 0-1.845.7-2.165 1.193v.025h-.016a5.54 5.54 0 0 1 .016-.025V6.169h-2.4c.03.678 0 7.225 0 7.225h2.4z"/>
    </symbol>
    <symbol id="icon-whatsapp" viewBox="0 0 16 16">
        <path fill="currentColor" d="M13.601 2.326A7.854 7.854 0 0 0 7.994 0C3.627 0 .068 3.558.064 7.926c0 1.399.366 2.76 1.057 3.965L0 16l4.204-1.102a7.933 7.933 0 0 0 3.79.965h.004c4.368 0 7.926-3.558 7.93-7.93A7.898 7.898 0 0 0 13.6 2.326zM7.994 14.521a6.573 6.573 0 0 1-3.356-.92l-.24-.144-2.494.654.666-2.433-.156-.251a6.56 6.56 0 0 1-1.007-3.505c0-3.626 2.957-6.584 6.591-6.584a6.56 6.56 0 0 1 4.66 1.931 6.557 6.557 0 0 1 1.928 4.66c-.004 3.639-2.961 6.592-6.592 6.592zm3.615-4.934c-.197-.099-1.17-.578-1.353-.646-.182-.065-.315-.099-.445.099-.133.197-.513.646-.627.775-.114.133-.232.148-.43.05-.197-.1-.836-.308-1.592-.985-.59-.525-.985-1.175-1.103-1.372-.114-.198-.011-.304.088-.403.087-.088.197-.232.296-.346.1-.114.133-.198.198-.33.065-.134.034-.248-.015-.347-.05-.099-.445-1.076-.612-1.47-.16-.389-.323-.335-.445-.34-.114-.007-.247-.007-.38-.007a.729.729 0 0 0-.529.247c-.182.198-.691.677-.691 1.654 0 .977.71 1.916.81 2.049.098.133 1.394 2.132 3.383 2.992.47.205.84.326 1.129.418.475.152.904.129 1.246.08.38-.058 1.171-.48 1.338-.943.164-.464.164-.86.114-.943-.049-.084-.182-.133-.38-.232z"/>
    </symbol>
    <symbol id="icon-email" viewBox="0 0 16 16">
        <path fill="currentColor" d="M.05 3.555A2 2 0 0 1 2 2h12a2 2 0 0 1 1.95 1.555L8 8.414.05 3.555ZM0 4.697v7.104l5.803-3.558L0 4.697ZM6.761 8.83l-6.57 4.027A2 2 0 0 0 2 14h12a2 2 0 0 0 1.808-1.144l-6.57-4.027L8 9.586l-1.239-.757Zm3.436-.586L16 11.801V4.697l-5.803 3.546Z"/>
    </symbol>
    <symbol id="icon-tiktok" viewBox="0 0 16 16">
        <path fill="currentColor" d="M9 0h1.98c.144.715.54 1.617 1.235 2.31C12.895 3.002 13.797 3.4 14.512 3.543v2.079c-.82-.241-1.717-.62-2.31-1.235-.595-.617-.994-1.52-1.235-2.31V11.095a3.6 3.6 0 1 1-2.468-3.415V5.987c-.554.017-1.119.078-1.723.24a3.6 3.6 0 1 0 3.942 3.65V.002z"/>
    </symbol>
    <symbol id="icon-instagram" viewBox="0 0 16 16">
        <path fill="currentColor" d="M8 0C5.829 0 5.556.01 4.703.048 3.85.088 3.269.222 2.76.42a3.917 3.917 0 0 0-1.417.923A3.917 3.917 0 0 0 .42 2.76C.222 3.268.087 3.85.048 4.7.01 5.555 0 5.827 0 8.001c0 2.172.01 2.444.048 3.297.04.852.174 1.433.372 1.942.205.526.478.972.923 1.417.444.445.89.719 1.416.923.51.198 1.09.333 1.942.372C5.555 15.99 5.827 16 8 16s2.444-.01 3.298-.048c.851-.04 1.434-.174 1.943-.372a3.916 3.916 0 0 0 1.416-.923c.445-.445.718-.891.923-1.416.197-.509.332-1.09.372-1.942C15.99 10.445 16 10.173 16 8s-.01-2.445-.048-3.299c-.04-.851-.175-1.433-.372-1.941a3.926 3.926 0 0 0-.923-1.417A3.911 3.911 0 0 0 13.24.42c-.51-.198-1.092-.333-1.943-.372C10.443.01 10.172 0 7.998 0h.003zm-.717 1.442h.718c2.136 0 2.389.007 3.232.047.78.035 1.204.166 1.486.275.373.145.64.319.92.599s.453.546.598.92c.11.281.24.705.275 1.485.039.843.047 1.096.047 3.231s-.008 2.389-.047 3.232c-.035.78-.166 1.203-.275 1.485a2.5 2.5 0 0 1-.599.919c-.28.28-.546.453-.92.598-.28.11-.704.24-1.485.276-.843.038-1.096.047-3.232.047s-2.39-.009-3.233-.047c-.78-.036-1.203-.166-1.485-.276a2.5 2.5 0 0 1-.92-.598 2.5 2.5 0 0 1-.6-.92c-.109-.281-.24-.705-.275-1.485-.038-.843-.046-1.096-.046-3.233s.008-2.388.046-3.231c.036-.78.166-1.204.276-1.486.145-.373.319-.64.599-.92s.546-.453.92-.598c.282-.11.705-.24 1.485-.276.844-.038 1.096-.046 3.233-.046zm3.944 1.144a.997.997 0 1 0-1.994 0 .997.997 0 0 0 1.994 0zm-3.947 1.45a4.062 4.062 0 1 0 0 8.125 4.062 4.062 0 0 0 0-8.125zm0 6.688a2.626 2.626 0 1 1 0-5.252 2.626 2.626 0 0 1 0 5.252z"/>
    </symbol>
    <symbol id="icon-youtube" viewBox="0 0 16 16">
        <path fill="currentColor" d="M8.051 1.999h.089c.822.003 4.987.033 6.11.335a2.01 2.01 0 0 1 1.415 1.42c.101.38.172.883.22 1.402l.01.104.022.26.008.042c.065.52.073 1.585.073 1.585s.008 1.065-.073 1.585l-.008.042-.022.26-.01.104c-.048.519-.119 1.023-.22 1.402a2.007 2.007 0 0 1-1.415 1.42c-1.16.312-5.569.334-6.18.335l-.142.002-.071.001H8l-.071-.001-.142-.002c-.619-.001-5.028-.023-6.18-.335a2.007 2.007 0 0 1-1.415-1.42c-.101-.38-.172-.883-.22-1.402l-.01-.104-.022-.26-.008-.042c-.065-.52-.073-1.585-.073-1.585s-.008-1.065.073-1.585l.008-.042.022.26.01-.104c.048-.519.119-1.023.22-1.402a2.007 2.007 0 0 1 1.415-1.42c.487-.13 1.544-.21 2.654-.26l.17-.007.122-.005.011-.001.006-.001h.004l.006.001.011.001.122.005.17.007c1.11.05 2.167.13 2.654.26z"/>
        <path fill="currentColor" d="M11.5 6.399a.25.25 0 0 0-.119-.025l-3.429-.529a.25.25 0 0 0-.264.125l-.172 1.039a.251.251 0 0 0 .1.25l3.182 1.853c.23.135.577.106.692-.021l.172-1.038a.25.25 0 0 0-.1-.251l-3.182-1.853z"/>
    </symbol>
  </defs>
</svg>