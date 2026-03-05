<template>
  <PublicLayout>
    <!-- Hero Section with Security Cards - Two Column Layout -->
    <div class="relative bg-gradient-to-br from-slate-900 via-emerald-900 to-slate-900 overflow-hidden py-20">
      <!-- Animated Background Elements -->
      <div class="absolute inset-0 overflow-hidden">
        <div class="absolute top-1/4 left-1/4 w-72 h-72 bg-emerald-500 rounded-full mix-blend-multiply filter blur-3xl opacity-20 animate-pulse"></div>
        <div class="absolute top-1/3 right-1/4 w-72 h-72 bg-teal-500 rounded-full mix-blend-multiply filter blur-3xl opacity-20 animate-pulse" style="animation-delay: 1s;"></div>
        <div class="absolute bottom-1/4 left-1/3 w-72 h-72 bg-cyan-500 rounded-full mix-blend-multiply filter blur-3xl opacity-20 animate-pulse" style="animation-delay: 2s;"></div>
      </div>

      <!-- Grid Pattern Overlay -->
      <div class="absolute inset-0 bg-[url('data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNjAiIGhlaWdodD0iNjAiIHZpZXdCb3g9IjAgMCA2MCA2MCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48ZyBmaWxsPSJub25lIiBmaWxsLXJ1bGU9ImV2ZW5vZGQiPjxwYXRoIGQ9Ik0wIDBoNjB2NjBIMHoiLz48cGF0aCBkPSJNMzAgMzBtLTEgMGExIDEgMCAxIDAgMiAwYTEgMSAwIDEgMCAtMiAwIiBmaWxsPSJyZ2JhKDI1NSwyNTUsMjU1LDAuMSkiLz48L2c+PC9zdmc+')] opacity-40"></div>

      <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
          <!-- Left Column: Hero Heading -->
          <div class="text-center lg:text-left">
            <!-- Badge -->
            <div class="inline-flex items-center px-4 py-2 rounded-full bg-emerald-500/20 border border-emerald-400/30 text-emerald-200 text-sm font-medium mb-6 backdrop-blur-sm">
              <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
              </svg>
              Your Data is Protected
            </div>

            <h1 class="text-4xl md:text-6xl font-bold text-white mb-6">
              Security &
              <span class="bg-gradient-to-r from-emerald-400 via-teal-400 to-cyan-400 bg-clip-text text-transparent">
                Privacy
              </span>
            </h1>
            <p class="text-xl text-slate-300">
              We take the security of your financial data seriously. Learn about the measures we have in place to protect your information.
            </p>
          </div>

          <!-- Right Column: Security Cards -->
          <div class="space-y-6 max-w-md mx-auto">
            <div class="bg-white/10 backdrop-blur-md rounded-2xl p-6 border border-white/20">
              <div class="w-12 h-12 bg-emerald-100 rounded-xl flex items-center justify-center mb-4">
                <svg class="w-6 h-6 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                </svg>
              </div>
              <h3 class="text-lg font-bold text-white mb-2">Encrypted Connections</h3>
              <p class="text-slate-300">All data transmitted between your browser and our servers is encrypted using TLS 1.3.</p>
            </div>

            <div class="bg-white/10 backdrop-blur-md rounded-2xl p-6 border border-white/20">
              <div class="w-12 h-12 bg-violet-100 rounded-xl flex items-center justify-center mb-4">
                <svg class="w-6 h-6 text-violet-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                </svg>
              </div>
              <h3 class="text-lg font-bold text-white mb-2">UK Data Residency</h3>
              <p class="text-slate-300">Your data is stored securely in UK-based data centres, compliant with UK data protection laws.</p>
            </div>

            <div class="bg-white/10 backdrop-blur-md rounded-2xl p-6 border border-white/20">
              <div class="w-12 h-12 bg-purple-100 rounded-xl flex items-center justify-center mb-4">
                <svg class="w-6 h-6 text-purple-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 11V7a4 4 0 118 0m-4 8v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2z" />
                </svg>
              </div>
              <h3 class="text-lg font-bold text-white mb-2">Your Data, Your Control</h3>
              <p class="text-slate-300">Export or delete your data at any time. We never share your information with third parties.</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Detailed Security Sections -->
    <div class="bg-slate-50 py-16">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

        <!-- Detailed Security Sections -->
        <div class="space-y-8">
          <!-- Authentication & Account Security -->
          <div class="bg-white rounded-2xl shadow-lg border border-slate-200 overflow-hidden security-section">
            <div class="bg-gradient-to-r from-emerald-600 to-teal-600 px-8 py-6">
              <div class="flex items-center">
                <div class="w-10 h-10 bg-white/20 rounded-lg flex items-center justify-center mr-4">
                  <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                  </svg>
                </div>
                <div>
                  <h2 class="text-xl font-bold text-white">Authentication & Account Security</h2>
                  <p class="text-emerald-100 text-sm">Protecting access to your account</p>
                </div>
              </div>
            </div>
            <div class="p-8">
              <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="flex items-start">
                  <div class="w-8 h-8 bg-emerald-100 rounded-lg flex items-center justify-center mr-3 mt-0.5 flex-shrink-0">
                    <svg class="w-4 h-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                  </div>
                  <div>
                    <h4 class="font-semibold text-slate-900">Multi-Factor Authentication</h4>
                    <p class="text-slate-600 text-sm">App-based TOTP authentication with backup recovery codes for secure account access.</p>
                  </div>
                </div>
                <div class="flex items-start">
                  <div class="w-8 h-8 bg-emerald-100 rounded-lg flex items-center justify-center mr-3 mt-0.5 flex-shrink-0">
                    <svg class="w-4 h-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                  </div>
                  <div>
                    <h4 class="font-semibold text-slate-900">Session Management</h4>
                    <p class="text-slate-600 text-sm">View and revoke active sessions, with automatic logout after periods of inactivity.</p>
                  </div>
                </div>
                <div class="flex items-start">
                  <div class="w-8 h-8 bg-emerald-100 rounded-lg flex items-center justify-center mr-3 mt-0.5 flex-shrink-0">
                    <svg class="w-4 h-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                  </div>
                  <div>
                    <h4 class="font-semibold text-slate-900">Brute-Force Protection</h4>
                    <p class="text-slate-600 text-sm">Progressive lockout after repeated failed login attempts protects against unauthorised access.</p>
                  </div>
                </div>
                <div class="flex items-start">
                  <div class="w-8 h-8 bg-emerald-100 rounded-lg flex items-center justify-center mr-3 mt-0.5 flex-shrink-0">
                    <svg class="w-4 h-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                  </div>
                  <div>
                    <h4 class="font-semibold text-slate-900">Password Security</h4>
                    <p class="text-slate-600 text-sm">Password breach checking and prevention of password reuse keeps your account secure.</p>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Data Protection & Encryption -->
          <div class="bg-white rounded-2xl shadow-lg border border-slate-200 overflow-hidden security-section">
            <div class="bg-gradient-to-r from-emerald-700 to-emerald-800 px-8 py-6">
              <div class="flex items-center">
                <div class="w-10 h-10 bg-white/20 rounded-lg flex items-center justify-center mr-4">
                  <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                  </svg>
                </div>
                <div>
                  <h2 class="text-xl font-bold text-white">Data Protection & Encryption</h2>
                  <p class="text-emerald-100 text-sm">How we protect your financial information</p>
                </div>
              </div>
            </div>
            <div class="p-8">
              <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="flex items-start">
                  <div class="w-8 h-8 bg-violet-100 rounded-lg flex items-center justify-center mr-3 mt-0.5 flex-shrink-0">
                    <svg class="w-4 h-4 text-violet-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                  </div>
                  <div>
                    <h4 class="font-semibold text-slate-900">Encryption at Rest</h4>
                    <p class="text-slate-600 text-sm">AES-256 encryption for sensitive financial fields including balances and account details.</p>
                  </div>
                </div>
                <div class="flex items-start">
                  <div class="w-8 h-8 bg-violet-100 rounded-lg flex items-center justify-center mr-3 mt-0.5 flex-shrink-0">
                    <svg class="w-4 h-4 text-violet-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                  </div>
                  <div>
                    <h4 class="font-semibold text-slate-900">Key Management</h4>
                    <p class="text-slate-600 text-sm">Centralised key management with regular key rotation policies to maintain security.</p>
                  </div>
                </div>
                <div class="flex items-start">
                  <div class="w-8 h-8 bg-violet-100 rounded-lg flex items-center justify-center mr-3 mt-0.5 flex-shrink-0">
                    <svg class="w-4 h-4 text-violet-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                  </div>
                  <div>
                    <h4 class="font-semibold text-slate-900">Encrypted Backups</h4>
                    <p class="text-slate-600 text-sm">All backups are encrypted with secure retention and deletion policies.</p>
                  </div>
                </div>
                <div class="flex items-start">
                  <div class="w-8 h-8 bg-violet-100 rounded-lg flex items-center justify-center mr-3 mt-0.5 flex-shrink-0">
                    <svg class="w-4 h-4 text-violet-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                  </div>
                  <div>
                    <h4 class="font-semibold text-slate-900">Secrets Management</h4>
                    <p class="text-slate-600 text-sm">Production secrets are stored in secure vault services, never in code or configuration files.</p>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Access Control -->
          <div class="bg-white rounded-2xl shadow-lg border border-slate-200 overflow-hidden security-section">
            <div class="bg-gradient-to-r from-blue-600 to-blue-700 px-8 py-6">
              <div class="flex items-center">
                <div class="w-10 h-10 bg-white/20 rounded-lg flex items-center justify-center mr-4">
                  <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                  </svg>
                </div>
                <div>
                  <h2 class="text-xl font-bold text-white">Access Control</h2>
                  <p class="text-violet-100 text-sm">Role-based permissions and least privilege access</p>
                </div>
              </div>
            </div>
            <div class="p-8">
              <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="flex items-start">
                  <div class="w-8 h-8 bg-violet-100 rounded-lg flex items-center justify-center mr-3 mt-0.5 flex-shrink-0">
                    <svg class="w-4 h-4 text-violet-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                  </div>
                  <div>
                    <h4 class="font-semibold text-slate-900">Role-Based Access Control</h4>
                    <p class="text-slate-600 text-sm">Distinct permission levels for different user types ensure appropriate access.</p>
                  </div>
                </div>
                <div class="flex items-start">
                  <div class="w-8 h-8 bg-violet-100 rounded-lg flex items-center justify-center mr-3 mt-0.5 flex-shrink-0">
                    <svg class="w-4 h-4 text-violet-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                  </div>
                  <div>
                    <h4 class="font-semibold text-slate-900">Principle of Least Privilege</h4>
                    <p class="text-slate-600 text-sm">Users and systems only have access to the minimum data required for their function.</p>
                  </div>
                </div>
                <div class="flex items-start">
                  <div class="w-8 h-8 bg-violet-100 rounded-lg flex items-center justify-center mr-3 mt-0.5 flex-shrink-0">
                    <svg class="w-4 h-4 text-violet-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                  </div>
                  <div>
                    <h4 class="font-semibold text-slate-900">Internal Access Logging</h4>
                    <p class="text-slate-600 text-sm">Every access to user data is logged and auditable for complete transparency.</p>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Audit & Monitoring -->
          <div class="bg-white rounded-2xl shadow-lg border border-slate-200 overflow-hidden security-section">
            <div class="bg-gradient-to-r from-blue-600 to-blue-600 px-8 py-6">
              <div class="flex items-center">
                <div class="w-10 h-10 bg-white/20 rounded-lg flex items-center justify-center mr-4">
                  <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                  </svg>
                </div>
                <div>
                  <h2 class="text-xl font-bold text-white">Auditability & Monitoring</h2>
                  <p class="text-violet-100 text-sm">Comprehensive logging and alerting</p>
                </div>
              </div>
            </div>
            <div class="p-8">
              <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="flex items-start">
                  <div class="w-8 h-8 bg-violet-100 rounded-lg flex items-center justify-center mr-3 mt-0.5 flex-shrink-0">
                    <svg class="w-4 h-4 text-violet-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                  </div>
                  <div>
                    <h4 class="font-semibold text-slate-900">Comprehensive Audit Logs</h4>
                    <p class="text-slate-600 text-sm">All login attempts, data access, and changes to financial plans are logged.</p>
                  </div>
                </div>
                <div class="flex items-start">
                  <div class="w-8 h-8 bg-violet-100 rounded-lg flex items-center justify-center mr-3 mt-0.5 flex-shrink-0">
                    <svg class="w-4 h-4 text-violet-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                  </div>
                  <div>
                    <h4 class="font-semibold text-slate-900">Immutable Logs</h4>
                    <p class="text-slate-600 text-sm">Append-only, tamper-resistant log storage ensures audit trail integrity.</p>
                  </div>
                </div>
                <div class="flex items-start">
                  <div class="w-8 h-8 bg-violet-100 rounded-lg flex items-center justify-center mr-3 mt-0.5 flex-shrink-0">
                    <svg class="w-4 h-4 text-violet-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                  </div>
                  <div>
                    <h4 class="font-semibold text-slate-900">Suspicious Activity Alerts</h4>
                    <p class="text-slate-600 text-sm">Automated alerts for unusual behaviour such as unexpected login locations.</p>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- GDPR & Privacy -->
          <div class="bg-white rounded-2xl shadow-lg border border-slate-200 overflow-hidden security-section">
            <div class="bg-gradient-to-r from-emerald-600 to-emerald-700 px-8 py-6">
              <div class="flex items-center">
                <div class="w-10 h-10 bg-white/20 rounded-lg flex items-center justify-center mr-4">
                  <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3" />
                  </svg>
                </div>
                <div>
                  <h2 class="text-xl font-bold text-white">GDPR & Privacy Compliance</h2>
                  <p class="text-emerald-100 text-sm">Your rights under UK/EU data protection law</p>
                </div>
              </div>
            </div>
            <div class="p-8">
              <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="flex items-start">
                  <div class="w-8 h-8 bg-emerald-100 rounded-lg flex items-center justify-center mr-3 mt-0.5 flex-shrink-0">
                    <svg class="w-4 h-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                  </div>
                  <div>
                    <h4 class="font-semibold text-slate-900">Right to Erasure</h4>
                    <p class="text-slate-600 text-sm">Full deletion workflow including removal from backups after retention period.</p>
                  </div>
                </div>
                <div class="flex items-start">
                  <div class="w-8 h-8 bg-emerald-100 rounded-lg flex items-center justify-center mr-3 mt-0.5 flex-shrink-0">
                    <svg class="w-4 h-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                  </div>
                  <div>
                    <h4 class="font-semibold text-slate-900">Data Minimisation</h4>
                    <p class="text-slate-600 text-sm">We only collect and store data that is necessary for your financial planning.</p>
                  </div>
                </div>
                <div class="flex items-start">
                  <div class="w-8 h-8 bg-emerald-100 rounded-lg flex items-center justify-center mr-3 mt-0.5 flex-shrink-0">
                    <svg class="w-4 h-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                  </div>
                  <div>
                    <h4 class="font-semibold text-slate-900">Consent Tracking</h4>
                    <p class="text-slate-600 text-sm">Timestamped consent records for terms, privacy policy, and any marketing preferences.</p>
                  </div>
                </div>
                <div class="flex items-start">
                  <div class="w-8 h-8 bg-emerald-100 rounded-lg flex items-center justify-center mr-3 mt-0.5 flex-shrink-0">
                    <svg class="w-4 h-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                  </div>
                  <div>
                    <h4 class="font-semibold text-slate-900">Data Export</h4>
                    <p class="text-slate-600 text-sm">Export all your data in a portable format at any time from your account settings.</p>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- API Security -->
          <div class="bg-white rounded-2xl shadow-lg border border-slate-200 overflow-hidden security-section">
            <div class="bg-gradient-to-r from-blue-700 to-blue-800 px-8 py-6">
              <div class="flex items-center">
                <div class="w-10 h-10 bg-white/20 rounded-lg flex items-center justify-center mr-4">
                  <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4" />
                  </svg>
                </div>
                <div>
                  <h2 class="text-xl font-bold text-white">API & Application Security</h2>
                  <p class="text-violet-200 text-sm">Protecting the technical infrastructure</p>
                </div>
              </div>
            </div>
            <div class="p-8">
              <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="flex items-start">
                  <div class="w-8 h-8 bg-violet-100 rounded-lg flex items-center justify-center mr-3 mt-0.5 flex-shrink-0">
                    <svg class="w-4 h-4 text-violet-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                  </div>
                  <div>
                    <h4 class="font-semibold text-slate-900">Rate Limiting</h4>
                    <p class="text-slate-600 text-sm">Per-user and per-token rate limits protect against abuse and ensure fair usage.</p>
                  </div>
                </div>
                <div class="flex items-start">
                  <div class="w-8 h-8 bg-violet-100 rounded-lg flex items-center justify-center mr-3 mt-0.5 flex-shrink-0">
                    <svg class="w-4 h-4 text-violet-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                  </div>
                  <div>
                    <h4 class="font-semibold text-slate-900">Token Security</h4>
                    <p class="text-slate-600 text-sm">Short-lived access tokens with automatic rotation maintain secure sessions.</p>
                  </div>
                </div>
                <div class="flex items-start">
                  <div class="w-8 h-8 bg-violet-100 rounded-lg flex items-center justify-center mr-3 mt-0.5 flex-shrink-0">
                    <svg class="w-4 h-4 text-violet-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                  </div>
                  <div>
                    <h4 class="font-semibold text-slate-900">Permission Scoping</h4>
                    <p class="text-slate-600 text-sm">Read-only vs write permissions ensure tokens only have necessary access.</p>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Business Continuity -->
          <div class="bg-white rounded-2xl shadow-lg border border-slate-200 overflow-hidden security-section">
            <div class="bg-gradient-to-r from-slate-600 to-slate-700 px-8 py-6">
              <div class="flex items-center">
                <div class="w-10 h-10 bg-white/20 rounded-lg flex items-center justify-center mr-4">
                  <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                  </svg>
                </div>
                <div>
                  <h2 class="text-xl font-bold text-white">Business Continuity</h2>
                  <p class="text-slate-300 text-sm">Ensuring your data is always available</p>
                </div>
              </div>
            </div>
            <div class="p-8">
              <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="flex items-start">
                  <div class="w-8 h-8 bg-slate-100 rounded-lg flex items-center justify-center mr-3 mt-0.5 flex-shrink-0">
                    <svg class="w-4 h-4 text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                  </div>
                  <div>
                    <h4 class="font-semibold text-slate-900">Disaster Recovery</h4>
                    <p class="text-slate-600 text-sm">Defined recovery objectives with regular testing ensure rapid restoration.</p>
                  </div>
                </div>
                <div class="flex items-start">
                  <div class="w-8 h-8 bg-slate-100 rounded-lg flex items-center justify-center mr-3 mt-0.5 flex-shrink-0">
                    <svg class="w-4 h-4 text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                  </div>
                  <div>
                    <h4 class="font-semibold text-slate-900">Uptime Monitoring</h4>
                    <p class="text-slate-600 text-sm">24/7 monitoring with automatic failover ensures continuous availability.</p>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Disclaimer -->
        <div class="mt-12 bg-slate-100 rounded-2xl p-8 border border-slate-200">
          <h3 class="text-lg font-bold text-slate-900 mb-4">Important Notice</h3>
          <p class="text-slate-600 mb-4">
            Fynla is a financial planning tool designed to help you organise and visualise your financial information. It does not constitute regulated financial advice. The projections and calculations provided are for illustrative purposes only and should not be relied upon as the sole basis for financial decisions.
          </p>
          <p class="text-slate-600">
            We recommend consulting with a qualified financial adviser for personalised advice tailored to your specific circumstances.
          </p>
        </div>

        <!-- Contact Section -->
        <div class="mt-8 text-center">
          <p class="text-slate-600 mb-4">Have questions about our security practices?</p>
          <a href="mailto:info@fynla.org" class="inline-flex items-center px-6 py-3 bg-emerald-600 text-white rounded-xl font-semibold hover:bg-emerald-700 transition-colors">
            <svg class="w-5 h-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
            </svg>
            Contact Us
          </a>
        </div>
      </div>
    </div>
  </PublicLayout>
</template>

<script>
import PublicLayout from '@/layouts/PublicLayout.vue';

export default {
  name: 'SecurityPage',

  components: {
    PublicLayout,
  },
};
</script>

<style scoped>
.security-section {
  animation: sectionFadeIn 0.6s ease-out both;
}

.security-section:nth-child(1) { animation-delay: 0s; }
.security-section:nth-child(2) { animation-delay: 0.1s; }
.security-section:nth-child(3) { animation-delay: 0.2s; }
.security-section:nth-child(4) { animation-delay: 0.3s; }
.security-section:nth-child(5) { animation-delay: 0.4s; }
.security-section:nth-child(6) { animation-delay: 0.5s; }

@keyframes sectionFadeIn {
  from {
    opacity: 0;
    transform: translateY(20px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}
</style>
