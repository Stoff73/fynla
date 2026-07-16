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
    />

    <!-- Mobile hamburger toggle -->
    <SideMenuMobileToggle @toggle="sideMenuMobileOpen = !sideMenuMobileOpen" />

    <!-- Main content wrapper with left margin for side menu -->
    <div
      class="flex flex-col min-h-screen transition-all duration-300 ease-out"
      :class="contentMarginClass"
    >
      <!-- Header is sticky so the top nav stays visible while dashboards scroll under it.
           Offset by 44px when the AdvisorBanner (fixed, z-50) is present so they don't overlap.
           z-[45] sits above the docked Fyn chat (z-40) so the nav dropdowns (Support /
           user menu) open OVER the chat, while staying below modals (z-50) and SideMenu (z-60). -->
      <div
        ref="appHeader"
        class="sticky z-[45] bg-eggshell-500"
        :class="isImpersonating ? 'top-[44px]' : 'top-0'"
      >
        <AppNavbar :subscription-data="subscriptionData" @toggle-chat="toggleChat" @open-plan-modal="showPlanModal = true" />

        <!-- Preview Mode Banner — always directly below nav -->
        <PreviewBanner v-if="isPreviewMode" />

        <!-- SubNavBar hidden globally — sibling tabs now live in the left sidebar and
             per-page CTAs live inline on each page. The component and subNavConfig.js
             are kept intact for easy re-enable (set v-if="true"). -->
        <SubNavBar v-if="false" />

        <!-- Offline Indicator Banner -->
        <OfflineBanner />

        <!-- Scheduled Deletion Banner — appears when the authenticated user has
             `deletion_scheduled_for` set (grace-period scheduled deletion).
             Renders a violet info bar with a "Cancel scheduled deletion" button. -->
        <ScheduledDeletionBanner v-if="isAuthenticated && !isPreviewMode" />

        <!-- Data Retention Overlay (non-dismissable modal for grace period users).
             Suppressed on /checkout because the user is already in the subscribe flow. -->
        <DataRetentionOverlay
          v-if="isAuthenticated && !isPreviewMode && !isOnCheckoutRoute"
          @subscribe="handleSubscribeFromOverlay"
        />
      </div>

      <!-- Content area -->
      <main class="flex-grow bg-eggshell-500 transition-all duration-300">
        <div class="py-2 sm:py-3 px-4 sm:px-6 lg:px-8">
          <slot />
        </div>
      </main>

      <AppFooter ref="appFooter" />
    </div>

    <!--
      Docked Fyn Chat (real users, desktop) — fixed to right edge, below navbar, stops at footer.
      Two widths only:
        • normal post-onboarding AND Fyn onboarding profile-review pause: 356px
        • Fyn onboarding wide (path_choice / asset_capture / etc.): 712px = double 356px,
          capped by viewport minus sidebar.
      On entering a profile-review pause the main <slot/> view is routed to /profile
      (UserProfile.vue) so the user can cross-reference the director's summary with the
      real profile surface; on resume we navigate back to wherever they were before.
      Stays right-anchored in all cases so the sidebar and chat never overlap.
    -->
    <aside
      v-if="showDockedChat"
      v-show="!chatCollapsed"
      class="hidden lg:flex lg:flex-col fixed right-0 border-l border-light-gray bg-white z-40 transition-all duration-300"
      :class="asideWidthClass"
      style="box-shadow: -6px 0 18px rgba(0, 0, 0, 0.12), 0 -4px 14px rgba(0, 0, 0, 0.06), 0 4px 14px rgba(0, 0, 0, 0.06);"
      :style="{ top: headerOffset + 'px', bottom: footerOffset + 'px' }"
    >
      <component
        ref="dockedChat"
        :is="isOnboardingRoute ? 'FynOnboardingChat' : 'AiChatPanel'"
        :docked="true"
        @collapse="toggleChat"
      />
    </aside>

    <!-- Collapsed chat strip -->
    <aside
      v-if="showDockedChat && chatCollapsed"
      class="hidden lg:flex lg:flex-col fixed right-0 w-10 bg-light-gray border-l border-light-gray z-30 items-center pt-3 gap-3 transition-all duration-300"
      :style="{ top: headerOffset + 'px', bottom: footerOffset + 'px' }"
    >
      <button
        ref="chatExpandButton"
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

    <!-- Trial Expired — plan selection.
         Suppressed on /checkout because the user is already in the subscribe flow.
         Dismissable when opened from DataRetentionOverlay (grace-period flow) so the user
         can back out and choose Delete All Data instead. -->
    <PlanSelectionModal
      v-if="showTrialExpiredModal && !isOnCheckoutRoute"
      :dismissable="planModalDismissable"
      :prefill-discount-code="lifecycleDiscountCode"
      @select="handlePlanSelect"
      @close="handleTrialModalClose"
    />

    <!-- Plan selection modal (from navbar/sidebar "Choose a Plan" / "Upgrade Now") -->
    <PlanSelectionModal
      v-if="showPlanModal && !showTrialExpiredModal"
      :current-plan="activePlanSlug"
      :prefill-discount-code="lifecycleDiscountCode"
      @select="handlePlanSelect"
      @close="showPlanModal = false"
    />
  </div>
</template>

<script>
import { mapGetters, mapActions } from 'vuex';
import AppNavbar from '@/components/AppNavbar.vue';
import AppFooter from '@/components/AppFooter.vue';
import PreviewBanner from '@/components/Preview/PreviewBanner.vue';
import ScheduledDeletionBanner from '@/components/Account/ScheduledDeletionBanner.vue';
import DataRetentionOverlay from '@/components/Payment/DataRetentionOverlay.vue';
import InfoGuidePanel from '@/components/Shared/InfoGuidePanel.vue';
import AiChatButton from '@/components/Shared/AiChatButton.vue';
import AiChatPanel from '@/components/Shared/AiChatPanel.vue';
import FynOnboardingChat from '@/components/Fyn/FynOnboardingChat.vue';
import ToastNotification from '@/components/Shared/ToastNotification.vue';
import SideMenu from '@/components/SideMenu.vue';
import SideMenuMobileToggle from '@/components/SideMenuMobileToggle.vue';
import OfflineBanner from '@/components/Common/OfflineBanner.vue';
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
    AppNavbar,
    AppFooter,
    PreviewBanner,
    ScheduledDeletionBanner,
    DataRetentionOverlay,
    InfoGuidePanel,
    AiChatButton,
    AiChatPanel,
    FynOnboardingChat,
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
      planModalDismissable: false,
      subscriptionData: null,
      isMobileView: window.innerWidth < 1024,
      // Route the user was on before the director pushed us to /profile for a
      // profile-review pause. Null when we are not currently displacing a route.
      preProfileRoute: null,
    };
  },

  computed: {
    ...mapGetters('preview', ['isPreviewMode']),
    ...mapGetters('auth', ['isAuthenticated', 'currentUser']),
    ...mapGetters('aiChat', { onboardingLayout: 'onboardingLayout', isOnboardingActive: 'isOnboardingActive' }),

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

    /**
     * True while the Fyn-driven onboarding is active. Gates the wide-chat
     * wrapper width and the profile-review route push (see asideWidthClass
     * and the onboardingLayout watcher).
     */
    isOnboardingRoute() {
      const path = this.$route?.path || '';
      if (path.startsWith('/onboarding')) return true;
      // Phase 13 — the "Quick start with Fyn" CTA launches the Fyn-driven
      // onboarding from /dashboard?openFyn=journey. Dashboard.vue strips
      // the query param immediately, leaving the app at /dashboard while
      // the onboarding director is still driving the conversation. In
      // that case the wide-chat wrapper must activate here even though the
      // URL no longer says /onboarding.
      return this.isOnboardingActive;
    },

    /**
     * Aside width class. Right-anchored always. Two sizes only:
     *   • onboarding wide state (path_choice / asset_capture, etc):
     *     712px = double the normal 356px docked width.
     *   • everything else (non-onboarding chat AND onboarding profile-
     *     review pauses): normal 356px docked width.
     */
    asideWidthClass() {
      if (this.isOnboardingRoute && this.onboardingLayout !== 'standard') {
        return 'w-[712px] max-w-[calc(100vw-15rem)]';
      }
      return 'w-[356px]';
    },

    // Only set for active paid subscribers — trial users see all plans
    activePlanSlug() {
      if (!this.subscriptionData || this.subscriptionData.status !== 'active') return null;
      return this.subscriptionData.plan;
    },

    // True when the current route is /checkout. Used to suppress the expired-trial
    // and grace-period overlays so the user can actually reach the Revolut widget.
    isOnCheckoutRoute() {
      return this.$route.path === '/checkout' || this.$route.name === 'Checkout';
    },

    // When a user arrives from a lifecycle email magic link, the controller
    // redirects them to /dashboard with ?lifecycle_discount=CODE. This
    // computed reads the query param so PlanSelectionModal can pre-populate
    // its discount field (via the prefill-discount-code prop).
    lifecycleDiscountCode() {
      const value = this.$route.query.lifecycle_discount;
      return typeof value === 'string' ? value : '';
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

    // Watch for ?openPricing=1 arriving via in-app navigation (e.g. router.push
    // after a successful restore). Mounted handles the initial-page-load case;
    // this handles SPA-internal transitions.
    '$route.query.openPricing'(value) {
      if (value) this.maybeOpenPricingFromQuery();
    },

    // Profile-review pause routing. When the onboarding director emits
    // `onboarding_layout_change { mode: 'standard' }` (entering
    // profile_review_family or profile_review_expenditure), route the main
    // <slot/> view to /profile (UserProfile.vue) so the user can see their
    // actual captured profile behind the shrunken chat. When the director
    // emits `{ mode: 'wide' }` on confirmation, return to whichever route
    // they were on before the pause (typically /dashboard).
    //
    // The chat itself lives in a fixed <aside> and is unaffected by the
    // <router-view> change; Vuex state (`aiChat`) persists across routes.
    onboardingLayout(newLayout) {
      if (!this.isOnboardingRoute) return;
      if (newLayout === 'standard') {
        if (this.$route.path !== '/profile') {
          this.preProfileRoute = this.$route.fullPath;
          this.$router.push('/profile').catch(() => {});
        }
      } else if (newLayout === 'wide') {
        // Returning from a profile-review pause. Prefer the stored pre-pause
        // route; fall back to /dashboard (where onboarding is always driven
        // from) if nothing was stored. Only navigate if we're currently on
        // /profile — on the very first wide event of a session we'd already
        // be on /dashboard and a spurious push would no-op-or-worse.
        if (this.$route.path === '/profile') {
          const target = this.preProfileRoute || '/dashboard';
          this.preProfileRoute = null;
          this.$router.push(target).catch(() => {});
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

    // Honour ?openPricing=1 — set by RestoreAccountController after a
    // successful account restore, and reusable elsewhere when we want to
    // pop the plan-selection modal on landing without forcing the user to
    // click through DataRetentionOverlay first.
    this.maybeOpenPricingFromQuery();

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
        this.$nextTick(() => this.$refs.chatExpandButton?.focus());
      } else {
        this.$nextTick(() => this.$refs.dockedChat?.focusInput?.());
      }
    },

    openChat() {
      this.chatCollapsed = false;
      storage.set('fynChatCollapsed', false);
    },

    async checkTrialStatus() {
      if (this.isPreviewMode) return;
      try {
        const response = await api.get('/payment/subscription-status');
        this.subscriptionData = response.data;
        this.$store.commit('auth/setSubscriptionData', response.data);
        if (!response.data.has_subscription) return;
        const status = response.data.status;
        // For grace-period users, DataRetentionOverlay is the primary surface.
        // PlanSelectionModal opens from its "Subscribe Now" button via handleSubscribeFromOverlay.
        // Only auto-show the non-dismissable plan modal for non-grace expired users.
        if (status !== 'trialing' && status !== 'active' && !response.data.is_in_grace_period) {
          this.planModalDismissable = false;
          this.showTrialExpiredModal = true;
        }
      } catch {
        // Silently fail
      }
    },

    handleSubscribeFromOverlay() {
      this.planModalDismissable = true;
      this.showTrialExpiredModal = true;
    },

    // Opens PlanSelectionModal when the URL carries ?openPricing=1. Used by
    // the post-restore redirect (see RestoreAccountController). Reuses the
    // same dismissable-plan-modal path as DataRetentionOverlay's "Subscribe
    // Now" CTA so the modal sits above the grace-period overlay if present.
    // Strips the query param after triggering so a refresh / back-nav does
    // not re-pop the modal.
    maybeOpenPricingFromQuery() {
      if (!this.$route.query.openPricing) return;
      if (!this.isAuthenticated || this.isPreviewMode) return;

      this.handleSubscribeFromOverlay();

      const rest = { ...this.$route.query };
      delete rest.openPricing;
      this.$router
        .replace({ path: this.$route.path, query: rest })
        .catch(() => {});
    },

    handleTrialModalClose() {
      this.showTrialExpiredModal = false;
    },

    handlePlanSelect({ plan, billingCycle, isUpgrade, discountCode }) {
      this.showTrialExpiredModal = false;
      this.showPlanModal = false;
      const upgradeParam = isUpgrade ? '&upgrade=true' : '';
      // Thread the discount code through to the checkout page — its
      // prefilledDiscountCode computed reads $route.query.discount and
      // auto-validates + applies before creating the Revolut order.
      const discountParam = discountCode ? `&discount=${encodeURIComponent(discountCode)}` : '';
      this.$router.push(`/checkout?plan=${plan}&cycle=${billingCycle}${upgradeParam}${discountParam}`);
    },
  },
};
</script>
