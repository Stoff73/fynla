<template>
  <AppLayout>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <!-- Header -->
      <div class="mb-8">
        <h1 class="text-h2 font-display text-gray-900">Version Information</h1>
        <p class="mt-2 text-body-base text-gray-600">
          Fynla - Financial Planning System version history and changelog
        </p>
      </div>

      <!-- Current Version Card -->
      <div class="bg-white rounded-lg shadow-sm mb-6">
        <div class="p-6 border-b border-gray-200">
          <div class="flex items-center justify-between">
            <div>
              <h2 class="text-h3 font-display text-gray-900">Current Version</h2>
              <p class="mt-1 text-body-sm text-gray-600">Latest release information</p>
            </div>
            <div class="text-right">
              <div class="text-3xl font-bold text-primary-600">v0.8.1</div>
              <div class="text-body-sm text-gray-500 mt-1">Production Ready</div>
            </div>
          </div>
        </div>

        <div class="p-6">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <div>
              <h3 class="text-body-sm font-semibold text-gray-700 mb-2">Release Date</h3>
              <p class="text-body-base text-gray-900">22 February 2026</p>
            </div>
            <div>
              <h3 class="text-body-sm font-semibold text-gray-700 mb-2">Status</h3>
              <span class="inline-flex items-center px-3 py-1 rounded-full text-body-sm font-medium bg-green-100 text-green-800">
                Production Ready
              </span>
            </div>
          </div>

          <!-- Security Hardening -->
          <div class="mb-6">
            <h3 class="text-body-lg font-semibold text-gray-900 mb-4">Security Hardening (4 Phases)</h3>
            <p class="text-body-sm text-gray-600 mb-4">Comprehensive security review addressing 46 findings across authentication, API controllers, models, and frontend configuration.</p>
            <ul class="space-y-2">
              <li class="flex items-start">
                <svg class="w-5 h-5 text-red-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-gray-700"><strong>Data Encryption at Rest:</strong> Account numbers, sort codes, mortgage account numbers, and National Insurance numbers now encrypted using Laravel's Crypt facade with graceful plaintext fallback.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-red-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-gray-700"><strong>Brute Force Protection:</strong> MFA verification, login email codes, and registration codes now track failed attempts and lock after 5 failures.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-red-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-gray-700"><strong>Account Enumeration Prevention:</strong> Registration endpoint returns identical responses for existing and new email addresses.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-red-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-gray-700"><strong>Challenge Token Authentication:</strong> Login email verification uses cache-backed challenge tokens instead of exposing raw user IDs.</span>
              </li>
            </ul>
          </div>

          <!-- Security Headers & Config -->
          <div class="mb-6">
            <h3 class="text-body-lg font-semibold text-gray-900 mb-4">Security Headers and Configuration</h3>
            <ul class="space-y-2">
              <li class="flex items-start">
                <svg class="w-5 h-5 text-blue-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-gray-700"><strong>Content Security Policy:</strong> Removed <code>unsafe-eval</code> from CSP headers across both deployment targets.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-blue-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-gray-700"><strong>Permissions Policy:</strong> Disabled camera, microphone, geolocation, payment, USB, and Bluetooth browser APIs.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-blue-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-gray-700"><strong>Session Security:</strong> Secure-only cookies enabled by default, sessions expire on browser close, HSTS headers added.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-blue-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-gray-700"><strong>Source Maps Disabled:</strong> Production builds no longer include JavaScript source maps.</span>
              </li>
            </ul>
          </div>

          <!-- Model & API Hardening -->
          <div class="mb-6">
            <h3 class="text-body-lg font-semibold text-gray-900 mb-4">Model and API Hardening</h3>
            <ul class="space-y-2">
              <li class="flex items-start">
                <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-gray-700"><strong>Hidden Attributes:</strong> Sensitive fields on LetterToSpouse, Payment, PasswordResetSession, and LoginAttempt models hidden from API serialisation.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-gray-700"><strong>Mass Assignment Protection:</strong> Subscription model switched from <code>$guarded</code> to explicit <code>$fillable</code>, ActuarialLifeTable guarded.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-gray-700"><strong>Generic Error Messages:</strong> Role and permission middleware no longer reveal internal authorisation details in error responses.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-gray-700"><strong>Audit Trail Improvements:</strong> MFA secrets excluded from audit logging, email addresses masked in registration logs, bug report log size reduced.</span>
              </li>
            </ul>
          </div>

          <!-- Other Changes -->
          <div class="mb-6">
            <h3 class="text-body-lg font-semibold text-gray-900 mb-4">Other Changes</h3>
            <ul class="space-y-2">
              <li class="flex items-start">
                <svg class="w-5 h-5 text-gray-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-gray-700"><strong>Token Storage:</strong> Removed legacy <code>localStorage</code> auth token fallback, now uses <code>sessionStorage</code> exclusively.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-gray-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-gray-700"><strong>Webhook Rate Limiting:</strong> Payment webhook endpoint now rate-limited to 30 requests per minute.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-gray-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-gray-700"><strong>Preview Mode Hardening:</strong> Fixed route matching in PreviewWriteInterceptor to prevent prefix bypass attacks.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-gray-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-gray-700"><strong>Debug Cleanup:</strong> Removed development <code>console.log</code> statements from RetirementIncomeTab and BalanceSheetTab components.</span>
              </li>
            </ul>
          </div>
        </div>

        <!-- Version History Divider -->
        <div class="border-t border-gray-200 my-8"></div>

        <!-- Previous Version: v0.7.0 -->
        <div class="mb-8 px-6">
          <h2 class="text-h3 font-display text-gray-900 mb-4">Previous Release: v0.7.0</h2>
          <p class="text-body-sm text-gray-600 mb-4">Released: 6 February 2026</p>

          <!-- Laravel Best Practices & Code Quality -->
          <div class="mb-6">
            <h3 class="text-body-lg font-semibold text-gray-900 mb-4">Laravel Best Practices Audit</h3>
            <ul class="space-y-2">
              <li class="flex items-start">
                <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-gray-700"><strong>Form Request Validation:</strong> Created 12 Form Request classes for Investment and Estate modules, replacing inline validation.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-gray-700"><strong>API Resources:</strong> Created 10 API Resource classes for consistent response transformation across all endpoints.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-gray-700"><strong>Controller Extraction:</strong> Extracted 470 lines from IHTController to IHTFormattingService. Code quality score improved from 85 to 94.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-gray-700"><strong>PHP 8 Modernisation:</strong> Readonly constructor properties, PHP 8 property promotion, Builder return types on 39 query scopes.</span>
              </li>
            </ul>
          </div>

          <!-- Goals & Life Events -->
          <div class="mb-6">
            <h3 class="text-body-lg font-semibold text-gray-900 mb-4">🎯 Goals & Life Events Improvements</h3>
            <ul class="space-y-2">
              <li class="flex items-start">
                <svg class="w-5 h-5 text-blue-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-gray-700"><strong>Realistic Projections:</strong> Replaced complex simulation with simple Future Value calculation using user's own growth and inflation assumptions.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-blue-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-gray-700"><strong>Cash Flow Chart Fix:</strong> Income now correctly calculated using UK tax calculator instead of showing zero.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-blue-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-gray-700"><strong>Contribution Streaks:</strong> Goal contribution streak tracking with longest streak badges and visual progress indicators.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-blue-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-gray-700"><strong>Life Events Management:</strong> Full CRUD for life events with household view toggle and projection impact display.</span>
              </li>
            </ul>
          </div>

          <!-- Retirement Planner -->
          <div class="mb-6">
            <h3 class="text-body-lg font-semibold text-gray-900 mb-4">🏦 Retirement Planner Enhancements</h3>
            <ul class="space-y-2">
              <li class="flex items-start">
                <svg class="w-5 h-5 text-purple-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-gray-700"><strong>Income Tax Slider:</strong> Interactive slider to model different retirement income levels and see the tax impact in real-time.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-purple-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-gray-700"><strong>Decumulation Graph:</strong> Visual projection of pension fund drawdown over retirement years.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-purple-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-gray-700"><strong>DB/State Pension Fallback:</strong> Dashboard retirement card now shows projected income for users with only DB or State pensions.</span>
              </li>
            </ul>
          </div>

          <!-- Net Worth & Wealth Summary -->
          <div class="mb-6">
            <h3 class="text-body-lg font-semibold text-gray-900 mb-4">📊 Net Worth & Wealth Summary</h3>
            <ul class="space-y-2">
              <li class="flex items-start">
                <svg class="w-5 h-5 text-teal-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-gray-700"><strong>DB Pension Exclusion:</strong> Wealth summary now correctly excludes defined benefit pensions (not accessible capital) with explanatory note.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-teal-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-gray-700"><strong>Responsive Values:</strong> Fixed currency values being cut off on smaller screens with flexible grid columns and responsive font sizing.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-teal-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-gray-700"><strong>Joint Ownership Accuracy:</strong> Dashboard investment and savings cards now display ownership share instead of full account value.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-teal-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-gray-700"><strong>Chattels Fix:</strong> Joint chattels now correctly show ownership type and percentage-based value share.</span>
              </li>
            </ul>
          </div>

          <!-- Bug Fixes -->
          <div class="mb-6">
            <h3 class="text-body-lg font-semibold text-gray-900 mb-4">🐛 Bug Fixes & Data Accuracy</h3>
            <ul class="space-y-2">
              <li class="flex items-start">
                <svg class="w-5 h-5 text-red-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-gray-700"><strong>API Resource Null Safety:</strong> Fixed 500 errors across 8 API Resources when eager-loaded relationships resolve to null.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-red-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-gray-700"><strong>Mortgage Display:</strong> Fixed mortgage values not appearing in property cards due to field name mismatch between API resource and frontend.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-red-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-gray-700"><strong>Info Guide Fix:</strong> Fixed 500 error on requirements endpoint and corrected DC pension requirement logic for DB-only users.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-red-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-gray-700"><strong>Occupation Lookup:</strong> Populated 406 ONS SOC 2020 occupation codes (table was empty after initial migration).</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-red-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-gray-700"><strong>DC Depletion Warning:</strong> Warning now only shows when user actually has DC pensions, not for DB/State-only users.</span>
              </li>
            </ul>
          </div>

          <!-- UI & Informational Updates -->
          <div class="mb-6">
            <h3 class="text-body-lg font-semibold text-gray-900 mb-4">⚙️ Other Changes</h3>
            <ul class="space-y-2">
              <li class="flex items-start">
                <svg class="w-5 h-5 text-gray-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-gray-700"><strong>Document Upload Disclaimer:</strong> Privacy banner on document upload modal explaining AI processing via Anthropic's Haiku 3.5.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-gray-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-gray-700"><strong>Postcode Lookup Removed:</strong> GetAddress.io provider shut down; manual address entry fields remain for all forms.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-gray-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-gray-700"><strong>Security Nav Link:</strong> Added Security page link to the main navigation sidebar.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-gray-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-gray-700"><strong>Lazy Loading Prevention:</strong> Enabled Model::preventLazyLoading() in development to catch N+1 query issues early.</span>
              </li>
            </ul>
          </div>
        </div>

        <!-- Version History Divider -->
        <div class="border-t border-gray-200 my-8"></div>

        <!-- Previous Version: v0.6.2 -->
        <div class="mb-8 px-6">
          <h2 class="text-h3 font-display text-gray-900 mb-4">Previous Release: v0.6.2</h2>
          <p class="text-body-sm text-gray-600 mb-4">Released: 19 January 2026</p>

          <div class="mb-6">
            <h3 class="text-body-lg font-semibold text-gray-900 mb-4">Security, Goals, Risk Calculator, Financial Statements, Investment Dashboard, Personas, Registration</h3>
            <ul class="space-y-2">
              <li class="flex items-start">
                <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-gray-700"><strong>TOTP MFA, Failed Login Tracking, Session Management, GDPR Compliance, Audit Logging, RBAC Authorisation</strong></span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-gray-700"><strong>Goals-Based Planning Module:</strong> Centralised goal management, progress tracking, dashboard integration, 8 goal types.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-gray-700"><strong>Automated Risk Calculator:</strong> 7-factor analysis with auto-recalculation via model observers.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-gray-700"><strong>Financial Statements:</strong> Balance sheet, income statement, and cash flow views with joint ownership calculations.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-gray-700"><strong>Investment Dashboard Redesign:</strong> Strategy terminology, sidebar card navigation, asset allocation bar, simplified Monte Carlo.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-gray-700"><strong>New Personas:</strong> Young Adult Saver (John Morgan) and Retired Couple (Patricia & Harold Bennett).</span>
              </li>
            </ul>
          </div>
        </div>

        <!-- Version History Divider -->
        <div class="border-t border-gray-200 my-8"></div>

        <!-- Previous Version: v0.5.1 -->
        <div class="mb-8 px-6">
          <h2 class="text-h3 font-display text-gray-900 mb-4">Previous Release: v0.5.1</h2>
          <p class="text-body-sm text-gray-600 mb-4">Released: 16 January 2026</p>

          <!-- What's New in v0.5.1 -->
          <div class="mb-6">
            <h3 class="text-body-lg font-semibold text-gray-900 mb-4">🎨 UI Refresh & New Features in v0.5.1</h3>
            <ul class="space-y-2">
              <li class="flex items-start">
                <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-gray-700"><strong>Security & Privacy Page:</strong> New comprehensive page covering authentication, data protection, GDPR compliance, and business continuity.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-gray-700"><strong>Landing Page Enhancements:</strong> Interactive hero stats cards, fixed rotating text spacing, optimised layout.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-gray-700"><strong>Restyled Public Pages:</strong> Calculators and Learning Centre with glass-morphism effects and coloured icons.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-gray-700"><strong>Other Trust Type:</strong> Support for offshore and foreign trusts with country selection field.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-gray-700"><strong>Platform Fee Options:</strong> Investment accounts now support percentage or fixed fees with monthly/quarterly/annual frequency.</span>
              </li>
            </ul>
          </div>

          <!-- Estate & Retirement Module Updates -->
          <div class="mb-6">
            <h3 class="text-body-lg font-semibold text-gray-900 mb-4">🏛️ Estate & Retirement Module Updates</h3>
            <ul class="space-y-2">
              <li class="flex items-start">
                <svg class="w-5 h-5 text-purple-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-gray-700"><strong>IHT → Inheritance Tax:</strong> Replaced acronym with full term throughout 19 components for clarity.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-purple-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-gray-700"><strong>Tax Projection Columns:</strong> Added -5 and +5 year projections to Inheritance Tax calculation tables.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-purple-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-gray-700"><strong>Estate Dashboard Redesign:</strong> Tab navigation removed, strategies accessible via clickable cards.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-purple-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-gray-700"><strong>Retirement Dashboard:</strong> New 3-column layout with Monte Carlo projections, pension sidebar, and income summary.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-purple-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-gray-700"><strong>Protection Gap Analysis:</strong> Moved into Policy Overview tab with 5 shortfall cards and affordability assessment.</span>
              </li>
            </ul>
          </div>

          <!-- Dashboard & UX Improvements -->
          <div class="mb-6">
            <h3 class="text-body-lg font-semibold text-gray-900 mb-4">📊 Dashboard & UX Improvements</h3>
            <ul class="space-y-2">
              <li class="flex items-start">
                <svg class="w-5 h-5 text-blue-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-gray-700"><strong>Draggable Dashboard Widgets:</strong> Drag and drop to reorder widgets, order saved to database.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-blue-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-gray-700"><strong>White Dashboard Background:</strong> Cleaner appearance with white instead of gray.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-blue-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-gray-700"><strong>Removed Curved Borders:</strong> Info boxes updated to neutral gray background (92 files updated).</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-blue-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-gray-700"><strong>Auto-Collapse Sidebar:</strong> Cash and Retirement tabs auto-collapse sidebar for more screen space.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-blue-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-gray-700"><strong>Clickable Wealth Summary:</strong> Each row in Net Worth summary navigates to the relevant section.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-blue-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-gray-700"><strong>Investment Card:</strong> Account list with type badges and annualised returns per account.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-blue-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-gray-700"><strong>Tax Allowances Card:</strong> Conditional display based on user situation (CGT only if GIA exists).</span>
              </li>
            </ul>
          </div>

          <!-- Forms & Data Entry -->
          <div class="mb-6">
            <h3 class="text-body-lg font-semibold text-gray-900 mb-4">📝 Forms & Data Entry</h3>
            <ul class="space-y-2">
              <li class="flex items-start">
                <svg class="w-5 h-5 text-blue-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-gray-700"><strong>Optional Form Fields:</strong> All fields now optional to allow partial data entry and saving.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-blue-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-gray-700"><strong>Red Asterisks Removed:</strong> No more required field indicators throughout the application.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-blue-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-gray-700"><strong>Database Defaults:</strong> Sensible defaults added for NOT NULL columns via migration.</span>
              </li>
            </ul>
          </div>

          <!-- Bug Fixes -->
          <div class="mb-6">
            <h3 class="text-body-lg font-semibold text-gray-900 mb-4">🐛 Bug Fixes</h3>
            <ul class="space-y-2">
              <li class="flex items-start">
                <svg class="w-5 h-5 text-red-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-gray-700"><strong>Life Expectancy Consolidated:</strong> Single source of truth (actuarial_life_tables database table).</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-red-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-gray-700"><strong>Spouse Data Visibility:</strong> Widowed and divorced users no longer see spouse-related UI elements.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-red-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-gray-700"><strong>Router Scroll Behaviour:</strong> Pages now load at the top when navigating.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-red-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-gray-700"><strong>Estate Card IHT Display:</strong> Fallback calculation when store data is null.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-red-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-gray-700"><strong>Protection Shortfalls:</strong> Now matches GapAnalysis component calculations exactly.</span>
              </li>
            </ul>
          </div>

          <!-- Technical Changes -->
          <div class="mb-6">
            <h3 class="text-body-lg font-semibold text-gray-900 mb-4">⚙️ Technical Changes</h3>
            <ul class="space-y-2">
              <li class="flex items-start">
                <svg class="w-5 h-5 text-gray-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-gray-700"><strong>Vuex Store Refactor:</strong> Protection store reduced by 40% with action factory functions.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-gray-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-gray-700"><strong>Rate Limit Increase:</strong> API rate limit increased from 60 to 300 requests/minute.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-gray-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-gray-700"><strong>Email Branding:</strong> Spouse account emails updated from FPS to Fynla branding.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-gray-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-gray-700"><strong>Vuedraggable:</strong> Added vuedraggable@next for dashboard widget reordering.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-gray-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-gray-700"><strong>Database Migrations:</strong> Added dashboard_widget_order, platform fee fields, other trust type fields.</span>
              </li>
            </ul>
          </div>
        </div>

        <!-- Version History Divider -->
        <div class="border-t border-gray-200 my-8"></div>

        <!-- Previous Version: v0.4.5 -->
        <div class="mb-8 px-6">
          <h2 class="text-h3 font-display text-gray-900 mb-4">Previous Release: v0.4.5</h2>
          <p class="text-body-sm text-gray-600 mb-4">Released: 1 January 2026</p>

          <div class="mb-6">
            <h3 class="text-body-lg font-semibold text-gray-900 mb-4">💎 Personal Valuables Module with CGT Calculator</h3>
            <ul class="space-y-2">
              <li class="flex items-start">
                <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-gray-700"><strong>Full Personal Valuables Module:</strong> Track vehicles, art, antiques, jewellery, collectibles in Net Worth.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-gray-700"><strong>CGT Calculator:</strong> UK-compliant with £6,000 threshold, marginal relief (5/3 rule), wasting asset exemption.</span>
              </li>
            </ul>
          </div>
        </div>

        <!-- Version History Divider -->
        <div class="border-t border-gray-200 my-8"></div>

        <!-- Previous Version: v0.4.4 -->
        <div class="mb-8 px-6">
          <h2 class="text-h3 font-display text-gray-900 mb-4">Previous Release: v0.4.4</h2>
          <p class="text-body-sm text-gray-600 mb-4">Released: 31 December 2025</p>

          <div class="mb-6">
            <h3 class="text-body-lg font-semibold text-gray-900 mb-4">🔧 Code Quality & Architecture Improvements</h3>
            <ul class="space-y-2">
              <li class="flex items-start">
                <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-gray-700"><strong>Investment Diversification Tab:</strong> New diversification analysis for investment accounts and DC pensions.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-gray-700"><strong>EstateAgent Created:</strong> New EstateAgent for architectural consistency.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-gray-700"><strong>Dynamic Tax Values:</strong> Removed hardcoded ISA allowances - uses API-backed TaxConfigService.</span>
              </li>
            </ul>
          </div>
        </div>

        <!-- Version History Divider -->
        <div class="border-t border-gray-200 my-8"></div>

        <!-- Previous Version: v0.4.3 -->
        <div class="mb-8 px-6">
          <h2 class="text-h3 font-display text-gray-900 mb-4">Previous Release: v0.4.3</h2>
          <p class="text-body-sm text-gray-600 mb-4">Released: 31 December 2025</p>

          <div class="mb-6">
            <h3 class="text-body-lg font-semibold text-gray-900 mb-4">📊 Investment Returns & Info Guide</h3>
            <ul class="space-y-2">
              <li class="flex items-start">
                <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-gray-700"><strong>Investment Account Panels:</strong> Holdings, Performance, Fees, and Rebalancing panels for detailed account analysis.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-gray-700"><strong>Portfolio Strategy Tab:</strong> Tax, fee, and rebalancing recommendations aggregated by priority.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-gray-700"><strong>Info Guide Feature:</strong> Floating help button showing data requirements per module with plain-language explanations.</span>
              </li>
            </ul>
          </div>
        </div>

        <!-- Version History Divider -->
        <div class="border-t border-gray-200 my-8"></div>

        <!-- Previous Version: v0.4.2 -->
        <div class="mb-8 px-6">
          <h2 class="text-h3 font-display text-gray-900 mb-4">Previous Release: v0.4.2</h2>
          <p class="text-body-sm text-gray-600 mb-4">Released: 29 December 2025</p>

          <div class="mb-6">
            <h3 class="text-body-lg font-semibold text-gray-900 mb-4">🏢 Business Interests Module</h3>
            <ul class="space-y-2">
              <li class="flex items-start">
                <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-gray-700"><strong>Full Business Interests Module:</strong> Track Sole Trader, Partnership, Limited Company, and LLP business structures.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-gray-700"><strong>Tax Deadlines:</strong> Automatic calculation based on business type (Self Assessment, Corporation Tax, VAT, PAYE).</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-gray-700"><strong>Exit Planning:</strong> Capital Gains Tax calculations with Business Asset Disposal Relief eligibility.</span>
              </li>
            </ul>
          </div>
        </div>

        <!-- Version History Divider -->
        <div class="border-t border-gray-200 my-8"></div>

        <!-- Previous Version: v0.4.1 -->
        <div class="mb-8 px-6">
          <h2 class="text-h3 font-display text-gray-900 mb-4">Previous Release: v0.4.1</h2>
          <p class="text-body-sm text-gray-600 mb-4">Released: 19 December 2025</p>

          <div class="mb-6">
            <h3 class="text-body-lg font-semibold text-gray-900 mb-4">🛡️ Protection Module Enhancements</h3>
            <ul class="space-y-2">
              <li class="flex items-start">
                <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-gray-700"><strong>Simplified Policy Overview:</strong> Clean Coverage Summary with 5 key metrics.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-gray-700"><strong>5 Protection Shortfall Cards:</strong> Debt Protection, Income Replacement, Critical Illness, Sickness Cover, Disability Cover.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-gray-700"><strong>UK Statutory Sick Pay:</strong> SSP calculation for employees with self-employed eligibility check.</span>
              </li>
            </ul>
          </div>
        </div>

        <!-- Version History Divider -->
        <div class="border-t border-gray-200 my-8"></div>

        <!-- Previous Version: v0.2.17 -->
        <div class="mb-8 px-6">
          <h2 class="text-h3 font-display text-gray-900 mb-4">Previous Release: v0.2.17</h2>
          <p class="text-body-sm text-gray-600 mb-4">Released: 8 December 2025</p>

          <div class="mb-6">
            <h3 class="text-body-lg font-semibold text-gray-900 mb-4">📄 Document Upload with AI Extraction</h3>
            <ul class="space-y-2">
              <li class="flex items-start">
                <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-gray-700"><strong>AI-Powered Extraction:</strong> Upload PDFs, images, and Excel files to extract financial data using Claude Sonnet 4.5.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-gray-700"><strong>Supported Documents:</strong> Pension statements, insurance policies, investment statements, mortgages, savings.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-gray-700"><strong>Review & Confirm:</strong> Review extracted fields with confidence scores before saving to your profile.</span>
              </li>
            </ul>
          </div>
        </div>

        <!-- Previous Version: v0.2.16 -->
        <div class="mb-8 px-6">
          <h2 class="text-h3 font-display text-gray-900 mb-4">v0.2.16</h2>
          <p class="text-body-sm text-gray-600 mb-4">Released: 27 November 2025</p>

          <div class="mb-6">
            <h3 class="text-body-lg font-semibold text-gray-900 mb-4">🚀 UI/UX Improvements</h3>
            <ul class="space-y-2">
              <li class="flex items-start">
                <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-gray-700">Income Protection benefit display fix, Investment & Savings Plan scaffold banner, Emergency Fund data fix.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-gray-700">Onboarding UI improvements: Property, Retirement, Investment, and Savings cards now match module dashboards.</span>
              </li>
            </ul>
          </div>
        </div>

        <!-- Previous Version: v0.2.15 -->
        <div class="mb-8 px-6">
          <h2 class="text-h3 font-display text-gray-900 mb-4">v0.2.15</h2>
          <p class="text-body-sm text-gray-600 mb-4">Released: 26 November 2025</p>

          <div class="mb-6">
            <h3 class="text-body-lg font-semibold text-gray-900 mb-4">🐛 Bug Fixes</h3>
            <ul class="space-y-2">
              <li class="flex items-start">
                <svg class="w-5 h-5 text-red-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-gray-700"><strong>Joint Expenditure 50/50 Split:</strong> Fixed expenditure not being halved when using joint mode during onboarding.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-red-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-gray-700"><strong>Spouse Edit Sync:</strong> Fixed editing spouse family member not updating the linked User account.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-red-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-gray-700"><strong>Estate Plan Spouse Children:</strong> Fixed Comprehensive Estate Plan not showing children added by spouse.</span>
              </li>
            </ul>
          </div>
        </div>

        <!-- Version History Divider -->
        <div class="border-t border-gray-200 my-8"></div>

        <!-- Previous Version: v0.2.14 -->
        <div class="mb-8">
          <h2 class="text-h3 font-display text-gray-900 mb-4">Previous Release: v0.2.14</h2>
          <p class="text-body-sm text-gray-600 mb-4">Released: 25 November 2025</p>

          <div class="mb-6">
            <h3 class="text-body-lg font-semibold text-gray-900 mb-4">🐛 10 Major Fixes in v0.2.14</h3>
            <ul class="space-y-2">
              <li class="flex items-start">
                <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-gray-700"><strong>Family Member/Spouse Account Linking:</strong> User Profile now uses same service as onboarding for consistent behaviour.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-gray-700"><strong>Joint Property Sync:</strong> Joint property editing shows full values and syncs with audit trail.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-gray-700"><strong>Joint Property Cost/Income Split:</strong> Monthly costs and rental income split by ownership percentage.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-gray-700"><strong>Joint Account History UI:</strong> New "Joint History" tab in Net Worth dashboard.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-gray-700"><strong>Code Quality Fixes:</strong> 11 issues resolved (1 critical: division by zero prevention).</span>
              </li>
            </ul>
          </div>
        </div>

        <!-- Previous Version: v0.2.8 -->
        <div class="mb-8">
          <h2 class="text-h3 font-display text-gray-900 mb-4">v0.2.8</h2>
          <p class="text-body-sm text-gray-600 mb-4">Released: 14-15 November 2025</p>

          <div class="mb-6">
            <h3 class="text-body-lg font-semibold text-gray-900 mb-4">🐛 Critical Bug Fixes in v0.2.8</h3>
            <ul class="space-y-2">
              <li class="flex items-start">
                <svg class="w-5 h-5 text-red-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-gray-700"><strong>Joint Mortgage Reciprocal Creation (CRITICAL):</strong> Fixed joint properties with mortgages only creating ONE mortgage record instead of TWO. Added missing database columns (ownership_type, joint_owner_name) and Vue watchers to sync ownership data.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-gray-700"><strong>Expenditure Form Defaulting:</strong> Fixed expenditure form defaulting to simple total instead of detailed breakdown during onboarding. Now correctly preserves user's entry mode choice.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-gray-700"><strong>State Pension Field Mismatch:</strong> Fixed 422 validation errors caused by field name mismatch. Changed backend validation to accept weekly_amount instead of amount.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-gray-700"><strong>Mortgage Fields Persistence:</strong> Fixed all 24 mortgage fields (repayment type, rate type, lender, start date, etc.) now persisting correctly. Added comprehensive validation.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-gray-700"><strong>Invalid Mortgage Type Removed:</strong> Removed invalid 'part_and_part' mortgage type from enum. Only valid types: repayment, interest_only, mixed.</span>
              </li>
            </ul>
          </div>

          <!-- Module Improvements -->
          <div class="mb-6">
            <h3 class="text-body-lg font-semibold text-gray-900 mb-4">⚙️ Module Improvements in v0.2.8</h3>
            <ul class="space-y-2">
              <li class="flex items-start">
                <svg class="w-5 h-5 text-blue-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-gray-700"><strong>Retirement Module Consolidation:</strong> Created unified pension form with visual type selection (DC/DB/State). Consolidated retirement access to /net-worth/retirement only. Removed standalone /retirement route.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-blue-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-gray-700"><strong>Net Worth UI Enhancements:</strong> Card grid layouts for investments and pensions. Color coding (blue assets, red liabilities). Joint property/mortgage full amounts with user share display. Mixed mortgage type display with percentages.</span>
              </li>
              <li class="flex items-start">
                <svg class="w-5 h-5 text-blue-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-body-base text-gray-700"><strong>Database Migrations:</strong> 10 new migrations adding DC pension types, joint mortgage ownership fields, state pension enhancements, and more.</span>
              </li>
            </ul>
          </div>

          <!-- Current Functionality -->
          <div>
            <h3 class="text-body-lg font-semibold text-gray-900 mb-4">🚀 Current Functionality</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div class="border border-gray-200 rounded-lg p-4">
                <h4 class="font-semibold text-gray-900 mb-2">✅ Completed Modules</h4>
                <ul class="space-y-1 text-body-sm text-gray-700">
                  <li><strong>Core Features:</strong></li>
                  <li>• User authentication & profile management</li>
                  <li>• Family members & spouse account linking</li>
                  <li>• Income, occupation, and assets tracking</li>
                  <li>• Joint ownership & trust ownership</li>
                  <li>• Main dashboard with quick actions</li>

                  <li class="mt-2"><strong>Investment Module:</strong></li>
                  <li>• Portfolio analysis with risk metrics (Alpha, Beta, Sharpe)</li>
                  <li>• Asset allocation & diversification scoring</li>
                  <li>• Fee analysis & optimisation suggestions</li>
                  <li>• Monte Carlo simulations (1,000 iterations)</li>
                  <li>• Efficient Frontier analysis</li>
                  <li>• Holdings management & rebalancing</li>

                  <li class="mt-2"><strong>Retirement Module:</strong></li>
                  <li>• DC/DB pension tracking & projections</li>
                  <li>• State pension integration</li>
                  <li>• Annual allowance monitoring (£60k + carry forward)</li>
                  <li>• DC pension portfolio optimisation</li>
                  <li>• Holdings management for pension pots</li>
                  <li>• Retirement readiness scoring</li>
                  <li>• Decumulation planning</li>

                  <li class="mt-2"><strong>Savings Module:</strong></li>
                  <li>• Emergency fund calculator & runway analysis</li>
                  <li>• Savings goals tracking & progress</li>
                  <li>• ISA allowance tracking (£20k annual limit)</li>
                  <li>• Cash accounts management</li>
                </ul>
              </div>
              <div class="border border-gray-200 rounded-lg p-4">
                <h4 class="font-semibold text-gray-900 mb-2">✅ Completed Modules (cont.)</h4>
                <ul class="space-y-1 text-body-sm text-gray-700">
                  <li><strong>Estate Planning Module:</strong></li>
                  <li>• IHT calculation (NRB £325k + RNRB £175k)</li>
                  <li>• Second death IHT planning</li>
                  <li>• Net worth aggregation across all modules</li>
                  <li>• Gifting strategy (PET/CLT 7-year rules)</li>
                  <li>• Life policy strategy (WoL vs self-insurance)</li>
                  <li>• Will planning with bequests</li>
                  <li>• Letter to Spouse (emergency instructions)</li>
                  <li>• Trust tracking & management</li>

                  <li class="mt-2"><strong>Protection Module:</strong></li>
                  <li>• Life insurance coverage gap analysis</li>
                  <li>• Critical illness adequacy scoring</li>
                  <li>• Income protection recommendations</li>
                  <li>• Human capital calculations</li>
                  <li>• Protection plan generation</li>
                  <li>• Scenario analysis (death, CI, disability)</li>

                  <li class="mt-2"><strong>Plans Module:</strong></li>
                  <li>• Investment & Savings Plan (consolidated view)</li>
                  <li>• Risk metrics dashboard</li>
                  <li>• Goal progress tracking</li>
                  <li>• Asset allocation analysis</li>
                </ul>

                <h4 class="font-semibold text-gray-900 mb-2 mt-4">🚧 In Development</h4>
                <ul class="space-y-1 text-body-sm text-gray-700">
                  <li>• Holistic planning coordination (cross-module)</li>
                  <li>• Advanced scenario modeling</li>
                  <li>• Tax optimisation strategies</li>
                  <li>• Probate planning</li>
                  <li>• Comprehensive reporting & exports</li>
                </ul>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Version History -->
      <div class="bg-white rounded-lg shadow-sm mb-6">
        <div class="p-6 border-b border-gray-200">
          <h2 class="text-h3 font-display text-gray-900">Version History</h2>
          <p class="mt-1 text-body-sm text-gray-600">Previous releases and changes</p>
        </div>
        <div class="p-6">
          <!-- v0.2.1 -->
          <div class="mb-8 pb-8 border-b border-gray-200">
            <div class="flex items-center justify-between mb-4">
              <div>
                <h3 class="text-xl font-bold text-gray-900">v0.2.1</h3>
                <p class="text-body-sm text-gray-600">4 November 2025</p>
              </div>
              <span class="inline-flex items-center px-3 py-1 rounded-full text-body-sm font-medium bg-blue-100 text-blue-800">
                Beta Release
              </span>
            </div>
            <div class="space-y-4">
              <div>
                <h4 class="font-semibold text-gray-900 mb-2">Features</h4>
                <ul class="space-y-1 text-body-sm text-gray-700">
                  <li>• Investment & Savings Plans Module with consolidated goal tracking</li>
                  <li>• DC Pension Portfolio Optimisation with full holdings management</li>
                  <li>• Polymorphic Holdings System for Investment Accounts and DC Pensions</li>
                  <li>• Advanced Risk Metrics (Alpha, Beta, Sharpe Ratio, VaR, Max Drawdown)</li>
                  <li>• Portfolio Analysis & Fee Optimisation suggestions</li>
                  <li>• Monte Carlo Simulation and Efficient Frontier integration</li>
                </ul>
              </div>
              <div>
                <h4 class="font-semibold text-gray-900 mb-2">Technical</h4>
                <ul class="space-y-1 text-body-sm text-gray-700">
                  <li>• Service Reuse Architecture (shared Investment services in Retirement)</li>
                  <li>• Database migration with rollback for polymorphic holdings</li>
                  <li>• Enhanced RetirementAgent with 5 Investment module services</li>
                  <li>• 9 new API endpoints for holdings and portfolio analysis</li>
                </ul>
              </div>
            </div>
          </div>

          <!-- v0.1.2.13 -->
          <div class="mb-8 pb-8 border-b border-gray-200">
            <div class="flex items-center justify-between mb-4">
              <div>
                <h3 class="text-xl font-bold text-gray-900">v0.1.2.13</h3>
                <p class="text-body-sm text-gray-600">29 October 2025</p>
              </div>
              <span class="inline-flex items-center px-3 py-1 rounded-full text-body-sm font-medium bg-green-100 text-green-800">
                Beta Release
              </span>
            </div>
            <div class="space-y-4">
              <div>
                <h4 class="font-semibold text-gray-900 mb-2">Features</h4>
                <ul class="space-y-1 text-body-sm text-gray-700">
                  <li>• Letter to Spouse feature with comprehensive emergency instructions</li>
                  <li>• Dual view mode (edit own letter, view spouse's letter)</li>
                  <li>• Auto-population from all modules (Protection, Estate, Savings, etc.)</li>
                  <li>• Application rebranding from "FPS" to "Fynla"</li>
                </ul>
              </div>
              <div>
                <h4 class="font-semibold text-gray-900 mb-2">Bug Fixes</h4>
                <ul class="space-y-1 text-body-sm text-gray-700">
                  <li>• Fixed Letter to Spouse type errors (number_format, ucfirst)</li>
                  <li>• Fixed API authentication issues (401 errors)</li>
                  <li>• Fixed component lifecycle mounting issues</li>
                  <li>• Fixed User Profile layout tab overflow</li>
                </ul>
              </div>
            </div>
          </div>

          <!-- v0.1.2.3 -->
          <div class="mb-8 pb-8 border-b border-gray-200">
            <div class="flex items-center justify-between mb-4">
              <div>
                <h3 class="text-xl font-bold text-gray-900">v0.1.2.3</h3>
                <p class="text-body-sm text-gray-600">25 October 2025</p>
              </div>
              <span class="inline-flex items-center px-3 py-1 rounded-full text-body-sm font-medium bg-green-100 text-green-800">
                Beta Release
              </span>
            </div>
            <div class="space-y-4">
              <div>
                <h4 class="font-semibold text-gray-900 mb-2">Features</h4>
                <ul class="space-y-1 text-body-sm text-gray-700">
                  <li>• Comprehensive Protection Plan with professional report generation</li>
                  <li>• Executive Summary with adequacy scores (0-100)</li>
                  <li>• Coverage Gap Analysis for Life/CI/IP</li>
                  <li>• Optimised Strategy with prioritized recommendations</li>
                  <li>• Scenario Analysis (death, critical illness, disability)</li>
                  <li>• Print/PDF Export functionality</li>
                </ul>
              </div>
              <div>
                <h4 class="font-semibold text-gray-900 mb-2">Bug Fixes</h4>
                <ul class="space-y-1 text-body-sm text-gray-700">
                  <li>• Fixed Protection Plan array key mismatch errors</li>
                  <li>• Fixed type errors with str_replace() for nullable fields</li>
                  <li>• Fixed Income Protection data structure access issues</li>
                </ul>
              </div>
            </div>
          </div>

          <!-- v0.1.2.2 -->
          <div class="mb-8 pb-8 border-b border-gray-200">
            <div class="flex items-center justify-between mb-4">
              <div>
                <h3 class="text-xl font-bold text-gray-900">v0.1.2.2</h3>
                <p class="text-body-sm text-gray-600">21-23 October 2025</p>
              </div>
              <span class="inline-flex items-center px-3 py-1 rounded-full text-body-sm font-medium bg-green-100 text-green-800">
                Beta Release
              </span>
            </div>
            <div class="space-y-4">
              <div>
                <h4 class="font-semibold text-gray-900 mb-2">Features</h4>
                <ul class="space-y-1 text-body-sm text-gray-700">
                  <li>• Second Death IHT Planning with actuarial life expectancy calculations</li>
                  <li>• Life Policy Strategy (Whole of Life vs. Self-Insurance comparison)</li>
                  <li>• Cross-Module Data Integration for estate planning</li>
                  <li>• Dual Gifting Timelines for married couples</li>
                  <li>• NRB Transfer Tracking (up to £650k total NRB)</li>
                  <li>• IHT Mitigation Strategies with priority badges</li>
                  <li>• Spouse Account Management (auto-creation & linking)</li>
                  <li>• Joint Ownership across all asset types</li>
                  <li>• Trust Ownership tracking</li>
                  <li>• Spouse Data Sharing Permissions</li>
                  <li>• Email Notifications for spouse accounts</li>
                  <li>• Password Security (first-time login password change)</li>
                  <li>• Will Planning Module with death scenarios & bequests</li>
                </ul>
              </div>
              <div>
                <h4 class="font-semibold text-gray-900 mb-2">Bug Fixes</h4>
                <ul class="space-y-1 text-body-sm text-gray-700">
                  <li>• Fixed Second Death IHT calculation undefined property error</li>
                  <li>• Fixed type error with nullable arrays in mitigation strategies</li>
                  <li>• Fixed incorrect array access for different IHT data structures</li>
                  <li>• Fixed Investment Account Form 422 validation error</li>
                  <li>• Fixed Estate IHT calculation to include liabilities</li>
                  <li>• Fixed Property & Mortgage calculation errors</li>
                  <li>• Fixed Dashboard Estate display issues</li>
                  <li>• Fixed Will Planning authentication (401 errors)</li>
                </ul>
              </div>
            </div>
          </div>

          <!-- v0.1.1 -->
          <div class="mb-8 pb-8 border-b border-gray-200">
            <div class="flex items-center justify-between mb-4">
              <div>
                <h3 class="text-xl font-bold text-gray-900">v0.1.1</h3>
                <p class="text-body-sm text-gray-600">20 October 2025</p>
              </div>
              <span class="inline-flex items-center px-3 py-1 rounded-full text-body-sm font-medium bg-gray-100 text-gray-700">
                Internal Release
              </span>
            </div>
            <ul class="space-y-2 text-body-sm text-gray-700">
              <li class="flex items-start">
                <svg class="w-4 h-4 text-gray-400 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                Net Worth module improvements (property management, ISA tracking)
              </li>
              <li class="flex items-start">
                <svg class="w-4 h-4 text-gray-400 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                Investment account management components
              </li>
              <li class="flex items-start">
                <svg class="w-4 h-4 text-gray-400 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                Emergency fund tracking and analysis
              </li>
            </ul>
          </div>

          <!-- v0.1.0 -->
          <div class="mb-4">
            <div class="flex items-center justify-between mb-4">
              <div>
                <h3 class="text-xl font-bold text-gray-900">v0.1.0</h3>
                <p class="text-body-sm text-gray-600">18 October 2025</p>
              </div>
              <span class="inline-flex items-center px-3 py-1 rounded-full text-body-sm font-medium bg-yellow-100 text-yellow-800">
                Alpha Release
              </span>
            </div>
            <div class="space-y-4">
              <div>
                <h4 class="font-semibold text-gray-900 mb-2">Features</h4>
                <ul class="space-y-1 text-body-sm text-gray-700">
                  <li>• Laravel 10.x backend with Sanctum authentication</li>
                  <li>• Vue.js 3 frontend with full auth flow</li>
                  <li>• User Profile module (personal info, family, income, assets, liabilities)</li>
                  <li>• Main dashboard with module overview cards</li>
                  <li>• Settings page</li>
                  <li>• Pest testing framework (60+ tests)</li>
                </ul>
              </div>
              <div>
                <h4 class="font-semibold text-gray-900 mb-2">Bug Fixes</h4>
                <ul class="space-y-1 text-body-sm text-gray-700">
                  <li>• Fixed rate limiting issues (disabled throttle for development)</li>
                  <li>• Resolved infinite loop in FamilyMembers component</li>
                  <li>• Fixed missing axios import in TrustsDashboard</li>
                  <li>• Corrected router navigation warnings</li>
                  <li>• Restored missing navbar in NetWorthDashboard</li>
                  <li>• Fixed RecommendationsAggregatorService 500 error</li>
                </ul>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Technical Details -->
      <div class="bg-white rounded-lg shadow-sm">
        <div class="p-6 border-b border-gray-200">
          <h2 class="text-h3 font-display text-gray-900">Technical Stack</h2>
          <p class="mt-1 text-body-sm text-gray-600">Technology and frameworks used in this version</p>
        </div>
        <div class="p-6">
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <div>
              <h4 class="font-semibold text-gray-900 mb-2">Backend</h4>
              <ul class="space-y-1 text-body-sm text-gray-700">
                <li>• Laravel 10.x</li>
                <li>• PHP 8.2+</li>
                <li>• MySQL 8.0+</li>
                <li>• Laravel Sanctum (auth)</li>
                <li>• Pest (testing)</li>
              </ul>
            </div>
            <div>
              <h4 class="font-semibold text-gray-900 mb-2">Frontend</h4>
              <ul class="space-y-1 text-body-sm text-gray-700">
                <li>• Vue.js 3</li>
                <li>• Vuex 4.x (state management)</li>
                <li>• Vue Router 4.x</li>
                <li>• Vite 5.x (build tool)</li>
                <li>• Tailwind CSS 3.x</li>
              </ul>
            </div>
            <div>
              <h4 class="font-semibold text-gray-900 mb-2">Development</h4>
              <ul class="space-y-1 text-body-sm text-gray-700">
                <li>• Git version control</li>
                <li>• Laravel Pint (code formatting)</li>
                <li>• PSR-12 coding standards</li>
                <li>• Hot Module Replacement (HMR)</li>
              </ul>
            </div>
          </div>
        </div>
      </div>

      <!-- Footer Note -->
      <div class="mt-8 text-center text-body-sm text-gray-500">
        <p>For detailed changelog and bug fixes, see the <a href="/OCTOBER_2025_FEATURES_UPDATE.md" class="text-primary-600 hover:text-primary-700 underline">October 2025 Features Update</a> document.</p>
        <p class="mt-2">Built with Claude Code</p>
      </div>
    </div>
  </AppLayout>
</template>

<script>
import AppLayout from '@/layouts/AppLayout.vue';

export default {
  name: 'Version',

  components: {
    AppLayout,
  },
};
</script>
