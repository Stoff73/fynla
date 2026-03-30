<template>
  <div class="pension-detail-inline">
    <!-- Back Button -->
    <button @click="$emit('back')" class="detail-inline-back mb-4">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
      </svg>
      Back to Pensions
    </button>

    <!-- Loading State -->
    <div v-if="loading" class="text-center py-12">
      <div class="inline-block animate-spin rounded-full h-12 w-12 border-b-2 border-violet-600"></div>
      <p class="mt-4 text-neutral-500">Loading pension details...</p>
    </div>

    <!-- Pension Content -->
    <div v-else class="space-y-6">
      <!-- Header -->
      <div class="bg-white rounded-lg shadow-md p-6">
        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-4">
          <div>
            <div class="flex flex-wrap items-center gap-2 sm:gap-3 mb-2">
              <span :class="['badge', badgeClass]">{{ pensionTypeLabel }}</span>
            </div>
            <h1 class="text-xl sm:text-2xl lg:text-3xl font-bold text-horizon-500">{{ pensionName }}</h1>
            <p class="text-base sm:text-lg text-neutral-500 mt-1">{{ providerName }}</p>
          </div>
          <div class="flex space-x-2 w-full sm:w-auto">
            <button
              v-preview-disabled="'edit'"
              @click="showEditModal = true"
              class="px-4 py-2 bg-raspberry-500 text-white rounded-button hover:bg-raspberry-600 transition-colors"
            >
              Edit
            </button>
            <button
              v-if="pensionType !== 'state'"
              v-preview-disabled="'delete'"
              @click="confirmDelete"
              class="px-4 py-2 bg-raspberry-600 text-white rounded-button hover:bg-raspberry-700 transition-colors"
            >
              Delete
            </button>
          </div>
        </div>
      </div>

      <!-- Tabs -->
      <div class="bg-white rounded-lg shadow-md">
        <div class="border-b border-light-gray">
          <nav class="flex -mb-px overflow-x-auto">
            <button
              v-for="tab in tabs"
              :key="tab.id"
              @click="activeTab = tab.id"
              class="px-6 py-3 border-b-2 font-medium text-sm transition-colors whitespace-nowrap"
              :class="
                activeTab === tab.id
                  ? 'border-violet-600 text-violet-600'
                  : 'border-transparent text-neutral-500 hover:text-neutral-500 hover:border-horizon-300'
              "
            >
              {{ tab.label }}
            </button>
          </nav>
        </div>

        <div class="p-6">
          <!-- Overview Tab -->
          <div v-show="activeTab === 'overview'" class="space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <!-- DC Pension Details -->
              <template v-if="pensionType === 'dc'">
                <div>
                  <h3 class="text-lg font-semibold text-horizon-500 mb-3">Pension Details</h3>
                  <dl class="space-y-2">
                    <div class="flex justify-between">
                      <dt class="text-sm text-neutral-500">Scheme Name:</dt>
                      <dd class="text-sm font-medium text-horizon-500 text-right">{{ pension.scheme_name || 'N/A' }}</dd>
                    </div>
                    <div class="flex justify-between">
                      <dt class="text-sm text-neutral-500">Provider:</dt>
                      <dd class="text-sm font-medium text-horizon-500">{{ pension.provider || 'N/A' }}</dd>
                    </div>
                    <div class="flex justify-between">
                      <dt class="text-sm text-neutral-500">Pension Type:</dt>
                      <dd class="text-sm font-medium text-horizon-500 capitalize">{{ pension.pension_type || 'N/A' }}</dd>
                    </div>
                    <div class="flex justify-between">
                      <dt class="text-sm text-neutral-500">Policy Number:</dt>
                      <dd class="text-sm font-medium text-horizon-500">{{ pension.member_number || pension.policy_number || 'N/A' }}</dd>
                    </div>
                  </dl>
                </div>

                <div>
                  <h3 class="text-lg font-semibold text-horizon-500 mb-3">Fund Value</h3>
                  <dl class="space-y-2">
                    <div class="flex justify-between">
                      <dt class="text-sm text-neutral-500">Current Fund Value:</dt>
                      <dd class="text-sm font-semibold text-violet-600">{{ formatCurrency(pension.current_fund_value) }}</dd>
                    </div>
                    <div class="flex justify-between">
                      <dt class="text-sm text-neutral-500">Valuation Date:</dt>
                      <dd class="text-sm font-medium text-horizon-500">{{ formatDate(pension.valuation_date) || 'N/A' }}</dd>
                    </div>
                  </dl>
                </div>

                <div>
                  <h3 class="text-lg font-semibold text-horizon-500 mb-3">Contributions</h3>
                  <dl class="space-y-2">
                    <div v-if="pension.employee_contribution_percent" class="flex justify-between">
                      <dt class="text-sm text-neutral-500">Employee Rate:</dt>
                      <dd class="text-sm font-medium text-horizon-500">{{ pension.employee_contribution_percent }}% ({{ formatCurrency(monthlyEmployeeContribution) }}/mo)</dd>
                    </div>
                    <div v-if="pension.employer_contribution_percent" class="flex justify-between">
                      <dt class="text-sm text-neutral-500">Employer Rate:</dt>
                      <dd class="text-sm font-medium text-horizon-500">{{ pension.employer_contribution_percent }}% ({{ formatCurrency(monthlyEmployerContribution) }}/mo)</dd>
                    </div>
                    <div v-if="pension.employer_matching_limit" class="flex justify-between">
                      <dt class="text-sm text-neutral-500">Employer Matching:</dt>
                      <dd class="text-sm font-medium text-horizon-500">{{ pension.employer_matching_limit == 100 ? 'Full matching' : 'Up to ' + pension.employer_matching_limit + '%' }}</dd>
                    </div>
                    <div class="flex justify-between">
                      <dt class="text-sm text-neutral-500">Total Monthly:</dt>
                      <dd class="text-sm font-medium text-horizon-500">{{ formatCurrency(totalMonthlyContribution) }}</dd>
                    </div>
                    <div class="flex justify-between">
                      <dt class="text-sm text-neutral-500">Annual Contribution:</dt>
                      <dd class="text-sm font-medium text-horizon-500">{{ formatCurrency(annualContribution) }}</dd>
                    </div>
                  </dl>
                </div>

                <div>
                  <h3 class="text-lg font-semibold text-horizon-500 mb-3">Retirement</h3>
                  <dl class="space-y-2">
                    <div class="flex justify-between">
                      <dt class="text-sm text-neutral-500">Retirement Age:</dt>
                      <dd class="text-sm font-medium text-horizon-500">{{ userRetirementAge }}</dd>
                    </div>
                    <div class="flex justify-between">
                      <dt class="text-sm text-neutral-500">Growth Rate Assumption:</dt>
                      <dd class="text-sm font-medium text-horizon-500">{{ pension.growth_rate ? (pension.growth_rate * 100).toFixed(1) + '%' : 'N/A' }}</dd>
                    </div>
                    <div class="flex justify-between">
                      <dt class="text-sm text-neutral-500">Beneficiary:</dt>
                      <dd class="text-sm font-medium text-horizon-500">{{ pension.beneficiary_name || 'Not specified' }}</dd>
                    </div>
                  </dl>
                </div>

                <div>
                  <h3 class="text-lg font-semibold text-horizon-500 mb-3">Fees</h3>
                  <dl class="space-y-2">
                    <div class="flex justify-between">
                      <dt class="text-sm text-neutral-500">Platform Fee:</dt>
                      <dd class="text-sm font-medium text-horizon-500">{{ platformFeeDisplay }}</dd>
                    </div>
                    <div v-if="advisorFeePercent > 0" class="flex justify-between">
                      <dt class="text-sm text-neutral-500">Advisor Fee:</dt>
                      <dd class="text-sm font-medium text-horizon-500">{{ advisorFeePercent.toFixed(2) }}% p.a.</dd>
                    </div>
                    <div v-if="hasHoldings" class="flex justify-between">
                      <dt class="text-sm text-neutral-500">Avg Fund Fee (OCF):</dt>
                      <dd class="text-sm font-medium text-horizon-500">{{ weightedAverageOCF.toFixed(2) }}%</dd>
                    </div>
                    <div class="flex justify-between border-t border-light-gray pt-2 mt-2">
                      <dt class="text-sm text-neutral-500 font-medium">Total Annual Cost:</dt>
                      <dd class="text-sm font-semibold text-horizon-500">{{ totalFeePercent.toFixed(2) }}%</dd>
                    </div>
                    <div class="flex justify-between">
                      <dt class="text-sm text-neutral-500">Annual Fee Impact:</dt>
                      <dd class="text-sm font-medium text-raspberry-600">{{ formatCurrency(annualFeeCost) }}/year</dd>
                    </div>
                  </dl>
                </div>
              </template>

              <!-- DB Pension Details -->
              <template v-else-if="pensionType === 'db'">
                <div>
                  <h3 class="text-lg font-semibold text-horizon-500 mb-3">Scheme Details</h3>
                  <dl class="space-y-2">
                    <div class="flex justify-between">
                      <dt class="text-sm text-neutral-500">Scheme Name:</dt>
                      <dd class="text-sm font-medium text-horizon-500 text-right">{{ pension.scheme_name || 'N/A' }}</dd>
                    </div>
                    <div class="flex justify-between">
                      <dt class="text-sm text-neutral-500">Employer:</dt>
                      <dd class="text-sm font-medium text-horizon-500">{{ pension.employer || 'N/A' }}</dd>
                    </div>
                    <div class="flex justify-between">
                      <dt class="text-sm text-neutral-500">Scheme Type:</dt>
                      <dd class="text-sm font-medium text-horizon-500 capitalize">{{ formatDBSchemeType(pension.scheme_type) }}</dd>
                    </div>
                  </dl>
                </div>

                <div>
                  <h3 class="text-lg font-semibold text-horizon-500 mb-3">Benefits</h3>
                  <dl class="space-y-2">
                    <div class="flex justify-between">
                      <dt class="text-sm text-neutral-500">Annual Pension:</dt>
                      <dd class="text-sm font-semibold text-violet-600">{{ formatCurrency(pension.accrued_annual_pension) }}/yr</dd>
                    </div>
                    <div class="flex justify-between">
                      <dt class="text-sm text-neutral-500">Lump Sum Entitlement:</dt>
                      <dd class="text-sm font-medium text-horizon-500">{{ formatCurrency(pension.lump_sum_entitlement || 0) }}</dd>
                    </div>
                    <div class="flex justify-between">
                      <dt class="text-sm text-neutral-500">Revaluation Rate:</dt>
                      <dd class="text-sm font-medium text-horizon-500">{{ pension.revaluation_rate ? (pension.revaluation_rate * 100).toFixed(1) + '%' : 'N/A' }}</dd>
                    </div>
                  </dl>
                </div>

                <div>
                  <h3 class="text-lg font-semibold text-horizon-500 mb-3">Payment Details</h3>
                  <dl class="space-y-2">
                    <div class="flex justify-between">
                      <dt class="text-sm text-neutral-500">Normal Retirement Age:</dt>
                      <dd class="text-sm font-medium text-horizon-500">{{ pension.normal_retirement_age || 65 }}</dd>
                    </div>
                    <div class="flex justify-between">
                      <dt class="text-sm text-neutral-500">Payment Start Age:</dt>
                      <dd class="text-sm font-medium text-horizon-500">{{ pension.payment_start_age || pension.normal_retirement_age || 65 }}</dd>
                    </div>
                    <div class="flex justify-between">
                      <dt class="text-sm text-neutral-500">Spouse Pension:</dt>
                      <dd class="text-sm font-medium text-horizon-500">{{ pension.spouse_pension_percentage ? pension.spouse_pension_percentage + '%' : 'N/A' }}</dd>
                    </div>
                  </dl>
                </div>

                <div>
                  <h3 class="text-lg font-semibold text-horizon-500 mb-3">Service Details</h3>
                  <dl class="space-y-2">
                    <div class="flex justify-between">
                      <dt class="text-sm text-neutral-500">Date Joined:</dt>
                      <dd class="text-sm font-medium text-horizon-500">{{ formatDate(pension.date_joined) || 'N/A' }}</dd>
                    </div>
                    <div class="flex justify-between">
                      <dt class="text-sm text-neutral-500">Date Left:</dt>
                      <dd class="text-sm font-medium text-horizon-500">{{ formatDate(pension.date_left) || 'Current' }}</dd>
                    </div>
                  </dl>
                </div>
              </template>

              <!-- State Pension Details -->
              <template v-else-if="pensionType === 'state'">
                <div>
                  <h3 class="text-lg font-semibold text-horizon-500 mb-3">State Pension Details</h3>
                  <dl class="space-y-2">
                    <div class="flex justify-between">
                      <dt class="text-sm text-neutral-500">Forecast Annual Amount:</dt>
                      <dd class="text-sm font-semibold text-spring-600">{{ formatCurrency(pension.state_pension_forecast_annual || 0) }}/yr</dd>
                    </div>
                    <div class="flex justify-between">
                      <dt class="text-sm text-neutral-500">Weekly Amount:</dt>
                      <dd class="text-sm font-medium text-horizon-500">{{ formatCurrency((pension.state_pension_forecast_annual || 0) / 52) }}/wk</dd>
                    </div>
                  </dl>
                </div>

                <div>
                  <h3 class="text-lg font-semibold text-horizon-500 mb-3">National Insurance Record</h3>
                  <dl class="space-y-2">
                    <div class="flex justify-between">
                      <dt class="text-sm text-neutral-500">National Insurance Years Completed:</dt>
                      <dd class="text-sm font-medium text-horizon-500">{{ pension.ni_years_completed || 0 }}</dd>
                    </div>
                    <div class="flex justify-between">
                      <dt class="text-sm text-neutral-500">Years to Full Pension:</dt>
                      <dd class="text-sm font-medium text-horizon-500">{{ Math.max(0, 35 - (pension.ni_years_completed || 0)) }}</dd>
                    </div>
                    <div class="flex justify-between">
                      <dt class="text-sm text-neutral-500">State Pension Age:</dt>
                      <dd class="text-sm font-medium text-horizon-500">{{ pension.state_pension_age || 67 }}</dd>
                    </div>
                  </dl>
                </div>
              </template>
            </div>

            <!-- Notes -->
            <div v-if="pension.notes" class="mt-6">
              <h3 class="text-lg font-semibold text-horizon-500 mb-3">Notes</h3>
              <p class="text-neutral-500 whitespace-pre-wrap">{{ pension.notes }}</p>
            </div>
          </div>

          <!-- Holdings Tab (DC pensions with holdings) -->
          <div v-show="activeTab === 'holdings'" class="space-y-4">
            <div class="overflow-x-auto">
              <table class="w-full text-sm">
                <thead>
                  <tr class="border-b border-light-gray">
                    <th class="text-left py-2 text-neutral-500 font-medium">Fund Name</th>
                    <th class="text-left py-2 text-neutral-500 font-medium">Type</th>
                    <th class="text-right py-2 text-neutral-500 font-medium">Allocation</th>
                    <th class="text-right py-2 text-neutral-500 font-medium">Value</th>
                    <th class="text-right py-2 text-neutral-500 font-medium">OCF</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="holding in pension.holdings" :key="holding.id" class="border-b border-light-gray last:border-0">
                    <td class="py-2 text-horizon-500 font-medium">{{ holding.security_name || 'Unnamed' }}</td>
                    <td class="py-2 text-neutral-500 capitalize">{{ formatAssetType(holding.asset_type) }}</td>
                    <td class="py-2 text-right text-horizon-500">{{ holding.allocation_percent || 0 }}%</td>
                    <td class="py-2 text-right text-horizon-500">{{ formatCurrency(holdingValue(holding)) }}</td>
                    <td class="py-2 text-right text-neutral-500">{{ holding.ocf_percent ? parseFloat(holding.ocf_percent).toFixed(2) + '%' : '—' }}</td>
                  </tr>
                </tbody>
                <tfoot v-if="holdingsCashPercent > 0">
                  <tr class="border-t border-light-gray">
                    <td class="py-2 text-neutral-500 italic">Cash (unallocated)</td>
                    <td></td>
                    <td class="py-2 text-right text-neutral-500">{{ holdingsCashPercent.toFixed(1) }}%</td>
                    <td class="py-2 text-right text-neutral-500">{{ formatCurrency(holdingsCashValue) }}</td>
                    <td></td>
                  </tr>
                </tfoot>
              </table>
            </div>

            <!-- Fee summary tied to holdings -->
            <div class="bg-savannah-100 rounded-lg p-4">
              <div class="flex justify-between text-sm">
                <span class="text-neutral-500">Weighted Avg Fund Fee (OCF)</span>
                <span class="font-medium text-horizon-500">{{ weightedAverageOCF.toFixed(2) }}%</span>
              </div>
              <div class="flex justify-between text-sm mt-1">
                <span class="text-neutral-500">Total Annual Cost (platform + advisor + fund fees)</span>
                <span class="font-semibold text-horizon-500">{{ totalFeePercent.toFixed(2) }}%</span>
              </div>
            </div>

            <!-- 10-Year Fee Impact -->
            <div v-if="annualFeeCost > 0" class="bg-white border border-light-gray rounded-lg p-4">
              <h4 class="text-sm font-semibold text-horizon-500 mb-3">10-Year Fee Impact</h4>
              <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                  <p class="text-xs text-neutral-500">Cumulative Fees Paid</p>
                  <p class="text-base font-semibold text-raspberry-600">{{ formatCurrency(feeImpact10yr.totalFees) }}</p>
                </div>
                <div>
                  <p class="text-xs text-neutral-500">Lost Growth (Fee Drag)</p>
                  <p class="text-base font-semibold text-raspberry-600">{{ formatCurrency(feeImpact10yr.lostGrowth) }}</p>
                </div>
                <div>
                  <p class="text-xs text-neutral-500">Total Impact</p>
                  <p class="text-base font-semibold text-horizon-500">{{ formatCurrency(feeImpact10yr.totalImpact) }}</p>
                </div>
              </div>
              <p class="text-xs text-neutral-500 mt-2">
                Assuming {{ pension.growth_rate ? (pension.growth_rate * 100).toFixed(1) + '%' : '5%' }} growth rate and current contribution levels.
              </p>
            </div>
          </div>

          <!-- Projections Tab (DC pensions only) -->
          <div v-show="activeTab === 'projections'" class="projections-tab">
            <div v-if="projectionLoading" class="text-center py-12">
              <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-violet-600"></div>
              <p class="mt-4 text-neutral-500">Loading projections...</p>
            </div>
            <div v-else-if="projectionData">
              <!-- Summary Cards -->
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                <div class="bg-savannah-100 rounded-lg p-4">
                  <p class="text-sm text-neutral-500">Current Value</p>
                  <p class="text-xl font-bold text-violet-600">{{ formatCurrency(projectionData.current_value) }}</p>
                </div>
                <div class="bg-savannah-100 rounded-lg p-4">
                  <p class="text-sm text-neutral-500">80% Probability at Retirement</p>
                  <p class="text-xl font-bold text-spring-600">{{ formatCurrency(projectionData.percentile_20_at_retirement) }}</p>
                </div>
              </div>

              <!-- Monte Carlo Chart -->
              <div class="bg-white rounded-lg border border-light-gray p-4">
                <h3 class="text-lg font-semibold text-horizon-500 mb-4">Projected Pension Pot Growth</h3>
                <PensionPotProjectionChart :data="projectionData" />
              </div>

              <!-- Assumptions -->
              <div class="mt-4 text-sm text-neutral-500">
                <p>Based on {{ projectionData.years_to_retirement }} years to retirement age {{ projectionData.retirement_age }},
                {{ projectionData.risk_level }} risk profile ({{ projectionData.expected_return }}% expected return),
                and {{ formatCurrency(projectionData.monthly_contribution) }}/month contributions.</p>
              </div>
            </div>
            <div v-else class="text-center py-12 text-neutral-500">
              <p>Unable to load projection data</p>
            </div>
          </div>

          <!-- Documents Tab (placeholder) -->
          <div v-show="activeTab === 'documents'" class="text-center py-12 text-neutral-500">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-12 h-12 mx-auto mb-4 text-horizon-400">
              <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
            </svg>
            <p class="text-lg font-medium">Documents Coming Soon</p>
            <p class="text-sm">Upload and manage pension documents in a future update.</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Edit Modal -->
    <UnifiedPensionForm
      v-if="showEditModal"
      :pension="pension"
      :state-pension="pensionType === 'state' ? pension : null"
      :is-edit="true"
      :initial-pension-type="pensionType"
      @close="showEditModal = false"
      @save="handleUpdate"
    />

    <!-- Delete Confirmation -->
    <ConfirmDialog
      :show="showDeleteConfirm"
      title="Delete Pension"
      message="Are you sure you want to delete this pension? This action cannot be undone."
      @confirm="handleDelete"
      @cancel="showDeleteConfirm = false"
    />
  </div>
</template>

<script>
import { mapActions, mapState } from 'vuex';
import UnifiedPensionForm from '@/components/Retirement/UnifiedPensionForm.vue';
import ConfirmDialog from '@/components/Common/ConfirmDialog.vue';
import PensionPotProjectionChart from '@/components/Retirement/PensionPotProjectionChart.vue';
import { currencyMixin } from '@/mixins/currencyMixin';
import retirementService from '@/services/retirementService';

import logger from '@/utils/logger';
export default {
  name: 'PensionDetailInline',
  mixins: [currencyMixin],

  components: {
    UnifiedPensionForm,
    ConfirmDialog,
    PensionPotProjectionChart,
  },

  props: {
    pension: {
      type: Object,
      required: true,
    },
    pensionType: {
      type: String,
      required: true,
      validator: (value) => ['dc', 'db', 'state'].includes(value),
    },
  },

  emits: ['back', 'deleted', 'pension-updated'],

  data() {
    return {
      activeTab: 'overview',
      loading: false,
      showEditModal: false,
      showDeleteConfirm: false,
      projectionData: null,
      projectionLoading: false,
    };
  },

  computed: {
    ...mapState('auth', ['user']),

    userRetirementAge() {
      return this.user?.target_retirement_age || 67;
    },

    tabs() {
      const baseTabs = [
        { id: 'overview', label: 'Overview' },
        { id: 'documents', label: 'Documents' },
      ];
      if (this.pensionType === 'dc') {
        if (this.hasHoldings) {
          baseTabs.splice(1, 0, { id: 'holdings', label: 'Holdings' });
        }
        baseTabs.splice(this.hasHoldings ? 2 : 1, 0, { id: 'projections', label: 'Projections' });
      }
      return baseTabs;
    },

    pensionName() {
      if (this.pensionType === 'dc') {
        return this.pension.scheme_name || 'Defined Contribution Pension';
      } else if (this.pensionType === 'db') {
        return this.pension.scheme_name || 'Defined Benefit Pension';
      }
      return 'UK State Pension';
    },

    providerName() {
      if (this.pensionType === 'dc') {
        return this.pension.provider || '';
      } else if (this.pensionType === 'db') {
        return this.pension.employer || '';
      }
      return 'State Retirement Pension';
    },

    pensionTypeLabel() {
      if (this.pensionType === 'dc') {
        return this.formatDCPensionType(this.pension.pension_type);
      } else if (this.pensionType === 'db') {
        return this.formatDBSchemeType(this.pension.scheme_type);
      }
      return 'State Pension';
    },

    badgeClass() {
      const classes = {
        dc: 'badge-dc',
        db: 'badge-db',
        state: 'badge-state',
      };
      return classes[this.pensionType] || 'badge-dc';
    },

    // Calculate employee contribution from percentage or fixed amount
    monthlyEmployeeContribution() {
      if (this.pension.employee_contribution_percent && this.pension.annual_salary) {
        return (this.pension.annual_salary * this.pension.employee_contribution_percent / 100) / 12;
      }
      return this.pension.monthly_contribution_amount || 0;
    },

    // Calculate employer contribution from percentage
    monthlyEmployerContribution() {
      if (this.pension.employer_contribution_percent && this.pension.annual_salary) {
        return (this.pension.annual_salary * this.pension.employer_contribution_percent / 100) / 12;
      }
      return 0;
    },

    // Total monthly contribution (employee + employer)
    totalMonthlyContribution() {
      return this.monthlyEmployeeContribution + this.monthlyEmployerContribution;
    },

    // Annual contribution
    annualContribution() {
      return this.totalMonthlyContribution * 12;
    },

    // Platform fee as annualised percentage
    platformFeePercent() {
      const fundValue = parseFloat(this.pension.current_fund_value) || 0;
      if (this.pension.platform_fee_type === 'fixed' && fundValue > 0) {
        const amount = parseFloat(this.pension.platform_fee_amount) || 0;
        let annualAmount = amount;
        if (this.pension.platform_fee_frequency === 'monthly') annualAmount = amount * 12;
        else if (this.pension.platform_fee_frequency === 'quarterly') annualAmount = amount * 4;
        return (annualAmount / fundValue) * 100;
      }
      return parseFloat(this.pension.platform_fee_percent) || 0;
    },

    // Platform fee display string matching the form inputs
    platformFeeDisplay() {
      if (this.pension.platform_fee_type === 'fixed') {
        const amount = parseFloat(this.pension.platform_fee_amount) || 0;
        const freqLabel = { monthly: '/month', quarterly: '/quarter', annually: '/year' };
        const freq = freqLabel[this.pension.platform_fee_frequency] || '/year';
        return this.formatCurrency(amount) + freq;
      }
      return this.platformFeePercent.toFixed(2) + '% p.a.';
    },

    // Advisor fee percentage
    advisorFeePercent() {
      return parseFloat(this.pension.advisor_fee_percent) || 0;
    },

    // Check if pension has holdings
    hasHoldings() {
      return this.pension.holdings?.length > 0;
    },

    // Total allocation percentage across holdings
    totalHoldingsAllocation() {
      if (!this.hasHoldings) return 0;
      return this.pension.holdings.reduce((sum, h) => sum + (parseFloat(h.allocation_percent) || 0), 0);
    },

    // Cash percentage (unallocated)
    holdingsCashPercent() {
      return Math.max(0, 100 - this.totalHoldingsAllocation);
    },

    // Cash value
    holdingsCashValue() {
      const fundValue = parseFloat(this.pension.current_fund_value) || 0;
      return fundValue * (this.holdingsCashPercent / 100);
    },

    // Total holdings value (by current_value if available, otherwise by allocation)
    totalHoldingsValue() {
      if (!this.pension.holdings?.length) return 0;
      return this.pension.holdings.reduce((sum, h) => sum + (parseFloat(h.current_value) || 0), 0);
    },

    // Weighted average OCF across holdings
    weightedAverageOCF() {
      if (!this.hasHoldings) return 0;
      const fundValue = parseFloat(this.pension.current_fund_value) || 0;
      if (fundValue === 0) return 0;
      const totalWeightedOCF = this.pension.holdings.reduce((sum, h) => {
        const value = this.holdingValue(h);
        return sum + (value * (parseFloat(h.ocf_percent) || 0));
      }, 0);
      return totalWeightedOCF / fundValue;
    },

    // Total fee percentage (platform + advisor + weighted OCF)
    totalFeePercent() {
      return this.platformFeePercent + this.advisorFeePercent + this.weightedAverageOCF;
    },

    // Annual fee cost in pounds
    annualFeeCost() {
      const fundValue = parseFloat(this.pension.current_fund_value) || 0;
      return fundValue * (this.totalFeePercent / 100);
    },

    // 10-year fee impact projection
    feeImpact10yr() {
      const fundValue = parseFloat(this.pension.current_fund_value) || 0;
      const feeRate = this.totalFeePercent / 100;
      const grossGrowth = this.pension.growth_rate ? parseFloat(this.pension.growth_rate) : 0.05;
      const annualContribution = this.totalMonthlyContribution * 12;
      const years = 10;

      // Project WITH fees (net growth)
      const netGrowth = grossGrowth - feeRate;
      let valueWithFees = fundValue;
      for (let i = 0; i < years; i++) {
        valueWithFees = (valueWithFees + annualContribution) * (1 + netGrowth);
      }

      // Project WITHOUT fees (gross growth)
      let valueWithoutFees = fundValue;
      for (let i = 0; i < years; i++) {
        valueWithoutFees = (valueWithoutFees + annualContribution) * (1 + grossGrowth);
      }

      const totalFees = this.annualFeeCost * years;
      const lostGrowth = Math.max(0, valueWithoutFees - valueWithFees - totalFees);
      const totalImpact = totalFees + lostGrowth;

      return { totalFees, lostGrowth, totalImpact };
    },
  },

  watch: {
    activeTab(newTab) {
      if (newTab === 'projections' && !this.projectionData && this.pensionType === 'dc') {
        this.loadProjections();
      }
    },
  },

  methods: {
    ...mapActions('retirement', [
      'updateDCPension',
      'updateDBPension',
      'updateStatePension',
      'deleteDCPension',
      'deleteDBPension',
      'fetchRetirementData',
    ]),

    formatDate(date) {
      if (!date) return '';
      return new Date(date).toLocaleDateString('en-GB', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
      });
    },

    holdingValue(holding) {
      const fundValue = parseFloat(this.pension.current_fund_value) || 0;
      return fundValue * (parseFloat(holding.allocation_percent) || 0) / 100;
    },

    formatAssetType(type) {
      const labels = {
        equity: 'Equity',
        uk_equity: 'UK Equity',
        us_equity: 'US Equity',
        international_equity: 'Intl Equity',
        fund: 'Fund',
        etf: 'ETF',
        bond: 'Bond',
        cash: 'Cash',
        alternative: 'Alternative',
        property: 'Property',
      };
      return labels[type] || type || '—';
    },

    formatDCPensionType(type) {
      const types = {
        occupational: 'Work Pension',
        sipp: 'Self-Invested Personal Pension',
        personal: 'Personal',
        stakeholder: 'Stakeholder',
        workplace: 'Workplace',
      };
      return types[type] || 'Defined Contribution Pension';
    },

    formatDBSchemeType(type) {
      const types = {
        final_salary: 'Final Salary',
        career_average: 'Career Average',
        public_sector: 'Public Sector',
      };
      return types[type] || 'Defined Benefit Pension';
    },

    confirmDelete() {
      this.showDeleteConfirm = true;
    },

    async handleUpdate(data) {
      try {
        const pensionType = data._pensionType || this.pensionType;
        delete data._pensionType;

        if (pensionType === 'dc') {
          await this.updateDCPension({ id: this.pension.id, data });
        } else if (pensionType === 'db') {
          await this.updateDBPension({ id: this.pension.id, data });
        } else if (pensionType === 'state') {
          await this.updateStatePension(data);
        }

        this.showEditModal = false;

        // In preview mode, update local state only (API returned fake success, DB not updated)
        const isPreview = this.$store.getters['preview/isPreviewMode'];
        if (isPreview) {
          // Emit updated pension data to parent so it can update local state
          this.$emit('pension-updated', { ...this.pension, ...data });
        } else {
          // Normal mode: reload from API
          await this.fetchRetirementData();
        }

        this.$emit('back'); // Return to list to show updated data
      } catch (error) {
        logger.error('Failed to update pension:', error);
      }
    },

    async handleDelete() {
      try {
        if (this.pensionType === 'dc') {
          await this.deleteDCPension(this.pension.id);
        } else if (this.pensionType === 'db') {
          await this.deleteDBPension(this.pension.id);
        }

        this.showDeleteConfirm = false;
        this.$emit('deleted');
      } catch (error) {
        logger.error('Failed to delete pension:', error);
      }
    },

    async loadProjections() {
      if (this.projectionLoading || this.projectionData) return;

      this.projectionLoading = true;
      try {
        const response = await retirementService.getDCPensionProjection(this.pension.id);
        if (response.success) {
          this.projectionData = response.data;
        }
      } catch (error) {
        logger.error('Failed to load projections:', error);
      } finally {
        this.projectionLoading = false;
      }
    },
  },
};
</script>

<style scoped>
.pension-detail-inline {
  animation: fadeIn 0.3s ease-out;
}

.badge {
  display: inline-block;
  padding: 4px 10px;
  font-size: 12px;
  font-weight: 600;
  border-radius: 6px;
}

.badge-dc {
  @apply bg-raspberry-500;
  color: white;
}

.badge-db {
  @apply bg-purple-500;
  color: white;
}

.badge-state {
  @apply bg-spring-500;
  color: white;
}
</style>
