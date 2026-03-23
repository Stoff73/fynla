<template>
  <!-- Logout Success Modal -->
  <LogoutSuccessModal
    :show="showLogoutModal"
    @close="handleLogoutModalClose"
  />

  <nav class="bg-light-blue-100 shadow-sm border-b border-light-gray">
    <div class="mx-auto px-4 sm:px-6 lg:px-8">
      <div class="flex items-center justify-between py-[15px]">

        <!-- Page Title -->
        <h1 v-if="pageTitle" class="text-2xl font-bold text-horizon-500 pl-12 sm:pl-0">{{ pageTitle }}</h1>
        <div v-else></div>

        <div class="flex items-center">
        <div class="hidden sm:flex sm:items-center space-x-4">
          <!-- Trial countdown (inline) -->
          <div v-if="trialData && trialData.status === 'trialing'" class="flex items-center gap-3">
            <div>
              <p class="text-xs font-medium text-horizon-500">
                Your {{ trialPlanName }} trial ends in {{ trialData.days_remaining }} {{ trialData.days_remaining === 1 ? 'day' : 'days' }}
              </p>
              <div class="mt-1 w-full bg-white/50 rounded-full h-1">
                <div
                  class="bg-violet-500 h-1 rounded-full transition-all duration-500"
                  :style="{ width: (100 - trialData.progress) + '%' }"
                ></div>
              </div>
            </div>
            <button
              @click="showPlanModal = true"
              class="inline-flex items-center text-sm font-semibold text-horizon-500 hover:text-horizon-600 hover:bg-white/40 px-3 py-1.5 rounded-md transition-all"
            >
              <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18" />
              </svg>
              Upgrade Now
            </button>
          </div>

          <!-- Pipe separator -->
          <span v-if="(trialData && trialData.status === 'trialing') && showMFAReminder" class="text-horizon-500 text-lg font-light">|</span>

          <!-- 2FA Reminder -->
          <router-link
            v-if="showMFAReminder"
            to="/settings/security"
            class="inline-flex items-center text-sm font-semibold text-horizon-500 hover:text-horizon-600 hover:bg-white/40 px-3 py-1.5 rounded-md transition-all"
            title="Secure your account with two-factor authentication"
          >
            <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
            </svg>
            Enable 2FA
          </router-link>

          <router-link
            v-if="isAdvisor"
            to="/advisor"
            class="inline-flex items-center px-3 py-2 border border-transparent text-body-sm font-medium rounded-button text-white bg-violet-500 hover:bg-violet-600"
          >
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
              <circle cx="8.5" cy="7" r="4" />
              <path d="M20 8v6M23 11h-6" />
            </svg>
            Advisor
          </router-link>
          <router-link
            v-if="isAdmin"
            to="/admin"
            class="inline-flex items-center px-3 py-2 border border-transparent text-body-sm font-medium rounded-button text-white bg-raspberry-600 hover:bg-raspberry-700"
          >
            <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M2.166 4.999A11.954 11.954 0 0010 1.944 11.954 11.954 0 0017.834 5c.11.65.166 1.32.166 2.001 0 5.225-3.34 9.67-8 11.317C5.34 16.67 2 12.225 2 7c0-.682.057-1.35.166-2.001zm11.541 3.708a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
            </svg>
            Admin
          </router-link>

          <!-- Info Guide Button (What data do I need?) -->
          <button
            v-if="shouldShowInfoGuide"
            @click="toggleInfoGuide"
            class="relative inline-flex items-center justify-center w-9 h-9 rounded-full bg-horizon-500 text-white hover:bg-horizon-600 transition-colors"
            :class="{ 'ring-2 ring-violet-200': infoGuideOpen }"
            :title="infoGuideOpen ? 'Close guide' : 'What data do I need?'"
          >
            <svg
              v-if="!infoGuideOpen"
              xmlns="http://www.w3.org/2000/svg"
              fill="none"
              viewBox="0 0 24 24"
              stroke-width="2"
              stroke="currentColor"
              class="w-5 h-5"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9 5.25h.008v.008H12v-.008z"
              />
            </svg>
            <svg
              v-else
              xmlns="http://www.w3.org/2000/svg"
              fill="none"
              viewBox="0 0 24 24"
              stroke-width="2"
              stroke="currentColor"
              class="w-5 h-5"
            >
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
            <span
              v-if="infoGuideMissingCount > 0 && !infoGuideOpen"
              class="absolute -top-1 -right-1 w-4 h-4 bg-spring-500 rounded-full text-[10px] font-bold text-white flex items-center justify-center"
            >
              {{ infoGuideMissingCount > 9 ? '9+' : infoGuideMissingCount }}
            </span>
          </button>

          <!-- User Dropdown Menu -->
          <div class="relative">
            <button
              type="button"
              @click="userDropdownOpen = !userDropdownOpen"
              class="inline-flex items-center px-3 py-2 border border-transparent text-body-sm font-medium rounded-button text-horizon-500 bg-savannah-100 hover:bg-savannah-200"
            >
              <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
              </svg>
              {{ userName }}
              <svg class="w-4 h-4 ml-2" :class="{'rotate-180': userDropdownOpen}" style="transition: transform 0.2s" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
              </svg>
            </button>

            <!-- Dropdown Menu -->
            <transition
              enter-active-class="transition ease-out duration-100"
              enter-from-class="transform opacity-0 scale-95"
              enter-to-class="transform opacity-100 scale-100"
              leave-active-class="transition ease-in duration-75"
              leave-from-class="transform opacity-100 scale-100"
              leave-to-class="transform opacity-0 scale-95"
            >
              <div
                v-if="userDropdownOpen"
                class="absolute right-0 mt-2 w-56 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 z-50"
              >
                <div class="py-1">
                  <!-- Valuable Info items shown directly for tablet+ -->
                  <router-link
                    to="/valuable-info?section=letter"
                    class="flex items-center px-4 py-2 text-body-sm text-horizon-500 hover:bg-savannah-100"
                  >
                    <svg class="w-4 h-4 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                    </svg>
                    Letter to Spouse
                  </router-link>
                  <router-link
                    to="/valuable-info?section=income"
                    class="flex items-center px-4 py-2 text-body-sm text-horizon-500 hover:bg-savannah-100"
                  >
                    <svg class="w-4 h-4 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    Income
                  </router-link>
                  <router-link
                    to="/valuable-info?section=expenditure"
                    class="flex items-center px-4 py-2 text-body-sm text-horizon-500 hover:bg-savannah-100"
                  >
                    <svg class="w-4 h-4 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                    Expenditure
                  </router-link>
                  <router-link
                    to="/valuable-info?section=risk"
                    class="flex items-center px-4 py-2 text-body-sm text-horizon-500 hover:bg-savannah-100"
                  >
                    <svg class="w-4 h-4 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                    </svg>
                    Risk Profile
                  </router-link>
                  <div class="border-t border-savannah-100 my-1"></div>
                  <router-link
                    to="/profile"
                    class="flex items-center px-4 py-2 text-body-sm text-horizon-500 hover:bg-savannah-100"
                  >
                    <svg class="w-4 h-4 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                    User Profile
                  </router-link>
                  <router-link
                    to="/settings"
                    class="flex items-center px-4 py-2 text-body-sm text-horizon-500 hover:bg-savannah-100"
                  >
                    <svg class="w-4 h-4 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    Settings
                  </router-link>
                  <button
                    @click="handleLogout"
                    class="flex items-center w-full text-left px-4 py-2 text-body-sm text-horizon-500 hover:bg-savannah-100"
                  >
                    <svg class="w-4 h-4 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                    </svg>
                    Logout
                  </button>
                </div>
              </div>
            </transition>
          </div>
        </div>
        </div>

      </div>
    </div>

  </nav>
  <PlanSelectionModal
    v-if="showPlanModal"
    @select="handlePlanSelect"
    @close="showPlanModal = false"
  />
</template>

<script>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import { useStore } from 'vuex';
import { useRoute, useRouter } from 'vue-router';
import LogoutSuccessModal from './Auth/LogoutSuccessModal.vue';
import PlanSelectionModal from '@/components/Payment/PlanSelectionModal.vue';
import api from '@/services/api';
import { stopInactivityTimer } from '@/services/sessionLifecycleService';

export default {
  name: 'Navbar',

  components: {
    LogoutSuccessModal,
    PlanSelectionModal,
  },

  setup() {
    const store = useStore();
    const route = useRoute();
    const router = useRouter();

    const userDropdownOpen = ref(false);
    const trialData = ref(null);
    const showPlanModal = ref(false);

    const trialPlanName = computed(() => {
      if (!trialData.value) return '';
      return trialData.value.plan;
    });

    const fetchTrialStatus = async () => {
      try {
        const response = await api.get('/payment/trial-status');
        trialData.value = response.data;
      } catch {
        // Silently fail
      }
    };

    const handlePlanSelect = ({ plan, billingCycle }) => {
      showPlanModal.value = false;
      router.push(`/checkout?plan=${plan}&cycle=${billingCycle}`);
    };

    const pageTitle = computed(() => {
      const path = route.path;
      const map = [
        { prefix: '/net-worth/wealth-summary', label: 'Net Worth' },
        { prefix: '/net-worth/retirement', label: 'Retirement' },
        { prefix: '/net-worth/investments', label: 'Investments' },
        { prefix: '/net-worth/investment-detail', label: 'Investments' },
        { prefix: '/net-worth/tax-efficiency', label: 'Investments' },
        { prefix: '/net-worth/holdings-detail', label: 'Investments' },
        { prefix: '/net-worth/fees-detail', label: 'Investments' },
        { prefix: '/net-worth/cash', label: 'Cash' },
        { prefix: '/net-worth/business', label: 'Business' },
        { prefix: '/net-worth/property', label: 'Property' },
        { prefix: '/net-worth/chattels', label: 'Personal Valuables' },
        { prefix: '/protection', label: 'Protection' },
        { prefix: '/estate', label: 'Estate Planning' },
        { prefix: '/trusts', label: 'Trusts' },
        { prefix: '/goals', label: 'Goals' },
        { prefix: '/risk-profile', label: 'Risk Profile' },
        { prefix: '/holistic-plan', label: 'Holistic Plan' },
        { prefix: '/plans', label: 'Plans' },
        { prefix: '/actions', label: 'Actions' },
        { prefix: '/profile', label: 'User Profile' },
        { prefix: '/valuable-info', label: 'Valuable Info' },
        { prefix: '/settings', label: 'Settings' },
        { prefix: '/help', label: 'Help' },
        { prefix: '/admin', label: 'Admin Panel' },
        { prefix: '/savings', label: 'Savings' },
        { prefix: '/onboarding', label: 'Setup' },
        { prefix: '/dashboard', label: 'Dashboard' },
      ];
      for (const entry of map) {
        if (path.startsWith(entry.prefix)) return entry.label;
      }
      return '';
    });
    const showLogoutModal = ref(false);
    const userName = computed(() => {
      const user = store.getters['auth/currentUser'];
      return user?.name || 'User';
    });

    const isAdmin = computed(() => {
      return store.getters['auth/isAdmin'];
    });

    const isAdvisor = computed(() => {
      return store.getters['auth/isAdvisor'];
    });

    // Info Guide (question mark button)
    const infoGuideOpen = computed(() => store.getters['infoGuide/isOpen']);
    const infoGuideMissingCount = computed(() => store.getters['infoGuide/missingCount']);
    const shouldShowInfoGuide = computed(() => {
      const publicRoutes = ['/login', '/register', '/forgot-password', '/reset-password'];
      if (publicRoutes.some(r => route.path.startsWith(r))) return false;
      return store.getters['infoGuide/shouldShowGuide'];
    });
    const toggleInfoGuide = () => store.dispatch('infoGuide/toggle');

    // Show 2FA reminder if MFA is not enabled and user is not a preview user
    const showMFAReminder = computed(() => {
      const user = store.getters['auth/currentUser'];
      if (!user) return false;
      // Don't show for preview users
      if (user.is_preview_user) return false;
      // Show if MFA is not enabled
      return user.mfa_enabled !== true;
    });

    const handleLogout = async () => {
      userDropdownOpen.value = false;

      try {
        // Stop inactivity timer before logout
        stopInactivityTimer();
        await store.dispatch('auth/logout');
        // Show success modal
        showLogoutModal.value = true;
      } catch (error) {
        console.error('Logout error:', error);
        // Even on error, redirect to login
        router.push('/login');
      }
    };

    const handleLogoutModalClose = () => {
      showLogoutModal.value = false;
      router.push('/login');
    };

    // Close dropdowns when clicking outside
    const handleClickOutside = (event) => {
      const dropdown = event.target.closest('.relative');
      if (!dropdown) {
        userDropdownOpen.value = false;
      }
    };

    onMounted(() => {
      document.addEventListener('click', handleClickOutside);
      fetchTrialStatus();
    });

    onBeforeUnmount(() => {
      document.removeEventListener('click', handleClickOutside);
    });

    return {
      pageTitle,
      userDropdownOpen,
      showLogoutModal,
      userName,
      isAdmin,
      isAdvisor,
      trialData,
      trialPlanName,
      showPlanModal,
      handlePlanSelect,
      showMFAReminder,
      infoGuideOpen,
      infoGuideMissingCount,
      shouldShowInfoGuide,
      toggleInfoGuide,
      handleLogout,
      handleLogoutModalClose,
    };
  },
};
</script>
