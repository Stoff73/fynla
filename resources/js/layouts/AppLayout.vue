<template>
  <div class="min-h-screen flex flex-col">
    <!-- Side Menu (teleported to body) -->
    <SideMenu
      :collapsed="sideMenuCollapsed"
      :mobile-open="sideMenuMobileOpen"
      @toggle="toggleSideMenu"
      @update:mobile-open="sideMenuMobileOpen = $event"
    />

    <!-- Mobile hamburger toggle -->
    <SideMenuMobileToggle @toggle="sideMenuMobileOpen = !sideMenuMobileOpen" />

    <!-- Main content wrapper with left margin for side menu -->
    <div
      class="flex flex-col min-h-screen transition-all duration-300 ease-out"
      :class="contentMarginClass"
    >
      <Navbar />

      <!-- Offline Indicator Banner -->
      <OfflineBanner />

      <!-- Trial Countdown Banner (non-preview users only) -->
      <TrialCountdownBanner v-if="isAuthenticated && !isPreviewMode" />

      <!-- Data Retention Overlay (non-dismissable modal for grace period users) -->
      <DataRetentionOverlay v-if="isAuthenticated && !isPreviewMode" />

      <!-- Preview Mode Banner -->
      <PreviewBanner v-if="isPreviewMode" />

      <main class="flex-grow bg-eggshell-500">
        <div class="max-w-7xl mx-auto py-2 sm:py-3 px-4 sm:px-6 lg:px-8">
          <slot />
        </div>
      </main>

      <Footer />
    </div>

    <!-- Information Guide (floating help button + panel) -->
    <InfoGuideButton />
    <InfoGuidePanel />

    <!-- AI Chat (floating button + panel) -->
    <AiChatButton />
    <AiChatPanel />
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
import AiChatButton from '@/components/Shared/AiChatButton.vue';
import AiChatPanel from '@/components/Shared/AiChatPanel.vue';
import SideMenu from '@/components/SideMenu.vue';
import SideMenuMobileToggle from '@/components/SideMenuMobileToggle.vue';
import OfflineBanner from '@/mobile/OfflineBanner.vue';

const STORAGE_KEY = 'sideMenuCollapsed';

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
    AiChatButton,
    AiChatPanel,
    SideMenu,
    SideMenuMobileToggle,
    OfflineBanner,
  },

  data() {
    return {
      sideMenuCollapsed: localStorage.getItem(STORAGE_KEY) === 'true',
      sideMenuMobileOpen: false,
    };
  },

  computed: {
    ...mapGetters('preview', ['isPreviewMode']),
    ...mapGetters('auth', ['isAuthenticated']),

    contentMarginClass() {
      // No margin on mobile (menu is overlay)
      // On desktop, margin matches side menu width
      return this.sideMenuCollapsed
        ? 'sm:ml-16'   // 64px collapsed
        : 'sm:ml-56';  // 224px expanded
    },
  },

  mounted() {
    // Fetch info guide preference when layout mounts
    if (this.isAuthenticated || this.isPreviewMode) {
      this.fetchInfoGuidePreference();
    }
  },

  methods: {
    ...mapActions('infoGuide', { fetchInfoGuidePreference: 'fetchPreference' }),

    toggleSideMenu() {
      this.sideMenuCollapsed = !this.sideMenuCollapsed;
      localStorage.setItem(STORAGE_KEY, String(this.sideMenuCollapsed));
    },
  },
};
</script>
