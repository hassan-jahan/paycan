#!/bin/bash

# Laravel E2E Test Runner
# Comprehensive script to set up and run end-to-end tests

set -e

echo "🚀 Laravel E2E Test Suite"
echo "=========================="

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
APP_URL=${APP_URL:-http://localhost:8000}
TEST_SUITE=${1:-all}
BROWSER=${2:-chromium}
MODE=${3:-headless}

echo -e "${BLUE}📋 Configuration:${NC}"
echo "   App URL: $APP_URL"
echo "   Test Suite: $TEST_SUITE"
echo "   Browser: $BROWSER"
echo "   Mode: $MODE"
echo

# Function to check if a service is running
check_service() {
    local url=$1
    local name=$2
    echo -n "   Checking $name... "
    if curl -s -f "$url" > /dev/null; then
        echo -e "${GREEN}✅ Running${NC}"
        return 0
    else
        echo -e "${RED}❌ Not accessible${NC}"
        return 1
    fi
}

# Pre-flight checks
echo -e "${BLUE}🔍 Pre-flight Checks${NC}"

# Check Laravel application
if ! check_service "$APP_URL/up" "Laravel app"; then
    echo -e "${YELLOW}⚠️  Starting Laravel server...${NC}"
    php artisan serve --host=0.0.0.0 --port=8000 &
    LARAVEL_PID=$!
    
    # Wait for Laravel to start
    echo "   Waiting for Laravel to start..."
    sleep 3
    
    if ! check_service "$APP_URL/up" "Laravel app (retry)"; then
        echo -e "${RED}❌ Failed to start Laravel server${NC}"
        exit 1
    fi
fi

# Check database connection
echo -n "   Checking database... "
if php artisan tinker --execute="DB::connection()->getPdo(); echo 'OK';" 2>/dev/null | grep -q "OK"; then
    echo -e "${GREEN}✅ Connected${NC}"
else
    echo -e "${RED}❌ Database connection failed${NC}"
    exit 1
fi

# Check if frontend is built
echo -n "   Checking frontend assets... "
if [ -f "public/build/manifest.json" ]; then
    echo -e "${GREEN}✅ Built${NC}"
else
    echo -e "${YELLOW}⚠️  Building frontend...${NC}"
    npm run build
    echo -e "${GREEN}✅ Built${NC}"
fi

# Check test data
echo -n "   Checking test data... "
PRODUCT_COUNT=$(php artisan tinker --execute="echo App\Models\Product::count();" 2>/dev/null | tail -1)
if [ "$PRODUCT_COUNT" -gt 0 ]; then
    echo -e "${GREEN}✅ $PRODUCT_COUNT products found${NC}"
else
    echo -e "${YELLOW}⚠️  Seeding database...${NC}"
    php artisan db:seed
    echo -e "${GREEN}✅ Database seeded${NC}"
fi

# Check Playwright installation
echo -n "   Checking Playwright... "
if npx playwright --version > /dev/null 2>&1; then
    echo -e "${GREEN}✅ Installed${NC}"
else
    echo -e "${YELLOW}⚠️  Installing Playwright...${NC}"
    npm install @playwright/test
    npx playwright install
    echo -e "${GREEN}✅ Installed${NC}"
fi

echo

# Build test command
TEST_CMD="npx playwright test"

# Add test suite filter
case $TEST_SUITE in
    "payment")
        TEST_CMD="$TEST_CMD e2e/payment-flow.spec.ts"
        echo -e "${BLUE}🧪 Running Payment Flow Tests${NC}"
        ;;
    "products")
        TEST_CMD="$TEST_CMD e2e/product-types.spec.ts"
        echo -e "${BLUE}🧪 Running Product Type Tests${NC}"
        ;;
    "all"|*)
        echo -e "${BLUE}🧪 Running All E2E Tests${NC}"
        ;;
esac

# Add browser selection
if [ "$BROWSER" != "all" ]; then
    TEST_CMD="$TEST_CMD --project=$BROWSER"
fi

# Add mode options
case $MODE in
    "headed"|"head")
        TEST_CMD="$TEST_CMD --headed"
        echo "   Mode: Browser visible"
        ;;
    "debug")
        TEST_CMD="$TEST_CMD --debug"
        echo "   Mode: Debug mode"
        ;;
    "ui")
        TEST_CMD="$TEST_CMD --ui"
        echo "   Mode: Playwright UI"
        ;;
    *)
        echo "   Mode: Headless"
        ;;
esac

echo "   Command: $TEST_CMD"
echo

# Set environment variables
export APP_URL="$APP_URL"

# Run tests
echo -e "${BLUE}🏃 Running Tests${NC}"
echo "================================="

if eval $TEST_CMD; then
    echo
    echo -e "${GREEN}✅ All tests passed successfully!${NC}"
    
    # Show report if available
    if [ -d "playwright-report" ]; then
        echo
        echo -e "${BLUE}📊 Test Report Available${NC}"
        echo "   Run: npm run e2e:report"
        echo "   Or:  npx playwright show-report"
    fi
    
    EXIT_CODE=0
else
    echo
    echo -e "${RED}❌ Some tests failed${NC}"
    
    # Show artifacts info
    echo -e "${YELLOW}🔍 Debug Information:${NC}"
    
    if [ -d "test-results" ]; then
        echo "   Screenshots: test-results/"
    fi
    
    if [ -d "playwright-report" ]; then
        echo "   Report: npm run e2e:report"
    fi
    
    echo
    echo -e "${YELLOW}💡 Debugging Tips:${NC}"
    echo "   • Run with --headed to see browser: ./run-e2e-tests.sh $TEST_SUITE $BROWSER headed"
    echo "   • Run with --debug for step-by-step: ./run-e2e-tests.sh $TEST_SUITE $BROWSER debug"
    echo "   • Use Playwright UI: ./run-e2e-tests.sh $TEST_SUITE $BROWSER ui"
    
    EXIT_CODE=1
fi

# Cleanup
if [ -n "$LARAVEL_PID" ]; then
    echo
    echo -e "${BLUE}🧹 Cleaning up Laravel server...${NC}"
    kill $LARAVEL_PID 2>/dev/null || true
fi

echo
echo -e "${BLUE}📋 Test Summary Complete${NC}"

exit $EXIT_CODE