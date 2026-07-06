# PayCan Checkout Modal - Setup & Usage

## Overview

The PayCan SDK now includes a complete checkout modal web component that can be embedded in any web application. The modal is framework-agnostic and provides a full checkout experience with:

- ✨ Automatic price selection UI (radio/dropdown based on count)
- 💳 Dynamic payment gateway display from preview API
- 📧 Guest checkout with email field
- 🌓 Light and dark theme support
- 📱 Fully responsive design
- 🔒 Secure with input sanitization

## Quick Start

### 1. Install the SDK Package

The SDK is already installed in this project from the local build:

```json
"@paycan/sdk": "file:sdk/front/paycan-sdk-1.0.0.tgz"
```

### 2. Copy SDK to Public Directory

After making changes to the SDK, run:

```bash
npm run copy-sdk
```

This will:
1. Build the SDK from `sdk/front/`
2. Copy the built file to `public/sdk/paycan-sdk.js`

### 3. View the Demo

Visit the demo page:

```
http://localhost:8000/checkout-modal-demo
```

## SDK Usage

### Import the SDK

```javascript
import { PayCan } from '@paycan/sdk';

const paycan = new PayCan({
  apiUrl: 'https://pay.yourapp.com',
  debug: true
});
```

### Open Checkout Modal for Product

Use this when you want users to select from multiple price options:

```javascript
paycan.openCheckoutModal(123, {
  theme: 'light', // or 'dark'
  onSuccess: (checkoutUrl) => {
    // Redirect to checkout
    window.location.href = checkoutUrl;
  },
  onCancel: () => {
    console.log('User cancelled');
  },
  onError: (error) => {
    console.error('Checkout error:', error);
  }
});
```

### Open Checkout Modal for Specific Price

Use this when you know exactly which price the user wants:

```javascript
paycan.openCheckoutModalPrice(456, {
  theme: 'dark',
  onSuccess: (checkoutUrl) => {
    window.location.href = checkoutUrl;
  }
});
```

## Modal Features

### Price Selection
- **< 3 prices**: Displays as radio buttons with visual cards
- **≥ 3 prices**: Displays as a dropdown select
- **Live updates**: Automatically fetches new preview when price changes

### Payment Gateways
- Shows available gateways from the preview API
- Updates when different price is selected
- Visual gateway cards with descriptions
- Subscription support badges

### Guest Checkout
- Automatically detects if user is authenticated
- Shows email field for guest users
- Email validation before checkout

### Responsive Design
- Mobile-first approach
- Tablet optimized
- Desktop enhanced
- Smooth animations

### Theme Support
- `theme: 'light'` - Light mode (default)
- `theme: 'dark'` - Dark mode

## Development Workflow

### 1. Make changes to SDK

Edit files in `sdk/front/src/`:
- `components/checkout-modal.ts` - Main modal component
- `paycan.ts` - SDK main class
- `types.ts` - TypeScript types

### 2. Build and copy SDK

```bash
npm run copy-sdk
```

Or manually:

```bash
cd sdk/front
npm run build
cd ../..
cp sdk/front/dist/index.esm.js public/sdk/paycan-sdk.js
```

### 3. Test in demo page

Visit `/checkout-modal-demo` to test your changes.

## File Structure

```
sdk/front/
├── src/
│   ├── components/
│   │   └── checkout-modal.ts    # Checkout modal web component
│   ├── resources/
│   │   └── checkout.ts          # Checkout API methods
│   ├── paycan.ts                # Main SDK class
│   ├── types.ts                 # TypeScript types
│   └── index.ts                 # Entry point
├── dist/                        # Built files
│   └── index.esm.js            # ES module build
└── package.json

public/sdk/
└── paycan-sdk.js                # Copied SDK for demo

resources/views/
└── checkout-modal-demo.blade.php # Demo page

routes/web.php                    # Contains /checkout-modal-demo route

scripts/
└── copy-sdk.sh                   # Helper script to build & copy
```

## API Methods

### `openCheckoutModal(productId, options)`

Opens checkout modal for a product.

**Parameters:**
- `productId` (number) - The product ID
- `options` (object) - Optional configuration
  - `theme` ('light' | 'dark') - Modal theme
  - `onSuccess` (function) - Called when checkout is created
  - `onCancel` (function) - Called when user cancels
  - `onError` (function) - Called on error

**Returns:** `CheckoutModal` instance

### `openCheckoutModalPrice(priceId, options)`

Opens checkout modal for a specific price.

**Parameters:**
- `priceId` (number) - The price ID
- `options` (object) - Same as above

**Returns:** `CheckoutModal` instance

## Security

The modal implements several security measures:

- **Input Sanitization**: All user inputs are validated and sanitized
- **XSS Prevention**: HTML escaping for all user-provided content
- **Email Validation**: Regex validation for guest email addresses
- **No Eval**: No use of `eval()` or inline JavaScript
- **HTTPS Ready**: Works with HTTPS for secure transmission

## Testing

### Test Scenarios

1. **Product with 1 price** - Should auto-select
2. **Product with 2 prices** - Should show radio buttons
3. **Product with 5+ prices** - Should show dropdown
4. **Authenticated user** - Should not show email field
5. **Guest user** - Should show email field
6. **Price change** - Should update preview and gateways
7. **Light theme** - Should display light colors
8. **Dark theme** - Should display dark colors
9. **Mobile view** - Should be responsive
10. **Error handling** - Should display errors gracefully

## Troubleshooting

### Modal not opening
- Check browser console for errors
- Ensure SDK is loaded: `/sdk/paycan-sdk.js`
- Verify product/price IDs exist in database

### Preview API errors
- Check that products and prices are active
- Verify payment gateways are configured
- Check network tab for API response

### Styling issues
- Modal uses inline styles, no external CSS needed
- Check z-index (set to 999999)
- Ensure no conflicting styles

## Publishing to NPM

When ready to publish:

```bash
cd sdk/front
npm version patch  # or minor/major
npm publish
```

Then update the main project:

```bash
npm install @paycan/sdk@latest
```

## Support

For issues or questions:
- GitHub Issues: https://github.com/paycan/sdk/issues
- Documentation: https://paycan.com/docs
- Email: support@paycan.com
