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
        <nav class="flex space-x-4 sm:space-x-8 flex-wrap">
          <template v-for="item in navItems" :key="item.id || item.group">
            <!-- Simple tab -->
            <button
              v-if="!item.children"
              @click="activeTab = item.id"
              :class="[
                'whitespace-nowrap py-3 sm:py-4 px-1 border-b-2 font-medium text-xs sm:text-sm transition-colors flex items-center flex-shrink-0',
                activeTab === item.id
                  ? 'border-raspberry-600 text-raspberry-600'
                  : 'border-transparent text-neutral-500 hover:text-neutral-500 hover:border-horizon-300'
              ]"
            >
              <svg class="w-4 h-4 sm:w-5 sm:h-5 mr-1 sm:mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="getTabIcon(item.id)" />
              </svg>
              <span class="hidden sm:inline">{{ item.label }}</span>
              <span class="sm:hidden">{{ item.shortLabel || item.label }}</span>
            </button>

            <!-- Dropdown group -->
            <div v-else class="relative flex-shrink-0">
              <button
                @click="openDropdown = openDropdown === item.group ? null : item.group"
                :class="[
                  'whitespace-nowrap py-3 sm:py-4 px-1 border-b-2 font-medium text-xs sm:text-sm transition-colors flex items-center',
                  item.children.some(c => c.id === activeTab)
                    ? 'border-raspberry-600 text-raspberry-600'
                    : 'border-transparent text-neutral-500 hover:text-neutral-500 hover:border-horizon-300'
                ]"
              >
                <svg class="w-4 h-4 sm:w-5 sm:h-5 mr-1 sm:mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="getTabIcon(item.group)" />
                </svg>
                <span class="hidden sm:inline">{{ item.label }}</span>
                <span class="sm:hidden">{{ item.shortLabel || item.label }}</span>
                <svg class="w-3 h-3 ml-1 transition-transform" :class="{ 'rotate-180': openDropdown === item.group }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
              </button>
              <div
                v-if="openDropdown === item.group"
                class="absolute top-full left-0 bg-white border border-light-gray rounded-lg shadow-lg py-1 z-50 min-w-[180px]"
              >
                <button
                  v-for="child in item.children"
                  :key="child.id"
                  @click="activeTab = child.id; openDropdown = null"
                  :class="[
                    'w-full text-left px-4 py-2 text-sm flex items-center transition-colors',
                    activeTab === child.id
                      ? 'text-raspberry-600 bg-raspberry-50 font-medium'
                      : 'text-horizon-500 hover:bg-savannah-100'
                  ]"
                >
                  <svg class="w-4 h-4 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="getTabIcon(child.id)" />
                  </svg>
                  {{ child.label }}
                </button>
              </div>
            </div>
          </template>
        </nav>
      </div>

      <!-- Tab Content -->
      <div class="space-y-6">
        <AdminDashboard v-if="activeTab === 'dashboard'" />
        <UserMetrics v-if="activeTab === 'user-metrics'" />
        <UserManagement v-if="activeTab === 'users'" />
        <DatabaseBackup v-if="activeTab === 'backups'" />
        <DecisionMatrix v-if="activeTab === 'decision-matrix'" />
        <TaxSettings v-if="activeTab === 'tax-settings'" />
        <AiSettings v-if="activeTab === 'ai-settings'" />
        <AiAudit v-if="activeTab === 'ai-audit'" />
        <DiscountCodes v-if="activeTab === 'discount-codes'" />
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
import AiSettings from '../../components/Admin/AiSettings.vue';
import { defineAsyncComponent } from 'vue';
const DecisionMatrix = defineAsyncComponent(() => import('../../components/Admin/DecisionMatrix.vue'));
const UserMetrics = defineAsyncComponent(() => import('../../components/Admin/metrics/UserMetrics.vue'));
const AiAudit = defineAsyncComponent(() => import('../../components/Admin/AiAudit.vue'));
const DiscountCodes = defineAsyncComponent(() => import('../../components/Admin/DiscountCodes.vue'));

export default {
  name: 'AdminPanel',

  components: {
    AppLayout,
    AdminDashboard,
    UserManagement,
    DatabaseBackup,
    TaxSettings,
    AiSettings,
    DecisionMatrix,
    UserMetrics,
    AiAudit,
    DiscountCodes,
  },

  data() {
    return {
      activeTab: 'dashboard',
      openDropdown: null,
      navItems: [
        { id: 'dashboard', label: 'Dashboard', shortLabel: 'Home' },
        {
          group: 'users-group',
          label: 'Users',
          shortLabel: 'Users',
          children: [
            { id: 'user-metrics', label: 'User Metrics' },
            { id: 'users', label: 'User Management' },
          ],
        },
        {
          group: 'ai-group',
          label: 'AI',
          shortLabel: 'AI',
          children: [
            { id: 'ai-audit', label: 'AI Audit' },
            { id: 'ai-settings', label: 'AI Provider' },
          ],
        },
        { id: 'discount-codes', label: 'Discount Codes', shortLabel: 'Codes' },
        { id: 'decision-matrix', label: 'Decision Matrix', shortLabel: 'Matrix' },
        { id: 'tax-settings', label: 'Tax Settings', shortLabel: 'Tax' },
        { id: 'backups', label: 'Database', shortLabel: 'Data' },
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
        'user-metrics': 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z',
        'users-group': 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z',
        users: 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z',
        'decision-matrix': 'M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2',
        'tax-settings': 'M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z',
        'ai-group': 'M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z',
        'ai-settings': 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z',
        'ai-audit': 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
        'discount-codes': 'M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z',
        backups: 'M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4',
      };
      return icons[tabId] || '';
    },
  },

};
</script>

<style scoped>
.-webkit-overflow-scrolling-touch {
  -webkit-overflow-scrolling: touch;
}
</style>
