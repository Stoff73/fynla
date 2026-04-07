<template>
  <div class="space-y-6">
    <!-- Success Message -->
    <div v-if="successMessage" class="rounded-md bg-spring-50 p-4">
      <div class="flex">
        <div class="ml-3">
          <p class="text-body-sm font-medium text-spring-800">
            {{ successMessage }}
          </p>
        </div>
      </div>
    </div>

    <!-- Error Message -->
    <div v-if="errorMessage" class="rounded-md bg-raspberry-50 p-4">
      <div class="flex">
        <div class="ml-3">
          <h3 class="text-body-sm font-medium text-raspberry-800">Error updating information</h3>
          <div class="mt-2 text-body-sm text-raspberry-700">
            <p>{{ errorMessage }}</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Income Needs Update Banner -->
    <div v-if="incomeNeedsUpdate" class="rounded-md bg-light-blue-100 border border-horizon-200 p-4">
      <div class="flex">
        <div class="flex-shrink-0">
          <svg class="h-5 w-5 text-horizon-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
          </svg>
        </div>
        <div class="ml-3">
          <h3 class="text-body-sm font-medium text-horizon-600">Employment Status Changed</h3>
          <div class="mt-1 text-body-sm text-horizon-500">
            <p>
              You recently changed your employment status{{ previousStatusLabel ? ` from ${previousStatusLabel}` : '' }}.
              Please update your income below to reflect your current earnings.
            </p>
          </div>
          <div class="mt-2">
            <button
              type="button"
              @click="isEditing = true"
              class="text-body-sm font-medium text-horizon-600 underline hover:text-horizon-700"
            >
              Update income now
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Income Card — Full Width -->
    <div class="space-y-6">
      <form @submit.prevent="handleSubmit">
        <div class="bg-white rounded-lg border border-light-gray p-6 module-gradient">
          <div class="flex justify-between items-start mb-6">
            <div>
              <h3 class="text-h4 font-semibold text-horizon-500">Income</h3>
              <p class="mt-1 text-body-sm text-neutral-500">
                Your annual income from all sources
              </p>
            </div>
            <button
              v-if="!isEditing"
              type="button"
              @click="isEditing = true"
              class="btn-secondary"
            >
              Edit
            </button>
          </div>

          <!-- VIEW MODE -->
          <div v-if="!isEditing">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
              <!-- Left: Donut Chart -->
              <div v-if="incomeChartData.length > 0" class="flex items-center justify-center">
                <div class="relative" style="width: 240px; height: 240px;">
                  <svg viewBox="0 0 220 220" width="240" height="240">
                    <defs>
                      <linearGradient
                        v-for="(seg, idx) in incomeDonutSegments"
                        :key="'grad-' + idx"
                        :id="'income-grad-' + idx"
                        x1="0%" y1="0%" x2="100%" y2="0%"
                      >
                        <stop offset="0%" :stop-color="seg.color" />
                        <stop offset="100%" :stop-color="seg.colorLight" />
                      </linearGradient>
                    </defs>
                    <circle
                      v-for="(seg, idx) in incomeDonutSegments"
                      :key="'seg-' + idx"
                      cx="110" cy="110" r="75"
                      fill="none"
                      :stroke="'url(#income-grad-' + idx + ')'"
                      stroke-width="40"
                      stroke-linecap="round"
                      :stroke-dasharray="seg.arcLength + ' ' + 471.2"
                      :stroke-dashoffset="-seg.offset"
                      transform="rotate(-90 110 110)"
                    />
                  </svg>
                  <div class="absolute inset-0 flex flex-col items-center justify-center">
                    <span class="text-[10px] font-semibold text-horizon-400">Total Annual</span>
                    <span class="text-lg font-bold text-horizon-700">{{ formatCurrency(totalIncomeValue) }}</span>
                  </div>
                </div>
              </div>

              <!-- Right: Income Breakdown Data -->
              <div>
                <div class="space-y-3">
                  <div v-if="form.annual_employment_income > 0" class="flex justify-between">
                    <span class="text-body-sm text-neutral-500">Employment Income:</span>
                    <span class="text-body-sm text-horizon-500 text-right">{{ formatCurrency(form.annual_employment_income) }}</span>
                  </div>
                  <div v-if="form.annual_self_employment_income > 0" class="flex justify-between">
                    <span class="text-body-sm text-neutral-500">Self-Employment Income:</span>
                    <span class="text-body-sm text-horizon-500 text-right">{{ formatCurrency(form.annual_self_employment_income) }}</span>
                  </div>
                  <div v-if="form.annual_rental_income > 0" class="flex justify-between">
                    <span class="text-body-sm text-neutral-500">Rental Income:</span>
                    <span class="text-body-sm text-horizon-500 text-right">{{ formatCurrency(form.annual_rental_income) }}</span>
                  </div>
                  <div v-if="form.annual_dividend_income > 0" class="flex justify-between">
                    <span class="text-body-sm text-neutral-500">Dividend Income:</span>
                    <span class="text-body-sm text-horizon-500 text-right">{{ formatCurrency(form.annual_dividend_income) }}</span>
                  </div>
                  <div v-if="form.annual_interest_income > 0" class="flex justify-between">
                    <span class="text-body-sm text-neutral-500">Interest Income:</span>
                    <span class="text-body-sm text-horizon-500 text-right">{{ formatCurrency(form.annual_interest_income) }}</span>
                  </div>
                  <div v-if="form.annual_pension_income > 0" class="flex justify-between">
                    <span class="text-body-sm text-neutral-500">Pension Income:</span>
                    <span class="text-body-sm text-horizon-500 text-right">{{ formatCurrency(form.annual_pension_income) }}</span>
                  </div>
                  <div v-if="form.annual_trust_income > 0" class="flex justify-between">
                    <span class="text-body-sm text-neutral-500">Trust Income:</span>
                    <span class="text-body-sm text-horizon-500 text-right">{{ formatCurrency(form.annual_trust_income) }}</span>
                  </div>
                  <!-- Child Benefit -->
                  <div v-if="childBenefitAmount > 0" class="flex justify-between">
                    <span class="text-body-sm text-neutral-500">Child Benefit:</span>
                    <span class="text-body-sm text-horizon-500 text-right">{{ formatCurrency(childBenefitAmount) }}</span>
                  </div>
                  <!-- Registered Blind -->
                  <div v-if="form.is_registered_blind" class="flex justify-between">
                    <span class="text-body-sm text-neutral-500">Registered Blind:</span>
                    <span class="text-body-sm text-violet-600 text-right">Blind Person's Allowance applied</span>
                  </div>
                </div>

                <!-- HICBC Warning -->
                <div v-if="hicbcApplies" class="mt-4 p-3 bg-light-blue-100 border border-horizon-200 rounded-lg">
                  <div class="flex items-start">
                    <svg class="h-5 w-5 text-horizon-400 mt-0.5 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                      <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a.75.75 0 000 1.5h.253a.25.25 0 01.244.304l-.459 2.066A1.75 1.75 0 0010.747 15H11a.75.75 0 000-1.5h-.253a.25.25 0 01-.244-.304l.459-2.066A1.75 1.75 0 009.253 9H9z" clip-rule="evenodd" />
                    </svg>
                    <div class="ml-2">
                      <p class="text-body-sm font-medium text-horizon-600">High Income Child Benefit Charge</p>
                      <p class="text-body-xs text-horizon-500 mt-1">
                        Your income exceeds {{ formatCurrency(HICBC_THRESHOLD) }}. You may need to pay back
                        <strong>{{ hicbcClawbackPercentage }}%</strong> of your Child Benefit ({{ formatCurrency(hicbcCharge) }}/year) through Self Assessment.
                      </p>
                      <p class="text-body-xs text-horizon-500 mt-1">
                        Net Child Benefit after HICBC: <strong>{{ formatCurrency(hicbcNetBenefit) }}/year</strong>
                      </p>
                    </div>
                  </div>
                </div>

                <!-- Total Annual Income -->
                <div class="mt-6 pt-4 border-t border-light-gray">
                  <div class="flex justify-between items-center">
                    <span class="text-body-sm font-semibold text-horizon-500">Total Annual Income:</span>
                    <span class="text-h4 font-semibold text-horizon-500">{{ formatCurrency(totalIncomeValue) }}</span>
                  </div>
                </div>

                <!-- Disposable Income (inside Income card) -->
                <div v-if="incomeOccupation?.net_income" class="border-t border-dashed border-neutral-300 pt-3 mt-4">
                  <h4 class="text-sm font-semibold text-horizon-500 mb-2">Disposable Income</h4>
                  <div class="space-y-2">
                    <div class="flex justify-between">
                      <span class="text-body-sm text-neutral-500">{{ netIncomeLabel }}</span>
                      <span class="text-body-sm text-horizon-500 text-right">{{ formatCurrency(incomeOccupation.net_income) }}</span>
                    </div>
                    <div class="flex justify-between">
                      <span class="text-body-sm text-neutral-500">Annual Expenditure:</span>
                      <span class="text-body-sm text-horizon-500 text-right">{{ formatCurrency(totalAnnualExpenditure) }}</span>
                    </div>
                    <div class="flex justify-between items-center pt-2 border-t border-savannah-100">
                      <span class="text-body-sm font-semibold" :class="disposableIncome >= 0 ? 'text-spring-600' : 'text-raspberry-600'">Disposable Income:</span>
                      <span class="text-body font-semibold" :class="disposableIncome >= 0 ? 'text-spring-600' : 'text-raspberry-600'">{{ formatCurrency(disposableIncome) }}</span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- EDIT MODE -->
          <div v-else class="space-y-4">
            <!-- Annual Employment Income -->
            <div>
              <label class="block text-body-sm font-medium text-neutral-500 mb-1">
                Employment Income
              </label>
              <div class="relative rounded-md shadow-sm">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                  <span class="text-neutral-500 sm:text-sm">£</span>
                </div>
                <input
                  id="annual_employment_income"
                  v-model.number="form.annual_employment_income"
                  type="number"
                  step="0.01"
                  min="0"
                  class="input-field pl-7"
                  placeholder="0.00"
                />
              </div>
            </div>

            <!-- Annual Self-Employment Income -->
            <div>
              <label class="block text-body-sm font-medium text-neutral-500 mb-1">
                Self-Employment Income
              </label>
              <div class="relative rounded-md shadow-sm">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                  <span class="text-neutral-500 sm:text-sm">£</span>
                </div>
                <input
                  id="annual_self_employment_income"
                  v-model.number="form.annual_self_employment_income"
                  type="number"
                  step="0.01"
                  min="0"
                  class="input-field pl-7"
                  placeholder="0.00"
                />
              </div>
            </div>

            <!-- Annual Rental Income (Auto-calculated from Properties) -->
            <div v-if="form.annual_rental_income > 0">
              <label class="block text-body-sm font-medium text-neutral-500 mb-1">
                Rental Income
              </label>
              <p class="text-body-base text-horizon-500 py-2">{{ formatCurrency(form.annual_rental_income) }}</p>
              <p class="text-body-xs text-neutral-500">Automatically calculated from your properties</p>
            </div>

            <!-- Annual Dividend Income -->
            <div>
              <label class="block text-body-sm font-medium text-neutral-500 mb-1">
                Dividend Income
              </label>
              <div class="relative rounded-md shadow-sm">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                  <span class="text-neutral-500 sm:text-sm">£</span>
                </div>
                <input
                  id="annual_dividend_income"
                  v-model.number="form.annual_dividend_income"
                  type="number"
                  step="0.01"
                  min="0"
                  class="input-field pl-7"
                  placeholder="0.00"
                />
              </div>
            </div>

            <!-- Annual Interest Income -->
            <div>
              <label class="block text-body-sm font-medium text-neutral-500 mb-1">
                Interest Income
              </label>
              <div class="relative rounded-md shadow-sm">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                  <span class="text-neutral-500 sm:text-sm">£</span>
                </div>
                <input
                  id="annual_interest_income"
                  v-model.number="form.annual_interest_income"
                  type="number"
                  step="0.01"
                  min="0"
                  class="input-field pl-7"
                  placeholder="0.00"
                />
              </div>
            </div>

            <!-- Annual Pension Income (Auto-calculated from Retirement module) -->
            <div v-if="form.annual_pension_income > 0">
              <label class="block text-body-sm font-medium text-neutral-500 mb-1">
                Pension Income
              </label>
              <p class="text-body-base text-horizon-500 py-2">{{ formatCurrency(form.annual_pension_income) }}</p>
              <p class="text-body-xs text-neutral-500">Calculated from Defined Benefit pensions and state pension in payment</p>
            </div>

            <!-- Annual Trust Income -->
            <div>
              <label class="block text-body-sm font-medium text-neutral-500 mb-1">
                Trust Income
              </label>
              <div class="relative rounded-md shadow-sm">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                  <span class="text-neutral-500 sm:text-sm">£</span>
                </div>
                <input
                  id="annual_trust_income"
                  v-model.number="form.annual_trust_income"
                  type="number"
                  step="0.01"
                  min="0"
                  class="input-field pl-7"
                  placeholder="0.00"
                />
              </div>
              <p class="text-body-xs text-neutral-500">Income received from trusts (taxable)</p>
            </div>

            <!-- Registered Blind -->
            <div class="col-span-full border-t pt-4">
              <div class="flex items-center gap-3">
                <input
                  id="is_registered_blind"
                  v-model="form.is_registered_blind"
                  type="checkbox"
                  class="h-4 w-4 rounded border-light-gray text-violet-500 focus:ring-violet-500"
                >
                <label for="is_registered_blind" class="text-body-sm text-horizon-500">
                  I am registered blind or severely sight impaired
                </label>
              </div>
              <p class="mt-1 ml-7 text-body-sm text-neutral-500">
                This qualifies you for the Blind Person's Allowance, which reduces your taxable income
              </p>
            </div>

            <!-- Total Annual Income -->
            <div class="pt-4 border-t border-light-gray">
              <div class="flex justify-between items-center">
                <span class="text-body-sm font-semibold text-horizon-500">Total Annual Income:</span>
                <span class="text-h4 font-semibold text-horizon-500">{{ formatCurrency(totalIncomeValue) }}</span>
              </div>
            </div>

            <!-- Action Buttons -->
            <div class="flex justify-end space-x-4 pt-4 border-t border-light-gray">
              <button
                type="button"
                @click="handleCancel"
                class="btn-secondary"
                :disabled="submitting"
              >
                Cancel
              </button>
              <button
                type="submit"
                class="btn-primary"
                :disabled="submitting"
              >
                <span v-if="!submitting">Save Changes</span>
                <span v-else>Saving...</span>
              </button>
            </div>
          </div>
        </div>
      </form>

      <!-- Tax + Disposable Income — Side by Side -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Tax Calculations Card -->
        <div v-if="detailedTaxBreakdown?.summary" class="bg-white rounded-lg border border-light-gray p-6 h-full">
          <h3 class="text-h4 font-semibold text-horizon-500 mb-4">Estimated Tax and National Insurance</h3>

          <!-- Income Type Cards -->
          <div
            v-if="detailedTaxBreakdown.income_breakdowns?.length > 0"
            class="space-y-4"
          >
            <TaxIncomeCard
              v-for="(breakdown, index) in detailedTaxBreakdown.income_breakdowns"
              :key="breakdown.income_type + '-' + index"
              :breakdown="breakdown"
              :rental-breakdown="breakdown.income_type === 'earned' ? rentalBreakdown : null"
              :section24="breakdown.income_type === 'earned' ? detailedTaxBreakdown.section_24 : null"
            />
          </div>

          <!-- Info Note -->
          <div class="mt-4 p-3 bg-light-blue-100 rounded-lg">
            <p class="text-body-xs text-horizon-600">
              <strong>Note:</strong> Tax calculations use {{ detailedTaxBreakdown.tax_year }} UK tax rates.
              Income is taxed in priority order: employment income uses the Personal Allowance first,
              with other income types taxed at remaining band positions.
            </p>
          </div>
        </div>

        <!-- Income Definitions Panel -->
        <IncomeDefinitionsPanel :definitions="incomeDefinitions" />
      </div>
    </div>
  </div>
</template>

<script>
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue';
import { useStore } from 'vuex';
import TaxIncomeCard from './TaxIncomeCard.vue';
import IncomeDefinitionsPanel from './IncomeDefinitionsPanel.vue';
import { formatCurrency } from '@/utils/currency';
import { CHART_COLORS } from '@/constants/designSystem';
import { HICBC_THRESHOLD } from '@/constants/taxConfig';
import api from '@/services/api';

import logger from '@/utils/logger';
export default {
  name: 'IncomeOccupation',

  components: {
    TaxIncomeCard,
    IncomeDefinitionsPanel,
  },

  setup() {
    const store = useStore();
    const isEditing = ref(false);
    const submitting = ref(false);
    const successMessage = ref('');
    const errorMessage = ref('');

    const incomeDefinitions = ref(null);

    let messageTimeout = null;

    const incomeOccupation = computed(() => store.getters['userProfile/incomeOccupation']);
    const detailedTaxBreakdown = computed(() => incomeOccupation.value?.detailed_tax_breakdown || null);
    const rentalBreakdown = computed(() => incomeOccupation.value?.rental_breakdown || null);

    // Child Benefit computed properties
    const childBenefitAmount = computed(() => incomeOccupation.value?.child_benefit?.annual_amount || 0);
    const hicbcApplies = computed(() => incomeOccupation.value?.hicbc?.applies || false);
    const hicbcCharge = computed(() => incomeOccupation.value?.hicbc?.charge || 0);
    const hicbcNetBenefit = computed(() => incomeOccupation.value?.hicbc?.net_benefit || 0);
    const hicbcClawbackPercentage = computed(() => incomeOccupation.value?.hicbc?.clawback_percentage || 0);

    // Check if income needs updating due to employment status change
    const incomeNeedsUpdate = computed(() => incomeOccupation.value?.income_needs_update || false);
    const previousEmploymentStatus = computed(() => incomeOccupation.value?.previous_employment_status || null);

    // Format previous status for display
    const previousStatusLabel = computed(() => {
      const statusMap = {
        'employed': 'Employed',
        'part_time': 'Part-Time',
        'self_employed': 'Self-Employed',
        'retired': 'Retired',
        'unemployed': 'Unemployed',
        'other': 'Other',
      };
      return previousEmploymentStatus.value ? statusMap[previousEmploymentStatus.value] || previousEmploymentStatus.value : null;
    });

    const form = ref({
      annual_employment_income: 0,
      annual_self_employment_income: 0,
      annual_rental_income: 0,
      annual_dividend_income: 0,
      annual_interest_income: 0,
      annual_pension_income: 0,
      annual_trust_income: 0,
      annual_other_income: 0,
      is_registered_blind: false,
    });

    // Income chart data for donut chart
    const incomeChartData = computed(() => {
      const sources = [
        { label: 'Employment', value: form.value.annual_employment_income || 0 },
        { label: 'Self-Employment', value: form.value.annual_self_employment_income || 0 },
        { label: 'Rental', value: form.value.annual_rental_income || 0 },
        { label: 'Dividend', value: form.value.annual_dividend_income || 0 },
        { label: 'Interest', value: form.value.annual_interest_income || 0 },
        { label: 'Pension', value: form.value.annual_pension_income || 0 },
        { label: 'Trust', value: form.value.annual_trust_income || 0 },
      ];
      return sources.filter(s => s.value > 0);
    });

    const lightenColor = (hex, amount) => {
      const r = parseInt(hex.slice(1, 3), 16);
      const g = parseInt(hex.slice(3, 5), 16);
      const b = parseInt(hex.slice(5, 7), 16);
      const lighten = (c) => Math.min(255, Math.round(c + (255 - c) * amount));
      return `#${lighten(r).toString(16).padStart(2, '0')}${lighten(g).toString(16).padStart(2, '0')}${lighten(b).toString(16).padStart(2, '0')}`;
    };

    const incomeDonutSegments = computed(() => {
      const data = incomeChartData.value;
      const total = data.reduce((sum, s) => sum + s.value, 0);
      if (total === 0) return [];

      const circumference = 471.2;
      const gap = 3;
      let offset = 0;
      return data.map((item, idx) => {
        const proportion = item.value / total;
        const arcLength = Math.max(proportion * circumference - gap, 2);
        const color = CHART_COLORS[idx % CHART_COLORS.length];
        const seg = {
          color,
          colorLight: lightenColor(color, 0.35),
          arcLength,
          offset,
        };
        offset += proportion * circumference;
        return seg;
      });
    });

    const totalIncomeValue = computed(() => {
      return (form.value.annual_employment_income || 0) +
        (form.value.annual_self_employment_income || 0) +
        (form.value.annual_rental_income || 0) +
        (form.value.annual_dividend_income || 0) +
        (form.value.annual_interest_income || 0) +
        (form.value.annual_pension_income || 0) +
        (form.value.annual_trust_income || 0);
    });

    // Use saved expenditure directly (Expenditure form saves total including commitments)
    const totalMonthlyExpenditure = computed(() => {
      return Number(incomeOccupation.value?.monthly_expenditure || 0);
    });

    const totalAnnualExpenditure = computed(() => {
      return Number(incomeOccupation.value?.annual_expenditure || 0) ||
             totalMonthlyExpenditure.value * 12;
    });

    const disposableIncome = computed(() => {
      if (!incomeOccupation.value) return 0;
      const netIncome = incomeOccupation.value.net_income || 0;
      return netIncome - totalAnnualExpenditure.value;
    });

    // Dynamic label for net income based on what deductions apply
    const netIncomeLabel = computed(() => {
      const hasPensionContributions = (incomeOccupation.value?.annual_pension_contributions || 0) > 0;
      const hasTaxCredits = (incomeOccupation.value?.detailed_tax_breakdown?.summary?.section_24_credit || 0) > 0;

      if (hasPensionContributions && hasTaxCredits) {
        return 'Net Income (after tax, pension contributions and tax credits):';
      } else if (hasPensionContributions) {
        return 'Net Income (after tax and pension contributions):';
      } else if (hasTaxCredits) {
        return 'Net Income (after tax and tax credits):';
      }
      return 'Net Income (after tax):';
    });

    const monthlyDisposable = computed(() => {
      return disposableIncome.value / 12;
    });

    const disposableIncomeClass = computed(() => {
      return disposableIncome.value >= 0 ? 'bg-spring-50' : 'bg-raspberry-50';
    });

    // Initialize form from incomeOccupation
    const initializeForm = () => {
      if (incomeOccupation.value) {
        form.value = {
          annual_employment_income: Number(incomeOccupation.value.annual_employment_income) || 0,
          annual_self_employment_income: Number(incomeOccupation.value.annual_self_employment_income) || 0,
          annual_rental_income: Number(incomeOccupation.value.annual_rental_income) || 0,
          annual_dividend_income: Number(incomeOccupation.value.annual_dividend_income) || 0,
          annual_interest_income: Number(incomeOccupation.value.annual_interest_income) || 0,
          annual_pension_income: Number(incomeOccupation.value.annual_pension_income) || 0,
          annual_trust_income: Number(incomeOccupation.value.annual_trust_income) || 0,
          annual_other_income: Number(incomeOccupation.value.annual_other_income) || 0,
          is_registered_blind: incomeOccupation.value.is_registered_blind || false,
        };
      }
    };

    // Watch for changes in incomeOccupation and reinitialize form
    watch(incomeOccupation, () => {
      if (!isEditing.value) {
        initializeForm();
      }
    }, { immediate: true });

    const handleSubmit = async () => {
      submitting.value = true;
      successMessage.value = '';
      errorMessage.value = '';

      try {
        // Preserve existing occupation values when updating income
        const updateData = {
          // Preserve occupation fields
          occupation: incomeOccupation.value?.occupation || null,
          employer: incomeOccupation.value?.employer || null,
          industry: incomeOccupation.value?.industry || null,
          employment_status: incomeOccupation.value?.employment_status || null,
          target_retirement_age: incomeOccupation.value?.target_retirement_age || null,
          retirement_date: incomeOccupation.value?.retirement_date || null,
          // Update income fields
          annual_employment_income: form.value.annual_employment_income || 0,
          annual_self_employment_income: form.value.annual_self_employment_income || 0,
          annual_dividend_income: form.value.annual_dividend_income || 0,
          annual_interest_income: form.value.annual_interest_income || 0,
          annual_trust_income: form.value.annual_trust_income || 0,
          annual_other_income: form.value.annual_other_income || 0,
          // Registered blind
          is_registered_blind: form.value.is_registered_blind || false,
          // Clear the income needs update flag since user is updating their income
          income_needs_update: false,
          previous_employment_status: null,
        };

        await store.dispatch('userProfile/updateIncomeOccupation', updateData);

        // Refresh profile data to get updated tax calculations
        await store.dispatch('userProfile/fetchProfile');

        successMessage.value = 'Income information updated successfully!';
        isEditing.value = false;

        // Refresh income definitions after income update
        try {
          const response = await api.get('/tax/income-definitions');
          incomeDefinitions.value = response.data.data;
        } catch (defError) {
          // Silently fail - income definitions are supplementary
        }

        // Trigger protection analysis refresh if user has protection module data
        try {
          await store.dispatch('protection/fetchProtectionData');
        } catch (protectionError) {
          // Silently fail - user might not have protection module set up yet
        }

        // Clear success message after 3 seconds
        if (messageTimeout) clearTimeout(messageTimeout);
        messageTimeout = setTimeout(() => {
          successMessage.value = '';
        }, 3000);
      } catch (error) {
        logger.error('Update error:', error);
        if (error.errors) {
          const errors = Object.values(error.errors).flat();
          errorMessage.value = errors.join('. ');
        } else {
          errorMessage.value = error.message || 'Failed to update income information';
        }
      } finally {
        submitting.value = false;
      }
    };

    const handleCancel = () => {
      initializeForm();
      isEditing.value = false;
      errorMessage.value = '';
    };

    onMounted(async () => {
      try {
        const response = await api.get('/tax/income-definitions');
        incomeDefinitions.value = response.data.data;
      } catch (error) {
        // Silently fail - income definitions are supplementary
        logger.error('Failed to fetch income definitions:', error);
      }
    });

    onBeforeUnmount(() => {
      if (messageTimeout) clearTimeout(messageTimeout);
    });

    return {
      form,
      isEditing,
      submitting,
      successMessage,
      errorMessage,
      totalIncomeValue,
      incomeChartData,
      incomeDonutSegments,
      incomeOccupation,
      detailedTaxBreakdown,
      rentalBreakdown,
      totalMonthlyExpenditure,
      totalAnnualExpenditure,
      disposableIncome,
      monthlyDisposable,
      disposableIncomeClass,
      incomeNeedsUpdate,
      previousStatusLabel,
      netIncomeLabel,
      childBenefitAmount,
      hicbcApplies,
      hicbcCharge,
      hicbcNetBenefit,
      hicbcClawbackPercentage,
      incomeDefinitions,
      handleSubmit,
      handleCancel,
      formatCurrency,
      HICBC_THRESHOLD,
    };
  },
};
</script>
