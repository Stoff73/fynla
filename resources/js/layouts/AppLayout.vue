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

      <!-- Content area -->
      <main class="flex-grow bg-eggshell-500" :class="showDockedChat ? 'lg:mr-[380px]' : ''">
        <div class="max-w-7xl mx-auto py-2 sm:py-3 px-4 sm:px-6 lg:px-8">
          <slot />
        </div>
      </main>

      <Footer ref="appFooter" />
    </div>

    <!-- Docked Fyn Chat (real users, desktop) — fixed to right edge, below navbar, stops at footer -->
    <aside
      v-if="showDockedChat"
      class="hidden lg:flex lg:flex-col fixed top-16 right-0 w-[380px] border-l border-light-gray bg-white z-30 transition-all duration-100"
      :style="{ bottom: footerOffset + 'px' }"
    >
      <AiChatPanel :docked="true" />
    </aside>

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
      footerOffset: 0,
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

    // Track footer visibility for docked chat positioning
    this._updateFooterOffset = () => {
      const footer = this.$refs.appFooter?.$el;
      if (!footer) { this.footerOffset = 0; return; }
      const footerRect = footer.getBoundingClientRect();
      const viewportHeight = window.innerHeight;
      if (footerRect.top < viewportHeight) {
        this.footerOffset = viewportHeight - footerRect.top;
      } else {
        this.footerOffset = 0;
      }
    };
    window.addEventListener('scroll', this._updateFooterOffset, { passive: true });
    window.addEventListener('resize', this._updateFooterOffset, { passive: true });
  },

  beforeUnmount() {
    if (this._updateFooterOffset) {
      window.removeEventListener('scroll', this._updateFooterOffset);
      window.removeEventListener('resize', this._updateFooterOffset);
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
