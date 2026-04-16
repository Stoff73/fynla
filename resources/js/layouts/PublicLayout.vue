<template>
  <div class="min-h-screen bg-eggshell-500">
    <!-- Navigation -->
    <nav class="bg-white shadow-sm border-b border-light-gray sticky top-0 z-50">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative">
        <div class="flex justify-between h-16">
          <div class="flex items-center">
            <!-- Logo -->
            <router-link to="/" class="flex items-center">
              <img :src="logoUrl" alt="Fynla" class="h-14 w-auto" />
            </router-link>

            <!-- Desktop Navigation -->
            <div class="hidden lg:ml-8 lg:flex lg:space-x-6">
              <router-link
                to="/"
                class="inline-flex items-center px-1 pt-1 text-sm font-medium text-neutral-500 hover:text-raspberry-500 transition-colors"
              >
                Home
              </router-link>

              <!-- How it works dropdown trigger -->
              <div class="relative" @mouseenter="howOpen = true" @mouseleave="howOpen = false">
                <button
                  type="button"
                  class="inline-flex items-center gap-1 px-1 pt-1 text-sm font-medium text-neutral-500 hover:text-raspberry-500 transition-colors"
                  :class="{ 'text-raspberry-500': howOpen || isHowActive }"
                >
                  How it works
                  <svg class="w-3.5 h-3.5 transition-transform duration-200" :class="{ 'rotate-180': howOpen }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                  </svg>
                </button>
                <!-- How it works mega menu panel -->
                <div v-if="howOpen" class="mega-menu-panel absolute top-full z-50 pt-2 w-[min(960px,calc(100vw-2rem))]">
                  <div class="bg-white rounded-xl shadow-lg border border-light-gray p-6">
                    <div class="flex gap-8">
                      <!-- Left column: Discover Fynla -->
                      <div class="w-[260px] flex-shrink-0">
                        <p class="text-xs font-semibold text-neutral-600 uppercase tracking-wider mb-4">Discover Fynla</p>
                        <div class="space-y-2">
                          <router-link to="/how-it-works" class="flex items-start gap-3 p-3 rounded-lg bg-eggshell-500 hover:bg-light-pink-100 transition-colors group" @click="howOpen = false">
                            <svg class="w-5 h-5 text-horizon-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
                            <div>
                              <p class="text-base font-bold text-horizon-500 group-hover:text-raspberry-500 transition-colors">Getting<br/>started</p>
                              <p class="text-xs text-neutral-500 mt-0.5 leading-relaxed">See how Fynla works and what you can do.</p>
                            </div>
                          </router-link>
                          <router-link to="/features" class="flex items-start gap-3 p-3 rounded-lg bg-eggshell-500 hover:bg-light-pink-100 transition-colors group" @click="howOpen = false">
                            <svg class="w-5 h-5 text-horizon-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" /></svg>
                            <div>
                              <p class="text-base font-bold text-horizon-500 group-hover:text-raspberry-500 transition-colors">Fynla<br/>Features</p>
                              <p class="text-xs text-neutral-500 mt-0.5 leading-relaxed">Compare plans and see what's included.</p>
                            </div>
                          </router-link>
                        </div>
                      </div>
                      <!-- Divider -->
                      <div class="w-px bg-neutral-300"></div>
                      <!-- Right column: Your personal journey -->
                      <div class="flex-1">
                        <p class="text-xs font-semibold text-neutral-600 uppercase tracking-wider mb-4">Your personal journey</p>
                        <div class="grid grid-cols-3 gap-3">
                          <router-link
                            v-for="stage in stages"
                            :key="stage.slug"
                            :to="`/stage/${stage.slug}`"
                            class="flex items-start gap-3 p-3 rounded-lg bg-eggshell-500 hover:bg-light-pink-100 transition-colors group"
                            @click="howOpen = false"
                          >
                            <svg class="w-5 h-5 text-horizon-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="stage.icon" /></svg>
                            <div>
                              <!-- SECURITY: v-html safe here — stage data is hardcoded in this component (menuName uses <br/> for line breaks), never from user input or API -->
                              <p class="text-base font-bold text-horizon-500 group-hover:text-raspberry-500 transition-colors" v-html="stage.menuName || stage.name"></p>
                              <p class="text-xs text-neutral-500 mt-0.5 leading-relaxed">{{ stage.sub }}</p>
                            </div>
                          </router-link>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Resources dropdown (mega menu) -->
              <div class="relative" @mouseenter="resourcesOpen = true" @mouseleave="resourcesOpen = false">
                <button
                  type="button"
                  class="inline-flex items-center gap-1 px-1 pt-1 text-sm font-medium text-neutral-500 hover:text-raspberry-500 transition-colors"
                  :class="{ 'text-raspberry-500': resourcesOpen || isResourcesActive }"
                >
                  Resources
                  <svg class="w-3.5 h-3.5 transition-transform duration-200" :class="{ 'rotate-180': resourcesOpen }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                  </svg>
                </button>
                <div v-if="resourcesOpen" class="mega-menu-panel absolute top-full w-[min(960px,calc(100vw-2rem))] z-50 pt-2">
                  <div class="bg-white rounded-xl shadow-lg border border-light-gray p-6">
                    <div class="grid grid-cols-3 gap-3">
                      <router-link to="/calculators" class="flex items-start gap-3 p-3 rounded-lg bg-eggshell-500 hover:bg-light-pink-100 transition-colors group" @click="resourcesOpen = false">
                        <svg class="w-5 h-5 text-horizon-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 11h.01M12 11h.01M15 11h.01M4 7h16a1 1 0 011 1v9a1 1 0 01-1 1H4a1 1 0 01-1-1V8a1 1 0 011-1z" /></svg>
                        <div>
                          <p class="text-base font-bold text-horizon-500 group-hover:text-raspberry-500 transition-colors">Free calculators</p>
                          <p class="text-xs text-neutral-500 mt-0.5 leading-relaxed">Free financial calculators for retirement, tax, and more.</p>
                        </div>
                      </router-link>
                      <router-link to="/learn" class="flex items-start gap-3 p-3 rounded-lg bg-eggshell-500 hover:bg-light-pink-100 transition-colors group" @click="resourcesOpen = false">
                        <svg class="w-5 h-5 text-horizon-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" /></svg>
                        <div>
                          <p class="text-base font-bold text-horizon-500 group-hover:text-raspberry-500 transition-colors">Guides &amp; Explainers</p>
                          <p class="text-xs text-neutral-500 mt-0.5 leading-relaxed">Plain-English guides to pensions, ISAs, tax, and more.</p>
                        </div>
                      </router-link>
                      <router-link to="/learn/glossary" class="flex items-start gap-3 p-3 rounded-lg bg-eggshell-500 hover:bg-light-pink-100 transition-colors group" @click="resourcesOpen = false">
                        <svg class="w-5 h-5 text-horizon-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5h12M9 3v2m1.048 9.5A18.022 18.022 0 016.412 9m6.088 9h7M11 21l5-10 5 10M12.751 5C11.783 10.77 8.07 15.61 3 18.129" /></svg>
                        <div>
                          <p class="text-base font-bold text-horizon-500 group-hover:text-raspberry-500 transition-colors">Glossary A-Z</p>
                          <p class="text-xs text-neutral-500 mt-0.5 leading-relaxed">Look up financial terms in plain English.</p>
                        </div>
                      </router-link>
                      <router-link to="/insights" class="flex items-start gap-3 p-3 rounded-lg bg-eggshell-500 hover:bg-light-pink-100 transition-colors group" @click="resourcesOpen = false">
                        <svg class="w-5 h-5 text-horizon-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z" /></svg>
                        <div>
                          <p class="text-base font-bold text-horizon-500 group-hover:text-raspberry-500 transition-colors">Latest Insights</p>
                          <p class="text-xs text-neutral-500 mt-0.5 leading-relaxed">Timely articles on tax changes and financial news.</p>
                        </div>
                      </router-link>
                      <router-link to="/faq" class="flex items-start gap-3 p-3 rounded-lg bg-eggshell-500 hover:bg-light-pink-100 transition-colors group" @click="resourcesOpen = false">
                        <svg class="w-5 h-5 text-horizon-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        <div>
                          <p class="text-base font-bold text-horizon-500 group-hover:text-raspberry-500 transition-colors">FAQ</p>
                          <p class="text-xs text-neutral-500 mt-0.5 leading-relaxed">Answers to common questions about Fynla.</p>
                        </div>
                      </router-link>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Why Fynla dropdown (mega menu) -->
              <div class="relative" @mouseenter="whyOpen = true" @mouseleave="whyOpen = false">
                <button
                  type="button"
                  class="inline-flex items-center gap-1 px-1 pt-1 text-sm font-medium text-neutral-500 hover:text-raspberry-500 transition-colors"
                  :class="{ 'text-raspberry-500': whyOpen || isWhyActive }"
                >
                  Why Fynla
                  <svg class="w-3.5 h-3.5 transition-transform duration-200" :class="{ 'rotate-180': whyOpen }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                  </svg>
                </button>
                <div v-if="whyOpen" class="mega-menu-panel absolute top-full w-[min(960px,calc(100vw-2rem))] z-50 pt-2">
                  <div class="bg-white rounded-xl shadow-lg border border-light-gray p-6">
                    <div class="grid grid-cols-3 gap-3">
                      <router-link to="/why-fynla/our-approach" class="flex items-start gap-3 p-3 rounded-lg bg-eggshell-500 hover:bg-light-pink-100 transition-colors group" @click="whyOpen = false">
                        <svg class="w-5 h-5 text-horizon-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" /></svg>
                        <div>
                          <p class="text-base font-bold text-horizon-500 group-hover:text-raspberry-500 transition-colors">Our Approach</p>
                          <p class="text-xs text-neutral-500 mt-0.5 leading-relaxed">How we think about financial planning differently.</p>
                        </div>
                      </router-link>
                      <router-link to="/why-fynla/one-platform" class="flex items-start gap-3 p-3 rounded-lg bg-eggshell-500 hover:bg-light-pink-100 transition-colors group" @click="whyOpen = false">
                        <svg class="w-5 h-5 text-horizon-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" /></svg>
                        <div>
                          <p class="text-base font-bold text-horizon-500 group-hover:text-raspberry-500 transition-colors">One platform</p>
                          <p class="text-xs text-neutral-500 mt-0.5 leading-relaxed">Everything in one place — no spreadsheets needed.</p>
                        </div>
                      </router-link>
                      <router-link to="/why-fynla/independent" class="flex items-start gap-3 p-3 rounded-lg bg-eggshell-500 hover:bg-light-pink-100 transition-colors group" @click="whyOpen = false">
                        <svg class="w-5 h-5 text-horizon-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" /></svg>
                        <div>
                          <p class="text-base font-bold text-horizon-500 group-hover:text-raspberry-500 transition-colors">Your financial companion</p>
                          <p class="text-xs text-neutral-500 mt-0.5 leading-relaxed">Not tied to an adviser — independent, unbiased, and always on your side.</p>
                        </div>
                      </router-link>
                      <router-link to="/security" class="flex items-start gap-3 p-3 rounded-lg bg-eggshell-500 hover:bg-light-pink-100 transition-colors group" @click="whyOpen = false">
                        <svg class="w-5 h-5 text-horizon-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>
                        <div>
                          <p class="text-base font-bold text-horizon-500 group-hover:text-raspberry-500 transition-colors">Security &amp; Privacy</p>
                          <p class="text-xs text-neutral-500 mt-0.5 leading-relaxed">Bank-grade encryption and data protection.</p>
                        </div>
                      </router-link>
                    </div>
                  </div>
                </div>
              </div>

              <router-link
                to="/pricing"
                class="inline-flex items-center px-1 pt-1 text-sm font-medium text-neutral-500 hover:text-raspberry-500 transition-colors"
              >
                Pricing
              </router-link>
            </div>
          </div>

          <!-- Right side - Sign in / User menu -->
          <div class="hidden lg:flex items-center">
            <!-- Signed-in user dropdown -->
            <div v-if="isAuthenticated" class="relative" data-dropdown="public-user">
              <button
                type="button"
                @click="userDropdownOpen = !userDropdownOpen"
                class="inline-flex items-center px-3 py-2 border border-transparent text-sm font-medium rounded-lg text-horizon-500 bg-savannah-100 hover:bg-savannah-200 transition-colors"
              >
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg>
                {{ userName }}
                <svg class="w-4 h-4 ml-2 transition-transform duration-200" :class="{'rotate-180': userDropdownOpen}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
              </button>
              <div v-if="userDropdownOpen" class="absolute right-0 mt-2 w-56 rounded-lg shadow-lg bg-white ring-1 ring-black ring-opacity-5 z-50">
                <div class="py-1">
                  <router-link to="/dashboard" class="flex items-center px-4 py-2 text-sm text-horizon-500 hover:bg-savannah-100" @click="userDropdownOpen = false">
                    <svg class="w-4 h-4 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" /></svg>
                    Dashboard
                  </router-link>
                  <router-link to="/profile" class="flex items-center px-4 py-2 text-sm text-horizon-500 hover:bg-savannah-100" @click="userDropdownOpen = false">
                    <svg class="w-4 h-4 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg>
                    User Profile
                  </router-link>
                  <router-link to="/settings" class="flex items-center px-4 py-2 text-sm text-horizon-500 hover:bg-savannah-100" @click="userDropdownOpen = false">
                    <svg class="w-4 h-4 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                    Settings
                  </router-link>
                  <div class="border-t border-savannah-100 my-1"></div>
                  <button @click="handleLogout" class="flex items-center w-full text-left px-4 py-2 text-sm text-horizon-500 hover:bg-savannah-100">
                    <svg class="w-4 h-4 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" /></svg>
                    Sign Out
                  </button>
                </div>
              </div>
            </div>
            <!-- Not signed in -->
            <router-link
              v-else
              to="/login"
              class="inline-flex items-center px-3 py-2 border border-transparent text-sm font-medium rounded-lg text-horizon-500 bg-light-pink-100 hover:bg-light-pink-200 transition-colors"
            >
              <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg>
              Sign in
            </router-link>
          </div>

          <!-- Mobile menu button -->
          <div class="flex items-center lg:hidden">
            <button
              @click="mobileMenuOpen = !mobileMenuOpen"
              class="inline-flex items-center justify-center p-2 rounded-md text-horizon-400 hover:text-neutral-500 hover:bg-savannah-100"
            >
              <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path v-if="!mobileMenuOpen" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>
        </div>

      </div>

      <!-- Mobile menu -->
      <div v-if="mobileMenuOpen" class="lg:hidden border-t border-light-gray">
        <div class="pt-2 pb-3 space-y-1">
          <router-link to="/" class="block pl-3 pr-4 py-2 text-base font-medium text-horizon-500 hover:bg-savannah-100 hover:text-raspberry-500" @click="mobileMenuOpen = false">Home</router-link>

          <!-- Mobile: How it works accordion -->
          <div>
            <button
              type="button"
              class="flex w-full items-center justify-between pl-3 pr-4 py-2 text-base font-medium text-horizon-500 hover:bg-savannah-100 hover:text-raspberry-500"
              @click="howOpen = !howOpen"
            >
              How it works
              <svg class="w-4 h-4 transition-transform duration-200" :class="{ 'rotate-180': howOpen }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
              </svg>
            </button>
            <div v-if="howOpen" class="pl-6 pb-1 space-y-0.5">
              <router-link to="/how-it-works" class="block py-1.5 text-sm text-neutral-500 hover:text-raspberry-500" @click="mobileMenuOpen = false; howOpen = false">Getting started</router-link>
              <router-link to="/features" class="block py-1.5 text-sm text-neutral-500 hover:text-raspberry-500" @click="mobileMenuOpen = false; howOpen = false">Features</router-link>
              <p class="text-xs font-semibold text-neutral-400 uppercase tracking-wider pt-2 pb-1">Your personal journey</p>
              <router-link
                v-for="stage in stages"
                :key="stage.slug"
                :to="`/stage/${stage.slug}`"
                class="flex items-center gap-2 py-1.5 text-sm text-neutral-500 hover:text-raspberry-500"
                @click="mobileMenuOpen = false; howOpen = false"
              >
                <div class="w-2 h-2 rounded-full" :style="{ backgroundColor: stage.colour }"></div>
                {{ stage.name }}
              </router-link>
            </div>
          </div>

          <!-- Mobile: Resources accordion -->
          <div>
            <button
              type="button"
              class="flex w-full items-center justify-between pl-3 pr-4 py-2 text-base font-medium text-horizon-500 hover:bg-savannah-100 hover:text-raspberry-500"
              @click="resourcesOpen = !resourcesOpen"
            >
              Resources
              <svg class="w-4 h-4 transition-transform duration-200" :class="{ 'rotate-180': resourcesOpen }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
              </svg>
            </button>
            <div v-if="resourcesOpen" class="pl-6 pb-1 space-y-0.5">
              <router-link to="/calculators" class="block py-1.5 text-sm text-neutral-500 hover:text-raspberry-500" @click="mobileMenuOpen = false; resourcesOpen = false">Calculators</router-link>
              <router-link to="/learn" class="block py-1.5 text-sm text-neutral-500 hover:text-raspberry-500" @click="mobileMenuOpen = false; resourcesOpen = false">Guides &amp; Explainers</router-link>
              <router-link to="/learn/glossary" class="block py-1.5 text-sm text-neutral-500 hover:text-raspberry-500" @click="mobileMenuOpen = false; resourcesOpen = false">Glossary A-Z</router-link>
              <router-link to="/insights" class="block py-1.5 text-sm text-neutral-500 hover:text-raspberry-500" @click="mobileMenuOpen = false; resourcesOpen = false">Latest Insights</router-link>
              <router-link to="/faq" class="block py-1.5 text-sm text-neutral-500 hover:text-raspberry-500" @click="mobileMenuOpen = false; resourcesOpen = false">FAQ</router-link>
            </div>
          </div>

          <!-- Mobile: Why Fynla accordion -->
          <div>
            <button
              type="button"
              class="flex w-full items-center justify-between pl-3 pr-4 py-2 text-base font-medium text-horizon-500 hover:bg-savannah-100 hover:text-raspberry-500"
              @click="whyOpen = !whyOpen"
            >
              Why Fynla
              <svg class="w-4 h-4 transition-transform duration-200" :class="{ 'rotate-180': whyOpen }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
              </svg>
            </button>
            <div v-if="whyOpen" class="pl-6 pb-1 space-y-0.5">
              <router-link to="/why-fynla/our-approach" class="block py-1.5 text-sm text-neutral-500 hover:text-raspberry-500" @click="mobileMenuOpen = false; whyOpen = false">Our Approach</router-link>
              <router-link to="/why-fynla/one-platform" class="block py-1.5 text-sm text-neutral-500 hover:text-raspberry-500" @click="mobileMenuOpen = false; whyOpen = false">One platform</router-link>
              <router-link to="/why-fynla/independent" class="block py-1.5 text-sm text-neutral-500 hover:text-raspberry-500" @click="mobileMenuOpen = false; whyOpen = false">Your financial companion</router-link>
              <router-link to="/security" class="block py-1.5 text-sm text-neutral-500 hover:text-raspberry-500" @click="mobileMenuOpen = false; whyOpen = false">Security &amp; Privacy</router-link>
            </div>
          </div>

          <router-link to="/pricing" class="block pl-3 pr-4 py-2 text-base font-medium text-horizon-500 hover:bg-savannah-100 hover:text-raspberry-500" @click="mobileMenuOpen = false">Pricing</router-link>
          <router-link to="/login" class="block pl-3 pr-4 py-2 text-base font-medium text-horizon-500 hover:bg-savannah-100 hover:text-raspberry-500" @click="mobileMenuOpen = false">Sign in</router-link>
        </div>
      </div>
    </nav>

    <!-- Main Content -->
    <main>
      <slot />
    </main>

    <!-- Footer -->
    <footer class="bg-gradient-to-r from-horizon-600 to-horizon-700 pt-40">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pb-12">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-7 gap-8">
          <!-- Company Info -->
          <div class="lg:col-span-2">
            <div class="-mt-3 mb-4">
              <router-link to="/"><img :src="footerLogoUrl" alt="Fynla" width="124" height="56" class="h-14 w-auto" /></router-link>
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
              <li><router-link to="/faq" class="text-sm text-white/70 hover:text-white transition-colors">FAQs</router-link></li>
              <li><router-link to="/learn" class="text-sm text-white/70 hover:text-white transition-colors">Guides and explainers</router-link></li>
              <li><router-link to="/contact" class="text-sm text-white/70 hover:text-white transition-colors">Contact us</router-link></li>
            </ul>
          </div>

          <!-- Terms -->
          <div>
            <h3 class="text-sm font-bold text-white mb-4">Terms</h3>
            <ul class="space-y-2">
              <li><router-link to="/terms" class="text-sm text-white/70 hover:text-white transition-colors">Terms &amp; conditions</router-link></li>
              <li><router-link to="/privacy" class="text-sm text-white/70 hover:text-white transition-colors">Privacy policy</router-link></li>
              <li><router-link to="/editorial-policy" class="text-sm text-white/70 hover:text-white transition-colors">Editorial policy</router-link></li>
            </ul>
          </div>

          <!-- Tools -->
          <div>
            <h3 class="text-sm font-bold text-white mb-4">Tools</h3>
            <ul class="space-y-2">
              <li><router-link to="/calculators" class="text-sm text-white/70 hover:text-white transition-colors">Calculators</router-link></li>
              <li><router-link to="/learn" class="text-sm text-white/70 hover:text-white transition-colors">Resources</router-link></li>
              <li><a href="#" @click.prevent="showDemoModal = true" class="text-sm text-white/70 hover:text-white transition-colors">View demo</a></li>
            </ul>
          </div>

          <!-- Advisors -->
          <div>
            <h3 class="text-sm font-bold text-white mb-4">Advisers</h3>
            <ul class="space-y-2">
              <li><router-link to="/advisors" class="text-sm text-white/70 hover:text-white transition-colors">Why Fynla advisers</router-link></li>
              <li><router-link to="/advisors?scrollTo=adviser-features" class="text-sm text-white/70 hover:text-white transition-colors">Adviser features</router-link></li>
              <li><router-link to="/advisors?scrollTo=adviser-signup" class="text-sm text-white/70 hover:text-white transition-colors">Adviser sign up</router-link></li>
            </ul>
          </div>
        </div>

        <div class="border-t border-white/20 mt-8 pt-8 flex items-center justify-between">
          <p class="text-sm text-white/70">
            &copy; Fynla 2026
          </p>
          <div class="flex items-center gap-4">
            <a href="https://www.youtube.com/@HelloFynla" target="_blank" rel="noopener noreferrer" class="text-white/70 hover:text-white transition-colors" aria-label="YouTube">
              <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg>
            </a>
            <a href="https://www.facebook.com/HelloFynla" target="_blank" rel="noopener noreferrer" class="text-white/70 hover:text-white transition-colors" aria-label="Facebook">
              <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
            </a>
            <a href="https://www.instagram.com/HelloFynla" target="_blank" rel="noopener noreferrer" class="text-white/70 hover:text-white transition-colors" aria-label="Instagram">
              <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 1 0 0 12.324 6.162 6.162 0 0 0 0-12.324zM12 16a4 4 0 1 1 0-8 4 4 0 0 1 0 8zm6.406-11.845a1.44 1.44 0 1 0 0 2.881 1.44 1.44 0 0 0 0-2.881z"/></svg>
            </a>
            <a href="https://www.tiktok.com/@HelloFynla" target="_blank" rel="noopener noreferrer" class="text-white/70 hover:text-white transition-colors" aria-label="TikTok">
              <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M12.525.02c1.31-.02 2.61-.01 3.91-.02.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.57-.26-1.1-.59-1.62-.93-.01 2.92.01 5.84-.02 8.75-.08 1.4-.54 2.79-1.35 3.94-1.31 1.92-3.58 3.17-5.91 3.21-1.43.08-2.86-.31-4.08-1.03-2.02-1.19-3.44-3.37-3.65-5.71-.02-.5-.03-1-.01-1.49.18-1.9 1.12-3.72 2.58-4.96 1.66-1.44 3.98-2.13 6.15-1.72.02 1.48-.04 2.96-.04 4.44-.99-.32-2.15-.23-3.02.37-.63.41-1.11 1.04-1.36 1.75-.21.51-.15 1.07-.14 1.61.24 1.64 1.82 3.02 3.5 2.87 1.12-.01 2.19-.66 2.77-1.61.19-.33.4-.67.41-1.06.1-1.79.06-3.57.07-5.36.01-4.03-.01-8.05.02-12.07z"/></svg>
            </a>
          </div>
        </div>
      </div>
    </footer>

    <!-- Global Persona Selection Modal -->
    <PersonaSelectionModal
      :is-open="showDemoModal"
      :personas="availablePersonas"
      :error="demoError"
      @close="closeDemoModal"
      @select="handleDemoPersonaSelect"
    />
  </div>
</template>

<script>
import { mapGetters } from 'vuex';
import { nextTick } from 'vue';
import PersonaSelectionModal from '@/components/Preview/PersonaSelectionModal.vue';

export default {
  name: 'PublicLayout',

  components: {
    PersonaSelectionModal,
  },

  data() {
    return {
      mobileMenuOpen: false,
      howOpen: false,
      resourcesOpen: false,
      whyOpen: false,
      userDropdownOpen: false,
      showDemoModal: false,
      demoError: '',
      enteringDemo: false,
      logoUrl: '/images/logos/LogoHiResFynlaDark.png',
      footerLogoUrl: '/images/logos/LogoHiResFynlaLight.png',
      stages: [
        { slug: 'starting-out', name: 'Starting Out', menuName: 'Starting<br/>Out', sub: 'First job, first steps', colour: '#1D9E75', icon: 'M13 10V3L4 14h7v7l9-11h-7z' },
        { slug: 'building-foundations', name: 'Building Foundations', sub: 'Saving, buying, growing', colour: '#5DCAA5', icon: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6' },
        { slug: 'protecting-and-growing', name: 'Protecting and Growing', sub: 'Family, home, investments', colour: '#378ADD', icon: 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z' },
        { slug: 'planning-your-future', name: 'Planning Your Future', sub: 'Peak earning, retirement prep', colour: '#7F77DD', icon: 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z' },
        { slug: 'enjoying-your-wealth', name: 'Enjoying Your Wealth', sub: 'Later life, legacy, estate', colour: '#B8956A', icon: 'M12 3v1m4.22 1.78l-.71.71M20 12h1M4 12H3m3.34-5.66l-.71-.71M15.54 8.46A5.99 5.99 0 0112 7a5.99 5.99 0 00-3.54 1.46M12 14a2 2 0 100-4 2 2 0 000 4zm0 0v7' },
      ],
    };
  },

  computed: {
    ...mapGetters('auth', ['isAuthenticated']),
    ...mapGetters('preview', ['availablePersonas']),
    userName() {
      return this.$store.state.auth.user?.first_name || 'Account';
    },
    isHowActive() {
      const p = this.$route.path;
      return p === '/how-it-works' || p === '/features' || p.startsWith('/stage/');
    },
    isResourcesActive() {
      const p = this.$route.path;
      return p === '/calculators' || p === '/faq' || p === '/insights' || p.startsWith('/learn');
    },
    isWhyActive() {
      const p = this.$route.path;
      return p === '/security' || p.startsWith('/why-fynla');
    },
  },

  methods: {
    async handleLogout() {
      this.userDropdownOpen = false;
      await this.$store.dispatch('auth/logout');
      this.$router.push('/');
    },
    handleClickOutside(event) {
      if (this.userDropdownOpen && !event.target.closest('[data-dropdown="public-user"]')) {
        this.userDropdownOpen = false;
      }
    },
    checkDemoQuery() {
      if (this.$route.query.demo === 'true' && this.$route.path !== '/') {
        this.showDemoModal = true;
        this.$router.replace({ query: { ...this.$route.query, demo: undefined } });
      }
    },
    closeDemoModal() {
      this.showDemoModal = false;
      this.demoError = '';
    },
    async handleDemoPersonaSelect(persona) {
      if (this.enteringDemo) return;
      this.enteringDemo = true;
      this.demoError = '';
      try {
        await this.$store.dispatch('preview/loadPersona', persona.id);
        await nextTick();
        this.$router.push('/dashboard');
      } catch (error) {
        this.demoError = 'Unable to load demo. Please try again or check your connection.';
        this.enteringDemo = false;
      }
    },
  },

  mounted() {
    document.addEventListener('click', this.handleClickOutside);
    this.checkDemoQuery();
  },

  beforeUnmount() {
    document.removeEventListener('click', this.handleClickOutside);
  },

  watch: {
    $route(to) {
      this.mobileMenuOpen = false;
      this.howOpen = false;
      this.resourcesOpen = false;
      this.whyOpen = false;
      this.userDropdownOpen = false;
      if (to.query.demo === 'true') {
        this.checkDemoQuery();
      }
    },
  }
};
</script>

<style scoped>
nav .lg\:space-x-6 .router-link-active {
  @apply text-raspberry-500;
}
/* All mega menu panels start from the same fixed left position */
.mega-menu-panel {
  position: fixed !important;
  top: 44px !important;
  width: min(960px, calc(100vw - 2rem)) !important;
  left: 50% !important;
  transform: translateX(-50%) !important;
  padding-top: 20px !important;
}
</style>
