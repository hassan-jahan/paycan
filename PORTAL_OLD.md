# PayCan Embedded Payment Portal

A secure, iframe-embeddable payment portal for managing products, orders, and subscriptions.

## Features

✨ **Complete Payment Flow**
- Product browsing with pricing
- Checkout with payment method selection
- Shipping address capture (for physical products)
- Order management
- Subscription management

🔒 **Security**
- Signed URL authentication with expiration
- Rate limiting
- XSS protection
- CSRF protection
- Secure iframe embedding

🎨 **Modern UI**
- Built with Vue 3 + Inertia.js
- shadcn-vue components
- Tailwind CSS v4
- Responsive design
- Dark mode support

📦 **Iframe-Ready**
- Minimal layout for embedding
- Responsive sizing
- postMessage support (for future enhancements)

## Quick Start

### 1. Generate Portal URL

Use the `PortalService` to generate signed URLs:

```php
use App\Services\PortalService;

// Generate portal link for a user (valid for 24 hours)
$portalUrl = PortalService::generatePortalUrl($userId);

// Generate with custom expiration (48 hours)
$portalUrl = PortalService::generatePortalUrl($userId, 48);

// Generate checkout link directly
$checkoutUrl = PortalService::generateCheckoutUrl($userId, $priceId);
```

### 2. Embed in Your Application

#### Option A: Direct Iframe

```html
<iframe
  src="{{ $portalUrl }}"
  width="100%"
  height="800"
  frameborder="0"
  style="border: 1px solid #e5e7eb; border-radius: 8px;"
></iframe>
```

#### Option B: Use Helper Method

```php
$embedCode = PortalService::getEmbedCode($portalUrl, 800, 600);
echo $embedCode;
```

#### Option C: Via JavaScript SDK

```javascript
import PayCan from '@paycan/sdk'

const paycan = new PayCan({
  apiUrl: 'https://pay.yourapp.com'
})

// Authenticate user
const response = await fetch('/api/paycan/token')
const { token } = await response.json()
paycan.setUserToken(token)

// Get products
const { data: products } = await paycan.products.list({
  include: 'activePrices'
})
```

## API Integration

### Using the SDK

The portal works seamlessly with the PayCan JavaScript SDK:

```javascript
// List products
const products = await paycan.products.list()

// Get specific product
const { product } = await paycan.products.get('prod-123', {
  include: 'activePrices'
})

// Create checkout (handled internally by portal)
// Users click "Buy" buttons which redirect to checkout

// View orders
const orders = await paycan.orders.list()

// Get downloads
const { downloads } = await paycan.orders.getDownloads('order-123')

// Get licenses
const { licenses } = await paycan.orders.getLicenses('order-123')

// Manage subscriptions
const subscriptions = await paycan.subscriptions.list()
await paycan.subscriptions.cancel('sub-123')
await paycan.subscriptions.resume('sub-123')
```

## Portal Routes

All portal routes require signed URL authentication:

### Products
- `GET /portal` - Product listing
- User can browse all active products with their prices

### Checkout
- `GET /portal/checkout/{price}` - Checkout page
- `POST /portal/checkout/{price}` - Process checkout
- Supports shipping address for physical products
- Payment gateway selection (Stripe/PayPal)

### Orders
- `GET /portal/orders` - Orders listing
- `GET /portal/orders/{order}/downloads` - Get download links
- `GET /portal/orders/{order}/licenses` - Get license keys
- View purchase history, download files, copy licenses

### Subscriptions
- `GET /portal/subscriptions` - Subscriptions listing
- `POST /portal/subscriptions/{subscription}/cancel` - Cancel subscription
- `POST /portal/subscriptions/{subscription}/resume` - Resume subscription
- `POST /portal/subscriptions/{subscription}/change` - Change plan
- `GET /portal/subscriptions/{subscription}/portal` - Gateway portal URL
- Manage active subscriptions, payment methods via gateway portal

## Configuration

### Environment Variables

```env
# Portal Configuration
PORTAL_ALLOWED_DOMAINS=*  # or comma-separated: https://example.com,https://app.example.com
PORTAL_LINK_EXPIRATION_HOURS=24
PORTAL_RATE_LIMIT=120
```

### Config File

Edit `config/portal.php`:

```php
return [
    'allowed_iframe_domains' => ['https://yourapp.com'],
    'link_expiration_hours' => 24,
    'rate_limit' => 120,
];
```

## Security Best Practices

### 1. Use Signed URLs
Always generate portal URLs server-side:

```php
// ✅ Good - Server-side generation
$url = PortalService::generatePortalUrl(auth()->id());

// ❌ Bad - Never expose user IDs in frontend
// window.location = `/portal?user=${userId}`;
```

### 2. Set URL Expiration
Keep expiration times reasonable:

```php
// Short-lived for sensitive operations
$checkoutUrl = PortalService::generateCheckoutUrl($userId, $priceId, 1); // 1 hour

// Longer for general access
$portalUrl = PortalService::generatePortalUrl($userId, 24); // 24 hours
```

### 3. Configure Allowed Domains
For production, restrict iframe embedding:

```php
// config/portal.php
'allowed_iframe_domains' => [
    'https://yourapp.com',
    'https://app.yourapp.com',
],
```

### 4. HTTPS Only
Always use HTTPS in production for secure communication.

### 5. Rate Limiting
Portal routes are rate-limited by default (120 requests/minute per user).

## Customization

### Styling
The portal uses Tailwind CSS v4 and shadcn-vue components. Customize:

1. **Tailwind Config**: Modify `tailwind.config.js`
2. **Components**: Edit files in `resources/js/components/Portal/`
3. **Layout**: Update `resources/js/Layouts/PortalLayout.vue`

### Adding Features

#### New Portal Page

1. Create controller:
```bash
php artisan make:controller Portal/MyController
```

2. Add route in `routes/portal.php`:
```php
Route::get('my-page', [MyController::class, 'index'])
    ->name('my-page');
```

3. Create Vue page:
```vue
<!-- resources/js/Pages/Portal/MyPage.vue -->
<script setup>
import PortalLayout from '@/Layouts/PortalLayout.vue'
</script>

<template>
  <PortalLayout>
    <!-- Your content -->
  </PortalLayout>
</template>
```

## Troubleshooting

### Portal Not Loading in Iframe

Check these common issues:

1. **X-Frame-Options**: Ensure `PORTAL_ALLOWED_DOMAINS` includes your domain
2. **HTTPS**: Both parent and iframe must use HTTPS in production
3. **CORS**: Verify CORS headers if making cross-origin requests
4. **Signature**: Ensure portal URL hasn't expired

### Authentication Errors

```
403: Invalid or expired portal link
```

- Portal URLs expire after configured hours (default: 24)
- Generate a fresh URL using `PortalService::generatePortalUrl()`

### Payment Failures

- Verify payment gateway credentials (Stripe/PayPal)
- Check webhook configuration for order status updates
- Review Laravel logs: `storage/logs/laravel.log`

## Example Integration

### Laravel Backend

```php
// routes/web.php
Route::get('/customer-portal', function () {
    $portalUrl = \App\Services\PortalService::generatePortalUrl(auth()->id());

    return view('portal-embed', [
        'portalUrl' => $portalUrl
    ]);
})->middleware('auth');
```

### Frontend (Blade)

```blade
<!-- resources/views/portal-embed.blade.php -->
<div class="container mx-auto py-8">
    <h1>Your Payment Portal</h1>
    <div class="mt-4">
        <iframe
            src="{{ $portalUrl }}"
            width="100%"
            height="800"
            frameborder="0"
            class="rounded-lg border shadow-lg"
        ></iframe>
    </div>
</div>
```

### Frontend (React/Vue)

```javascript
// In your app
const PortalEmbed = () => {
  const [portalUrl, setPortalUrl] = useState('')

  useEffect(() => {
    // Fetch portal URL from your backend
    fetch('/api/get-portal-url')
      .then(res => res.json())
      .then(data => setPortalUrl(data.url))
  }, [])

  return (
    <iframe
      src={portalUrl}
      width="100%"
      height="800"
      frameBorder="0"
      style={{ border: '1px solid #e5e7eb', borderRadius: '8px' }}
    />
  )
}
```

## Support

For issues, questions, or feature requests:
- Check the documentation
- Review existing issues
- Open a new issue with detailed information

## License

MIT License - See LICENSE file for details
