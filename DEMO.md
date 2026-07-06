# Portal Demo - Quick Start Guide

## 🚀 Access the Demo

Visit the demo page to see the portal in action:

```
http://your-app-url/portal-demo
```

**Local Development:**
```
http://localhost:8000/portal-demo
```

## 📦 What's Included

The demo includes:

### **4 Sample Products**

1. **Complete Laravel Guide** (Digital)
   - $49.99 one-time payment
   - E-book product

2. **Web Development Consultation** (Service)
   - $150.00 hourly rate
   - Consultation service

3. **Premium SaaS Plan** (Subscription)
   - $29.99/month or $287.88/year
   - Recurring subscription with multiple pricing options

4. **Laravel Developer T-Shirt** (Physical)
   - $24.99 one-time payment
   - Requires shipping address

### **Demo User**
- Email: `demo@paycan.com`
- User ID: `demo-user`
- Automatically created when accessing demo page

## 🎯 Testing Features

### 1. Browse Products
- View product cards with pricing
- See different product types (badges)
- Multiple pricing options for subscriptions

### 2. Checkout Flow
- Click "Buy" on any product
- Select payment gateway (Stripe/PayPal)
- Enter shipping address (for physical products)
- Preview order before purchase

### 3. Orders Management
- View purchase history
- Download digital products
- Copy license keys
- Filter by status

### 4. Subscriptions
- View active subscriptions
- Cancel/Resume subscriptions
- Change subscription plans
- Manage payment methods via gateway portal

## 🔧 Setup

### Run the Seeder (if not done already)

```bash
php artisan db:seed --class=PortalDemoSeeder
```

This creates the 4 sample products and their prices.

### Start the Development Server

```bash
php artisan serve
```

Then visit: `http://localhost:8000/portal-demo`

## 📋 Demo Page Features

The demo page includes:

- ✅ **Live Portal Preview** - Embedded iframe showing the actual portal
- ✅ **Portal URL** - Signed URL with 24-hour expiration
- ✅ **Embed Code** - Ready-to-copy HTML code
- ✅ **User Info** - Current demo user details
- ✅ **Controls** - Open in new tab, copy URL, view embed code

## 🔐 Security

The portal URL is:
- **Signed** - Tamper-proof
- **Expirable** - Valid for 24 hours (configurable)
- **User-scoped** - Only accessible to the specific user

## 🎨 Customization

### Change Link Expiration

Edit `app/Http/Controllers/PortalDemoController.php`:

```php
// Change from 24 hours to 48 hours
$portalUrl = PortalService::generatePortalUrl($user->id, 48);
```

### Modify Demo Products

Edit `database/seeders/PortalDemoSeeder.php` and re-run:

```bash
php artisan db:seed --class=PortalDemoSeeder
```

### Configure Portal Settings

Edit `config/portal.php`:

```php
return [
    'allowed_iframe_domains' => '*', // or specific domains
    'link_expiration_hours' => 24,
    'rate_limit' => 120,
];
```

## 📖 Full Documentation

For complete implementation guide, see [PORTAL.md](PORTAL.md)

## ✨ Next Steps

1. **Test the Portal** - Click around, try different features
2. **View Generated URLs** - Check the signed URL structure
3. **Copy Embed Code** - Use in your own application
4. **Read Documentation** - Learn about customization options
5. **Build Integration** - Integrate into your app using the SDK

## 🐛 Troubleshooting

### Demo Page Not Loading

1. Ensure server is running: `php artisan serve`
2. Check database is seeded: `php artisan db:seed --class=PortalDemoSeeder`
3. Clear cache: `php artisan config:clear`

### Portal Shows "Invalid Signature"

- The signed URL expired (24 hours)
- Refresh the demo page to generate a new URL

### No Products Showing

- Run the seeder: `php artisan db:seed --class=PortalDemoSeeder`
- Check database has products: `php artisan tinker` then `Product::count()`

## 📱 Mobile Testing

The portal is fully responsive. Test on different screen sizes:
- Desktop (1200px+)
- Tablet (768px - 1200px)
- Mobile (< 768px)

## 🎯 Production Deployment

Before deploying to production:

1. **Configure Allowed Domains**
   ```php
   // config/portal.php
   'allowed_iframe_domains' => [
       'https://yourapp.com',
       'https://app.yourapp.com'
   ]
   ```

2. **Set Environment Variables**
   ```env
   PORTAL_ALLOWED_DOMAINS=https://yourapp.com,https://app.yourapp.com
   PORTAL_LINK_EXPIRATION_HOURS=24
   ```

3. **Build Frontend Assets**
   ```bash
   npm run build
   ```

4. **Enable HTTPS**
   - Required for iframe embedding
   - Required for payment processing

Enjoy testing the portal! 🎉
