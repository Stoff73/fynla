<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <h2 class="text-xl font-bold text-horizon-500">Savings market rates</h2>
      <button
        class="px-4 py-2 bg-raspberry-500 text-white rounded-md hover:bg-raspberry-600 transition-colors"
        @click="openCreate"
      >
        Add rate
      </button>
    </div>

    <div v-if="loading" class="text-neutral-500 py-8 text-center">Loading...</div>
    <div v-else-if="error" class="text-raspberry-500 py-4">{{ error }}</div>

    <table v-else-if="items.length > 0" class="w-full bg-white rounded-lg shadow-sm overflow-hidden">
      <thead class="bg-savannah-50">
        <tr class="text-left text-sm text-horizon-400 uppercase">
          <th class="px-4 py-3">Rate key</th>
          <th class="px-4 py-3">Label</th>
          <th class="px-4 py-3 text-right">AER %</th>
          <th class="px-4 py-3">Tax year</th>
          <th class="px-4 py-3">Effective from</th>
          <th class="px-4 py-3 text-right">Actions</th>
        </tr>
      </thead>
      <tbody>
        <tr
          v-for="row in items"
          :key="row.id"
          class="border-t border-savannah-200 hover:bg-eggshell"
        >
          <td class="px-4 py-3 font-mono text-sm text-horizon-500">{{ row.rate_key }}</td>
          <td class="px-4 py-3">{{ row.label }}</td>
          <td class="px-4 py-3 text-right font-medium">{{ (row.rate * 100).toFixed(2) }}%</td>
          <td class="px-4 py-3">{{ row.tax_year }}</td>
          <td class="px-4 py-3">{{ row.effective_from }}</td>
          <td class="px-4 py-3 text-right">
            <button class="text-horizon-500 hover:text-horizon-700 mr-4" @click="openEdit(row)">Edit</button>
            <button class="text-raspberry-500 hover:text-raspberry-700" @click="handleDelete(row)">Delete</button>
          </td>
        </tr>
      </tbody>
    </table>

    <div v-else class="text-neutral-500 py-8 text-center">
      No savings market rates yet. Click "Add rate" to create the first one.
    </div>

    <!-- Inline form modal -->
    <div
      v-if="formModal.open"
      class="fixed inset-0 bg-horizon-500/50 flex items-center justify-center z-50"
      @click.self="closeForm"
    >
      <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-md mx-4">
        <h3 class="text-lg font-bold text-horizon-500 mb-4">
          {{ formModal.row ? 'Edit rate' : 'Add rate' }}
        </h3>
        <form @submit.prevent="handleSave">
          <div class="mb-4">
            <label class="block text-sm font-medium text-horizon-400 mb-1">Rate key</label>
            <input
              v-model="formData.rate_key"
              type="text"
              required
              placeholder="easy_access"
              class="w-full px-3 py-2 border border-savannah-300 rounded-md focus:outline-none focus:border-violet-400"
            />
          </div>
          <div class="mb-4">
            <label class="block text-sm font-medium text-horizon-400 mb-1">Label</label>
            <input
              v-model="formData.label"
              type="text"
              required
              placeholder="Easy Access"
              class="w-full px-3 py-2 border border-savannah-300 rounded-md focus:outline-none focus:border-violet-400"
            />
          </div>
          <div class="mb-4">
            <label class="block text-sm font-medium text-horizon-400 mb-1">AER (%)</label>
            <input
              v-model.number="ratePercent"
              type="number"
              step="0.01"
              min="0"
              max="100"
              required
              placeholder="4.75"
              class="w-full px-3 py-2 border border-savannah-300 rounded-md focus:outline-none focus:border-violet-400"
            />
            <p class="text-xs text-neutral-500 mt-1">Stored as a fraction (4.75% → 0.0475)</p>
          </div>
          <div class="mb-4">
            <label class="block text-sm font-medium text-horizon-400 mb-1">Tax year</label>
            <input
              v-model="formData.tax_year"
              type="text"
              required
              placeholder="2026/27"
              class="w-full px-3 py-2 border border-savannah-300 rounded-md focus:outline-none focus:border-violet-400"
            />
          </div>
          <div class="mb-6">
            <label class="block text-sm font-medium text-horizon-400 mb-1">Effective from</label>
            <input
              v-model="formData.effective_from"
              type="date"
              required
              class="w-full px-3 py-2 border border-savannah-300 rounded-md focus:outline-none focus:border-violet-400"
            />
          </div>
          <div v-if="formError" class="text-raspberry-500 text-sm mb-4">{{ formError }}</div>
          <div class="flex justify-end gap-3">
            <button
              type="button"
              class="px-4 py-2 text-horizon-500 hover:bg-savannah-50 rounded-md"
              @click="closeForm"
            >
              Cancel
            </button>
            <button
              type="submit"
              :disabled="saving"
              class="px-4 py-2 bg-raspberry-500 text-white rounded-md hover:bg-raspberry-600 disabled:opacity-50"
            >
              {{ saving ? 'Saving...' : 'Save' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script>
import { mapActions, mapGetters } from 'vuex';

export default {
  name: 'SavingsMarketRates',
  data() {
    return {
      formModal: { open: false, row: null },
      formData: { rate_key: '', label: '', tax_year: '', effective_from: '' },
      ratePercent: null,
      saving: false,
      formError: null,
    };
  },
  computed: {
    ...mapGetters('savingsMarketRates', ['items', 'loading', 'error']),
  },
  methods: {
    ...mapActions('savingsMarketRates', ['fetchItems', 'createItem', 'updateItem', 'deleteItem']),
    openCreate() {
      this.formData = { rate_key: '', label: '', tax_year: '2026/27', effective_from: '' };
      this.ratePercent = null;
      this.formError = null;
      this.formModal = { open: true, row: null };
    },
    openEdit(row) {
      this.formData = {
        rate_key: row.rate_key,
        label: row.label,
        tax_year: row.tax_year,
        effective_from: row.effective_from,
      };
      this.ratePercent = +(row.rate * 100).toFixed(2);
      this.formError = null;
      this.formModal = { open: true, row };
    },
    closeForm() {
      this.formModal = { open: false, row: null };
      this.saving = false;
      this.formError = null;
    },
    async handleSave() {
      this.saving = true;
      this.formError = null;
      const payload = {
        ...this.formData,
        rate: this.ratePercent != null ? +(this.ratePercent / 100).toFixed(6) : null,
      };
      try {
        if (this.formModal.row) {
          await this.updateItem({ id: this.formModal.row.id, payload });
        } else {
          await this.createItem(payload);
        }
        this.closeForm();
      } catch (e) {
        this.formError = e.response?.data?.message || e.message || 'Save failed';
        this.saving = false;
      }
    },
    async handleDelete(row) {
      if (!window.confirm(`Delete "${row.label}" (${row.rate_key}, ${row.tax_year})?`)) {
        return;
      }
      try {
        await this.deleteItem(row.id);
      } catch (e) {
        window.alert(e.response?.data?.message || e.message || 'Delete failed');
      }
    },
  },
  mounted() {
    this.fetchItems();
  },
};
</script>
