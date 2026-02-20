<template>
  <PublicLayout>
    <div class="min-h-screen bg-gray-50 py-12">
      <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="text-center mb-12">
          <h1 class="text-4xl font-bold text-gray-900 mb-4">Sitemap</h1>
          <p class="text-lg text-gray-600">
            Navigate to all sections of the Fynla Financial Planning System
          </p>
        </div>

        <!-- Main Content -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
          <!-- Public Pages -->
          <div class="bg-white rounded-xl shadow-sm p-6">
            <h2 class="text-xl font-semibold text-gray-900 mb-4 flex items-center">
              <svg class="w-5 h-5 mr-2 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
              </svg>
              Main Pages
            </h2>
            <ul class="space-y-3">
              <li>
                <router-link to="/" class="text-blue-600 hover:text-blue-800 hover:underline flex items-center">
                  <span class="w-2 h-2 bg-blue-400 rounded-full mr-3"></span>
                  Home
                </router-link>
              </li>
              <li>
                <router-link to="/calculators" class="text-blue-600 hover:text-blue-800 hover:underline flex items-center">
                  <span class="w-2 h-2 bg-blue-400 rounded-full mr-3"></span>
                  Financial Calculators
                </router-link>
              </li>
              <li>
                <router-link to="/learning-centre" class="text-blue-600 hover:text-blue-800 hover:underline flex items-center">
                  <span class="w-2 h-2 bg-blue-400 rounded-full mr-3"></span>
                  Learning Centre
                </router-link>
              </li>
              <li>
                <router-link to="/security" class="text-blue-600 hover:text-blue-800 hover:underline flex items-center">
                  <span class="w-2 h-2 bg-blue-400 rounded-full mr-3"></span>
                  Security & Privacy
                </router-link>
              </li>
              <li>
                <router-link to="/privacy" class="text-blue-600 hover:text-blue-800 hover:underline flex items-center">
                  <span class="w-2 h-2 bg-blue-400 rounded-full mr-3"></span>
                  Privacy Policy
                </router-link>
              </li>
              <li>
                <router-link to="/terms" class="text-blue-600 hover:text-blue-800 hover:underline flex items-center">
                  <span class="w-2 h-2 bg-blue-400 rounded-full mr-3"></span>
                  Terms of Service
                </router-link>
              </li>
              <li>
                <router-link to="/version" class="text-blue-600 hover:text-blue-800 hover:underline flex items-center">
                  <span class="w-2 h-2 bg-blue-400 rounded-full mr-3"></span>
                  Version History
                </router-link>
              </li>
              <li>
                <router-link to="/help" class="text-blue-600 hover:text-blue-800 hover:underline flex items-center">
                  <span class="w-2 h-2 bg-blue-400 rounded-full mr-3"></span>
                  Help
                </router-link>
              </li>
            </ul>
          </div>

          <!-- Account Pages -->
          <div class="bg-white rounded-xl shadow-sm p-6">
            <h2 class="text-xl font-semibold text-gray-900 mb-4 flex items-center">
              <svg class="w-5 h-5 mr-2 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
              </svg>
              Account
            </h2>
            <ul class="space-y-3">
              <li>
                <router-link to="/login" class="text-blue-600 hover:text-blue-800 hover:underline flex items-center">
                  <span class="w-2 h-2 bg-emerald-400 rounded-full mr-3"></span>
                  Log In
                </router-link>
              </li>
              <li>
                <router-link to="/register" class="text-blue-600 hover:text-blue-800 hover:underline flex items-center">
                  <span class="w-2 h-2 bg-emerald-400 rounded-full mr-3"></span>
                  Register
                </router-link>
              </li>
              <li>
                <router-link to="/forgot-password" class="text-blue-600 hover:text-blue-800 hover:underline flex items-center">
                  <span class="w-2 h-2 bg-emerald-400 rounded-full mr-3"></span>
                  Forgot Password
                </router-link>
              </li>
            </ul>
          </div>

          <!-- Planning Modules -->
          <div class="bg-white rounded-xl shadow-sm p-6">
            <h2 class="text-xl font-semibold text-gray-900 mb-4 flex items-center">
              <svg class="w-5 h-5 mr-2 text-purple-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
              </svg>
              Planning Modules
            </h2>
            <p class="text-sm text-gray-500 mb-3">Requires login</p>
            <ul class="space-y-3">
              <li class="flex items-center text-gray-600">
                <span class="w-2 h-2 bg-purple-400 rounded-full mr-3"></span>
                Dashboard
              </li>
              <li class="flex items-center text-gray-600">
                <span class="w-2 h-2 bg-purple-400 rounded-full mr-3"></span>
                Net Worth
              </li>
              <li class="flex items-center text-gray-600">
                <span class="w-2 h-2 bg-purple-400 rounded-full mr-3"></span>
                Protection Planning
              </li>
              <li class="flex items-center text-gray-600">
                <span class="w-2 h-2 bg-purple-400 rounded-full mr-3"></span>
                Savings & Goals
              </li>
              <li class="flex items-center text-gray-600">
                <span class="w-2 h-2 bg-purple-400 rounded-full mr-3"></span>
                Investment Portfolio
              </li>
              <li class="flex items-center text-gray-600">
                <span class="w-2 h-2 bg-purple-400 rounded-full mr-3"></span>
                Retirement Planning
              </li>
              <li class="flex items-center text-gray-600">
                <span class="w-2 h-2 bg-purple-400 rounded-full mr-3"></span>
                Estate Planning
              </li>
            </ul>
          </div>

          <!-- Financial Calculators -->
          <div class="bg-white rounded-xl shadow-sm p-6">
            <h2 class="text-xl font-semibold text-gray-900 mb-4 flex items-center">
              <svg class="w-5 h-5 mr-2 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
              </svg>
              Calculators
            </h2>
            <ul class="space-y-3">
              <li>
                <router-link to="/calculators" class="text-blue-600 hover:text-blue-800 hover:underline flex items-center">
                  <span class="w-2 h-2 bg-blue-400 rounded-full mr-3"></span>
                  Income Tax Calculator
                </router-link>
              </li>
              <li>
                <router-link to="/calculators" class="text-blue-600 hover:text-blue-800 hover:underline flex items-center">
                  <span class="w-2 h-2 bg-blue-400 rounded-full mr-3"></span>
                  Retirement Calculator
                </router-link>
              </li>
              <li>
                <router-link to="/calculators" class="text-blue-600 hover:text-blue-800 hover:underline flex items-center">
                  <span class="w-2 h-2 bg-blue-400 rounded-full mr-3"></span>
                  Inheritance Tax Calculator
                </router-link>
              </li>
              <li>
                <router-link to="/calculators" class="text-blue-600 hover:text-blue-800 hover:underline flex items-center">
                  <span class="w-2 h-2 bg-blue-400 rounded-full mr-3"></span>
                  Capital Gains Tax Calculator
                </router-link>
              </li>
              <li>
                <router-link to="/calculators" class="text-blue-600 hover:text-blue-800 hover:underline flex items-center">
                  <span class="w-2 h-2 bg-blue-400 rounded-full mr-3"></span>
                  Pension Contribution Calculator
                </router-link>
              </li>
            </ul>
          </div>
        </div>

        <!-- XML Sitemap Note -->
        <div class="mt-12 text-center">
          <p class="text-sm text-gray-500">
            For search engines: <a href="/sitemap.xml" class="text-blue-600 hover:underline">sitemap.xml</a>
          </p>
        </div>
      </div>
    </div>
  </PublicLayout>
</template>

<script>
import PublicLayout from '@/layouts/PublicLayout.vue';

export default {
  name: 'SitemapPage',

  components: {
    PublicLayout,
  },
};
</script>
