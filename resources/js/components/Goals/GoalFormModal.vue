<template>
  <div v-if="isOpen" class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
      <!-- Background overlay -->
      <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"></div>

      <!-- Modal panel -->
      <div class="relative z-10 inline-block align-bottom bg-white rounded-lg text-left shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full max-h-[90vh] overflow-y-auto">
        <form @submit.prevent="handleSubmit">
          <div class="bg-white px-4 pt-5 pb-4 sm:p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">
              {{ isEditing ? 'Edit Goal' : 'Create New Goal' }}
            </h3>

            <!-- Goal Name -->
            <div class="mb-4">
              <label class="block text-sm font-medium text-gray-700 mb-1">Goal Name</label>
              <input
                v-model="form.goal_name"
                type="text"
                class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-primary-500 focus:border-primary-500"
                placeholder="e.g., Emergency Fund, House Deposit"
                required
              />
            </div>

            <!-- Goal Type -->
            <div class="mb-4">
              <label class="block text-sm font-medium text-gray-700 mb-1">Goal Type</label>
              <select
                v-model="form.goal_type"
                class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-primary-500 focus:border-primary-500"
                required
              >
                <option value="">Select a type...</option>
                <option v-for="type in goalTypes" :key="type.type" :value="type.type">
                  {{ type.label }}
                </option>
              </select>
            </div>

            <!-- Custom Goal Type Name (if custom selected) -->
            <div v-if="form.goal_type === 'custom'" class="mb-4">
              <label class="block text-sm font-medium text-gray-700 mb-1">Custom Goal Name</label>
              <input
                v-model="form.custom_goal_type_name"
                type="text"
                class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-primary-500 focus:border-primary-500"
                placeholder="Enter your custom goal type"
                required
              />
            </div>

            <!-- Description -->
            <div class="mb-4">
              <label class="block text-sm font-medium text-gray-700 mb-1">Description (optional)</label>
              <textarea
                v-model="form.description"
                rows="2"
                class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-primary-500 focus:border-primary-500"
                placeholder="Brief description of your goal..."
              ></textarea>
            </div>

            <!-- Target Amount & Current Amount -->
            <div class="grid grid-cols-2 gap-4 mb-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Target Amount</label>
                <div class="relative">
                  <span class="absolute left-3 top-2 text-gray-500">£</span>
                  <input
                    v-model.number="form.target_amount"
                    type="number"
                    min="1"
                    step="1"
                    class="w-full pl-7 pr-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-primary-500 focus:border-primary-500"
                    placeholder="10000"
                    required
                  />
                </div>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Current Amount</label>
                <div class="relative">
                  <span class="absolute left-3 top-2 text-gray-500">£</span>
                  <input
                    v-model.number="form.current_amount"
                    type="number"
                    min="0"
                    step="1"
                    class="w-full pl-7 pr-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-primary-500 focus:border-primary-500"
                    placeholder="0"
                  />
                </div>
              </div>
            </div>

            <!-- Target Date & Monthly Contribution -->
            <div class="grid grid-cols-2 gap-4 mb-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Target Date</label>
                <input
                  v-model="form.target_date"
                  type="date"
                  :min="minDate"
                  class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-primary-500 focus:border-primary-500"
                  required
                />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Monthly Contribution</label>
                <div class="relative">
                  <span class="absolute left-3 top-2 text-gray-500">£</span>
                  <input
                    v-model.number="form.monthly_contribution"
                    type="number"
                    min="0"
                    step="1"
                    class="w-full pl-7 pr-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-primary-500 focus:border-primary-500"
                    placeholder="500"
                  />
                </div>
              </div>
            </div>

            <!-- Priority -->
            <div class="mb-4">
              <label class="block text-sm font-medium text-gray-700 mb-1">Priority</label>
              <div class="grid grid-cols-4 gap-2">
                <button
                  v-for="priority in priorities"
                  :key="priority.value"
                  type="button"
                  @click="form.priority = priority.value"
                  class="px-3 py-2 text-sm rounded-md border transition-colors"
                  :class="form.priority === priority.value ? priority.activeClass : 'border-gray-300 text-gray-700 hover:bg-gray-50'"
                >
                  {{ priority.label }}
                </button>
              </div>
            </div>

            <!-- Module Assignment Info -->
            <div v-if="assignedModule" class="mb-4 p-3 bg-gray-50 rounded-lg">
              <p class="text-sm text-gray-600">
                <span class="font-medium">Auto-assigned to:</span>
                <span class="ml-1 px-2 py-0.5 rounded text-xs" :class="moduleTagClass">
                  {{ moduleLabel }}
                </span>
              </p>
              <p class="text-xs text-gray-500 mt-1">Based on goal type and timeline</p>
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
                <p class="text-xs text-gray-500 ml-6">
                  Visible in combined household projection if spouse has granted permission.
                </p>
              </div>
            </div>

            <!-- Property-specific fields -->
            <div v-if="isPropertyGoal" class="border-t border-gray-200 pt-4 mt-4">
              <h4 class="text-sm font-medium text-gray-900 mb-3">Property Details</h4>

              <div class="grid grid-cols-2 gap-4 mb-4">
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">Est. Property Price</label>
                  <div class="relative">
                    <span class="absolute left-3 top-2 text-gray-500">£</span>
                    <input
                      v-model.number="form.estimated_property_price"
                      type="number"
                      min="0"
                      class="w-full pl-7 pr-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-primary-500 focus:border-primary-500"
                      placeholder="350000"
                    />
                  </div>
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">Deposit %</label>
                  <input
                    v-model.number="form.deposit_percentage"
                    type="number"
                    min="0"
                    max="100"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-primary-500 focus:border-primary-500"
                    placeholder="10"
                  />
                </div>
              </div>

              <div class="flex items-center mb-4">
                <input
                  v-model="form.is_first_time_buyer"
                  type="checkbox"
                  id="first-time-buyer"
                  class="h-4 w-4 text-primary-600 focus:ring-primary-500 border-gray-300 rounded"
                />
                <label for="first-time-buyer" class="ml-2 text-sm text-gray-700">First-time buyer (SDLT relief)</label>
              </div>

              <!-- Property Cost Estimate -->
              <button
                v-if="form.estimated_property_price"
                type="button"
                @click="calculatePropertyCosts"
                class="text-sm text-primary-600 hover:text-primary-700 font-medium"
              >
                Calculate total costs →
              </button>

              <div v-if="propertyCosts" class="mt-3 p-3 bg-blue-50 rounded-lg text-sm">
                <div class="grid grid-cols-2 gap-2">
                  <div><span class="text-gray-600">Deposit:</span> <span class="font-medium">{{ formatCurrency(propertyCosts.deposit) }}</span></div>
                  <div><span class="text-gray-600">Stamp Duty:</span> <span class="font-medium">{{ formatCurrency(propertyCosts.stamp_duty) }}</span></div>
                  <div><span class="text-gray-600">Legal Fees:</span> <span class="font-medium">{{ formatCurrency(propertyCosts.legal_fees) }}</span></div>
                  <div><span class="text-gray-600">Survey:</span> <span class="font-medium">{{ formatCurrency(propertyCosts.survey_costs) }}</span></div>
                </div>
                <div class="border-t border-blue-200 mt-2 pt-2">
                  <span class="text-gray-700 font-medium">Total Upfront: {{ formatCurrency(propertyCosts.total_upfront) }}</span>
                  <button
                    type="button"
                    @click="applyPropertyTotal"
                    class="ml-3 text-xs text-primary-600 hover:text-primary-700"
                  >
                    Use as target
                  </button>
                </div>
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
              {{ loading ? 'Saving...' : (isEditing ? 'Update Goal' : 'Create Goal') }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script>
import { currencyMixin } from '@/mixins/currencyMixin';
import { mapState, mapActions } from 'vuex';

export default {
  name: 'GoalFormModal',
  mixins: [currencyMixin],

  props: {
    isOpen: {
      type: Boolean,
      default: false,
    },
    goal: {
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
      propertyCosts: null,
      priorities: [
        { value: 'critical', label: 'Critical', activeClass: 'border-red-500 bg-red-50 text-red-700' },
        { value: 'high', label: 'High', activeClass: 'border-blue-500 bg-blue-50 text-blue-700' },
        { value: 'medium', label: 'Medium', activeClass: 'border-blue-500 bg-blue-50 text-blue-700' },
        { value: 'low', label: 'Low', activeClass: 'border-gray-500 bg-gray-50 text-gray-700' },
      ],
    };
  },

  computed: {
    ...mapState('goals', ['goalTypes']),

    isEditing() {
      return !!this.goal;
    },

    minDate() {
      const tomorrow = new Date();
      tomorrow.setDate(tomorrow.getDate() + 1);
      return tomorrow.toISOString().split('T')[0];
    },

    isPropertyGoal() {
      return ['property_purchase', 'home_deposit'].includes(this.form.goal_type);
    },

    assignedModule() {
      const typeModules = {
        emergency_fund: 'savings',
        property_purchase: 'property',
        home_deposit: 'property',
        retirement: 'retirement',
        wealth_accumulation: 'investment',
        debt_repayment: 'savings',
      };
      return typeModules[this.form.goal_type] || null;
    },

    moduleLabel() {
      const labels = {
        savings: 'Savings',
        investment: 'Investment',
        property: 'Property',
        retirement: 'Retirement',
      };
      return labels[this.assignedModule] || '';
    },

    moduleTagClass() {
      const classes = {
        savings: 'bg-emerald-100 text-emerald-700',
        investment: 'bg-blue-100 text-blue-700',
        property: 'bg-purple-100 text-purple-700',
        retirement: 'bg-blue-100 text-blue-700',
      };
      return classes[this.assignedModule] || 'bg-gray-100 text-gray-700';
    },
  },

  watch: {
    isOpen(newVal) {
      if (newVal) {
        this.initForm();
      }
    },
    goal: {
      handler() {
        this.initForm();
      },
      immediate: true,
    },
  },

  mounted() {
    this.fetchGoalTypes();
  },

  methods: {
    ...mapActions('goals', ['fetchGoalTypes', 'calculatePropertyCosts']),

    getDefaultForm() {
      return {
        goal_name: '',
        goal_type: '',
        custom_goal_type_name: '',
        description: '',
        target_amount: null,
        current_amount: 0,
        target_date: '',
        monthly_contribution: null,
        priority: 'medium',
        estimated_property_price: null,
        deposit_percentage: 10,
        is_first_time_buyer: false,
        show_in_projection: true,
        show_in_household_view: true,
      };
    },

    initForm() {
      if (this.goal) {
        this.form = {
          goal_name: this.goal.goal_name || '',
          goal_type: this.goal.goal_type || '',
          custom_goal_type_name: this.goal.custom_goal_type_name || '',
          description: this.goal.description || '',
          target_amount: parseFloat(this.goal.target_amount) || null,
          current_amount: parseFloat(this.goal.current_amount) || 0,
          target_date: this.goal.target_date ? this.goal.target_date.split('T')[0] : '',
          monthly_contribution: this.goal.monthly_contribution ? parseFloat(this.goal.monthly_contribution) : null,
          priority: this.goal.priority || 'medium',
          estimated_property_price: this.goal.estimated_property_price ? parseFloat(this.goal.estimated_property_price) : null,
          deposit_percentage: this.goal.deposit_percentage ? parseFloat(this.goal.deposit_percentage) : 10,
          is_first_time_buyer: this.goal.is_first_time_buyer || false,
          show_in_projection: this.goal.show_in_projection ?? true,
          show_in_household_view: this.goal.show_in_household_view ?? true,
        };
      } else {
        this.form = this.getDefaultForm();
      }
      this.propertyCosts = null;
      this.validationErrors = [];
    },

    async calculatePropertyCosts() {
      if (!this.form.estimated_property_price) return;

      try {
        const response = await this.$store.dispatch('goals/calculatePropertyCosts', {
          estimated_property_price: this.form.estimated_property_price,
          deposit_percentage: this.form.deposit_percentage || 10,
          is_first_time_buyer: this.form.is_first_time_buyer,
        });

        if (response.success) {
          this.propertyCosts = response.data;
        }
      } catch (error) {
        console.error('Failed to calculate property costs:', error);
      }
    },

    applyPropertyTotal() {
      if (this.propertyCosts) {
        this.form.target_amount = this.propertyCosts.total_upfront;
      }
    },

    async handleSubmit() {
      this.validationErrors = [];

      if (!this.form.goal_name) this.validationErrors.push('Goal name is required');
      if (!this.form.goal_type) this.validationErrors.push('Goal type is required');
      if (!this.form.target_amount) this.validationErrors.push('Target amount is required');
      if (!this.form.target_date) this.validationErrors.push('Target date is required');

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
