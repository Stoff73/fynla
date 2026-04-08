<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div>
        <h2 class="text-xl font-semibold text-horizon-500">Discount Codes</h2>
        <p class="text-sm text-neutral-500 mt-1">Manage promotional and campaign discount codes</p>
      </div>
      <button @click="openCreateModal" class="px-4 py-2 bg-raspberry-500 text-white text-sm font-medium rounded-lg hover:bg-raspberry-600 transition-colors">
        Add Code
      </button>
    </div>

    <!-- Success/Error Messages -->
    <div v-if="successMessage" class="rounded-md bg-spring-50 border border-spring-200 p-3 flex items-center gap-2">
      <svg class="w-4 h-4 text-spring-600 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" /></svg>
      <p class="text-sm text-spring-800">{{ successMessage }}</p>
    </div>
    <div v-if="error" class="rounded-md bg-raspberry-50 border border-raspberry-200 p-3 flex items-center gap-2">
      <svg class="w-4 h-4 text-raspberry-600 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" /></svg>
      <p class="text-sm text-raspberry-800">{{ error }}</p>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="flex justify-center py-12">
      <div class="w-10 h-10 border-4 border-horizon-200 border-t-raspberry-500 rounded-full animate-spin"></div>
    </div>

    <!-- Empty State -->
    <div v-else-if="discountCodes.length === 0" class="bg-white rounded-lg border border-light-gray p-12 text-center">
      <svg class="w-12 h-12 text-neutral-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
      </svg>
      <p class="text-body-base text-neutral-500">No discount codes yet.</p>
      <p class="text-body-sm text-neutral-400 mt-1">Create your first code to offer promotions.</p>
    </div>

    <!-- Table -->
    <div v-else class="bg-white rounded-lg border border-light-gray overflow-hidden">
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-light-gray">
          <thead class="bg-savannah-100">
            <tr>
              <th class="px-4 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Code</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Type</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Value</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Uses</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Status</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Valid Period</th>
              <th class="px-4 py-3 text-right text-xs font-medium text-neutral-500 uppercase tracking-wider">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-light-gray">
            <tr v-for="code in discountCodes" :key="code.id" class="hover:bg-savannah-50 transition-colors">
              <td class="px-4 py-3 text-sm font-mono font-semibold text-horizon-500">{{ code.code }}</td>
              <td class="px-4 py-3">
                <span :class="typeBadgeClass(code.type)" class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium">
                  {{ typeLabel(code.type) }}
                </span>
              </td>
              <td class="px-4 py-3 text-sm text-horizon-500">{{ formatValue(code) }}</td>
              <td class="px-4 py-3 text-sm text-neutral-500">{{ code.times_used }} / {{ code.max_uses ?? 'Unlimited' }}</td>
              <td class="px-4 py-3">
                <button
                  @click="toggleCode(code)"
                  :class="code.is_active ? 'bg-spring-500' : 'bg-neutral-300'"
                  class="relative inline-flex h-5 w-9 flex-shrink-0 cursor-pointer rounded-full transition-colors duration-200 ease-in-out focus:outline-none"
                >
                  <span
                    :class="code.is_active ? 'translate-x-4' : 'translate-x-0'"
                    class="inline-block h-4 w-4 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out mt-0.5 ml-0.5"
                  ></span>
                </button>
              </td>
              <td class="px-4 py-3 text-sm text-neutral-500">{{ formatPeriod(code) }}</td>
              <td class="px-4 py-3 text-right">
                <button @click="editCode(code)" class="text-horizon-400 hover:text-horizon-500 transition-colors mr-2" title="Edit">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                </button>
                <button @click="confirmDelete(code)" class="text-raspberry-400 hover:text-raspberry-600 transition-colors" title="Delete">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Create/Edit Modal -->
    <DiscountCodeModal
      v-if="showModal"
      :code="editingCode"
      :saving="modalSaving"
      @save="handleSave"
      @close="closeModal"
    />

    <!-- Delete Confirmation -->
    <div v-if="showDeleteConfirm" class="fixed inset-0 z-50 overflow-y-auto">
      <div class="flex items-center justify-center min-h-screen px-4">
        <div class="fixed inset-0 bg-savannah-1000/75" @click="showDeleteConfirm = false"></div>
        <div class="relative bg-white rounded-lg shadow-xl max-w-sm w-full p-6 z-10">
          <h3 class="text-lg font-semibold text-horizon-500 mb-2">Delete Discount Code</h3>
          <p class="text-sm text-neutral-500 mb-6">Are you sure you want to delete <strong class="font-mono">{{ deletingCode?.code }}</strong>? This action cannot be undone.</p>
          <div class="flex gap-3 justify-end">
            <button @click="showDeleteConfirm = false" class="px-4 py-2 text-sm text-neutral-500 hover:text-horizon-500 transition-colors">Cancel</button>
            <button @click="deleteCode" class="px-4 py-2 bg-raspberry-500 text-white text-sm font-medium rounded-lg hover:bg-raspberry-600 transition-colors">Delete</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import adminService from '@/services/adminService';
import DiscountCodeModal from './DiscountCodeModal.vue';

export default {
  name: 'DiscountCodes',
  components: { DiscountCodeModal },

  data() {
    return {
      loading: true,
      error: null,
      successMessage: '',
      discountCodes: [],
      showModal: false,
      editingCode: null,
      modalSaving: false,
      showDeleteConfirm: false,
      deletingCode: null,
      successTimeout: null,
    };
  },

  mounted() {
    this.loadDiscountCodes();
  },

  beforeUnmount() {
    if (this.successTimeout) clearTimeout(this.successTimeout);
  },

  methods: {
    async loadDiscountCodes() {
      this.loading = true;
      this.error = null;
      try {
        const response = await adminService.getDiscountCodes();
        if (response.data.success) {
          this.discountCodes = response.data.data;
        }
      } catch (err) {
        this.error = err.response?.data?.message || 'Failed to load discount codes';
      } finally {
        this.loading = false;
      }
    },

    openCreateModal() {
      this.editingCode = null;
      this.showModal = true;
    },

    editCode(code) {
      this.editingCode = { ...code };
      this.showModal = true;
    },

    closeModal() {
      this.showModal = false;
      this.editingCode = null;
    },

    async handleSave(formData) {
      this.modalSaving = true;
      this.error = null;
      try {
        if (this.editingCode) {
          await adminService.updateDiscountCode(this.editingCode.id, formData);
          this.showSuccess('Discount code updated');
        } else {
          await adminService.createDiscountCode(formData);
          this.showSuccess('Discount code created');
        }
        this.showModal = false;
        this.editingCode = null;
        this.loadDiscountCodes();
      } catch (err) {
        this.error = err.response?.data?.message || 'Operation failed';
      } finally {
        this.modalSaving = false;
      }
    },

    confirmDelete(code) {
      this.deletingCode = code;
      this.showDeleteConfirm = true;
    },

    async deleteCode() {
      if (!this.deletingCode) return;
      try {
        await adminService.deleteDiscountCode(this.deletingCode.id);
        this.showSuccess('Discount code deleted');
        this.showDeleteConfirm = false;
        this.deletingCode = null;
        this.loadDiscountCodes();
      } catch (err) {
        this.error = err.response?.data?.message || 'Delete failed';
        this.showDeleteConfirm = false;
      }
    },

    async toggleCode(code) {
      try {
        const response = await adminService.toggleDiscountCode(code.id);
        if (response.data.success) {
          code.is_active = response.data.data.is_active;
          this.showSuccess(response.data.message);
        }
      } catch (err) {
        this.error = err.response?.data?.message || 'Toggle failed';
      }
    },

    showSuccess(message) {
      this.successMessage = message;
      if (this.successTimeout) clearTimeout(this.successTimeout);
      this.successTimeout = setTimeout(() => { this.successMessage = ''; }, 3000);
    },

    typeLabel(type) {
      return { percentage: 'Percentage', fixed_amount: 'Fixed Amount', trial_extension: 'Trial Extension' }[type] || type;
    },

    typeBadgeClass(type) {
      return {
        percentage: 'bg-violet-100 text-violet-800',
        fixed_amount: 'bg-horizon-100 text-horizon-700',
        trial_extension: 'bg-spring-100 text-spring-800',
      }[type] || 'bg-neutral-100 text-neutral-600';
    },

    formatValue(code) {
      if (code.type === 'percentage') return `${code.value}%`;
      if (code.type === 'fixed_amount') return `\u00A3${(code.value / 100).toFixed(2)}`;
      if (code.type === 'trial_extension') return `${code.value} days`;
      return code.value;
    },

    formatPeriod(code) {
      if (!code.starts_at && !code.expires_at) return 'No expiry';
      const start = code.starts_at ? new Date(code.starts_at).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' }) : '';
      const end = code.expires_at ? new Date(code.expires_at).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' }) : '';
      if (start && end) return `${start} \u2014 ${end}`;
      if (start) return `From ${start}`;
      return `Until ${end}`;
    },
  },
};
</script>
