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
            class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent"
            @input="debouncedSearch"
          />
          <svg class="absolute left-3 top-2.5 w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
          </svg>
        </div>
      </div>
      <button @click="openCreateModal" class="btn-primary whitespace-nowrap">
        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
        </svg>
        Create User
      </button>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="flex items-center justify-center py-12">
      <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-primary-600"></div>
    </div>

    <!-- Users Table -->
    <div v-else class="card overflow-hidden">
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Role</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Spouse</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created</th>
              <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200">
            <tr v-for="user in users" :key="user.id" class="hover:bg-gray-50">
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ user.id }}</td>
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm font-medium text-gray-900">{{ user.name }}</div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ user.email }}</td>
              <td class="px-6 py-4 whitespace-nowrap">
                <span v-if="user.is_admin" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                  <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M2.166 4.999A11.954 11.954 0 0010 1.944 11.954 11.954 0 0017.834 5c.11.65.166 1.32.166 2.001 0 5.225-3.34 9.67-8 11.317C5.34 16.67 2 12.225 2 7c0-.682.057-1.35.166-2.001zm11.541 3.708a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                  </svg>
                  Admin
                </span>
                <span v-else class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                  User
                </span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                <span v-if="user.spouse">
                  <span class="inline-flex items-center text-green-600">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                    </svg>
                    {{ user.spouse.name }}
                  </span>
                </span>
                <span v-else class="text-gray-400">-</span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                {{ formatDate(user.created_at) }}
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                <button
                  @click="editUser(user)"
                  class="text-primary-600 hover:text-primary-900 mr-3"
                  title="Edit user"
                >
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                  </svg>
                </button>
                <button
                  @click="confirmDeleteUser(user)"
                  class="text-red-600 hover:text-red-900"
                  title="Delete user"
                  :disabled="user.is_admin && totalAdmins === 1"
                >
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                  </svg>
                </button>
              </td>
            </tr>
            <tr v-if="users.length === 0">
              <td colspan="7" class="px-6 py-8 text-center text-gray-500">
                No users found
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="totalPages > 1" class="bg-gray-50 px-6 py-4 flex items-center justify-between border-t border-gray-200">
        <div class="flex-1 flex justify-between sm:hidden">
          <button
            @click="previousPage"
            :disabled="currentPage === 1"
            class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"
            :class="{ 'opacity-50 cursor-not-allowed': currentPage === 1 }"
          >
            Previous
          </button>
          <button
            @click="nextPage"
            :disabled="currentPage === totalPages"
            class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"
            :class="{ 'opacity-50 cursor-not-allowed': currentPage === totalPages }"
          >
            Next
          </button>
        </div>
        <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
          <div>
            <p class="text-sm text-gray-700">
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
                class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50"
                :class="{ 'opacity-50 cursor-not-allowed': currentPage === 1 }"
              >
                <span class="sr-only">Previous</span>
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
              </button>
              <span class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700">
                Page {{ currentPage }} of {{ totalPages }}
              </span>
              <button
                @click="nextPage"
                :disabled="currentPage === totalPages"
                class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50"
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
    <div v-if="error" class="card bg-red-50 border-red-200">
      <div class="flex items-start">
        <div class="flex-shrink-0">
          <svg class="h-6 w-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
        </div>
        <div class="ml-3">
          <h3 class="text-sm font-medium text-red-900">Error</h3>
          <p class="mt-2 text-sm text-red-800">{{ error }}</p>
        </div>
      </div>
    </div>

    <!-- Success Message -->
    <div v-if="successMessage" class="card bg-green-50 border-green-200">
      <div class="flex items-start">
        <div class="flex-shrink-0">
          <svg class="h-6 w-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
        </div>
        <div class="ml-3">
          <p class="text-sm text-green-800">{{ successMessage }}</p>
        </div>
      </div>
    </div>

    <!-- User Form Modal -->
    <UserFormModal
      v-if="showModal"
      :user="selectedUser"
      :is-editing="isEditing"
      @save="saveUser"
      @close="closeModal"
    />

    <!-- Delete Confirmation Dialog -->
    <ConfirmDialog
      v-if="showDeleteDialog"
      title="Delete User"
      :message="`Are you sure you want to delete ${userToDelete?.name}? This action cannot be undone.`"
      confirm-button-text="Delete User"
      confirm-button-class="bg-error-600 hover:bg-error-700"
      @confirm="deleteUser"
      @cancel="cancelDelete"
    />
  </div>
</template>

<script>
import adminService from '../../services/adminService';
import UserFormModal from './UserFormModal.vue';
import ConfirmDialog from '../Common/ConfirmDialog.vue';

export default {
  name: 'UserManagement',

  components: {
    UserFormModal,
    ConfirmDialog,
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
      searchTimeout: null,
      messageTimeout: null,
    };
  },

  beforeUnmount() {
    if (this.searchTimeout) clearTimeout(this.searchTimeout);
    if (this.messageTimeout) clearTimeout(this.messageTimeout);
  },

  mounted() {
    this.loadUsers();
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

          // Count admins
          this.totalAdmins = this.users.filter(u => u.is_admin).length;
        } else {
          this.error = response.data.message;
        }
      } catch (error) {
        console.error('Failed to load users:', error);
        this.error = error.response?.data?.message || 'Failed to load users';
      } finally {
        this.loading = false;
      }
    },

    debouncedSearch() {
      clearTimeout(this.searchTimeout);
      this.searchTimeout = setTimeout(() => {
        this.currentPage = 1;
        this.loadUsers();
      }, 500);
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
        console.error('Failed to save user:', error);
        this.error = error.response?.data?.message || 'Failed to save user';
      }
    },

    closeModal() {
      this.showModal = false;
      this.selectedUser = null;
      this.isEditing = false;
    },

    confirmDeleteUser(user) {
      if (user.is_admin && this.totalAdmins === 1) {
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
        console.error('Failed to delete user:', error);
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
