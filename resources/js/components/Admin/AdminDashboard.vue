<template>
  <div class="space-y-6">
    <!-- Loading State -->
    <div v-if="loading" class="flex items-center justify-center py-12">
      <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-raspberry-600"></div>
    </div>

    <!-- Dashboard Content -->
    <div v-else>
      <!-- Statistics Cards -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
        <!-- Total Users -->
        <div class="card">
          <div class="flex items-center">
            <div class="flex-shrink-0">
              <div class="p-3 bg-violet-100 rounded-lg">
                <svg class="w-6 h-6 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                </svg>
              </div>
            </div>
            <div class="ml-4">
              <p class="text-sm font-medium text-neutral-500">Total Users</p>
              <p class="text-2xl font-bold text-horizon-500">{{ stats.total_users || 0 }}</p>
            </div>
          </div>
        </div>

        <!-- Admin Users -->
        <div class="card">
          <div class="flex items-center">
            <div class="flex-shrink-0">
              <div class="p-3 bg-raspberry-100 rounded-lg">
                <svg class="w-6 h-6 text-raspberry-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                </svg>
              </div>
            </div>
            <div class="ml-4">
              <p class="text-sm font-medium text-neutral-500">Administrators</p>
              <p class="text-2xl font-bold text-horizon-500">{{ stats.admin_users || 0 }}</p>
            </div>
          </div>
        </div>

        <!-- Linked Spouses -->
        <div class="card">
          <div class="flex items-center">
            <div class="flex-shrink-0">
              <div class="p-3 bg-spring-100 rounded-lg">
                <svg class="w-6 h-6 text-spring-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                </svg>
              </div>
            </div>
            <div class="ml-4">
              <p class="text-sm font-medium text-neutral-500">Linked Spouses</p>
              <p class="text-2xl font-bold text-horizon-500">{{ stats.linked_spouses || 0 }}</p>
            </div>
          </div>
        </div>

        <!-- Database Size -->
        <div class="card">
          <div class="flex items-center">
            <div class="flex-shrink-0">
              <div class="p-3 bg-purple-100 rounded-lg">
                <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4" />
                </svg>
              </div>
            </div>
            <div class="ml-4">
              <p class="text-sm font-medium text-neutral-500">Database Size</p>
              <p class="text-2xl font-bold text-horizon-500">{{ stats.database_size || 'N/A' }}</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Subscription Stats -->
      <div v-if="subStats" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
        <div class="card">
          <div class="flex items-center">
            <div class="flex-shrink-0">
              <div class="p-3 bg-violet-100 rounded-lg">
                <svg class="w-6 h-6 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
              </div>
            </div>
            <div class="ml-4">
              <p class="text-sm font-medium text-neutral-500">Trialing</p>
              <p class="text-2xl font-bold text-horizon-500">{{ subStats.trialing || 0 }}</p>
            </div>
          </div>
        </div>

        <div class="card">
          <div class="flex items-center">
            <div class="flex-shrink-0">
              <div class="p-3 bg-spring-100 rounded-lg">
                <svg class="w-6 h-6 text-spring-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
              </div>
            </div>
            <div class="ml-4">
              <p class="text-sm font-medium text-neutral-500">Active</p>
              <p class="text-2xl font-bold text-horizon-500">{{ subStats.active || 0 }}</p>
            </div>
          </div>
        </div>

        <div class="card">
          <div class="flex items-center">
            <div class="flex-shrink-0">
              <div class="p-3 bg-raspberry-100 rounded-lg">
                <svg class="w-6 h-6 text-raspberry-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
              </div>
            </div>
            <div class="ml-4">
              <p class="text-sm font-medium text-neutral-500">Expired</p>
              <p class="text-2xl font-bold text-horizon-500">{{ subStats.expired || 0 }}</p>
            </div>
          </div>
        </div>

        <div class="card">
          <div class="flex items-center">
            <div class="flex-shrink-0">
              <div class="p-3 bg-emerald-100 rounded-lg">
                <svg class="w-6 h-6 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
              </div>
            </div>
            <div class="ml-4">
              <p class="text-sm font-medium text-neutral-500">Total Revenue</p>
              <p class="text-2xl font-bold text-horizon-500">£{{ ((subStats.total_revenue || 0) / 100).toFixed(2) }}</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Recent Users -->
      <div class="card">
        <div class="px-6 py-4 border-b border-light-gray">
          <h3 class="text-lg font-semibold text-horizon-500">Recent Users</h3>
        </div>
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-light-gray">
            <thead class="bg-savannah-100">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">ID</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Name</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Email</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Created At</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-light-gray">
              <tr v-for="user in stats.recent_users" :key="user.id">
                <td class="px-6 py-4 whitespace-nowrap text-sm text-horizon-500">{{ user.id }}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-horizon-500">{{ user.name }}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-neutral-500">{{ user.email }}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-neutral-500">{{ formatDate(user.created_at) }}</td>
              </tr>
              <tr v-if="!stats.recent_users || stats.recent_users.length === 0">
                <td colspan="4" class="px-6 py-8 text-center text-neutral-500">No users found</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Backup Info -->
      <div class="card bg-violet-50 border-violet-200">
        <div class="flex items-start">
          <div class="flex-shrink-0">
            <svg class="h-6 w-6 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </div>
          <div class="ml-3 flex-1">
            <h3 class="text-sm font-medium text-violet-900">Database Backup Status</h3>
            <div class="mt-2 text-sm text-violet-800">
              <p v-if="stats.last_backup">
                Last backup: <strong>{{ stats.last_backup }}</strong>
              </p>
              <p v-else>
                No backups created yet. Create your first backup in the Backups tab.
              </p>
            </div>
            <div class="mt-4">
              <button
                @click="refreshData"
                class="btn-primary"
              >
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                </svg>
                Refresh Dashboard
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Error State -->
    <div v-if="error" class="card bg-raspberry-50 border-raspberry-200">
      <div class="flex items-start">
        <div class="flex-shrink-0">
          <svg class="h-6 w-6 text-raspberry-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
        </div>
        <div class="ml-3">
          <h3 class="text-sm font-medium text-raspberry-900">Error Loading Dashboard</h3>
          <p class="mt-2 text-sm text-raspberry-800">{{ error }}</p>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import adminService from '../../services/adminService';

import logger from '@/utils/logger';
export default {
  name: 'AdminDashboard',

  data() {
    return {
      loading: true,
      error: null,
      stats: {},
      subStats: null,
    };
  },

  mounted() {
    this.loadDashboard();
    this.loadSubscriptionStats();
  },

  methods: {
    async loadDashboard() {
      this.loading = true;
      this.error = null;

      try {
        const response = await adminService.getDashboard();
        if (response.data.success) {
          this.stats = response.data.data;
        } else {
          this.error = response.data.message;
        }
      } catch (error) {
        logger.error('Failed to load dashboard:', error);
        this.error = error.response?.data?.message || 'Failed to load dashboard data';
      } finally {
        this.loading = false;
      }
    },

    async loadSubscriptionStats() {
      try {
        const response = await adminService.getSubscriptionStats();
        if (response.data.success) {
          this.subStats = response.data.data;
        }
      } catch {
        // Silently fail — subscription stats are supplementary
      }
    },

    refreshData() {
      this.loadDashboard();
      this.loadSubscriptionStats();
    },

    formatDate(dateString) {
      if (!dateString) return 'N/A';
      const date = new Date(dateString);
      return date.toLocaleDateString('en-GB', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
      });
    },
  },
};
</script>
