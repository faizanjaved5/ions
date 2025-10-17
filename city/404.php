<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 - Page Not Found | ION Local Network</title>
    <meta name="description" content="The requested ION city page could not be found. Explore other ION channels or return to the main site.">
    <meta name="robots" content="noindex, nofollow">
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="/assets/icons/favicon.ico">
    <link rel="icon" type="image/png" sizes="16x16" href="/assets/icons/favicon-16x16.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/assets/icons/favicon-32x32.png">
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #1f2937 0%, #111827 100%);
            color: #fff;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            line-height: 1.6;
        }
        
        .error-container {
            text-align: center;
            padding: 2rem;
            max-width: 600px;
            width: 100%;
        }
        
        .error-code {
            font-family: 'Bebas Neue', sans-serif;
            font-size: 8rem;
            font-weight: 400;
            color: #6366f1;
            text-shadow: 0 0 30px rgba(99, 102, 241, 0.3);
            margin-bottom: 1rem;
            line-height: 1;
        }
        
        .error-title {
            font-family: 'Bebas Neue', sans-serif;
            font-size: 2.5rem;
            color: #f9fafb;
            margin-bottom: 1rem;
            letter-spacing: 2px;
        }
        
        .error-message {
            font-size: 1.1rem;
            color: #d1d5db;
            margin-bottom: 2rem;
            max-width: 500px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .error-actions {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
            margin-bottom: 3rem;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            border-radius: 0.5rem;
            text-decoration: none;
            transition: all 0.2s ease;
            font-size: 0.95rem;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(99, 102, 241, 0.3);
        }
        
        .btn-secondary {
            background: rgba(75, 85, 99, 0.5);
            color: #d1d5db;
            border: 1px solid rgba(209, 213, 219, 0.2);
        }
        
        .btn-secondary:hover {
            background: rgba(75, 85, 99, 0.7);
            color: white;
        }
        
        .suggestions {
            background: rgba(31, 41, 55, 0.5);
            border: 1px solid rgba(75, 85, 99, 0.3);
            border-radius: 0.75rem;
            padding: 2rem;
            backdrop-filter: blur(10px);
        }
        
        .suggestions h3 {
            font-family: 'Bebas Neue', sans-serif;
            font-size: 1.5rem;
            color: #f9fafb;
            margin-bottom: 1rem;
            letter-spacing: 1px;
        }
        
        .suggestions ul {
            list-style: none;
            text-align: left;
        }
        
        .suggestions li {
            color: #d1d5db;
            margin-bottom: 0.75rem;
            padding-left: 1.5rem;
            position: relative;
        }
        
        .suggestions li::before {
            content: '→';
            position: absolute;
            left: 0;
            color: #6366f1;
            font-weight: bold;
        }
        
        .logo {
            margin-bottom: 2rem;
        }
        
        .logo img {
            max-height: 80px;
        }
        
        /* Floating animation for the 404 */
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
        }
        
        .error-code {
            animation: float 3s ease-in-out infinite;
        }
        
        /* Responsive design */
        @media (max-width: 768px) {
            .error-code {
                font-size: 6rem;
            }
            
            .error-title {
                font-size: 2rem;
            }
            
            .error-container {
                padding: 1rem;
            }
            
            .error-actions {
                flex-direction: column;
                align-items: center;
            }
            
            .btn {
                width: 100%;
                max-width: 300px;
                justify-content: center;
            }
        }
        
        @media (max-width: 480px) {
            .error-code {
                font-size: 4rem;
            }
            
            .error-title {
                font-size: 1.5rem;
            }
            
            .suggestions {
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="logo">
            <img src="https://ions.com/wp-content/uploads/2024/08/ion-logo-purple-collaboration.png" alt="ION Logo">
        </div>
        
        <div class="error-code">404</div>
        <h1 class="error-title">City Not Found</h1>
        <p class="error-message">
            The ION city channel you're looking for doesn't exist or may have been moved. 
            Don't worry, there are plenty of other amazing local channels to explore!
        </p>
        
        <div class="error-actions">
            <a href="https://ions.com/" class="btn btn-primary">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                    <polyline points="9,22 9,12 15,12 15,22"/>
                </svg>
                ION Home
            </a>
            <a href="javascript:history.back()" class="btn btn-secondary">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M19 12H6m6-6l-6 6 6 6"/>
                </svg>
                Go Back
            </a>
        </div>
        
        <div class="suggestions">
            <h3>What you can do:</h3>
            <ul>
                <li>Check the URL for typos</li>
                <li>Visit the main ION website to find your city</li>
                <li>Browse other available ION city channels</li>
                <li>Contact us if you believe this is an error</li>
            </ul>
        </div>
    </div>

    <script>
        // Add some interactive elements
        document.addEventListener('DOMContentLoaded', function() {
            const errorCode = document.querySelector('.error-code');
            
            // Add click effect to 404
            errorCode.addEventListener('click', function() {
                this.style.transform = 'scale(1.1)';
                this.style.color = '#8b5cf6';
                setTimeout(() => {
                    this.style.transform = 'scale(1)';
                    this.style.color = '#6366f1';
                }, 200);
            });
            
            // Add hover effect to buttons
            const buttons = document.querySelectorAll('.btn');
            buttons.forEach(button => {
                button.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-2px)';
                });
                
                button.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                });
            });
        });
    </script>
</body>
</html>