<template>
  <header class="mobile-header bg-white border-b border-light-gray flex items-center px-4"
          style="min-height: 48px;">
    <!-- Left: Back button on sub-pages, user avatar on root pages -->
    <div class="w-10">
      <button
        v-if="showBack"
        class="p-1 -ml-1 text-horizon-500"
        @click="$emit('back')"
      >
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
        </svg>
      </button>
      <button
        v-else
        class="w-8 h-8 rounded-full bg-horizon-500 flex items-center justify-center"
        @click="$emit('profile')"
      >
        <span class="text-white font-bold text-xs">{{ initials }}</span>
      </button>
    </div>

    <!-- Centre: Fynla logo -->
    <div class="flex-1 flex justify-center">
      <img
        src="/images/logos/LogoHiResFynlaDark.png"
        alt="Fynla"
        class="h-8"
      />
    </div>

    <!-- Right: spacer to balance layout -->
    <div class="w-10"></div>
  </header>
</template>

<script>
import { mapGetters } from 'vuex';

export default {
  name: 'MobileHeader',
  props: {
    showBack: { type: Boolean, default: false },
  },
  emits: ['back', 'profile'],

  computed: {
    ...mapGetters('auth', ['currentUser']),

    initials() {
      if (!this.currentUser) return '?';
      const first = (this.currentUser.first_name || '')[0] || '';
      const last = (this.currentUser.last_name || '')[0] || '';
      return (first + last).toUpperCase() || '?';
    },
  },
};
</script>
