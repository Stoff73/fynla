<template>
  <div class="min-h-screen flex flex-col" :class="{ 'pt-[44px]': isImpersonating }">
    <!-- Advisor Impersonation Banner -->
    <AdvisorBanner v-if="isImpersonating" />

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
      <div ref="appHeader">
        <Navbar />

        <!-- Offline Indicator Banner -->
        <OfflineBanner />

        <!-- Trial Countdown Banner (non-preview users only) -->
        <TrialCountdownBanner v-if="isAuthenticated && !isPreviewMode" />

        <!-- Data Retention Overlay (non-dismissable modal for grace period users) -->
        <DataRetentionOverlay v-if="isAuthenticated && !isPreviewMode" />

        <!-- Preview Mode Banner -->
        <PreviewBanner v-if="isPreviewMode" />
      </div>

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
      class="hidden lg:flex lg:flex-col fixed right-0 w-[380px] border-l border-light-gray bg-white z-30 transition-all duration-100"
      :style="{ top: headerOffset + 'px', bottom: footerOffset + 'px' }"
    >
      <AiChatPanel :docked="true" />
    </aside>

    <!-- Information Guide panel (button moved to Navbar) -->
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
import InfoGuidePanel from '@/components/Shared/InfoGuidePanel.vue';
import AiChatButton from '@/components/Shared/AiChatButton.vue';
import AiChatPanel from '@/components/Shared/AiChatPanel.vue';
import SideMenu from '@/components/SideMenu.vue';
import SideMenuMobileToggle from '@/components/SideMenuMobileToggle.vue';
import OfflineBanner from '@/mobile/OfflineBanner.vue';
import AdvisorBanner from '@/components/Advisor/AdvisorBanner.vue';
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
    InfoGuidePanel,
    AiChatButton,
    AiChatPanel,
    SideMenu,
    SideMenuMobileToggle,
    OfflineBanner,
    AdvisorBanner,
  },

  data() {
    return {
      sideMenuCollapsed: storage.get(STORAGE_KEY) === 'true',
      sideMenuMobileOpen: false,
      headerOffset: 64,
      footerOffset: 0,
    };
  },

  computed: {
    ...mapGetters('preview', ['isPreviewMode']),
    ...mapGetters('auth', ['isAuthenticated', 'currentUser']),

    isImpersonating() {
      return this.$store.state.advisor?.impersonating === true;
    },

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

    // Track header height + footer visibility for docked chat positioning
    this._updateChatOffsets = () => {
      // Header: measure actual height of navbar + banners
      const header = this.$refs.appHeader;
      if (header) {
        this.headerOffset = header.offsetHeight;
      }

      // Footer: adjust bottom when footer scrolls into view
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
    window.addEventListener('scroll', this._updateChatOffsets, { passive: true });
    window.addEventListener('resize', this._updateChatOffsets, { passive: true });
    // Initial measurement after DOM settles
    this.$nextTick(() => this._updateChatOffsets());
    // Re-measure when banners appear/disappear
    if (this.$refs.appHeader) {
      this._headerObserver = new MutationObserver(() => this._updateChatOffsets());
      this._headerObserver.observe(this.$refs.appHeader, { childList: true, subtree: true, attributes: true });
    }
  },

  beforeUnmount() {
    if (this._updateChatOffsets) {
      window.removeEventListener('scroll', this._updateChatOffsets);
      window.removeEventListener('resize', this._updateChatOffsets);
    }
    if (this._headerObserver) {
      this._headerObserver.disconnect();
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
