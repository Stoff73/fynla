<template>
  <AppLayout>
    <div class="assumptions-settings module-gradient py-8">
      <div class="mb-8">
        <h1 class="text-h2 font-display text-horizon-500">Settings</h1>
        <p class="mt-2 text-body-base text-neutral-500">
          Configure the assumptions used in your pension and investment projections.
          These values help calculate future growth estimates.
        </p>
      </div>

      <SettingsTabBar />

      <div v-if="loading" class="loading-state">
        <div class="w-10 h-10 border-[3px] border-light-gray border-t-blue-500 rounded-full animate-spin mx-auto mb-4"></div>
        <p>Loading assumptions...</p>
      </div>

      <div v-else-if="error" class="error-state">
        <p class="error-message">{{ error }}</p>
        <button class="btn btn-primary" @click="loadAssumptions">
          Try Again
        </button>
      </div>

      <template v-else>
        <!-- Pensions Section -->
        <div class="settings-section">
          <div class="section-header">
            <h2 class="section-title">Pension Projections</h2>
            <span
              v-if="assumptions.pensions?.has_overrides"
              class="status-badge custom"
            >Custom</span>
            <span v-else class="status-badge default">Defaults</span>
          </div>
          <p class="section-description">
            These assumptions are used to project the future value of your Defined Contribution pensions.
          </p>

          <div class="assumptions-grid">
            <div class="assumption-field">
              <label for="pension-inflation">Inflation Rate</label>
              <div class="input-group">
                <input
                  id="pension-inflation"
                  v-model.number="form.pensions.inflation_rate"
                  type="number"
                  step="0.1"
                  min="0"
                  max="20"
                  class="form-input"
                  :placeholder="assumptions.pensions?.inflation_rate_default"
                >
                <span class="input-suffix">%</span>
              </div>
              <p class="field-hint">
                Default: {{ assumptions.pensions?.inflation_rate_default }}%
              </p>
            </div>

            <div class="assumption-field">
              <label for="pension-return">Expected Return</label>
              <div class="input-group">
                <input
                  id="pension-return"
                  v-model.number="form.pensions.return_rate"
                  type="number"
                  step="0.1"
                  min="-10"
                  max="30"
                  class="form-input"
                  :placeholder="assumptions.pensions?.return_rate_default"
                >
                <span class="input-suffix">%</span>
              </div>
              <p class="field-hint">
                Based on {{ formatRiskLevel(assumptions.pensions?.risk_level) }} risk profile
                (default: {{ assumptions.pensions?.return_rate_default }}%)
              </p>
            </div>

            <div class="assumption-field">
              <label for="pension-compound">Compound Periods</label>
              <div class="input-group">
                <input
                  id="pension-compound"
                  v-model.number="form.pensions.compound_periods"
                  type="number"
                  step="1"
                  min="1"
                  max="365"
                  class="form-input"
                  :placeholder="assumptions.pensions?.compound_periods_default"
                >
                <span class="input-suffix">per year</span>
              </div>
              <p class="field-hint">
                Default: {{ assumptions.pensions?.compound_periods_default }} (monthly)
              </p>
            </div>

            <div class="assumption-field readonly">
              <label>Weighted Average Fees</label>
              <div class="readonly-value">
                <span class="value">{{ formatFees(assumptions.pensions?.fees) }}</span>
              </div>
              <p class="field-hint">
                Calculated from your pension accounts
                <span v-if="assumptions.pensions?.fees?.platform > 0">
                  (Platform: {{ assumptions.pensions?.fees?.platform }}%, OCF: {{ assumptions.pensions?.fees?.ocf }}%)
                </span>
              </p>
            </div>
          </div>

          <div class="summary-row">
            <div class="summary-item">
              <span class="label">Years to Retirement:</span>
              <span class="value">{{ assumptions.pensions?.years_to_retirement ?? '-' }}</span>
            </div>
            <div class="summary-item">
              <span class="label">Total Pension Value:</span>
              <span class="value">{{ formatCurrency(assumptions.pensions?.total_value) }}</span>
            </div>
          </div>

          <div class="section-actions">
            <button
              class="btn btn-outline"
              :disabled="saving.pensions || !pensionsHasChanges"
              @click="resetType('pensions')"
            >
              Reset to Defaults
            </button>
            <button
              class="btn btn-primary"
              :disabled="saving.pensions || !pensionsHasChanges"
              @click="saveType('pensions')"
            >
              <span v-if="saving.pensions">Saving...</span>
              <span v-else>Save Changes</span>
            </button>
          </div>
        </div>

        <!-- Investments Section -->
        <div class="settings-section">
          <div class="section-header">
            <h2 class="section-title">Investment Projections</h2>
            <span
              v-if="assumptions.investments?.has_overrides"
              class="status-badge custom"
            >Custom</span>
            <span v-else class="status-badge default">Defaults</span>
          </div>
          <p class="section-description">
            These assumptions are used to project the future value of your investment accounts (ISAs, General Investment Accounts, etc.).
          </p>

          <div class="assumptions-grid">
            <div class="assumption-field">
              <label for="investment-inflation">Inflation Rate</label>
              <div class="input-group">
                <input
                  id="investment-inflation"
                  v-model.number="form.investments.inflation_rate"
                  type="number"
                  step="0.1"
                  min="0"
                  max="20"
                  class="form-input"
                  :placeholder="assumptions.investments?.inflation_rate_default"
                >
                <span class="input-suffix">%</span>
              </div>
              <p class="field-hint">
                Default: {{ assumptions.investments?.inflation_rate_default }}%
              </p>
            </div>

            <div class="assumption-field">
              <label for="investment-return">Expected Return</label>
              <div class="input-group">
                <input
                  id="investment-return"
                  v-model.number="form.investments.return_rate"
                  type="number"
                  step="0.1"
                  min="-10"
                  max="30"
                  class="form-input"
                  :placeholder="assumptions.investments?.return_rate_default"
                >
                <span class="input-suffix">%</span>
              </div>
              <p class="field-hint">
                Based on {{ formatRiskLevel(assumptions.investments?.risk_level) }} risk profile
                (default: {{ assumptions.investments?.return_rate_default }}%)
              </p>
            </div>

            <div class="assumption-field">
              <label for="investment-compound">Compound Periods</label>
              <div class="input-group">
                <input
                  id="investment-compound"
                  v-model.number="form.investments.compound_periods"
                  type="number"
                  step="1"
                  min="1"
                  max="365"
                  class="form-input"
                  :placeholder="assumptions.investments?.compound_periods_default"
                >
                <span class="input-suffix">per year</span>
              </div>
              <p class="field-hint">
                Default: {{ assumptions.investments?.compound_periods_default }} (monthly)
              </p>
            </div>

            <div class="assumption-field readonly">
              <label>Weighted Average Fees</label>
              <div class="readonly-value">
                <span class="value">{{ formatFees(assumptions.investments?.fees) }}</span>
              </div>
              <p class="field-hint">
                Calculated from your investment accounts
                <span v-if="assumptions.investments?.fees?.platform > 0">
                  (Platform: {{ assumptions.investments?.fees?.platform }}%,
                  OCF: {{ assumptions.investments?.fees?.ocf }}%<template v-if="assumptions.investments?.fees?.advisory > 0">,
                  Advisory: {{ assumptions.investments?.fees?.advisory }}%</template>)
                </span>
              </p>
            </div>
          </div>

          <div class="summary-row">
            <div class="summary-item">
              <span class="label">Years to Retirement:</span>
              <span class="value">{{ assumptions.investments?.years_to_retirement ?? '-' }}</span>
            </div>
            <div class="summary-item">
              <span class="label">Total Investment Value:</span>
              <span class="value">{{ formatCurrency(assumptions.investments?.total_value) }}</span>
            </div>
          </div>

          <div class="section-actions">
            <button
              class="btn btn-outline"
              :disabled="saving.investments || !investmentsHasChanges"
              @click="resetType('investments')"
            >
              Reset to Defaults
            </button>
            <button
              class="btn btn-primary"
              :disabled="saving.investments || !investmentsHasChanges"
              @click="saveType('investments')"
            >
              <span v-if="saving.investments">Saving...</span>
              <span v-else>Save Changes</span>
            </button>
          </div>
        </div>

        <!-- Estate Planning Section -->
        <div class="settings-section">
          <div class="section-header">
            <h2 class="section-title">Estate Planning Projections</h2>
            <span
              v-if="assumptions.estate_planning?.has_overrides"
              class="status-badge custom"
            >Custom</span>
            <span v-else class="status-badge default">Defaults</span>
          </div>
          <p class="section-description">
            These assumptions are used to project future estate values for Inheritance Tax planning.
          </p>

          <div class="assumptions-grid">
            <div class="assumption-field">
              <label for="estate-inflation">Inflation Rate</label>
              <div class="input-group">
                <input
                  id="estate-inflation"
                  v-model.number="form.estate_planning.inflation_rate"
                  type="number"
                  step="0.1"
                  min="0"
                  max="20"
                  class="form-input"
                  :placeholder="assumptions.estate_planning?.inflation_rate_default"
                >
                <span class="input-suffix">%</span>
              </div>
              <p class="field-hint">
                Default: {{ assumptions.estate_planning?.inflation_rate_default }}%
              </p>
            </div>

            <div class="assumption-field">
              <label for="estate-property-growth">Property Growth Rate</label>
              <div class="input-group">
                <input
                  id="estate-property-growth"
                  v-model.number="form.estate_planning.property_growth_rate"
                  type="number"
                  step="0.1"
                  min="-10"
                  max="20"
                  class="form-input"
                  :placeholder="assumptions.estate_planning?.property_growth_rate_default"
                >
                <span class="input-suffix">%</span>
              </div>
              <p class="field-hint">
                Default: {{ assumptions.estate_planning?.property_growth_rate_default }}% annual property value growth
              </p>
            </div>

            <div class="assumption-field">
              <label for="estate-growth-method">Investment Growth Method</label>
              <div class="input-group">
                <select
                  id="estate-growth-method"
                  v-model="form.estate_planning.investment_growth_method"
                  class="form-input"
                >
                  <option value="monte_carlo">Monte Carlo (80% confidence)</option>
                  <option value="custom">Custom Rate</option>
                </select>
              </div>
              <p class="field-hint">
                Monte Carlo uses probabilistic modelling for more realistic projections
              </p>
            </div>

            <div
              v-if="form.estate_planning.investment_growth_method === 'custom'"
              class="assumption-field"
            >
              <label for="estate-custom-rate">Custom Investment Rate</label>
              <div class="input-group">
                <input
                  id="estate-custom-rate"
                  v-model.number="form.estate_planning.custom_investment_rate"
                  type="number"
                  step="0.1"
                  min="-10"
                  max="30"
                  class="form-input"
                  placeholder="Enter custom rate"
                >
                <span class="input-suffix">%</span>
              </div>
              <p class="field-hint">
                Your custom annual growth rate for investment projections
              </p>
            </div>
          </div>

          <div class="section-actions">
            <button
              class="btn btn-outline"
              :disabled="saving.estate_planning || !estatePlanningHasChanges"
              @click="resetType('estate_planning')"
            >
              Reset to Defaults
            </button>
            <button
              class="btn btn-primary"
              :disabled="saving.estate_planning || !estatePlanningHasChanges"
              @click="saveType('estate_planning')"
            >
              <span v-if="saving.estate_planning">Saving...</span>
              <span v-else>Save Changes</span>
            </button>
          </div>
        </div>

        <!-- Life Expectancy Section -->
        <div class="settings-section">
          <div class="section-header">
            <h2 class="section-title">Life Expectancy</h2>
          </div>
          <p class="section-description">
            Override the actuarial default used in retirement drawdown, estate planning, and gifting strategy calculations.
          </p>

          <div class="assumptions-grid">
            <div class="assumption-field">
              <label for="life-expectancy">Life Expectancy Assumption</label>
              <div class="input-group">
                <input
                  id="life-expectancy"
                  v-model.number="lifeExpectancyForm.life_expectancy_override"
                  type="number"
                  step="1"
                  min="60"
                  max="110"
                  class="form-input"
                  placeholder="Use actuarial default"
                >
                <span class="input-suffix">years</span>
              </div>
              <p class="field-hint">
                Leave blank to use statistical life expectancy based on your age and gender.
              </p>
            </div>
          </div>

          <div class="section-actions">
            <button
              class="btn btn-outline"
              :disabled="savingLifeExpectancy || !lifeExpectancyHasChanges"
              @click="clearLifeExpectancy"
            >
              Reset to Default
            </button>
            <button
              class="btn btn-primary"
              :disabled="savingLifeExpectancy || !lifeExpectancyHasChanges"
              @click="saveLifeExpectancy"
            >
              <span v-if="savingLifeExpectancy">Saving...</span>
              <span v-else>Save Changes</span>
            </button>
          </div>
        </div>

        <!-- Info Section -->
        <div class="settings-section info-section">
          <div class="section-header">
            <h2 class="section-title">About These Assumptions</h2>
          </div>
          <ul class="info-list">
            <li>
              <strong>Inflation Rate:</strong> The expected annual increase in the cost of living.
              Used to calculate real (inflation-adjusted) returns.
            </li>
            <li>
              <strong>Expected Return:</strong> The anticipated annual growth rate before fees.
              Default values are based on your risk profile.
            </li>
            <li>
              <strong>Compound Periods:</strong> How often returns are compounded.
              Monthly (12) is typical for most calculations.
            </li>
            <li>
              <strong>Fees:</strong> Automatically calculated from your actual accounts.
              Platform fees, fund fees (OCF), and advisory fees are included.
            </li>
            <li>
              <strong>Property Growth Rate:</strong> The expected annual increase in property values.
              Used for estate planning projections.
            </li>
            <li>
              <strong>Investment Growth Method:</strong> Monte Carlo uses probabilistic modelling
              at 80% confidence. Custom allows you to set a specific growth rate.
            </li>
          </ul>
        </div>
      </template>
    </div>
  </AppLayout>
</template>

<script>
import AppLayout from '@/layouts/AppLayout.vue';
import assumptionsService from '@/services/assumptionsService';
import api from '@/services/api';
import { currencyMixin } from '@/mixins/currencyMixin';
import SettingsTabBar from '@/components/Settings/SettingsTabBar.vue';

import logger from '@/utils/logger';
export default {
  name: 'AssumptionsSettings',

  components: {
    AppLayout,
    SettingsTabBar,
  },

  mixins: [currencyMixin],

  data() {
    return {
      loading: true,
      error: null,
      assumptions: {
        pensions: null,
        investments: null,
        estate_planning: null,
      },
      form: {
        pensions: {
          inflation_rate: null,
          return_rate: null,
          compound_periods: null,
        },
        investments: {
          inflation_rate: null,
          return_rate: null,
          compound_periods: null,
        },
        estate_planning: {
          inflation_rate: null,
          property_growth_rate: null,
          investment_growth_method: 'monte_carlo',
          custom_investment_rate: null,
        },
      },
      originalForm: {
        pensions: {},
        investments: {},
        estate_planning: {},
      },
      saving: {
        pensions: false,
        investments: false,
        estate_planning: false,
      },
      lifeExpectancyForm: {
        life_expectancy_override: null,
      },
      originalLifeExpectancy: null,
      savingLifeExpectancy: false,
    };
  },

  computed: {
    pensionsHasChanges() {
      return this.hasChanges('pensions');
    },

    investmentsHasChanges() {
      return this.hasChanges('investments');
    },

    estatePlanningHasChanges() {
      return this.hasChanges('estate_planning');
    },

    lifeExpectancyHasChanges() {
      return this.lifeExpectancyForm.life_expectancy_override !== this.originalLifeExpectancy;
    },
  },

  mounted() {
    this.loadAssumptions();
    this.loadLifeExpectancy();
  },

  methods: {
    async loadAssumptions() {
      this.loading = true;
      this.error = null;

      try {
        const response = await assumptionsService.getAssumptions();
        this.assumptions = response.data.data;
        this.initializeForm();
      } catch (err) {
        logger.error('Failed to load assumptions:', err);
        this.error = err.message || 'Failed to load assumptions. Please try again.';
      } finally {
        this.loading = false;
      }
    },

    initializeForm() {
      // Initialize form with current values for pensions and investments
      ['pensions', 'investments'].forEach((type) => {
        const data = this.assumptions[type];
        if (data) {
          this.form[type] = {
            inflation_rate: data.inflation_rate,
            return_rate: data.return_rate,
            compound_periods: data.compound_periods,
          };
          // Store original values for change detection
          this.originalForm[type] = { ...this.form[type] };
        }
      });

      // Initialize estate planning separately (different fields)
      const estateData = this.assumptions.estate_planning;
      if (estateData) {
        this.form.estate_planning = {
          inflation_rate: estateData.inflation_rate,
          property_growth_rate: estateData.property_growth_rate,
          investment_growth_method: estateData.investment_growth_method || 'monte_carlo',
          custom_investment_rate: estateData.custom_investment_rate,
        };
        this.originalForm.estate_planning = { ...this.form.estate_planning };
      }
    },

    hasChanges(type) {
      const current = this.form[type];
      const original = this.originalForm[type];

      if (type === 'estate_planning') {
        return (
          current.inflation_rate !== original.inflation_rate ||
          current.property_growth_rate !== original.property_growth_rate ||
          current.investment_growth_method !== original.investment_growth_method ||
          current.custom_investment_rate !== original.custom_investment_rate
        );
      }

      return (
        current.inflation_rate !== original.inflation_rate ||
        current.return_rate !== original.return_rate ||
        current.compound_periods !== original.compound_periods
      );
    },

    async saveType(type) {
      this.saving[type] = true;

      try {
        let data;

        if (type === 'estate_planning') {
          data = {
            inflation_rate: this.form[type].inflation_rate,
            property_growth_rate: this.form[type].property_growth_rate,
            investment_growth_method: this.form[type].investment_growth_method,
            custom_investment_rate: this.form[type].custom_investment_rate,
          };
        } else {
          data = {
            inflation_rate: this.form[type].inflation_rate,
            return_rate: this.form[type].return_rate,
            compound_periods: this.form[type].compound_periods,
          };
        }

        const response = await assumptionsService.updateAssumptions(type, data);
        this.assumptions[type] = response.data.data;

        // Update original form values
        this.originalForm[type] = { ...this.form[type] };

        this.$toast?.success?.(`${this.formatTypeName(type)} assumptions saved successfully.`);
      } catch (err) {
        logger.error(`Failed to save ${type} assumptions:`, err);
        this.$toast?.error?.(err.message || `Failed to save ${type} assumptions.`);
      } finally {
        this.saving[type] = false;
      }
    },

    async resetType(type) {
      this.saving[type] = true;

      try {
        const response = await assumptionsService.resetAssumptions(type);
        this.assumptions[type] = response.data.data;

        // Reset form to defaults based on type
        if (type === 'estate_planning') {
          this.form[type] = {
            inflation_rate: response.data.data.inflation_rate,
            property_growth_rate: response.data.data.property_growth_rate,
            investment_growth_method: response.data.data.investment_growth_method || 'monte_carlo',
            custom_investment_rate: response.data.data.custom_investment_rate,
          };
        } else {
          this.form[type] = {
            inflation_rate: response.data.data.inflation_rate,
            return_rate: response.data.data.return_rate,
            compound_periods: response.data.data.compound_periods,
          };
        }
        this.originalForm[type] = { ...this.form[type] };

        this.$toast?.success?.(`${this.formatTypeName(type)} assumptions reset to defaults.`);
      } catch (err) {
        logger.error(`Failed to reset ${type} assumptions:`, err);
        this.$toast?.error?.(err.message || `Failed to reset ${type} assumptions.`);
      } finally {
        this.saving[type] = false;
      }
    },

    async loadLifeExpectancy() {
      try {
        const response = await api.get('/user/profile');
        const profile = response.data?.data;
        this.lifeExpectancyForm.life_expectancy_override = profile?.personal_info?.life_expectancy_override || null;
        this.originalLifeExpectancy = this.lifeExpectancyForm.life_expectancy_override;
      } catch (err) {
        logger.error('Failed to load life expectancy:', err);
      }
    },

    async saveLifeExpectancy() {
      this.savingLifeExpectancy = true;
      try {
        await api.put('/user/profile/personal', {
          life_expectancy_override: this.lifeExpectancyForm.life_expectancy_override || null,
        });
        this.originalLifeExpectancy = this.lifeExpectancyForm.life_expectancy_override;
        this.$toast?.success?.('Life expectancy assumption saved successfully.');
      } catch (err) {
        logger.error('Failed to save life expectancy:', err);
        this.$toast?.error?.(err.message || 'Failed to save life expectancy.');
      } finally {
        this.savingLifeExpectancy = false;
      }
    },

    clearLifeExpectancy() {
      this.lifeExpectancyForm.life_expectancy_override = null;
      this.saveLifeExpectancy();
    },

    formatFees(fees) {
      if (!fees || fees.total === 0) {
        return 'No fees recorded';
      }
      return `${fees.total}%`;
    },

    formatRiskLevel(level) {
      if (!level) return 'medium';

      const labels = {
        low: 'Low',
        lower_medium: 'Lower-Medium',
        medium: 'Medium',
        upper_medium: 'Upper-Medium',
        high: 'High',
      };

      return labels[level] || level;
    },

    capitalise(str) {
      return str.charAt(0).toUpperCase() + str.slice(1);
    },

    formatTypeName(type) {
      const names = {
        pensions: 'Pension',
        investments: 'Investment',
        estate_planning: 'Estate Planning',
      };
      return names[type] || this.capitalise(type);
    },
  },
};
</script>

<style scoped>
.loading-state,
.error-state {
  text-align: center;
  padding: 3rem;
}

.error-message {
  @apply text-raspberry-500;
  margin-bottom: 1rem;
}

.settings-section {
  background: white;
  border-radius: 0.5rem;
  padding: 1.5rem;
  margin-bottom: 1.5rem;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.section-header {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  margin-bottom: 0.75rem;
}

.section-title {
  font-size: 1.125rem;
  font-weight: 600;
  @apply text-horizon-700;
  margin: 0;
}

.status-badge {
  font-size: 0.75rem;
  font-weight: 500;
  padding: 0.25rem 0.5rem;
  border-radius: 9999px;
}

.status-badge.default {
  @apply bg-eggshell-100 text-horizon-600;
}

.status-badge.custom {
  @apply bg-violet-100 text-violet-800;
}

.section-description {
  @apply text-neutral-500;
  font-size: 0.875rem;
  margin-bottom: 1.5rem;
}

.assumptions-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 1.5rem;
  margin-bottom: 1.5rem;
}

@media (max-width: 640px) {
  .assumptions-grid {
    grid-template-columns: 1fr;
  }
}

.assumption-field {
  display: flex;
  flex-direction: column;
}

.assumption-field label {
  font-size: 0.875rem;
  font-weight: 500;
  @apply text-horizon-600;
  margin-bottom: 0.5rem;
}

.input-group {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.form-input {
  flex: 1;
  padding: 0.5rem 0.75rem;
  @apply border border-light-gray;
  border-radius: 0.375rem;
  font-size: 0.875rem;
  transition: border-color 0.15s ease-in-out;
}

.form-input:focus {
  outline: none;
  @apply border-violet-500;
  box-shadow: 0 0 0 3px rgba(88, 84, 230, 0.1);
}

.input-suffix {
  @apply text-neutral-500;
  font-size: 0.875rem;
  min-width: 60px;
}

.field-hint {
  font-size: 0.75rem;
  @apply text-neutral-400;
  margin-top: 0.25rem;
}

.assumption-field.readonly .readonly-value {
  padding: 0.5rem 0.75rem;
  @apply bg-eggshell-50 border border-light-gray;
  border-radius: 0.375rem;
}

.assumption-field.readonly .value {
  font-size: 0.875rem;
  @apply text-horizon-600;
}

.summary-row {
  display: flex;
  gap: 2rem;
  padding: 1rem;
  @apply bg-eggshell-50;
  border-radius: 0.375rem;
  margin-bottom: 1.5rem;
}

.summary-item {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.summary-item .label {
  font-size: 0.875rem;
  @apply text-neutral-500;
}

.summary-item .value {
  font-size: 0.875rem;
  font-weight: 600;
  @apply text-horizon-700;
}

.section-actions {
  display: flex;
  justify-content: flex-end;
  gap: 0.75rem;
}

.btn {
  padding: 0.5rem 1rem;
  font-size: 0.875rem;
  font-weight: 500;
  border-radius: 0.375rem;
  cursor: pointer;
  transition: all 0.15s ease-in-out;
}

.btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.btn-primary {
  @apply bg-raspberry-500;
  color: white;
  border: none;
}

.btn-primary:hover:not(:disabled) {
  @apply bg-raspberry-600;
}

.btn-outline {
  @apply bg-white text-horizon-600 border border-light-gray;
}

.btn-outline:hover:not(:disabled) {
  @apply bg-eggshell-50;
}

.info-section {
  @apply bg-spring-50 border border-spring-200;
}

.info-list {
  list-style: none;
  padding: 0;
  margin: 0;
}

.info-list li {
  padding: 0.5rem 0;
  @apply text-spring-700;
  font-size: 0.875rem;
}

.info-list li strong {
  @apply text-spring-800;
}
</style>
