# E2E Testing Documentation

## Overview

Comprehensive End-to-End testing suite for the Laravel 12 + Vue 3 payment system covering all product types and user scenarios.

## Test Structure

### 🏗️ **Base Test Setup**
- **File**: `tests/Feature/BaseApiTest.php`
- **Features**:
  - Mock payment gateways (Stripe, PayPal)
  - Authentication helpers
  - Database setup and teardown
  - Common assertions
  - Security testing utilities

### 🔄 **Subscription Flow Tests**
- **File**: `tests/Feature/SubscriptionFlowTest.php`
- **Scenarios**:
  - Complete lifecycle: register → subscribe → cancel → resume
  - Different payment gateways (Stripe, PayPal)
  - Plan changes and upgrades
  - Trial period handling
  - Error cases and edge scenarios

### 📦 **Physical Product Tests** 
- **File**: `tests/Feature/PhysicalProductFlowTest.php`
- **Scenarios**:
  - Complete purchase flow with shipping
  - Order fulfillment and tracking
  - Multiple quantities and bulk orders
  - Shipping address validation
  - Failed payment handling

### 💾 **Digital Product Tests**
- **File**: `tests/Feature/DigitalProductFlowTest.php`
- **Scenarios**:
  - Instant digital delivery
  - License key generation
  - Download link creation
  - Software licenses vs eBooks
  - Access control and expiration
  - Multi-format content delivery

### 🛠️ **Service Product Tests**
- **File**: `tests/Feature/ServiceProductFlowTest.php`
- **Scenarios**:
  - Consultation booking
  - Appointment-based services
  - Training and workshop registration
  - Multi-session packages
  - Service expiration and validity
  - Team assignment services

### ⚠️ **Validation & Error Tests**
- **File**: `tests/Feature/ApiValidationAndErrorTest.php`
- **Scenarios**:
  - Input validation and sanitization
  - Authentication and authorization
  - Rate limiting and security
  - SQL injection prevention
  - XSS protection
  - Payment gateway error handling
  - Concurrent operations

## 🏭 **Model Factories**

### Product Factory
```php
Product::factory()->physical()->create();
Product::factory()->digital()->create();
Product::factory()->service()->create();
Product::factory()->subscription()->create();
```

### ProductPrice Factory
```php
ProductPrice::factory()->oneTime()->create();
ProductPrice::factory()->monthly()->create();
ProductPrice::factory()->yearly()->create();
ProductPrice::factory()->withTrial(14)->create();
```

### Order Factory
```php
Order::factory()->pending()->create();
Order::factory()->completed()->create();
Order::factory()->forStripe()->create();
Order::factory()->forPaypal()->create();
```

### Subscription Factory
```php
Subscription::factory()->active()->create();
Subscription::factory()->trialing()->create();
Subscription::factory()->canceled()->create();
```

## 🚀 **Running Tests**

### Using Test Runner
```bash
# Run all tests
php tests/TestRunner.php all

# Run specific test group
php tests/TestRunner.php subscription
php tests/TestRunner.php physical
php tests/TestRunner.php digital
php tests/TestRunner.php service
php tests/TestRunner.php validation
```

### Using Artisan
```bash
# Run all E2E tests
php artisan test tests/Feature/

# Run specific test file
php artisan test tests/Feature/SubscriptionFlowTest.php

# Run with coverage
php artisan test tests/Feature/ --coverage
```

### Using Pest
```bash
# Run all tests
./vendor/bin/pest tests/Feature/

# Run specific group
./vendor/bin/pest tests/Feature/SubscriptionFlowTest.php
```

## 📋 **Test Scenarios Covered**

### **1. User Registration & Authentication**
- ✅ Valid registration
- ✅ Email validation
- ✅ Password confirmation
- ✅ Duplicate email handling
- ✅ Authentication token management

### **2. Product Browsing**
- ✅ List all products
- ✅ Filter by product type
- ✅ Search functionality
- ✅ Product details retrieval
- ✅ Active/inactive product handling

### **3. One-Time Purchases**
- ✅ Physical product checkout
- ✅ Digital product instant delivery
- ✅ Service booking confirmation
- ✅ Payment gateway integration
- ✅ Order fulfillment workflows

### **4. Subscription Management**
- ✅ Subscription creation
- ✅ Trial period management
- ✅ Subscription cancellation
- ✅ Subscription resumption
- ✅ Plan changes and upgrades
- ✅ Billing cycle management

### **5. Payment Processing**
- ✅ Stripe integration
- ✅ PayPal integration
- ✅ Payment method validation
- ✅ Failed payment handling
- ✅ Refund processing
- ✅ Webhook verification

### **6. Order & Fulfillment**
- ✅ Order status tracking
- ✅ Physical item shipping
- ✅ Digital delivery automation
- ✅ Service activation
- ✅ Fulfillment notifications
- ✅ Customer communication

### **7. Security & Validation**
- ✅ Input sanitization
- ✅ SQL injection prevention
- ✅ XSS protection
- ✅ Authentication requirements
- ✅ Authorization checks
- ✅ Rate limiting
- ✅ Webhook security

### **8. Error Handling**
- ✅ Graceful error responses
- ✅ Validation error messages
- ✅ Payment gateway errors
- ✅ Network failure handling
- ✅ Concurrent operation conflicts
- ✅ Edge case management

## 🎯 **Success Criteria**

Each test verifies:
- ✅ **Functionality**: Feature works as intended
- ✅ **Data Integrity**: Database state is consistent
- ✅ **Security**: Proper authorization and validation
- ✅ **User Experience**: Appropriate responses and notifications
- ✅ **Business Logic**: Payment flows and fulfillment rules
- ✅ **Error Handling**: Graceful failure scenarios

## 🔧 **Mock Services**

Payment gateways are mocked to:
- ✅ Avoid real API calls during testing
- ✅ Test both success and failure scenarios
- ✅ Ensure consistent test results
- ✅ Speed up test execution
- ✅ Test edge cases safely

## 📊 **Expected Test Results**

When all tests pass:
- **~50+ test cases** covering all scenarios
- **100% critical path coverage**
- **All payment flows validated**
- **Security measures verified**
- **Error handling confirmed**

## 🚨 **Troubleshooting**

### Common Issues:
1. **Database Connection**: Ensure test database is configured
2. **Mock Services**: Verify payment gateway mocks are working
3. **Authentication**: Check Sanctum configuration for API tests
4. **Migrations**: Ensure all migrations are applied
5. **Environment**: Confirm `.env.testing` configuration

### Debug Mode:
```bash
# Run with verbose output
php artisan test tests/Feature/ --verbose

# Run specific test with debugging
php artisan test tests/Feature/SubscriptionFlowTest.php::test_complete_subscription_lifecycle --stop-on-failure
```

This comprehensive E2E testing suite ensures your Laravel 12 + Vue 3 payment system is production-ready and handles all user scenarios correctly! 🎉