<template>
  <div v-if="isOpen" class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
      <!-- Background overlay -->
      <div class="fixed inset-0 bg-savannah-1000 bg-opacity-75 transition-opacity"></div>

      <!-- Modal panel -->
      <div class="relative z-10 inline-block align-bottom bg-white rounded-lg text-left shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full max-h-[90vh] overflow-y-auto">
        <form @submit.prevent="handleSubmit">
          <div class="bg-white px-4 pt-5 pb-4 sm:p-6">
            <h3 class="text-lg font-semibold text-horizon-500 mb-4">
              {{ isEditing ? 'Edit Goal' : 'Create New Goal' }}
            </h3>

            <!-- Goal Name -->
            <div class="mb-4" :class="{ 'ai-fill-highlight rounded-lg': highlightedField === 'goal_name' }">
              <label class="block text-sm font-medium text-neutral-500 mb-1">Goal Name</label>
              <input
                v-model="form.goal_name"
                type="text"
                class="w-full px-3 py-2 border border-horizon-300 rounded-md shadow-sm focus:ring-violet-500 focus:border-raspberry-500"
                placeholder="e.g., Emergency Fund, House Deposit"
                required
              />
            </div>

            <!-- Goal Type -->
            <div class="mb-4" :class="{ 'ai-fill-highlight rounded-lg': highlightedField === 'goal_type' }">
              <label class="block text-sm font-medium text-neutral-500 mb-1">Goal Type</label>
              <select
                v-model="form.goal_type"
                class="w-full px-3 py-2 border border-horizon-300 rounded-md shadow-sm focus:ring-violet-500 focus:border-raspberry-500"
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
              <label class="block text-sm font-medium text-neutral-500 mb-1">Custom Goal Name</label>
              <input
                v-model="form.custom_goal_type_name"
                type="text"
                class="w-full px-3 py-2 border border-horizon-300 rounded-md shadow-sm focus:ring-violet-500 focus:border-raspberry-500"
                placeholder="Enter your custom goal type"
                required
              />
            </div>

            <!-- Description -->
            <div class="mb-4">
              <label class="block text-sm font-medium text-neutral-500 mb-1">Description (optional)</label>
              <textarea
                v-model="form.description"
                rows="2"
                class="w-full px-3 py-2 border border-horizon-300 rounded-md shadow-sm focus:ring-violet-500 focus:border-raspberry-500"
                placeholder="Brief description of your goal..."
              ></textarea>
            </div>

            <!-- Target Amount & Current Amount -->
            <div class="grid grid-cols-2 gap-4 mb-4">
              <div :class="{ 'ai-fill-highlight rounded-lg': highlightedField === 'target_amount' }">
                <label class="block text-sm font-medium text-neutral-500 mb-1">Target Amount</label>
                <div class="relative">
                  <span class="absolute left-3 top-2 text-neutral-500">£</span>
                  <input
                    v-model.number="form.target_amount"
                    type="number"
                    min="1"
                    step="1"
                    class="w-full pl-7 pr-3 py-2 border border-horizon-300 rounded-md shadow-sm focus:ring-violet-500 focus:border-raspberry-500"
                    placeholder="10000"
                    required
                  />
                </div>
              </div>
              <div>
                <label class="block text-sm font-medium text-neutral-500 mb-1">Current Amount</label>
                <div class="relative">
                  <span class="absolute left-3 top-2 text-neutral-500">£</span>
                  <input
                    v-model.number="form.current_amount"
                    type="number"
                    min="0"
                    step="1"
                    class="w-full pl-7 pr-3 py-2 border border-horizon-300 rounded-md shadow-sm focus:ring-violet-500 focus:border-raspberry-500"
                    placeholder="0"
                  />
                </div>
              </div>
            </div>

            <!-- Target Date & Monthly Contribution -->
            <div class="grid grid-cols-2 gap-4 mb-4">
              <div :class="{ 'ai-fill-highlight rounded-lg': highlightedField === 'target_date' }">
                <label class="block text-sm font-medium text-neutral-500 mb-1">Target Date</label>
                <input
                  v-model="form.target_date"
                  type="date"
                  :min="minDate"
                  class="w-full px-3 py-2 border border-horizon-300 rounded-md shadow-sm focus:ring-violet-500 focus:border-raspberry-500"
                  required
                />
              </div>
              <div :class="{ 'ai-fill-highlight rounded-lg': highlightedField === 'monthly_contribution' }">
                <label class="block text-sm font-medium text-neutral-500 mb-1">Monthly Contribution</label>
                <div class="relative">
                  <span class="absolute left-3 top-2 text-neutral-500">£</span>
                  <input
                    v-model.number="form.monthly_contribution"
                    type="number"
                    min="0"
                    step="1"
                    class="w-full pl-7 pr-3 py-2 border border-horizon-300 rounded-md shadow-sm focus:ring-violet-500 focus:border-raspberry-500"
                    placeholder="500"
                  />
                </div>
              </div>
            </div>

            <!-- Priority -->
            <div class="mb-4" :class="{ 'ai-fill-highlight rounded-lg': highlightedField === 'priority' }">
              <label class="block text-sm font-medium text-neutral-500 mb-1">Priority</label>
              <div class="grid grid-cols-4 gap-2">
                <button
                  v-for="priority in priorities"
                  :key="priority.value"
                  type="button"
                  @click="form.priority = priority.value"
                  class="px-3 py-2 text-sm rounded-md border transition-colors"
                  :class="form.priority === priority.value ? priority.activeClass : 'border-horizon-300 text-neutral-500 hover:bg-savannah-100'"
                >
                  {{ priority.label }}
                </button>
              </div>
            </div>

            <!-- Module Assignment Info -->
            <div v-if="assignedModule" class="mb-4 p-3 bg-savannah-100 rounded-lg">
              <p class="text-sm text-neutral-500">
                <span class="font-medium">Auto-assigned to:</span>
                <span class="ml-1 px-2 py-0.5 rounded text-xs" :class="moduleTagClass">
                  {{ moduleLabel }}
                </span>
              </p>
              <p class="text-xs text-neutral-500 mt-1">Based on goal type and timeline</p>
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
                <p class="text-xs text-neutral-500 ml-6">
                  Visible in combined household projection if spouse has granted permission.
                </p>
              </div>
            </div>

            <!-- Goal Dependencies (edit mode only) -->
            <div v-if="isEditing" class="border-t border-light-gray pt-4 mt-4">
              <h4 class="text-sm font-medium text-horizon-500 mb-3">Goal Dependencies</h4>
              <p class="text-xs text-neutral-500 mb-3">Link goals that must be completed before this one can start.</p>

              <!-- Current dependencies -->
              <div v-if="dependencies.length > 0" class="space-y-2 mb-3">
                <div
                  v-for="dep in dependencies"
                  :key="dep.id"
                  class="flex items-center justify-between p-2 bg-savannah-100 rounded-lg"
                >
                  <div class="flex items-center gap-2 flex-1 min-w-0">
                    <svg class="h-4 w-4 text-horizon-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                    </svg>
                    <span class="text-sm text-neutral-500 truncate">{{ dep.goal_name }}</span>
                    <span class="text-xs px-1.5 py-0.5 rounded-full flex-shrink-0" :class="dependencyTypeClass(dep.dependency_type)">
                      {{ dependencyTypeLabel(dep.dependency_type) }}
                    </span>
                  </div>
                  <button
                    type="button"
                    @click="removeDependency(dep.id)"
                    class="ml-2 p-1 text-horizon-400 hover:text-raspberry-500 flex-shrink-0"
                    title="Remove dependency"
                  >
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                  </button>
                </div>
              </div>

              <!-- Add dependency -->
              <div v-if="availableGoalsForDependency.length > 0" class="flex gap-2">
                <select
                  v-model="selectedDependencyGoalId"
                  class="flex-1 px-3 py-1.5 text-sm border border-horizon-300 rounded-md shadow-sm focus:ring-violet-500 focus:border-raspberry-500"
                >
                  <option value="">Select a goal...</option>
                  <option v-for="g in availableGoalsForDependency" :key="g.id" :value="g.id">
                    {{ g.goal_name }}
                  </option>
                </select>
                <select
                  v-model="selectedDependencyType"
                  class="w-32 px-2 py-1.5 text-sm border border-horizon-300 rounded-md shadow-sm focus:ring-violet-500 focus:border-raspberry-500"
                >
                  <option value="prerequisite">Prerequisite</option>
                  <option value="blocks">Blocks</option>
                  <option value="funds">Funds</option>
                </select>
                <button
                  type="button"
                  @click="addDependency"
                  :disabled="!selectedDependencyGoalId"
                  class="px-3 py-1.5 text-sm font-medium text-white bg-raspberry-600 rounded-md hover:bg-raspberry-700 disabled:opacity-50"
                >
                  Add
                </button>
              </div>
              <p v-else-if="!dependencies.length" class="text-xs text-horizon-400 italic">
                No other goals available to link as dependencies.
              </p>
            </div>

            <!-- Property-specific fields -->
            <div v-if="isPropertyGoal" class="border-t border-light-gray pt-4 mt-4">
              <h4 class="text-sm font-medium text-horizon-500 mb-3">Property Details</h4>

              <div class="grid grid-cols-2 gap-4 mb-4">
                <div>
                  <label class="block text-sm font-medium text-neutral-500 mb-1">Est. Property Price</label>
                  <div class="relative">
                    <span class="absolute left-3 top-2 text-neutral-500">£</span>
                    <input
                      v-model.number="form.estimated_property_price"
                      type="number"
                      min="0"
                      class="w-full pl-7 pr-3 py-2 border border-horizon-300 rounded-md shadow-sm focus:ring-violet-500 focus:border-raspberry-500"
                      placeholder="350000"
                    />
                  </div>
                </div>
                <div>
                  <label class="block text-sm font-medium text-neutral-500 mb-1">Deposit %</label>
                  <input
                    v-model.number="form.deposit_percentage"
                    type="number"
                    min="0"
                    max="100"
                    class="w-full px-3 py-2 border border-horizon-300 rounded-md shadow-sm focus:ring-violet-500 focus:border-raspberry-500"
                    placeholder="10"
                  />
                </div>
              </div>

              <div class="flex items-center mb-4">
                <input
                  v-model="form.is_first_time_buyer"
                  type="checkbox"
                  id="first-time-buyer"
                  class="h-4 w-4 text-raspberry-600 focus:ring-violet-500 border-horizon-300 rounded"
                />
                <label for="first-time-buyer" class="ml-2 text-sm text-neutral-500">First-time buyer (Stamp Duty relief)</label>
              </div>

              <!-- Property Cost Estimate -->
              <button
                v-if="form.estimated_property_price"
                type="button"
                @click="calculatePropertyCosts"
                class="text-sm text-raspberry-600 hover:text-raspberry-700 font-medium"
              >
                Calculate total costs →
              </button>

              <div v-if="propertyCosts" class="mt-3 p-3 bg-violet-50 rounded-lg text-sm">
                <div class="grid grid-cols-2 gap-2">
                  <div><span class="text-neutral-500">Deposit:</span> <span class="font-medium">{{ formatCurrency(propertyCosts.deposit) }}</span></div>
                  <div><span class="text-neutral-500">Stamp Duty:</span> <span class="font-medium">{{ formatCurrency(propertyCosts.stamp_duty) }}</span></div>
                  <div><span class="text-neutral-500">Legal Fees:</span> <span class="font-medium">{{ formatCurrency(propertyCosts.legal_fees) }}</span></div>
                  <div><span class="text-neutral-500">Survey:</span> <span class="font-medium">{{ formatCurrency(propertyCosts.survey_costs) }}</span></div>
                </div>
                <div class="border-t border-violet-200 mt-2 pt-2">
                  <span class="text-neutral-500 font-medium">Total Upfront: {{ formatCurrency(propertyCosts.total_upfront) }}</span>
                  <button
                    type="button"
                    @click="applyPropertyTotal"
                    class="ml-3 text-xs text-raspberry-600 hover:text-raspberry-700"
                  >
                    Use as target
                  </button>
                </div>
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
import { mapState, mapGetters, mapActions } from 'vuex';

import logger from '@/utils/logger';
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
      dependencies: [],
      selectedDependencyGoalId: '',
      selectedDependencyType: 'prerequisite',
      priorities: [
        { value: 'critical', label: 'Critical', activeClass: 'border-raspberry-500 bg-raspberry-50 text-raspberry-700' },
        { value: 'high', label: 'High', activeClass: 'border-violet-500 bg-violet-50 text-violet-700' },
        { value: 'medium', label: 'Medium', activeClass: 'border-violet-500 bg-violet-50 text-violet-700' },
        { value: 'low', label: 'Low', activeClass: 'border-neutral-500 bg-savannah-100 text-neutral-500' },
      ],
    };
  },

  computed: {
    ...mapState('goals', ['goalTypes', 'goals']),
    ...mapState('aiFormFill', ['pendingFill', 'highlightedField', 'filling']),

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
        investment: 'bg-violet-100 text-violet-700',
        property: 'bg-purple-100 text-purple-700',
        retirement: 'bg-violet-100 text-violet-700',
      };
      return classes[this.assignedModule] || 'bg-savannah-100 text-neutral-500';
    },

    availableGoalsForDependency() {
      if (!this.goal || !this.goals) return [];
      const depIds = this.dependencies.map(d => d.id);
      return this.goals.filter(g =>
        g.id !== this.goal.id && !depIds.includes(g.id)
      );
    },
  },

  watch: {
    goal: {
      handler() {
        this.initForm();
        this.loadDependencies();
      },
      immediate: true,
    },

    pendingFill(fill) {
      if (fill && fill.entityType === 'goal' && fill.fields) {
        // Pre-set key fields before field sequence so Vue select reactivity works
        if (fill.fields.goal_name) {
          this.form.goal_name = fill.fields.goal_name;
        }
        if (fill.fields.goal_type) {
          this.form.goal_type = fill.fields.goal_type;
        }
        if (fill.fields.target_amount) {
          this.form.target_amount = fill.fields.target_amount;
        }
        if (fill.fields.target_date) {
          this.form.target_date = fill.fields.target_date;
        }
        if (fill.fields.custom_goal_type_name) {
          this.form.custom_goal_type_name = fill.fields.custom_goal_type_name;
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
      if (isFilling === false && this.pendingFill?.entityType === 'goal') {
        setTimeout(() => {
          this.$nextTick(() => {
            this.handleSubmit();
            if (this.validationErrors.length > 0) {
              const errorList = this.validationErrors.join(', ');
              this.$store.commit('aiChat/ADD_MESSAGE', {
                id: 'fill_error_' + Date.now(),
                role: 'assistant',
                content: `I wasn't able to save the goal — ${errorList}. Please check the form and try again.`,
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
    this.fetchGoalTypes();
  },

  methods: {
    ...mapActions('goals', ['fetchGoalTypes', 'calculatePropertyCosts', 'fetchDependencies']),

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
        logger.error('Failed to calculate property costs:', error);
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

    async loadDependencies() {
      if (!this.goal) {
        this.dependencies = [];
        return;
      }
      try {
        const response = await this.fetchDependencies(this.goal.id);
        if (response.success) {
          this.dependencies = response.data.depends_on || [];
        }
      } catch {
        this.dependencies = [];
      }
    },

    async addDependency() {
      if (!this.selectedDependencyGoalId || !this.goal) return;
      try {
        await this.$store.dispatch('goals/addDependency', {
          goalId: this.goal.id,
          dependsOnGoalId: this.selectedDependencyGoalId,
          dependencyType: this.selectedDependencyType,
        });
        this.selectedDependencyGoalId = '';
        this.selectedDependencyType = 'prerequisite';
        await this.loadDependencies();
      } catch (error) {
        const message = error.response?.data?.message || 'Failed to add dependency';
        this.validationErrors = [message];
      }
    },

    async removeDependency(dependsOnGoalId) {
      if (!this.goal) return;
      try {
        await this.$store.dispatch('goals/removeDependency', {
          goalId: this.goal.id,
          dependsOnGoalId,
        });
        await this.loadDependencies();
      } catch (error) {
        logger.error('Failed to remove dependency:', error);
      }
    },

    dependencyTypeLabel(type) {
      const labels = { blocks: 'Blocks', funds: 'Funds', prerequisite: 'Prerequisite' };
      return labels[type] || 'Prerequisite';
    },

    dependencyTypeClass(type) {
      const classes = {
        blocks: 'bg-raspberry-100 text-raspberry-700',
        funds: 'bg-emerald-100 text-emerald-700',
        prerequisite: 'bg-violet-100 text-violet-700',
      };
      return classes[type] || 'bg-savannah-100 text-neutral-500';
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
