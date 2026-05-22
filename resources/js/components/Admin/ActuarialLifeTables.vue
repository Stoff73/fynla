<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <h2 class="text-xl font-bold text-horizon-500">Actuarial life tables</h2>
      <button
        class="px-4 py-2 bg-raspberry-500 text-white rounded-md hover:bg-raspberry-600 transition-colors"
        @click="openCreate"
      >
        Add row
      </button>
    </div>

    <div v-if="loading" class="text-neutral-500 py-8 text-center">Loading...</div>
    <div v-else-if="error" class="text-raspberry-500 py-4">{{ error }}</div>

    <table v-else-if="items.length > 0" class="w-full bg-white rounded-lg shadow-sm overflow-hidden">
      <thead class="bg-savannah-50">
        <tr class="text-left text-sm text-horizon-400 uppercase">
          <th class="px-4 py-3 text-right">Age</th>
          <th class="px-4 py-3">Gender</th>
          <th class="px-4 py-3 text-right">Life expectancy (yrs)</th>
          <th class="px-4 py-3 text-right">Probability of death</th>
          <th class="px-4 py-3">Table year</th>
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
          <td class="px-4 py-3 text-right font-mono text-sm">{{ row.age }}</td>
          <td class="px-4 py-3 capitalize">{{ row.gender }}</td>
          <td class="px-4 py-3 text-right">{{ Number(row.life_expectancy_years).toFixed(2) }}</td>
          <td class="px-4 py-3 text-right">{{ Number(row.probability_of_death).toFixed(5) }}</td>
          <td class="px-4 py-3">{{ row.table_year }}</td>
          <td class="px-4 py-3 text-sm text-horizon-400">{{ row.table_source }}</td>
          <td class="px-4 py-3 text-right">
            <button class="text-horizon-500 hover:text-horizon-700 mr-4" @click="openEdit(row)">Edit</button>
            <button class="text-raspberry-500 hover:text-raspberry-700" @click="handleDelete(row)">Delete</button>
          </td>
        </tr>
      </tbody>
    </table>

    <div v-else class="text-neutral-500 py-8 text-center">
      No actuarial life table rows yet. Click "Add row" to create the first one.
    </div>

    <!-- Inline form modal -->
    <div
      v-if="formModal.open"
      class="fixed inset-0 bg-horizon-500/50 flex items-center justify-center z-50"
      @click.self="closeForm"
    >
      <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-md mx-4">
        <h3 class="text-lg font-bold text-horizon-500 mb-4">
          {{ formModal.row ? 'Edit row' : 'Add row' }}
        </h3>
        <form @submit.prevent="handleSave">
          <div class="grid grid-cols-2 gap-4 mb-4">
            <div>
              <label class="block text-sm font-medium text-horizon-400 mb-1">Age</label>
              <input
                v-model.number="formData.age"
                type="number"
                min="0"
                max="125"
                step="1"
                required
                class="w-full px-3 py-2 border border-savannah-300 rounded-md focus:outline-none focus:border-violet-400"
              />
            </div>
            <div>
              <label class="block text-sm font-medium text-horizon-400 mb-1">Gender</label>
              <select
                v-model="formData.gender"
                required
                class="w-full px-3 py-2 border border-savannah-300 rounded-md focus:outline-none focus:border-violet-400"
              >
                <option value="">Select...</option>
                <option value="male">Male</option>
                <option value="female">Female</option>
              </select>
            </div>
          </div>
          <div class="mb-4">
            <label class="block text-sm font-medium text-horizon-400 mb-1">Life expectancy (years)</label>
            <input
              v-model.number="formData.life_expectancy_years"
              type="number"
              step="0.01"
              min="0.1"
              max="120"
              required
              placeholder="18.30"
              class="w-full px-3 py-2 border border-savannah-300 rounded-md focus:outline-none focus:border-violet-400"
            />
          </div>
          <div class="mb-4">
            <label class="block text-sm font-medium text-horizon-400 mb-1">Probability of death (per year)</label>
            <input
              v-model.number="formData.probability_of_death"
              type="number"
              step="0.00001"
              min="0"
              max="1"
              required
              placeholder="0.00989"
              class="w-full px-3 py-2 border border-savannah-300 rounded-md focus:outline-none focus:border-violet-400"
            />
            <p class="text-xs text-neutral-500 mt-1">Stored as a fraction (0.00989 = 0.989%)</p>
          </div>
          <div class="mb-4">
            <label class="block text-sm font-medium text-horizon-400 mb-1">Table year</label>
            <input
              v-model="formData.table_year"
              type="text"
              required
              placeholder="2020-2022"
              class="w-full px-3 py-2 border border-savannah-300 rounded-md focus:outline-none focus:border-violet-400"
            />
          </div>
          <div class="mb-6">
            <label class="block text-sm font-medium text-horizon-400 mb-1">Source</label>
            <input
              v-model="formData.table_source"
              type="text"
              required
              placeholder="UK ONS National Life Tables"
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
  name: 'ActuarialLifeTables',
  data() {
    return {
      formModal: { open: false, row: null },
      formData: {
        age: null,
        gender: '',
        life_expectancy_years: null,
        probability_of_death: null,
        table_year: '2020-2022',
        table_source: 'UK ONS National Life Tables',
      },
      saving: false,
      formError: null,
    };
  },
  computed: {
    ...mapGetters('actuarialLifeTables', ['items', 'loading', 'error']),
  },
  methods: {
    ...mapActions('actuarialLifeTables', ['fetchItems', 'createItem', 'updateItem', 'deleteItem']),
    openCreate() {
      this.formData = {
        age: null,
        gender: '',
        life_expectancy_years: null,
        probability_of_death: null,
        table_year: '2020-2022',
        table_source: 'UK ONS National Life Tables',
      };
      this.formError = null;
      this.formModal = { open: true, row: null };
    },
    openEdit(row) {
      this.formData = {
        age: row.age,
        gender: row.gender,
        life_expectancy_years: row.life_expectancy_years,
        probability_of_death: row.probability_of_death,
        table_year: row.table_year,
        table_source: row.table_source,
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
      try {
        if (this.formModal.row) {
          await this.updateItem({ id: this.formModal.row.id, payload: this.formData });
        } else {
          await this.createItem(this.formData);
        }
        this.closeForm();
      } catch (e) {
        this.formError = e.response?.data?.message || e.message || 'Save failed';
        this.saving = false;
      }
    },
    async handleDelete(row) {
      if (!window.confirm(`Delete age ${row.age} ${row.gender} (${row.table_year})?`)) {
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
