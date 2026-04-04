<template>
  <Teleport to="body">
    <!-- Mobile backdrop -->
    <Transition
      enter-active-class="transition ease-out duration-300"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition ease-in duration-200"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div
        v-if="mobileOpen"
        class="fixed inset-0 bg-black/50 z-[59] sm:hidden"
        @click="closeMobile"
      ></div>
    </Transition>

    <!-- Side menu -->
    <nav
      class="fixed top-0 bottom-0 left-0 z-[60] bg-white border-r border-light-gray shadow-lg flex flex-col overflow-hidden"
      :class="[
        menuWidthClass,
        mobileOpen ? 'translate-x-0' : '-translate-x-full sm:translate-x-0',
        'transition-all duration-300 ease-out'
      ]"
    >
      <!-- Logo -->
      <div class="flex items-center h-16 border-b border-light-gray flex-shrink-0" :class="effectiveCollapsed ? 'justify-center px-2' : 'pl-12 sm:pl-4 pr-4'">
        <router-link to="/dashboard" class="flex items-center flex-shrink-0 overflow-hidden" @click="closeMobile">
          <!-- Collapsed: just favicon -->
          <img v-if="effectiveCollapsed" :src="faviconUrl" alt="Fynla" class="h-8 w-8" />
          <!-- Expanded: full logo -->
          <img v-else :src="logoUrl" alt="Fynla" class="h-14 w-auto" />
        </router-link>
      </div>

      <!-- Collapse toggle (desktop only) -->
      <button
        @click="toggleCollapsed"
        class="hidden sm:flex items-center justify-center h-8 mx-2 mt-2 mb-1 rounded-md bg-light-blue-100 text-horizon-500 hover:bg-light-blue-500 hover:text-white transition-colors flex-shrink-0"
        :title="collapsed ? 'Expand menu' : 'Collapse menu'"
      >
        <svg class="w-5 h-5 transition-transform duration-300" :class="collapsed ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19l-7-7 7-7m8 14l-7-7 7-7" />
        </svg>
      </button>

      <!-- Navigation items -->
      <div class="flex-1 overflow-y-auto py-2 scrollbar-hide">

        <!-- Dashboard & Net Worth (no section heading) -->
        <div class="mb-1">
          <div class="flex flex-col pt-1">
            <SideMenuItem icon="home" label="Dashboard" to="/dashboard" :collapsed="effectiveCollapsed" :active="isExactActive('/dashboard')" :active-colour="currentStage ? stageColour : ''" @navigate="closeMobile" />
            <SideMenuItem icon="chart-bar" label="Net Worth" to="/net-worth/wealth-summary" :collapsed="effectiveCollapsed" :active="isNetWorthActive" :active-colour="currentStage ? stageColour : ''" @navigate="closeMobile" />
          </div>
          <div v-if="effectiveCollapsed" class="mx-3 my-2 border-t border-light-gray"></div>
        </div>

        <!-- Cash Management -->
        <SideMenuSection label="Cash Management" :collapsed="effectiveCollapsed" :expanded="isSectionExpanded('cashManagement')" @toggle="toggleSection('cashManagement')">
          <SideMenuItem icon="banknotes" label="Bank Accounts" to="/net-worth/cash" :collapsed="effectiveCollapsed" :active="isActive('/net-worth/cash')" :active-colour="currentStage ? stageColour : ''" @navigate="closeMobile" />
          <SideMenuItem icon="currency-pound" label="Income" :to="{ path: '/valuable-info', query: { section: 'income' } }" :collapsed="effectiveCollapsed" :active="isValuableInfoSection('income')" :active-colour="currentStage ? stageColour : ''" @navigate="closeMobile" />
          <SideMenuItem icon="arrow-up-tray" label="Expenditure" :to="{ path: '/valuable-info', query: { section: 'expenditure' } }" :collapsed="effectiveCollapsed" :active="isValuableInfoSection('expenditure')" :active-colour="currentStage ? stageColour : ''" @navigate="closeMobile" />
        </SideMenuSection>

        <!-- Finances -->
        <SideMenuSection label="Finances" :collapsed="effectiveCollapsed" :expanded="isSectionExpanded('finances')" @toggle="toggleSection('finances')">
          <SideMenuItem icon="trending-up" label="Investments" to="/net-worth/investments" :collapsed="effectiveCollapsed" :active="isInvestmentsActive" :active-colour="currentStage ? stageColour : ''" @navigate="closeMobile" />
          <SideMenuItem icon="clock" label="Retirement" to="/net-worth/retirement" :collapsed="effectiveCollapsed" :active="isActive('/net-worth/retirement')" :active-colour="currentStage ? stageColour : ''" @navigate="closeMobile" />
          <SideMenuItem icon="home-modern" label="Property" to="/net-worth/property" :collapsed="effectiveCollapsed" :active="isActive('/net-worth/property')" :active-colour="currentStage ? stageColour : ''" :locked="isLocked('standard')" requiredPlan="Standard" @navigate="closeMobile" />
          <SideMenuItem icon="credit-card" label="Liabilities" to="/net-worth/liabilities" :collapsed="effectiveCollapsed" :active="isLiabilitiesActive" :active-colour="currentStage ? stageColour : ''" :locked="isLocked('standard')" requiredPlan="Standard" @navigate="closeMobile" />
          <SideMenuItem icon="cube" label="Personal Valuables" to="/net-worth/chattels" :collapsed="effectiveCollapsed" :active="isActive('/net-worth/chattels')" :active-colour="currentStage ? stageColour : ''" :locked="isLocked('standard')" requiredPlan="Standard" @navigate="closeMobile" />
          <SideMenuItem icon="chart-pie" label="Risk Profile" to="/risk-profile" :collapsed="effectiveCollapsed" :active="isActive('/risk-profile')" :active-colour="currentStage ? stageColour : ''" @navigate="closeMobile" />
          <SideMenuItem icon="briefcase" label="Business" to="/net-worth/business" :collapsed="effectiveCollapsed" :active="isActive('/net-worth/business')" :active-colour="currentStage ? stageColour : ''" :locked="isLocked('standard')" requiredPlan="Standard" @navigate="closeMobile" />
        </SideMenuSection>

        <!-- Family (has spouse) / Admin (no spouse) -->
        <SideMenuSection :label="hasSpouse ? 'Family' : 'Personal Affairs'" :collapsed="effectiveCollapsed" :expanded="isSectionExpanded('family')" @toggle="toggleSection('family')">
          <SideMenuItem icon="shield-check" label="Protection" to="/protection" :collapsed="effectiveCollapsed" :active="isActive('/protection')" :active-colour="currentStage ? stageColour : ''" @navigate="closeMobile" />
          <SideMenuItem icon="document-check" label="Will" to="/estate/will-builder" :collapsed="effectiveCollapsed" :active="isWillBuilderActive" :active-colour="currentStage ? stageColour : ''" :locked="isLocked('pro')" requiredPlan="Pro" @navigate="closeMobile" />
          <SideMenuItem icon="envelope" :label="hasSpouse ? 'Letter to Spouse' : 'Expression of Wishes'" :to="{ path: '/valuable-info', query: { section: 'letter' } }" :collapsed="effectiveCollapsed" :active="isValuableInfoSection('letter')" :active-colour="currentStage ? stageColour : ''" :locked="isLocked('standard')" requiredPlan="Standard" @navigate="closeMobile" />
          <SideMenuItem icon="building-library" label="Trusts" to="/trusts" :collapsed="effectiveCollapsed" :active="isActive('/trusts')" :active-colour="currentStage ? stageColour : ''" :locked="isLocked('pro')" requiredPlan="Pro" @navigate="closeMobile" />
          <SideMenuItem icon="document-text" label="Estate Planning" to="/estate" :collapsed="effectiveCollapsed" :active="isEstateActive" :active-colour="currentStage ? stageColour : ''" :locked="isLocked('pro')" requiredPlan="Pro" @navigate="closeMobile" />
          <SideMenuItem icon="key" label="Power of Attorney" to="/estate/power-of-attorney" :collapsed="effectiveCollapsed" :active="isLpaActive" :active-colour="currentStage ? stageColour : ''" :locked="isLocked('pro')" requiredPlan="Pro" @navigate="closeMobile" />
        </SideMenuSection>

        <!-- Planning -->
        <SideMenuSection label="Planning" :collapsed="effectiveCollapsed" :expanded="isSectionExpanded('planning')" @toggle="toggleSection('planning')">
          <SideMenuItem icon="puzzle-piece" label="Holistic Plan" to="/holistic-plan" :collapsed="effectiveCollapsed" :active="isActive('/holistic-plan')" :active-colour="currentStage ? stageColour : ''" :locked="isLocked('pro')" requiredPlan="Pro" @navigate="closeMobile" />
          <SideMenuItem icon="clipboard-list" label="Plans" to="/plans" :collapsed="effectiveCollapsed" :active="isActive('/plans')" :active-colour="currentStage ? stageColour : ''" @navigate="closeMobile" />
          <SideMenuItem icon="map" label="Journeys" to="/planning/journeys" :collapsed="effectiveCollapsed" :active="isActive('/planning/journeys')" :active-colour="currentStage ? stageColour : ''" @navigate="closeMobile" />
          <SideMenuItem icon="beaker" label="What If Scenarios" to="/planning/what-if" :collapsed="effectiveCollapsed" :active="isActive('/planning/what-if')" :active-colour="currentStage ? stageColour : ''" :locked="isLocked('standard')" requiredPlan="Standard" @navigate="closeMobile" />
          <SideMenuItem icon="flag" label="Goals" to="/goals" :collapsed="effectiveCollapsed" :active="isGoalsOverviewActive" :active-colour="currentStage ? stageColour : ''" @navigate="closeMobile" />
          <SideMenuItem icon="calendar" label="Life Events" :to="{ path: '/goals', query: { tab: 'events' } }" :collapsed="effectiveCollapsed" :active="isGoalsEventsActive" :active-colour="currentStage ? stageColour : ''" @navigate="closeMobile" />
          <SideMenuItem icon="lightning-bolt" label="Actions" to="/actions" :collapsed="effectiveCollapsed" :active="isActive('/actions')" :active-colour="currentStage ? stageColour : ''" @navigate="closeMobile" />
        </SideMenuSection>

        <!-- Advisor (conditional) -->
        <SideMenuSection v-if="isAdvisor" label="Advisor" :collapsed="effectiveCollapsed" :expanded="isSectionExpanded('advisorPanel')" @toggle="toggleSection('advisorPanel')">
          <SideMenuItem icon="briefcase" label="Advisor Dashboard" to="/advisor" :collapsed="effectiveCollapsed" :active="isActive('/advisor')" @navigate="closeMobile" />
        </SideMenuSection>

        <!-- Admin (conditional) -->
        <SideMenuSection v-if="isAdmin" label="Admin" :collapsed="effectiveCollapsed" :expanded="isSectionExpanded('adminPanel')" @toggle="toggleSection('adminPanel')">
          <SideMenuItem icon="shield-exclamation" label="Admin Panel" to="/admin" :collapsed="effectiveCollapsed" :active="isActive('/admin')" @navigate="closeMobile" />
        </SideMenuSection>
      </div>

      <!-- Upgrade / Sign Up link -->
      <div v-if="showUpgradeLink" class="border-t border-light-gray p-2 flex-shrink-0">
        <router-link
          v-if="isPreviewMode"
          to="/register"
          class="flex items-center w-full rounded-md px-3 py-2.5 text-raspberry-500 hover:text-raspberry-600 hover:bg-savannah-100 transition-colors"
          :class="effectiveCollapsed ? 'justify-center' : ''"
          :title="effectiveCollapsed ? 'Sign Up Now' : ''"
          @click="closeMobile"
        >
          <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18" />
          </svg>
          <span v-if="!effectiveCollapsed" class="ml-3 text-sm font-medium whitespace-nowrap">Sign Up Now</span>
        </router-link>
        <button
          v-else
          class="flex items-center w-full rounded-md px-3 py-2.5 text-raspberry-500 hover:text-raspberry-600 hover:bg-savannah-100 transition-colors"
          :class="effectiveCollapsed ? 'justify-center' : ''"
          :title="effectiveCollapsed ? (subscriptionData && subscriptionData.status === 'trialing' ? 'Choose a Plan' : 'Upgrade Now') : ''"
          @click="$emit('open-plan-modal'); closeMobile()"
        >
          <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18" />
          </svg>
          <span v-if="!effectiveCollapsed" class="ml-3 text-sm font-medium whitespace-nowrap">{{ subscriptionData && subscriptionData.status === 'trialing' ? 'Choose a Plan' : 'Upgrade Now' }}</span>
        </button>
      </div>

      <!-- Account + Logout -->
      <div class="border-t border-light-gray p-2 flex-shrink-0">
        <router-link
          to="/settings"
          class="flex items-center w-full rounded-md px-3 py-2.5 text-neutral-500 hover:bg-savannah-100 hover:text-horizon-500 transition-colors"
          :class="effectiveCollapsed ? 'justify-center' : ''"
          :title="effectiveCollapsed ? 'Account' : ''"
          @click="closeMobile"
        >
          <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
          </svg>
          <span v-if="!effectiveCollapsed" class="ml-3 text-sm font-medium whitespace-nowrap">Account</span>
        </router-link>
        <button
          @click="handleLogout"
          class="flex items-center w-full rounded-md px-3 py-2.5 text-neutral-500 hover:bg-savannah-100 hover:text-horizon-500 transition-colors"
          :class="effectiveCollapsed ? 'justify-center' : ''"
          :title="effectiveCollapsed ? 'Sign Out' : ''"
        >
          <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
          </svg>
          <span v-if="!effectiveCollapsed" class="ml-3 text-sm font-medium whitespace-nowrap">Sign Out</span>
        </button>
      </div>
    </nav>

    <!-- Bug Report Modal -->
    <BugReportModal :show="showBugReportModal" @close="showBugReportModal = false" />
  </Teleport>
</template>

<script>
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue';
import { useStore } from 'vuex';
import { useRoute, useRouter } from 'vue-router';
import SideMenuItem from './SideMenuItem.vue';
import SideMenuSection from './SideMenuSection.vue';
import BugReportModal from './BugReportModal.vue';
import { stopInactivityTimer } from '@/services/sessionLifecycleService';
import storage from '@/utils/storage';

import logger from '@/utils/logger';
import { hasFeatureAccess } from '@/constants/featureGating';
export default {
  name: 'SideMenu',

  components: {
    SideMenuItem,
    SideMenuSection,
    BugReportModal,
  },

  props: {
    collapsed: {
      type: Boolean,
      default: false,
    },
    mobileOpen: {
      type: Boolean,
      default: false,
    },
    subscriptionData: {
      type: Object,
      default: null,
    },
  },

  emits: ['toggle', 'update:mobileOpen', 'open-plan-modal'],

  setup(props, { emit }) {
    const store = useStore();
    const route = useRoute();
    const router = useRouter();

    const logoUrl = '/images/logos/LogoHiResFynlaDark.png';
    const faviconUrl = '/images/logos/favicon.png';
    const showBugReportModal = ref(false);
    const isAdmin = computed(() => store.getters['auth/isAdmin']);
    const isAdvisor = computed(() => store.getters['auth/isAdvisor']);
    const isPreviewMode = computed(() => store.getters['preview/isPreviewMode']);
    const hasSpouse = computed(() => {
      if (isPreviewMode.value) {
        return store.getters['preview/hasSpouse'];
      }
      return store.getters['spousePermission/hasSpouse'];
    });

    // ---------------------------------------------------------------
    // Life stage getters
    // ---------------------------------------------------------------
    const currentStage = computed(() => store.getters['lifeStage/currentStage']);
    const stageLabel = computed(() => store.getters['lifeStage/stageLabel']);
    const stageColour = computed(() => store.getters['lifeStage/stageColour']);
    const lifeStageLoading = computed(() => store.state.lifeStage?.loading ?? true);
    const progressPercentage = computed(() => store.getters['lifeStage/progressPercentage']);
    // Only show progress section once data has loaded to prevent 0% flash
    const showProgress = computed(() => currentStage.value && !lifeStageLoading.value);

    // Colour class mappings — Tailwind JIT needs full class names
    const COLOUR_CLASSES = {
      text: {
        'violet': 'text-violet-500',
        'spring': 'text-spring-500',
        'raspberry': 'text-raspberry-500',
        'light-blue': 'text-light-blue-500',
        'horizon': 'text-horizon-500',
      },
      bg: {
        'violet': 'bg-violet-500',
        'spring': 'bg-spring-500',
        'raspberry': 'bg-raspberry-500',
        'light-blue': 'bg-light-blue-500',
        'horizon': 'bg-horizon-500',
      },
      stroke: {
        'violet': 'stroke-violet-500',
        'spring': 'stroke-spring-500',
        'raspberry': 'stroke-raspberry-500',
        'light-blue': 'stroke-light-blue-500',
        'horizon': 'stroke-horizon-500',
      },
      activeFlyout: {
        'violet': 'bg-violet-50 text-violet-700',
        'spring': 'bg-spring-50 text-spring-700',
        'raspberry': 'bg-raspberry-50 text-raspberry-700',
        'light-blue': 'bg-light-blue-100 text-horizon-700',
        'horizon': 'bg-horizon-100 text-horizon-700',
      },
    };

    const stageLabelColourClass = computed(() => 'text-horizon-500');
    const progressBarColourClass = computed(() => 'bg-raspberry-500');
    const progressRingColourClass = computed(() => 'stroke-raspberry-500');
    // ---------------------------------------------------------------
    // Active state detection (used by both legacy and stage layouts)
    // ---------------------------------------------------------------
    // On mobile overlay, always show expanded (not collapsed)
    const effectiveCollapsed = computed(() => {
      if (props.mobileOpen) return false;
      return props.collapsed;
    });

    const menuWidthClass = computed(() => {
      if (props.mobileOpen) return 'w-56';
      return props.collapsed ? 'w-16' : 'w-56';
    });

    const currentPath = computed(() => route.path);

    const isExactActive = (path) => currentPath.value === path;

    const isActive = (prefix) => currentPath.value.startsWith(prefix);

    const isValuableInfoSection = (section) => {
      return currentPath.value.startsWith('/valuable-info') && route.query.section === section;
    };

    // Net Worth active when on /net-worth but NOT on any dedicated module sub-paths
    const isNetWorthActive = computed(() => {
      const path = currentPath.value;
      if (!path.startsWith('/net-worth')) return false;
      if (path.startsWith('/net-worth/retirement')) return false;
      if (path.startsWith('/net-worth/investments')) return false;
      if (path.startsWith('/net-worth/investment-detail')) return false;
      if (path.startsWith('/net-worth/tax-efficiency')) return false;
      if (path.startsWith('/net-worth/holdings-detail')) return false;
      if (path.startsWith('/net-worth/fees-detail')) return false;
      if (path.startsWith('/net-worth/business')) return false;
      if (path.startsWith('/net-worth/cash')) return false;
      if (path.startsWith('/net-worth/chattels')) return false;
      if (path.startsWith('/net-worth/property')) return false;
      if (path.startsWith('/net-worth/liabilities')) return false;
      return true;
    });

    // Investments active for investments and related sub-paths
    const isInvestmentsActive = computed(() => {
      const path = currentPath.value;
      return path.startsWith('/net-worth/investments') ||
             path.startsWith('/net-worth/investment-detail') ||
             path.startsWith('/net-worth/tax-efficiency') ||
             path.startsWith('/net-worth/holdings-detail') ||
             path.startsWith('/net-worth/fees-detail');
    });

    // Liabilities active
    const isLiabilitiesActive = computed(() => {
      return currentPath.value.startsWith('/net-worth/liabilities');
    });

    // Estate active for /estate routes (but not LPA or will-builder sub-paths)
    const isEstateActive = computed(() => {
      if (currentPath.value.startsWith('/estate/lpa')) return false;
      if (currentPath.value.startsWith('/estate/power-of-attorney')) return false;
      if (currentPath.value.startsWith('/estate/will-builder')) return false;
      return currentPath.value.startsWith('/estate');
    });

    // Will Builder active
    const isWillBuilderActive = computed(() => {
      return currentPath.value.startsWith('/estate/will-builder');
    });

    // LPA active for /estate/power-of-attorney or /estate/lpa/* routes
    const isLpaActive = computed(() => {
      return currentPath.value.startsWith('/estate/power-of-attorney') ||
             currentPath.value.startsWith('/estate/lpa');
    });

    // Goals overview active (on /goals without tab=events)
    const isGoalsOverviewActive = computed(() => {
      return currentPath.value.startsWith('/goals') && route.query.tab !== 'events';
    });

    // Goals events tab active
    const isGoalsEventsActive = computed(() => {
      return currentPath.value.startsWith('/goals') && route.query.tab === 'events';
    });

    // ---------------------------------------------------------------
    // Section expand/collapse state
    // ---------------------------------------------------------------
    const STORAGE_KEY = 'sideMenuExpandedSections';
    const expandedSections = ref({});

    const INIT_KEY = 'sideMenuInitialised';

    const loadExpandedState = () => {
      try {
        const stored = storage.get(STORAGE_KEY);
        if (stored) {
          expandedSections.value = JSON.parse(stored);
        }
        // On first ever visit, all sections start collapsed (empty object is default)
      } catch {
        expandedSections.value = {};
      }
    };

    const saveExpandedState = () => {
      try {
        storage.set(STORAGE_KEY, JSON.stringify(expandedSections.value));
      } catch {
        // Silently fail
      }
    };

    // Determine which section the current route belongs to
    const activeSectionKey = computed(() => {
      const path = currentPath.value;
      const section = route.query.section;

      if (path.startsWith('/net-worth/cash') ||
          (path.startsWith('/valuable-info') && (section === 'income' || section === 'expenditure'))) {
        return 'cashManagement';
      }
      if (isInvestmentsActive.value ||
          path.startsWith('/net-worth/retirement') ||
          path.startsWith('/net-worth/property') ||
          path.startsWith('/net-worth/liabilities') ||
          path.startsWith('/net-worth/chattels') ||
          path.startsWith('/risk-profile') ||
          path.startsWith('/net-worth/business')) {
        return 'finances';
      }
      if (path.startsWith('/protection') ||
          isEstateActive.value ||
          isWillBuilderActive.value ||
          path.startsWith('/trusts') ||
          (path.startsWith('/valuable-info') && section === 'letter')) {
        return 'family';
      }
      if (path.startsWith('/holistic-plan') ||
          path.startsWith('/plans') ||
          path.startsWith('/planning/') ||
          path.startsWith('/goals') ||
          path.startsWith('/actions')) {
        return 'planning';
      }
      if (path.startsWith('/profile') || path.startsWith('/settings')) {
        return 'account';
      }
      if (path.startsWith('/advisor')) {
        return 'advisorPanel';
      }
      if (path.startsWith('/admin')) {
        return 'adminPanel';
      }
      return null;
    });

    const toggleSection = (key) => {
      expandedSections.value = { ...expandedSections.value, [key]: !expandedSections.value[key] };
      saveExpandedState();
    };

    const isSectionExpanded = (key) => {
      return expandedSections.value[key] || false;
    };

    const PENDING_EXPAND_KEY = 'sideMenuPendingExpand';

    const toggleCollapsed = () => {
      emit('toggle');
    };

    const closeMobile = () => {
      // If menu is collapsed on desktop and user clicked a nav item,
      // set a flag so the next mount auto-expands the menu + section
      if (props.collapsed && !props.mobileOpen) {
        storage.set(PENDING_EXPAND_KEY, 'true');
      }
      if (props.mobileOpen) {
        emit('update:mobileOpen', false);
      }
    };

    const openBugReport = () => {
      showBugReportModal.value = true;
      closeMobile();
    };

    const handleLogout = async () => {
      closeMobile();
      try {
        stopInactivityTimer();
        await store.dispatch('auth/logout');
        if (!router.currentRoute.value.meta?.public) {
          router.push('/login');
        }
      } catch (error) {
        logger.error('Logout error:', error);
        if (!router.currentRoute.value.meta?.public) {
          router.push('/login');
        }
      }
    };

    // Close mobile menu on Escape key
    const handleKeydown = (e) => {
      if (e.key === 'Escape' && props.mobileOpen) {
        closeMobile();
      }
    };

    // Subscription data for upgrade button visibility (from AppLayout prop)
    // Only filter plans for active paid subscribers — trial users see all plans
    const currentPlanSlug = computed(() => {
      if (!props.subscriptionData || props.subscriptionData.status !== 'active') return null;
      return props.subscriptionData.plan;
    });
    const showUpgradeLink = computed(() => {
      if (isPreviewMode.value) return true; // Shows "Sign Up Now"
      if (!props.subscriptionData) return false;
      if (props.subscriptionData.plan === 'pro') return false;
      return true;
    });

    // Feature gating: determine effective plan for sidebar gating
    const userPlan = computed(() => {
      if (isPreviewMode.value) return 'pro';
      if (!props.subscriptionData) return 'pro'; // No data = payments disabled, show all
      if (props.subscriptionData.status === 'trialing') return 'pro';
      return props.subscriptionData.plan || 'student';
    });

    const isLocked = (requiredTier) => !hasFeatureAccess(userPlan.value, requiredTier);

    onMounted(() => {
      document.addEventListener('keydown', handleKeydown);
      loadExpandedState();

      // Auto-expand menu + section when navigating from collapsed state
      if (storage.get(PENDING_EXPAND_KEY) === 'true') {
        storage.remove(PENDING_EXPAND_KEY);
        // Expand the menu if currently collapsed
        if (props.collapsed) {
          emit('toggle');
        }
        // Expand the section for the current route
        const sectionKey = activeSectionKey.value;
        if (sectionKey && !expandedSections.value[sectionKey]) {
          expandedSections.value = { ...expandedSections.value, [sectionKey]: true };
          saveExpandedState();
        }
      }
    });

    onBeforeUnmount(() => {
      document.removeEventListener('keydown', handleKeydown);
    });

    return {
      logoUrl,
      faviconUrl,
      isAdmin,
      isAdvisor,
      hasSpouse,
      effectiveCollapsed,
      menuWidthClass,
      showBugReportModal,
      currentPath,

      // Life stage
      currentStage,
      stageLabel,
      stageColour,
      progressPercentage,
      showProgress,
      stageLabelColourClass,
      progressBarColourClass,
      progressRingColourClass,
      // Active state
      isExactActive,
      isActive,
      isNetWorthActive,
      isInvestmentsActive,
      isLiabilitiesActive,
      isEstateActive,
      isWillBuilderActive,
      isLpaActive,
      isGoalsOverviewActive,
      isGoalsEventsActive,
      isValuableInfoSection,

      // Section state
      toggleSection,
      isSectionExpanded,
      toggleCollapsed,
      closeMobile,
      openBugReport,
      handleLogout,
      isPreviewMode,
      showUpgradeLink,
      currentPlanSlug,
      userPlan,
      isLocked,
    };
  },
};
</script>
