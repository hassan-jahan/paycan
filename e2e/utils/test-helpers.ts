import { Page, expect } from '@playwright/test';

export interface TestUser {
  name: string;
  email: string;
  password: string;
}

export interface TestProduct {
  id: number;
  title: string;
  slug: string;
  type: 'physical' | 'digital' | 'service' | 'subscription';
  prices: TestProductPrice[];
}

export interface TestProductPrice {
  id: number;
  title: string;
  amount: string;
  currency: string;
  billing_period: 'once' | 'monthly' | 'yearly';
}

export class PaymentTestHelpers {
  constructor(private page: Page) {}

  /**
   * Create or ensure test user exists
   */
  async ensureTestUser(user: TestUser): Promise<void> {
    await this.page.goto('/');
    
    // Try to login first
    await this.page.goto('/login');
    await this.page.fill('[name="email"]', user.email);
    await this.page.fill('[name="password"]', user.password);
    await this.page.click('button[type="submit"]');
    
    // If login fails, register the user
    const currentUrl = this.page.url();
    if (currentUrl.includes('/login')) {
      await this.page.goto('/register');
      await this.page.fill('[name="name"]', user.name);
      await this.page.fill('[name="email"]', user.email);
      await this.page.fill('[name="password"]', user.password);
      await this.page.fill('[name="password_confirmation"]', user.password);
      await this.page.click('button[type="submit"]');
    }
    
    // Wait for successful authentication
    await this.page.waitForURL(/\/(dashboard|products)/);
  }

  /**
   * Navigate to products page and wait for it to load
   */
  async goToProductsPage(): Promise<void> {
    await this.page.goto('/products');
    await this.page.waitForSelector('[data-testid="products-grid"]', { timeout: 10000 });
  }

  /**
   * Select a product and open payment modal
   */
  async selectProduct(productTitle: string, priceTitle: string): Promise<void> {
    // Find the product card
    const productCard = this.page.locator('[data-testid="product-card"]', { 
      hasText: productTitle 
    });
    
    await expect(productCard).toBeVisible();
    
    // Find the price within the product card
    const priceButton = productCard.locator('[data-testid="price-button"]', {
      hasText: priceTitle
    });
    
    await expect(priceButton).toBeVisible();
    await priceButton.click();
    
    // Wait for payment modal to open
    await this.page.waitForSelector('[data-testid="payment-modal"]', { 
      state: 'visible',
      timeout: 5000 
    });
  }

  /**
   * Fill payment modal form
   */
  async fillPaymentForm(options: {
    gateway?: 'stripe' | 'paypal';
    quantity?: number;
    customerNote?: string;
  } = {}): Promise<void> {
    const {
      gateway = 'stripe',
      quantity = 1,
      customerNote = ''
    } = options;

    // Select payment gateway
    await this.page.click(`[data-testid="gateway-${gateway}"]`);
    
    // Set quantity if visible (for non-subscription products)
    const quantityInput = this.page.locator('[data-testid="quantity-input"]');
    if (await quantityInput.isVisible()) {
      await quantityInput.fill(quantity.toString());
    }
    
    // Add customer note if provided
    if (customerNote) {
      await this.page.fill('[data-testid="customer-note"]', customerNote);
    }
  }

  /**
   * Submit payment and wait for redirect
   */
  async submitPayment(): Promise<void> {
    await this.page.click('[data-testid="pay-button"]');
    
    // Wait for either redirect to payment gateway or error message
    await Promise.race([
      this.page.waitForURL(/stripe\.com|paypal\.com/, { timeout: 15000 }),
      this.page.waitForSelector('[data-testid="error-message"]', { timeout: 15000 })
    ]);
  }

  /**
   * Check for error messages
   */
  async expectError(errorText?: string): Promise<void> {
    const errorElement = this.page.locator('[data-testid="error-message"]');
    await expect(errorElement).toBeVisible();
    
    if (errorText) {
      await expect(errorElement).toContainText(errorText);
    }
  }

  /**
   * Check for success redirect to payment gateway
   */
  async expectPaymentGatewayRedirect(gateway: 'stripe' | 'paypal'): Promise<void> {
    const expectedDomain = gateway === 'stripe' ? 'stripe.com' : 'paypal.com';
    
    await this.page.waitForURL(new RegExp(expectedDomain), { timeout: 15000 });
    expect(this.page.url()).toContain(expectedDomain);
  }

  /**
   * Mock successful payment return
   */
  async mockSuccessfulPayment(): Promise<void> {
    await this.page.goto('/products?success=1');
    
    // Wait for success notification
    await this.page.waitForSelector('[data-testid="success-notification"]', {
      state: 'visible',
      timeout: 5000
    });
  }

  /**
   * Mock cancelled payment return
   */
  async mockCancelledPayment(): Promise<void> {
    await this.page.goto('/products?cancelled=1');
    
    // Wait for cancelled notification
    await this.page.waitForSelector('[data-testid="cancelled-notification"]', {
      state: 'visible',
      timeout: 5000
    });
  }

  /**
   * Wait for and verify success notification
   */
  async expectSuccessNotification(): Promise<void> {
    const notification = this.page.locator('[data-testid="success-notification"]');
    await expect(notification).toBeVisible();
    await expect(notification).toContainText('Payment Successful');
  }

  /**
   * Wait for and verify cancelled notification
   */
  async expectCancelledNotification(): Promise<void> {
    const notification = this.page.locator('[data-testid="cancelled-notification"]');
    await expect(notification).toBeVisible();
    await expect(notification).toContainText('Payment Cancelled');
  }

  /**
   * Dismiss notifications
   */
  async dismissNotifications(): Promise<void> {
    // Click dismiss buttons if present
    const dismissButtons = this.page.locator('[data-testid="dismiss-notification"]');
    const count = await dismissButtons.count();
    
    for (let i = 0; i < count; i++) {
      await dismissButtons.nth(i).click();
    }
  }

  /**
   * Take screenshot with timestamp
   */
  async takeScreenshot(name: string): Promise<void> {
    const timestamp = new Date().toISOString().replace(/[:.]/g, '-');
    await this.page.screenshot({ 
      path: `e2e-screenshots/${name}-${timestamp}.png`,
      fullPage: true
    });
  }
}

/**
 * Test data for consistent testing
 */
export const TEST_DATA = {
  users: {
    testUser: {
      name: 'E2E Test User',
      email: 'test@example.com',
      password: 'password'
    } as TestUser,
    
    newUser: {
      name: 'New Test User',
      email: 'newuser@example.com',
      password: 'password123'
    } as TestUser
  },
  
  products: {
    physical: {
      title: 'Premium Coffee Beans',
      type: 'physical' as const,
    },
    
    digital: {
      title: 'Digital Course Bundle',
      type: 'digital' as const,
    },
    
    subscription: {
      title: 'Monthly Software License',
      type: 'subscription' as const,
    }
  },
  
  payments: {
    stripeTestCard: '4242424242424242',
    paypalTestEmail: 'test@example.com'
  }
};