@echo off
REM ============================================================================
REM  start-fynla.bat — one-click local dev for Fynla.
REM
REM  Fixes the recurring "localhost:8000 not showing" problem:
REM   - removes a stale public\hot file (left over when Vite stops while Laravel
REM     still thinks the dev server is up — causes 500 "Vite manifest not found")
REM   - starts the Laravel server on http://localhost:8000
REM   - starts the Vite dev server on http://localhost:5173 (hot reload)
REM
REM  Each server opens in its own window. Close those windows to stop them.
REM ============================================================================

cd /d "%~dp0"

echo Removing stale public\hot (if present)...
if exist "public\hot" del /f /q "public\hot"

echo Starting Laravel server on http://localhost:8000 ...
start "Fynla - Laravel :8000" cmd /k "php artisan serve --host=127.0.0.1 --port=8000"

echo Starting Vite dev server on http://localhost:5173 ...
start "Fynla - Vite :5173" cmd /k "npm run dev"

echo.
echo Both servers are starting in separate windows.
echo   App:  http://localhost:8000
echo   Vite: http://localhost:5173
echo.
echo Close the two server windows to stop them.
echo (If localhost:8000 ever 500s after closing Vite, just run this script again.)
pause
