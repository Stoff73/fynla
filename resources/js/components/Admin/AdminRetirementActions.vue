<template>
  <div class="space-y-6">
    <!-- Header Section -->
    <div class="flex items-center justify-between">
      <div>
        <h2 class="text-xl font-semibold text-gray-900">Retirement Action Definitions</h2>
        <p class="text-sm text-gray-600 mt-1">
          Configure the actions that appear in retirement plans. Edit thresholds, toggle actions on/off, or create new ones.
        </p>
      </div>
      <button
        @click="openCreateModal"
        class="px-4 py-2 bg-primary-600 text-white rounded-button hover:bg-primary-700 transition-colors"
      >
        Add Action
      </button>
    </div>

    <!-- Error Message -->
    <div v-if="error" class="rounded-md bg-red-50 border border-red-200 p-4">
      <div class="flex">
        <svg class="h-5 w-5 text-red-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <p class="ml-3 text-sm text-red-800">{{ error }}</p>
      </div>
    </div>

    <!-- Success Message -->
    <div v-if="successMessage" class="rounded-md bg-green-50 border border-green-200 p-4">
      <div class="flex">
        <svg class="h-5 w-5 text-green-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <p class="ml-3 text-sm text-green-800">{{ successMessage }}</p>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="flex items-center justify-center py-12">
      <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-primary-600"></div>
    </div>

    <!-- Table -->
    <div v-else-if="definitions.length > 0" class="card overflow-hidden">
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Order</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Title</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Source</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Priority</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">What-if Type</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Trigger</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Enabled</th>
              <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200">
            <tr v-for="def in definitions" :key="def.id" :class="{ 'opacity-50': !def.is_enabled }">
              <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                {{ def.sort_order }}
              </td>
              <td class="px-4 py-3 text-sm text-gray-900">
                <div class="font-medium">{{ def.title_template }}</div>
                <div class="text-xs text-gray-500 mt-0.5">{{ def.key }}</div>
              </td>
              <td class="px-4 py-3 whitespace-nowrap">
                <span :class="sourceClass(def.source)">{{ def.source }}</span>
              </td>
              <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-700">
                {{ def.category }}
              </td>
              <td class="px-4 py-3 whitespace-nowrap">
                <span :class="priorityClass(def.priority)">{{ def.priority }}</span>
              </td>
              <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-700">
                {{ def.what_if_impact_type }}
              </td>
              <td class="px-4 py-3 text-sm text-gray-500">
                <span v-if="def.trigger_config && def.trigger_config.threshold">
                  {{ def.trigger_config.condition }}: {{ def.trigger_config.threshold }}
                </span>
                <span v-else-if="def.trigger_config">
                  {{ def.trigger_config.condition }}
                </span>
              </td>
              <td class="px-4 py-3 whitespace-nowrap">
                <button
                  @click="toggleAction(def)"
                  :class="[
                    'relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2',
                    def.is_enabled ? 'bg-primary-600' : 'bg-gray-200'
                  ]"
                >
                  <span
                    :class="[
                      'pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out',
                      def.is_enabled ? 'translate-x-5' : 'translate-x-0'
                    ]"
                  />
                </button>
              </td>
              <td class="px-4 py-3 whitespace-nowrap text-right text-sm">
                <button @click="openEditModal(def)" class="text-primary-600 hover:text-primary-800 mr-3">
                  Edit
                </button>
                <button @click="confirmDelete(def)" class="text-red-600 hover:text-red-800">
                  Delete
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Empty State -->
    <div v-else class="card p-12 text-center">
      <p class="text-gray-500">No retirement action definitions found. Create one to get started.</p>
    </div>

    <!-- Delete Confirmation -->
    <div v-if="showDeleteConfirm" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div class="bg-white rounded-lg p-6 max-w-md mx-4">
        <h3 class="text-lg font-medium text-gray-900 mb-2">Delete Action Definition</h3>
        <p class="text-sm text-gray-600 mb-4">
          Are you sure you want to delete "{{ deleteTarget?.title_template }}"? This cannot be undone.
        </p>
        <div class="flex justify-end space-x-3">
          <button @click="showDeleteConfirm = false" class="px-4 py-2 border border-gray-300 rounded-button text-gray-700 hover:bg-gray-50">
            Cancel
          </button>
          <button @click="executeDelete" class="px-4 py-2 bg-red-600 text-white rounded-button hover:bg-red-700">
            Delete
          </button>
        </div>
      </div>
    </div>

    <!-- Create/Edit Modal -->
    <RetirementActionModal
      v-if="showModal"
      :definition="editingDefinition"
      :saving="modalSaving"
      :server-errors="modalErrors"
      @save="handleSave"
      @close="closeModal"
    />
  </div>
</template>

<script>
import adminService from '../../services/adminService';
import RetirementActionModal from './RetirementActionModal.vue';

export default {
  name: 'AdminRetirementActions',

  components: {
    RetirementActionModal,
  },

  data() {
    return {
      definitions: [],
      loading: false,
      error: null,
      successMessage: null,
      showModal: false,
      editingDefinition: null,
      modalSaving: false,
      modalErrors: null,
      showDeleteConfirm: false,
      deleteTarget: null,
    };
  },

  mounted() {
    this.fetchDefinitions();
  },

  methods: {
    async fetchDefinitions() {
      this.loading = true;
      this.error = null;

      try {
        const response = await adminService.getRetirementActions();
        this.definitions = response.data.data || [];
      } catch (err) {
        this.error = 'Failed to load retirement action definitions.';
      } finally {
        this.loading = false;
      }
    },

    openCreateModal() {
      this.editingDefinition = null;
      this.showModal = true;
    },

    openEditModal(def) {
      this.editingDefinition = { ...def };
      this.showModal = true;
    },

    closeModal() {
      this.showModal = false;
      this.editingDefinition = null;
      this.modalSaving = false;
      this.modalErrors = null;
    },

    async handleSave(formData) {
      this.modalSaving = true;
      this.modalErrors = null;

      try {
        if (this.editingDefinition?.id) {
          await adminService.updateRetirementAction(this.editingDefinition.id, formData);
          this.showSuccess('Action definition updated successfully.');
        } else {
          await adminService.createRetirementAction(formData);
          this.showSuccess('Action definition created successfully.');
        }
        this.closeModal();
        await this.fetchDefinitions();
      } catch (err) {
        if (err.response?.data?.errors) {
          this.modalErrors = err.response.data.errors;
        } else {
          this.modalErrors = { general: [err.response?.data?.message || 'Failed to save action definition.'] };
        }
      } finally {
        this.modalSaving = false;
      }
    },

    async toggleAction(def) {
      try {
        const response = await adminService.toggleRetirementAction(def.id);
        def.is_enabled = response.data.data.is_enabled;
        this.showSuccess(response.data.message);
      } catch (err) {
        this.error = 'Failed to toggle action.';
      }
    },

    confirmDelete(def) {
      this.deleteTarget = def;
      this.showDeleteConfirm = true;
    },

    async executeDelete() {
      if (!this.deleteTarget) return;

      try {
        await adminService.deleteRetirementAction(this.deleteTarget.id);
        this.showDeleteConfirm = false;
        this.deleteTarget = null;
        this.showSuccess('Action definition deleted.');
        await this.fetchDefinitions();
      } catch (err) {
        this.error = 'Failed to delete action definition.';
        this.showDeleteConfirm = false;
      }
    },

    showSuccess(message) {
      this.successMessage = message;
      this.error = null;
      setTimeout(() => { this.successMessage = null; }, 3000);
    },

    priorityClass(priority) {
      const base = 'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium capitalize';

      return {
        critical: `${base} bg-red-100 text-red-800`,
        high: `${base} bg-blue-100 text-blue-800`,
        medium: `${base} bg-gray-100 text-gray-800`,
        low: `${base} bg-green-100 text-green-800`,
      }[priority] || `${base} bg-gray-100 text-gray-800`;
    },

    sourceClass(source) {
      const base = 'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium';

      return source === 'agent'
        ? `${base} bg-indigo-100 text-indigo-800`
        : `${base} bg-teal-100 text-teal-800`;
    },
  },
};
</script>
