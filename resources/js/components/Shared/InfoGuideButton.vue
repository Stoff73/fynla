<template>
  <Teleport to="body">
    <button
      v-if="shouldShow"
      @click="toggle"
      class="fixed bottom-6 right-6 z-40 w-14 h-14 rounded-full bg-raspberry-600
             text-white shadow-lg hover:bg-raspberry-700 hover:shadow-xl
             transition-all duration-200 flex items-center justify-center
             focus:outline-none focus:ring-4 focus:ring-violet-300"
      :class="{ 'ring-4 ring-violet-200': isOpen }"
      :title="isOpen ? 'Close guide' : 'What data do I need?'"
    >
      <!-- Question mark icon when closed -->
      <svg
        v-if="!isOpen"
        xmlns="http://www.w3.org/2000/svg"
        fill="none"
        viewBox="0 0 24 24"
        stroke-width="2"
        stroke="currentColor"
        class="w-7 h-7"
      >
        <path
          stroke-linecap="round"
          stroke-linejoin="round"
          d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9 5.25h.008v.008H12v-.008z"
        />
      </svg>

      <!-- X icon when open -->
      <svg
        v-else
        xmlns="http://www.w3.org/2000/svg"
        fill="none"
        viewBox="0 0 24 24"
        stroke-width="2"
        stroke="currentColor"
        class="w-6 h-6"
      >
        <path
          stroke-linecap="round"
          stroke-linejoin="round"
          d="M6 18L18 6M6 6l12 12"
        />
      </svg>

      <!-- Badge showing missing items count -->
      <span
        v-if="missingCount > 0 && !isOpen"
        class="absolute -top-1 -right-1 w-5 h-5 bg-spring-500 rounded-full
               text-xs font-bold flex items-center justify-center shadow"
      >
        {{ missingCount > 9 ? '9+' : missingCount }}
      </span>
    </button>
  </Teleport>
</template>

<script>
import { mapGetters, mapActions } from 'vuex';

export default {
  name: 'InfoGuideButton',

  computed: {
    ...mapGetters('infoGuide', ['isOpen', 'shouldShowGuide', 'missingCount']),

    shouldShow() {
      // Don't show on public pages
      if (this.isPublicPage) {
        return false;
      }
      return this.shouldShowGuide;
    },

    isPublicPage() {
      const publicRoutes = ['/login', '/register', '/forgot-password', '/reset-password'];
      return publicRoutes.some(route => this.$route.path.startsWith(route));
    },
  },

  methods: {
    ...mapActions('infoGuide', ['toggle']),
  },
};
</script>
