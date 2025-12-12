<template>
  <footer class="bg-white border-t border-gray-200">
    <div class="max-w-7xl mx-auto py-4 sm:py-6 px-4 sm:px-6 lg:px-8">
      <div class="flex flex-col lg:flex-row justify-between items-center space-y-4 lg:space-y-0">
        <div class="text-body-sm text-gray-500 text-center lg:text-left">
          <span class="block sm:inline">&copy; {{ currentYear }} Fynla - Financial Planning System.</span>
          <span class="hidden sm:inline"> For demonstration purposes only.</span>
          <span class="block sm:hidden text-xs mt-1">For demonstration purposes only.</span>
          <span class="hidden sm:inline ml-2 text-gray-400">|</span>
          <router-link to="/version" class="sm:ml-2 font-semibold text-primary-600 hover:text-primary-700 hover:underline">
            v0.2.15
          </router-link>
          <span class="ml-2 text-gray-400">|</span>
          <a href="https://csjones.co" target="_blank" rel="noopener noreferrer" class="ml-2 font-semibold text-primary-600 hover:text-primary-700 hover:underline">
            Boma Build
          </a>
        </div>
        <div class="flex flex-wrap justify-center gap-4 sm:gap-6">
          <a href="#" class="text-body-sm text-gray-500 hover:text-gray-700">
            Privacy Policy
          </a>
          <a href="#" class="text-body-sm text-gray-500 hover:text-gray-700">
            Terms of Service
          </a>
          <router-link to="/help" class="text-body-sm text-gray-500 hover:text-gray-700">
            Help
          </router-link>
        </div>
      </div>
      <div class="mt-3 sm:mt-4 text-center">
        <p class="text-caption text-gray-400 text-xs sm:text-sm">
          This system is for demonstration purposes only and does not constitute regulated financial advice.
        </p>
      </div>
    </div>
  </footer>
</template>

<script>
import { computed } from 'vue';

export default {
  name: 'Footer',

  setup() {
    const currentYear = computed(() => new Date().getFullYear());

    return {
      currentYear,
    };
  },
};
</script>
