<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <h2 class="text-xl font-bold text-horizon-500">Currency rates</h2>
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
          <th class="px-4 py-3">From</th>
          <th class="px-4 py-3">To</th>
          <th class="px-4 py-3 text-right">Rate</th>
          <th class="px-4 py-3">Effective at</th>
          <th class="px-4 py-3">Source</th>
          <th class="px-4 py-3 text-right">Actions</th>
        </tr>
      </thead>
      <tbody>
        <tr
          v-for="row in items"
          :key="row.id"
          class="border-t border-savannah-200 hover:bg-eggshell"
        >
          <td class="px-4 py-3 font-mono text-sm">{{ row.from_ccy }}</td>
          <td class="px-4 py-3 font-mono text-sm">{{ row.to_ccy }}</td>
          <td class="px-4 py-3 text-right font-medium">{{ Number(row.rate).toFixed(6) }}</td>
          <td class="px-4 py-3 text-sm">{{ formatDate(row.effective_at) }}</td>
          <td class="px-4 py-3 text-sm text-horizon-400">{{ row.source }}</td>
          <td class="px-4 py-3 text-right">
            <button class="text-horizon-500 hover:text-horizon-700 mr-4" @click="openEdit(row)">Edit</button>
            <button class="text-raspberry-500 hover:text-raspberry-700" @click="handleDelete(row)">Delete</button>
          </td>
        </tr>
      </tbody>
    </table>

    <div v-else class="text-neutral-500 py-8 text-center">
      No currency rates yet. Click "Add rate" to create the first one.
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
          <div class="grid grid-cols-2 gap-4 mb-4">
            <div>
              <label class="block text-sm font-medium text-horizon-400 mb-1">From currency</label>
              <input
                v-model="formData.from_ccy"
                type="text"
                maxlength="3"
                required
                placeholder="GBP"
                class="w-full px-3 py-2 border border-savannah-300 rounded-md uppercase focus:outline-none focus:border-violet-400"
              />
            </div>
            <div>
              <label class="block text-sm font-medium text-horizon-400 mb-1">To currency</label>
              <input
                v-model="formData.to_ccy"
                type="text"
                maxlength="3"
                required
                placeholder="EUR"
                class="w-full px-3 py-2 border border-savannah-300 rounded-md uppercase focus:outline-none focus:border-violet-400"
              />
            </div>
          </div>
          <div class="mb-4">
            <label class="block text-sm font-medium text-horizon-400 mb-1">Rate (1 from = ? to)</label>
            <input
              v-model.number="formData.rate"
              type="number"
              step="0.00000001"
              min="0.00000001"
              required
              placeholder="1.17000000"
              class="w-full px-3 py-2 border border-savannah-300 rounded-md focus:outline-none focus:border-violet-400"
            />
          </div>
          <div class="mb-4">
            <label class="block text-sm font-medium text-horizon-400 mb-1">Effective at</label>
            <input
              v-model="formData.effective_at"
              type="datetime-local"
              required
              class="w-full px-3 py-2 border border-savannah-300 rounded-md focus:outline-none focus:border-violet-400"
            />
          </div>
          <div class="mb-6">
            <label class="block text-sm font-medium text-horizon-400 mb-1">Source</label>
            <input
              v-model="formData.source"
              type="text"
              maxlength="64"
              placeholder="manual"
              class="w-full px-3 py-2 border border-savannah-300 rounded-md focus:outline-none focus:border-violet-400"
            />
            <p class="text-xs text-neutral-500 mt-1">e.g. "manual", "feed:ecb", "feed:revolut"</p>
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
  name: 'CurrencyRates',
  data() {
    return {
      formModal: { open: false, row: null },
      formData: {
        from_ccy: 'GBP',
        to_ccy: '',
        rate: null,
        effective_at: '',
        source: 'manual',
      },
      saving: false,
      formError: null,
    };
  },
  computed: {
    ...mapGetters('currencyRates', ['items', 'loading', 'error']),
  },
  methods: {
    ...mapActions('currencyRates', ['fetchItems', 'createItem', 'updateItem', 'deleteItem']),
    formatDate(value) {
      if (!value) return '';
      const d = new Date(value);
      return Number.isNaN(d.getTime()) ? value : d.toISOString().slice(0, 16).replace('T', ' ');
    },
    toDatetimeLocal(value) {
      if (!value) return '';
      const d = new Date(value);
      if (Number.isNaN(d.getTime())) return '';
      // datetime-local input wants YYYY-MM-DDTHH:MM (no timezone)
      const pad = (n) => String(n).padStart(2, '0');
      return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
    },
    openCreate() {
      this.formData = {
        from_ccy: 'GBP',
        to_ccy: '',
        rate: null,
        effective_at: this.toDatetimeLocal(new Date()),
        source: 'manual',
      };
      this.formError = null;
      this.formModal = { open: true, row: null };
    },
    openEdit(row) {
      this.formData = {
        from_ccy: row.from_ccy,
        to_ccy: row.to_ccy,
        rate: row.rate,
        effective_at: this.toDatetimeLocal(row.effective_at),
        source: row.source,
      };
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
        from_ccy: (this.formData.from_ccy || '').toUpperCase(),
        to_ccy: (this.formData.to_ccy || '').toUpperCase(),
        // backend accepts YYYY-MM-DD HH:MM:SS — the datetime-local input gives YYYY-MM-DDTHH:MM
        effective_at: this.formData.effective_at
          ? `${this.formData.effective_at.replace('T', ' ')}:00`.slice(0, 19)
          : null,
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
      if (!window.confirm(`Delete ${row.from_ccy}→${row.to_ccy} (${row.effective_at})?`)) {
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
