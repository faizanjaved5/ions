# PHP Checkout MVP

A minimal viable product (MVP) extracted from the Next.js Landing Page project, demonstrating core checkout functionality using PHP.

## 📋 Overview

This PHP implementation recreates the essential checkout functionality from the Next.js project, including:

- Product cart management
- Customer information collection
- Address validation
- Coupon code application
- Payment gateway integration (Stripe & PayPal)
- Session management
- Form validation

## 🏗️ Architecture

### Directory Structure

```
php-checkout-mvp/
├── classes/
│   ├── CheckoutManager.php     # Core checkout logic
│   ├── ApiClient.php          # Sperse API integration
│   └── ValidationHelper.php   # Form validation utilities
├── config/
│   └── config.php             # Configuration (replaces .env)
├── public/
│   ├── checkout.php           # Main checkout page
│   └── success.php            # Payment success page
├── templates/                 # Future template files
└── utils/                     # Additional utilities
```

### Key Components

#### 1. CheckoutManager.php
- **Purpose**: Central checkout logic management
- **Extracted from**: Next.js checkout components and context
- **Key Features**:
  - Cart management (add/remove items)
  - Customer info validation
  - Address handling
  - Payment request submission
  - Session management

#### 2. ApiClient.php
- **Purpose**: Sperse Platform API integration
- **Extracted from**: `src/services/api.service.ts`
- **Key Features**:
  - Product request submission
  - Domain variables retrieval
  - Coupon validation
  - Tax estimation
  - Error handling

#### 3. ValidationHelper.php
- **Purpose**: Form validation and data sanitization
- **Extracted from**: Next.js Zod validation schemas
- **Key Features**:
  - Customer data validation
  - Address validation
  - Product validation
  - CSRF protection

## 🚀 Setup Instructions

### Prerequisites
- PHP 7.4 or higher
- cURL extension enabled
- Web server (Apache/Nginx) or PHP built-in server

### Installation

1. **Clone/Copy the files** to your web server directory

2. **Configure the application**:
   ```bash
   # Copy the config file and customize if needed
   cp config/config.php config/config.local.php
   ```

3. **Set up web server** or use PHP built-in server:
   ```bash
   cd php-checkout-mvp/public
   php -S localhost:8000
   ```

4. **Access the application**:
   ```
   http://localhost:8000/checkout.php
   ```

## ⚙️ Configuration

### config.php
The configuration file replaces the Next.js `.env` file:

```php
// API Configuration
const API_URL = 'https://beta.sperse.com';

// Default settings
const DEFAULT_DOMAIN_TENANT = 'imarketer-sperse.vercel.app';
const DEFAULT_CURRENCY = 'USD';

// Payment Gateways
const PAYMENT_GATEWAYS = [
    'STRIPE' => ['name' => 'Stripe', 'enabled' => true],
    'PAYPAL' => ['name' => 'PayPal', 'enabled' => true]
];
```

### Environment-specific Configuration
Create `config/config.local.php` for environment-specific overrides:

```php
<?php
// Override settings for local development
define('Config::DEBUG_MODE', true);
define('Config::API_URL', 'https://dev.sperse.com');
?>
```

## 🔄 API Integration

### Sperse Platform Endpoints

The MVP integrates with the same Sperse Platform APIs as the Next.js version:

- **Submit Product Request**: `/api/services/CRM/PublicProduct/SubmitProductRequest`
- **Get Domain Variables**: `/api/services/CRM/ContactLandingPage/GetDomainVariables`
- **Get Contact Info**: `/api/services/CRM/ContactLandingPage/GetPublicContactInfo`
- **Coupon Validation**: `/api/services/CRM/PublicProduct/GetCouponInfo`

### Request Format
```php
$requestData = [
    'products' => [
        [
            'productId' => 606,
            'optionId' => null,
            'unit' => 'piece',
            'price' => 29.99,
            'quantity' => 1
        ]
    ],
    'tenantId' => 1133,
    'paymentGateway' => 'Stripe',
    'firstName' => 'John',
    'lastName' => 'Doe',
    'email' => 'john@example.com',
    // ... additional fields
];
```

## 🧪 Testing

### Demo Usage

1. **Add a product** using the demo form
2. **Fill customer information**
3. **Add billing address**
4. **Apply coupon code** (optional)
5. **Submit payment** via Stripe or PayPal

### Test Data
- **Tenant ID**: 1133 (default demo tenant)
- **Product ID**: 606 (demo product)
- **Test Coupon**: Use any code to test coupon validation

## 🔒 Security Features

### Implemented Security
- **CSRF Protection**: Token-based form security
- **Input Validation**: Comprehensive data validation
- **Session Management**: Secure session handling
- **Data Sanitization**: XSS prevention
- **SSL Support**: HTTPS validation

### Security Notes
- Currently uses demo tenant (1133) for testing
- Real payment processing requires valid API credentials
- SSL should be enabled for production use

## 🚧 Current Limitations

### MVP Scope
- **Payment Processing**: Redirects to external payment gateways
- **Tax Calculation**: Simplified 8% tax rate (needs Stripe Tax integration)
- **Product Catalog**: Manual product entry (no dynamic catalog)
- **Multi-language**: Not implemented (English only)
- **Themes**: Basic styling only

### Future Enhancements
- Dynamic product catalog integration
- Advanced tax calculation
- Multi-language support
- Enhanced UI/UX
- Admin dashboard
- Order management

## 🔗 Relationship to Next.js Project

### Extracted Functionality

| Next.js Component | PHP Equivalent | Status |
|------------------|----------------|---------|
| `newThemesform.tsx` | `CheckoutManager.php` | ✅ Core logic extracted |
| `api.service.ts` | `ApiClient.php` | ✅ API integration complete |
| Zod validation | `ValidationHelper.php` | ✅ Validation rules ported |
| React context | PHP sessions | ✅ State management |
| `Paypal.tsx` | Integrated in checkout | ✅ PayPal support |

### Maintained Compatibility
- Same API endpoints and request format
- Compatible with existing Sperse Platform
- Identical validation rules
- Same checkout flow logic

## 📈 Next Steps

1. **Test with real Sperse tenant data**
2. **Implement proper error logging**
3. **Add email notifications**
4. **Enhance UI with better styling**
5. **Add admin interface for order management**
6. **Implement webhook handling for payment status**

## 🤝 Contributing

This MVP serves as a foundation for PHP-based checkout implementation. Feel free to extend and enhance based on your specific requirements.

## 📝 Notes

- This MVP demonstrates the core checkout functionality extracted from the Next.js project
- Production deployment requires proper server configuration and SSL certificates
- Payment gateway credentials need to be configured for live transactions
- The codebase maintains compatibility with the original Sperse Platform APIs
