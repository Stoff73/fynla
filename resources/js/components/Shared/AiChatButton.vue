<template>
  <Teleport to="body">
    <button
      v-if="shouldShow"
      @click="toggle"
      class="fixed bottom-6 right-24 z-40 w-14 h-14 rounded-full
             text-white shadow-lg hover:shadow-xl
             transition-all duration-200 flex items-center justify-center
             focus:outline-none focus:ring-4 focus:ring-violet-300"
      :class="buttonClass"
      :title="isOpen ? 'Close chat' : 'Chat with Fynla'"
    >
      <!-- Chat icon when closed -->
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
          d="M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a5.969 5.969 0 01-.474-.065 4.48 4.48 0 00.978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z"
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
    </button>
  </Teleport>
</template>

<script>
import { mapGetters, mapActions } from 'vuex';

export default {
    name: 'AiChatButton',

    computed: {
        ...mapGetters('aiChat', { isChatOpen: 'isOpen' }),
        ...mapGetters('infoGuide', { isInfoGuideOpen: 'isOpen' }),

        isOpen() {
            return this.isChatOpen;
        },

        shouldShow() {
            // Don't show on public pages
            const publicRoutes = ['/login', '/register', '/forgot-password', '/reset-password', '/'];
            const currentPath = this.$route?.path;
            if (!currentPath) return false;
            // Exact match for root, startsWith for others
            if (currentPath === '/') return false;
            if (publicRoutes.some((route) => route !== '/' && currentPath.startsWith(route))) {
                return false;
            }
            return true;
        },

        buttonClass() {
            return this.isOpen
                ? 'bg-neutral-600 hover:bg-neutral-700 ring-4 ring-savannah-200'
                : 'bg-raspberry-600 hover:bg-raspberry-700';
        },
    },

    methods: {
        ...mapActions('aiChat', ['toggle']),
    },
};
</script>
