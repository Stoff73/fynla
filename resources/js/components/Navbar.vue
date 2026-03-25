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
          <!-- Countdown Timer -->
          <div v-if="countdown" class="flex items-center space-x-1 text-body-sm font-semibold tabular-nums">
            <span class="bg-horizon-500 text-white px-1.5 py-0.5 rounded">{{ countdown.days }}</span>
            <span class="text-neutral-500">:</span>
            <span class="bg-horizon-500 text-white px-1.5 py-0.5 rounded">{{ countdown.hours }}</span>
            <span class="text-neutral-500">:</span>
            <span class="bg-horizon-500 text-white px-1.5 py-0.5 rounded">{{ countdown.minutes }}</span>
            <span class="text-neutral-500">:</span>
            <span class="bg-horizon-500 text-white px-1.5 py-0.5 rounded">{{ countdown.seconds }}</span>
          </div>

          <!-- Pipe separator -->
          <span v-if="countdown && (trialData && trialData.status === 'trialing')" class="text-horizon-500 text-lg font-light">|</span>
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
              class="inline-flex items-center text-sm font-semibold text-raspberry-500 hover:text-raspberry-600 hover:bg-white/40 px-3 py-1.5 rounded-md transition-all"
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

          <!-- Support Dropdown -->
          <div class="relative">
            <button
              @click="supportDropdownOpen = !supportDropdownOpen"
              class="inline-flex items-center text-sm font-semibold text-horizon-500 hover:text-horizon-600 hover:bg-white/40 px-3 py-1.5 rounded-md transition-all"
            >
              <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z" />
              </svg>
              Support
              <svg class="w-3.5 h-3.5 ml-1.5" :class="{'rotate-180': supportDropdownOpen}" style="transition: transform 0.2s" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
              </svg>
            </button>
            <transition
              enter-active-class="transition ease-out duration-100"
              enter-from-class="transform opacity-0 scale-95"
              enter-to-class="transform opacity-100 scale-100"
              leave-active-class="transition ease-in duration-75"
              leave-from-class="transform opacity-100 scale-100"
              leave-to-class="transform opacity-0 scale-95"
            >
              <div
                v-if="supportDropdownOpen"
                class="absolute right-0 mt-2 w-48 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 z-50"
              >
                <div class="py-1">
                  <router-link
                    to="/help"
                    class="flex items-center px-4 py-2 text-sm text-horizon-500 hover:bg-savannah-100"
                    @click="supportDropdownOpen = false"
                  >
                    <svg class="w-4 h-4 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    Help
                  </router-link>
                  <a
                    href="https://docs.google.com/forms/d/e/1FAIpQLSeEotaP8CrnnhPYcuLdhl9fwIDT2V8GoduC0ytNtPcyD4FdSw/viewform?usp=publish-editor"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="flex items-center px-4 py-2 text-sm text-horizon-500 hover:bg-savannah-100"
                    @click="supportDropdownOpen = false"
                  >
                    <svg class="w-4 h-4 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
                    </svg>
                    Feedback
                  </a>
                  <button
                    class="flex items-center w-full text-left px-4 py-2 text-sm text-horizon-500 hover:bg-savannah-100"
                    @click="openBugReport"
                  >
                    <svg class="w-4 h-4 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4.5c-.77-.833-2.694-.833-3.464 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z" />
                    </svg>
                    Bug Report
                  </button>
                </div>
              </div>
            </transition>
          </div>

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
                  <router-link
                    to="/profile"
                    class="flex items-center px-4 py-2 text-body-sm text-horizon-500 hover:bg-savannah-100"
                    @click="userDropdownOpen = false"
                  >
                    <svg class="w-4 h-4 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                    User Profile
                  </router-link>
                  <router-link
                    to="/risk-profile"
                    class="flex items-center px-4 py-2 text-body-sm text-horizon-500 hover:bg-savannah-100"
                    @click="userDropdownOpen = false"
                  >
                    <svg class="w-4 h-4 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                    </svg>
                    Risk Profile
                  </router-link>
                  <router-link
                    to="/settings"
                    class="flex items-center px-4 py-2 text-body-sm text-horizon-500 hover:bg-savannah-100"
                    @click="userDropdownOpen = false"
                  >
                    <svg class="w-4 h-4 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    Settings
                  </router-link>
                  <div class="border-t border-savannah-100 my-1"></div>
                  <button
                    @click="handleLogout"
                    class="flex items-center w-full text-left px-4 py-2 text-body-sm text-horizon-500 hover:bg-savannah-100"
                  >
                    <svg class="w-4 h-4 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                    </svg>
                    Sign Out
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
  <BugReportModal :show="showBugReportModal" @close="showBugReportModal = false" />
</template>

<script>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import { useStore } from 'vuex';
import { useRoute, useRouter } from 'vue-router';
import LogoutSuccessModal from './Auth/LogoutSuccessModal.vue';
import PlanSelectionModal from '@/components/Payment/PlanSelectionModal.vue';
import BugReportModal from './BugReportModal.vue';
import api from '@/services/api';
import { findCategoryConfig } from '@/constants/subNavConfig';
import { stopInactivityTimer } from '@/services/sessionLifecycleService';

export default {
  name: 'Navbar',

  components: {
    LogoutSuccessModal,
    PlanSelectionModal,
    BugReportModal,
  },

  setup() {
    const store = useStore();
    const route = useRoute();
    const router = useRouter();

    const userDropdownOpen = ref(false);
    const supportDropdownOpen = ref(false);
    const showBugReportModal = ref(false);
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

    // Countdown timer to 9 April 2026 12:00
    const countdown = ref(null);
    let countdownInterval = null;

    const updateCountdown = () => {
      const target = new Date('2026-04-09T12:00:00').getTime();
      const now = Date.now();
      const diff = target - now;

      if (diff <= 0) {
        countdown.value = null;
        if (countdownInterval) {
          clearInterval(countdownInterval);
          countdownInterval = null;
        }
        return;
      }

      const days = String(Math.floor(diff / (1000 * 60 * 60 * 24))).padStart(2, '0');
      const hours = String(Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60))).padStart(2, '0');
      const minutes = String(Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60))).padStart(2, '0');
      const seconds = String(Math.floor((diff % (1000 * 60)) / 1000)).padStart(2, '0');

      countdown.value = { days, hours, minutes, seconds };
    };

    const pageTitle = computed(() => {
      const path = route.path;
      const query = route.query;

      // Standalone pages (no category)
      if (path.startsWith('/dashboard')) return 'Dashboard';
      if (path.startsWith('/net-worth/wealth-summary')) return 'Net Worth';
      if (path.startsWith('/help')) return 'Help';
      if (path.startsWith('/admin')) return 'Admin Panel';
      if (path.startsWith('/onboarding')) return 'Setup';

      // Category-based titles
      const categoryConfig = findCategoryConfig(path, query);
      if (categoryConfig) return categoryConfig.headerTitle;

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

    const openBugReport = () => {
      supportDropdownOpen.value = false;
      showBugReportModal.value = true;
    };

    // Close dropdowns when clicking outside
    const handleClickOutside = (event) => {
      const dropdown = event.target.closest('.relative');
      if (!dropdown) {
        userDropdownOpen.value = false;
        supportDropdownOpen.value = false;
      }
    };

    onMounted(() => {
      document.addEventListener('click', handleClickOutside);
      updateCountdown();
      countdownInterval = setInterval(updateCountdown, 1000);
      fetchTrialStatus();
    });

    onBeforeUnmount(() => {
      document.removeEventListener('click', handleClickOutside);
      if (countdownInterval) {
        clearInterval(countdownInterval);
        countdownInterval = null;
      }
    });

    return {
      countdown,
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
      supportDropdownOpen,
      showBugReportModal,
      openBugReport,
      handleLogout,
      handleLogoutModalClose,
    };
  },
};
</script>
