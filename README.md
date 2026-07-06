# PayCan

PayCan is a self-hosted payment-integration platform. External applications sync their users with PayCan and use its unified APIs, JavaScript SDK, and embeddable web components to accept one-time and recurring payments through around 10 lines of code.

[![GitHub stars](https://img.shields.io/github/stars/paycan-app/paycan?style=social)](https://github.com/paycan-app/paycan)

> **[Star PayCan on GitHub](https://github.com/paycan-app/paycan).**  
> Stars are signals of support. It takes one click and makes a big difference.

## Features

- **Unified payments API** – one API for one-time purchases, subscriptions, digital downloads and more (around 10 lines of code for integration)
- **Multiple gateways** – Stripe and PayPal out of the box, (more is coming soon)
- **Web components** – framework-agnostic modal components (checkout, products, subscriptions, orders, transactions) that drop into any site
- **Self-service portal** – embeddable Vue portal secured by signed URLs
web-components-demo

## Roadmap

### Developer experience

| Item | Description |
|---|---|
| **MCP server** | Model Context Protocol integration so AI assistants and IDEs can manage products, orders, checkout, and settings through PayCan |
| **Agent skills** | Ready-made Cursor / Claude skills for common PayCan workflows — install, sync users, embed checkout, configure webhooks |

### Payment providers

These are on the roadmap next:

| Provider | Regions / use case |
|---|---|
| [Razorpay](https://razorpay.com/) | India and emerging markets |
| [Paddle](https://www.paddle.com/) | SaaS billing, tax, and merchant of record |
| [PayU](https://payu.in/) | India, LATAM, Europe, Africa |
| [YooKassa](https://yookassa.ru/) | Russia and CIS |
| [Cryptomus](https://cryptomus.com/) | Cryptocurrency payments |

Want one of these sooner? **[Star the repo](https://github.com/paycan-app/paycan)** and, if you can, [open an issue](https://github.com/paycan-app/paycan/issues) naming the provider or feature you need. Pull requests for roadmap items are always welcome — see [Development](#development) to get started.

## Tech Stack

Laravel 12 · PHP 8.4 · Vue 3 · Tailwind CSS 4 · shadcn-vue · Filament v4 · Pest v4

## Quick Start

1. **Install PayCan** – follow [INSTALLATION.md](INSTALLATION.md) (web installer at `/install` or `php artisan paycan:install`).
2. **Configure a payment gateway** – Admin Panel → Settings → Payment Providers, and set up webhooks (see [INSTALLATION.md](INSTALLATION.md#-post-installation-steps)).
3. **Get your API secret key** – Admin Panel → Settings → API Secret Key.
4. **Create products and prices** – in the admin panel or via the Admin API.
5. **Sync a user and integrate** – see below.

## Authentication

| Credential | Header | Used for |
|---|---|---|
| API secret key | `X-API-Key: <key>` | Admin API (server-to-server only — never expose it to browsers) |
| User token (JWT) | `Authorization: Bearer <token>` | User API and SDK, scoped to one user |

Your backend exchanges the API secret key for a user-scoped token:

```bash
curl -X POST https://pay.yourapp.com/api/admin/users/sync \
  -H "X-API-Key: your_api_secret_key" \
  -H "Content-Type: application/json" \
  -d '{
    "user_id": "external-user-123",
    "user": { "name": "John Doe", "email": "john@example.com" }
  }'

# → { "token": "eyJ...", "user": { ... } }
```

Pass that token to your frontend and the SDK; it only grants access to that user's own data. See [API_STRUCTURE.md](API_STRUCTURE.md) for the complete route reference (user, admin, public, and webhook endpoints).

## JavaScript SDK

The SDK lives in [sdk/front/](sdk/front/) 

```html
<script type="module">
  import { PayCan } from 'https://paycan.yourapp.com/sdk/paycan-sdk.js';

  const paycan = new PayCan({ apiUrl: 'https://pay.yourapp.com' });

  // Token fetched from YOUR backend (which calls /api/admin/users/sync)
  const { token } = await (await fetch('/api/paycan/token')).json();
  paycan.setUserToken(token);

  const orders = await paycan.orders.list();
  const active = await paycan.subscriptions.listActive();
</script>
```


## Web Components (UI Modals)

The SDK includes five framework-agnostic modal components. They render inside a Shadow DOM (no CSS conflicts with your page), are fully responsive, and support `light`, `dark`, and `auto` themes.

| Component | Purpose | Auth required |
|---|---|---|
| `CheckoutModal` | Full checkout flow: price selection, gateway selection, guest email | No (guest checkout supported) |
| `ProductsModal` | Browse products/plans; opens checkout on selection; can drive plan changes | No |
| `SubscriptionsModal` | List, cancel, and resume the user's subscriptions | Yes |
| `OrdersModal` | The user's order history with downloads and licenses | Yes |
| `TransactionsModal` | The user's payment history | Yes |

### Checkout and products modals (helper methods)

```javascript
// Checkout for a product (user picks a price)
paycan.openCheckoutModal(productId, {
  theme: 'dark',                 // 'light' (default) | 'dark' | 'auto'
  onSuccess: (checkoutUrl) => { window.location.href = checkoutUrl; },
  onCancel: () => {},
  onError: (error) => console.error(error),
});

// Checkout for a specific price
paycan.openCheckoutModalPrice(priceId, { theme: 'light' });

// Browse products; optionally filter by type
paycan.openProductsModal({
  type: 'subscription',          // 'physical' | 'digital' | 'service' | 'subscription'
  theme: 'auto',
  onProductSelected: (product) => console.log(product),
});
```

### Account modals (direct instantiation)

```javascript
import { PayCan, SubscriptionsModal, OrdersModal, TransactionsModal }
  from 'https://pay.yourapp.com/sdk/paycan-sdk.js';

const paycan = new PayCan({ apiUrl: 'https://pay.yourapp.com' });
paycan.setUserToken(token); // required for these modals

new SubscriptionsModal(paycan, { theme: 'auto', onClose: () => {} }).open();
new OrdersModal(paycan, { theme: 'light' }).open();
new TransactionsModal(paycan, { theme: 'dark', onError: (e) => console.error(e) }).open();
```

## Self-Service Portal

Alternatively, embed the complete hosted portal (products, checkout, orders, subscriptions) with one signed URL — no SDK code required:

```php
use App\Services\PortalService;

$portalUrl = PortalService::generatePortalUrl($userId, 24); // expires in 24h
```

```html
<iframe src="{{ $portalUrl }}" width="100%" height="800"></iframe>
```

See [PORTAL.md](PORTAL.md) and the demo at `/portal-demo`.

## Development

```bash
composer install && npm install
php artisan serve          # terminal 1
npm run dev                # terminal 2

npm run copy-sdk           # rebuild SDK and copy to public/sdk/
php artisan optimize:clear # clear caches after config/route changes
php artisan test           # run the Pest test suite
vendor/bin/pint --dirty    # code style
```

After any breaking change, update the APIs, OpenAPI docs, SDK, portal, tests, and docs in lock-step (see [CLAUDE.md](CLAUDE.md)).

## Security

- Keep the API secret key on your server only; browsers must only ever receive user-scoped tokens.
- User tokens are limited to the authenticated user's own data.
- All public endpoints are rate-limited; webhooks are signature-verified.
- Store secrets in `.env`, never in code or the database.

Found a vulnerability? Please report it privately rather than opening a public issue.
