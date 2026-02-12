<template>
  <PublicLayout>
    <!-- Hero Section -->
    <div class="relative bg-gradient-to-br from-slate-900 via-blue-900 to-slate-900 overflow-hidden">
      <!-- Background Elements -->
      <div class="absolute inset-0">
        <div class="absolute inset-0 bg-gradient-to-br from-primary-900/80 to-slate-900/90"></div>
        <!-- Animated gradient orbs -->
        <div class="absolute top-1/4 left-1/4 w-96 h-96 bg-blue-500/20 rounded-full blur-3xl animate-pulse"></div>
        <div class="absolute bottom-1/4 right-1/4 w-96 h-96 bg-purple-500/20 rounded-full blur-3xl animate-pulse" style="animation-delay: 1s;"></div>
      </div>

      <!-- Grid Pattern Overlay -->
      <div class="absolute inset-0 bg-[url('data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNjAiIGhlaWdodD0iNjAiIHZpZXdCb3g9IjAgMCA2MCA2MCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48ZyBmaWxsPSJub25lIiBmaWxsLXJ1bGU9ImV2ZW5vZGQiPjxwYXRoIGQ9Ik0wIDBoNjB2NjBIMHoiLz48cGF0aCBkPSJNMzAgMzBtLTEgMGExIDEgMCAxIDAgMiAwYTEgMSAwIDEgMCAtMiAwIiBmaWxsPSJyZ2JhKDI1NSwyNTUsMjU1LDAuMSkiLz48L2c+PC9zdmc+')] opacity-30"></div>

      <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <div class="flex flex-col lg:flex-row lg:items-start lg:gap-12">
          <!-- Left: Hero Content -->
          <div class="lg:flex-1 text-center lg:text-left">
            <!-- Badge -->
            <button
              type="button"
              @click="enterPreviewMode"
              class="inline-flex items-center px-4 py-2 rounded-full bg-white/10 backdrop-blur-md border border-white/20 text-blue-200 text-sm font-medium mb-6 hover:bg-white/20 transition-all cursor-pointer"
            >
              <span class="w-2 h-2 bg-blue-400 rounded-full mr-2 animate-pulse"></span>
              Interactive Demo - No Sign-up Required
            </button>

            <h1 class="text-5xl md:text-7xl font-bold text-white mb-4 leading-tight">
              Your Complete<br />
              <span class="text-emerald-400">Financial Planning</span><br />
              Platform
            </h1>

            <p class="text-xl md:text-2xl text-slate-300 mb-6 max-w-2xl leading-relaxed">
              Comprehensive tools designed for UK individuals and families to plan protection, savings, investments, retirement, and estate with confidence.
            </p>

            <div class="flex flex-col sm:flex-row justify-center lg:justify-start gap-4 mb-6">
              <button
                type="button"
                @click="enterPreviewMode"
                :disabled="enteringPreview"
                class="group px-8 py-4 bg-blue-500 text-slate-900 rounded-xl font-semibold text-lg hover:bg-blue-400 transition-all shadow-lg hover:shadow-xl hover:-translate-y-0.5 disabled:opacity-50 disabled:cursor-wait"
              >
                <span class="flex items-center justify-center">
                  <svg v-if="enteringPreview" class="w-5 h-5 mr-2 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                  </svg>
                  <svg v-else class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                  </svg>
                  {{ enteringPreview ? 'Loading...' : 'Try the Demo' }}
                  <svg v-if="!enteringPreview" class="w-5 h-5 ml-2 group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                  </svg>
                </span>
              </button>
              <button
                type="button"
                @click="enterPreviewMode"
                :disabled="enteringPreview"
                class="group px-8 py-4 bg-white/10 backdrop-blur-md text-white rounded-xl font-semibold text-lg hover:bg-white/20 transition-all border border-white/20 disabled:opacity-50 disabled:cursor-wait"
              >
                <span class="flex items-center justify-center">
                  Get Started Free
                  <svg class="w-5 h-5 ml-2 group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                  </svg>
                </span>
              </button>
              <router-link
                to="/login"
                class="px-8 py-4 bg-transparent text-white rounded-xl font-semibold text-lg hover:bg-white/10 transition-all border border-white/20"
              >
                Sign In
              </router-link>
            </div>

            <!-- Wishlist Link -->
            <div class="mb-8 text-center lg:text-left">
              <a
                href="https://docs.google.com/forms/d/e/1FAIpQLSds1-zixuMDTjkBCZ3lEl-q5NzA0pwXyvb8cJIuNrz2fwjSXg/viewform?usp=publish-editor"
                target="_blank"
                rel="noopener noreferrer"
                class="inline-flex items-center gap-2 px-5 py-2.5 bg-white/10 backdrop-blur-md text-blue-200 rounded-full font-medium text-sm hover:bg-white/20 transition-all border border-white/20"
              >
                Join waitlist for priority access on release
              </a>
            </div>
          </div>

          <!-- Right: Stats cards stacked vertically -->
          <div class="flex flex-col gap-4 lg:w-72 lg:pt-16 text-center">
            <div
              @click="scrollToSection('planning-modules')"
              class="bg-white/10 backdrop-blur-md rounded-xl p-4 border border-white/20 cursor-pointer hover:bg-white/20 hover:border-white/30 transition-all"
            >
              <div class="text-3xl md:text-4xl font-bold text-white mb-1">5</div>
              <div class="text-slate-300 text-sm">Planning Modules</div>
            </div>
            <div
              @click="scrollToSection('key-features')"
              class="bg-white/10 backdrop-blur-md rounded-xl p-4 border border-white/20 cursor-pointer hover:bg-white/20 hover:border-white/30 transition-all"
            >
              <div class="text-3xl md:text-4xl font-bold text-white mb-1">UK</div>
              <div class="text-slate-300 text-sm">Tax Optimised</div>
            </div>
            <router-link
              to="/security"
              class="bg-white/10 backdrop-blur-md rounded-xl p-4 border border-white/20 cursor-pointer hover:bg-white/20 hover:border-white/30 transition-all block"
            >
              <div class="text-3xl md:text-4xl font-bold text-white mb-1">100%</div>
              <div class="text-slate-300 text-sm">Secure & Private</div>
            </router-link>
            <div
              @click="enterPreviewMode"
              class="bg-white/10 backdrop-blur-md rounded-xl p-4 border border-white/20 cursor-pointer hover:bg-white/20 hover:border-white/30 transition-all"
            >
              <div class="text-3xl md:text-4xl font-bold text-white mb-1">Free</div>
              <div class="text-slate-300 text-sm">Demo Access</div>
            </div>
          </div>
        </div>

        <!-- Scroll Indicator -->
        <div class="flex justify-center mt-8 animate-bounce">
          <svg class="w-6 h-6 text-white/50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3" />
          </svg>
        </div>
      </div>
    </div>

    <!-- Planning Modules Section -->
    <div id="planning-modules" class="relative bg-gradient-to-b from-slate-900 to-slate-800 py-24 overflow-hidden">
      <!-- Background blur elements -->
      <div class="absolute top-0 left-1/4 w-64 h-64 bg-blue-500/10 rounded-full blur-3xl"></div>
      <div class="absolute bottom-0 right-1/4 w-64 h-64 bg-purple-500/10 rounded-full blur-3xl"></div>

      <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-16">
          <span class="inline-block px-4 py-1 bg-white/10 backdrop-blur-md rounded-full text-primary-400 font-semibold text-sm uppercase tracking-wider border border-white/20 mb-4">Five Integrated Modules</span>
          <h2 class="text-4xl md:text-5xl font-bold text-white mt-3 mb-4">
            Complete Financial Planning
          </h2>
          <p class="text-xl text-slate-400 max-w-3xl mx-auto">
            All your financial planning needs in one integrated platform
          </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          <!-- Protection Module -->
          <div class="group bg-white/5 backdrop-blur-md rounded-2xl p-6 border border-white/10 hover:border-blue-500/50 hover:bg-white/10 transition-all">
            <div class="w-14 h-14 bg-blue-500/20 rounded-xl flex items-center justify-center mb-4">
              <svg class="w-7 h-7 text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
              </svg>
            </div>
            <h3 class="text-xl font-bold text-white mb-2">Protection</h3>
            <p class="text-slate-400 mb-4">Analyse life insurance, critical illness, and income protection coverage gaps.</p>
            <div class="flex flex-wrap gap-2">
              <span class="px-3 py-1 bg-white/10 text-slate-300 text-xs font-medium rounded-full">Life Cover</span>
              <span class="px-3 py-1 bg-white/10 text-slate-300 text-xs font-medium rounded-full">Income Protection</span>
              <span class="px-3 py-1 bg-white/10 text-slate-300 text-xs font-medium rounded-full">Adequacy Scoring</span>
            </div>
          </div>

          <!-- Savings Module -->
          <div class="group bg-white/5 backdrop-blur-md rounded-2xl p-6 border border-white/10 hover:border-emerald-500/50 hover:bg-white/10 transition-all">
            <div class="w-14 h-14 bg-emerald-500/20 rounded-xl flex items-center justify-center mb-4">
              <svg class="w-7 h-7 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
              </svg>
            </div>
            <h3 class="text-xl font-bold text-white mb-2">Savings</h3>
            <p class="text-slate-400 mb-4">Track emergency funds, ISA allowances, and savings goals across all accounts.</p>
            <div class="flex flex-wrap gap-2">
              <span class="px-3 py-1 bg-white/10 text-slate-300 text-xs font-medium rounded-full">Emergency Fund</span>
              <span class="px-3 py-1 bg-white/10 text-slate-300 text-xs font-medium rounded-full">ISA Tracking</span>
              <span class="px-3 py-1 bg-white/10 text-slate-300 text-xs font-medium rounded-full">Goal Planning</span>
            </div>
          </div>

          <!-- Investment Module -->
          <div class="group bg-white/5 backdrop-blur-md rounded-2xl p-6 border border-white/10 hover:border-blue-500/50 hover:bg-white/10 transition-all">
            <div class="w-14 h-14 bg-blue-500/20 rounded-xl flex items-center justify-center mb-4">
              <svg class="w-7 h-7 text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
              </svg>
            </div>
            <h3 class="text-xl font-bold text-white mb-2">Investment</h3>
            <p class="text-slate-400 mb-4">Portfolio analysis, risk profiling, and Monte Carlo retirement projections.</p>
            <div class="flex flex-wrap gap-2">
              <span class="px-3 py-1 bg-white/10 text-slate-300 text-xs font-medium rounded-full">Risk Profile</span>
              <span class="px-3 py-1 bg-white/10 text-slate-300 text-xs font-medium rounded-full">Portfolio View</span>
              <span class="px-3 py-1 bg-white/10 text-slate-300 text-xs font-medium rounded-full">Monte Carlo</span>
            </div>
          </div>

          <!-- Retirement Module -->
          <div class="group bg-white/5 backdrop-blur-md rounded-2xl p-6 border border-white/10 hover:border-purple-500/50 hover:bg-white/10 transition-all">
            <div class="w-14 h-14 bg-purple-500/20 rounded-xl flex items-center justify-center mb-4">
              <svg class="w-7 h-7 text-purple-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
            </div>
            <h3 class="text-xl font-bold text-white mb-2">Retirement</h3>
            <p class="text-slate-400 mb-4">DC, DB, and State Pension tracking with retirement income projections.</p>
            <div class="flex flex-wrap gap-2">
              <span class="px-3 py-1 bg-white/10 text-slate-300 text-xs font-medium rounded-full">DC Pensions</span>
              <span class="px-3 py-1 bg-white/10 text-slate-300 text-xs font-medium rounded-full">DB Pensions</span>
              <span class="px-3 py-1 bg-white/10 text-slate-300 text-xs font-medium rounded-full">State Pension</span>
            </div>
          </div>

          <!-- Estate Module -->
          <div class="group bg-white/5 backdrop-blur-md rounded-2xl p-6 border border-white/10 hover:border-rose-500/50 hover:bg-white/10 transition-all">
            <div class="w-14 h-14 bg-rose-500/20 rounded-xl flex items-center justify-center mb-4">
              <svg class="w-7 h-7 text-rose-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
              </svg>
            </div>
            <h3 class="text-xl font-bold text-white mb-2">Estate</h3>
            <p class="text-slate-400 mb-4">IHT calculations, gifting strategies, and estate value projections.</p>
            <div class="flex flex-wrap gap-2">
              <span class="px-3 py-1 bg-white/10 text-slate-300 text-xs font-medium rounded-full">IHT Calculator</span>
              <span class="px-3 py-1 bg-white/10 text-slate-300 text-xs font-medium rounded-full">Gifting</span>
              <span class="px-3 py-1 bg-white/10 text-slate-300 text-xs font-medium rounded-full">NRB/RNRB</span>
            </div>
          </div>

          <!-- Net Worth Module -->
          <div class="group bg-white/5 backdrop-blur-md rounded-2xl p-6 border border-white/10 hover:border-cyan-500/50 hover:bg-white/10 transition-all">
            <div class="w-14 h-14 bg-cyan-500/20 rounded-xl flex items-center justify-center mb-4">
              <svg class="w-7 h-7 text-cyan-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
              </svg>
            </div>
            <h3 class="text-xl font-bold text-white mb-2">Net Worth</h3>
            <p class="text-slate-400 mb-4">Complete balance sheet with properties, assets, and liabilities tracking.</p>
            <div class="flex flex-wrap gap-2">
              <span class="px-3 py-1 bg-white/10 text-slate-300 text-xs font-medium rounded-full">Properties</span>
              <span class="px-3 py-1 bg-white/10 text-slate-300 text-xs font-medium rounded-full">Liabilities</span>
              <span class="px-3 py-1 bg-white/10 text-slate-300 text-xs font-medium rounded-full">Balance Sheet</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Key Features Section -->
    <div id="key-features" class="relative bg-slate-800 py-24 overflow-hidden">
      <div class="absolute inset-0 bg-gradient-to-b from-slate-800 to-slate-900"></div>

      <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-16">
          <span class="inline-block px-4 py-1 bg-white/10 backdrop-blur-md rounded-full text-primary-400 font-semibold text-sm uppercase tracking-wider border border-white/20 mb-4">Key Features</span>
          <h2 class="text-4xl md:text-5xl font-bold text-white mt-3 mb-4">
            Built for UK Families
          </h2>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
          <div class="bg-white/5 backdrop-blur-md rounded-2xl p-8 border border-white/10 text-center">
            <div class="w-16 h-16 bg-primary-500/20 rounded-full flex items-center justify-center mx-auto mb-6">
              <svg class="w-8 h-8 text-primary-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
              </svg>
            </div>
            <h3 class="text-xl font-bold text-white mb-3">Spouse Linking</h3>
            <p class="text-slate-400">Joint assets and liabilities tracked together with automatic reciprocal records.</p>
          </div>

          <div class="bg-white/5 backdrop-blur-md rounded-2xl p-8 border border-white/10 text-center">
            <div class="w-16 h-16 bg-primary-500/20 rounded-full flex items-center justify-center mx-auto mb-6">
              <svg class="w-8 h-8 text-primary-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
              </svg>
            </div>
            <h3 class="text-xl font-bold text-white mb-3">UK Tax Optimised</h3>
            <p class="text-slate-400">2025/26 tax rates, ISA allowances, pension limits, and IHT thresholds built-in.</p>
          </div>

          <div class="bg-white/5 backdrop-blur-md rounded-2xl p-8 border border-white/10 text-center">
            <div class="w-16 h-16 bg-primary-500/20 rounded-full flex items-center justify-center mx-auto mb-6">
              <svg class="w-8 h-8 text-primary-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
              </svg>
            </div>
            <h3 class="text-xl font-bold text-white mb-3">Letter to Spouse</h3>
            <p class="text-slate-400">Essential information document with auto-populated financial data for loved ones.</p>
          </div>
        </div>
      </div>
    </div>

    <!-- CTA Section -->
    <div class="relative bg-gradient-to-br from-slate-900 via-blue-900 to-slate-900 py-24 overflow-hidden">
      <!-- Background Elements -->
      <div class="absolute inset-0">
        <div class="absolute inset-0 bg-gradient-to-br from-primary-900/50 to-slate-900/80"></div>
        <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[800px] h-[800px] bg-blue-500/10 rounded-full blur-3xl"></div>
      </div>

      <div class="relative max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <div class="bg-white/5 backdrop-blur-md rounded-3xl p-12 border border-white/10">
          <h2 class="text-4xl md:text-5xl font-bold text-white mb-6">
            Ready to Plan Your Financial Future?
          </h2>
          <p class="text-xl text-slate-300 mb-10 max-w-2xl mx-auto">
            Get started with Fynla today. Create your free account or try the interactive demo.
          </p>
          <div class="flex flex-col sm:flex-row justify-center gap-4">
            <button
              type="button"
              @click="enterPreviewMode"
              :disabled="enteringPreview"
              class="group px-8 py-4 bg-blue-500 text-slate-900 rounded-xl font-semibold text-lg hover:bg-blue-400 transition-all shadow-lg hover:shadow-xl disabled:opacity-50"
            >
              <span class="flex items-center justify-center">
                Try the Demo
                <svg class="w-5 h-5 ml-2 group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                </svg>
              </span>
            </button>
            <button
              type="button"
              @click="enterPreviewMode"
              :disabled="enteringPreview"
              class="group px-8 py-4 bg-white text-slate-900 rounded-xl font-semibold text-lg hover:bg-slate-100 transition-all shadow-lg hover:shadow-xl disabled:opacity-50 disabled:cursor-wait"
            >
              <span class="flex items-center justify-center">
                Get Started Free
                <svg class="w-5 h-5 ml-2 group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                </svg>
              </span>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Persona Selection Modal -->
    <PersonaSelectionModal
      :is-open="showSelectionModal"
      :personas="availablePersonas"
      :error="previewError"
      @close="cancelPreview"
      @select="handlePersonaSelect"
    />
  </PublicLayout>
</template>

<script>
import { mapActions, mapGetters } from 'vuex';
import { nextTick } from 'vue';
import PublicLayout from '@/layouts/PublicLayout.vue';
import PersonaSelectionModal from '@/components/Preview/PersonaSelectionModal.vue';

export default {
  name: 'LandingPage',

  components: {
    PublicLayout,
    PersonaSelectionModal,
  },

  data() {
    return {
      enteringPreview: false,
      showSelectionModal: false,
      previewError: '',
    };
  },

  computed: {
    ...mapGetters('preview', ['availablePersonas']),

  },

  mounted() {
    this.checkDemoParam();
  },

  watch: {
    '$route.query.demo': {
      handler(newVal) {
        if (newVal === 'true') {
          this.checkDemoParam();
        }
      },
    },
  },

  methods: {
    ...mapActions('preview', ['loadPersona']),

    checkDemoParam() {
      if (this.$route.query.demo === 'true') {
        this.showSelectionModal = true;
        // Clear the query parameter from URL
        this.$router.replace({ path: '/', query: {} });
      }
    },

    scrollToSection(sectionId) {
      const element = document.getElementById(sectionId);
      if (element) {
        element.scrollIntoView({ behavior: 'smooth' });
      }
    },

    enterPreviewMode() {
      this.previewError = '';
      this.showSelectionModal = true;
    },

    cancelPreview() {
      this.showSelectionModal = false;
      this.previewError = '';
    },

    async handlePersonaSelect(persona) {
      if (this.enteringPreview) return;
      this.enteringPreview = true;
      this.previewError = '';

      try {
        await this.$store.dispatch('preview/loadPersona', persona.id);
        await nextTick();
        this.$router.push('/dashboard');
      } catch (error) {
        console.error('Failed to enter preview mode:', error);
        this.previewError = 'Unable to load demo. Please try again or check your connection.';
        this.enteringPreview = false;
      }
    },
  },
};
</script>

