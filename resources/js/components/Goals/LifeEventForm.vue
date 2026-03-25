<template>
  <div v-if="isOpen" class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
      <!-- Background overlay -->
      <div class="fixed inset-0 bg-savannah-1000 bg-opacity-75 transition-opacity" @click="close"></div>

      <!-- Modal panel -->
      <div class="relative z-10 inline-block align-bottom bg-white rounded-lg text-left shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full max-h-[90vh] overflow-y-auto">
        <form @submit.prevent="handleSubmit">
          <div class="bg-white px-4 pt-5 pb-4 sm:p-6">
            <h3 class="text-lg font-semibold text-horizon-500 mb-4">
              {{ isEditing ? 'Edit Life Event' : 'Add Life Event' }}
            </h3>

            <!-- Event Name -->
            <div class="mb-4" :class="{ 'ai-fill-highlight rounded-lg': highlightedField === 'event_name' }">
              <label class="block text-sm font-medium text-neutral-500 mb-1">Event Name</label>
              <input
                v-model="form.event_name"
                type="text"
                class="w-full px-3 py-2 border border-horizon-300 rounded-md shadow-sm focus:ring-violet-500 focus:border-raspberry-500"
                placeholder="e.g., Parents' Estate, New Car"
                required
              />
            </div>

            <!-- Event Type -->
            <div class="mb-4" :class="{ 'ai-fill-highlight rounded-lg': highlightedField === 'event_type' }">
              <label class="block text-sm font-medium text-neutral-500 mb-1">Event Type</label>
              <select
                v-model="form.event_type"
                class="w-full px-3 py-2 border border-horizon-300 rounded-md shadow-sm focus:ring-violet-500 focus:border-raspberry-500"
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
              <p v-if="selectedTypeDescription" class="mt-1 text-xs text-neutral-500">
                {{ selectedTypeDescription }}
              </p>
            </div>

            <!-- Amount -->
            <div class="mb-4" :class="{ 'ai-fill-highlight rounded-lg': highlightedField === 'amount' }">
              <label class="block text-sm font-medium text-neutral-500 mb-1">Expected Amount</label>
              <div class="relative">
                <span class="absolute left-3 top-2 text-neutral-500">£</span>
                <input
                  v-model.number="form.amount"
                  type="number"
                  min="1"
                  step="1"
                  class="w-full pl-7 pr-3 py-2 border border-horizon-300 rounded-md shadow-sm focus:ring-violet-500 focus:border-raspberry-500"
                  placeholder="50000"
                  required
                />
              </div>
            </div>

            <!-- Expected Date -->
            <div class="mb-4" :class="{ 'ai-fill-highlight rounded-lg': highlightedField === 'expected_date' }">
              <label class="block text-sm font-medium text-neutral-500 mb-1">Expected Date</label>
              <input
                v-model="form.expected_date"
                type="date"
                :min="minDate"
                class="w-full px-3 py-2 border border-horizon-300 rounded-md shadow-sm focus:ring-violet-500 focus:border-raspberry-500"
                required
              />
            </div>

            <!-- Certainty -->
            <div class="mb-4" :class="{ 'ai-fill-highlight rounded-lg': highlightedField === 'certainty' }">
              <label class="block text-sm font-medium text-neutral-500 mb-2">How certain is this event?</label>
              <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                <button
                  v-for="level in certaintyLevels"
                  :key="level.value"
                  type="button"
                  @click="form.certainty = level.value"
                  class="px-3 py-2 text-sm rounded-md border transition-colors"
                  :class="form.certainty === level.value ? level.activeClass : 'border-horizon-300 text-neutral-500 hover:bg-savannah-100'"
                >
                  {{ level.label }}
                </button>
              </div>
            </div>

            <!-- Description -->
            <div class="mb-4">
              <label class="block text-sm font-medium text-neutral-500 mb-1">Description (optional)</label>
              <textarea
                v-model="form.description"
                rows="2"
                class="w-full px-3 py-2 border border-horizon-300 rounded-md shadow-sm focus:ring-violet-500 focus:border-raspberry-500"
                placeholder="Add any relevant details..."
              ></textarea>
            </div>

            <!-- Projection Settings -->
            <div class="border-t border-light-gray pt-4 mt-4">
              <h4 class="text-sm font-medium text-horizon-500 mb-3">Projection Settings</h4>

              <div class="space-y-3">
                <label class="flex items-center">
                  <input
                    v-model="form.show_in_projection"
                    type="checkbox"
                    class="h-4 w-4 text-raspberry-600 focus:ring-violet-500 border-horizon-300 rounded"
                  />
                  <span class="ml-2 text-sm text-neutral-500">Show in projection chart</span>
                </label>

                <label class="flex items-center">
                  <input
                    v-model="form.show_in_household_view"
                    type="checkbox"
                    class="h-4 w-4 text-raspberry-600 focus:ring-violet-500 border-horizon-300 rounded"
                  />
                  <span class="ml-2 text-sm text-neutral-500">Show in household view</span>
                </label>
              </div>
            </div>
          </div>

          <!-- Validation Errors -->
          <div v-if="validationErrors.length" class="px-4 sm:px-6 pb-2">
            <div class="p-3 bg-raspberry-50 border border-raspberry-200 rounded-md">
              <ul class="list-disc list-inside text-sm text-raspberry-700 space-y-1">
                <li v-for="error in validationErrors" :key="error">{{ error }}</li>
              </ul>
            </div>
          </div>

          <!-- Footer -->
          <div class="bg-savannah-100 px-4 py-3 sm:px-6 flex justify-end gap-3">
            <button
              type="button"
              @click="close"
              class="px-4 py-2 text-sm font-medium text-neutral-500 bg-white border border-horizon-300 rounded-button hover:bg-savannah-100"
            >
              Cancel
            </button>
            <button
              type="submit"
              :disabled="loading"
              class="px-4 py-2 text-sm font-medium text-white bg-raspberry-600 border border-transparent rounded-button hover:bg-raspberry-700 disabled:opacity-50"
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
        { value: 'confirmed', label: 'Confirmed', activeClass: 'border-spring-500 bg-spring-50 text-spring-700' },
        { value: 'likely', label: 'Likely', activeClass: 'border-violet-500 bg-violet-50 text-violet-700' },
        { value: 'possible', label: 'Possible', activeClass: 'border-violet-500 bg-violet-50 text-violet-700' },
        { value: 'speculative', label: 'Speculative', activeClass: 'border-neutral-500 bg-savannah-100 text-neutral-500' },
      ],
    };
  },

  computed: {
    ...mapState('goals', ['eventTypes']),
    ...mapState('aiFormFill', ['pendingFill', 'highlightedField', 'filling']),

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

    pendingFill(fill) {
      if (fill && fill.entityType === 'life_event' && fill.fields) {
        // Pre-set key fields before field sequence
        if (fill.fields.event_name) {
          this.form.event_name = fill.fields.event_name;
        }
        if (fill.fields.event_type) {
          this.form.event_type = fill.fields.event_type;
        }
        if (fill.fields.amount) {
          this.form.amount = fill.fields.amount;
        }
        if (fill.fields.expected_date) {
          this.form.expected_date = fill.fields.expected_date;
        }
        const fieldOrder = Object.keys(fill.fields).filter(k => fill.fields[k] !== null && fill.fields[k] !== '');
        this.$store.dispatch('aiFormFill/beginFieldSequence', fieldOrder);
      }
    },

    highlightedField(fieldKey) {
      if (fieldKey && this.pendingFill?.fields) {
        const value = this.pendingFill.fields[fieldKey];
        if (value !== undefined) {
          this.form[fieldKey] = value;
        }
      }
    },

    filling(isFilling) {
      if (isFilling === false && this.pendingFill?.entityType === 'life_event') {
        setTimeout(() => {
          this.$nextTick(() => {
            this.handleSubmit();
            if (this.validationErrors.length > 0) {
              const errorList = this.validationErrors.join(', ');
              this.$store.commit('aiChat/ADD_MESSAGE', {
                id: 'fill_error_' + Date.now(),
                role: 'assistant',
                content: `I wasn't able to save the life event — ${errorList}. Please check the form and try again.`,
                created_at: new Date().toISOString(),
              }, { root: true });
              this.$store.dispatch('aiFormFill/cancelFill');
            }
          });
        }, 500);
      }
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
      if (this.pendingFill) {
        this.$store.dispatch('aiFormFill/cancelFill');
      }
      this.$emit('close');
    },
  },
};
</script>
