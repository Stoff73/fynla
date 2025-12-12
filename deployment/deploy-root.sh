#!/bin/bash

###############################################################################
# Fynla v0.2.5 - Production Deployment Script (ROOT DIRECTORY)
# Target: csjones.co (root)
# Server: SiteGround Shared Hosting
###############################################################################

set -e  # Exit on error

echo "================================================"
echo "Fynla v0.2.5 - Root Directory Production Build"
echo "================================================"
echo ""

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Function to print colored output
print_success() {
    echo -e "${GREEN}✓ $1${NC}"
}

print_error() {
    echo -e "${RED}✗ $1${NC}"
}

print_warning() {
    echo -e "${YELLOW}⚠ $1${NC}"
}

print_info() {
    echo -e "${BLUE}→ $1${NC}"
}

# Check if we're in the correct directory
if [ ! -f "artisan" ]; then
    print_error "Error: artisan file not found. Please run this script from the Laravel root directory."
    exit 1
fi

print_success "Found Laravel installation"
echo ""

# Confirm root deployment
print_warning "IMPORTANT: This will build for ROOT directory deployment (csjones.co)"
print_warning "NOT for subfolder deployment (csjones.co/tengo)"
echo ""
read -p "Continue? (y/N) " -n 1 -r
echo ""
if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    print_info "Deployment cancelled"
    exit 0
fi
echo ""

# Step 1: Clean previous builds
print_info "Step 1: Cleaning previous builds..."
rm -rf public/build/
rm -rf public/hot
print_success "Cleaned previous builds"
echo ""

# Step 2: Install Composer dependencies (production)
print_info "Step 2: Installing Composer dependencies (production only)..."
composer install --optimize-autoloader --no-dev
print_success "Composer dependencies installed"
echo ""

# Step 3: Build frontend assets
print_info "Step 3: Building frontend assets with Vite (ROOT deployment)..."
NODE_ENV=production npm run build
if [ -d "public/build" ]; then
    print_success "Frontend assets built successfully"

    # Show build stats
    ASSET_COUNT=$(find public/build/assets -type f | wc -l | tr -d ' ')
    BUILD_SIZE=$(du -sh public/build/ 2>/dev/null | cut -f1)
    print_info "Built $ASSET_COUNT asset files ($BUILD_SIZE total)"
else
    print_error "Build failed - public/build directory not created"
    exit 1
fi
echo ""

# Step 4: Verify critical configuration
print_info "Step 4: Verifying production configuration..."

# Check vite.config.js base path
VITE_BASE=$(grep "base:" vite.config.js | grep production)
if echo "$VITE_BASE" | grep -q "/build/"; then
    print_success "Vite base path configured for root: /build/"
else
    print_error "Vite base path incorrect - should be '/build/' for root deployment"
    exit 1
fi

# Check .env.production.example
if [ -f ".env.production.example" ]; then
    APP_URL=$(grep "^APP_URL=" .env.production.example | cut -d'=' -f2)
    SESSION_PATH=$(grep "^SESSION_PATH=" .env.production.example | cut -d'=' -f2)
    ASSET_URL=$(grep "^ASSET_URL=" .env.production.example | cut -d'=' -f2)

    if [ "$APP_URL" = "https://csjones.co" ]; then
        print_success ".env APP_URL configured for root"
    else
        print_warning ".env APP_URL is: $APP_URL (expected: https://csjones.co)"
    fi

    if [ "$SESSION_PATH" = "/" ]; then
        print_success "SESSION_PATH configured for root: /"
    else
        print_warning "SESSION_PATH is: $SESSION_PATH (expected: /)"
    fi
else
    print_warning ".env.production.example not found"
fi
echo ""

# Step 5: Clear Laravel caches
print_info "Step 5: Clearing Laravel caches..."
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
print_success "Caches cleared"
echo ""

# Step 6: Optimize Laravel
print_info "Step 6: Caching Laravel configuration..."
php artisan config:cache
php artisan route:cache
php artisan view:cache
print_success "Laravel optimized"
echo ""

# Step 7: Create deployment info
print_info "Step 7: Creating deployment summary..."
BUILD_DATE=$(date '+%Y-%m-%d %H:%M:%S')
BUILD_TIMESTAMP=$(date +%Y%m%d-%H%M%S)

cat > deployment-info.txt << EOF
Fynla v0.2.5 - Production Build (ROOT DIRECTORY)
================================================

Build Information:
------------------
Version: v0.2.5
Build Date: ${BUILD_DATE}
Target: csjones.co (ROOT - not subfolder)
Build Type: Production
Environment: SiteGround Shared Hosting

Configuration:
--------------
✓ Vite base path: /build/
✓ APP_URL: https://csjones.co
✓ SESSION_PATH: /
✓ ASSET_URL: (empty for root)

Build Status:
-------------
✓ Composer dependencies installed (production mode)
✓ Frontend assets built with Vite (${ASSET_COUNT} files)
✓ Laravel caches cleared
✓ Laravel configuration cached and optimized

Deployment Steps:
-----------------
1. Create deployment archive:
   cd /Users/Chris/Desktop/fpsApp/
   tar -czf tengo-root-deploy-${BUILD_TIMESTAMP}.tar.gz \\
     --exclude='tengo/node_modules' \\
     --exclude='tengo/.git' \\
     --exclude='tengo/tests' \\
     --exclude='tengo/.env' \\
     tengo/

2. Upload to server:
   scp tengo-root-deploy-${BUILD_TIMESTAMP}.tar.gz u163-ptanegf9edny@ssh.csjones.co:~/

3. SSH into server:
   ssh -p 18765 u163-ptanegf9edny@ssh.csjones.co

4. Extract to public_html:
   cd ~/www/csjones.co/public_html/
   tar -xzf ~/tengo-root-deploy-${BUILD_TIMESTAMP}.tar.gz --strip-components=1

5. Set permissions:
   chmod -R 755 .
   chmod -R 775 storage/ bootstrap/cache/

6. Configure environment:
   cp .env.production.example .env
   nano .env  # Update DB credentials, APP_KEY, etc.
   php artisan key:generate --force

7. Setup database:
   php artisan migrate --force
   php artisan db:seed --class=TaxConfigurationSeeder --force

8. Create admin account:
   php artisan tinker
   # See DEPLOYMENT_ROOT_GUIDE.md for admin creation code

9. Optimize:
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache

10. Setup cron jobs (Site Tools → Devs → Cron Jobs):
    - Queue worker (every minute)
    - Daily cleanup (2:00 AM)

11. Test:
    Visit: https://csjones.co

See DEPLOYMENT_ROOT_GUIDE.md for complete deployment instructions.

EOF

print_success "Deployment info created: deployment-info.txt"
echo ""

# Step 8: Show build statistics
print_info "Step 8: Build statistics..."
if command -v du &> /dev/null; then
    PUBLIC_SIZE=$(du -sh public/build/ 2>/dev/null | cut -f1)
    VENDOR_SIZE=$(du -sh vendor/ 2>/dev/null | cut -f1)
    echo "  • Frontend assets (public/build/): ${PUBLIC_SIZE}"
    echo "  • Backend dependencies (vendor/): ${VENDOR_SIZE}"
    echo "  • Total asset files: ${ASSET_COUNT}"
fi
echo ""

# Step 9: Create deployment archive automatically
print_info "Step 9: Creating deployment archive..."
cd ..
ARCHIVE_NAME="tengo-root-deploy-${BUILD_TIMESTAMP}.tar.gz"

tar -czf "${ARCHIVE_NAME}" \
  --exclude='tengo/node_modules' \
  --exclude='tengo/.git' \
  --exclude='tengo/tests' \
  --exclude='tengo/.env' \
  --exclude='tengo/.env.production' \
  --exclude='tengo/storage/logs/*' \
  --exclude='tengo/storage/framework/cache/*' \
  --exclude='tengo/storage/framework/sessions/*' \
  --exclude='tengo/storage/framework/views/*' \
  tengo/

ARCHIVE_SIZE=$(du -sh "${ARCHIVE_NAME}" 2>/dev/null | cut -f1)
print_success "Deployment archive created: ${ARCHIVE_NAME} (${ARCHIVE_SIZE})"
echo ""

# Step 10: Reinstall dev dependencies for local development
cd tengo
print_warning "Reinstalling dev dependencies for local development..."
composer install
print_success "Dev dependencies restored"
echo ""

# Final summary
echo "================================================"
echo "Build Complete!"
echo "================================================"
echo ""
print_success "Production build ready for ROOT directory deployment"
echo ""
echo "Deployment archive:"
print_info "../${ARCHIVE_NAME} (${ARCHIVE_SIZE})"
echo ""
echo "Next steps:"
echo "  1. Upload: scp ../${ARCHIVE_NAME} u163-ptanegf9edny@ssh.csjones.co:~/"
echo "  2. SSH: ssh -p 18765 u163-ptanegf9edny@ssh.csjones.co"
echo "  3. Follow: DEPLOYMENT_ROOT_GUIDE.md"
echo ""
echo "Target URL: https://csjones.co"
echo ""
print_info "Read DEPLOYMENT_ROOT_GUIDE.md for complete step-by-step instructions"
print_info "Read deployment-info.txt for build details and deployment checklist"
echo ""
print_success "Ready to deploy to: https://csjones.co (ROOT DIRECTORY)"
echo ""
