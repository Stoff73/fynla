<template>
  <div class="space-y-6">
    <!-- Header with Search and Create Button -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
      <div class="w-full sm:w-96">
        <div class="relative">
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Search users by name or email..."
            class="w-full pl-10 pr-4 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500 focus:border-transparent"
            @input="debouncedSearch"
          />
          <svg class="absolute left-3 top-2.5 w-5 h-5 text-horizon-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
          </svg>
        </div>
      </div>
      <div class="flex items-center gap-3">
        <select
          v-model="statusFilter"
          @change="currentPage = 1; loadUsers()"
          class="border border-horizon-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500 focus:border-transparent"
        >
          <option value="">All Statuses</option>
          <option value="trialing">Trialing</option>
          <option value="active">Active</option>
          <option value="expired">Expired</option>
          <option value="cancelled">Cancelled</option>
        </select>
        <button @click="openCreateModal" class="btn-primary whitespace-nowrap">
          <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
          </svg>
          Create User
        </button>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="flex items-center justify-center py-12">
      <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-raspberry-600"></div>
    </div>

    <!-- Users Table -->
    <div v-else class="card overflow-hidden">
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-light-gray">
          <thead class="bg-savannah-100">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">ID</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Name</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Email</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Modules</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Role</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Spouse</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Plan</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Status</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Trial</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Payment</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Created</th>
              <th class="px-6 py-3 text-right text-xs font-medium text-neutral-500 uppercase tracking-wider">Actions</th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-light-gray">
            <template v-for="user in users" :key="user.id">
            <tr class="hover:bg-savannah-100 cursor-pointer" @click="toggleExpandedUser(user.id)">
              <td class="px-6 py-4 whitespace-nowrap text-sm text-horizon-500">{{ user.id }}</td>
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm font-medium text-horizon-500">{{ user.name }}</div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-neutral-500">{{ user.email }}</td>
              <td class="px-6 py-4 whitespace-nowrap">
                <UserModuleStatus :user-id="user.id" />
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <span v-if="user.role?.name === 'admin'" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-raspberry-100 text-raspberry-800">
                  <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M2.166 4.999A11.954 11.954 0 0010 1.944 11.954 11.954 0 0017.834 5c.11.65.166 1.32.166 2.001 0 5.225-3.34 9.67-8 11.317C5.34 16.67 2 12.225 2 7c0-.682.057-1.35.166-2.001zm11.541 3.708a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                  </svg>
                  Admin
                </span>
                <span v-else-if="user.role?.name === 'support'" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-violet-100 text-violet-800">
                  Support
                </span>
                <span v-else class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-savannah-100 text-horizon-500">
                  User
                </span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-neutral-500">
                <span v-if="user.spouse">
                  <span class="inline-flex items-center text-spring-600">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                    </svg>
                    {{ user.spouse.name }}
                  </span>
                </span>
                <span v-else class="text-horizon-400">-</span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <span :class="planBadgeClass(user.plan)">
                  {{ user.plan || 'free' }}
                </span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <span v-if="user.subscription" :class="statusBadgeClass(user.subscription.status)">
                  {{ user.subscription.status }}
                </span>
                <span v-else class="text-horizon-400 text-sm">-</span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-neutral-500">
                <template v-if="user.subscription && user.subscription.status === 'trialing'">
                  Day {{ trialDay(user.subscription) }}/7
                </template>
                <template v-else-if="user.subscription && user.subscription.status === 'expired'">
                  Ended
                </template>
                <template v-else>-</template>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-neutral-500">
                <template v-if="lastPayment(user)">
                  {{ formatDate(lastPayment(user).created_at) }} &middot; £{{ (lastPayment(user).amount / 100).toFixed(2) }}
                </template>
                <template v-else>-</template>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-neutral-500">
                {{ formatDate(user.created_at) }}
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                <button
                  @click.stop="editUser(user)"
                  class="text-raspberry-600 hover:text-raspberry-900 mr-3"
                  title="Edit user"
                >
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                  </svg>
                </button>
                <button
                  @click.stop="confirmDeleteUser(user)"
                  class="text-raspberry-600 hover:text-raspberry-900"
                  title="Delete user"
                  :disabled="user.role?.name === 'admin' && totalAdmins === 1"
                >
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                  </svg>
                </button>
              </td>
            </tr>
            <!-- Expanded row -->
            <tr v-if="expandedUserId === user.id">
              <td :colspan="12" class="px-6 py-4 bg-eggshell-500">
                <div class="flex gap-6">
                  <UserModuleStatus :user-id="user.id" :expanded="true" />
                  <UserOnboardingProgress :user-id="user.id" />
                </div>
              </td>
            </tr>
            </template>
            <tr v-if="users.length === 0">
              <td colspan="12" class="px-6 py-8 text-center text-neutral-500">
                No users found
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="totalPages > 1" class="bg-savannah-100 px-6 py-4 flex items-center justify-between border-t border-light-gray">
        <div class="flex-1 flex justify-between sm:hidden">
          <button
            @click="previousPage"
            :disabled="currentPage === 1"
            class="relative inline-flex items-center px-4 py-2 border border-horizon-300 text-sm font-medium rounded-md text-neutral-500 bg-white hover:bg-savannah-100"
            :class="{ 'opacity-50 cursor-not-allowed': currentPage === 1 }"
          >
            Previous
          </button>
          <button
            @click="nextPage"
            :disabled="currentPage === totalPages"
            class="ml-3 relative inline-flex items-center px-4 py-2 border border-horizon-300 text-sm font-medium rounded-md text-neutral-500 bg-white hover:bg-savannah-100"
            :class="{ 'opacity-50 cursor-not-allowed': currentPage === totalPages }"
          >
            Next
          </button>
        </div>
        <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
          <div>
            <p class="text-sm text-neutral-500">
              Showing
              <span class="font-medium">{{ (currentPage - 1) * perPage + 1 }}</span>
              to
              <span class="font-medium">{{ Math.min(currentPage * perPage, totalUsers) }}</span>
              of
              <span class="font-medium">{{ totalUsers }}</span>
              users
            </p>
          </div>
          <div>
            <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
              <button
                @click="previousPage"
                :disabled="currentPage === 1"
                class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-horizon-300 bg-white text-sm font-medium text-neutral-500 hover:bg-savannah-100"
                :class="{ 'opacity-50 cursor-not-allowed': currentPage === 1 }"
              >
                <span class="sr-only">Previous</span>
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
              </button>
              <span class="relative inline-flex items-center px-4 py-2 border border-horizon-300 bg-white text-sm font-medium text-neutral-500">
                Page {{ currentPage }} of {{ totalPages }}
              </span>
              <button
                @click="nextPage"
                :disabled="currentPage === totalPages"
                class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-horizon-300 bg-white text-sm font-medium text-neutral-500 hover:bg-savannah-100"
                :class="{ 'opacity-50 cursor-not-allowed': currentPage === totalPages }"
              >
                <span class="sr-only">Next</span>
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
              </button>
            </nav>
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
          <h3 class="text-sm font-medium text-raspberry-900">Error</h3>
          <p class="mt-2 text-sm text-raspberry-800">{{ error }}</p>
        </div>
      </div>
    </div>

    <!-- Success Message -->
    <div v-if="successMessage" class="card bg-spring-50 border-spring-200">
      <div class="flex items-start">
        <div class="flex-shrink-0">
          <svg class="h-6 w-6 text-spring-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
        </div>
        <div class="ml-3">
          <p class="text-sm text-spring-800">{{ successMessage }}</p>
        </div>
      </div>
    </div>

    <!-- User Form Modal -->
    <UserFormModal
      :show="showModal"
      :user="selectedUser"
      :available-roles="availableRoles"
      @save="saveUser"
      @close="closeModal"
    />

    <!-- Delete Confirmation Dialog -->
    <ConfirmDialog
      v-if="showDeleteDialog"
      title="Delete User"
      :message="`Are you sure you want to delete ${userToDelete?.name}? This action cannot be undone.`"
      confirm-button-text="Delete User"
      confirm-button-class="bg-raspberry-600 hover:bg-raspberry-700"
      @confirm="deleteUser"
      @cancel="cancelDelete"
    />
  </div>
</template>

<script>
import adminService from '../../services/adminService';
import UserFormModal from './UserFormModal.vue';
import ConfirmDialog from '../Common/ConfirmDialog.vue';
import UserModuleStatus from './UserModuleStatus.vue';
import UserOnboardingProgress from './UserOnboardingProgress.vue';

import logger from '@/utils/logger';
export default {
  name: 'UserManagement',

  components: {
    UserFormModal,
    ConfirmDialog,
    UserModuleStatus,
    UserOnboardingProgress,
  },

  data() {
    return {
      users: [],
      loading: true,
      error: null,
      successMessage: null,
      searchQuery: '',
      currentPage: 1,
      perPage: 15,
      totalUsers: 0,
      totalPages: 0,
      totalAdmins: 0,
      showModal: false,
      selectedUser: null,
      isEditing: false,
      showDeleteDialog: false,
      userToDelete: null,
      statusFilter: '',
      searchTimeout: null,
      messageTimeout: null,
      availableRoles: [],
      expandedUserId: null,
    };
  },

  beforeUnmount() {
    if (this.searchTimeout) clearTimeout(this.searchTimeout);
    if (this.messageTimeout) clearTimeout(this.messageTimeout);
  },

  mounted() {
    this.loadUsers();
    this.loadRoles();
  },

  methods: {
    async loadUsers() {
      this.loading = true;
      this.error = null;

      try {
        const response = await adminService.getUsers({
          page: this.currentPage,
          per_page: this.perPage,
          search: this.searchQuery,
        });

        if (response.data.success) {
          this.users = response.data.data.data;
          this.totalUsers = response.data.data.total;
          this.totalPages = response.data.data.last_page;
          this.currentPage = response.data.data.current_page;

          // Count admins by role
          this.totalAdmins = this.users.filter(u => u.role?.name === 'admin').length;
        } else {
          this.error = response.data.message;
        }
      } catch (error) {
        logger.error('Failed to load users:', error);
        this.error = error.response?.data?.message || 'Failed to load users';
      } finally {
        this.loading = false;
      }
    },

    async loadRoles() {
      try {
        const response = await adminService.getRoles();
        if (response.data.success) {
          this.availableRoles = response.data.data;
        }
      } catch (error) {
        logger.error('Failed to load roles:', error);
      }
    },

    debouncedSearch() {
      clearTimeout(this.searchTimeout);
      this.searchTimeout = setTimeout(() => {
        this.currentPage = 1;
        this.loadUsers();
      }, 500);
    },

    toggleExpandedUser(userId) {
      this.expandedUserId = this.expandedUserId === userId ? null : userId;
    },

    openCreateModal() {
      this.selectedUser = null;
      this.isEditing = false;
      this.showModal = true;
    },

    editUser(user) {
      this.selectedUser = { ...user };
      this.isEditing = true;
      this.showModal = true;
    },

    async saveUser(userData) {
      this.error = null;
      this.successMessage = null;

      try {
        if (this.isEditing) {
          await adminService.updateUser(userData.id, userData);
          this.successMessage = 'User updated successfully';
        } else {
          await adminService.createUser(userData);
          this.successMessage = 'User created successfully';
        }

        this.closeModal();
        this.loadUsers();

        // Clear success message after 3 seconds
        if (this.messageTimeout) clearTimeout(this.messageTimeout);
        this.messageTimeout = setTimeout(() => {
          this.successMessage = null;
        }, 3000);
      } catch (error) {
        logger.error('Failed to save user:', error);
        this.error = error.response?.data?.message || 'Failed to save user';
      }
    },

    closeModal() {
      this.showModal = false;
      this.selectedUser = null;
      this.isEditing = false;
    },

    confirmDeleteUser(user) {
      if (user.role?.name === 'admin' && this.totalAdmins === 1) {
        this.error = 'Cannot delete the last admin user';
        return;
      }
      this.userToDelete = user;
      this.showDeleteDialog = true;
    },

    async deleteUser() {
      this.error = null;
      this.successMessage = null;

      try {
        await adminService.deleteUser(this.userToDelete.id);
        this.successMessage = 'User deleted successfully';
        this.showDeleteDialog = false;
        this.userToDelete = null;
        this.loadUsers();

        // Clear success message after 3 seconds
        if (this.messageTimeout) clearTimeout(this.messageTimeout);
        this.messageTimeout = setTimeout(() => {
          this.successMessage = null;
        }, 3000);
      } catch (error) {
        logger.error('Failed to delete user:', error);
        this.error = error.response?.data?.message || 'Failed to delete user';
        this.showDeleteDialog = false;
      }
    },

    cancelDelete() {
      this.showDeleteDialog = false;
      this.userToDelete = null;
    },

    previousPage() {
      if (this.currentPage > 1) {
        this.currentPage--;
        this.loadUsers();
      }
    },

    nextPage() {
      if (this.currentPage < this.totalPages) {
        this.currentPage++;
        this.loadUsers();
      }
    },

    planBadgeClass(plan) {
      const base = 'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium capitalize';
      const colors = {
        free: 'bg-savannah-100 text-horizon-500',
        student: 'bg-violet-100 text-violet-800',
        standard: 'bg-violet-200 text-violet-900',
        pro: 'bg-emerald-100 text-emerald-800',
      };
      return `${base} ${colors[plan] || colors.free}`;
    },

    statusBadgeClass(status) {
      const base = 'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium capitalize';
      const colors = {
        trialing: 'bg-violet-100 text-violet-800',
        active: 'bg-spring-100 text-spring-800',
        expired: 'bg-raspberry-100 text-raspberry-800',
        cancelled: 'bg-savannah-100 text-horizon-500',
        past_due: 'bg-raspberry-100 text-raspberry-800',
      };
      return `${base} ${colors[status] || 'bg-savannah-100 text-horizon-500'}`;
    },

    trialDay(subscription) {
      if (!subscription.trial_started_at) return '?';
      const start = new Date(subscription.trial_started_at);
      const now = new Date();
      const diffMs = now - start;
      const diffDays = Math.ceil(diffMs / (1000 * 60 * 60 * 24));
      return Math.min(Math.max(diffDays, 1), 7);
    },

    lastPayment(user) {
      if (!user.subscription || !user.subscription.payments || user.subscription.payments.length === 0) return null;
      const completed = user.subscription.payments.filter(p => p.status === 'completed');
      if (completed.length === 0) return null;
      return completed.sort((a, b) => new Date(b.created_at) - new Date(a.created_at))[0];
    },

    filteredUsers() {
      if (!this.statusFilter) return this.users;
      return this.users.filter(u => u.subscription?.status === this.statusFilter);
    },

    formatDate(dateString) {
      if (!dateString) return 'N/A';
      const date = new Date(dateString);
      return date.toLocaleDateString('en-GB', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
      });
    },
  },
};
</script>
