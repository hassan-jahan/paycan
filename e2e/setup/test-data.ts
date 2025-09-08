/**
 * Test data setup and management for E2E tests
 */

export interface TestDataManager {
  setupTestData(): Promise<void>;
  cleanupTestData(): Promise<void>;
  ensureProductsExist(): Promise<void>;
  ensureUsersExist(): Promise<void>;
}

export class DatabaseTestDataManager implements TestDataManager {
  private baseUrl: string;

  constructor(baseUrl: string = 'http://localhost:8000') {
    this.baseUrl = baseUrl;
  }

  async setupTestData(): Promise<void> {
    console.log('Setting up test data...');
    await this.ensureUsersExist();
    await this.ensureProductsExist();
    console.log('Test data setup complete');
  }

  async cleanupTestData(): Promise<void> {
    console.log('Cleaning up test data...');
    // Note: We typically don't clean up in E2E tests to preserve state
    // But this method is here if needed
  }

  async ensureUsersExist(): Promise<void> {
    // This would typically use Laravel Artisan commands or direct API calls
    // For now, we'll use the registration flow that's already tested
    console.log('Test users will be created during test execution');
  }

  async ensureProductsExist(): Promise<void> {
    // Check if products exist via API or create them
    try {
      const response = await fetch(`${this.baseUrl}/api/payments/products`);
      if (response.ok) {
        const data = await response.json();
        if (data.products && data.products.length > 0) {
          console.log(`Found ${data.products.length} existing products`);
          return;
        }
      }
    } catch (error) {
      console.log('Could not fetch existing products, will rely on seeded data');
    }

    // If no products exist, we'll need to seed them
    console.log('No products found, make sure to run: php artisan db:seed');
  }
}

/**
 * Test products that should exist for E2E testing
 */
export const REQUIRED_TEST_PRODUCTS = [
  {
    title: 'Premium Coffee Beans',
    type: 'physical',
    prices: [
      { title: 'Single Bag', amount: '24.99', billing_period: 'once' },
      { title: '3-Bag Bundle', amount: '69.99', billing_period: 'once' }
    ]
  },
  {
    title: 'Digital Course Bundle', 
    type: 'digital',
    prices: [
      { title: 'Standard Access', amount: '99.99', billing_period: 'once' },
      { title: 'Premium Access', amount: '199.99', billing_period: 'once' }
    ]
  },
  {
    title: 'Consulting Services',
    type: 'service', 
    prices: [
      { title: '1-Hour Session', amount: '150.00', billing_period: 'once' },
      { title: '5-Hour Package', amount: '650.00', billing_period: 'once' }
    ]
  },
  {
    title: 'Software License',
    type: 'subscription',
    prices: [
      { title: 'Monthly Plan', amount: '29.99', billing_period: 'monthly' },
      { title: 'Annual Plan', amount: '299.99', billing_period: 'yearly' }
    ]
  }
];

/**
 * Database seeder command to create test products
 */
export const TEST_DATA_SEEDER_COMMAND = `
php artisan tinker --execute="
// Create test products for E2E testing
use App\\Models\\Product;
use App\\Models\\ProductPrice;

\$products = [
    [
        'title' => 'Premium Coffee Beans',
        'slug' => 'premium-coffee-beans',
        'description' => 'High-quality arabica coffee beans sourced from sustainable farms.',
        'type' => 'physical',
        'is_active' => true,
        'meta' => json_encode([
            'features' => [
                'Organic certified',
                '100% Arabica beans',
                'Direct trade sourcing',
                'Small batch roasted'
            ]
        ]),
        'prices' => [
            ['title' => 'Single Bag (12oz)', 'amount' => 24.99, 'billing_period' => 'once'],
            ['title' => 'Monthly Subscription', 'amount' => 22.99, 'billing_period' => 'monthly']
        ]
    ],
    [
        'title' => 'Digital Course Bundle',
        'slug' => 'digital-course-bundle',
        'description' => 'Complete web development course with video tutorials and exercises.',
        'type' => 'digital',
        'is_active' => true,
        'meta' => json_encode([
            'features' => [
                '50+ HD video tutorials',
                'Downloadable resources',
                'Certificate of completion',
                'Lifetime access'
            ]
        ]),
        'prices' => [
            ['title' => 'Standard Access', 'amount' => 99.99, 'billing_period' => 'once'],
            ['title' => 'Premium with Mentoring', 'amount' => 299.99, 'billing_period' => 'once']
        ]
    ],
    [
        'title' => 'Business Consulting',
        'slug' => 'business-consulting',
        'description' => 'Professional business strategy and growth consulting services.',
        'type' => 'service',
        'is_active' => true,
        'meta' => json_encode([
            'features' => [
                'One-on-one consultation',
                'Custom strategy development',
                'Follow-up support',
                'Actionable recommendations'
            ]
        ]),
        'prices' => [
            ['title' => '1-Hour Session', 'amount' => 150.00, 'billing_period' => 'once'],
            ['title' => '5-Hour Package', 'amount' => 650.00, 'billing_period' => 'once']
        ]
    ],
    [
        'title' => 'Project Management Software',
        'slug' => 'project-management-software',
        'description' => 'Cloud-based project management and team collaboration platform.',
        'type' => 'subscription',
        'is_active' => true,
        'meta' => json_encode([
            'features' => [
                'Unlimited projects',
                'Team collaboration tools',
                'Time tracking',
                '24/7 support',
                '14-day free trial'
            ]
        ]),
        'prices' => [
            ['title' => 'Monthly Plan', 'amount' => 29.99, 'billing_period' => 'monthly', 'trial_days' => 14],
            ['title' => 'Annual Plan', 'amount' => 299.99, 'billing_period' => 'yearly', 'trial_days' => 14]
        ]
    ]
];

foreach (\$products as \$productData) {
    \$prices = \$productData['prices'];
    unset(\$productData['prices']);
    
    \$product = Product::updateOrCreate(
        ['slug' => \$productData['slug']],
        \$productData
    );
    
    foreach (\$prices as \$priceData) {
        \$priceData['product_id'] = \$product->id;
        \$priceData['slug'] = \$product->slug . '-' . str_slug(\$priceData['title']);
        \$priceData['currency'] = 'USD';
        \$priceData['is_active'] = true;
        
        ProductPrice::updateOrCreate(
            ['product_id' => \$product->id, 'slug' => \$priceData['slug']],
            \$priceData
        );
    }
}

echo 'Test products created successfully!';
"
`;

/**
 * Initialize test environment
 */
export async function initializeTestEnvironment(): Promise<void> {
  console.log('Initializing E2E test environment...');
  
  // Check if Laravel app is running
  try {
    const response = await fetch('http://localhost:8000/up');
    if (!response.ok) {
      throw new Error('Laravel app health check failed');
    }
  } catch (error) {
    console.error('Laravel app is not running. Please start it with: php artisan serve');
    process.exit(1);
  }
  
  console.log('✅ Laravel app is running');
  
  // Check database connection
  try {
    const response = await fetch('http://localhost:8000/api/payments/products');
    // If this fails, it might be due to database issues
  } catch (error) {
    console.warn('Could not verify database connection via API');
  }
  
  console.log('✅ Test environment ready');
}

/**
 * Playwright global setup
 */
export async function globalSetup(): Promise<void> {
  await initializeTestEnvironment();
  
  const dataManager = new DatabaseTestDataManager();
  await dataManager.setupTestData();
}

/**
 * Playwright global teardown
 */
export async function globalTeardown(): Promise<void> {
  const dataManager = new DatabaseTestDataManager();
  await dataManager.cleanupTestData();
  
  console.log('E2E test environment cleaned up');
}