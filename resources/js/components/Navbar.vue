<template>
  <!-- Logout Success Modal -->
  <LogoutSuccessModal
    :show="showLogoutModal"
    @close="handleLogoutModalClose"
  />

  <!-- Referral Modal -->
  <ReferralModal
    :show="showReferralModal"
    @close="showReferralModal = false"
  />

  <nav class="bg-light-blue-100 shadow-sm border-b border-light-gray">
    <div class="mx-auto px-4 sm:px-6 lg:px-8">
      <div class="flex items-center py-[15px]">

        <!-- Page Title -->
        <h1 v-if="pageTitle" class="text-2xl font-bold text-horizon-500 pl-12 sm:pl-0 flex-shrink-0">{{ pageTitle }}</h1>
        <div v-else class="flex-shrink-0"></div>

        <!-- Spacer (fills remaining space) -->
        <div class="flex-1 min-w-0"></div>

        <div class="flex items-center flex-shrink-0 gap-2">
          <!-- Fyn Chat Button (mobile) -->
          <button
            v-if="!isPreviewMode"
            @click="$emit('toggle-chat')"
            class="sm:hidden inline-flex items-center px-2.5 py-1.5 border border-transparent text-xs font-medium rounded-button text-raspberry-600 bg-light-pink-100 hover:bg-light-pink-200 transition-all"
            title="Chat"
          >
            <img :src="fynIconUrl" alt="Fyn" class="w-4 h-4 rounded-full mr-1" />
            Chat
          </button>

        <div class="hidden sm:flex sm:items-center space-x-4">
          <!-- Trial info (inline — only during free trial, not for active subscribers) -->
          <div v-if="trialData && trialData.status === 'trialing'" class="flex items-center gap-3">
            <div>
              <p class="text-xs font-medium text-horizon-500">
                Free trial ends in {{ trialData.days_remaining }} {{ trialData.days_remaining === 1 ? 'day' : 'days' }}
              </p>
              <div class="mt-1 w-full bg-white/50 rounded-full h-1">
                <div
                  class="bg-violet-500 h-1 rounded-full transition-all duration-500"
                  :style="{ width: trialData.progress + '%' }"
                ></div>
              </div>
            </div>
            <button
              @click="$emit('open-plan-modal')"
              class="inline-flex items-center text-sm font-semibold text-raspberry-500 hover:text-raspberry-600 hover:bg-white/40 px-3 py-1.5 rounded-md transition-all"
            >
              <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18" />
              </svg>
              Choose a Plan
            </button>
          </div>

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

          <!-- Sign Up (preview mode only) -->
          <router-link
            v-if="isPreviewMode"
            to="/register"
            class="inline-flex items-center text-sm font-semibold text-raspberry-500 hover:text-raspberry-600 hover:bg-white/40 px-3 py-1.5 rounded-md transition-all"
          >
            <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18" />
            </svg>
            Sign Up Now
          </router-link>

          <!-- Upgrade (non-trial, non-pro subscribers only) -->
          <button
            v-else-if="showUpgradeButton"
            @click="$emit('open-plan-modal')"
            class="inline-flex items-center text-sm font-semibold text-raspberry-500 hover:text-raspberry-600 hover:bg-white/40 px-3 py-1.5 rounded-md transition-all"
          >
            <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18" />
            </svg>
            Upgrade Now
          </button>

          <!-- Refer a Friend (active paid subscribers only) -->
          <button
            v-if="isPaidSubscriber"
            @click="showReferralModal = true"
            class="inline-flex items-center text-sm font-semibold text-horizon-500 hover:text-horizon-600 hover:bg-white/40 px-3 py-1.5 rounded-md transition-all"
          >
            <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
            </svg>
            Refer a Friend
          </button>

          <!-- Support Dropdown -->
          <div class="relative" data-dropdown="support">
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

          <!-- Fyn Chat Button (desktop) -->
          <button
            v-if="!isPreviewMode"
            @click="$emit('toggle-chat')"
            class="hidden sm:inline-flex items-center px-3 py-2 border border-transparent text-body-sm font-medium rounded-button text-raspberry-600 bg-light-pink-100 hover:bg-light-pink-200 transition-all"
            title="Chat with Fyn"
          >
            <img :src="fynIconUrl" alt="Fyn" class="w-5 h-5 rounded-full mr-2" />
            Chat with Fyn
          </button>

          <!-- User Dropdown Menu -->
          <div class="relative" data-dropdown="user">
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
                    to="/dashboard"
                    class="flex items-center px-4 py-2 text-body-sm text-horizon-500 hover:bg-savannah-100"
                    @click="userDropdownOpen = false"
                  >
                    <svg class="w-4 h-4 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                    </svg>
                    Dashboard
                  </router-link>
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

  <!-- Countdown Bar -->
  <div v-if="countdown" class="bg-horizon-500 text-white">
    <div class="mx-auto px-4 sm:px-6 lg:px-8 py-1.5 flex items-center justify-center space-x-1 text-body-sm font-semibold tabular-nums">
      <span class="bg-white/20 px-1.5 py-0.5 rounded">{{ countdown.days }}</span>
      <span class="text-white/50">:</span>
      <span class="bg-white/20 px-1.5 py-0.5 rounded">{{ countdown.hours }}</span>
      <span class="text-white/50">:</span>
      <span class="bg-white/20 px-1.5 py-0.5 rounded">{{ countdown.minutes }}</span>
      <span class="text-white/50">:</span>
      <span class="bg-white/20 px-1.5 py-0.5 rounded">{{ countdown.seconds }}</span>
    </div>
  </div>

  <BugReportModal :show="showBugReportModal" @close="showBugReportModal = false" />
</template>

<script>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import { useStore } from 'vuex';
import { useRoute, useRouter } from 'vue-router';
import LogoutSuccessModal from './Auth/LogoutSuccessModal.vue';
import BugReportModal from './BugReportModal.vue';
import ReferralModal from './Payment/ReferralModal.vue';
import { findCategoryConfig } from '@/constants/subNavConfig';
import { stopInactivityTimer } from '@/services/sessionLifecycleService';
import { fynIconUrl } from '@/constants/fynIcon';

import logger from '@/utils/logger';
export default {
  name: 'AppNavbar',

  emits: ['open-chat', 'open-plan-modal'],

  props: {
    subscriptionData: {
      type: Object,
      default: null,
    },
  },

  components: {
    LogoutSuccessModal,
    BugReportModal,
    ReferralModal,
  },

  setup(props) {
    const store = useStore();
    const route = useRoute();
    const router = useRouter();

    const userDropdownOpen = ref(false);
    const supportDropdownOpen = ref(false);
    const showBugReportModal = ref(false);
    const trialData = computed(() => props.subscriptionData);

    const trialPlanName = computed(() => {
      if (!trialData.value) return '';
      return trialData.value.plan;
    });

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

    const isPreviewMode = computed(() => {
      return store.getters['preview/isPreviewMode'];
    });

    // Show upgrade for non-pro subscribers (trialing or active on student/standard/family)
    const showUpgradeButton = computed(() => {
      if (!trialData.value) return false;
      if (isPreviewMode.value) return false;
      const plan = trialData.value.plan;
      const status = trialData.value.status;
      // Don't show for pro users
      if (plan === 'pro') return false;
      // Don't show if already showing the trial upgrade inline
      if (status === 'trialing') return false;
      // Show for active subscribers on student/standard/family
      return status === 'active';
    });

    const isPaidSubscriber = computed(() => {
      if (!trialData.value) return false;
      if (isPreviewMode.value) return false;
      return trialData.value.status === 'active';
    });

    const showReferralModal = ref(false);

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
        logger.error('Logout error:', error);
        // Even on error, redirect to login
        if (!router.currentRoute.value.meta?.public) {
          router.push('/login');
        }
      }
    };

    const handleLogoutModalClose = () => {
      showLogoutModal.value = false;
      // Stay on current page if it's public, otherwise go to login
      if (!router.currentRoute.value.meta?.public) {
        router.push('/login');
      }
    };

    const openBugReport = () => {
      supportDropdownOpen.value = false;
      showBugReportModal.value = true;
    };

    // Close dropdowns when clicking outside or when clicking the other dropdown
    const handleClickOutside = (event) => {
      // Check if click is inside the user dropdown wrapper
      const userDropdownEl = event.target.closest('[data-dropdown="user"]');
      // Check if click is inside the support dropdown wrapper
      const supportDropdownEl = event.target.closest('[data-dropdown="support"]');

      // Close user dropdown if click is outside it
      if (!userDropdownEl) {
        userDropdownOpen.value = false;
      }
      // Close support dropdown if click is outside it
      if (!supportDropdownEl) {
        supportDropdownOpen.value = false;
      }
    };

    onMounted(() => {
      document.addEventListener('click', handleClickOutside);
      updateCountdown();
      countdownInterval = setInterval(updateCountdown, 1000);
    });

    onBeforeUnmount(() => {
      document.removeEventListener('click', handleClickOutside);
      if (countdownInterval) {
        clearInterval(countdownInterval);
        countdownInterval = null;
      }
    });

    return {
      fynIconUrl,
      countdown,
      pageTitle,
      userDropdownOpen,
      showLogoutModal,
      userName,
      isAdmin,
      isAdvisor,
      isPreviewMode,
      showUpgradeButton,
      isPaidSubscriber,
      showReferralModal,
      trialData,
      trialPlanName,
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
