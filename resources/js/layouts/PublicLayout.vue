<template>
  <div class="min-h-screen bg-gray-50">
    <!-- Navigation -->
    <nav class="bg-white shadow-sm border-b border-gray-200 sticky top-0 z-50">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
          <div class="flex items-center">
            <!-- Logo -->
            <router-link to="/" class="flex items-center">
              <span class="text-xl font-bold text-gray-900">Fynla</span>
            </router-link>

            <!-- Navigation Links -->
            <div class="hidden md:ml-10 md:flex md:space-x-8">
              <router-link
                to="/"
                class="inline-flex items-center px-1 pt-1 text-sm font-medium text-gray-900 hover:text-blue-600 transition-colors"
              >
                Home
              </router-link>
              <router-link
                to="/calculators"
                class="inline-flex items-center px-1 pt-1 text-sm font-medium text-gray-500 hover:text-blue-600 transition-colors"
              >
                Calculators
              </router-link>
              <router-link
                to="/learning-centre"
                class="inline-flex items-center px-1 pt-1 text-sm font-medium text-gray-500 hover:text-blue-600 transition-colors"
              >
                Learning Centre
              </router-link>
            </div>
          </div>

          <!-- Right side buttons - hidden on mobile -->
          <div class="hidden md:flex items-center space-x-4">
            <router-link
              to="/login"
              class="text-sm font-medium text-gray-700 hover:text-blue-600 transition-colors"
            >
              Log In
            </router-link>
            <router-link
              to="/register"
              class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700 transition-colors"
            >
              Get Started
            </router-link>
          </div>

          <!-- Mobile menu button -->
          <div class="flex items-center md:hidden">
            <button
              @click="mobileMenuOpen = !mobileMenuOpen"
              class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100"
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
      <div v-if="mobileMenuOpen" class="md:hidden border-t border-gray-200">
        <div class="pt-2 pb-3 space-y-1">
          <router-link
            to="/"
            class="block pl-3 pr-4 py-2 text-base font-medium text-gray-700 hover:bg-gray-50 hover:text-blue-600"
            @click="mobileMenuOpen = false"
          >
            Home
          </router-link>
          <router-link
            to="/calculators"
            class="block pl-3 pr-4 py-2 text-base font-medium text-gray-700 hover:bg-gray-50 hover:text-blue-600"
            @click="mobileMenuOpen = false"
          >
            Calculators
          </router-link>
          <router-link
            to="/learning-centre"
            class="block pl-3 pr-4 py-2 text-base font-medium text-gray-700 hover:bg-gray-50 hover:text-blue-600"
            @click="mobileMenuOpen = false"
          >
            Learning Centre
          </router-link>
          <router-link
            to="/login"
            class="block pl-3 pr-4 py-2 text-base font-medium text-gray-700 hover:bg-gray-50 hover:text-blue-600"
            @click="mobileMenuOpen = false"
          >
            Log In
          </router-link>
          <router-link
            to="/register"
            class="block pl-3 pr-4 py-2 text-base font-medium text-blue-600 hover:bg-gray-50"
            @click="mobileMenuOpen = false"
          >
            Get Started
          </router-link>
        </div>
      </div>
    </nav>

    <!-- Main Content -->
    <main>
      <slot />
    </main>

    <!-- Footer -->
    <footer class="bg-gray-900 text-white mt-20">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
          <!-- Company Info -->
          <div class="col-span-1 md:col-span-2">
            <div class="flex items-center mb-4">
              <svg class="h-8 w-8 text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
              </svg>
              <span class="ml-2 text-xl font-bold">Fynla</span>
            </div>
            <p class="text-gray-400 text-sm mb-4">
              Financial Planning System - Your comprehensive solution for UK financial planning.
              Educational tool for demonstration purposes only.
            </p>
            <p class="text-xs text-gray-500">
              Not regulated financial advice. Always consult a qualified financial adviser.
            </p>
          </div>

          <!-- Quick Links -->
          <div>
            <h3 class="text-sm font-semibold uppercase tracking-wider mb-4">Resources</h3>
            <ul class="space-y-2">
              <li><router-link to="/calculators" class="text-gray-400 hover:text-white text-sm">Calculators</router-link></li>
              <li><router-link to="/learning-centre" class="text-gray-400 hover:text-white text-sm">Learning Centre</router-link></li>
              <li><a href="https://www.fca.org.uk" target="_blank" class="text-gray-400 hover:text-white text-sm">FCA Website</a></li>
            </ul>
          </div>

          <!-- Legal -->
          <div>
            <h3 class="text-sm font-semibold uppercase tracking-wider mb-4">Legal</h3>
            <ul class="space-y-2">
              <li><a href="#" class="text-gray-400 hover:text-white text-sm">Privacy Policy</a></li>
              <li><a href="#" class="text-gray-400 hover:text-white text-sm">Terms of Service</a></li>
              <li><a href="#" class="text-gray-400 hover:text-white text-sm">Cookie Policy</a></li>
            </ul>
          </div>
        </div>

        <div class="border-t border-gray-800 mt-8 pt-8 text-center">
          <p class="text-sm text-gray-400">
            &copy; 2025 Financial Planning System. Built with Claude Code.
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
.router-link-active {
  @apply text-blue-600;
}
</style>
