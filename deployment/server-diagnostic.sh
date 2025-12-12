#!/bin/bash
################################################################################
# Fynla Server Diagnostic Script
#
# Run this on the SiteGround server to diagnose deployment issues
#
# Usage:
#   chmod +x server-diagnostic.sh
#   ./server-diagnostic.sh
################################################################################

echo "========================================"
echo "  Fynla v0.2.7 - Server Diagnostics"
echo "========================================"
echo ""

TENGO_DIR=~/www/csjones.co/public_html/tengo
LOG_FILE=~/tengo-diagnostic-$(date +%Y%m%d-%H%M%S).log

# Function to check and report
check_item() {
    local name="$1"
    local command="$2"

    echo -n "Checking $name... "
    if eval "$command" > /dev/null 2>&1; then
        echo "✓ OK"
        echo "✓ $name: OK" >> "$LOG_FILE"
    else
        echo "✗ FAILED"
        echo "✗ $name: FAILED" >> "$LOG_FILE"
    fi
}

# Start logging
echo "Fynla Diagnostic Report - $(date)" > "$LOG_FILE"
echo "======================================" >> "$LOG_FILE"
echo ""

# 1. Directory Structure
echo "1. DIRECTORY STRUCTURE"
echo "======================"

if [ -d "$TENGO_DIR" ]; then
    echo "✓ Fynla directory exists: $TENGO_DIR"
    cd "$TENGO_DIR"

    # Check critical directories
    for dir in app bootstrap config database public resources routes storage vendor; do
        if [ -d "$dir" ]; then
            echo "  ✓ $dir/"
        else
            echo "  ✗ $dir/ MISSING!"
        fi
    done
else
    echo "✗ Fynla directory NOT FOUND: $TENGO_DIR"
    echo "   Please check the deployment path."
    exit 1
fi

echo ""

# 2. File Permissions
echo "2. FILE PERMISSIONS"
echo "==================="

cd "$TENGO_DIR"

# Check storage permissions
if [ -d "storage" ]; then
    storage_perm=$(stat -f "%Lp" storage 2>/dev/null || stat -c "%a" storage 2>/dev/null)
    if [ "$storage_perm" = "775" ] || [ "$storage_perm" = "777" ]; then
        echo "✓ storage/ permissions: $storage_perm"
    else
        echo "✗ storage/ permissions: $storage_perm (should be 775 or 777)"
        echo "  Fix with: chmod -R 775 storage/"
    fi
fi

# Check bootstrap/cache permissions
if [ -d "bootstrap/cache" ]; then
    cache_perm=$(stat -f "%Lp" bootstrap/cache 2>/dev/null || stat -c "%a" bootstrap/cache 2>/dev/null)
    if [ "$cache_perm" = "775" ] || [ "$cache_perm" = "777" ]; then
        echo "✓ bootstrap/cache/ permissions: $cache_perm"
    else
        echo "✗ bootstrap/cache/ permissions: $cache_perm (should be 775 or 777)"
        echo "  Fix with: chmod -R 775 bootstrap/cache/"
    fi
fi

echo ""

# 3. Critical Files
echo "3. CRITICAL FILES"
echo "================="

cd "$TENGO_DIR"

# Check for critical files
files=(".env" "artisan" "composer.json" "public/index.php" "public/.htaccess" ".htaccess")

for file in "${files[@]}"; do
    if [ -f "$file" ]; then
        echo "✓ $file exists"
    else
        echo "✗ $file MISSING!"
    fi
done

echo ""

# 4. Hot Files Check (CRITICAL)
echo "4. HOT FILES CHECK (Critical for Production)"
echo "============================================="

cd "$TENGO_DIR"

hot_files_found=0

for hot_file in "hot" "public/hot" "public/build/hot"; do
    if [ -f "$hot_file" ]; then
        echo "✗ PROBLEM: $hot_file exists (causes dev server redirect)"
        echo "  Fix with: rm -f $hot_file"
        hot_files_found=$((hot_files_found + 1))
    fi
done

if [ $hot_files_found -eq 0 ]; then
    echo "✓ No hot files found (correct for production)"
fi

echo ""

# 5. Vite Build Assets
echo "5. VITE BUILD ASSETS"
echo "===================="

cd "$TENGO_DIR"

if [ -d "public/build" ]; then
    echo "✓ public/build/ directory exists"

    # Check for manifest.json
    if [ -f "public/build/manifest.json" ]; then
        echo "✓ public/build/manifest.json exists"

        # Check if it's a symlink
        if [ -L "public/build/manifest.json" ]; then
            target=$(readlink public/build/manifest.json)
            echo "  → Symlink pointing to: $target"
        fi
    else
        echo "✗ public/build/manifest.json MISSING!"

        # Check if .vite/manifest.json exists
        if [ -f "public/build/.vite/manifest.json" ]; then
            echo "  ✓ public/build/.vite/manifest.json exists"
            echo "  Fix with: cd public/build && ln -sf .vite/manifest.json manifest.json"
        else
            echo "  ✗ public/build/.vite/manifest.json also MISSING!"
            echo "  You need to upload the build files."
        fi
    fi

    # Check assets directory
    if [ -d "public/build/assets" ]; then
        asset_count=$(ls -1 public/build/assets/ | wc -l)
        echo "✓ public/build/assets/ exists with $asset_count files"
    else
        echo "✗ public/build/assets/ MISSING!"
    fi
else
    echo "✗ public/build/ directory MISSING!"
fi

echo ""

# 6. .htaccess Configuration
echo "6. .HTACCESS CONFIGURATION"
echo "=========================="

cd "$TENGO_DIR"

# Check root .htaccess
if [ -f ".htaccess" ]; then
    echo "✓ Root .htaccess exists"

    # Check for correct redirect rule
    if grep -q "RewriteRule.*public/\$1.*\[L,QSA\]" .htaccess; then
        echo "✓ Root .htaccess has correct redirect rule"
    else
        echo "✗ Root .htaccess may have incorrect redirect rule"
        echo "  Should contain: RewriteRule ^(.*)$ public/\$1 [L,QSA]"
    fi
else
    echo "✗ Root .htaccess MISSING!"
    echo "  This file is critical for routing requests to public/"
fi

# Check public/.htaccess
if [ -f "public/.htaccess" ]; then
    echo "✓ public/.htaccess exists"
else
    echo "✗ public/.htaccess MISSING!"
fi

echo ""

# 7. Environment Configuration
echo "7. ENVIRONMENT CONFIGURATION"
echo "============================"

cd "$TENGO_DIR"

if [ -f ".env" ]; then
    echo "✓ .env file exists"

    # Check critical env vars (without exposing values)
    for var in APP_KEY APP_URL DB_DATABASE DB_USERNAME SESSION_PATH CACHE_DRIVER; do
        if grep -q "^${var}=" .env; then
            value=$(grep "^${var}=" .env | cut -d'=' -f2- | head -c 20)
            if [ -n "$value" ]; then
                echo "✓ $var is set"
            else
                echo "✗ $var is empty"
            fi
        else
            echo "✗ $var not found in .env"
        fi
    done
else
    echo "✗ .env file MISSING!"
    echo "  Copy from .env.example and configure"
fi

echo ""

# 8. PHP Version and Extensions
echo "8. PHP ENVIRONMENT"
echo "=================="

php_version=$(php -v | head -n1)
echo "PHP Version: $php_version"

# Check required extensions
required_extensions="pdo pdo_mysql mbstring tokenizer xml ctype json bcmath"

for ext in $required_extensions; do
    if php -m | grep -qi "^$ext$"; then
        echo "✓ $ext extension loaded"
    else
        echo "✗ $ext extension MISSING!"
    fi
done

echo ""

# 9. Database Connection
echo "9. DATABASE CONNECTION"
echo "======================"

cd "$TENGO_DIR"

if php artisan db:show > /dev/null 2>&1; then
    echo "✓ Database connection successful"
    php artisan db:show 2>/dev/null | head -10
else
    echo "✗ Database connection FAILED"
    echo "  Check DB credentials in .env file"
fi

echo ""

# 10. Laravel Routes
echo "10. LARAVEL ROUTES"
echo "=================="

cd "$TENGO_DIR"

route_count=$(php artisan route:list 2>/dev/null | grep -c "POST\|GET" || echo "0")

if [ "$route_count" -gt 0 ]; then
    echo "✓ Laravel routes loaded: $route_count routes found"
    echo ""
    echo "Sample routes:"
    php artisan route:list 2>/dev/null | grep -E "api/auth|dashboard" | head -5
else
    echo "✗ No routes found - application may not be configured correctly"
fi

echo ""

# 11. Storage Directories
echo "11. STORAGE DIRECTORIES"
echo "======================="

cd "$TENGO_DIR"

storage_dirs=(
    "storage/framework/sessions"
    "storage/framework/cache/data"
    "storage/framework/views"
    "storage/logs"
    "storage/app/backups"
)

missing_dirs=0

for dir in "${storage_dirs[@]}"; do
    if [ -d "$dir" ]; then
        echo "✓ $dir/"
    else
        echo "✗ $dir/ MISSING"
        missing_dirs=$((missing_dirs + 1))
    fi
done

if [ $missing_dirs -gt 0 ]; then
    echo ""
    echo "Fix missing directories with:"
    echo "  mkdir -p storage/framework/{sessions,cache/data,views}"
    echo "  mkdir -p storage/logs storage/app/backups"
    echo "  chmod -R 775 storage/"
fi

echo ""

# 12. Recent Laravel Logs
echo "12. RECENT ERRORS (Last 20 lines)"
echo "=================================="

cd "$TENGO_DIR"

if [ -f "storage/logs/laravel.log" ]; then
    echo "Latest log entries:"
    tail -20 storage/logs/laravel.log
else
    echo "No log file found yet (application may not have run)"
fi

echo ""
echo "========================================"
echo "  Diagnostic Complete"
echo "========================================"
echo ""
echo "Full log saved to: $LOG_FILE"
echo ""
echo "NEXT STEPS:"
echo "1. Fix any ✗ issues found above"
echo "2. Clear all caches: php artisan cache:clear && php artisan config:clear"
echo "3. Test the application: curl -I https://csjones.co/tengo"
echo "4. Check browser console for JavaScript errors"
echo ""
