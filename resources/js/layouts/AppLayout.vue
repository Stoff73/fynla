<template>
  <div class="min-h-screen flex flex-col" :class="{ 'pt-[44px]': isImpersonating }">
    <!-- Advisor Impersonation Banner -->
    <AdvisorBanner v-if="isImpersonating" />

    <!-- Side Menu (teleported to body) -->
    <SideMenu
      :collapsed="sideMenuCollapsed"
      :mobile-open="sideMenuMobileOpen"
      :subscription-data="subscriptionData"
      @toggle="toggleSideMenu"
      @update:mobile-open="sideMenuMobileOpen = $event"
      @open-plan-modal="showPlanModal = true"
    />

    <!-- Mobile hamburger toggle -->
    <SideMenuMobileToggle @toggle="sideMenuMobileOpen = !sideMenuMobileOpen" />

    <!-- Main content wrapper with left margin for side menu -->
    <div
      class="flex flex-col min-h-screen transition-all duration-300 ease-out"
      :class="contentMarginClass"
    >
      <div ref="appHeader">
        <Navbar :subscription-data="subscriptionData" @toggle-chat="toggleChat" @open-plan-modal="showPlanModal = true" />

        <!-- Preview Mode Banner — always directly below nav -->
        <PreviewBanner v-if="isPreviewMode" />

        <SubNavBar />

        <!-- Offline Indicator Banner -->
        <OfflineBanner />

        <!-- Data Retention Overlay (non-dismissable modal for grace period users) -->
        <DataRetentionOverlay v-if="isAuthenticated && !isPreviewMode" />
      </div>

      <!-- Content area -->
      <main class="flex-grow bg-eggshell-500">
        <div class="py-2 sm:py-3 px-4 sm:px-6 lg:px-8">
          <slot />
        </div>
      </main>

      <Footer ref="appFooter" />
    </div>

    <!-- Docked Fyn Chat (real users, desktop) — fixed to right edge, below navbar, stops at footer -->
    <!-- Expanded chat panel -->
    <aside
      v-if="showDockedChat && !chatCollapsed"
      class="hidden lg:flex lg:flex-col fixed right-0 w-[356px] border-l border-light-gray bg-white z-40 transition-all duration-300"
      style="box-shadow: -6px 0 18px rgba(0, 0, 0, 0.12), 0 -4px 14px rgba(0, 0, 0, 0.06), 0 4px 14px rgba(0, 0, 0, 0.06);"
      :style="{ top: headerOffset + 'px', bottom: footerOffset + 'px' }"
    >
      <AiChatPanel :docked="true" @collapse="toggleChat" />
    </aside>

    <!-- Collapsed chat strip -->
    <aside
      v-if="showDockedChat && chatCollapsed"
      class="hidden lg:flex lg:flex-col fixed right-0 w-10 bg-light-gray border-l border-light-gray z-30 items-center pt-3 gap-3 transition-all duration-300"
      :style="{ top: headerOffset + 'px', bottom: footerOffset + 'px' }"
    >
      <button
        @click="toggleChat"
        class="w-7 h-7 flex items-center justify-center rounded-md bg-light-blue-100 text-horizon-500 hover:bg-light-blue-500 hover:text-white transition-colors"
        title="Expand Fyn chat"
      >
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19l-7-7 7-7m8 14l-7-7 7-7" />
        </svg>
      </button>
      <img :src="fynIconUrl" alt="Fyn" class="w-7 h-7 rounded-full" />
    </aside>

    <!-- Information Guide panel (button moved to Navbar) -->
    <InfoGuidePanel />

    <!-- AI Chat floating button + panel (real users only, hidden when docked chat is active on desktop) -->
    <AiChatButton v-if="!showDockedChat && !isPreviewMode" />
    <AiChatPanel v-if="(!showDockedChat || isMobileView) && !isPreviewMode" />

    <!-- Global toast notifications -->
    <ToastNotification />

    <!-- Trial Expired — non-dismissable plan selection -->
    <PlanSelectionModal
      v-if="showTrialExpiredModal"
      :dismissable="false"
      @select="handlePlanSelect"
    />

    <!-- Plan selection modal (from navbar/sidebar "Choose a Plan" / "Upgrade Now") -->
    <PlanSelectionModal
      v-if="showPlanModal && !showTrialExpiredModal"
      :current-plan="activePlanSlug"
      @select="handlePlanSelect"
      @close="showPlanModal = false"
    />
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
import ToastNotification from '@/components/Shared/ToastNotification.vue';
import SideMenu from '@/components/SideMenu.vue';
import SideMenuMobileToggle from '@/components/SideMenuMobileToggle.vue';
import OfflineBanner from '@/mobile/OfflineBanner.vue';
import AdvisorBanner from '@/components/Advisor/AdvisorBanner.vue';
import SubNavBar from '@/components/SubNavBar.vue';
import PlanSelectionModal from '@/components/Payment/PlanSelectionModal.vue';
import api from '@/services/api';
import storage from '@/utils/storage';
import { fynIconUrl } from '@/constants/fynIcon';

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
    SubNavBar,
    PlanSelectionModal,
    ToastNotification,
  },

  data() {
    return {
      sideMenuCollapsed: storage.get(STORAGE_KEY) === 'true',
      sideMenuMobileOpen: false,
      fynIconUrl,
      chatCollapsed: storage.get('fynChatCollapsed') === null ? true : storage.get('fynChatCollapsed') === 'true',
      headerOffset: 64,
      footerOffset: 0,
      showTrialExpiredModal: false,
      showPlanModal: false,
      subscriptionData: null,
      isMobileView: window.innerWidth < 1024,
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

    // Only set for active paid subscribers — trial users see all plans
    activePlanSlug() {
      if (!this.subscriptionData || this.subscriptionData.status !== 'active') return null;
      return this.subscriptionData.plan;
    },
  },

  watch: {
    // Collapse side menu when docked chat becomes active and expanded
    showDockedChat(active) {
      if (active && !this.chatCollapsed && !this.sideMenuCollapsed) {
        this.sideMenuCollapsed = true;
        storage.set(STORAGE_KEY, true);
      }
    },

    // Re-fetch subscription data on route change, throttled to once per 5 minutes
    '$route.path'() {
      if (this.isAuthenticated && !this.isPreviewMode) {
        const now = Date.now();
        if (!this._lastTrialCheck || now - this._lastTrialCheck > 300000) {
          this._lastTrialCheck = now;
          this.checkTrialStatus();
        }
      }
    },
  },

  mounted() {
    if (this.isAuthenticated || this.isPreviewMode) {
      this.fetchInfoGuidePreference();
    }
    if (this.isAuthenticated) {
      this.checkTrialStatus();
    }

    // Listen for Fyn chat toggle from child views (e.g. Dashboard)
    this._onFynToggle = () => this.toggleChat();
    window.addEventListener('fyn-toggle-chat', this._onFynToggle);

    // Listen for explicit open-chat requests (e.g. from registration via Fyn)
    this._onFynOpen = () => this.openChat();
    window.addEventListener('fyn-open-chat', this._onFynOpen);

    // Note: do NOT auto-collapse side menu here — AppLayout remounts on every
    // route change, which would override the user's explicit expand/collapse choice.
    // The watcher on showDockedChat handles the initial collapse when chat first opens.

    // Track mobile view state for floating chat on small screens
    this._updateMobileView = () => { this.isMobileView = window.innerWidth < 1024; };
    window.addEventListener('resize', this._updateMobileView);

    // Track header height + footer visibility for docked chat positioning
    this._updateChatOffsets = () => {
      // Header: use visible portion of header (shrinks to 0 as header scrolls out)
      const header = this.$refs.appHeader;
      if (header) {
        const headerRect = header.getBoundingClientRect();
        this.headerOffset = Math.max(0, headerRect.bottom);
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
    if (this._updateMobileView) {
      window.removeEventListener('resize', this._updateMobileView);
    }
    if (this._updateChatOffsets) {
      window.removeEventListener('scroll', this._updateChatOffsets);
      window.removeEventListener('resize', this._updateChatOffsets);
    }
    if (this._headerObserver) {
      this._headerObserver.disconnect();
    }
    if (this._onFynToggle) {
      window.removeEventListener('fyn-toggle-chat', this._onFynToggle);
    }
  },

  methods: {
    ...mapActions('infoGuide', { fetchInfoGuidePreference: 'fetchPreference' }),

    toggleSideMenu() {
      this.sideMenuCollapsed = !this.sideMenuCollapsed;
      storage.set(STORAGE_KEY, this.sideMenuCollapsed);
    },

    toggleChat() {
      // On mobile (below lg breakpoint), open the floating chat panel instead of docked
      if (window.innerWidth < 1024) {
        this.$store.dispatch('aiChat/toggle');
        return;
      }
      this.chatCollapsed = !this.chatCollapsed;
      storage.set('fynChatCollapsed', this.chatCollapsed);
      if (this.chatCollapsed) {
        window.dispatchEvent(new Event('fyn-chat-interaction'));
      }
    },

    openChat() {
      this.chatCollapsed = false;
      storage.set('fynChatCollapsed', false);
    },

    async checkTrialStatus() {
      if (this.isPreviewMode) return;
      try {
        const response = await api.get('/payment/trial-status');
        this.subscriptionData = response.data;
        if (!response.data.has_subscription) return;
        const status = response.data.status;
        if (status !== 'trialing' && status !== 'active') {
          this.showTrialExpiredModal = true;
        }
      } catch {
        // Silently fail
      }
    },

    handlePlanSelect({ plan, billingCycle, isUpgrade }) {
      this.showTrialExpiredModal = false;
      this.showPlanModal = false;
      const upgradeParam = isUpgrade ? '&upgrade=true' : '';
      this.$router.push(`/checkout?plan=${plan}&cycle=${billingCycle}${upgradeParam}`);
    },
  },
};
</script>
