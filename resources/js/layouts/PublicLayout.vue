<template>
  <div class="min-h-screen bg-eggshell-500">
    <!-- Navigation -->
    <nav class="bg-white shadow-sm border-b border-light-gray sticky top-0 z-50">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-20">
          <div class="flex items-center">
            <!-- Logo -->
            <router-link to="/" class="flex items-center">
              <img :src="logoUrl" alt="Fynla" class="h-20 w-auto" />
            </router-link>

            <!-- Navigation Links -->
            <div class="hidden md:ml-10 md:flex md:space-x-8">
              <router-link
                to="/"
                class="inline-flex items-center px-1 pt-1 text-sm font-medium text-horizon-500 hover:text-raspberry-500 transition-colors"
              >
                Home
              </router-link>
              <router-link
                to="/calculators"
                class="inline-flex items-center px-1 pt-1 text-sm font-medium text-neutral-500 hover:text-raspberry-500 transition-colors"
              >
                Calculators
              </router-link>
              <router-link
                to="/learning-centre"
                class="inline-flex items-center px-1 pt-1 text-sm font-medium text-neutral-500 hover:text-raspberry-500 transition-colors"
              >
                Learning Centre
              </router-link>
              <router-link
                to="/security"
                class="inline-flex items-center px-1 pt-1 text-sm font-medium text-neutral-500 hover:text-raspberry-500 transition-colors"
              >
                Security
              </router-link>
              <router-link
                to="/about"
                class="inline-flex items-center px-1 pt-1 text-sm font-medium text-neutral-500 hover:text-raspberry-500 transition-colors"
              >
                About
              </router-link>
              <router-link
                to="/pricing"
                class="inline-flex items-center px-1 pt-1 text-sm font-medium text-neutral-500 hover:text-raspberry-500 transition-colors"
              >
                Pricing
              </router-link>
            </div>
          </div>

          <!-- Right side buttons - hidden on mobile -->
          <div class="hidden md:flex items-center space-x-4">
            <router-link
              to="/login"
              class="text-sm font-medium text-horizon-500 hover:text-raspberry-500 transition-colors"
            >
              Log In
            </router-link>
            <router-link
              to="/?demo=true"
              class="px-4 py-2 bg-raspberry-500 text-white text-sm font-medium rounded-button hover:bg-raspberry-600 transition-colors"
            >
              Get Started
            </router-link>
          </div>

          <!-- Mobile menu button -->
          <div class="flex items-center md:hidden">
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
      <div v-if="mobileMenuOpen" class="md:hidden border-t border-light-gray">
        <div class="pt-2 pb-3 space-y-1">
          <router-link
            to="/"
            class="block pl-3 pr-4 py-2 text-base font-medium text-horizon-500 hover:bg-savannah-100 hover:text-raspberry-500"
            @click="mobileMenuOpen = false"
          >
            Home
          </router-link>
          <router-link
            to="/calculators"
            class="block pl-3 pr-4 py-2 text-base font-medium text-horizon-500 hover:bg-savannah-100 hover:text-raspberry-500"
            @click="mobileMenuOpen = false"
          >
            Calculators
          </router-link>
          <router-link
            to="/learning-centre"
            class="block pl-3 pr-4 py-2 text-base font-medium text-horizon-500 hover:bg-savannah-100 hover:text-raspberry-500"
            @click="mobileMenuOpen = false"
          >
            Learning Centre
          </router-link>
          <router-link
            to="/security"
            class="block pl-3 pr-4 py-2 text-base font-medium text-horizon-500 hover:bg-savannah-100 hover:text-raspberry-500"
            @click="mobileMenuOpen = false"
          >
            Security
          </router-link>
          <router-link
            to="/about"
            class="block pl-3 pr-4 py-2 text-base font-medium text-horizon-500 hover:bg-savannah-100 hover:text-raspberry-500"
            @click="mobileMenuOpen = false"
          >
            About
          </router-link>
          <router-link
            to="/pricing"
            class="block pl-3 pr-4 py-2 text-base font-medium text-horizon-500 hover:bg-savannah-100 hover:text-raspberry-500"
            @click="mobileMenuOpen = false"
          >
            Pricing
          </router-link>
          <router-link
            to="/login"
            class="block pl-3 pr-4 py-2 text-base font-medium text-horizon-500 hover:bg-savannah-100 hover:text-raspberry-500"
            @click="mobileMenuOpen = false"
          >
            Log In
          </router-link>
          <router-link
            to="/?demo=true"
            class="block pl-3 pr-4 py-2 text-base font-medium text-raspberry-500 hover:bg-savannah-100"
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
    <footer class="bg-horizon-500 text-white">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
          <!-- Company Info -->
          <div class="col-span-1 md:col-span-2">
            <div class="flex items-center mb-4">
              <img :src="footerLogoUrl" alt="Fynla" class="h-52 w-auto" />
            </div>
            <p class="text-horizon-400 text-sm mb-4">
              Financial Freedom Mapping - Your comprehensive solution for UK financial freedom.
              Educational tool for demonstration purposes only.
            </p>
            <p class="text-xs text-neutral-500">
              Not regulated financial advice. Always consult a qualified financial adviser.
            </p>
          </div>

          <!-- Quick Links -->
          <div>
            <h3 class="text-sm font-semibold uppercase tracking-wider mb-4">Resources</h3>
            <ul class="space-y-2">
              <li><router-link to="/calculators" class="text-horizon-400 hover:text-white text-sm">Calculators</router-link></li>
              <li><router-link to="/learning-centre" class="text-horizon-400 hover:text-white text-sm">Learning Centre</router-link></li>
              <li><router-link to="/about" class="text-horizon-400 hover:text-white text-sm">About Us</router-link></li>
              <li><a href="https://www.fca.org.uk" target="_blank" class="text-horizon-400 hover:text-white text-sm">FCA Website</a></li>
            </ul>
          </div>

          <!-- Legal -->
          <div>
            <h3 class="text-sm font-semibold uppercase tracking-wider mb-4">Legal</h3>
            <ul class="space-y-2">
              <li><router-link to="/privacy" class="text-horizon-400 hover:text-white text-sm">Privacy Policy</router-link></li>
              <li><router-link to="/terms" class="text-horizon-400 hover:text-white text-sm">Terms of Service</router-link></li>
              <li><router-link to="/sitemap" class="text-horizon-400 hover:text-white text-sm">Sitemap</router-link></li>
            </ul>
          </div>
        </div>

        <div class="border-t border-horizon-400 mt-8 pt-8 text-center">
          <p class="text-sm text-horizon-400">
            &copy; 2026 Fynla - Financial Freedom Mapping.
            <router-link to="/version" class="ml-2 text-raspberry-400 hover:text-raspberry-300">v0.7.0</router-link>
            <span class="mx-2">|</span>
            <a href="https://fynla.org" target="_blank" rel="noopener noreferrer" class="text-raspberry-400 hover:text-raspberry-300">fynla.org</a>
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
