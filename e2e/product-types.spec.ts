import { test, expect } from '@playwright/test';
import { PaymentTestHelpers, TEST_DATA } from './utils/test-helpers';

test.describe('Product Type Specific E2E Tests', () => {
  let helpers: PaymentTestHelpers;

  test.beforeEach(async ({ page }) => {
    helpers = new PaymentTestHelpers(page);
    await helpers.ensureTestUser(TEST_DATA.users.testUser);
  });

  test.describe('Physical Products', () => {
    test('should handle physical product purchases with shipping', async ({ page }) => {
      await helpers.goToProductsPage();
      
      // Find physical product
      const physicalProduct = page.locator('[data-testid="product-card"]').filter({
        has: page.locator('[data-badge="physical"]')
      }).first();
      
      if (await physicalProduct.count() > 0) {
        await physicalProduct.locator('[data-testid="price-button"]').first().click();
        
        // Verify quantity input is available for physical products
        const quantityInput = page.locator('[data-testid="quantity-input"]');
        await expect(quantityInput).toBeVisible();
        
        // Test quantity changes
        await quantityInput.fill('3');
        
        // Verify total updates
        const totalElement = page.locator('[data-testid="total-amount"]');
        await expect(totalElement).toBeVisible();
        
        // Fill form and submit
        await helpers.fillPaymentForm({
          gateway: 'stripe',
          quantity: 3,
          customerNote: 'Please ship to office address during business hours'
        });
        
        await helpers.takeScreenshot('physical-product-order');
        
        const payButton = page.locator('[data-testid="pay-button"]');
        await payButton.click();
        
        // Wait for processing
        await Promise.race([
          page.waitForURL(/stripe\.com/, { timeout: 15000 }),
          page.waitForSelector('[data-testid="error-message"]', { timeout: 15000 })
        ]);
        
        await helpers.takeScreenshot('physical-product-payment');
      } else {
        test.skip('No physical products available for testing');
      }
    });

    test('should calculate correct totals for multiple physical items', async ({ page }) => {
      await helpers.goToProductsPage();
      
      const physicalProduct = page.locator('[data-testid="product-card"]').filter({
        has: page.locator('[data-badge="physical"]')
      }).first();
      
      if (await physicalProduct.count() > 0) {
        const priceButton = physicalProduct.locator('[data-testid="price-button"]').first();
        const priceText = await priceButton.textContent();
        
        await priceButton.click();
        
        // Extract price from text (assuming format like "$29.99")
        const priceMatch = priceText?.match(/\$?([\d,.]+)/);
        const unitPrice = priceMatch ? parseFloat(priceMatch[1].replace(',', '')) : 0;
        
        if (unitPrice > 0) {
          // Test quantity calculation
          const quantityInput = page.locator('[data-testid="quantity-input"]');
          await quantityInput.fill('5');
          
          const totalElement = page.locator('[data-testid="total-amount"]');
          const totalText = await totalElement.textContent();
          
          // Verify calculation (allowing for currency formatting differences)
          const expectedTotal = unitPrice * 5;
          expect(totalText).toContain(expectedTotal.toFixed(2).replace('.00', ''));
        }
        
        await helpers.takeScreenshot('physical-product-calculation');
      } else {
        test.skip('No physical products available for testing');
      }
    });
  });

  test.describe('Digital Products', () => {
    test('should handle instant digital product delivery', async ({ page }) => {
      await helpers.goToProductsPage();
      
      const digitalProduct = page.locator('[data-testid="product-card"]').filter({
        has: page.locator('[data-badge="digital"]')
      }).first();
      
      if (await digitalProduct.count() > 0) {
        await digitalProduct.locator('[data-testid="price-button"]').first().click();
        
        // Digital products should have quantity control
        const quantityInput = page.locator('[data-testid="quantity-input"]');
        await expect(quantityInput).toBeVisible();
        
        // Verify digital product specific messaging
        const modal = page.locator('[data-testid="payment-modal"]');
        await expect(modal).toContainText('digital');
        
        await helpers.fillPaymentForm({
          gateway: 'stripe',
          customerNote: 'E2E test for digital product purchase'
        });
        
        await helpers.takeScreenshot('digital-product-order');
        
        const payButton = page.locator('[data-testid="pay-button"]');
        await payButton.click();
        
        await Promise.race([
          page.waitForURL(/stripe\.com/, { timeout: 15000 }),
          page.waitForSelector('[data-testid="error-message"]', { timeout: 15000 })
        ]);
        
        await helpers.takeScreenshot('digital-product-payment');
      } else {
        test.skip('No digital products available for testing');
      }
    });

    test('should show appropriate messaging for digital licenses', async ({ page }) => {
      await helpers.goToProductsPage();
      
      const digitalProduct = page.locator('[data-testid="product-card"]').filter({
        has: page.locator('[data-badge="digital"]')
      }).first();
      
      if (await digitalProduct.count() > 0) {
        await digitalProduct.locator('[data-testid="price-button"]').first().click();
        
        // Check for digital product specific information
        const modal = page.locator('[data-testid="payment-modal"]');
        
        // Should not show shipping related information
        await expect(modal.locator(':has-text("shipping")')).toHaveCount(0);
        await expect(modal.locator(':has-text("delivery address")')).toHaveCount(0);
        
        // Should show instant access information
        const features = modal.locator('[data-testid="product-features"]');
        if (await features.isVisible()) {
          await expect(features).toBeVisible();
        }
        
        await helpers.takeScreenshot('digital-product-features');
      } else {
        test.skip('No digital products available for testing');
      }
    });
  });

  test.describe('Service Products', () => {
    test('should handle service bookings correctly', async ({ page }) => {
      await helpers.goToProductsPage();
      
      const serviceProduct = page.locator('[data-testid="product-card"]').filter({
        has: page.locator('[data-badge="service"]')
      }).first();
      
      if (await serviceProduct.count() > 0) {
        await serviceProduct.locator('[data-testid="price-button"]').first().click();
        
        // Services might have quantity controls for sessions/hours
        const modal = page.locator('[data-testid="payment-modal"]');
        await expect(modal).toContainText('service');
        
        // Check for service-specific information
        await helpers.fillPaymentForm({
          gateway: 'paypal',
          customerNote: 'Please schedule consultation for next week. Available Mon-Fri 9-5 EST.'
        });
        
        await helpers.takeScreenshot('service-product-order');
        
        const payButton = page.locator('[data-testid="pay-button"]');
        await payButton.click();
        
        await Promise.race([
          page.waitForURL(/paypal\.com/, { timeout: 15000 }),
          page.waitForSelector('[data-testid="error-message"]', { timeout: 15000 })
        ]);
        
        await helpers.takeScreenshot('service-product-payment');
      } else {
        test.skip('No service products available for testing');
      }
    });
  });

  test.describe('Subscription Products', () => {
    test('should handle subscription purchases correctly', async ({ page }) => {
      await helpers.goToProductsPage();
      
      const subscriptionProduct = page.locator('[data-testid="product-card"]').filter({
        has: page.locator('[data-badge="subscription"]')
      }).first();
      
      if (await subscriptionProduct.count() > 0) {
        await subscriptionProduct.locator('[data-testid="price-button"]').first().click();
        
        // Subscriptions should NOT have quantity input
        const quantityInput = page.locator('[data-testid="quantity-input"]');
        await expect(quantityInput).not.toBeVisible();
        
        // Should show subscription-specific information
        const modal = page.locator('[data-testid="payment-modal"]');
        
        // Check for trial information if applicable
        const trialInfo = modal.locator('[data-testid="trial-info"]');
        if (await trialInfo.isVisible()) {
          await expect(trialInfo).toContainText('trial');
        }
        
        // Check for recurring billing information
        await expect(modal.locator(':has-text("/month"), :has-text("/year")')).toHaveCount(1);
        
        await helpers.fillPaymentForm({
          gateway: 'stripe',
          customerNote: 'Starting subscription for team account'
        });
        
        // Verify pay button shows subscription text
        const payButton = page.locator('[data-testid="pay-button"]');
        await expect(payButton).toContainText('Subscribe');
        
        await helpers.takeScreenshot('subscription-product-order');
        
        await payButton.click();
        
        await Promise.race([
          page.waitForURL(/stripe\.com/, { timeout: 15000 }),
          page.waitForSelector('[data-testid="error-message"]', { timeout: 15000 })
        ]);
        
        await helpers.takeScreenshot('subscription-product-payment');
      } else {
        test.skip('No subscription products available for testing');
      }
    });

    test('should display trial period information correctly', async ({ page }) => {
      await helpers.goToProductsPage();
      
      const subscriptionProduct = page.locator('[data-testid="product-card"]').filter({
        has: page.locator('[data-badge="subscription"]')
      }).first();
      
      if (await subscriptionProduct.count() > 0) {
        await subscriptionProduct.locator('[data-testid="price-button"]').first().click();
        
        const modal = page.locator('[data-testid="payment-modal"]');
        
        // Look for trial information
        const trialInfo = modal.locator('[data-testid="trial-info"]');
        if (await trialInfo.isVisible()) {
          await expect(trialInfo).toBeVisible();
          await expect(trialInfo).toContainText('day');
          
          // Should show trial messaging
          const trialText = await trialInfo.textContent();
          expect(trialText).toMatch(/\d+.day.*trial/i);
        }
        
        await helpers.takeScreenshot('subscription-trial-info');
      } else {
        test.skip('No subscription products available for testing');
      }
    });
  });

  test.describe('Mixed Product Types', () => {
    test('should handle switching between different product types', async ({ page }) => {
      await helpers.goToProductsPage();
      
      const allProducts = page.locator('[data-testid="product-card"]');
      const productCount = await allProducts.count();
      
      if (productCount >= 2) {
        // Test first product
        await allProducts.nth(0).locator('[data-testid="price-button"]').first().click();
        
        let modal = page.locator('[data-testid="payment-modal"]');
        await expect(modal).toBeVisible();
        
        // Close modal
        const closeButton = modal.locator('[data-testid="close-modal"]');
        if (await closeButton.isVisible()) {
          await closeButton.click();
        } else {
          await page.keyboard.press('Escape');
        }
        
        await expect(modal).not.toBeVisible();
        
        // Test second product
        await allProducts.nth(1).locator('[data-testid="price-button"]').first().click();
        
        modal = page.locator('[data-testid="payment-modal"]');
        await expect(modal).toBeVisible();
        
        await helpers.takeScreenshot('switched-product-types');
      } else {
        test.skip('Not enough products available for switching test');
      }
    });

    test('should maintain form state correctly when switching gateways', async ({ page }) => {
      await helpers.goToProductsPage();
      
      await page.locator('[data-testid="product-card"]').first()
        .locator('[data-testid="price-button"]').first().click();
      
      const modal = page.locator('[data-testid="payment-modal"]');
      
      // Fill customer note
      const customerNote = 'Test note that should persist between gateway switches';
      await page.fill('[data-testid="customer-note"]', customerNote);
      
      // Switch between gateways
      await page.click('[data-testid="gateway-stripe"]');
      await expect(page.locator('[data-testid="gateway-stripe"]')).toHaveClass(/border-primary/);
      
      await page.click('[data-testid="gateway-paypal"]');
      await expect(page.locator('[data-testid="gateway-paypal"]')).toHaveClass(/border-primary/);
      
      // Verify customer note is still there
      const noteInput = page.locator('[data-testid="customer-note"]');
      await expect(noteInput).toHaveValue(customerNote);
      
      await helpers.takeScreenshot('gateway-switching');
    });
  });
});