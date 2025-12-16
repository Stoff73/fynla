<template>
  <PublicLayout>
    <!-- Hero Section -->
    <div class="relative min-h-[90vh] flex items-center bg-gradient-to-br from-slate-900 via-blue-900 to-slate-900 overflow-hidden">
      <!-- Background Elements -->
      <div class="absolute inset-0 bg-slate-900">
        <div class="absolute inset-0 bg-gradient-to-br from-primary-900 to-slate-900 opacity-90"></div>
      </div>

      <!-- Grid Pattern Overlay -->
      <div class="absolute inset-0 bg-[url('data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNjAiIGhlaWdodD0iNjAiIHZpZXdCb3g9IjAgMCA2MCA2MCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48ZyBmaWxsPSJub25lIiBmaWxsLXJ1bGU9ImV2ZW5vZGQiPjxwYXRoIGQ9Ik0wIDBoNjB2NjBIMHoiLz48cGF0aCBkPSJNMzAgMzBtLTEgMGExIDEgMCAxIDAgMiAwYTEgMSAwIDEgMCAtMiAwIiBmaWxsPSJyZ2JhKDI1NSwyNTUsMjU1LDAuMSkiLz48L2c+PC9zdmc+')] opacity-40"></div>

      <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20">
        <div class="text-center">
          <!-- Badge -->
          <button
            type="button"
            @click="enterPreviewMode"
            class="inline-flex items-center px-4 py-2 rounded-full bg-amber-500/20 border border-amber-400/50 text-amber-200 text-sm font-medium mb-8 backdrop-blur-sm hover:bg-amber-500/30 transition-colors cursor-pointer"
          >
            <span class="w-2 h-2 bg-amber-400 rounded-full mr-2 animate-pulse"></span>
            Interactive Demo - No Sign-up Required
          </button>

          <h1 class="text-5xl md:text-7xl font-bold text-white mb-6 leading-tight">
            Your Complete <br class="sm:hidden" />
            <span class="hero-word-container">
              <transition name="hero-fade" mode="out-in">
                <span
                  :class="['hero-word', currentWordColor]"
                  :key="currentWord"
                >{{ currentWord }}</span>
              </transition>
            </span><span :class="['hero-word', currentWordColor]">Planning</span><br class="sm:hidden" />
            Platform
          </h1>

          <p class="text-xl md:text-2xl text-slate-300 mb-10 max-w-3xl mx-auto leading-relaxed">
            Comprehensive tools designed for UK individuals and families to plan protection, savings, investments, retirement, and estate with confidence.
          </p>

          <div class="flex flex-col sm:flex-row justify-center gap-4 mb-16">
            <button
              type="button"
              @click="enterPreviewMode"
              :disabled="enteringPreview"
              class="group px-8 py-4 bg-amber-500 text-slate-900 rounded-xl font-semibold text-lg hover:bg-amber-400 transition-all shadow-lg hover:shadow-xl hover:-translate-y-0.5 disabled:opacity-50 disabled:cursor-wait"
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
            <router-link
              to="/register"
              class="group px-8 py-4 bg-primary-600 text-white rounded-xl font-semibold text-lg hover:bg-primary-700 transition-all shadow-lg hover:shadow-xl hover:-translate-y-0.5"
            >
              <span class="flex items-center justify-center">
                Get Started Free
                <svg class="w-5 h-5 ml-2 group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                </svg>
              </span>
            </router-link>
            <router-link
              to="/login"
              class="px-8 py-4 bg-white/10 backdrop-blur-sm text-white rounded-xl font-semibold text-lg hover:bg-white/20 transition-all border border-white/20"
            >
              Sign In
            </router-link>
          </div>

          <!-- Stats -->
          <div class="grid grid-cols-2 md:grid-cols-4 gap-8 max-w-4xl mx-auto">
            <div class="text-center">
              <div class="text-3xl md:text-4xl font-bold text-white mb-1">5</div>
              <div class="text-slate-400 text-sm">Planning Modules</div>
            </div>
            <div class="text-center">
              <div class="text-3xl md:text-4xl font-bold text-white mb-1">UK</div>
              <div class="text-slate-400 text-sm">Tax Optimised</div>
            </div>
            <div class="text-center">
              <div class="text-3xl md:text-4xl font-bold text-white mb-1">100%</div>
              <div class="text-slate-400 text-sm">Secure & Private</div>
            </div>
            <div class="text-center">
              <div class="text-3xl md:text-4xl font-bold text-white mb-1">Free</div>
              <div class="text-slate-400 text-sm">Demo Access</div>
            </div>
          </div>
        </div>
      </div>

      <!-- Scroll Indicator -->
      <div class="absolute bottom-8 left-1/2 transform -translate-x-1/2 animate-bounce">
        <svg class="w-6 h-6 text-white/50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3" />
        </svg>
      </div>
    </div>

    <!-- What's Inside Section -->
    <div class="bg-slate-50 py-24">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-16">
          <span class="text-blue-600 font-semibold text-sm uppercase tracking-wider">Demo Features</span>
          <h2 class="text-4xl md:text-5xl font-bold text-slate-900 mt-3 mb-4">
            What's in the Demo
          </h2>
          <p class="text-xl text-slate-600 max-w-3xl mx-auto">
            A comprehensive testing environment showcasing core planning features and data integration
          </p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
          <!-- Feature Card 1 -->
          <!-- Feature Card 1 -->
          <div class="group bg-white rounded-2xl p-8 shadow-sm hover:shadow-md hover:-translate-y-0.5 transition-all duration-200 border border-secondary-200">
            <div class="flex items-start gap-5">
              <div class="flex-shrink-0 w-14 h-14 bg-primary-100 rounded-xl flex items-center justify-center">
                <svg class="w-7 h-7 text-primary-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                </svg>
              </div>
              <div class="flex-1">
              </div>
            </div>
          </div>

          <!-- Feature Card 2 -->
          <!-- Feature Card 2 -->
          <div class="group bg-white rounded-2xl p-8 shadow-sm hover:shadow-md hover:-translate-y-0.5 transition-all duration-200 border border-secondary-200">
            <div class="flex items-start gap-5">
              <div class="flex-shrink-0 w-14 h-14 bg-primary-100 rounded-xl flex items-center justify-center">
                <svg class="w-7 h-7 text-primary-700" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                </svg>
              </div>
              <div class="flex-1">
                <h3 class="text-xl font-bold text-slate-900 mb-2 transition-colors">Estate Planning</h3>
                <p class="text-slate-600 mb-4">
                  Comprehensive estate analysis with current and projected IHT liability calculations.
                </p>
                <div class="flex flex-wrap gap-2">
                  <span class="px-3 py-1 bg-slate-100 text-slate-600 text-xs font-medium rounded-full">Estate Value Overview</span>
                  <span class="px-3 py-1 bg-slate-100 text-slate-600 text-xs font-medium rounded-full">IHT Projection</span>
                  <span class="px-3 py-1 bg-slate-100 text-slate-600 text-xs font-medium rounded-full">Gifting Strategy</span>
                  <span class="px-3 py-1 bg-slate-100 text-slate-600 text-xs font-medium rounded-full">Life Policies</span>
                </div>
              </div>
            </div>
          </div>

          <!-- Feature Card 3 -->
          <!-- Feature Card 3 -->
          <div class="group bg-white rounded-2xl p-8 shadow-sm hover:shadow-md hover:-translate-y-0.5 transition-all duration-200 border border-secondary-200">
            <div class="flex items-start gap-5">
              <div class="flex-shrink-0 w-14 h-14 bg-primary-100 rounded-xl flex items-center justify-center">
                <svg class="w-7 h-7 text-primary-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                </svg>
              </div>
              <div class="flex-1">
                <h3 class="text-xl font-bold text-slate-900 mb-2 transition-colors">Protection Planning</h3>
                <p class="text-slate-600 mb-4">
                  Analyse your current protection coverage and identify any gaps in life and income protection.
                </p>
                <div class="flex flex-wrap gap-2">
                  <span class="px-3 py-1 bg-slate-100 text-slate-600 text-xs font-medium rounded-full">Coverage Overview</span>
                  <span class="px-3 py-1 bg-slate-100 text-slate-600 text-xs font-medium rounded-full">Life Insurance</span>
                  <span class="px-3 py-1 bg-slate-100 text-slate-600 text-xs font-medium rounded-full">Income Protection</span>
                  <span class="px-3 py-1 bg-slate-100 text-slate-600 text-xs font-medium rounded-full">Plan Generation</span>
                </div>
              </div>
            </div>
          </div>

          <!-- Feature Card 4 -->
          <!-- Feature Card 4 -->
          <div class="group bg-white rounded-2xl p-8 shadow-sm hover:shadow-md hover:-translate-y-0.5 transition-all duration-200 border border-secondary-200">
            <div class="flex items-start gap-5">
              <div class="flex-shrink-0 w-14 h-14 bg-primary-100 rounded-xl flex items-center justify-center">
                <svg class="w-7 h-7 text-primary-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
              </div>
              <div class="flex-1">
                <h3 class="text-xl font-bold text-slate-900 mb-2 transition-colors">Comprehensive Data Entry</h3>
                <p class="text-slate-600 mb-4">
                  Enter and manage all your financial assets, accounts, and property details.
                </p>
                <div class="flex flex-wrap gap-2">
                  <span class="px-3 py-1 bg-slate-100 text-slate-600 text-xs font-medium rounded-full">DC, DB & State Pensions</span>
                  <span class="px-3 py-1 bg-slate-100 text-slate-600 text-xs font-medium rounded-full">Investments</span>
                  <span class="px-3 py-1 bg-slate-100 text-slate-600 text-xs font-medium rounded-full">Bank Accounts</span>
                  <span class="px-3 py-1 bg-slate-100 text-slate-600 text-xs font-medium rounded-full">Property Portfolio</span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Additional Features Banner -->
        <div class="mt-12 bg-slate-900 rounded-2xl p-8 md:p-10">
          <h3 class="text-2xl font-bold text-white mb-8 text-center">Additional Features Available</h3>
          <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <div class="flex items-center gap-4">
              <div class="w-12 h-12 bg-slate-800 rounded-xl flex items-center justify-center flex-shrink-0">
                <svg class="w-6 h-6 text-primary-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                </svg>
              </div>
              <div>
                <h4 class="font-semibold text-white">Spouse Account Linking</h4>
                <p class="text-sm text-slate-400">Joint assets, liabilities & shared planning</p>
              </div>
            </div>

            <div class="flex items-center gap-4">
              <div class="w-12 h-12 bg-slate-800 rounded-xl flex items-center justify-center flex-shrink-0">
                <svg class="w-6 h-6 text-primary-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
              </div>
              <div>
                <h4 class="font-semibold text-white">Balance Sheet View</h4>
                <p class="text-sm text-slate-400">Joint & personal asset/liability breakdown</p>
              </div>
            </div>

            <div class="flex items-center gap-4">
              <div class="w-12 h-12 bg-slate-800 rounded-xl flex items-center justify-center flex-shrink-0">
                <svg class="w-6 h-6 text-primary-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                </svg>
              </div>
              <div>
                <h4 class="font-semibold text-white">Letter to Spouse</h4>
                <p class="text-sm text-slate-400">Essential information document generator</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Future Vision Section -->
    <div class="bg-white py-24">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-16">
          <span class="text-primary-600 font-semibold text-sm uppercase tracking-wider">The Vision</span>
          <h2 class="text-4xl md:text-5xl font-bold text-slate-900 mt-3 mb-4">
            What Fynla Will Become
          </h2>
          <p class="text-xl text-slate-600 max-w-3xl mx-auto">
            Full financial planning software ensuring you're covered for all financial planning scenarios
          </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          <!-- Module Cards -->
          <div class="group relative bg-white rounded-2xl p-6 border border-secondary-200 hover:border-primary-500 transition-all hover:shadow-md hover:-translate-y-1">
            <div class="absolute top-4 right-4 w-8 h-8 bg-primary-600 rounded-lg flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">
              <svg class="w-4 h-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" />
              </svg>
            </div>
            <div class="w-12 h-12 bg-primary-100 rounded-xl flex items-center justify-center mb-4">
              <svg class="w-6 h-6 text-primary-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
              </svg>
            </div>
            <h3 class="text-lg font-bold text-slate-900 mb-2">Protection Planning</h3>
            <p class="text-sm text-slate-600">Showing any gaps, issues and strategies to protect you and your family.</p>
          </div>

          <div class="group relative bg-white rounded-2xl p-6 border border-secondary-200 hover:border-primary-500 transition-all hover:shadow-md hover:-translate-y-1">
            <div class="absolute top-4 right-4 w-8 h-8 bg-primary-600 rounded-lg flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">
              <svg class="w-4 h-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" />
              </svg>
            </div>
            <div class="w-12 h-12 bg-primary-100 rounded-xl flex items-center justify-center mb-4">
              <svg class="w-6 h-6 text-primary-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
              </svg>
            </div>
            <h3 class="text-lg font-bold text-slate-900 mb-2">Investment Planning</h3>
            <p class="text-sm text-slate-600">All the relevant tools to optimise both tax and portfolio performance.</p>
          </div>

          <div class="group relative bg-white rounded-2xl p-6 border border-secondary-200 hover:border-primary-500 transition-all hover:shadow-md hover:-translate-y-1">
            <div class="absolute top-4 right-4 w-8 h-8 bg-primary-600 rounded-lg flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">
              <svg class="w-4 h-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" />
              </svg>
            </div>
            <div class="w-12 h-12 bg-primary-100 rounded-xl flex items-center justify-center mb-4">
              <svg class="w-6 h-6 text-primary-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
            </div>
            <h3 class="text-lg font-bold text-slate-900 mb-2">Retirement Planning</h3>
            <p class="text-sm text-slate-600">For accumulation and decumulation, ensuring tax optimal strategies for both.</p>
          </div>

          <div class="group relative bg-white rounded-2xl p-6 border border-secondary-200 hover:border-primary-500 transition-all hover:shadow-md hover:-translate-y-1">
            <div class="absolute top-4 right-4 w-8 h-8 bg-primary-600 rounded-lg flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">
              <svg class="w-4 h-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" />
              </svg>
            </div>
            <div class="w-12 h-12 bg-primary-100 rounded-xl flex items-center justify-center mb-4">
              <svg class="w-6 h-6 text-primary-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
              </svg>
            </div>
            <h3 class="text-lg font-bold text-slate-900 mb-2">Estate Planning</h3>
            <p class="text-sm text-slate-600">Linking all modules for tax-optimised generational wealth transfer.</p>
          </div>

          <div class="group relative bg-white rounded-2xl p-6 border border-secondary-200 hover:border-primary-500 transition-all hover:shadow-md hover:-translate-y-1">
            <div class="absolute top-4 right-4 w-8 h-8 bg-primary-600 rounded-lg flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">
              <svg class="w-4 h-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" />
              </svg>
            </div>
            <div class="w-12 h-12 bg-primary-100 rounded-xl flex items-center justify-center mb-4">
              <svg class="w-6 h-6 text-start-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" />
              </svg>
            </div>
            <h3 class="text-lg font-bold text-slate-900 mb-2">Document Repository</h3>
            <p class="text-sm text-slate-600">Secure storage for all your important financial and tax documents.</p>
          </div>

          <div class="group relative bg-white rounded-2xl p-6 border border-secondary-200 hover:border-primary-500 transition-all hover:shadow-md hover:-translate-y-1">
            <div class="absolute top-4 right-4 w-8 h-8 bg-primary-600 rounded-lg flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">
              <svg class="w-4 h-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" />
              </svg>
            </div>
            <div class="w-12 h-12 bg-primary-100 rounded-xl flex items-center justify-center mb-4">
              <svg class="w-6 h-6 text-primary-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
              </svg>
            </div>
            <h3 class="text-lg font-bold text-slate-900 mb-2">Full Account Linking</h3>
            <p class="text-sm text-slate-600">Spouse access to all important information in the event of illness or death.</p>
          </div>

          <div class="group relative bg-white rounded-2xl p-6 border border-secondary-200 hover:border-primary-500 transition-all hover:shadow-md hover:-translate-y-1">
            <div class="absolute top-4 right-4 w-8 h-8 bg-primary-600 rounded-lg flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">
              <svg class="w-4 h-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" />
              </svg>
            </div>
            <div class="w-12 h-12 bg-primary-100 rounded-xl flex items-center justify-center mb-4">
              <svg class="w-6 h-6 text-primary-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
              </svg>
            </div>
            <h3 class="text-lg font-bold text-slate-900 mb-2">Partial Account Linking</h3>
            <p class="text-sm text-slate-600">View-only access for advisers with death certificate verification.</p>
          </div>

          <div class="group relative bg-white rounded-2xl p-6 border border-secondary-200 hover:border-primary-500 transition-all hover:shadow-md hover:-translate-y-1">
            <div class="absolute top-4 right-4 w-8 h-8 bg-primary-600 rounded-lg flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">
              <svg class="w-4 h-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" />
              </svg>
            </div>
            <div class="w-12 h-12 bg-primary-100 rounded-xl flex items-center justify-center mb-4">
              <svg class="w-6 h-6 text-primary-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
              </svg>
            </div>
            <h3 class="text-lg font-bold text-slate-900 mb-2">Personal Accounts</h3>
            <p class="text-sm text-slate-600">Comprehensive tracking of all your personal financial accounts.</p>
          </div>

          <div class="group relative bg-white rounded-2xl p-6 border border-secondary-200 hover:border-primary-500 transition-all hover:shadow-md hover:-translate-y-1">
            <div class="absolute top-4 right-4 w-8 h-8 bg-primary-600 rounded-lg flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">
              <svg class="w-4 h-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" />
              </svg>
            </div>
            <div class="w-12 h-12 bg-primary-100 rounded-xl flex items-center justify-center mb-4">
              <svg class="w-6 h-6 text-primary-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
            </div>
            <h3 class="text-lg font-bold text-slate-900 mb-2">Cross-Border Planning</h3>
            <p class="text-sm text-slate-600">Full cross-border financial planning for international families.</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Stay Informed Section -->
    <div class="bg-slate-100 py-24">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-12">
          <span class="text-primary-600 font-semibold text-sm uppercase tracking-wider">Stay Informed</span>
          <h2 class="text-4xl md:text-5xl font-bold text-slate-900 mt-3 mb-4">
            Latest Financial News
          </h2>
          <p class="text-xl text-slate-600">
            Stay informed with the latest regulatory updates and financial news from trusted sources
          </p>
        </div>

        <!-- Source Tabs -->
        <div class="flex justify-center mb-8">
          <div class="inline-flex bg-white rounded-xl p-1 shadow-sm">
            <button
              v-for="source in newsSources"
              :key="source.id"
              @click="activeNewsSource = source.id"
              :class="[
                  'px-6 py-2 rounded-lg text-sm font-medium transition-all',
                activeNewsSource === source.id
                  ? 'bg-primary-600 text-white shadow-sm'
                  : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50'
              ]"
            >
              {{ source.name }}
            </button>
          </div>
        </div>

        <div v-if="loadingNews" class="text-center py-12">
          <div class="inline-flex items-center gap-3 px-6 py-3 bg-white rounded-full shadow-sm">
            <div class="w-5 h-5 border-2 border-blue-600 border-t-transparent rounded-full animate-spin"></div>
            <span class="text-slate-600">Loading news...</span>
          </div>
        </div>

        <div v-else-if="filteredNews.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
          <div
            v-for="(item, index) in filteredNews"
            :key="index"
            class="group bg-white rounded-2xl shadow-sm hover:shadow-xl transition-all duration-300 overflow-hidden"
          >
            <div class="p-6">
              <div class="flex items-center gap-2 mb-4">
                <span :class="getSourceBadgeClass(item.source)">
                  {{ item.source }}
                </span>
                <span class="text-slate-400 text-xs">{{ item.date }}</span>
              </div>
              <h3 class="text-lg font-bold text-slate-900 mb-3 group-hover:text-blue-600 transition-colors line-clamp-2">{{ item.title }}</h3>
              <p class="text-slate-600 mb-4 text-sm leading-relaxed line-clamp-3">{{ item.excerpt }}</p>
              <a :href="item.url" target="_blank" rel="noopener noreferrer" class="inline-flex items-center text-blue-600 hover:text-blue-700 text-sm font-medium group/link">
                Read more
                <svg class="w-4 h-4 ml-1 group-hover/link:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                </svg>
              </a>
            </div>
          </div>
        </div>

        <div v-else class="bg-white rounded-2xl shadow-sm p-10 text-center">
          <svg class="w-12 h-12 text-slate-300 mx-auto mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z" />
          </svg>
          <p class="text-slate-600 mb-4">Unable to load news at this time.</p>
          <div class="flex flex-wrap justify-center gap-4">
            <a href="https://www.fca.org.uk/news" target="_blank" rel="noopener noreferrer" class="inline-flex items-center text-blue-600 hover:text-blue-700 font-medium">
              Visit FCA News
              <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
              </svg>
            </a>
            <a href="https://www.gov.uk/government/news" target="_blank" rel="noopener noreferrer" class="inline-flex items-center text-slate-600 hover:text-slate-700 font-medium">
              Visit GOV.UK News
              <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
              </svg>
            </a>
            <a href="https://www.reuters.com/business/finance/" target="_blank" rel="noopener noreferrer" class="inline-flex items-center text-orange-600 hover:text-orange-700 font-medium">
              Visit Reuters Finance
              <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
              </svg>
            </a>
          </div>
        </div>
      </div>
    </div>

    <!-- CTA Section -->
    <div class="relative bg-gradient-to-br from-slate-900 via-blue-900 to-slate-900 py-24 overflow-hidden">
      <!-- Background Elements -->
      <div class="absolute inset-0 overflow-hidden bg-slate-900">
        <div class="absolute inset-0 bg-gradient-to-br from-primary-900 to-slate-900 opacity-90"></div>
      </div>

      <div class="relative max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h2 class="text-4xl md:text-5xl font-bold text-white mb-6">
          Ready to Plan Your Financial Future?
        </h2>
        <p class="text-xl text-slate-300 mb-10 max-w-2xl mx-auto">
          Get started with Fynla today. Create your free account and access all planning tools.
        </p>
        <div class="flex flex-col sm:flex-row justify-center gap-4">
          <router-link
            to="/register"
            class="group px-8 py-4 bg-white text-slate-900 rounded-xl font-semibold text-lg hover:bg-slate-100 transition-all shadow-lg hover:shadow-xl hover:scale-105"
          >
            <span class="flex items-center justify-center">
              Create Free Account
              <svg class="w-5 h-5 ml-2 group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" />
              </svg>
            </span>
          </router-link>
          <a
            href="https://docs.google.com/forms/d/e/1FAIpQLSeEotaP8CrnnhPYcuLdhl9fwIDT2V8GoduC0ytNtPcyD4FdSw/viewform"
            target="_blank"
            rel="noopener noreferrer"
            class="px-8 py-4 bg-transparent text-white rounded-xl font-semibold text-lg border-2 border-white/30 hover:bg-white/10 transition-all"
          >
            Give Feedback
          </a>
        </div>
      </div>
    </div>
    <!-- Persona Intro Modal -->
    <PersonaIntroModal
      :is-open="showIntroModal"
      :persona="selectedPersona"
      @close="cancelPreview"
      @explore="confirmPreview"
    />
  </PublicLayout>
</template>

<script>
import { mapActions } from 'vuex';
import { nextTick } from 'vue';
import PublicLayout from '@/layouts/PublicLayout.vue';
import PersonaIntroModal from '@/components/Preview/PersonaIntroModal.vue';

// Default persona metadata for the intro modal
const DEFAULT_PERSONA = {
  id: 'young_family',
  name: 'Emily & James Carter',
  tagline: 'Young family building their future',
  netWorthRange: '£80k - £120k',
  focus: 'Protection gaps, emergency fund',
  description: 'A young married couple in their early 30s with two children, mortgage, and workplace pensions.',
};

export default {
  name: 'LandingPage',

  components: {
    PublicLayout,
    PersonaIntroModal,
  },

  data() {
    return {
      loadingNews: true,
      activeNewsSource: 'all',
      enteringPreview: false,
      showIntroModal: false,
      selectedPersona: null,
      // Rotating hero words with colors
      heroWords: [
        { word: 'Financial', color: 'text-primary-400' },
        { word: 'Retirement', color: 'text-emerald-400' },
        { word: 'Investment', color: 'text-amber-400' },
        { word: 'Estate', color: 'text-rose-400' },
      ],
      currentWordIndex: 0,
      heroWordInterval: null,
      newsSources: [
        { id: 'all', name: 'All Sources' },
        { id: 'fca', name: 'FCA' },
        { id: 'govuk', name: 'GOV.UK' },
        { id: 'reuters', name: 'Reuters' },
      ],
      newsItems: [],
    };
  },

  computed: {
    currentWord() {
      return this.heroWords[this.currentWordIndex].word;
    },
    currentWordColor() {
      return this.heroWords[this.currentWordIndex].color;
    },
    filteredNews() {
      if (this.activeNewsSource === 'all') {
        return this.newsItems.slice(0, 6);
      }
      return this.newsItems.filter(item => item.sourceId === this.activeNewsSource).slice(0, 6);
    },
  },

  mounted() {
    this.loadNews();
    this.startHeroWordRotation();
  },

  beforeUnmount() {
    // Clean up interval to prevent memory leaks
    if (this.heroWordInterval) {
      clearInterval(this.heroWordInterval);
    }
  },

  methods: {
    ...mapActions('preview', ['loadPersona']),

    startHeroWordRotation() {
      // Rotate words every 3 seconds
      this.heroWordInterval = setInterval(() => {
        this.currentWordIndex = (this.currentWordIndex + 1) % this.heroWords.length;
      }, 3000);
    },

    enterPreviewMode() {
      // Show the intro modal instead of directly navigating
      this.selectedPersona = DEFAULT_PERSONA;
      this.showIntroModal = true;
    },

    cancelPreview() {
      // User clicked Cancel on the modal
      this.showIntroModal = false;
      this.selectedPersona = null;
    },

    async confirmPreview() {
      // User clicked "Explore Dashboard" on the modal
      console.log('[LandingPage] confirmPreview called');
      if (this.enteringPreview) {
        console.log('[LandingPage] Already entering preview, returning');
        return;
      }

      this.enteringPreview = true;
      this.showIntroModal = false;

      try {
        console.log('[LandingPage] About to call loadPersona...');
        await this.$store.dispatch('preview/loadPersona', 'young_family');
        console.log('[LandingPage] loadPersona completed');

        // Verify auth state is set correctly
        const isAuthenticated = this.$store.getters['auth/isAuthenticated'];
        const isPreviewMode = this.$store.getters['preview/isPreviewMode'];
        console.log('[LandingPage] isAuthenticated:', isAuthenticated);
        console.log('[LandingPage] isPreviewMode:', isPreviewMode);

        if (!isAuthenticated && !isPreviewMode) {
          throw new Error('Authentication failed - state not set correctly');
        }

        // Wait for Vue to process store updates before navigating
        await nextTick();

        console.log('[LandingPage] Navigating to /dashboard...');
        this.$router.push('/dashboard');
      } catch (error) {
        console.error('[LandingPage] Failed to enter preview mode:', error);
        alert('Failed to load demo data: ' + error.message);
      } finally {
        this.enteringPreview = false;
        console.log('[LandingPage] confirmPreview finished');
      }
    },

    loadNews() {
      // Curated news from multiple sources - in production, this could fetch from APIs/RSS feeds
      setTimeout(() => {
        this.newsItems = [
          // FCA News
          {
            sourceId: 'fca',
            source: 'FCA',
            date: 'Nov 2025',
            title: 'FCA warns consumers about pension scams',
            excerpt: 'New guidance on identifying and avoiding pension transfer scams targeting UK savers. Learn the warning signs.',
            url: 'https://www.fca.org.uk/news'
          },
          {
            sourceId: 'fca',
            source: 'FCA',
            date: 'Nov 2025',
            title: 'Updated Consumer Duty guidance',
            excerpt: 'Financial firms must ensure products and services deliver good outcomes for retail customers.',
            url: 'https://www.fca.org.uk/news'
          },
          // GOV.UK News
          {
            sourceId: 'govuk',
            source: 'GOV.UK',
            date: 'Nov 2025',
            title: 'Autumn Budget 2025: Key financial announcements',
            excerpt: 'Summary of key tax and financial policy changes announced in the Autumn Budget affecting personal finances.',
            url: 'https://www.gov.uk/government/news'
          },
          {
            sourceId: 'govuk',
            source: 'GOV.UK',
            date: 'Nov 2025',
            title: 'State Pension changes for 2025/26',
            excerpt: 'Details on the new State Pension rates and triple lock guarantee for the upcoming tax year.',
            url: 'https://www.gov.uk/government/news'
          },
          // Reuters Finance
          {
            sourceId: 'reuters',
            source: 'Reuters',
            date: 'Nov 2025',
            title: 'Bank of England holds interest rates steady',
            excerpt: 'The BoE maintained rates amid mixed economic signals, with focus on inflation trajectory.',
            url: 'https://www.reuters.com/business/finance/'
          },
          {
            sourceId: 'reuters',
            source: 'Reuters',
            date: 'Nov 2025',
            title: 'UK mortgage rates continue to ease',
            excerpt: 'Average mortgage rates decline as lenders compete for business in stabilising market conditions.',
            url: 'https://www.reuters.com/business/finance/'
          },
        ];
        this.loadingNews = false;
      }, 1000);
    },

    getSourceBadgeClass(source) {
      const baseClasses = 'inline-flex items-center px-3 py-1 text-xs font-semibold rounded-full';
      switch (source) {
        case 'FCA':
          return `${baseClasses} bg-blue-100 text-blue-700`;
        case 'GOV.UK':
          return `${baseClasses} bg-slate-100 text-slate-700`;
        case 'Reuters':
          return `${baseClasses} bg-orange-100 text-orange-700`;
        default:
          return `${baseClasses} bg-gray-100 text-gray-700`;
      }
    },
  },
};
</script>

<style scoped>
/* Hero word animation */
.hero-word {
  display: inline-block;
  transition: color 0.5s ease-in-out;
}

/* Fixed width container to prevent layout shift */
.hero-word-container {
  display: inline-block;
  min-width: 5.5em; /* Width based on "Investment" - longest word */
  text-align: left;
  margin-right: 0.2em; /* Space before "Planning" */
}

/* Vue transition for rotating words */
.hero-fade-enter-active,
.hero-fade-leave-active {
  transition: all 0.4s ease;
}

.hero-fade-enter-from {
  opacity: 0;
  transform: translateY(20px);
}

.hero-fade-leave-to {
  opacity: 0;
  transform: translateY(-20px);
}

@keyframes blob {
  0% {
    transform: translate(0px, 0px) scale(1);
  }
  33% {
    transform: translate(30px, -50px) scale(1.1);
  }
  66% {
    transform: translate(-20px, 20px) scale(0.9);
  }
  100% {
    transform: translate(0px, 0px) scale(1);
  }
}

.animate-blob {
  animation: blob 7s infinite;
}

.animation-delay-2000 {
  animation-delay: 2s;
}

.animation-delay-4000 {
  animation-delay: 4s;
}

/* Line clamp utilities for text truncation */
.line-clamp-2 {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.line-clamp-3 {
  display: -webkit-box;
  -webkit-line-clamp: 3;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
</style>
