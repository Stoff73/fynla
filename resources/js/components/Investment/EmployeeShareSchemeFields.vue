<template>
  <div>
    <!-- Employer Details Section -->
    <div class="border-t border-light-gray pt-4 mt-4">
      <h4 class="text-sm font-semibold text-horizon-500 mb-3">Employer Details</h4>
      <div class="space-y-4">
        <div>
          <label for="employer_name" class="block text-sm font-medium text-neutral-500 mb-1">
            Employer Name          </label>
          <input
            id="employer_name"
            v-model="modelValue.employer_name"
            type="text"
            class="w-full border border-horizon-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-violet-500"
            :class="{ 'border-raspberry-500': errors.employer_name }"
            placeholder="e.g., Acme plc"
          />
          <p v-if="errors.employer_name" class="mt-1 text-sm text-raspberry-600">{{ errors.employer_name }}</p>
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label for="employer_registration" class="block text-sm font-medium text-neutral-500 mb-1">
              Company Registration
            </label>
            <input
              id="employer_registration"
              v-model="modelValue.employer_registration"
              type="text"
              class="w-full border border-horizon-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-violet-500"
              placeholder="e.g., 12345678"
            />
          </div>
          <div>
            <label for="employer_ticker" class="block text-sm font-medium text-neutral-500 mb-1">
              Ticker Symbol
            </label>
            <input
              id="employer_ticker"
              v-model="modelValue.employer_ticker"
              type="text"
              class="w-full border border-horizon-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-violet-500"
              placeholder="e.g., ACME.L"
            />
          </div>
        </div>
        <div class="flex items-center">
          <input
            id="employer_is_listed"
            v-model="modelValue.employer_is_listed"
            type="checkbox"
            class="h-4 w-4 text-violet-600 focus:ring-violet-500 border-horizon-300 rounded"
          />
          <label for="employer_is_listed" class="ml-2 block text-sm text-neutral-500">
            Shares are listed/publicly traded
          </label>
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label for="parent_company_name" class="block text-sm font-medium text-neutral-500 mb-1">
              Parent Company
            </label>
            <input
              id="parent_company_name"
              v-model="modelValue.parent_company_name"
              type="text"
              class="w-full border border-horizon-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-violet-500"
              placeholder="If US/overseas parent"
            />
          </div>
          <div>
            <label for="parent_company_country" class="block text-sm font-medium text-neutral-500 mb-1">
              Parent Country
            </label>
            <input
              id="parent_company_country"
              v-model="modelValue.parent_company_country"
              type="text"
              class="w-full border border-horizon-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-violet-500"
              placeholder="e.g., United States"
            />
          </div>
        </div>
      </div>
    </div>

    <!-- Grant Details Section -->
    <div class="border-t border-light-gray pt-4 mt-4">
      <h4 class="text-sm font-semibold text-horizon-500 mb-3">Grant Details</h4>
      <div class="space-y-4">
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label for="grant_date" class="block text-sm font-medium text-neutral-500 mb-1">
              Grant Date            </label>
            <input
              id="grant_date"
              v-model="modelValue.grant_date"
              type="date"
              class="w-full border border-horizon-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-violet-500"
              :class="{ 'border-raspberry-500': errors.grant_date }"
            />
            <p v-if="errors.grant_date" class="mt-1 text-sm text-raspberry-600">{{ errors.grant_date }}</p>
          </div>
          <div>
            <label for="grant_reference" class="block text-sm font-medium text-neutral-500 mb-1">
              Grant Reference
            </label>
            <input
              id="grant_reference"
              v-model="modelValue.grant_reference"
              type="text"
              class="w-full border border-horizon-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-violet-500"
              placeholder="e.g., GRANT-2024-001"
            />
          </div>
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label for="units_granted" class="block text-sm font-medium text-neutral-500 mb-1">
              Units Granted            </label>
            <input
              id="units_granted"
              v-model.number="modelValue.units_granted"
              type="number"
              min="0"
              class="w-full border border-horizon-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-violet-500"
              :class="{ 'border-raspberry-500': errors.units_granted }"
              placeholder="1000"
            />
            <p v-if="errors.units_granted" class="mt-1 text-sm text-raspberry-600">{{ errors.units_granted }}</p>
          </div>
          <div v-if="isOptionsScheme">
            <label for="exercise_price" class="block text-sm font-medium text-neutral-500 mb-1">
              Exercise Price            </label>
            <div class="relative">
              <span class="absolute left-3 top-2.5 text-neutral-500">£</span>
              <input
                id="exercise_price"
                v-model.number="modelValue.exercise_price"
                type="number"
                min="0"
                step="0.01"
                class="w-full border border-horizon-300 rounded-md pl-8 pr-3 py-2 focus:outline-none focus:ring-2 focus:ring-violet-500"
                :class="{ 'border-raspberry-500': errors.exercise_price }"
                placeholder="1.50"
              />
            </div>
            <p v-if="errors.exercise_price" class="mt-1 text-sm text-raspberry-600">{{ errors.exercise_price }}</p>
          </div>
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label for="market_value_at_grant" class="block text-sm font-medium text-neutral-500 mb-1">
              Market Value at Grant
            </label>
            <div class="relative">
              <span class="absolute left-3 top-2.5 text-neutral-500">£</span>
              <input
                id="market_value_at_grant"
                v-model.number="modelValue.market_value_at_grant"
                type="number"
                min="0"
                step="0.01"
                class="w-full border border-horizon-300 rounded-md pl-8 pr-3 py-2 focus:outline-none focus:ring-2 focus:ring-violet-500"
                placeholder="2.00"
              />
            </div>
          </div>
          <div>
            <label for="share_class_scheme" class="block text-sm font-medium text-neutral-500 mb-1">
              Share Class
            </label>
            <input
              id="share_class_scheme"
              v-model="modelValue.share_class_scheme"
              type="text"
              class="w-full border border-horizon-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-violet-500"
              placeholder="e.g., Ordinary"
            />
          </div>
        </div>
      </div>
    </div>

    <!-- SAYE Savings Details (only for SAYE) -->
    <div v-if="isSAYEScheme" class="border-t border-light-gray pt-4 mt-4">
      <div class="bg-spring-50 border border-spring-200 rounded-md p-4">
        <h4 class="text-sm font-semibold text-spring-900 mb-3">SAYE Savings Details</h4>
        <div class="space-y-4">
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label for="saye_monthly_savings" class="block text-sm font-medium text-neutral-500 mb-1">
                Monthly Savings (max £500)
              </label>
              <div class="relative">
                <span class="absolute left-3 top-2.5 text-neutral-500">£</span>
                <input
                  id="saye_monthly_savings"
                  v-model.number="modelValue.saye_monthly_savings"
                  type="number"
                  min="0"
                  max="500"
                  class="w-full border border-horizon-300 rounded-md pl-8 pr-3 py-2 focus:outline-none focus:ring-2 focus:ring-violet-500"
                  placeholder="500"
                />
              </div>
            </div>
            <div>
              <label for="saye_current_savings_balance" class="block text-sm font-medium text-neutral-500 mb-1">
                Current Savings Balance
              </label>
              <div class="relative">
                <span class="absolute left-3 top-2.5 text-neutral-500">£</span>
                <input
                  id="saye_current_savings_balance"
                  v-model.number="modelValue.saye_current_savings_balance"
                  type="number"
                  min="0"
                  class="w-full border border-horizon-300 rounded-md pl-8 pr-3 py-2 focus:outline-none focus:ring-2 focus:ring-violet-500"
                  placeholder="0"
                />
              </div>
            </div>
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label for="scheme_start_date" class="block text-sm font-medium text-neutral-500 mb-1">
                Contract Start Date
              </label>
              <input
                id="scheme_start_date"
                v-model="modelValue.scheme_start_date"
                type="date"
                class="w-full border border-horizon-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-violet-500"
              />
            </div>
            <div>
              <label for="scheme_duration_months" class="block text-sm font-medium text-neutral-500 mb-1">
                Contract Duration
              </label>
              <select
                id="scheme_duration_months"
                v-model.number="modelValue.scheme_duration_months"
                class="w-full border border-horizon-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-violet-500"
              >
                <option value="">Select duration</option>
                <option :value="36">3 Years</option>
                <option :value="60">5 Years</option>
              </select>
            </div>
          </div>
          <div v-if="modelValue.saye_maturity_date" class="text-sm text-spring-700">
            <strong>Maturity Date:</strong> {{ formatDate(modelValue.saye_maturity_date) }}
          </div>
        </div>
      </div>
    </div>

    <!-- CSOP Info (only for CSOP) -->
    <div v-if="isCSOPScheme" class="border-t border-light-gray pt-4 mt-4">
      <div class="bg-violet-50 border border-violet-200 rounded-md p-4">
        <h4 class="text-sm font-semibold text-violet-900 mb-2">CSOP Tax Advantage Window</h4>
        <p class="text-sm text-violet-800 mb-3">
          To benefit from tax-advantaged treatment, options must be exercised between 3 and 10 years from grant date.
        </p>
        <div v-if="modelValue.csop_three_year_date" class="text-sm text-violet-700">
          <strong>Earliest Tax-Advantaged Exercise:</strong> {{ formatDate(modelValue.csop_three_year_date) }}
        </div>
      </div>
    </div>

    <!-- Vesting Schedule Section -->
    <div class="border-t border-light-gray pt-4 mt-4">
      <h4 class="text-sm font-semibold text-horizon-500 mb-3">Vesting Schedule</h4>
      <div class="space-y-4">
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label for="vesting_type" class="block text-sm font-medium text-neutral-500 mb-1">
              Vesting Type
            </label>
            <select
              id="vesting_type"
              v-model="modelValue.vesting_type"
              class="w-full border border-horizon-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-violet-500"
            >
              <option value="">Select type</option>
              <option value="cliff">Cliff</option>
              <option value="monthly">Monthly</option>
              <option value="quarterly">Quarterly</option>
              <option value="annual">Annual</option>
              <option value="performance">Performance-based</option>
              <option value="immediate">Immediate</option>
            </select>
          </div>
          <div>
            <label for="full_vest_date" class="block text-sm font-medium text-neutral-500 mb-1">
              Full Vest Date
            </label>
            <input
              id="full_vest_date"
              v-model="modelValue.full_vest_date"
              type="date"
              class="w-full border border-horizon-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-violet-500"
            />
          </div>
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label for="cliff_date" class="block text-sm font-medium text-neutral-500 mb-1">
              Cliff Date
            </label>
            <input
              id="cliff_date"
              v-model="modelValue.cliff_date"
              type="date"
              class="w-full border border-horizon-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-violet-500"
            />
          </div>
          <div>
            <label for="cliff_percentage" class="block text-sm font-medium text-neutral-500 mb-1">
              Cliff Percentage
            </label>
            <input
              id="cliff_percentage"
              v-model.number="modelValue.cliff_percentage"
              type="number"
              min="0"
              max="100"
              class="w-full border border-horizon-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-violet-500"
              placeholder="25"
            />
          </div>
        </div>
        <div class="flex items-center">
          <input
            id="has_performance_conditions"
            v-model="modelValue.has_performance_conditions"
            type="checkbox"
            class="h-4 w-4 text-violet-600 focus:ring-violet-500 border-horizon-300 rounded"
          />
          <label for="has_performance_conditions" class="ml-2 block text-sm text-neutral-500">
            Has performance conditions
          </label>
        </div>
        <div v-if="modelValue.has_performance_conditions">
          <label for="performance_conditions_description" class="block text-sm font-medium text-neutral-500 mb-1">
            Performance Conditions
          </label>
          <textarea
            id="performance_conditions_description"
            v-model="modelValue.performance_conditions_description"
            rows="3"
            class="w-full border border-horizon-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-violet-500"
            placeholder="Describe performance targets..."
          ></textarea>
        </div>
      </div>
    </div>

    <!-- Current Status Section -->
    <div class="border-t border-light-gray pt-4 mt-4">
      <h4 class="text-sm font-semibold text-horizon-500 mb-3">Current Status</h4>
      <div class="space-y-4">
        <div class="grid grid-cols-3 gap-4">
          <div>
            <label for="units_vested" class="block text-sm font-medium text-neutral-500 mb-1">
              Units Vested
            </label>
            <input
              id="units_vested"
              v-model.number="modelValue.units_vested"
              type="number"
              min="0"
              class="w-full border border-horizon-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-violet-500"
              placeholder="0"
            />
          </div>
          <div>
            <label for="units_unvested" class="block text-sm font-medium text-neutral-500 mb-1">
              Units Unvested
            </label>
            <input
              id="units_unvested"
              v-model.number="modelValue.units_unvested"
              type="number"
              min="0"
              class="w-full border border-horizon-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-violet-500"
              placeholder="0"
            />
          </div>
          <div>
            <label for="units_exercised" class="block text-sm font-medium text-neutral-500 mb-1">
              Units Exercised
            </label>
            <input
              id="units_exercised"
              v-model.number="modelValue.units_exercised"
              type="number"
              min="0"
              class="w-full border border-horizon-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-violet-500"
              placeholder="0"
            />
          </div>
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label for="current_share_price" class="block text-sm font-medium text-neutral-500 mb-1">
              Current Share Price
            </label>
            <div class="relative">
              <span class="absolute left-3 top-2.5 text-neutral-500">£</span>
              <input
                id="current_share_price"
                v-model.number="modelValue.current_share_price"
                type="number"
                min="0"
                step="0.01"
                class="w-full border border-horizon-300 rounded-md pl-8 pr-3 py-2 focus:outline-none focus:ring-2 focus:ring-violet-500"
                placeholder="2.50"
              />
            </div>
          </div>
          <div>
            <label for="scheme_status" class="block text-sm font-medium text-neutral-500 mb-1">
              Scheme Status
            </label>
            <select
              id="scheme_status"
              v-model="modelValue.scheme_status"
              class="w-full border border-horizon-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-violet-500"
            >
              <option value="active">Active</option>
              <option value="vesting">Vesting</option>
              <option value="exercisable">Exercisable</option>
              <option value="exercised">Fully Exercised</option>
              <option value="expired">Expired</option>
              <option value="forfeited">Forfeited</option>
              <option value="cancelled">Cancelled</option>
            </select>
          </div>
        </div>
        <!-- Intrinsic Value Display -->
        <div v-if="isOptionsScheme && intrinsicValue !== null" class="bg-violet-50 border border-violet-200 rounded-md p-3">
          <div class="text-sm text-violet-800">
            <strong>Intrinsic Value (Vested):</strong> {{ formatCurrency(intrinsicValue) }}
          </div>
          <div v-if="unvestedValue !== null" class="text-sm text-violet-700 mt-1">
            <strong>Potential Value (Unvested):</strong> {{ formatCurrency(unvestedValue) }}
          </div>
        </div>
      </div>
    </div>

    <!-- Exercise & Expiry Section (options only, not RSUs) -->
    <div v-if="isOptionsScheme" class="border-t border-light-gray pt-4 mt-4">
      <h4 class="text-sm font-semibold text-horizon-500 mb-3">Exercise & Expiry</h4>
      <div class="space-y-4">
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label for="exercise_window_start" class="block text-sm font-medium text-neutral-500 mb-1">
              Exercise Window Start
            </label>
            <input
              id="exercise_window_start"
              v-model="modelValue.exercise_window_start"
              type="date"
              class="w-full border border-horizon-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-violet-500"
            />
          </div>
          <div>
            <label for="exercise_window_end" class="block text-sm font-medium text-neutral-500 mb-1">
              Exercise Window End / Expiry
            </label>
            <input
              id="exercise_window_end"
              v-model="modelValue.exercise_window_end"
              type="date"
              class="w-full border border-horizon-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-violet-500"
            />
          </div>
        </div>
      </div>
    </div>

    <!-- Tax Treatment Section -->
    <div class="border-t border-light-gray pt-4 mt-4">
      <h4 class="text-sm font-semibold text-horizon-500 mb-3">Tax Treatment</h4>
      <div class="space-y-4">
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label for="tax_treatment" class="block text-sm font-medium text-neutral-500 mb-1">
              Tax Treatment
            </label>
            <select
              id="tax_treatment"
              v-model="modelValue.tax_treatment"
              class="w-full border border-horizon-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-violet-500"
            >
              <option value="">Select treatment</option>
              <option value="tax_advantaged">Tax Advantaged</option>
              <option value="unapproved">Unapproved</option>
              <option value="mixed">Mixed</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-neutral-500 mb-1">Tax Flags</label>
            <div class="space-y-2">
              <div class="flex items-center">
                <input
                  id="is_readily_convertible_asset"
                  v-model="modelValue.is_readily_convertible_asset"
                  type="checkbox"
                  class="h-4 w-4 text-violet-600 focus:ring-violet-500 border-horizon-300 rounded"
                />
                <label for="is_readily_convertible_asset" class="ml-2 block text-sm text-neutral-500">
                  Readily Convertible Asset (RCA)
                </label>
              </div>
              <div class="flex items-center">
                <input
                  id="paye_via_payroll"
                  v-model="modelValue.paye_via_payroll"
                  type="checkbox"
                  class="h-4 w-4 text-violet-600 focus:ring-violet-500 border-horizon-300 rounded"
                />
                <label for="paye_via_payroll" class="ml-2 block text-sm text-neutral-500">
                  PAYE via Payroll
                </label>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Leaver Terms Section -->
    <div class="border-t border-light-gray pt-4 mt-4">
      <h4 class="text-sm font-semibold text-horizon-500 mb-3">Leaver Terms</h4>
      <div class="space-y-4">
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label for="leaver_category" class="block text-sm font-medium text-neutral-500 mb-1">
              Leaver Category
            </label>
            <select
              id="leaver_category"
              v-model="modelValue.leaver_category"
              class="w-full border border-horizon-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-violet-500"
            >
              <option value="">Not applicable</option>
              <option value="good_leaver">Good Leaver</option>
              <option value="bad_leaver">Bad Leaver</option>
              <option value="death">Death</option>
              <option value="redundancy">Redundancy</option>
              <option value="retirement">Retirement</option>
            </select>
          </div>
          <div v-if="modelValue.leaver_category">
            <label for="termination_date" class="block text-sm font-medium text-neutral-500 mb-1">
              Termination Date
            </label>
            <input
              id="termination_date"
              v-model="modelValue.termination_date"
              type="date"
              class="w-full border border-horizon-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-violet-500"
            />
          </div>
        </div>
        <div v-if="modelValue.leaver_category">
          <label for="leaver_notes" class="block text-sm font-medium text-neutral-500 mb-1">
            Leaver Notes
          </label>
          <textarea
            id="leaver_notes"
            v-model="modelValue.leaver_notes"
            rows="2"
            class="w-full border border-horizon-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-violet-500"
            placeholder="Any notes about leaver treatment..."
          ></textarea>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'EmployeeShareSchemeFields',

  mixins: [currencyMixin],

  props: {
    modelValue: {
      type: Object,
      required: true,
    },
    errors: {
      type: Object,
      default: () => ({}),
    },
    schemeType: {
      type: String,
      required: true,
    },
  },

  emits: ['update:modelValue'],

  computed: {
    isOptionsScheme() {
      return ['saye', 'csop', 'emi', 'unapproved_options'].includes(this.schemeType);
    },

    isSAYEScheme() {
      return this.schemeType === 'saye';
    },

    isCSOPScheme() {
      return this.schemeType === 'csop';
    },

    isRSUScheme() {
      return this.schemeType === 'rsu';
    },

    intrinsicValue() {
      if (!this.isOptionsScheme || !this.modelValue.current_share_price || !this.modelValue.exercise_price) {
        return null;
      }
      const gain = Math.max(0, this.modelValue.current_share_price - this.modelValue.exercise_price);
      return gain * (this.modelValue.units_vested || 0);
    },

    unvestedValue() {
      if (!this.modelValue.current_share_price) return null;
      if (this.isOptionsScheme) {
        const gain = Math.max(0, this.modelValue.current_share_price - (this.modelValue.exercise_price || 0));
        return gain * (this.modelValue.units_unvested || 0);
      }
      return this.modelValue.current_share_price * (this.modelValue.units_unvested || 0);
    },
  },

  methods: {
    formatDate(dateStr) {
      if (!dateStr) return '';
      const date = new Date(dateStr);
      return date.toLocaleDateString('en-GB', { day: 'numeric', month: 'long', year: 'numeric' });
    },
  },
};
</script>
