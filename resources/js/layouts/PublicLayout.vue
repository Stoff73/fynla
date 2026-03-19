<template>
  <div class="min-h-screen bg-eggshell-500">
    <!-- Navigation -->
    <nav aria-label="Main navigation" class="bg-white shadow-sm border-b border-light-gray sticky top-0 z-50">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
          <div class="flex items-center">
            <!-- Logo -->
            <router-link to="/" class="flex items-center">
              <img :src="logoUrl" alt="Fynla" width="124" height="56" class="h-14 w-auto" />
            </router-link>

            <!-- Navigation Links -->
            <div class="hidden md:ml-10 md:flex md:space-x-8">
              <router-link
                to="/"
                class="inline-flex items-center px-1 pt-1 text-base font-medium text-horizon-500 hover:text-raspberry-500 transition-colors"
              >
                Home
              </router-link>

              <!-- Features Mega Menu -->
              <div class="relative" @mouseenter="featuresOpen = true" @mouseleave="featuresOpen = false">
                <button
                  type="button"
                  class="inline-flex items-center gap-1 px-1 pt-1 text-base font-medium text-neutral-500 hover:text-raspberry-500 transition-colors"
                  :class="{ 'text-raspberry-500': featuresOpen }"
                >
                  Features
                  <svg class="w-4 h-4 transition-transform duration-200" :class="{ 'rotate-180': featuresOpen }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                  </svg>
                </button>
                <div v-if="featuresOpen" class="absolute left-1/2 -translate-x-1/2 top-full w-[720px] z-50 pt-2">
                  <div class="bg-white rounded-xl shadow-lg border border-light-gray p-6">
                    <p class="text-xs font-semibold text-neutral-400 uppercase tracking-wider mb-4">How Fyn can help you</p>
                    <div class="grid grid-cols-3 gap-3">
                      <a href="/#features" class="flex items-start gap-3 p-3 rounded-lg hover:bg-savannah-100 transition-colors group" @click="featuresOpen = false">
                        <div class="w-9 h-9 rounded-full bg-raspberry-500 flex-shrink-0 flex items-center justify-center">
                          <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" /></svg>
                        </div>
                        <div>
                          <p class="text-sm font-bold text-horizon-500 group-hover:text-raspberry-500 transition-colors">Protection</p>
                          <p class="text-xs text-neutral-500 mt-0.5 leading-relaxed">Analyse life insurance, critical illness, and income protection coverage gaps.</p>
                        </div>
                      </a>
                      <a href="/#features" class="flex items-start gap-3 p-3 rounded-lg hover:bg-savannah-100 transition-colors group" @click="featuresOpen = false">
                        <div class="w-9 h-9 rounded-full bg-spring-500 flex-shrink-0 flex items-center justify-center">
                          <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        </div>
                        <div>
                          <p class="text-sm font-bold text-horizon-500 group-hover:text-raspberry-500 transition-colors">Savings</p>
                          <p class="text-xs text-neutral-500 mt-0.5 leading-relaxed">Track emergency funds, ISA allowances, and savings goals across all your accounts.</p>
                        </div>
                      </a>
                      <a href="/#features" class="flex items-start gap-3 p-3 rounded-lg hover:bg-savannah-100 transition-colors group" @click="featuresOpen = false">
                        <div class="w-9 h-9 rounded-full bg-violet-500 flex-shrink-0 flex items-center justify-center">
                          <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" /></svg>
                        </div>
                        <div>
                          <p class="text-sm font-bold text-horizon-500 group-hover:text-raspberry-500 transition-colors">Investment</p>
                          <p class="text-xs text-neutral-500 mt-0.5 leading-relaxed">Portfolio analysis, risk profiling, and Monte Carlo projections to optimise your strategy.</p>
                        </div>
                      </a>
                      <a href="/#features" class="flex items-start gap-3 p-3 rounded-lg hover:bg-savannah-100 transition-colors group" @click="featuresOpen = false">
                        <div class="w-9 h-9 rounded-full bg-light-blue-500 flex-shrink-0 flex items-center justify-center">
                          <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        </div>
                        <div>
                          <p class="text-sm font-bold text-horizon-500 group-hover:text-raspberry-500 transition-colors">Retirement</p>
                          <p class="text-xs text-neutral-500 mt-0.5 leading-relaxed">Defined Contribution, Defined Benefit, and State Pension tracking with income projections.</p>
                        </div>
                      </a>
                      <a href="/#features" class="flex items-start gap-3 p-3 rounded-lg hover:bg-savannah-100 transition-colors group" @click="featuresOpen = false">
                        <div class="w-9 h-9 rounded-full bg-savannah-500 flex-shrink-0 flex items-center justify-center">
                          <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" /></svg>
                        </div>
                        <div>
                          <p class="text-sm font-bold text-horizon-500 group-hover:text-raspberry-500 transition-colors">Estate</p>
                          <p class="text-xs text-neutral-500 mt-0.5 leading-relaxed">Inheritance Tax calculations, gifting strategies, and estate value projections.</p>
                        </div>
                      </a>
                      <a href="/#features" class="flex items-start gap-3 p-3 rounded-lg hover:bg-savannah-100 transition-colors group" @click="featuresOpen = false">
                        <div class="w-9 h-9 rounded-full bg-horizon-400 flex-shrink-0 flex items-center justify-center">
                          <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" /></svg>
                        </div>
                        <div>
                          <p class="text-sm font-bold text-horizon-500 group-hover:text-raspberry-500 transition-colors">Net Worth</p>
                          <p class="text-xs text-neutral-500 mt-0.5 leading-relaxed">Complete balance sheet with properties, assets, and liabilities for a clear financial picture.</p>
                        </div>
                      </a>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Scenarios Mega Menu -->
              <div class="relative" @mouseenter="solutionsOpen = true" @mouseleave="solutionsOpen = false">
                <button
                  type="button"
                  class="inline-flex items-center gap-1 px-1 pt-1 text-base font-medium text-neutral-500 hover:text-raspberry-500 transition-colors"
                  :class="{ 'text-raspberry-500': solutionsOpen }"
                >
                  Scenarios
                  <svg class="w-4 h-4 transition-transform duration-200" :class="{ 'rotate-180': solutionsOpen }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                  </svg>
                </button>
                <div v-if="solutionsOpen" class="absolute left-1/2 -translate-x-1/2 top-full w-[720px] z-50 pt-2">
                  <div class="bg-white rounded-xl shadow-lg border border-light-gray p-6">
                    <p class="text-xs font-semibold text-neutral-400 uppercase tracking-wider mb-4">Choose your scenario</p>
                    <div class="grid grid-cols-3 gap-3">
                      <a href="/#solutions" class="flex items-start gap-3 p-3 rounded-lg hover:bg-savannah-100 transition-colors group" @click="solutionsOpen = false">
                        <div>
                          <p class="text-sm font-bold text-horizon-500 group-hover:text-raspberry-500 transition-colors">Starting out</p>
                          <p class="text-xs text-neutral-500 mt-0.5 leading-relaxed">Build strong financial habits for your future.</p>
                        </div>
                      </a>
                      <a href="/#solutions" class="flex items-start gap-3 p-3 rounded-lg hover:bg-savannah-100 transition-colors group" @click="solutionsOpen = false">
                        <div>
                          <p class="text-sm font-bold text-horizon-500 group-hover:text-raspberry-500 transition-colors">Building foundations</p>
                          <p class="text-xs text-neutral-500 mt-0.5 leading-relaxed">Save, invest, and grow your wealth with confidence.</p>
                        </div>
                      </a>
                      <a href="/#solutions" class="flex items-start gap-3 p-3 rounded-lg hover:bg-savannah-100 transition-colors group" @click="solutionsOpen = false">
                        <div>
                          <p class="text-sm font-bold text-horizon-500 group-hover:text-raspberry-500 transition-colors">Protecting what matters</p>
                          <p class="text-xs text-neutral-500 mt-0.5 leading-relaxed">Ensure your family and assets are fully covered.</p>
                        </div>
                      </a>
                      <a href="/#solutions" class="flex items-start gap-3 p-3 rounded-lg hover:bg-savannah-100 transition-colors group" @click="solutionsOpen = false">
                        <div>
                          <p class="text-sm font-bold text-horizon-500 group-hover:text-raspberry-500 transition-colors">Planning your future</p>
                          <p class="text-xs text-neutral-500 mt-0.5 leading-relaxed">Retirement projections, pension tracking, and estate planning.</p>
                        </div>
                      </a>
                      <a href="/#solutions" class="flex items-start gap-3 p-3 rounded-lg hover:bg-savannah-100 transition-colors group" @click="solutionsOpen = false">
                        <div>
                          <p class="text-sm font-bold text-horizon-500 group-hover:text-raspberry-500 transition-colors">Enjoying your wealth</p>
                          <p class="text-xs text-neutral-500 mt-0.5 leading-relaxed">Make the most of your financial success.</p>
                        </div>
                      </a>
                    </div>
                  </div>
                </div>
              </div>

              <router-link
                to="/pricing"
                class="inline-flex items-center px-1 pt-1 text-base font-medium text-neutral-500 hover:text-raspberry-500 transition-colors"
              >
                Pricing
              </router-link>

              <!-- Learning Centre Dropdown -->
              <div class="relative" @mouseenter="learningOpen = true" @mouseleave="learningOpen = false">
                <button
                  type="button"
                  class="inline-flex items-center gap-1 px-1 pt-1 text-base font-medium text-neutral-500 hover:text-raspberry-500 transition-colors"
                  :class="{ 'text-raspberry-500': learningOpen }"
                >
                  Learning centre
                  <svg class="w-4 h-4 transition-transform duration-200" :class="{ 'rotate-180': learningOpen }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                  </svg>
                </button>
                <div v-if="learningOpen" class="absolute right-0 top-full w-56 z-50 pt-2">
                  <div class="bg-white rounded-xl shadow-lg border border-light-gray py-2">
                    <router-link
                      to="/calculators"
                      class="flex items-center gap-3 px-4 py-2.5 text-sm text-neutral-500 hover:bg-savannah-100 hover:text-raspberry-500 transition-colors"
                      @click="learningOpen = false"
                    >
                      <svg class="w-4 h-4 text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 11h.01M12 11h.01M15 11h.01M4 7h16a1 1 0 011 1v9a1 1 0 01-1 1H4a1 1 0 01-1-1V8a1 1 0 011-1z" /></svg>
                      Calculators
                    </router-link>
                    <router-link
                      to="/learning-centre"
                      class="flex items-center gap-3 px-4 py-2.5 text-sm text-neutral-500 hover:bg-savannah-100 hover:text-raspberry-500 transition-colors"
                      @click="learningOpen = false"
                    >
                      <svg class="w-4 h-4 text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" /></svg>
                      Resources
                    </router-link>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Right side button - hidden on mobile -->
          <div class="hidden md:flex items-center">
            <router-link
              to="/login"
              class="min-w-[120px] px-5 py-2.5 bg-light-pink-100 text-horizon-500 text-sm font-semibold rounded-lg hover:bg-light-pink-400 hover:text-white transition-colors text-center"
            >
              Sign in
            </router-link>
          </div>

          <!-- Mobile menu button -->
          <div class="flex items-center md:hidden">
            <button
              @click="mobileMenuOpen = !mobileMenuOpen"
              :aria-expanded="mobileMenuOpen"
              :aria-label="mobileMenuOpen ? 'Close menu' : 'Open menu'"
              class="inline-flex items-center justify-center p-2 rounded-md text-horizon-400 hover:text-neutral-500 hover:bg-savannah-100"
            >
              <svg class="h-6 w-6" aria-hidden="true" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path v-if="!mobileMenuOpen" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>
        </div>
      </div>

      <!-- Mobile menu -->
      <div v-if="mobileMenuOpen" class="md:hidden border-t border-light-gray">
        <div class="pt-2 pb-3 space-y-1">
          <router-link
            to="/"
            class="block pl-3 pr-4 py-2 text-base font-medium text-horizon-500 hover:bg-savannah-100 hover:text-raspberry-500"
            @click="mobileMenuOpen = false"
          >
            Home
          </router-link>
          <!-- Mobile: Features accordion -->
          <div>
            <button
              type="button"
              :aria-expanded="featuresOpen"
              class="flex w-full items-center justify-between pl-3 pr-4 py-2 text-base font-medium text-horizon-500 hover:bg-savannah-100 hover:text-raspberry-500"
              @click="featuresOpen = !featuresOpen"
            >
              Features
              <svg class="w-4 h-4 transition-transform duration-200" :class="{ 'rotate-180': featuresOpen }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
              </svg>
            </button>
            <div v-if="featuresOpen" class="pl-6 pb-1 space-y-0.5">
              <a href="/#features" class="block py-1.5 text-sm text-neutral-500 hover:text-raspberry-500" @click="mobileMenuOpen = false; featuresOpen = false">Protection</a>
              <a href="/#features" class="block py-1.5 text-sm text-neutral-500 hover:text-raspberry-500" @click="mobileMenuOpen = false; featuresOpen = false">Savings</a>
              <a href="/#features" class="block py-1.5 text-sm text-neutral-500 hover:text-raspberry-500" @click="mobileMenuOpen = false; featuresOpen = false">Investment</a>
              <a href="/#features" class="block py-1.5 text-sm text-neutral-500 hover:text-raspberry-500" @click="mobileMenuOpen = false; featuresOpen = false">Retirement</a>
              <a href="/#features" class="block py-1.5 text-sm text-neutral-500 hover:text-raspberry-500" @click="mobileMenuOpen = false; featuresOpen = false">Estate</a>
              <a href="/#features" class="block py-1.5 text-sm text-neutral-500 hover:text-raspberry-500" @click="mobileMenuOpen = false; featuresOpen = false">Net Worth</a>
            </div>
          </div>

          <!-- Mobile: Scenarios accordion -->
          <div>
            <button
              type="button"
              :aria-expanded="solutionsOpen"
              class="flex w-full items-center justify-between pl-3 pr-4 py-2 text-base font-medium text-horizon-500 hover:bg-savannah-100 hover:text-raspberry-500"
              @click="solutionsOpen = !solutionsOpen"
            >
              Scenarios
              <svg class="w-4 h-4 transition-transform duration-200" :class="{ 'rotate-180': solutionsOpen }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
              </svg>
            </button>
            <div v-if="solutionsOpen" class="pl-6 pb-1 space-y-0.5">
              <a href="/#solutions" class="block py-1.5 text-sm text-neutral-500 hover:text-raspberry-500" @click="mobileMenuOpen = false; solutionsOpen = false">Starting out</a>
              <a href="/#solutions" class="block py-1.5 text-sm text-neutral-500 hover:text-raspberry-500" @click="mobileMenuOpen = false; solutionsOpen = false">Building foundations</a>
              <a href="/#solutions" class="block py-1.5 text-sm text-neutral-500 hover:text-raspberry-500" @click="mobileMenuOpen = false; solutionsOpen = false">Protecting what matters</a>
              <a href="/#solutions" class="block py-1.5 text-sm text-neutral-500 hover:text-raspberry-500" @click="mobileMenuOpen = false; solutionsOpen = false">Planning your future</a>
              <a href="/#solutions" class="block py-1.5 text-sm text-neutral-500 hover:text-raspberry-500" @click="mobileMenuOpen = false; solutionsOpen = false">Enjoying your wealth</a>
            </div>
          </div>

          <router-link
            to="/pricing"
            class="block pl-3 pr-4 py-2 text-base font-medium text-horizon-500 hover:bg-savannah-100 hover:text-raspberry-500"
            @click="mobileMenuOpen = false"
          >
            Pricing
          </router-link>

          <!-- Mobile: Learning Centre accordion -->
          <div>
            <button
              type="button"
              :aria-expanded="learningOpen"
              class="flex w-full items-center justify-between pl-3 pr-4 py-2 text-base font-medium text-horizon-500 hover:bg-savannah-100 hover:text-raspberry-500"
              @click="learningOpen = !learningOpen"
            >
              Learning centre
              <svg class="w-4 h-4 transition-transform duration-200" :class="{ 'rotate-180': learningOpen }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
              </svg>
            </button>
            <div v-if="learningOpen" class="pl-6 pb-1 space-y-0.5">
              <router-link to="/calculators" class="block py-1.5 text-sm text-neutral-500 hover:text-raspberry-500" @click="mobileMenuOpen = false; learningOpen = false">Calculators</router-link>
              <router-link to="/learning-centre" class="block py-1.5 text-sm text-neutral-500 hover:text-raspberry-500" @click="mobileMenuOpen = false; learningOpen = false">Resources</router-link>
            </div>
          </div>
          <router-link
            to="/login"
            class="block pl-3 pr-4 py-2 text-base font-medium text-horizon-500 hover:bg-savannah-100 hover:text-raspberry-500"
            @click="mobileMenuOpen = false"
          >
            Sign in
          </router-link>
        </div>
      </div>
    </nav>

    <!-- Main Content -->
    <main>
      <slot />
    </main>


    <!-- Footer -->
    <footer class="bg-gradient-to-r from-horizon-600 to-horizon-700 pt-28">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pb-12">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-7 gap-8">
          <!-- Company Info -->
          <div class="lg:col-span-2">
            <div class="-mt-3 mb-4">
              <img :src="footerLogoUrl" alt="Fynla" width="124" height="56" class="h-14 w-auto" />
            </div>
            <p class="text-sm text-white/70 leading-relaxed">
              Financial freedom mapping -<br />your financial companion for life<br />through one single finance platform
            </p>
          </div>

          <!-- About Fynla -->
          <div>
            <h3 class="text-sm font-bold text-white mb-4">About Fynla</h3>
            <ul class="space-y-2">
              <li><router-link to="/about" class="text-sm text-white/70 hover:text-white transition-colors">About us</router-link></li>
              <li><router-link to="/about" class="text-sm text-white/70 hover:text-white transition-colors">Accreditations</router-link></li>
              <li><a href="https://www.fca.org.uk" target="_blank" class="text-sm text-white/70 hover:text-white transition-colors">FCA website</a></li>
            </ul>
          </div>

          <!-- Help Centre -->
          <div>
            <h3 class="text-sm font-bold text-white mb-4">Help centre</h3>
            <ul class="space-y-2">
              <li><router-link to="/learning-centre" class="text-sm text-white/70 hover:text-white transition-colors">FAQs</router-link></li>
              <li><router-link to="/learning-centre" class="text-sm text-white/70 hover:text-white transition-colors">Ask Fyn</router-link></li>
              <li><router-link to="/learning-centre" class="text-sm text-white/70 hover:text-white transition-colors">Learning Centre</router-link></li>
            </ul>
          </div>

          <!-- Terms -->
          <div>
            <h3 class="text-sm font-bold text-white mb-4">Terms</h3>
            <ul class="space-y-2">
              <li><router-link to="/terms" class="text-sm text-white/70 hover:text-white transition-colors">Terms &amp; conditions</router-link></li>
              <li><router-link to="/privacy" class="text-sm text-white/70 hover:text-white transition-colors">Privacy policy</router-link></li>
            </ul>
          </div>

          <!-- Tools -->
          <div>
            <h3 class="text-sm font-bold text-white mb-4">Tools</h3>
            <ul class="space-y-2">
              <li><router-link to="/calculators" class="text-sm text-white/70 hover:text-white transition-colors">Calculators</router-link></li>
              <li><router-link to="/learning-centre" class="text-sm text-white/70 hover:text-white transition-colors">Resources</router-link></li>
              <li><a href="/?demo=true" class="text-sm text-white/70 hover:text-white transition-colors">View demo</a></li>
            </ul>
          </div>

          <!-- Advisors -->
          <div>
            <h3 class="text-sm font-bold text-white mb-4">Advisors</h3>
            <ul class="space-y-2">
              <li><router-link to="/advisors" class="text-sm text-white/70 hover:text-white transition-colors">Why Fynla Advisors</router-link></li>
              <li><router-link to="/advisors" class="text-sm text-white/70 hover:text-white transition-colors">Advisor features</router-link></li>
              <li><router-link to="/register" class="text-sm text-white/70 hover:text-white transition-colors">Advisor sign up</router-link></li>
            </ul>
          </div>
        </div>

        <div class="border-t border-white/20 mt-8 pt-8">
          <p class="text-sm text-white/70">
            &copy; Fynla 2026
          </p>
        </div>
      </div>
    </footer>
  </div>
</template>

<script>
export default {
  name: 'PublicLayout',

  data() {
    return {
      mobileMenuOpen: false,
      solutionsOpen: false,
      featuresOpen: false,
      learningOpen: false,
      logoUrl: '/images/logos/LogoHiResFynlaDark.png',
      footerLogoUrl: '/images/logos/LogoHiResFynlaLight.png',
    };
  },

  watch: {
    $route() {
      this.mobileMenuOpen = false;
    }
  }
};
</script>

<style scoped>
/* Only apply active styling to navigation links, not buttons */
nav .md\:space-x-8 .router-link-active {
  @apply text-raspberry-500;
}
</style>
