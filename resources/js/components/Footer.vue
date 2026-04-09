<template>
  <footer class="bg-white border-t border-light-gray">
    <div class="max-w-7xl mx-auto py-4 sm:py-6 px-4 sm:px-6 lg:px-8">
      <div class="flex flex-col lg:flex-row justify-between items-center space-y-4 lg:space-y-0">
        <div class="flex items-center gap-4">
          <router-link to="/"><img :src="logoUrl" alt="Fynla" class="h-8 w-auto" /></router-link>
        </div>
        <div class="text-body-sm text-neutral-500 text-center lg:text-left">
          <span class="block sm:inline">&copy; {{ currentYear }} Fynla - Your personal finance platform.</span>
          <span class="hidden sm:inline"> For demonstration purposes only.</span>
          <span class="block sm:hidden text-xs mt-1">For demonstration purposes only.</span>
          <span class="hidden sm:inline ml-2 text-horizon-400">|</span>
          <router-link to="/version" class="sm:ml-2 font-semibold text-raspberry-500 hover:text-raspberry-700 hover:underline">
            v1.0
          </router-link>
          <span class="ml-2 text-horizon-400">|</span>
          <a href="https://fynla.org" target="_blank" rel="noopener noreferrer" class="ml-2 font-semibold text-raspberry-500 hover:text-raspberry-700 hover:underline">
            fynla.org
          </a>
        </div>
        <div class="flex flex-wrap justify-center gap-4 sm:gap-6">
          <router-link to="/privacy" class="text-body-sm text-neutral-500 hover:text-horizon-500">
            Privacy Policy
          </router-link>
          <router-link to="/terms" class="text-body-sm text-neutral-500 hover:text-horizon-500">
            Terms of Service
          </router-link>
          <router-link to="/help" class="text-body-sm text-neutral-500 hover:text-horizon-500">
            Help
          </router-link>
          <router-link to="/sitemap" class="text-body-sm text-neutral-500 hover:text-horizon-500">
            Sitemap
          </router-link>
        </div>
      </div>
      <div class="mt-3 sm:mt-4 text-center">
        <p class="text-caption text-horizon-400 text-xs sm:text-sm">
          This system is for demonstration purposes only and does not constitute regulated financial advice.
        </p>
      </div>
    </div>
  </footer>
</template>

<script>
import { computed } from 'vue';

export default {
  name: 'AppFooter',

  setup() {
    const currentYear = computed(() => new Date().getFullYear());
    const logoUrl = '/images/logos/LogoHiResFynlaLight.png';

    return {
      currentYear,
      logoUrl,
    };
  },
};
</script>
