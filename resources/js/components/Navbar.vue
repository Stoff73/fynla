<template>
  <!-- Logout Success Modal -->
  <LogoutSuccessModal
    :show="showLogoutModal"
    @close="handleLogoutModalClose"
  />

  <nav class="bg-white shadow-sm border-b border-gray-200">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="flex justify-between h-16">
        <div class="flex">
          <div class="flex-shrink-0 flex items-center">
            <router-link to="/dashboard" class="cursor-pointer">
              <img :src="logoUrl" alt="Fynla" class="h-36 w-auto mt-4" />
            </router-link>
          </div>
          <div class="hidden sm:ml-6 sm:flex sm:space-x-8">
            <router-link
              to="/dashboard"
              class="inline-flex items-center px-1 pt-1 border-b-2 text-body-sm font-medium"
              :class="isActive('/dashboard') ? 'border-primary-600 text-gray-900' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700'"
            >
              Dashboard
            </router-link>
          </div>
        </div>

        <!-- Center - Feedback Button -->
        <div class="hidden sm:flex sm:items-center space-x-3">
          <!-- Feedback Button -->
          <a
            href="https://docs.google.com/forms/d/e/1FAIpQLSeEotaP8CrnnhPYcuLdhl9fwIDT2V8GoduC0ytNtPcyD4FdSw/viewform?usp=publish-editor"
            target="_blank"
            rel="noopener noreferrer"
            class="inline-flex items-center px-4 py-2 border-2 border-amber-500 text-body-sm font-medium rounded-button text-amber-700 bg-white hover:bg-amber-50 transition-colors"
          >
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
            </svg>
            Feedback
          </a>
        </div>

        <div class="hidden sm:ml-6 sm:flex sm:items-center space-x-4">
          <!-- 2FA Reminder -->
          <router-link
            v-if="showMFAReminder"
            to="/settings/security"
            class="inline-flex items-center px-3 py-2 border border-green-600 text-body-sm font-medium rounded-button text-white bg-green-600 hover:bg-green-700 transition-colors"
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
            class="inline-flex items-center px-3 py-2 border border-transparent text-body-sm font-medium rounded-button text-white bg-primary-600 hover:bg-primary-700"
          >
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
            </svg>
            Complete Setup
          </router-link>
          <router-link
            v-if="isAdmin"
            to="/admin"
            class="inline-flex items-center px-3 py-2 border border-transparent text-body-sm font-medium rounded-button text-white bg-red-600 hover:bg-red-700"
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
              class="inline-flex items-center px-3 py-2 border border-transparent text-body-sm font-medium rounded-button text-gray-700 bg-gray-100 hover:bg-gray-200"
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
                class="absolute right-0 mt-2 w-48 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 z-50"
              >
                <div class="py-1">
                  <router-link
                    to="/valuable-info"
                    class="flex items-center px-4 py-2 text-body-sm text-gray-700 hover:bg-gray-100"
                  >
                    <svg class="w-4 h-4 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    Valuable Info
                  </router-link>
                  <router-link
                    to="/profile"
                    class="flex items-center px-4 py-2 text-body-sm text-gray-700 hover:bg-gray-100"
                  >
                    <svg class="w-4 h-4 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                    User Profile
                  </router-link>
                  <router-link
                    to="/settings"
                    class="flex items-center px-4 py-2 text-body-sm text-gray-700 hover:bg-gray-100"
                  >
                    <svg class="w-4 h-4 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    Settings
                  </router-link>
                  <button
                    @click="handleLogout"
                    class="flex items-center w-full text-left px-4 py-2 text-body-sm text-gray-700 hover:bg-gray-100"
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

        <div class="flex items-center sm:hidden">
          <button
            @click="mobileMenuOpen = !mobileMenuOpen"
            class="inline-flex items-center justify-center p-2 rounded-button text-gray-400 hover:text-gray-500 hover:bg-gray-100"
          >
            <span class="sr-only">Open main menu</span>
            <svg
              class="h-6 w-6"
              fill="none"
              viewBox="0 0 24 24"
              stroke="currentColor"
            >
              <path
                v-if="!mobileMenuOpen"
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M4 6h16M4 12h16M4 18h16"
              />
              <path
                v-else
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M6 18L18 6M6 6l12 12"
              />
            </svg>
          </button>
        </div>
      </div>
    </div>

    <div v-if="mobileMenuOpen" class="sm:hidden">
      <div class="pt-2 pb-3 space-y-1">
        <router-link
          to="/dashboard"
          class="block pl-3 pr-4 py-2 border-l-4 text-base font-medium"
          :class="isActive('/dashboard') ? 'bg-primary-50 border-primary-600 text-primary-700' : 'border-transparent text-gray-500 hover:bg-gray-50 hover:border-gray-300 hover:text-gray-700'"
        >
          Dashboard
        </router-link>
        <router-link
          v-if="showMFAReminder"
          to="/settings/security"
          class="block pl-3 pr-4 py-2 border-l-4 border-green-600 text-base font-medium bg-green-600 text-white"
        >
          Enable 2FA
        </router-link>
        <router-link
          v-if="showCompleteSetupButton"
          to="/onboarding"
          class="block pl-3 pr-4 py-2 border-l-4 border-primary-600 text-base font-medium bg-primary-50 text-primary-700"
        >
          Complete Setup
        </router-link>
        <a
          href="https://docs.google.com/forms/d/e/1FAIpQLSeEotaP8CrnnhPYcuLdhl9fwIDT2V8GoduC0ytNtPcyD4FdSw/viewform?usp=publish-editor"
          target="_blank"
          rel="noopener noreferrer"
          class="block pl-3 pr-4 py-2 border-l-4 border-amber-500 text-base font-medium bg-amber-50 text-amber-700"
        >
          Feedback
        </a>
        <router-link
          v-if="isAdmin"
          to="/admin"
          class="block pl-3 pr-4 py-2 border-l-4 text-base font-medium"
          :class="isActive('/admin') ? 'bg-red-50 border-red-600 text-red-700' : 'border-transparent text-gray-500 hover:bg-gray-50 hover:border-gray-300 hover:text-gray-700'"
        >
          Admin Panel
        </router-link>
      </div>
      <div class="pt-4 pb-3 border-t border-gray-200">
        <div class="flex items-center px-4 mb-3">
          <div class="flex-shrink-0">
            <span class="text-body font-medium text-gray-900">{{ userName }}</span>
          </div>
        </div>
        <div class="space-y-1">
          <router-link
            to="/valuable-info"
            class="block pl-3 pr-4 py-2 border-l-4 text-base font-medium"
            :class="isActive('/valuable-info') ? 'bg-primary-50 border-primary-600 text-primary-700' : 'border-transparent text-gray-500 hover:bg-gray-50 hover:border-gray-300 hover:text-gray-700'"
          >
            Valuable Info
          </router-link>
          <router-link
            to="/profile"
            class="block pl-3 pr-4 py-2 border-l-4 text-base font-medium"
            :class="isActive('/profile') ? 'bg-primary-50 border-primary-600 text-primary-700' : 'border-transparent text-gray-500 hover:bg-gray-50 hover:border-gray-300 hover:text-gray-700'"
          >
            User Profile
          </router-link>
          <router-link
            to="/settings"
            class="block pl-3 pr-4 py-2 border-l-4 text-base font-medium"
            :class="isActive('/settings') ? 'bg-primary-50 border-primary-600 text-primary-700' : 'border-transparent text-gray-500 hover:bg-gray-50 hover:border-gray-300 hover:text-gray-700'"
          >
            Settings
          </router-link>
          <button
            @click="handleLogout"
            class="w-full text-left block pl-3 pr-4 py-2 border-l-4 border-transparent text-base font-medium text-gray-500 hover:bg-gray-50 hover:border-gray-300 hover:text-gray-700"
          >
            Logout
          </button>
        </div>
      </div>
    </div>
  </nav>
</template>

<script>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import { useStore } from 'vuex';
import { useRoute, useRouter } from 'vue-router';
import logoImage from '@/assets/logoTransparent.png';
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

    const logoUrl = logoImage;
    const mobileMenuOpen = ref(false);
    const userDropdownOpen = ref(false);
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

    const isActive = (path) => {
      return route.path === path;
    };

    const handleLogout = async () => {
      // Close dropdown menu immediately
      userDropdownOpen.value = false;
      mobileMenuOpen.value = false;

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
      logoUrl,
      mobileMenuOpen,
      userDropdownOpen,
      showLogoutModal,
      userName,
      isAdmin,
      onboardingCompleted,
      showCompleteSetupButton,
      showMFAReminder,
      isActive,
      handleLogout,
      handleLogoutModalClose,
    };
  },
};
</script>
