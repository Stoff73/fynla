<template>
  <div v-if="isOpen" class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
      <!-- Background overlay -->
      <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="close"></div>

      <!-- Modal panel -->
      <div class="relative z-10 inline-block align-bottom bg-white rounded-lg text-left shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full max-h-[90vh] overflow-y-auto">
        <form @submit.prevent="handleSubmit">
          <div class="bg-white px-4 pt-5 pb-4 sm:p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">
              {{ isEditing ? 'Edit Life Event' : 'Add Life Event' }}
            </h3>

            <!-- Event Name -->
            <div class="mb-4">
              <label class="block text-sm font-medium text-gray-700 mb-1">Event Name</label>
              <input
                v-model="form.event_name"
                type="text"
                class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-primary-500 focus:border-primary-500"
                placeholder="e.g., Parents' Estate, New Car"
                required
              />
            </div>

            <!-- Event Type -->
            <div class="mb-4">
              <label class="block text-sm font-medium text-gray-700 mb-1">Event Type</label>
              <select
                v-model="form.event_type"
                class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-primary-500 focus:border-primary-500"
                required
              >
                <option value="">Select a type...</option>
                <optgroup label="Income Events">
                  <option v-for="type in incomeTypes" :key="type.type" :value="type.type">
                    {{ type.label }}
                  </option>
                </optgroup>
                <optgroup label="Expense Events">
                  <option v-for="type in expenseTypes" :key="type.type" :value="type.type">
                    {{ type.label }}
                  </option>
                </optgroup>
              </select>
              <p v-if="selectedTypeDescription" class="mt-1 text-xs text-gray-500">
                {{ selectedTypeDescription }}
              </p>
            </div>

            <!-- Amount -->
            <div class="mb-4">
              <label class="block text-sm font-medium text-gray-700 mb-1">Expected Amount</label>
              <div class="relative">
                <span class="absolute left-3 top-2 text-gray-500">£</span>
                <input
                  v-model.number="form.amount"
                  type="number"
                  min="1"
                  step="1"
                  class="w-full pl-7 pr-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-primary-500 focus:border-primary-500"
                  placeholder="50000"
                  required
                />
              </div>
            </div>

            <!-- Expected Date -->
            <div class="mb-4">
              <label class="block text-sm font-medium text-gray-700 mb-1">Expected Date</label>
              <input
                v-model="form.expected_date"
                type="date"
                :min="minDate"
                class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-primary-500 focus:border-primary-500"
                required
              />
            </div>

            <!-- Certainty -->
            <div class="mb-4">
              <label class="block text-sm font-medium text-gray-700 mb-2">How certain is this event?</label>
              <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                <button
                  v-for="level in certaintyLevels"
                  :key="level.value"
                  type="button"
                  @click="form.certainty = level.value"
                  class="px-3 py-2 text-sm rounded-md border transition-colors"
                  :class="form.certainty === level.value ? level.activeClass : 'border-gray-300 text-gray-700 hover:bg-gray-50'"
                >
                  {{ level.label }}
                </button>
              </div>
            </div>

            <!-- Description -->
            <div class="mb-4">
              <label class="block text-sm font-medium text-gray-700 mb-1">Description (optional)</label>
              <textarea
                v-model="form.description"
                rows="2"
                class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-primary-500 focus:border-primary-500"
                placeholder="Add any relevant details..."
              ></textarea>
            </div>

            <!-- Projection Settings -->
            <div class="border-t border-gray-200 pt-4 mt-4">
              <h4 class="text-sm font-medium text-gray-900 mb-3">Projection Settings</h4>

              <div class="space-y-3">
                <label class="flex items-center">
                  <input
                    v-model="form.show_in_projection"
                    type="checkbox"
                    class="h-4 w-4 text-primary-600 focus:ring-primary-500 border-gray-300 rounded"
                  />
                  <span class="ml-2 text-sm text-gray-700">Show in projection chart</span>
                </label>

                <label class="flex items-center">
                  <input
                    v-model="form.show_in_household_view"
                    type="checkbox"
                    class="h-4 w-4 text-primary-600 focus:ring-primary-500 border-gray-300 rounded"
                  />
                  <span class="ml-2 text-sm text-gray-700">Show in household view</span>
                </label>
              </div>
            </div>
          </div>

          <!-- Validation Errors -->
          <div v-if="validationErrors.length" class="px-4 sm:px-6 pb-2">
            <div class="p-3 bg-red-50 border border-red-200 rounded-md">
              <ul class="list-disc list-inside text-sm text-red-700 space-y-1">
                <li v-for="error in validationErrors" :key="error">{{ error }}</li>
              </ul>
            </div>
          </div>

          <!-- Footer -->
          <div class="bg-gray-50 px-4 py-3 sm:px-6 flex justify-end gap-3">
            <button
              type="button"
              @click="close"
              class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50"
            >
              Cancel
            </button>
            <button
              type="submit"
              :disabled="loading"
              class="px-4 py-2 text-sm font-medium text-white bg-primary-600 border border-transparent rounded-md hover:bg-primary-700 disabled:opacity-50"
            >
              {{ loading ? 'Saving...' : (isEditing ? 'Update Event' : 'Add Event') }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script>
import { mapState, mapActions } from 'vuex';

export default {
  name: 'LifeEventForm',

  props: {
    isOpen: {
      type: Boolean,
      default: false,
    },
    event: {
      type: Object,
      default: null,
    },
  },

  emits: ['close', 'save'],

  data() {
    return {
      form: this.getDefaultForm(),
      loading: false,
      validationErrors: [],
      certaintyLevels: [
        { value: 'confirmed', label: 'Confirmed', activeClass: 'border-green-500 bg-green-50 text-green-700' },
        { value: 'likely', label: 'Likely', activeClass: 'border-blue-500 bg-blue-50 text-blue-700' },
        { value: 'possible', label: 'Possible', activeClass: 'border-blue-500 bg-blue-50 text-blue-700' },
        { value: 'speculative', label: 'Speculative', activeClass: 'border-gray-500 bg-gray-50 text-gray-700' },
      ],
    };
  },

  computed: {
    ...mapState('goals', ['eventTypes']),

    isEditing() {
      return !!this.event;
    },

    minDate() {
      const tomorrow = new Date();
      tomorrow.setDate(tomorrow.getDate() + 1);
      return tomorrow.toISOString().split('T')[0];
    },

    incomeTypes() {
      return (this.eventTypes || []).filter(t => t.impact_type === 'income');
    },

    expenseTypes() {
      return (this.eventTypes || []).filter(t => t.impact_type === 'expense');
    },

    selectedTypeDescription() {
      if (!this.form.event_type) return null;
      const type = this.eventTypes?.find(t => t.type === this.form.event_type);
      return type?.description || null;
    },
  },

  watch: {
    event: {
      handler() {
        this.initForm();
      },
      immediate: true,
    },
  },

  mounted() {
    this.fetchEventTypes();
  },

  methods: {
    ...mapActions('goals', ['fetchEventTypes']),

    getDefaultForm() {
      return {
        event_name: '',
        event_type: '',
        description: '',
        amount: null,
        expected_date: '',
        certainty: 'likely',
        show_in_projection: true,
        show_in_household_view: true,
      };
    },

    initForm() {
      if (this.event) {
        this.form = {
          event_name: this.event.event_name || '',
          event_type: this.event.event_type || '',
          description: this.event.description || '',
          amount: parseFloat(this.event.amount) || null,
          expected_date: this.event.expected_date ? this.event.expected_date.split('T')[0] : '',
          certainty: this.event.certainty || 'likely',
          show_in_projection: this.event.show_in_projection ?? true,
          show_in_household_view: this.event.show_in_household_view ?? true,
        };
      } else {
        this.form = this.getDefaultForm();
      }
      this.validationErrors = [];
    },

    handleSubmit() {
      this.validationErrors = [];

      if (!this.form.event_name) this.validationErrors.push('Event name is required');
      if (!this.form.event_type) this.validationErrors.push('Event type is required');
      if (!this.form.amount) this.validationErrors.push('Amount is required');
      if (!this.form.expected_date) this.validationErrors.push('Expected date is required');

      if (this.validationErrors.length > 0) {
        return;
      }

      this.loading = true;
      try {
        this.$emit('save', { ...this.form });
      } finally {
        this.loading = false;
      }
    },

    close() {
      this.$emit('close');
    },
  },
};
</script>
