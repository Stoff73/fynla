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

      <!-- Content area: flex row when docked chat is active -->
      <div class="flex-grow flex bg-eggshell-500">
        <main class="flex-1 min-w-0">
          <div class="max-w-7xl mx-auto py-2 sm:py-3 px-4 sm:px-6 lg:px-8">
            <slot />
          </div>
        </main>

        <!-- Docked Fyn Chat (real users, desktop) — sticky to viewport -->
        <aside v-if="showDockedChat" class="hidden lg:flex lg:flex-col w-[380px] flex-shrink-0 border-l border-light-gray bg-white sticky top-0 h-screen overflow-hidden">
          <AiChatPanel :docked="true" />
        </aside>
      </div>

      <Footer />
    </div>

    <!-- Information Guide (floating help button + panel) -->
    <InfoGuideButton />
    <InfoGuidePanel />

    <!-- AI Chat floating button + panel (hidden when docked chat is active) -->
    <AiChatButton v-if="!showDockedChat" />
    <AiChatPanel v-if="!showDockedChat" />
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
import storage from '@/utils/storage';

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
      sideMenuCollapsed: storage.get(STORAGE_KEY) === 'true',
      sideMenuMobileOpen: false,
    };
  },

  computed: {
    ...mapGetters('preview', ['isPreviewMode']),
    ...mapGetters('auth', ['isAuthenticated', 'currentUser']),

    contentMarginClass() {
      return this.sideMenuCollapsed
        ? 'sm:ml-16'
        : 'sm:ml-56';
    },

    showDockedChat() {
      return this.isAuthenticated
        && !this.isPreviewMode
        && this.currentUser
        && !this.currentUser.is_preview_user;
    },
  },

  watch: {
    // Collapse side menu when docked chat becomes active
    showDockedChat(active) {
      if (active && !this.sideMenuCollapsed) {
        this.sideMenuCollapsed = true;
        storage.set(STORAGE_KEY, true);
      }
    },
  },

  mounted() {
    if (this.isAuthenticated || this.isPreviewMode) {
      this.fetchInfoGuidePreference();
    }

    // Collapse side menu for real users with docked chat
    if (this.showDockedChat && !this.sideMenuCollapsed) {
      this.sideMenuCollapsed = true;
      storage.set(STORAGE_KEY, true);
    }
  },

  methods: {
    ...mapActions('infoGuide', { fetchInfoGuidePreference: 'fetchPreference' }),

    toggleSideMenu() {
      this.sideMenuCollapsed = !this.sideMenuCollapsed;
      storage.set(STORAGE_KEY, this.sideMenuCollapsed);
    },
  },
};
</script>
