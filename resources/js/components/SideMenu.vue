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
      class="fixed top-0 bottom-0 left-0 z-[60] bg-white border-r border-gray-200 shadow-lg flex flex-col overflow-hidden"
      :class="[
        menuWidthClass,
        mobileOpen ? 'translate-x-0' : '-translate-x-full sm:translate-x-0',
        'transition-all duration-300 ease-out'
      ]"
    >
      <!-- Logo -->
      <div class="flex items-center h-16 border-b border-gray-200 flex-shrink-0" :class="effectiveCollapsed ? 'justify-center px-2' : 'px-4'">
        <router-link to="/dashboard" class="flex items-center flex-shrink-0 overflow-hidden" @click="closeMobile">
          <img v-if="effectiveCollapsed" :src="faviconUrl" alt="Fynla" class="h-8 w-8" />
          <img v-else :src="logoUrl" alt="Fynla" class="h-28 w-auto mt-3" />
        </router-link>
      </div>

      <!-- Collapse toggle (desktop only) -->
      <button
        @click="toggleCollapsed"
        class="hidden sm:flex items-center justify-center h-8 mx-2 mt-2 mb-1 rounded-md text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition-colors flex-shrink-0"
        :title="collapsed ? 'Expand menu' : 'Collapse menu'"
      >
        <svg class="w-5 h-5 transition-transform duration-300" :class="collapsed ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19l-7-7 7-7m8 14l-7-7 7-7" />
        </svg>
      </button>

      <!-- Navigation items -->
      <div class="flex-1 overflow-y-auto py-2 scrollbar-hide">
        <!-- Main -->
        <SideMenuSection label="Main" :collapsed="effectiveCollapsed">
          <SideMenuItem icon="home" label="Dashboard" to="/dashboard" :collapsed="effectiveCollapsed" :active="isExactActive('/dashboard')" @navigate="closeMobile" />
          <SideMenuItem icon="chart-bar" label="Net Worth" to="/net-worth/wealth-summary" :collapsed="effectiveCollapsed" :active="isNetWorthActive" @navigate="closeMobile" />
        </SideMenuSection>

        <!-- Planning -->
        <SideMenuSection label="Planning" :collapsed="effectiveCollapsed">
          <SideMenuItem icon="clock" label="Retirement" to="/net-worth/retirement" :collapsed="effectiveCollapsed" :active="isActive('/net-worth/retirement')" @navigate="closeMobile" />
          <SideMenuItem icon="trending-up" label="Investments" to="/net-worth/investments" :collapsed="effectiveCollapsed" :active="isInvestmentsActive" @navigate="closeMobile" />
          <SideMenuItem icon="banknotes" label="Cash" to="/net-worth/cash" :collapsed="effectiveCollapsed" :active="isActive('/net-worth/cash')" @navigate="closeMobile" />
          <SideMenuItem icon="shield-check" label="Protection" to="/protection" :collapsed="effectiveCollapsed" :active="isActive('/protection')" @navigate="closeMobile" />
          <SideMenuItem icon="document-text" label="Estate Planning" to="/estate" :collapsed="effectiveCollapsed" :active="isEstateActive" @navigate="closeMobile" />
          <SideMenuItem icon="cube" label="Personal Valuables" to="/net-worth/chattels" :collapsed="effectiveCollapsed" :active="isActive('/net-worth/chattels')" @navigate="closeMobile" />
        </SideMenuSection>

        <!-- Advanced -->
        <SideMenuSection label="Advanced" :collapsed="effectiveCollapsed">
          <SideMenuItem icon="building-library" label="Trusts" to="/trusts" :collapsed="effectiveCollapsed" :active="isActive('/trusts')" @navigate="closeMobile" />
          <SideMenuItem icon="briefcase" label="Business" to="/net-worth/business" :collapsed="effectiveCollapsed" :active="isActive('/net-worth/business')" @navigate="closeMobile" />
          <SideMenuItem icon="flag" label="Goals" to="/goals" :collapsed="effectiveCollapsed" :active="isActive('/goals')" @navigate="closeMobile" />
          <SideMenuItem icon="chart-pie" label="Risk Profile" to="/risk-profile" :collapsed="effectiveCollapsed" :active="isActive('/risk-profile')" @navigate="closeMobile" />
        </SideMenuSection>

        <!-- Plans & Actions -->
        <SideMenuSection label="Plans & Actions" :collapsed="effectiveCollapsed">
          <SideMenuItem icon="puzzle-piece" label="Holistic Plan" to="/holistic-plan" :collapsed="effectiveCollapsed" :active="isActive('/holistic-plan')" @navigate="closeMobile" />
          <SideMenuItem icon="clipboard-list" label="Plans" to="/plans" :collapsed="effectiveCollapsed" :active="isActive('/plans')" @navigate="closeMobile" />
          <SideMenuItem icon="lightning-bolt" label="Actions" to="/actions" :collapsed="effectiveCollapsed" :active="isActive('/actions')" @navigate="closeMobile" />
        </SideMenuSection>

        <!-- Account -->
        <SideMenuSection label="Account" :collapsed="effectiveCollapsed">
          <SideMenuItem icon="user" label="User Profile" to="/profile" :collapsed="effectiveCollapsed" :active="isActive('/profile')" @navigate="closeMobile" />
          <SideMenuItem icon="document-search" label="Valuable Info" to="/valuable-info" :collapsed="effectiveCollapsed" :active="isActive('/valuable-info')" @navigate="closeMobile" />
          <SideMenuItem icon="cog" label="Settings" to="/settings" :collapsed="effectiveCollapsed" :active="isActive('/settings')" @navigate="closeMobile" />
        </SideMenuSection>

        <!-- Support -->
        <SideMenuSection label="Support" :collapsed="effectiveCollapsed">
          <SideMenuItem icon="question-mark-circle" label="Help" to="/help" :collapsed="effectiveCollapsed" :active="isActive('/help')" @navigate="closeMobile" />
          <SideMenuItem
            icon="chat-bubble"
            label="Feedback"
            href="https://docs.google.com/forms/d/e/1FAIpQLSeEotaP8CrnnhPYcuLdhl9fwIDT2V8GoduC0ytNtPcyD4FdSw/viewform?usp=publish-editor"
            :collapsed="effectiveCollapsed"
            :active="false"
            external
            @navigate="closeMobile"
          />
          <SideMenuItem icon="bug" label="Bug Report" :collapsed="effectiveCollapsed" :active="false" @action="openBugReport" />
        </SideMenuSection>

        <!-- Admin (conditional) -->
        <SideMenuSection v-if="isAdmin" label="Admin" :collapsed="effectiveCollapsed">
          <SideMenuItem icon="shield-exclamation" label="Admin Panel" to="/admin" :collapsed="effectiveCollapsed" :active="isActive('/admin')" @navigate="closeMobile" />
          <SideMenuItem icon="calculator" label="UK Taxes" to="/uk-taxes" :collapsed="effectiveCollapsed" :active="isActive('/uk-taxes')" @navigate="closeMobile" />
        </SideMenuSection>
      </div>

      <!-- Logout button -->
      <div class="border-t border-gray-200 p-2 flex-shrink-0">
        <button
          @click="handleLogout"
          class="flex items-center w-full rounded-md px-3 py-2.5 text-gray-500 hover:bg-gray-50 hover:text-gray-700 transition-colors"
          :class="effectiveCollapsed ? 'justify-center' : ''"
          :title="effectiveCollapsed ? 'Logout' : ''"
        >
          <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
          </svg>
          <span v-if="!effectiveCollapsed" class="ml-3 text-sm font-medium whitespace-nowrap">Logout</span>
        </button>
      </div>
    </nav>

    <!-- Bug Report Modal -->
    <BugReportModal :show="showBugReportModal" @close="showBugReportModal = false" />
  </Teleport>
</template>

<script>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import { useStore } from 'vuex';
import { useRoute, useRouter } from 'vue-router';
import logoImage from '@/assets/logoTransparent.png';
import faviconImage from '@/assets/favicon.png';
import SideMenuItem from './SideMenuItem.vue';
import SideMenuSection from './SideMenuSection.vue';
import BugReportModal from './BugReportModal.vue';
import { stopInactivityTimer } from '@/services/sessionLifecycleService';

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
  },

  emits: ['toggle', 'update:mobileOpen'],

  setup(props, { emit }) {
    const store = useStore();
    const route = useRoute();
    const router = useRouter();

    const logoUrl = logoImage;
    const faviconUrl = faviconImage;
    const showBugReportModal = ref(false);

    const isAdmin = computed(() => store.getters['auth/isAdmin']);

    // On mobile overlay, always show expanded (not collapsed)
    const effectiveCollapsed = computed(() => {
      if (props.mobileOpen) return false;
      return props.collapsed;
    });

    const menuWidthClass = computed(() => {
      if (props.mobileOpen) return 'w-56';
      return props.collapsed ? 'w-16' : 'w-56';
    });

    // Active state detection
    const currentPath = computed(() => route.path);

    const isExactActive = (path) => currentPath.value === path;

    const isActive = (prefix) => currentPath.value.startsWith(prefix);

    // Net Worth active when on /net-worth but NOT on retirement/investments/business sub-paths
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

    // Estate active for /estate routes
    const isEstateActive = computed(() => {
      return currentPath.value.startsWith('/estate');
    });

    const toggleCollapsed = () => {
      emit('toggle');
    };

    const closeMobile = () => {
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
        router.push('/login');
      } catch (error) {
        console.error('Logout error:', error);
        router.push('/login');
      }
    };

    // Close mobile menu on Escape key
    const handleKeydown = (e) => {
      if (e.key === 'Escape' && props.mobileOpen) {
        closeMobile();
      }
    };

    onMounted(() => {
      document.addEventListener('keydown', handleKeydown);
    });

    onBeforeUnmount(() => {
      document.removeEventListener('keydown', handleKeydown);
    });

    return {
      logoUrl,
      faviconUrl,
      isAdmin,
      effectiveCollapsed,
      menuWidthClass,
      showBugReportModal,
      currentPath,
      isExactActive,
      isActive,
      isNetWorthActive,
      isInvestmentsActive,
      isEstateActive,
      toggleCollapsed,
      closeMobile,
      openBugReport,
      handleLogout,
    };
  },
};
</script>

<style scoped>
.scrollbar-hide {
  -ms-overflow-style: none;
  scrollbar-width: none;
}
.scrollbar-hide::-webkit-scrollbar {
  display: none;
}
</style>
