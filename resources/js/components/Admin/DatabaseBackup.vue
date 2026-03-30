<template>
  <div class="space-y-6">
    <!-- Header Section -->
    <div class="flex items-center justify-between">
      <div>
        <h2 class="text-xl font-semibold text-horizon-500">Database Backups</h2>
        <p class="text-sm text-neutral-500 mt-1">
          Create, manage, and restore database backups
        </p>
      </div>
      <button
        class="btn-primary inline-flex items-center"
        :disabled="creatingBackup"
        @click="createBackup"
      >
        <svg
          v-if="creatingBackup"
          class="animate-spin -ml-1 mr-2 h-4 w-4 text-white"
          fill="none"
          viewBox="0 0 24 24"
        >
          <circle
            class="opacity-25"
            cx="12"
            cy="12"
            r="10"
            stroke="currentColor"
            stroke-width="4"
          ></circle>
          <path
            class="opacity-75"
            fill="currentColor"
            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
          ></path>
        </svg>
        <svg v-else class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
        </svg>
        {{ creatingBackup ? 'Creating Backup...' : 'Create New Backup' }}
      </button>
    </div>

    <!-- Success Message -->
    <div
      v-if="successMessage"
      class="rounded-md bg-spring-50 border border-spring-200 p-4"
    >
      <div class="flex">
        <div class="flex-shrink-0">
          <svg class="h-5 w-5 text-spring-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
        </div>
        <div class="ml-3 flex-1">
          <p class="text-sm text-spring-800">{{ successMessage }}</p>
        </div>
        <div class="ml-auto pl-3">
          <button
            class="inline-flex text-spring-400 hover:text-spring-600"
            @click="successMessage = null"
          >
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>
      </div>
    </div>

    <!-- Error Message -->
    <div
      v-if="error"
      class="rounded-md bg-raspberry-50 border border-raspberry-200 p-4"
    >
      <div class="flex">
        <div class="flex-shrink-0">
          <svg class="h-5 w-5 text-raspberry-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
        </div>
        <div class="ml-3 flex-1">
          <p class="text-sm text-raspberry-800">{{ error }}</p>
        </div>
        <div class="ml-auto pl-3">
          <button
            class="inline-flex text-raspberry-400 hover:text-raspberry-600"
            @click="error = null"
          >
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="flex items-center justify-center py-12">
      <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-raspberry-600"></div>
    </div>

    <!-- Backups List -->
    <div v-else class="card">
      <div class="px-6 py-4 border-b border-light-gray">
        <div class="flex items-center justify-between">
          <h3 class="text-lg font-semibold text-horizon-500">Available Backups</h3>
          <button
            class="text-sm text-raspberry-600 hover:text-raspberry-700 inline-flex items-center"
            @click="loadBackups"
          >
            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
            </svg>
            Refresh
          </button>
        </div>
      </div>

      <!-- Empty State -->
      <div v-if="!backups || backups.length === 0" class="px-6 py-12 text-center">
        <svg
          class="mx-auto h-12 w-12 text-horizon-400"
          fill="none"
          stroke="currentColor"
          viewBox="0 0 24 24"
        >
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4" />
        </svg>
        <h3 class="mt-2 text-sm font-medium text-horizon-500">No backups found</h3>
        <p class="mt-1 text-sm text-neutral-500">
          Get started by creating your first database backup.
        </p>
      </div>

      <!-- Backups Table -->
      <div v-else class="overflow-x-auto">
        <table class="min-w-full divide-y divide-light-gray">
          <thead class="bg-savannah-100">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">
                Filename
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">
                Size
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">
                Created At
              </th>
              <th class="px-6 py-3 text-right text-xs font-medium text-neutral-500 uppercase tracking-wider">
                Actions
              </th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-light-gray">
            <tr v-for="backup in backups" :key="backup.filename">
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="flex items-center">
                  <svg class="w-5 h-5 text-horizon-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                  </svg>
                  <span class="text-sm font-medium text-horizon-500">{{ backup.filename }}</span>
                </div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-neutral-500">
                {{ formatFileSize(backup.size) }}
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-neutral-500">
                {{ formatDate(backup.created_at) }}
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-2">
                <button
                  class="text-raspberry-600 hover:text-raspberry-900 inline-flex items-center"
                  @click="confirmRestore(backup)"
                >
                  <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                  </svg>
                  Restore
                </button>
                <button
                  class="text-raspberry-600 hover:text-raspberry-900 inline-flex items-center"
                  @click="confirmDelete(backup)"
                >
                  <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                  </svg>
                  Delete
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Warning Box -->
    <div class="card bg-violet-50 border-violet-200">
      <div class="flex items-start">
        <div class="flex-shrink-0">
          <svg class="h-6 w-6 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
          </svg>
        </div>
        <div class="ml-3 flex-1">
          <h3 class="text-sm font-medium text-violet-900">Important Notes</h3>
          <div class="mt-2 text-sm text-violet-800">
            <ul class="list-disc pl-5 space-y-1">
              <li>Backups are stored in the <code class="bg-violet-100 px-1 rounded">storage/app/backups</code> directory</li>
              <li>Restoring a backup will <strong>overwrite all current data</strong> - this action cannot be undone</li>
              <li>Large databases may take several minutes to backup or restore</li>
              <li>Always create a backup before making major changes to the system</li>
            </ul>
          </div>
        </div>
      </div>
    </div>

    <!-- Confirmation Dialogs -->
    <ConfirmDialog
      :show="showRestoreConfirm"
      title="Restore Database Backup?"
      :message="`Are you sure you want to restore the backup '${selectedBackup?.filename}'? This will overwrite all current data and cannot be undone.`"
      type="warning"
      confirm-text="Restore Backup"
      cancel-text="Cancel"
      :loading="restoring"
      loading-text="Restoring..."
      @confirm="restoreBackup"
      @cancel="showRestoreConfirm = false"
    />

    <ConfirmDialog
      :show="showDeleteConfirm"
      title="Delete Backup?"
      :message="`Are you sure you want to delete the backup '${selectedBackup?.filename}'? This action cannot be undone.`"
      type="danger"
      confirm-text="Delete Backup"
      cancel-text="Cancel"
      :loading="deleting"
      loading-text="Deleting..."
      @confirm="deleteBackup"
      @cancel="showDeleteConfirm = false"
    />
  </div>
</template>

<script>
import adminService from '../../services/adminService';
import ConfirmDialog from '../Common/ConfirmDialog.vue';

import logger from '@/utils/logger';
export default {
  name: 'DatabaseBackup',

  components: {
    ConfirmDialog,
  },

  data() {
    return {
      loading: false,
      creatingBackup: false,
      restoring: false,
      deleting: false,
      backups: [],
      selectedBackup: null,
      showRestoreConfirm: false,
      showDeleteConfirm: false,
      successMessage: null,
      error: null,
    };
  },

  mounted() {
    this.loadBackups();
  },

  methods: {
    async loadBackups() {
      this.loading = true;
      this.error = null;

      try {
        const response = await adminService.listBackups();
        if (response.data.success) {
          this.backups = response.data.data;
        } else {
          this.error = response.data.message;
        }
      } catch (error) {
        logger.error('Failed to load backups:', error);
        if (error.response?.status === 429) {
          this.error = 'Please wait a moment before refreshing the backup list.';
        } else {
          this.error = error.response?.data?.message || 'Failed to load backup list';
        }
      } finally {
        this.loading = false;
      }
    },

    async createBackup() {
      this.creatingBackup = true;
      this.error = null;
      this.successMessage = null;

      try {
        const response = await adminService.createBackup();
        if (response.data.success) {
          this.successMessage = `Backup created successfully: ${response.data.data.filename}`;
          await this.loadBackups();
        } else {
          this.error = response.data.message;
        }
      } catch (error) {
        logger.error('Failed to create backup:', error);
        if (error.response?.status === 429) {
          this.error = 'Backup operations are limited to 3 per minute for safety. Please wait a moment and try again.';
        } else {
          this.error = error.response?.data?.message || 'Failed to create backup';
        }
      } finally {
        this.creatingBackup = false;
      }
    },

    confirmRestore(backup) {
      this.selectedBackup = backup;
      this.showRestoreConfirm = true;
    },

    async restoreBackup() {
      if (!this.selectedBackup) return;

      this.restoring = true;
      this.error = null;
      this.successMessage = null;

      try {
        const response = await adminService.restoreBackup(this.selectedBackup.filename);
        if (response.data.success) {
          this.successMessage = 'Database restored successfully from backup';
          this.showRestoreConfirm = false;
          this.selectedBackup = null;
          await this.loadBackups();
        } else {
          this.error = response.data.message;
        }
      } catch (error) {
        logger.error('Failed to restore backup:', error);
        if (error.response?.status === 429) {
          this.error = 'Backup operations are limited to 3 per minute for safety. Please wait a moment and try again.';
        } else {
          this.error = error.response?.data?.message || 'Failed to restore backup';
        }
      } finally {
        this.restoring = false;
      }
    },

    confirmDelete(backup) {
      this.selectedBackup = backup;
      this.showDeleteConfirm = true;
    },

    async deleteBackup() {
      if (!this.selectedBackup) return;

      this.deleting = true;
      this.error = null;
      this.successMessage = null;

      try {
        const response = await adminService.deleteBackup(this.selectedBackup.filename);
        if (response.data.success) {
          this.successMessage = 'Backup deleted successfully';
          this.showDeleteConfirm = false;
          this.selectedBackup = null;
          await this.loadBackups();
        } else {
          this.error = response.data.message;
        }
      } catch (error) {
        logger.error('Failed to delete backup:', error);
        if (error.response?.status === 429) {
          this.error = 'Backup operations are limited to 3 per minute for safety. Please wait a moment and try again.';
        } else {
          this.error = error.response?.data?.message || 'Failed to delete backup';
        }
      } finally {
        this.deleting = false;
      }
    },

    formatFileSize(bytes) {
      if (!bytes || bytes === 0) return '0 Bytes';

      const k = 1024;
      const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
      const i = Math.floor(Math.log(bytes) / Math.log(k));

      return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    },

    formatDate(dateString) {
      if (!dateString) return 'N/A';
      const date = new Date(dateString);
      return date.toLocaleDateString('en-GB', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
      });
    },
  },
};
</script>
