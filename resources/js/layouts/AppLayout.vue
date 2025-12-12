<template>
  <div class="min-h-screen flex flex-col">
    <Navbar />

    <!-- Preview Mode Banner -->
    <PreviewBanner v-if="isPreviewMode" />

    <main class="flex-grow bg-gray-50">
      <div class="max-w-7xl mx-auto py-4 sm:py-6 px-4 sm:px-6 lg:px-8">
        <slot />
      </div>
    </main>

    <Footer />
  </div>
</template>

<script>
import { mapGetters } from 'vuex';
import Navbar from '@/components/Navbar.vue';
import Footer from '@/components/Footer.vue';
import PreviewBanner from '@/components/Preview/PreviewBanner.vue';

export default {
  name: 'AppLayout',

  components: {
    Navbar,
    Footer,
    PreviewBanner,
  },

  computed: {
    ...mapGetters('preview', ['isPreviewMode']),
  },
};
</script>
