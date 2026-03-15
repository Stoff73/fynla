# ==================================================
# Fynla Development Server Startup Script (Windows)
# ==================================================
# This script starts both Laravel and Vite dev servers
# with correct local environment variables set.
#
# Usage: .\dev.ps1
#
# Last Updated: March 15, 2026
# ==================================================

Write-Host "===================================================" -ForegroundColor Blue
Write-Host "  Fynla Development Environment (Windows)" -ForegroundColor Blue
Write-Host "===================================================" -ForegroundColor Blue
Write-Host ""

# Check if .env file exists
if (-not (Test-Path ".env")) {
    Write-Host "ERROR: .env file not found!" -ForegroundColor Red
    Write-Host "Please create .env file from .env.example"
    Write-Host "Run: Copy-Item .env.example .env"
    exit 1
}

# Kill any existing servers on ports 8000 and 5173
Write-Host "Checking for existing server processes..." -ForegroundColor Yellow

$port8000 = netstat -ano 2>$null | Select-String ":8000\s" | ForEach-Object {
    ($_ -split '\s+')[-1]
} | Where-Object { $_ -match '^\d+$' } | Sort-Object -Unique
if ($port8000) {
    foreach ($pid in $port8000) {
        Write-Host "Killing process on port 8000 (PID: $pid)" -ForegroundColor Yellow
        taskkill /PID $pid /F 2>$null | Out-Null
    }
}

$port5173 = netstat -ano 2>$null | Select-String ":5173\s" | ForEach-Object {
    ($_ -split '\s+')[-1]
} | Where-Object { $_ -match '^\d+$' } | Sort-Object -Unique
if ($port5173) {
    foreach ($pid in $port5173) {
        Write-Host "Killing process on port 5173 (PID: $pid)" -ForegroundColor Yellow
        taskkill /PID $pid /F 2>$null | Out-Null
    }
}

Start-Sleep -Seconds 1

# Set local development environment variables
Write-Host "Setting local development environment variables..." -ForegroundColor Green
$env:APP_ENV = "local"
$env:DB_CONNECTION = "mysql"
$env:DB_HOST = "localhost"
$env:DB_PORT = "3306"
$env:DB_DATABASE = "laravel"
$env:DB_USERNAME = "root"
$env:DB_PASSWORD = ""
$env:CACHE_DRIVER = "file"
$env:APP_URL = "http://localhost:8000"
$env:VITE_API_BASE_URL = "http://localhost:8000"

# Verify environment
Write-Host ""
Write-Host "Environment Configuration:" -ForegroundColor Blue
Write-Host "  APP_ENV:           $env:APP_ENV"
Write-Host "  APP_URL:           $env:APP_URL"
Write-Host "  VITE_API_BASE_URL: $env:VITE_API_BASE_URL"
Write-Host "  DB_DATABASE:       $env:DB_DATABASE"
Write-Host "  DB_USERNAME:       $env:DB_USERNAME"
Write-Host "  CACHE_DRIVER:      $env:CACHE_DRIVER"
Write-Host ""

# Check if MySQL is running
Write-Host "Checking MySQL connection..." -ForegroundColor Yellow
try {
    $result = mysql -u root -e "SELECT 1;" 2>&1
    if ($LASTEXITCODE -ne 0) { throw "MySQL connection failed" }
} catch {
    Write-Host "ERROR: Cannot connect to MySQL!" -ForegroundColor Red
    Write-Host "Please ensure MySQL is running:"
    Write-Host "  net start MySQL"
    Write-Host "  or start it from Services (services.msc)"
    exit 1
}

# Check if database exists
try {
    mysql -u root -e "USE $env:DB_DATABASE;" 2>&1 | Out-Null
    if ($LASTEXITCODE -ne 0) { throw "Database not found" }
} catch {
    Write-Host "WARNING: Database '$env:DB_DATABASE' does not exist!" -ForegroundColor Red
    Write-Host "Creating database..."
    mysql -u root -e "CREATE DATABASE IF NOT EXISTS $env:DB_DATABASE;"
    Write-Host "Database created successfully" -ForegroundColor Green
}

# Clear caches
Write-Host "Clearing Laravel caches..." -ForegroundColor Yellow
php artisan config:clear 2>$null | Out-Null
php artisan cache:clear 2>$null | Out-Null

# Clear Vite cache
if (Test-Path "node_modules\.vite") {
    Write-Host "Clearing Vite cache..." -ForegroundColor Yellow
    Remove-Item -Recurse -Force "node_modules\.vite"
}

Write-Host ""
Write-Host "Starting servers..." -ForegroundColor Green
Write-Host ""

# Start Laravel server in background with increased upload limits
Write-Host "Starting Laravel backend on http://127.0.0.1:8000" -ForegroundColor Blue
$laravelProcess = Start-Process -FilePath "php" -ArgumentList "-d upload_max_filesize=100M -d post_max_size=110M -d memory_limit=512M -d max_execution_time=300 -d max_input_time=300 artisan serve" -NoNewWindow -PassThru -RedirectStandardOutput "NUL" -RedirectStandardError "NUL"

# Wait for Laravel to start
Start-Sleep -Seconds 2

# Check if Laravel started successfully
if ($laravelProcess.HasExited) {
    Write-Host "ERROR: Laravel server failed to start!" -ForegroundColor Red
    Write-Host "Check logs: Get-Content storage\logs\laravel.log -Tail 50"
    exit 1
}

# Start Vite server in background
Write-Host "Starting Vite frontend on http://127.0.0.1:5173" -ForegroundColor Blue
$viteProcess = Start-Process -FilePath "npm" -ArgumentList "run dev" -NoNewWindow -PassThru -RedirectStandardOutput "$env:TEMP\vite-output.log" -RedirectStandardError "$env:TEMP\vite-error.log"

# Wait for Vite to compile
Write-Host "Waiting for Vite to compile..." -ForegroundColor Yellow
Start-Sleep -Seconds 3

# Check if Vite started successfully
if ($viteProcess.HasExited) {
    Write-Host "ERROR: Vite server failed to start!" -ForegroundColor Red
    Write-Host "Check logs: Get-Content $env:TEMP\vite-output.log"
    exit 1
}

# Check Vite output for correct URL
if (Test-Path "$env:TEMP\vite-output.log") {
    $viteLog = Get-Content "$env:TEMP\vite-output.log" -ErrorAction SilentlyContinue
    if ($viteLog -match "APP_URL:") {
        Write-Host "Vite started successfully" -ForegroundColor Green
    } else {
        Write-Host "Vite may still be compiling" -ForegroundColor Yellow
    }
}

Write-Host ""
Write-Host "===================================================" -ForegroundColor Green
Write-Host "  Servers Started Successfully!" -ForegroundColor Green
Write-Host "===================================================" -ForegroundColor Green
Write-Host ""
Write-Host "Frontend:  " -NoNewline -ForegroundColor Blue; Write-Host "http://localhost:8000"
Write-Host "API:       " -NoNewline -ForegroundColor Blue; Write-Host "http://localhost:8000/api"
Write-Host "Vite HMR:  " -NoNewline -ForegroundColor Blue; Write-Host "http://127.0.0.1:5173"
Write-Host ""
Write-Host "Process IDs:" -ForegroundColor Yellow
Write-Host "  Laravel: $($laravelProcess.Id)"
Write-Host "  Vite:    $($viteProcess.Id)"
Write-Host ""
Write-Host "To stop servers:" -ForegroundColor Yellow
Write-Host "  Stop-Process -Id $($laravelProcess.Id), $($viteProcess.Id)"
Write-Host "  or: Get-Process php, node | Stop-Process -Force"
Write-Host ""
Write-Host "Demo Login Credentials:" -ForegroundColor Blue
Write-Host "  Email:    demo@fps.com"
Write-Host "  Password: password"
Write-Host ""
Write-Host "Logs:" -ForegroundColor Yellow
Write-Host "  Laravel: Get-Content storage\logs\laravel.log -Tail 50 -Wait"
Write-Host "  Vite:    Get-Content $env:TEMP\vite-output.log -Tail 50 -Wait"
Write-Host ""
Write-Host "Press Ctrl+C to stop watching (servers will continue running)" -ForegroundColor Green
Write-Host ""

# Follow Laravel logs
Get-Content storage\logs\laravel.log -Wait -ErrorAction SilentlyContinue
