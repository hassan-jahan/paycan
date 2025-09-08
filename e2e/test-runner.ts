#!/usr/bin/env node

/**
 * E2E Test Runner for Laravel Payment System
 * 
 * Usage:
 *   npm run e2e                    # Run all tests
 *   npm run e2e:ui                 # Run with UI mode
 *   npm run e2e:headed             # Run with browser visible
 *   npm run e2e:debug              # Run in debug mode
 * 
 * Or directly:
 *   tsx e2e/test-runner.ts --help
 */

import { execSync } from 'child_process';
import { existsSync } from 'fs';

interface TestOptions {
  suite?: string;
  browser?: 'chromium' | 'firefox' | 'webkit' | 'all';
  headed?: boolean;
  debug?: boolean;
  ui?: boolean;
  reporter?: 'list' | 'dot' | 'line' | 'github' | 'json' | 'html';
  workers?: number;
  retries?: number;
}

class E2ETestRunner {
  private baseUrl = process.env.APP_URL || 'http://localhost:8000';
  
  async run(options: TestOptions = {}) {
    console.log('🚀 Starting E2E Test Suite for Laravel Payment System');
    console.log(`📍 Base URL: ${this.baseUrl}`);
    
    // Pre-flight checks
    await this.performPreflightChecks();
    
    // Build Playwright command
    const command = this.buildPlaywrightCommand(options);
    
    console.log(`🧪 Running command: ${command}`);
    console.log('─'.repeat(60));
    
    try {
      execSync(command, { stdio: 'inherit' });
      console.log('─'.repeat(60));
      console.log('✅ All tests completed successfully!');
    } catch (error) {
      console.log('─'.repeat(60));
      console.log('❌ Some tests failed. Check the output above for details.');
      process.exit(1);
    }
  }
  
  private async performPreflightChecks() {
    console.log('🔍 Performing pre-flight checks...');
    
    // Check if Laravel app is running
    try {
      const response = await fetch(`${this.baseUrl}/up`);
      if (!response.ok) {
        throw new Error(`Laravel health check failed: ${response.status}`);
      }
      console.log('✅ Laravel application is running');
    } catch (error) {
      console.error('❌ Laravel application is not accessible');
      console.error('   Please ensure Laravel is running with: php artisan serve');
      process.exit(1);
    }
    
    // Check if frontend is built
    if (!existsSync('public/build/manifest.json')) {
      console.log('⚠️  Frontend assets not found. Building...');
      try {
        execSync('npm run build', { stdio: 'inherit' });
        console.log('✅ Frontend assets built successfully');
      } catch (error) {
        console.error('❌ Failed to build frontend assets');
        process.exit(1);
      }
    } else {
      console.log('✅ Frontend assets are available');
    }
    
    // Check if test data exists
    try {
      const response = await fetch(`${this.baseUrl}/api/payments/products`);
      if (response.ok) {
        const data = await response.json();
        if (data.products && data.products.length > 0) {
          console.log(`✅ Found ${data.products.length} test products`);
        } else {
          console.log('⚠️  No products found. You may need to seed the database.');
          console.log('   Run: php artisan db:seed');
        }
      }
    } catch (error) {
      console.log('⚠️  Could not verify test data availability');
    }
    
    // Check if test user exists
    console.log('✅ Test user will be created during test execution if needed');
    
    console.log('─'.repeat(40));
  }
  
  private buildPlaywrightCommand(options: TestOptions): string {
    let command = 'npx playwright test';
    
    // Add test suite filter
    if (options.suite) {
      switch (options.suite) {
        case 'payment':
          command += ' e2e/payment-flow.spec.ts';
          break;
        case 'products':
          command += ' e2e/product-types.spec.ts';
          break;
        case 'all':
        default:
          // Run all tests
          break;
      }
    }
    
    // Add browser selection
    if (options.browser && options.browser !== 'all') {
      command += ` --project=${options.browser}`;
    }
    
    // Add execution mode options
    if (options.headed) {
      command += ' --headed';
    }
    
    if (options.debug) {
      command += ' --debug';
    }
    
    if (options.ui) {
      command += ' --ui';
    }
    
    // Add reporter
    if (options.reporter) {
      command += ` --reporter=${options.reporter}`;
    }
    
    // Add worker configuration
    if (options.workers) {
      command += ` --workers=${options.workers}`;
    }
    
    // Add retry configuration
    if (options.retries) {
      command += ` --retries=${options.retries}`;
    }
    
    return command;
  }
}

// CLI execution
if (require.main === module) {
  const args = process.argv.slice(2);
  const options: TestOptions = {};
  
  // Parse command line arguments
  for (let i = 0; i < args.length; i++) {
    const arg = args[i];
    
    switch (arg) {
      case '--suite':
        options.suite = args[++i] as any;
        break;
      case '--browser':
        options.browser = args[++i] as any;
        break;
      case '--headed':
        options.headed = true;
        break;
      case '--debug':
        options.debug = true;
        break;
      case '--ui':
        options.ui = true;
        break;
      case '--reporter':
        options.reporter = args[++i] as any;
        break;
      case '--workers':
        options.workers = parseInt(args[++i]);
        break;
      case '--retries':
        options.retries = parseInt(args[++i]);
        break;
      case '--help':
      case '-h':
        console.log(`
Laravel E2E Test Runner

Usage: tsx e2e/test-runner.ts [options]

Options:
  --suite <name>      Run specific test suite (payment, products, all)
  --browser <name>    Run on specific browser (chromium, firefox, webkit, all)
  --headed           Run tests with browser UI visible
  --debug            Run tests in debug mode
  --ui               Run tests with Playwright UI
  --reporter <name>  Use specific reporter (list, dot, line, github, json, html)
  --workers <num>    Number of parallel workers
  --retries <num>    Number of retries for failed tests
  --help, -h         Show this help message

Examples:
  tsx e2e/test-runner.ts --suite payment --browser chromium --headed
  tsx e2e/test-runner.ts --debug
  tsx e2e/test-runner.ts --ui
        `);
        process.exit(0);
        break;
    }
  }
  
  const runner = new E2ETestRunner();
  runner.run(options);
}

export { E2ETestRunner };