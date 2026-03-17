<template>
  <AppLayout>
    <div class="py-2 sm:py-0">
      <!-- Header -->
      <div class="mb-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
          <div>
            <h1 class="font-display text-2xl sm:text-h1 text-horizon-500">Admin Panel</h1>
            <p class="text-body text-neutral-500 mt-2">
              System administration and management
            </p>
          </div>
          <div class="flex items-center space-x-2 flex-shrink-0">
            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-raspberry-100 text-raspberry-800">
              <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M2.166 4.999A11.954 11.954 0 0010 1.944 11.954 11.954 0 0017.834 5c.11.65.166 1.32.166 2.001 0 5.225-3.34 9.67-8 11.317C5.34 16.67 2 12.225 2 7c0-.682.057-1.35.166-2.001zm11.541 3.708a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
              </svg>
              Administrator
            </span>
          </div>
        </div>
      </div>

      <!-- Tabs -->
      <div class="border-b border-light-gray mb-6">
        <nav class="flex space-x-4 sm:space-x-8 overflow-x-auto scrollbar-hide -webkit-overflow-scrolling-touch">
          <button
            v-for="tab in tabs"
            :key="tab.id"
            @click="activeTab = tab.id"
            :class="[
              'whitespace-nowrap py-3 sm:py-4 px-1 border-b-2 font-medium text-xs sm:text-sm transition-colors flex items-center flex-shrink-0',
              activeTab === tab.id
                ? 'border-raspberry-600 text-raspberry-600'
                : 'border-transparent text-neutral-500 hover:text-neutral-500 hover:border-horizon-300'
            ]"
          >
            <svg class="w-4 h-4 sm:w-5 sm:h-5 mr-1 sm:mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="getTabIcon(tab.id)" />
            </svg>
            <span class="hidden sm:inline">{{ tab.label }}</span>
            <span class="sm:hidden">{{ getTabShortLabel(tab.id) }}</span>
          </button>
        </nav>
      </div>

      <!-- Tab Content -->
      <div class="space-y-6">
        <!-- Dashboard Tab -->
        <AdminDashboard v-if="activeTab === 'dashboard'" />

        <!-- Users Tab -->
        <UserManagement v-if="activeTab === 'users'" />

        <!-- Backups Tab -->
        <DatabaseBackup v-if="activeTab === 'backups'" />

        <!-- Decision Matrix Tab -->
        <DecisionMatrix v-if="activeTab === 'decision-matrix'" />

        <!-- Tax Settings Tab -->
        <TaxSettings v-if="activeTab === 'tax-settings'" />
      </div>
    </div>
  </AppLayout>
</template>

<script>
import { mapGetters } from 'vuex';
import AppLayout from '../../layouts/AppLayout.vue';
import AdminDashboard from '../../components/Admin/AdminDashboard.vue';
import UserManagement from '../../components/Admin/UserManagement.vue';
import DatabaseBackup from '../../components/Admin/DatabaseBackup.vue';
import TaxSettings from '../../components/Admin/TaxSettings.vue';
const DecisionMatrix = () => import('../../components/Admin/DecisionMatrix.vue');

export default {
  name: 'AdminPanel',

  components: {
    AppLayout,
    AdminDashboard,
    UserManagement,
    DatabaseBackup,
    TaxSettings,
    DecisionMatrix,
  },

  data() {
    return {
      activeTab: 'dashboard',
      tabs: [
        {
          id: 'dashboard',
          label: 'Dashboard',
        },
        {
          id: 'users',
          label: 'User Management',
        },
        {
          id: 'decision-matrix',
          label: 'Decision Matrix',
        },
        {
          id: 'tax-settings',
          label: 'Tax Settings',
        },
        {
          id: 'backups',
          label: 'Database',
        },
      ],
    };
  },

  computed: {
    ...mapGetters('auth', ['currentUser', 'isAdmin']),
  },

  methods: {
    getTabIcon(tabId) {
      const icons = {
        dashboard: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6',
        users: 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z',
        'decision-matrix': 'M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2',
        'tax-settings': 'M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z',
        backups: 'M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4',
      };
      return icons[tabId] || '';
    },

    getTabShortLabel(tabId) {
      const labels = {
        dashboard: 'Dashboard',
        users: 'Users',
        'decision-matrix': 'Matrix',
        'tax-settings': 'Tax',
        backups: 'Database',
      };
      return labels[tabId] || tabId;
    },
  },

};
</script>

<style scoped>
/* Smooth scrolling on iOS */
.-webkit-overflow-scrolling-touch {
  -webkit-overflow-scrolling: touch;
}
</style>
