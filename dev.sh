#!/bin/bash

# ==================================================
# Fynla Development Server Startup Script
# ==================================================
# This script starts both Laravel and Vite dev servers
# with correct local environment variables exported.
#
# Usage: ./dev.sh
#
# Last Updated: February 25, 2026
# ==================================================

# Colors for output
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo -e "${BLUE}===================================================${NC}"
echo -e "${BLUE}  Fynla Development Environment${NC}"
echo -e "${BLUE}===================================================${NC}"
echo ""

# Check if .env file exists
if [ ! -f .env ]; then
    echo -e "${RED}ERROR: .env file not found!${NC}"
    echo "Please create .env file from .env.example"
    echo "Run: cp .env.example .env"
    exit 1
fi

# Kill any existing servers
echo -e "${YELLOW}Checking for existing server processes...${NC}"
php_pids=$(pgrep -f "php artisan serve")
node_pids=$(pgrep -f "vite")

if [ ! -z "$php_pids" ]; then
    echo -e "${YELLOW}Killing existing PHP servers (PIDs: $php_pids)${NC}"
    kill -9 $php_pids 2>/dev/null
fi

if [ ! -z "$node_pids" ]; then
    echo -e "${YELLOW}Killing existing Vite servers (PIDs: $node_pids)${NC}"
    kill -9 $node_pids 2>/dev/null
fi

sleep 1

# Export local development environment variables
echo -e "${GREEN}Setting local development environment variables...${NC}"
export APP_ENV=local
export DB_CONNECTION=mysql
export DB_HOST=localhost
export DB_PORT=3306
export DB_DATABASE=laravel
export DB_USERNAME=root
export DB_PASSWORD=""
export CACHE_DRIVER=array
export APP_URL=http://localhost:8000
export VITE_API_BASE_URL=http://localhost:8000

# Verify environment
echo ""
echo -e "${BLUE}Environment Configuration:${NC}"
echo "  APP_ENV:           $APP_ENV"
echo "  APP_URL:           $APP_URL"
echo "  VITE_API_BASE_URL: $VITE_API_BASE_URL"
echo "  DB_DATABASE:       $DB_DATABASE"
echo "  DB_USERNAME:       $DB_USERNAME"
echo "  CACHE_DRIVER:      $CACHE_DRIVER"
echo ""

# Check if MySQL is running
echo -e "${YELLOW}Checking MySQL connection...${NC}"
if ! mysql -u root -e "SELECT 1;" > /dev/null 2>&1; then
    echo -e "${RED}ERROR: Cannot connect to MySQL!${NC}"
    echo "Please ensure MySQL is running:"
    echo "  brew services start mysql"
    exit 1
fi

# Check if database exists
if ! mysql -u root -e "USE $DB_DATABASE;" > /dev/null 2>&1; then
    echo -e "${RED}WARNING: Database '$DB_DATABASE' does not exist!${NC}"
    echo "Creating database..."
    mysql -u root -e "CREATE DATABASE IF NOT EXISTS $DB_DATABASE;"
    echo -e "${GREEN}Database created successfully${NC}"
fi

# Clear caches
echo -e "${YELLOW}Clearing Laravel caches...${NC}"
php artisan config:clear > /dev/null 2>&1
php artisan cache:clear > /dev/null 2>&1

# Clear Vite cache
if [ -d "node_modules/.vite" ]; then
    echo -e "${YELLOW}Clearing Vite cache...${NC}"
    rm -rf node_modules/.vite
fi

echo ""
echo -e "${GREEN}Starting servers...${NC}"
echo ""

# Start Laravel server in background with increased upload limits
echo -e "${BLUE}Starting Laravel backend on http://127.0.0.1:8000${NC}"
php -d upload_max_filesize=100M -d post_max_size=110M -d memory_limit=512M -d max_execution_time=300 -d max_input_time=300 artisan serve > /dev/null 2>&1 &
LARAVEL_PID=$!

# Wait for Laravel to start
sleep 2

# Check if Laravel started successfully
if ! ps -p $LARAVEL_PID > /dev/null; then
    echo -e "${RED}ERROR: Laravel server failed to start!${NC}"
    echo "Check logs: tail -50 storage/logs/laravel.log"
    exit 1
fi

# Start Vite server in background
echo -e "${BLUE}Starting Vite frontend on http://127.0.0.1:5173${NC}"
npm run dev > /tmp/vite-output.log 2>&1 &
VITE_PID=$!

# Wait for Vite to compile
echo -e "${YELLOW}Waiting for Vite to compile...${NC}"
sleep 3

# Check if Vite started successfully
if ! ps -p $VITE_PID > /dev/null; then
    echo -e "${RED}ERROR: Vite server failed to start!${NC}"
    echo "Check logs: cat /tmp/vite-output.log"
    exit 1
fi

# Check Vite output for correct URL
if grep -q "APP_URL:" /tmp/vite-output.log 2>/dev/null; then
    echo -e "${GREEN}✓ Vite started successfully${NC}"
else
    echo -e "${YELLOW}⚠ Vite may still be compiling${NC}"
fi

echo ""
echo -e "${GREEN}===================================================${NC}"
echo -e "${GREEN}  Servers Started Successfully!${NC}"
echo -e "${GREEN}===================================================${NC}"
echo ""
echo -e "${BLUE}Frontend:${NC}  http://localhost:8000"
echo -e "${BLUE}API:${NC}       http://localhost:8000/api"
echo -e "${BLUE}Vite HMR:${NC}  http://127.0.0.1:5173"
echo ""
echo -e "${YELLOW}Process IDs:${NC}"
echo "  Laravel: $LARAVEL_PID"
echo "  Vite:    $VITE_PID"
echo ""
echo -e "${YELLOW}To stop servers:${NC}"
echo "  kill $LARAVEL_PID $VITE_PID"
echo "  or: pkill -9 php && pkill -9 node"
echo ""
echo -e "${BLUE}Demo Login Credentials:${NC}"
echo "  Email:    demo@fps.com"
echo "  Password: password"
echo ""
echo -e "${YELLOW}Logs:${NC}"
echo "  Laravel: tail -f storage/logs/laravel.log"
echo "  Vite:    tail -f /tmp/vite-output.log"
echo ""
echo -e "${GREEN}Press Ctrl+C to stop watching (servers will continue running)${NC}"
echo ""

# Follow Laravel logs
tail -f storage/logs/laravel.log 2>/dev/null
