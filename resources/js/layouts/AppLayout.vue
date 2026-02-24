<template>
  <div class="min-h-screen flex flex-col">
    <Navbar />

    <!-- Trial Countdown Banner (non-preview users only) -->
    <TrialCountdownBanner v-if="isAuthenticated && !isPreviewMode" />

    <!-- Data Retention Overlay (non-dismissable modal for grace period users) -->
    <DataRetentionOverlay v-if="isAuthenticated && !isPreviewMode" />

    <!-- Preview Mode Banner -->
    <PreviewBanner v-if="isPreviewMode" />

    <main class="flex-grow bg-gray-50">
      <div class="max-w-7xl mx-auto py-2 sm:py-3 px-4 sm:px-6 lg:px-8">
        <slot />
      </div>
    </main>

    <Footer />

    <!-- Information Guide (floating help button + panel) -->
    <InfoGuideButton />
    <InfoGuidePanel />
  </div>
</template>

<script>
import { mapGetters, mapActions } from 'vuex';
import Navbar from '@/components/Navbar.vue';
import Footer from '@/components/Footer.vue';
import PreviewBanner from '@/components/Preview/PreviewBanner.vue';
import TrialCountdownBanner from '@/components/Trial/TrialCountdownBanner.vue';
import DataRetentionOverlay from '@/components/Payment/DataRetentionOverlay.vue';
import InfoGuideButton from '@/components/Shared/InfoGuideButton.vue';
import InfoGuidePanel from '@/components/Shared/InfoGuidePanel.vue';

export default {
  name: 'AppLayout',

  components: {
    Navbar,
    Footer,
    PreviewBanner,
    TrialCountdownBanner,
    DataRetentionOverlay,
    InfoGuideButton,
    InfoGuidePanel,
  },

  computed: {
    ...mapGetters('preview', ['isPreviewMode']),
    ...mapGetters('auth', ['isAuthenticated']),
  },

  mounted() {
    // Fetch info guide preference when layout mounts
    if (this.isAuthenticated || this.isPreviewMode) {
      this.fetchInfoGuidePreference();
    }
  },

  methods: {
    ...mapActions('infoGuide', { fetchInfoGuidePreference: 'fetchPreference' }),
  },
};
</script>
