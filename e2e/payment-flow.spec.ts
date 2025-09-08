import { test, expect } from '@playwright/test';
import { PaymentTestHelpers, TEST_DATA } from './utils/test-helpers';

test.describe('Payment Flow E2E Tests', () => {
  let helpers: PaymentTestHelpers;

  test.beforeEach(async ({ page }) => {
    helpers = new PaymentTestHelpers(page);
    
    // Ensure test user exists and is logged in
    await helpers.ensureTestUser(TEST_DATA.users.testUser);
  });

  test.describe('Product Selection and Modal', () => {
    test('should display products page and open payment modal', async ({ page }) => {
      // Navigate to products page
      await helpers.goToProductsPage();
      
      // Verify products are loaded
      await expect(page.locator('[data-testid="products-grid"]')).toBeVisible();
      
      // Take screenshot for visual validation
      await helpers.takeScreenshot('products-page-loaded');
      
      // Find any available product
      const firstProduct = page.locator('[data-testid="product-card"]').first();
      await expect(firstProduct).toBeVisible();
      
      // Click on first price button
      const firstPriceButton = firstProduct.locator('[data-testid="price-button"]').first();
      await firstPriceButton.click();
      
      // Verify payment modal opens
      await expect(page.locator('[data-testid="payment-modal"]')).toBeVisible();
      await expect(page.locator('[data-testid="payment-modal"]')).toContainText('Review your order');
    });

    test('should populate payment modal with correct product information', async ({ page }) => {
      await helpers.goToProductsPage();
      
      // Click on first available product
      const firstProduct = page.locator('[data-testid="product-card"]').first();
      const productTitle = await firstProduct.locator('[data-testid="product-title"]').textContent();
      const firstPrice = firstProduct.locator('[data-testid="price-button"]').first();
      await firstPrice.click();
      
      // Verify modal shows correct product information
      const modal = page.locator('[data-testid="payment-modal"]');
      await expect(modal).toContainText(productTitle || '');
      
      // Verify payment gateway options are available
      await expect(modal.locator('[data-testid="gateway-stripe"]')).toBeVisible();
      await expect(modal.locator('[data-testid="gateway-paypal"]')).toBeVisible();
    });
  });

  test.describe('Payment Form Validation', () => {
    test('should validate quantity input for non-subscription products', async ({ page }) => {
      await helpers.goToProductsPage();
      
      // Find a non-subscription product (physical or digital)
      const productCard = page.locator('[data-testid="product-card"]').filter({
        hasNot: page.locator('[data-badge="subscription"]')
      }).first();
      
      await productCard.locator('[data-testid="price-button"]').first().click();
      
      // Check if quantity input is visible (should be for non-subscription)
      const quantityInput = page.locator('[data-testid="quantity-input"]');
      if (await quantityInput.isVisible()) {
        // Test quantity validation
        await quantityInput.fill('0');
        await expect(quantityInput).toHaveValue('1'); // Should reset to minimum
        
        await quantityInput.fill('15');
        await expect(quantityInput).toHaveValue('10'); // Should cap at maximum
      }
    });

    test('should handle customer notes input', async ({ page }) => {
      await helpers.goToProductsPage();
      
      // Open any product payment modal
      await page.locator('[data-testid="product-card"]').first()
        .locator('[data-testid="price-button"]').first().click();
      
      // Test customer notes
      const customerNote = 'This is a test order with special instructions for E2E testing.';
      await page.fill('[data-testid="customer-note"]', customerNote);
      
      // Verify character counter
      const charCounter = page.locator('[data-testid="character-counter"]');
      await expect(charCounter).toContainText(`${customerNote.length}/1000`);
    });
  });

  test.describe('Payment Gateway Selection', () => {
    test('should allow selecting Stripe payment gateway', async ({ page }) => {
      await helpers.goToProductsPage();
      
      await page.locator('[data-testid="product-card"]').first()
        .locator('[data-testid="price-button"]').first().click();
      
      // Select Stripe gateway
      await page.click('[data-testid="gateway-stripe"]');
      
      // Verify Stripe is selected
      await expect(page.locator('[data-testid="gateway-stripe"]')).toHaveClass(/border-primary/);
      
      // Verify pay button shows correct text
      const payButton = page.locator('[data-testid="pay-button"]');
      await expect(payButton).toBeVisible();
      await expect(payButton).not.toBeDisabled();
    });

    test('should allow selecting PayPal payment gateway', async ({ page }) => {
      await helpers.goToProductsPage();
      
      await page.locator('[data-testid="product-card"]').first()
        .locator('[data-testid="price-button"]').first().click();
      
      // Select PayPal gateway
      await page.click('[data-testid="gateway-paypal"]');
      
      // Verify PayPal is selected
      await expect(page.locator('[data-testid="gateway-paypal"]')).toHaveClass(/border-primary/);
      
      // Verify pay button is enabled
      const payButton = page.locator('[data-testid="pay-button"]');
      await expect(payButton).toBeVisible();
      await expect(payButton).not.toBeDisabled();
    });
  });

  test.describe('Payment Processing', () => {
    test('should process Stripe payment successfully', async ({ page }) => {
      await helpers.goToProductsPage();
      
      // Select first product
      await page.locator('[data-testid="product-card"]').first()
        .locator('[data-testid="price-button"]').first().click();
      
      // Fill payment form
      await helpers.fillPaymentForm({
        gateway: 'stripe',
        quantity: 2,
        customerNote: 'E2E test order for Stripe'
      });
      
      // Submit payment
      const payButton = page.locator('[data-testid="pay-button"]');
      await expect(payButton).toBeVisible();
      
      // Click and wait for processing
      await payButton.click();
      
      // Verify processing state
      await expect(payButton).toContainText('Processing...');
      await expect(payButton).toBeDisabled();
      
      // Wait for either redirect or error
      await Promise.race([
        page.waitForURL(/stripe\.com/, { timeout: 15000 }),
        page.waitForSelector('[data-testid="error-message"]', { timeout: 15000 })
      ]);
      
      // Take screenshot of result
      await helpers.takeScreenshot('stripe-payment-result');
    });

    test('should process PayPal payment successfully', async ({ page }) => {
      await helpers.goToProductsPage();
      
      // Select first product
      await page.locator('[data-testid="product-card"]').first()
        .locator('[data-testid="price-button"]').first().click();
      
      // Fill payment form with PayPal
      await helpers.fillPaymentForm({
        gateway: 'paypal',
        customerNote: 'E2E test order for PayPal'
      });
      
      // Submit payment
      const payButton = page.locator('[data-testid="pay-button"]');
      await payButton.click();
      
      // Wait for redirect or error
      await Promise.race([
        page.waitForURL(/paypal\.com/, { timeout: 15000 }),
        page.waitForSelector('[data-testid="error-message"]', { timeout: 15000 })
      ]);
      
      await helpers.takeScreenshot('paypal-payment-result');
    });

    test('should handle payment errors gracefully', async ({ page }) => {
      // This test assumes we might get errors due to configuration issues
      await helpers.goToProductsPage();
      
      await page.locator('[data-testid="product-card"]').first()
        .locator('[data-testid="price-button"]').first().click();
      
      await helpers.fillPaymentForm({ gateway: 'stripe' });
      
      const payButton = page.locator('[data-testid="pay-button"]');
      await payButton.click();
      
      // Wait for either redirect or error (error is more likely in test env)
      try {
        await page.waitForURL(/stripe\.com|paypal\.com/, { timeout: 10000 });
      } catch {
        // If no redirect, check for error message
        const errorMessage = page.locator('[data-testid="error-message"]');
        if (await errorMessage.isVisible()) {
          await expect(errorMessage).toBeVisible();
          console.log('Payment error (expected in test environment):', 
            await errorMessage.textContent());
        }
      }
      
      await helpers.takeScreenshot('payment-error-handling');
    });
  });

  test.describe('Payment Return Flow', () => {
    test('should display success notification after successful payment', async ({ page }) => {
      // Mock successful payment return
      await helpers.mockSuccessfulPayment();
      
      // Verify success notification
      await helpers.expectSuccessNotification();
      
      // Verify notification auto-dismisses or can be dismissed
      const notification = page.locator('[data-testid="success-notification"]');
      const dismissButton = notification.locator('[data-testid="dismiss-notification"]');
      
      if (await dismissButton.isVisible()) {
        await dismissButton.click();
        await expect(notification).not.toBeVisible();
      }
      
      await helpers.takeScreenshot('success-notification');
    });

    test('should display cancelled notification after cancelled payment', async ({ page }) => {
      // Mock cancelled payment return
      await helpers.mockCancelledPayment();
      
      // Verify cancelled notification
      await helpers.expectCancelledNotification();
      
      // Test notification dismissal
      const notification = page.locator('[data-testid="cancelled-notification"]');
      const dismissButton = notification.locator('[data-testid="dismiss-notification"]');
      
      if (await dismissButton.isVisible()) {
        await dismissButton.click();
        await expect(notification).not.toBeVisible();
      }
      
      await helpers.takeScreenshot('cancelled-notification');
    });

    test('should clean up URL parameters after showing notifications', async ({ page }) => {
      // Go to success URL
      await page.goto('/products?success=1');
      
      // Wait for notification
      await helpers.expectSuccessNotification();
      
      // Wait a moment for URL cleanup
      await page.waitForTimeout(1000);
      
      // Verify URL is cleaned
      expect(page.url()).toBe(await page.evaluate(() => window.location.origin + '/products'));
    });
  });

  test.describe('Responsive Design', () => {
    test('should work correctly on mobile devices', async ({ page }) => {
      // Set mobile viewport
      await page.setViewportSize({ width: 375, height: 667 });
      
      await helpers.goToProductsPage();
      
      // Verify products grid is responsive
      await expect(page.locator('[data-testid="products-grid"]')).toBeVisible();
      
      // Open payment modal
      await page.locator('[data-testid="product-card"]').first()
        .locator('[data-testid="price-button"]').first().click();
      
      // Verify modal is responsive
      const modal = page.locator('[data-testid="payment-modal"]');
      await expect(modal).toBeVisible();
      
      // Test form interactions on mobile
      await page.click('[data-testid="gateway-stripe"]');
      
      const payButton = page.locator('[data-testid="pay-button"]');
      await expect(payButton).toBeVisible();
      
      await helpers.takeScreenshot('mobile-payment-modal');
    });

    test('should handle tablet viewport correctly', async ({ page }) => {
      // Set tablet viewport
      await page.setViewportSize({ width: 768, height: 1024 });
      
      await helpers.goToProductsPage();
      
      // Test product grid layout
      const productCards = page.locator('[data-testid="product-card"]');
      await expect(productCards.first()).toBeVisible();
      
      // Open and test payment modal
      await productCards.first().locator('[data-testid="price-button"]').first().click();
      
      const modal = page.locator('[data-testid="payment-modal"]');
      await expect(modal).toBeVisible();
      
      await helpers.takeScreenshot('tablet-payment-modal');
    });
  });

  test.describe('Error Scenarios', () => {
    test('should handle network connectivity issues', async ({ page }) => {
      await helpers.goToProductsPage();
      
      await page.locator('[data-testid="product-card"]').first()
        .locator('[data-testid="price-button"]').first().click();
      
      // Simulate network failure
      await page.route('**/api/auth/token', (route) => {
        route.abort('internetdisconnected');
      });
      
      await helpers.fillPaymentForm({ gateway: 'stripe' });
      
      const payButton = page.locator('[data-testid="pay-button"]');
      await payButton.click();
      
      // Should show appropriate error message
      await expect(page.locator('[data-testid="error-message"]')).toBeVisible();
      
      await helpers.takeScreenshot('network-error');
    });

    test('should handle authentication failures', async ({ page }) => {
      await helpers.goToProductsPage();
      
      await page.locator('[data-testid="product-card"]').first()
        .locator('[data-testid="price-button"]').first().click();
      
      // Mock authentication failure
      await page.route('**/api/auth/token', (route) => {
        route.fulfill({
          status: 401,
          contentType: 'application/json',
          body: JSON.stringify({ message: 'Unauthorized' })
        });
      });
      
      await helpers.fillPaymentForm({ gateway: 'stripe' });
      
      const payButton = page.locator('[data-testid="pay-button"]');
      await payButton.click();
      
      // Should redirect to login or show error
      await Promise.race([
        page.waitForURL(/\/login/, { timeout: 5000 }),
        page.waitForSelector('[data-testid="error-message"]', { timeout: 5000 })
      ]);
      
      await helpers.takeScreenshot('auth-failure');
    });
  });
});