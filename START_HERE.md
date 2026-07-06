# 🎯 START HERE - PayCan Portal Demo

## ✅ Prerequisites Complete

The payment portal is fully implemented and ready to test!

---

## 🚀 Quick Start (3 Steps)

### Step 1: Seed Demo Products
```bash
php artisan db:seed --class=PortalDemoSeeder
```
This creates 4 sample products (digital, service, subscription, physical).

### Step 2: Start Development Servers

**Terminal 1 - Laravel:**
```bash
php artisan serve
```

**Terminal 2 - Vite (Asset Building):**
```bash
npm run dev
```

### Step 3: Open Demo Page
```
http://localhost:8000/portal-demo
```

---

## 🎨 What You'll See

### Demo Page Features:
- 📊 Feature highlights (Security, Features, iframe-ready)
- 👤 Demo user info (auto-created: demo@paycan.com)
- 🎛️ Controls (open in new tab, copy URLs, show embed code)
- 🖼️ **Live embedded portal in iframe**
- 📖 Integration examples and documentation links

### Portal Features (in iframe):
- **Products Page**: 4 sample products with different types
- **Checkout Page**: Payment method selection + shipping address
- **Orders Page**: Purchase history, downloads, licenses
- **Subscriptions Page**: Cancel/resume/change plans

---

## 📦 Sample Products Included

1. **Complete Laravel Guide** (Digital)
   - Type: Digital product
   - Price: $49.99 one-time
   - Features: Downloads, license keys

2. **Web Development Consultation** (Service)
   - Type: Service
   - Price: $150.00 hourly
   - Features: One-time purchase

3. **Premium SaaS Plan** (Subscription)
   - Type: Recurring subscription
   - Prices: $29.99/month OR $287.88/year
   - Features: Cancel, resume, change plans

4. **Laravel Developer T-Shirt** (Physical)
   - Type: Physical product
   - Price: $24.99 one-time
   - Features: Requires shipping address

---

## 🔍 Testing Checklist

### ✅ Navigation
- [ ] Visit demo page: `http://localhost:8000/portal-demo`
- [ ] See embedded portal loaded in iframe
- [ ] Click between Products/Orders/Subscriptions tabs

### ✅ Products Page
- [ ] View all 4 products in grid layout
- [ ] See product types (badges: digital, service, subscription, physical)
- [ ] View pricing for each product
- [ ] See multiple price options for SaaS subscription
- [ ] Click "Buy" button on any product

### ✅ Checkout Page
- [ ] Payment method selection (Stripe/PayPal buttons)
- [ ] Shipping address form (appears for physical products only)
- [ ] Order summary shows correct product and price
- [ ] Total amount displayed
- [ ] Cancel returns to products page

### ✅ Security
- [ ] Portal URL is signed (check URL has `signature=` parameter)
- [ ] Copy portal URL and check it works in new tab
- [ ] URL expires after 24 hours
- [ ] Cannot access portal without valid signature

### ✅ Demo Page Controls
- [ ] "Open in New Tab" button works
- [ ] "Show Embed Code" reveals HTML code
- [ ] "Copy Portal URL" copies to clipboard
- [ ] Embed code is properly formatted

---

## 🔧 Configuration

### Portal Settings
File: `config/portal.php`

```php
return [
    // Allow iframe embedding from all domains (dev only)
    'allowed_iframe_domains' => '*',

    // Portal links valid for 24 hours
    'link_expiration_hours' => 24,

    // Rate limit: 120 requests per minute
    'rate_limit' => 120,
];
```

### Environment Variables
File: `.env`

```env
# Portal Configuration (optional, uses defaults)
PORTAL_ALLOWED_DOMAINS=*
PORTAL_LINK_EXPIRATION_HOURS=24
PORTAL_RATE_LIMIT=120
```

---

## 📚 Documentation Structure

1. **START_HERE.md** (this file) - Quick start guide
2. **[QUICK_START.md](QUICK_START.md)** - 30-second setup
3. **[DEMO.md](DEMO.md)** - Detailed demo guide
4. **[PORTAL.md](PORTAL.md)** - Complete implementation guide

---

## 🎯 How It Works

### 1. URL Generation (Backend)
```php
use App\Services\PortalService;

// Generate signed URL for user
$portalUrl = PortalService::generatePortalUrl($userId, 24);
```

### 2. Embedding (Frontend)
```html
<iframe src="{{ $portalUrl }}" width="100%" height="800"></iframe>
```

### 3. Portal Routes (Authenticated via signed URL)
```
GET  /portal                           → Products listing
GET  /portal/checkout/{price}          → Checkout page
GET  /portal/orders                    → Orders management
GET  /portal/subscriptions             → Subscriptions management
```

### 4. SDK Integration (JavaScript)
```javascript
import PayCan from '@paycan/sdk'

const paycan = new PayCan({ apiUrl: 'https://pay.yourapp.com' })
const products = await paycan.products.list()
```

---

## 🔐 Security Features

✅ **Signed URLs**: Cryptographically secure, tamper-proof
✅ **Expiration**: Links automatically expire (default: 24 hours)
✅ **User-scoped**: Each user gets unique portal access
✅ **Rate Limiting**: 120 requests/minute protection
✅ **CSRF Protection**: Built-in Laravel protection
✅ **XSS Protection**: Content Security Policy headers
✅ **iframe Security**: Configurable domain allowlist

---

## 🐛 Troubleshooting

### Issue: "Unable to locate file in Vite manifest"
**Solution**: Make sure Vite dev server is running
```bash
npm run dev
```

### Issue: "No products showing in portal"
**Solution**: Run the seeder
```bash
php artisan db:seed --class=PortalDemoSeeder
```

### Issue: "Invalid or expired portal link"
**Solution**: URL expired (24h). Refresh demo page to generate new URL

### Issue: "Demo page won't load"
**Solution**: Check both servers are running
```bash
# Terminal 1
php artisan serve

# Terminal 2
npm run dev
```

### Issue: "Portal won't load in iframe"
**Solution**: Check config/portal.php
```php
'allowed_iframe_domains' => '*', // Allow all for testing
```

---

## 🚀 Integration in Your App

### Basic Integration
```php
// In your controller
use App\Services\PortalService;

public function customerPortal()
{
    $user = auth()->user();
    $portalUrl = PortalService::generatePortalUrl($user->id);

    return view('customer-portal', ['portalUrl' => $portalUrl]);
}
```

```blade
<!-- customer-portal.blade.php -->
<div class="container">
    <h1>Your Payment Portal</h1>
    <iframe
        src="{{ $portalUrl }}"
        width="100%"
        height="800"
        style="border: 1px solid #e5e7eb; border-radius: 8px;"
    ></iframe>
</div>
```

### Advanced Integration with SDK
```javascript
// Frontend integration
import PayCan from '@paycan/sdk'

const paycan = new PayCan({
  apiUrl: 'https://pay.yourapp.com'
})

// Authenticate
const response = await fetch('/api/paycan/token')
const { token } = await response.json()
paycan.setUserToken(token)

// Use API
const products = await paycan.products.list({ include: 'activePrices' })
const orders = await paycan.orders.list()
const subscriptions = await paycan.subscriptions.list()
```

---

## 📊 Portal Architecture

```
┌─────────────────────────────────────────────┐
│           Your Application                  │
│  ┌───────────────────────────────────────┐ │
│  │   Backend (Laravel)                   │ │
│  │   - Generate signed portal URL        │ │
│  │   - PortalService::generatePortalUrl()│ │
│  └───────────────┬───────────────────────┘ │
│                  │ Signed URL               │
│  ┌───────────────▼───────────────────────┐ │
│  │   Frontend (Your UI)                  │ │
│  │   - Embed portal in <iframe>         │ │
│  └───────────────────────────────────────┘ │
└─────────────────────────────────────────────┘
                   │
                   │ Signed URL in iframe src
                   │
┌──────────────────▼──────────────────────────┐
│         PayCan Portal (Embedded)            │
│  ┌────────────────────────────────────────┐ │
│  │  Portal Routes (/portal/*)             │ │
│  │  - Products listing                    │ │
│  │  - Checkout flow                       │ │
│  │  - Orders management                   │ │
│  │  - Subscriptions management            │ │
│  └────────────────────────────────────────┘ │
│                                              │
│  Authentication: Signed URL Middleware       │
│  Security: Rate limiting, CSRF, XSS         │
│  UI: Vue + Tailwind + shadcn-vue            │
└──────────────────────────────────────────────┘
```

---

## ✨ Next Steps

1. ✅ **Test the Demo** - Visit `http://localhost:8000/portal-demo`
2. ✅ **Explore Features** - Try all 4 portal sections
3. ✅ **Copy Embed Code** - Test embedding in your own page
4. ✅ **Read Docs** - Check [PORTAL.md](PORTAL.md) for full guide
5. ✅ **Integrate** - Add portal to your application

---

## 🎉 You're Ready!

**Everything is set up and working!**

Visit: `http://localhost:8000/portal-demo`

Need help? Check the documentation:
- [QUICK_START.md](QUICK_START.md) - Fast setup
- [DEMO.md](DEMO.md) - Testing guide
- [PORTAL.md](PORTAL.md) - Full documentation

Enjoy your payment portal! 🚀
