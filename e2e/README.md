# Frontend End-to-End Tests

This directory contains comprehensive end-to-end tests for the Laravel payment system frontend, built with Playwright.

## 🧪 Test Structure

### Test Files
- **`payment-flow.spec.ts`** - Complete payment flow testing including modal interactions, gateway selection, and error handling
- **`product-types.spec.ts`** - Product type specific tests (physical, digital, service, subscription)

### Utilities
- **`utils/test-helpers.ts`** - Reusable test helpers and data definitions
- **`setup/test-data.ts`** - Test data management and seeding utilities
- **`test-runner.ts`** - Custom test runner with pre-flight checks

## 🚀 Running Tests

### Quick Start
```bash
# Install dependencies and browsers
npm install
npx playwright install

# Ensure Laravel app is running
php artisan serve

# Run all tests
npm run e2e

# Run with browser UI visible
npm run e2e:headed

# Run with Playwright UI for debugging
npm run e2e:ui
```

### Test Commands
```bash
# Development
npm run e2e              # Run all tests headless
npm run e2e:headed       # Run with browser visible
npm run e2e:ui          # Run with Playwright UI
npm run e2e:debug       # Run in debug mode

# Reporting
npm run e2e:report      # View test report

# Custom runner
tsx e2e/test-runner.ts --suite payment --browser chromium --headed
```

## 📋 Test Coverage

### Payment Flow Tests
- ✅ Product selection and modal opening
- ✅ Payment form validation (quantity, notes)
- ✅ Gateway selection (Stripe/PayPal)
- ✅ Payment processing and redirects
- ✅ Success/cancel notifications
- ✅ URL parameter handling
- ✅ Responsive design (mobile/tablet)
- ✅ Error scenarios and network issues

### Product Type Tests
- ✅ Physical products with quantity and shipping
- ✅ Digital products with instant delivery
- ✅ Service bookings with consultation notes
- ✅ Subscriptions with trial periods
- ✅ Mixed product type interactions

### Integration Tests
- ✅ Authentication flows
- ✅ API token management
- ✅ Dynamic price creation
- ✅ Gateway error handling
- ✅ Form state persistence

## 🔧 Configuration

### Environment Setup
The tests expect a running Laravel application with:
- Default URL: `http://localhost:8000`
- Test user: `test@example.com` / `password`
- Seeded products with various types and pricing

### Test Data Requirements
```bash
# Ensure database is seeded with test products
php artisan db:seed

# Or create specific test products
php artisan tinker --execute="/* see setup/test-data.ts for seeder code */"
```

### Browser Configuration
Tests run on multiple browsers by default:
- Chromium (primary)
- Firefox
- WebKit (Safari)
- Mobile Chrome
- Mobile Safari

## 📊 Test Scenarios

### Core Payment Flow
1. Navigate to products page
2. Select product and pricing option
3. Fill payment modal form
4. Select payment gateway
5. Submit payment
6. Verify redirect to gateway
7. Test return flow with notifications

### Error Handling
- Invalid authentication
- Network connectivity issues
- Gateway configuration errors
- Form validation failures
- Payment processing errors

### Responsive Design
- Mobile viewports (375x667)
- Tablet viewports (768x1024)
- Desktop viewports (1920x1080)

## 🎯 Test Helpers

### PaymentTestHelpers Class
```typescript
const helpers = new PaymentTestHelpers(page);

// User management
await helpers.ensureTestUser(TEST_DATA.users.testUser);

// Navigation
await helpers.goToProductsPage();

// Product selection
await helpers.selectProduct('Product Title', 'Price Title');

// Form interactions
await helpers.fillPaymentForm({
  gateway: 'stripe',
  quantity: 2,
  customerNote: 'Special instructions'
});

// Payment processing
await helpers.submitPayment();
await helpers.expectPaymentGatewayRedirect('stripe');

// Notifications
await helpers.mockSuccessfulPayment();
await helpers.expectSuccessNotification();
```

### Test Data
```typescript
// Predefined test users
TEST_DATA.users.testUser        // test@example.com
TEST_DATA.users.newUser         // newuser@example.com

// Expected products
TEST_DATA.products.physical     // Physical products
TEST_DATA.products.digital      // Digital downloads
TEST_DATA.products.subscription // Recurring services
```

## 🚨 Troubleshooting

### Common Issues

**Tests fail to find elements**
- Ensure frontend is built: `npm run build`
- Check that test IDs are present in components
- Verify Laravel app is running on expected port

**Payment gateway errors**
- Expected in test environment without real gateway credentials
- Tests should handle gracefully and verify error messages

**Database issues**
- Ensure migrations are run: `php artisan migrate`
- Seed test data: `php artisan db:seed`
- Check database connection in `.env`

**Browser installation**
- Install browsers: `npx playwright install`
- For system dependencies: `npx playwright install-deps`

### Debug Mode
```bash
# Run single test in debug mode
npx playwright test payment-flow.spec.ts --debug

# Use console logs in tests
console.log('Debug info:', await page.textContent('selector'));

# Take screenshots manually
await helpers.takeScreenshot('debug-screenshot');
```

## 📈 CI/CD Integration

### GitHub Actions
The project includes GitHub Actions workflow (`.github/workflows/e2e-tests.yml`) that:
- Sets up PHP and Node.js environments
- Configures MySQL database
- Installs dependencies and builds assets
- Runs Laravel server
- Executes all E2E tests
- Uploads test reports and screenshots

### Local CI Testing
```bash
# Simulate CI environment
docker-compose up -d mysql
php artisan migrate --env=testing
npm run build
npm run e2e
```

## 🔍 Writing New Tests

### Test Structure
```typescript
import { test, expect } from '@playwright/test';
import { PaymentTestHelpers, TEST_DATA } from './utils/test-helpers';

test.describe('Feature Name', () => {
  let helpers: PaymentTestHelpers;

  test.beforeEach(async ({ page }) => {
    helpers = new PaymentTestHelpers(page);
    await helpers.ensureTestUser(TEST_DATA.users.testUser);
  });

  test('should do something specific', async ({ page }) => {
    // Test implementation
    await helpers.goToProductsPage();
    // ... test steps
    await expect(page.locator('[data-testid="element"]')).toBeVisible();
  });
});
```

### Adding Test IDs
When adding new components, include test IDs:
```vue
<template>
  <div data-testid="component-name">
    <button data-testid="action-button">Click Me</button>
  </div>
</template>
```

### Best Practices
- Use descriptive test names
- Include setup and teardown
- Test both success and error scenarios
- Add screenshots for visual validation
- Group related tests in describe blocks
- Use helper functions for common operations

## 📚 Resources

- [Playwright Documentation](https://playwright.dev/docs/intro)
- [Vue.js Testing Guide](https://vuejs.org/guide/scaling-up/testing.html)
- [Laravel Testing Documentation](https://laravel.com/docs/testing)
- [Inertia.js Testing](https://inertiajs.com/testing)

---

*E2E tests ensure the complete payment system works correctly from the user's perspective, providing confidence in production deployments.*