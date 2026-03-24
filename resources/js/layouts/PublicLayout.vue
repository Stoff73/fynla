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
              <a
                href="/#features"
                class="inline-flex items-center px-1 pt-1 text-sm font-medium text-neutral-500 hover:text-raspberry-500 transition-colors"
              >
                Features
              </a>
              <a
                href="/#solutions"
                class="inline-flex items-center px-1 pt-1 text-sm font-medium text-neutral-500 hover:text-raspberry-500 transition-colors"
              >
                Solutions
              </a>
              <router-link
                to="/how-it-works"
                class="inline-flex items-center px-1 pt-1 text-sm font-medium text-neutral-500 hover:text-raspberry-500 transition-colors"
              >
                How it works
              </router-link>
              <router-link
                to="/pricing"
                class="inline-flex items-center px-1 pt-1 text-sm font-medium text-neutral-500 hover:text-raspberry-500 transition-colors"
              >
                Pricing
              </router-link>
              <router-link
                to="/faq"
                class="inline-flex items-center px-1 pt-1 text-sm font-medium text-neutral-500 hover:text-raspberry-500 transition-colors"
              >
                FAQ
              </router-link>
              <router-link
                to="/learning-centre"
                class="inline-flex items-center px-1 pt-1 text-sm font-medium text-neutral-500 hover:text-raspberry-500 transition-colors"
              >
                Learning centre
              </router-link>
            </div>
          </div>

          <!-- Right side button - hidden on mobile -->
          <div class="hidden md:flex items-center">
            <router-link
              to="/login"
              class="min-w-[120px] px-5 py-2.5 bg-spring-500 text-white text-sm font-semibold rounded-lg hover:bg-spring-600 transition-colors text-center"
            >
              Sign in
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
          <a
            href="/#features"
            class="block pl-3 pr-4 py-2 text-base font-medium text-horizon-500 hover:bg-savannah-100 hover:text-raspberry-500"
            @click="mobileMenuOpen = false"
          >
            Features
          </a>
          <a
            href="/#solutions"
            class="block pl-3 pr-4 py-2 text-base font-medium text-horizon-500 hover:bg-savannah-100 hover:text-raspberry-500"
            @click="mobileMenuOpen = false"
          >
            Solutions
          </a>
          <router-link
            to="/how-it-works"
            class="block pl-3 pr-4 py-2 text-base font-medium text-horizon-500 hover:bg-savannah-100 hover:text-raspberry-500"
            @click="mobileMenuOpen = false"
          >
            How it works
          </router-link>
          <router-link
            to="/pricing"
            class="block pl-3 pr-4 py-2 text-base font-medium text-horizon-500 hover:bg-savannah-100 hover:text-raspberry-500"
            @click="mobileMenuOpen = false"
          >
            Pricing
          </router-link>
          <router-link
            to="/faq"
            class="block pl-3 pr-4 py-2 text-base font-medium text-horizon-500 hover:bg-savannah-100 hover:text-raspberry-500"
            @click="mobileMenuOpen = false"
          >
            FAQ
          </router-link>
          <router-link
            to="/learning-centre"
            class="block pl-3 pr-4 py-2 text-base font-medium text-horizon-500 hover:bg-savannah-100 hover:text-raspberry-500"
            @click="mobileMenuOpen = false"
          >
            Learning centre
          </router-link>
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
    <footer class="bg-savannah-100 pt-16">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pb-12">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-6 gap-8">
          <!-- Company Info -->
          <div class="lg:col-span-2">
            <div class="flex items-center mb-4">
              <img :src="logoUrl" alt="Fynla" class="h-16 w-auto" />
            </div>
            <p class="text-sm text-neutral-500 leading-relaxed">
              Financial freedom mapping - your financial companion for life through one single finance platform
            </p>
          </div>

          <!-- About Fynla -->
          <div>
            <h3 class="text-sm font-bold text-horizon-500 mb-4">About Fynla</h3>
            <ul class="space-y-2">
              <li><router-link to="/about" class="text-sm text-neutral-500 hover:text-raspberry-500 transition-colors">About us</router-link></li>
              <li><router-link to="/about" class="text-sm text-neutral-500 hover:text-raspberry-500 transition-colors">Accreditations</router-link></li>
              <li><a href="https://www.fca.org.uk" target="_blank" class="text-sm text-neutral-500 hover:text-raspberry-500 transition-colors">FCA website</a></li>
            </ul>
          </div>

          <!-- Help Centre -->
          <div>
            <h3 class="text-sm font-bold text-horizon-500 mb-4">Help centre</h3>
            <ul class="space-y-2">
              <li><router-link to="/faq" class="text-sm text-neutral-500 hover:text-raspberry-500 transition-colors">FAQs</router-link></li>
              <li><router-link to="/learning-centre" class="text-sm text-neutral-500 hover:text-raspberry-500 transition-colors">Ask Fyn</router-link></li>
              <li><router-link to="/learning-centre" class="text-sm text-neutral-500 hover:text-raspberry-500 transition-colors">Learning Centre</router-link></li>
            </ul>
          </div>

          <!-- Terms -->
          <div>
            <h3 class="text-sm font-bold text-horizon-500 mb-4">Terms</h3>
            <ul class="space-y-2">
              <li><router-link to="/terms" class="text-sm text-neutral-500 hover:text-raspberry-500 transition-colors">Terms &amp; conditions</router-link></li>
              <li><router-link to="/privacy" class="text-sm text-neutral-500 hover:text-raspberry-500 transition-colors">Privacy policy</router-link></li>
            </ul>
          </div>

          <!-- Tools -->
          <div>
            <h3 class="text-sm font-bold text-horizon-500 mb-4">Tools</h3>
            <ul class="space-y-2">
              <li><router-link to="/calculators" class="text-sm text-neutral-500 hover:text-raspberry-500 transition-colors">Calculators</router-link></li>
              <li><router-link to="/learning-centre" class="text-sm text-neutral-500 hover:text-raspberry-500 transition-colors">Resources</router-link></li>
              <li><a href="/?demo=true" class="text-sm text-neutral-500 hover:text-raspberry-500 transition-colors">View demo</a></li>
            </ul>
          </div>
        </div>

        <div class="border-t border-light-gray mt-8 pt-8">
          <p class="text-sm text-neutral-500">
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
      logoUrl: '/images/logos/LogoHiResFynlaDark.png',
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
