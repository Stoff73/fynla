<template>
  <!-- Logout Success Modal -->
  <LogoutSuccessModal
    :show="showLogoutModal"
    @close="handleLogoutModalClose"
  />

  <nav class="bg-eggshell-500 shadow-sm border-b border-light-gray">
    <div class="mx-auto px-4 sm:px-6 lg:px-8">
      <div class="flex items-center justify-between h-16">

        <!-- Page Title -->
        <h1 v-if="pageTitle" class="text-lg font-semibold text-horizon-500">{{ pageTitle }}</h1>
        <div v-else></div>

        <div class="flex items-center">
        <div class="hidden sm:flex sm:items-center space-x-4">
          <!-- 2FA Reminder -->
          <router-link
            v-if="showMFAReminder"
            to="/settings/security"
            class="inline-flex items-center px-3 py-2 border border-spring-600 text-body-sm font-medium rounded-button text-white bg-spring-600 hover:bg-spring-700 transition-colors"
            title="Secure your account with two-factor authentication"
          >
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
            </svg>
            Enable 2FA
          </router-link>

          <router-link
            v-if="showCompleteSetupButton"
            to="/onboarding"
            class="inline-flex items-center px-3 py-2 border border-transparent text-body-sm font-medium rounded-button text-white bg-raspberry-500 hover:bg-raspberry-600"
          >
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
            </svg>
            Complete Setup
          </router-link>
          <router-link
            v-if="isAdmin"
            to="/admin"
            class="inline-flex items-center px-3 py-2 border border-transparent text-body-sm font-medium rounded-button text-white bg-error-600 hover:bg-error-700"
          >
            <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M2.166 4.999A11.954 11.954 0 0010 1.944 11.954 11.954 0 0017.834 5c.11.65.166 1.32.166 2.001 0 5.225-3.34 9.67-8 11.317C5.34 16.67 2 12.225 2 7c0-.682.057-1.35.166-2.001zm11.541 3.708a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
            </svg>
            Admin
          </router-link>

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
</template>

<script>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import { useStore } from 'vuex';
import { useRoute, useRouter } from 'vue-router';
import LogoutSuccessModal from './Auth/LogoutSuccessModal.vue';
import { stopInactivityTimer } from '@/services/sessionLifecycleService';

export default {
  name: 'Navbar',

  components: {
    LogoutSuccessModal,
  },

  setup() {
    const store = useStore();
    const route = useRoute();
    const router = useRouter();

    const userDropdownOpen = ref(false);

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
        { prefix: '/uk-taxes', label: 'UK Taxes' },
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

    const onboardingCompleted = computed(() => {
      const user = store.getters['auth/currentUser'];
      return user?.onboarding_completed || false;
    });

    // Show "Complete Setup" button if onboarding is not done OR if sections were skipped
    // Never show for preview users (they don't have onboarding data)
    const showCompleteSetupButton = computed(() => {
      const user = store.getters['auth/currentUser'];
      if (!user) return false;
      // Never show for preview users
      if (user.is_preview_user) return false;
      if (!user.onboarding_completed) {
        return true; // Not completed yet
      }
      // Check if there are skipped sections from the user's data
      const skippedSteps = user?.onboarding_skipped_steps;
      return Array.isArray(skippedSteps) && skippedSteps.length > 0;
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

    // Close dropdowns when clicking outside
    const handleClickOutside = (event) => {
      const dropdown = event.target.closest('.relative');
      if (!dropdown) {
        userDropdownOpen.value = false;
      }
    };

    onMounted(() => {
      document.addEventListener('click', handleClickOutside);
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
      onboardingCompleted,
      showCompleteSetupButton,
      showMFAReminder,
      handleLogout,
      handleLogoutModalClose,
    };
  },
};
</script>
