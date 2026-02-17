<template>
  <AppLayout>
    <div class="py-2 sm:py-3">
      <!-- 2FA Security Reminder Banner -->
      <div
        v-if="showMFABanner"
        class="mb-6 bg-green-100 border border-green-300 rounded-lg p-4 shadow-sm"
      >
        <div class="flex items-start gap-4">
          <div class="flex-shrink-0">
            <div class="w-10 h-10 bg-green-200 rounded-full flex items-center justify-center">
              <svg class="w-5 h-5 text-green-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
              </svg>
            </div>
          </div>
          <div class="flex-1">
            <h3 class="text-sm font-semibold text-green-800">Secure Your Account with Two-Factor Authentication</h3>
            <p class="mt-1 text-sm text-green-700">
              Protect your financial data by enabling two-factor authentication. It adds an extra layer of security using an authenticator app on your phone.
            </p>
            <div class="mt-3 flex items-center gap-3">
              <router-link
                to="/settings/security"
                class="inline-flex items-center px-4 py-2 bg-primary-600 text-white text-sm font-medium rounded-button hover:bg-primary-700 transition-colors"
              >
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                </svg>
                Enable Two-Factor Authentication
              </router-link>
              <button
                @click="dismissMFABanner"
                class="text-sm text-green-700 hover:text-green-900 underline"
              >
                Remind me later
              </button>
            </div>
          </div>
          <button
            @click="dismissMFABanner"
            class="flex-shrink-0 text-green-500 hover:text-green-700"
          >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>
      </div>

      <!-- Three-column dashboard grid -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
        <!-- Areas to Complete Card (shown first when user has skipped steps) -->
        <div v-if="hasAreasToComplete" class="bg-white rounded-lg border border-gray-200 p-6">
          <AreasToCompleteCard />
        </div>

        <!-- Net Worth Card -->
        <DashboardCard
          title="Net Worth"
          :loading="loading.netWorth"
          @click="navigateTo('/net-worth/wealth-summary')"
        >
          <div v-if="hasNetWorthData">
            <!-- Donut chart -->
            <apexchart
              :key="netWorthChartKey"
              type="donut"
              :options="netWorthChartOptions"
              :series="netWorthChartSeries"
              height="260"
            />

            <!-- Assets / Liabilities summary -->
            <div class="flex justify-between text-sm mt-2">
              <div>
                <span class="text-gray-500">Assets</span>
                <div class="font-semibold text-blue-600">{{ formatCurrency(netWorthData.totalAssets) }}</div>
              </div>
              <div class="text-right">
                <span class="text-gray-500">Liabilities</span>
                <div class="font-semibold text-red-600">{{ formatCurrency(netWorthData.totalLiabilities) }}</div>
              </div>
            </div>
          </div>

          <!-- Empty state when no assets or liabilities -->
          <div v-else class="space-y-4">
            <div class="border-b border-gray-200 pb-4">
              <span class="text-3xl font-bold text-gray-400">
                {{ formatCurrency(0) }}
              </span>
            </div>
            <p class="text-sm text-gray-500">
              Add your assets and liabilities to see your net worth here.
            </p>
          </div>
        </DashboardCard>

        <!-- Estate Planning Card -->
        <DashboardCard
          title="Estate Planning"
          :loading="loading.estate"
          @click="hasEstateData ? navigateTo('/estate') : null"
        >
          <div v-if="hasEstateData" class="space-y-4">
            <!-- Taxable Estate Now -->
            <div class="border-b border-gray-200 pb-4">
              <span class="text-sm text-gray-500">Taxable Estate on Joint Death Now</span>
              <div class="mt-1">
                <span class="text-2xl font-bold text-primary-600">
                  {{ formatCurrency(estateData.taxableEstate) }}
                </span>
              </div>
            </div>

            <!-- Current IHT Liability -->
            <div class="border-b border-gray-200 pb-4">
              <div class="text-sm font-semibold text-gray-900 mb-2">Current Inheritance Tax Liability</div>
              <div class="flex justify-between text-sm">
                <span class="text-gray-600">Amount Due</span>
                <span class="font-medium text-gray-900">{{ formatCurrency(estateData.ihtLiability) }}</span>
              </div>
            </div>

            <!-- Trusts -->
            <div v-if="trustsList.length > 0" class="space-y-3">
              <div class="text-sm font-semibold text-gray-900">Trusts</div>
              <div
                v-for="trust in trustsList"
                :key="trust.id"
                class="pb-2 border-b border-gray-100 last:border-0 last:pb-0"
              >
                <div class="flex justify-between text-sm">
                  <span class="font-medium text-gray-900">{{ trust.trust_name }}</span>
                  <span class="font-medium text-gray-900">{{ formatCurrency(trust.total_asset_value || trust.current_value || 0) }}</span>
                </div>
                <div v-if="trust.beneficiaries" class="text-xs text-gray-500 mt-1">
                  Beneficiaries: {{ trust.beneficiaries }}
                </div>
              </div>
            </div>
          </div>

          <!-- Empty state when no estate data -->
          <div v-else class="space-y-4">
            <div class="border-b border-gray-200 pb-4">
              <span class="text-sm text-gray-500">Taxable Estate</span>
              <div class="mt-1">
                <span class="text-3xl font-bold text-gray-400">
                  {{ formatCurrency(0) }}
                </span>
              </div>
            </div>
            <p class="text-sm text-gray-500">
              Add your assets and liabilities to see your estate value and inheritance tax position.
            </p>
          </div>

          <!-- Will question when not yet answered -->
          <div v-if="!willAnswered" class="mt-4 pt-4 border-t border-gray-200" @click.stop>
            <div class="text-sm font-semibold text-gray-900 mb-2">Do you currently have a valid will?</div>
            <div class="flex gap-3">
              <button
                type="button"
                class="flex-1 py-2 px-3 text-sm font-medium rounded-button border transition-colors"
                :class="willSelection === true ? 'bg-primary-600 text-white border-primary-600' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50'"
                @click="selectWill(true)"
              >
                Yes
              </button>
              <button
                type="button"
                class="flex-1 py-2 px-3 text-sm font-medium rounded-button border transition-colors"
                :class="willSelection === false ? 'bg-primary-600 text-white border-primary-600' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50'"
                @click="selectWill(false)"
              >
                No
              </button>
            </div>
            <p v-if="willSelection === false" class="mt-2 text-xs text-gray-500">
              A valid will ensures your estate is distributed according to your wishes.
            </p>
          </div>
        </DashboardCard>

        <!-- Investment & Savings Card -->
        <DashboardCard
          v-if="hasInvestmentData"
          title="Investments & Savings"
          :loading="loading.investment"
          @click="navigateTo('/net-worth/investments')"
        >
          <div class="space-y-4">
            <!-- Investment accounts list -->
            <div v-if="investmentAccountsList.length > 0" class="space-y-2">
              <div class="text-sm font-semibold text-gray-700">Investments</div>
              <div
                v-for="account in investmentAccountsList"
                :key="'inv-' + account.id"
                class="flex justify-between text-sm"
              >
                <span class="text-gray-600 truncate mr-2">{{ account.account_name }}</span>
                <span class="font-medium text-gray-900 whitespace-nowrap">{{ formatCurrency(ownershipValue(account, 'current_value')) }}</span>
              </div>
              <div class="flex justify-between text-sm pt-2 border-t border-gray-100">
                <span class="text-gray-700 font-medium">Total Investments</span>
                <span class="font-semibold text-primary-600">{{ formatCurrency(investmentData.totalInvestments) }}</span>
              </div>
            </div>

            <!-- Cash accounts list -->
            <div v-if="cashAccountsList.length > 0" class="space-y-2">
              <div class="text-sm font-semibold text-gray-700">Cash Accounts</div>
              <div
                v-for="account in cashAccountsList"
                :key="'cash-' + account.id"
                class="flex justify-between text-sm"
              >
                <span class="text-gray-600 truncate mr-2">{{ formatCashAccountName(account) }}</span>
                <span class="font-medium text-gray-900 whitespace-nowrap">{{ formatCurrency(ownershipValue(account, 'current_balance')) }}</span>
              </div>
              <div class="flex justify-between text-sm pt-2 border-t border-gray-100">
                <span class="text-gray-700 font-medium">Total Cash</span>
                <span class="font-semibold text-primary-600">{{ formatCurrency(investmentData.totalCash) }}</span>
              </div>
            </div>
          </div>
        </DashboardCard>

        <!-- Protection Card -->
        <DashboardCard
          v-if="hasProtectionData"
          title="Protection"
          :loading="loading.protection"
          @click="navigateTo('/protection')"
        >
          <div class="space-y-4">
            <!-- Life Insurance policies -->
            <div v-if="protectionLifePolicies.length > 0" class="space-y-2">
              <div class="text-sm font-semibold text-gray-700">Life Insurance</div>
              <div
                v-for="policy in protectionLifePolicies"
                :key="'life-' + policy.id"
                class="flex justify-between text-sm"
              >
                <span class="text-gray-600 truncate mr-2">{{ formatPolicyName(policy, 'Life') }}</span>
                <span class="font-medium text-gray-900 whitespace-nowrap">{{ formatCurrency(policy.sum_assured) }}</span>
              </div>
            </div>

            <!-- Critical Illness policies -->
            <div v-if="protectionCriticalIllnessPolicies.length > 0" class="space-y-2">
              <div class="text-sm font-semibold text-gray-700">Critical Illness</div>
              <div
                v-for="policy in protectionCriticalIllnessPolicies"
                :key="'ci-' + policy.id"
                class="flex justify-between text-sm"
              >
                <span class="text-gray-600 truncate mr-2">{{ formatPolicyName(policy, 'Critical Illness') }}</span>
                <span class="font-medium text-gray-900 whitespace-nowrap">{{ formatCurrency(policy.sum_assured) }}</span>
              </div>
            </div>

            <!-- Income Protection policies -->
            <div v-if="protectionIncomeProtectionPolicies.length > 0" class="space-y-2">
              <div class="text-sm font-semibold text-gray-700">Income Protection</div>
              <div
                v-for="policy in protectionIncomeProtectionPolicies"
                :key="'ip-' + policy.id"
                class="flex justify-between text-sm"
              >
                <span class="text-gray-600 truncate mr-2">{{ formatPolicyName(policy, 'Income Protection') }}</span>
                <span class="font-medium text-gray-900 whitespace-nowrap">{{ formatCurrency(policy.annual_benefit || policy.sum_assured) }}/yr</span>
              </div>
            </div>

            <!-- Total coverage and premium -->
            <div class="pt-2 border-t border-gray-100 space-y-2">
              <div class="flex justify-between text-sm">
                <span class="text-gray-700 font-medium">Total Coverage</span>
                <span class="font-semibold text-primary-600">{{ formatCurrency(protectionData.totalCoverage) }}</span>
              </div>
              <div class="flex justify-between text-sm">
                <span class="text-gray-600">Total Monthly Premium</span>
                <span class="font-medium text-gray-900">{{ formatCurrency(protectionData.premiumTotal) }}/mo</span>
              </div>
            </div>
          </div>
        </DashboardCard>

        <!-- Retirement Card -->
        <DashboardCard
          v-if="hasRetirementData"
          :title="retirementCardTitle"
          :loading="loading.retirement"
          @click="navigateTo('/net-worth/retirement')"
        >
          <!-- RETIRED USER: Show income breakdown -->
          <div v-if="isRetired" class="space-y-3">
            <!-- Income Sources Breakdown -->
            <div v-if="retiredIncomeData.pensionDrawdown > 0" class="flex justify-between items-center">
              <span class="text-sm text-gray-600">Pension Drawdown</span>
              <span class="text-sm font-semibold text-gray-900">{{ formatCurrency(retiredIncomeData.pensionDrawdown) }}/yr</span>
            </div>
            <div v-if="retiredIncomeData.dbPensionIncome > 0" class="flex justify-between items-center">
              <span class="text-sm text-gray-600">Defined Benefit Pension</span>
              <span class="text-sm font-semibold text-gray-900">{{ formatCurrency(retiredIncomeData.dbPensionIncome) }}/yr</span>
            </div>
            <div v-if="retiredIncomeData.statePensionIncome > 0" class="flex justify-between items-center">
              <span class="text-sm text-gray-600">State Pension</span>
              <span class="text-sm font-semibold text-gray-900">{{ formatCurrency(retiredIncomeData.statePensionIncome) }}/yr</span>
            </div>

            <!-- Total Income -->
            <div class="flex justify-between items-center pt-3 border-t border-gray-200">
              <span class="text-sm font-medium text-gray-700">Total Income</span>
              <span class="text-sm font-bold text-green-600">{{ formatCurrency(retiredIncomeData.totalIncome) }}/yr</span>
            </div>

            <!-- Income Need -->
            <div class="flex justify-between items-center">
              <span class="text-sm text-gray-600">Income Need</span>
              <span class="text-sm font-semibold text-gray-900">{{ formatCurrency(retirementData.targetIncome) }}/yr</span>
            </div>

            <!-- Surplus/Shortfall aligned right -->
            <div class="flex justify-between items-center">
              <span class="text-sm text-gray-600">{{ retiredIncomeData.totalIncome >= retirementData.targetIncome ? 'Surplus' : 'Shortfall' }}</span>
              <span
                class="text-sm font-semibold"
                :class="retiredIncomeData.totalIncome >= retirementData.targetIncome ? 'text-green-600' : 'text-blue-600'"
              >
                {{ formatCurrency(Math.abs(retiredIncomeData.totalIncome - retirementData.targetIncome)) }}/yr
              </span>
            </div>
          </div>

          <!-- NON-RETIRED USER: Show projections -->
          <div v-else class="space-y-4">
            <!-- Income row: Projected and Required inline -->
            <div class="flex justify-between border-b border-gray-200 pb-4">
              <div>
                <span class="text-sm text-gray-500">Projected Income</span>
                <div class="flex items-baseline gap-1 mt-1">
                  <span class="text-2xl font-bold text-green-600">
                    {{ formatCurrency(retirementData.projectedIncome) }}
                  </span>
                  <span class="text-xs text-gray-500">/yr</span>
                </div>
              </div>
              <div class="text-right">
                <span class="text-sm text-gray-500">Required Income</span>
                <div class="flex items-baseline justify-end gap-1 mt-1">
                  <span class="text-2xl font-bold text-gray-700">
                    {{ formatCurrency(retirementData.targetIncome) }}
                  </span>
                  <span class="text-xs text-gray-500">/yr</span>
                </div>
              </div>
            </div>

            <!-- Capital row: Projected and Required inline -->
            <div class="flex justify-between border-b border-gray-200 pb-4">
              <div>
                <span class="text-sm text-gray-500">Projected Capital</span>
                <div class="mt-1">
                  <span class="text-2xl font-bold text-green-600">
                    {{ formatCurrency(retirementData.projectedCapital) }}
                  </span>
                </div>
              </div>
              <div class="text-right">
                <span class="text-sm text-gray-500">Capital Required</span>
                <div class="mt-1">
                  <span class="text-2xl font-bold text-gray-700">
                    {{ formatCurrency(retirementData.capitalRequired) }}
                  </span>
                </div>
              </div>
            </div>

            <!-- Retirement age and years -->
            <div class="flex justify-between">
              <div v-if="retirementData.retirementAge" class="text-center">
                <span class="text-sm text-gray-500">Retirement Age</span>
                <div class="text-lg font-semibold text-gray-900">{{ retirementData.retirementAge }}</div>
              </div>
              <div v-if="retirementData.yearsToRetirement !== null" class="text-center">
                <span class="text-sm text-gray-500">Years to Retirement</span>
                <div class="text-lg font-semibold text-gray-900">{{ retirementData.yearsToRetirement }} years</div>
              </div>
            </div>
          </div>
        </DashboardCard>

        <!-- Allowances Card -->
        <DashboardCard
          title="Allowances"
          :loading="loading.taxAllowances"
        >
          <div v-if="hasAllowancesData" class="space-y-4">
            <!-- ISA Allowance -->
            <div v-if="isaAllowanceData">
              <div class="flex justify-between items-baseline mb-1">
                <span class="text-sm font-semibold text-gray-700">ISA Allowance</span>
                <span class="text-xs text-gray-500">2025/26</span>
              </div>
              <!-- Progress bar -->
              <div class="w-full bg-gray-100 rounded-full h-2 mb-2">
                <div
                  class="h-2 rounded-full transition-all"
                  :class="allowanceBarClass(isaAllowanceData.percentUsed, false)"
                  :style="{ width: Math.min(isaAllowanceData.percentUsed, 100) + '%' }"
                ></div>
              </div>
              <div class="flex justify-between text-sm">
                <span class="text-gray-600">{{ formatCurrency(isaAllowanceData.totalUsed) }} used</span>
                <span :class="isaAllowanceData.remaining >= 0 ? 'text-green-600' : 'text-red-600'" class="font-medium">
                  {{ formatCurrency(isaAllowanceData.remaining) }} remaining
                </span>
              </div>
              <!-- Cash / Stocks & Shares breakdown -->
              <div v-if="isaAllowanceData.cashUsed > 0 || isaAllowanceData.ssUsed > 0" class="flex gap-4 mt-1 text-xs text-gray-500">
                <span v-if="isaAllowanceData.cashUsed > 0">Cash ISA: {{ formatCurrency(isaAllowanceData.cashUsed) }}</span>
                <span v-if="isaAllowanceData.ssUsed > 0">Stocks &amp; Shares ISA: {{ formatCurrency(isaAllowanceData.ssUsed) }}</span>
              </div>
            </div>

            <!-- Divider -->
            <div v-if="isaAllowanceData && pensionAllowanceData" class="border-t border-gray-100"></div>

            <!-- Pension Annual Allowance -->
            <div v-if="pensionAllowanceData">
              <div class="flex justify-between items-baseline mb-1">
                <div class="flex items-center gap-2">
                  <span class="text-sm font-semibold text-gray-700">Pension Annual Allowance</span>
                  <span
                    v-if="pensionAllowanceData.isTapered"
                    class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-700"
                  >Tapered</span>
                  <span
                    v-if="pensionAllowanceData.mpaaTriggered"
                    class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-red-100 text-red-700"
                  >Money Purchase Annual Allowance</span>
                </div>
                <span class="text-xs text-gray-500">2025/26</span>
              </div>
              <!-- Progress bar -->
              <div class="w-full bg-gray-100 rounded-full h-2 mb-2">
                <div
                  class="h-2 rounded-full transition-all"
                  :class="allowanceBarClass(pensionAllowanceData.percentUsed, pensionAllowanceData.hasExcess)"
                  :style="{ width: Math.min(pensionAllowanceData.percentUsed, 100) + '%' }"
                ></div>
              </div>
              <div class="flex justify-between text-sm">
                <span class="text-gray-600">{{ formatCurrency(pensionAllowanceData.totalContributions) }} used</span>
                <span :class="pensionAllowanceData.remaining >= 0 ? 'text-green-600' : 'text-red-600'" class="font-medium">
                  {{ formatCurrency(pensionAllowanceData.remaining) }} remaining
                </span>
              </div>
              <div class="text-xs text-gray-500 mt-1">
                of {{ formatCurrency(pensionAllowanceData.availableAllowance) }} allowance
              </div>
            </div>
          </div>

          <!-- Empty state -->
          <div v-else class="text-center py-4">
            <div class="mx-auto w-12 h-12 rounded-full bg-primary-100 flex items-center justify-center mb-3">
              <svg class="w-6 h-6 text-primary-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
              </svg>
            </div>
            <h3 class="text-sm font-semibold text-gray-900 mb-1">No Allowance Data</h3>
            <p class="text-xs text-gray-500">
              Add savings or pension accounts to track your tax allowances.
            </p>
          </div>
        </DashboardCard>

        <!-- Goals & Events Card (spans 2 columns on larger screens) -->
        <DashboardCard
          v-if="hasGoalsData"
          title="Goals & Life Events"
          :loading="loading.goals"
          class="lg:col-span-2"
          @click="navigateTo('/goals')"
        >
          <!-- Bar chart with event icons - simplified for dashboard -->
          <div v-if="goalsData.hasProjection || goalsData.hasGoals" class="cursor-pointer">
            <GoalsProjectionChartDashboard />
          </div>

          <!-- Empty state for goals -->
          <div v-else class="text-center py-4">
            <div class="mx-auto w-12 h-12 rounded-full bg-primary-100 flex items-center justify-center mb-3">
              <svg class="w-6 h-6 text-primary-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
              </svg>
            </div>
            <h3 class="text-sm font-semibold text-gray-900 mb-1">Set Your First Goal</h3>
            <p class="text-xs text-gray-500">
              Track your financial goals and life events
            </p>
          </div>
        </DashboardCard>

        <!-- Other Areas to Consider Card -->
        <div class="bg-white rounded-lg border border-gray-200 p-6">
          <AreasToConsiderCard :limit="5" />
        </div>

        <!-- UK Taxes card removed — accessible via /uk-taxes route and admin panel -->
      </div>
    </div>
  </AppLayout>
</template>

<script>
import { mapGetters, mapState, mapActions } from 'vuex';
import AppLayout from '@/layouts/AppLayout.vue';
import DashboardCard from '@/components/Dashboard/DashboardCard.vue';
import GoalsProjectionChartDashboard from '@/components/Dashboard/GoalsProjectionChartDashboard.vue';
import AreasToConsiderCard from '@/components/Dashboard/AreasToConsiderCard.vue';
import AreasToCompleteCard from '@/components/Dashboard/AreasToCompleteCard.vue';
import { currencyMixin } from '@/mixins/currencyMixin';
import { ASSET_COLORS, TEXT_COLORS } from '@/constants/designSystem';
import userProfileService from '@/services/userProfileService';

export default {
  name: 'Dashboard',

  components: {
    AppLayout,
    DashboardCard,
    GoalsProjectionChartDashboard,
    AreasToConsiderCard,
    AreasToCompleteCard,
  },

  mixins: [currencyMixin],

  data() {
    return {
      loading: {
        netWorth: true,
        retirement: true,
        estate: true,
        investment: true,
        protection: true,
        goals: true,
        taxAllowances: true,
      },
      errors: {
        protection: null,
        estate: null,
      },
      dataLoaded: false,
      mfaBannerDismissed: localStorage.getItem('mfaBannerDismissed') === 'true',
      financialCommitmentsData: null,
      willSelection: null,
    };
  },

  computed: {
    ...mapGetters('auth', ['isAdmin', 'currentUser']),

    hasAreasToComplete() {
      const skippedSteps = this.currentUser?.onboarding_skipped_steps || [];
      return skippedSteps.length > 0;
    },

    // Check if the user is currently retired
    isRetired() {
      return this.currentUser?.employment_status === 'retired';
    },

    // Dynamic title for retirement card
    retirementCardTitle() {
      return this.isRetired ? 'Retirement Income' : 'Retirement';
    },

    showMFABanner() {
      const user = this.currentUser;
      if (!user) return false;
      if (user.is_preview_user) return false;
      if (this.mfaBannerDismissed) return false;
      return user.mfa_enabled !== true;
    },

    // Net Worth data
    ...mapGetters('netWorth', {
      netWorthValue: 'netWorth',
      netWorthAssets: 'totalAssets',
      netWorthLiabilities: 'totalLiabilities',
      netWorthOverview: 'overview',
    }),

    netWorthData() {
      return {
        netWorth: this.netWorthValue || 0,
        totalAssets: this.netWorthAssets || 0,
        totalLiabilities: this.netWorthLiabilities || 0,
      };
    },

    // Breakdown of assets and liabilities by category
    netWorthBreakdown() {
      const overview = this.netWorthOverview;
      // Filter out categories with zero values
      const assets = {};
      const liabilities = {};

      if (overview.breakdown) {
        Object.entries(overview.breakdown).forEach(([key, value]) => {
          if (value > 0) {
            assets[key] = value;
          }
        });
      }

      if (overview.liabilitiesBreakdown) {
        Object.entries(overview.liabilitiesBreakdown).forEach(([key, value]) => {
          if (value > 0) {
            liabilities[key] = value;
          }
        });
      }

      return { assets, liabilities };
    },

    hasNetWorthData() {
      return this.netWorthData.totalAssets > 0 || this.netWorthData.totalLiabilities > 0;
    },

    // Net Worth donut chart data
    netWorthChartCategories() {
      const LIABILITY_COLORS = {
        mortgages: '#B91C1C',
        loans: '#991B1B',
        credit_cards: '#DC2626',
        other: '#7F1D1D',
      };

      const categories = [];

      // Asset categories
      Object.entries(this.netWorthBreakdown.assets).forEach(([key, value]) => {
        if (value > 0) {
          categories.push({
            label: this.formatAssetCategory(key),
            value,
            color: ASSET_COLORS[key] || '#94A3B8',
          });
        }
      });

      // Liability categories
      Object.entries(this.netWorthBreakdown.liabilities).forEach(([key, value]) => {
        if (value > 0) {
          categories.push({
            label: this.formatLiabilityCategory(key),
            value,
            color: LIABILITY_COLORS[key] || '#B91C1C',
          });
        }
      });

      return categories;
    },

    netWorthChartSeries() {
      return this.netWorthChartCategories.map(c => c.value);
    },

    netWorthChartLabels() {
      return this.netWorthChartCategories.map(c => c.label);
    },

    netWorthChartColors() {
      return this.netWorthChartCategories.map(c => c.color);
    },

    netWorthChartKey() {
      const total = this.netWorthChartSeries.reduce((a, b) => a + b, 0);
      return `nw-donut-${this.netWorthChartSeries.length}-${Math.round(total)}`;
    },

    netWorthChartOptions() {
      const netWorth = this.netWorthData.netWorth;
      const vm = this;

      return {
        chart: {
          type: 'donut',
          fontFamily: 'Inter, system-ui, sans-serif',
        },
        labels: this.netWorthChartLabels,
        colors: this.netWorthChartColors,
        legend: { show: false },
        dataLabels: { enabled: false },
        plotOptions: {
          pie: {
            donut: {
              size: '65%',
              labels: {
                show: true,
                name: {
                  show: false,
                },
                value: {
                  show: true,
                  fontSize: '16px',
                  fontWeight: 700,
                  color: netWorth >= 0 ? '#16A34A' : '#DC2626',
                  offsetY: 24,
                  formatter: () => vm.formatCurrency(netWorth),
                },
                total: {
                  show: true,
                  showAlways: true,
                  label: '',
                  fontSize: '16px',
                  fontWeight: 700,
                  color: netWorth >= 0 ? '#16A34A' : '#DC2626',
                  formatter: () => vm.formatCurrency(netWorth),
                },
              },
            },
          },
        },
        tooltip: {
          y: {
            formatter: (val) => {
              const total = vm.netWorthChartSeries.reduce((a, b) => a + b, 0);
              const percent = total > 0 ? ((val / total) * 100).toFixed(1) : 0;
              return `${vm.formatCurrency(val)} (${percent}%)`;
            },
          },
        },
        responsive: [
          {
            breakpoint: 768,
            options: {
              chart: { height: 240 },
            },
          },
        ],
      };
    },

    // Retirement data - using real API data
    ...mapState('retirement', ['dcPensions', 'dbPensions', 'statePension', 'profile', 'requiredCapital', 'analysis', 'annualAllowance']),
    ...mapGetters('retirement', ['totalPensionWealth', 'yearsToRetirement', 'projectedIncome']),

    retirementData() {
      // Use real data from API - NOT made up calculations
      const requiredCapital = this.requiredCapital || {};

      // Use analysis projected income if available
      let projected = this.projectedIncome || 0;

      // If analysis hasn't run (no retirement profile) but user has DB/State pensions,
      // calculate guaranteed income directly from pension records
      if (projected === 0 && (this.dbPensions?.length > 0 || this.statePension)) {
        const dbIncome = (this.dbPensions || []).reduce((sum, p) => sum + parseFloat(p.accrued_annual_pension || 0), 0);
        const stateIncome = parseFloat(this.statePension?.annual_amount || 0);
        projected = dbIncome + stateIncome;
      }

      return {
        projectedIncome: projected,
        targetIncome: requiredCapital.required_income || 0,
        projectedCapital: this.totalPensionWealth || 0,
        capitalRequired: requiredCapital.required_capital_at_retirement || 0,
        retirementAge: this.profile?.target_retirement_age || null,
        yearsToRetirement: this.yearsToRetirement || null,
      };
    },

    hasRetirementData() {
      return (this.dcPensions && this.dcPensions.length > 0) ||
             (this.dbPensions && this.dbPensions.length > 0) ||
             !!this.statePension;
    },

    // Retired user income breakdown - calculates actual income from pension sources
    retiredIncomeData() {
      // Pension Drawdown: DC pension wealth × 4% safe withdrawal rate
      const dcWealth = this.totalPensionWealth || 0;
      const pensionDrawdown = dcWealth * 0.04;

      // DB Pension: Sum of accrued annual pension from all DB schemes
      const dbPensionIncome = (this.dbPensions || []).reduce((sum, pension) => {
        return sum + parseFloat(pension.accrued_annual_pension || 0);
      }, 0);

      // State Pension: Use configured amount or UK default (£11,502 for 2024/25)
      const statePensionIncome = parseFloat(this.statePension?.annual_amount || 0) || 11502;

      // Total retirement income
      const totalIncome = pensionDrawdown + dbPensionIncome + statePensionIncome;

      return {
        pensionDrawdown,
        dbPensionIncome,
        statePensionIncome,
        totalIncome,
      };
    },

    // Investment & Savings data
    ...mapState('investment', { investmentAccounts: 'accounts', investmentAnalysis: 'analysis' }),
    ...mapGetters('investment', ['totalPortfolioValue']),
    ...mapState('savings', ['expenditureProfile', 'accounts', 'isaAllowance']),

    // Investment accounts list (for line items)
    investmentAccountsList() {
      return this.investmentAccounts || [];
    },

    // Cash/savings accounts list (for line items)
    cashAccountsList() {
      return this.accounts || [];
    },

    investmentData() {
      // Total investments from investment accounts (adjusted for ownership)
      const totalInvestments = this.investmentAccountsList.reduce((sum, account) => {
        return sum + this.ownershipValue(account, 'current_value');
      }, 0);

      // Total cash from savings accounts (adjusted for ownership)
      const totalCash = this.cashAccountsList.reduce((sum, account) => {
        return sum + this.ownershipValue(account, 'current_balance');
      }, 0);

      return {
        totalInvestments,
        totalCash,
        totalValue: totalInvestments + totalCash,
        accountsCount: (this.investmentAccountsList?.length || 0) + (this.cashAccountsList?.length || 0),
      };
    },

    hasInvestmentData() {
      return (this.investmentAccountsList && this.investmentAccountsList.length > 0) ||
             (this.cashAccountsList && this.cashAccountsList.length > 0);
    },

    // Protection data
    ...mapGetters('protection', {
      protectionTotalCoverage: 'totalCoverage',
      protectionTotalPremium: 'totalPremium',
      protectionLifePolicies: 'lifePolicies',
      protectionCriticalIllnessPolicies: 'criticalIllnessPolicies',
      protectionIncomeProtectionPolicies: 'incomeProtectionPolicies',
    }),

    protectionData() {
      return {
        totalCoverage: this.protectionTotalCoverage || 0,
        premiumTotal: this.protectionTotalPremium || 0, // Already monthly from store getter
      };
    },

    hasProtectionData() {
      return (this.protectionLifePolicies?.length || 0) +
             (this.protectionCriticalIllnessPolicies?.length || 0) +
             (this.protectionIncomeProtectionPolicies?.length || 0) > 0;
    },

    // Goals data
    ...mapState('goals', ['dashboardOverview', 'projectionData']),
    ...mapGetters('goals', ['dashboardData']),

    // Estate data
    ...mapGetters('estate', ['ihtLiability', 'taxableEstate']),
    ...mapState('estate', { willInfo: 'willInfo' }),
    ...mapState('trusts', { trusts: 'trusts' }),

    estateData() {
      return {
        taxableEstate: this.taxableEstate || 0,
        ihtLiability: this.ihtLiability || 0,
      };
    },

    trustsList() {
      return this.trusts || [];
    },

    hasEstateData() {
      return this.taxableEstate > 0 || this.ihtLiability > 0;
    },

    willAnswered() {
      return this.willInfo?.will_answered === true;
    },

    goalsData() {
      const data = this.dashboardData || {};
      return {
        hasGoals: data.has_goals || false,
        totalGoals: data.total_goals || 0,
        onTrackCount: data.on_track_count || 0,
        totalTarget: data.total_target || 0,
        totalCurrent: data.total_current || 0,
        overallProgress: Math.round(data.overall_progress || 0),
        lifeEventsCount: data.life_events_count || 0,
        hasProjection: !!this.projectionData,
      };
    },

    hasGoalsData() {
      // Always show goals card - it has empty state
      return true;
    },

    // ISA Allowance computed — uses server-calculated tracking data
    isaAllowanceData() {
      if (!this.isaAllowance) return null;
      const totalAllowance = this.isaAllowance.total_allowance || 20000;
      const cashUsed = parseFloat(this.isaAllowance.cash_isa_used || 0);
      const ssUsed = parseFloat(this.isaAllowance.stocks_shares_isa_used || 0);
      const totalUsed = parseFloat(this.isaAllowance.total_used || 0) || (cashUsed + ssUsed);
      const remaining = totalAllowance - totalUsed;
      const percentUsed = this.isaAllowance.percentage_used || (totalAllowance > 0 ? (totalUsed / totalAllowance) * 100 : 0);

      return {
        totalAllowance,
        cashUsed,
        ssUsed,
        totalUsed,
        remaining,
        percentUsed,
      };
    },

    // Pension Annual Allowance computed
    pensionAllowanceData() {
      if (this.annualAllowance) {
        const available = this.annualAllowance.available_allowance || 60000;
        const contributions = this.annualAllowance.total_contributions || 0;
        const remaining = this.annualAllowance.remaining_allowance || (available - contributions);
        const percentUsed = available > 0 ? (contributions / available) * 100 : 0;

        return {
          availableAllowance: available,
          totalContributions: contributions,
          remaining,
          percentUsed,
          isTapered: this.annualAllowance.is_tapered || false,
          mpaaTriggered: false,
          hasExcess: this.annualAllowance.has_excess || false,
        };
      }

      // Fallback: calculate from DC pensions if annual allowance API didn't return data
      if (this.dcPensions && this.dcPensions.length > 0) {
        const totalContributions = this.dcPensions.reduce((sum, p) => {
          const employee = parseFloat(p.employee_contribution_amount || 0);
          const employer = parseFloat(p.employer_contribution_amount || 0);
          // Annualise monthly contributions
          const freq = (p.contribution_frequency || 'monthly').toLowerCase();
          const multiplier = freq === 'annual' || freq === 'annually' ? 1 : 12;
          return sum + (employee + employer) * multiplier;
        }, 0);

        if (totalContributions > 0) {
          const available = 60000;
          const remaining = available - totalContributions;
          const percentUsed = (totalContributions / available) * 100;

          return {
            availableAllowance: available,
            totalContributions,
            remaining,
            percentUsed,
            isTapered: false,
            mpaaTriggered: false,
            hasExcess: totalContributions > available,
          };
        }
      }

      return null;
    },

    hasAllowancesData() {
      return !!this.isaAllowanceData || !!this.pensionAllowanceData;
    },
  },

  methods: {
    ...mapActions('goals', ['fetchDashboardOverview', 'fetchProjection']),
    ...mapActions('retirement', ['fetchRequiredCapital']),

    // Format asset category names for display
    formatAssetCategory(category) {
      const categoryLabels = {
        pensions: 'Pensions',
        property: 'Property',
        investments: 'Investments',
        cash: 'Cash & Savings',
        business: 'Business Interests',
        chattels: 'Personal Valuables',
      };
      return categoryLabels[category] || category.charAt(0).toUpperCase() + category.slice(1).replace(/_/g, ' ');
    },

    // Format liability category names for display
    formatLiabilityCategory(category) {
      const categoryLabels = {
        mortgages: 'Mortgages',
        loans: 'Loans',
        credit_cards: 'Credit Cards',
        other: 'Other Liabilities',
      };
      return categoryLabels[category] || category.charAt(0).toUpperCase() + category.slice(1).replace(/_/g, ' ');
    },

    // Format cash account name from institution and account type
    ownershipValue(account, valueField) {
      const value = parseFloat(account[valueField] || 0);
      if (account.ownership_type === 'joint' || account.ownership_type === 'tenants_in_common') {
        const percentage = account.ownership_percentage ?? 50;
        return value * (percentage / 100);
      }
      return value;
    },

    formatCashAccountName(account) {
      const isJoint = account.ownership_type === 'joint' || account.ownership_type === 'tenants_in_common';
      const jointLabel = isJoint ? ' (Joint)' : '';
      const name = account.account_name || '';
      const provider = account.institution || account.provider_name || '';

      if (name) return name + jointLabel;
      if (provider) return provider + jointLabel;
      return 'Cash Account' + jointLabel;
    },

    // Format protection policy name from provider and policy type
    formatPolicyName(policy, fallbackType) {
      const provider = policy.provider || policy.provider_name || '';
      const policyType = policy.policy_type || '';

      // Format policy type for display
      const typeLabels = {
        level_term: 'Level Term',
        decreasing_term: 'Decreasing Term',
        whole_of_life: 'Whole of Life',
        family_income_benefit: 'Family Income Benefit',
      };
      const formattedType = typeLabels[policyType] || policyType.replace(/_/g, ' ');

      if (provider && formattedType) {
        return `${provider} ${formattedType}`;
      }
      return provider || formattedType || fallbackType;
    },

    allowanceBarClass(percentUsed, isOverLimit) {
      if (isOverLimit || percentUsed >= 95) return 'bg-red-500';
      if (percentUsed >= 75) return 'bg-blue-500';
      return 'bg-green-500';
    },

    dismissMFABanner() {
      this.mfaBannerDismissed = true;
      localStorage.setItem('mfaBannerDismissed', 'true');
    },

    navigateTo(path) {
      if (path) {
        this.$router.push(path);
      }
    },

    async selectWill(hasWill) {
      this.willSelection = hasWill;
      try {
        await this.$store.dispatch('estate/saveWill', { has_will: hasWill });
      } catch (error) {
        console.error('Failed to save will information:', error);
      }
    },

    async loadFinancialCommitments() {
      try {
        const response = await userProfileService.getFinancialCommitments();
        if (response.success) {
          this.financialCommitmentsData = response.data;
        }
      } catch (error) {
        console.error('Failed to load financial commitments:', error);
        this.financialCommitmentsData = null;
      }
    },

    async loadAllData() {
      const user = this.currentUser;
      const isMarried = user && user.marital_status === 'married';
      const estateCalculationAction = isMarried
        ? 'estate/calculateSecondDeathIHTPlanning'
        : 'estate/calculateIHT';

      const moduleLoaders = [
        { name: 'netWorth', action: 'netWorth/fetchOverview' },
        { name: 'protection', action: 'protection/fetchProtectionData' },
        { name: 'estate', action: 'estate/fetchEstateData' },
        { name: 'estate', action: estateCalculationAction, payload: {} },
        { name: 'retirement', action: 'trusts/fetchTrusts' },
        { name: 'investment', action: 'userProfile/fetchProfile' },
        { name: 'retirement', action: 'retirement/fetchRetirementData' },
        { name: 'retirement', action: 'retirement/fetchRequiredCapital' },
        { name: 'retirement', action: 'retirement/analyseRetirement' },
        { name: 'investment', action: 'investment/fetchInvestmentData' },
        { name: 'investment', action: 'investment/analyseInvestment' },
        { name: 'taxAllowances', action: 'savings/fetchSavingsData' },
        { name: 'taxAllowances', action: 'retirement/fetchAnnualAllowance', payload: '2025/26' },
        { name: 'goals', action: 'goals/fetchDashboardOverview' },
      ];

      Object.keys(this.loading).forEach(key => {
        this.loading[key] = true;
      });
      Object.keys(this.errors).forEach(key => {
        this.errors[key] = null;
      });

      // Load financial commitments
      this.loadFinancialCommitments();

      const moduleActionCounts = {};
      moduleLoaders.forEach(loader => {
        moduleActionCounts[loader.name] = (moduleActionCounts[loader.name] || 0) + 1;
      });

      const moduleCompletedCounts = {};

      const promises = moduleLoaders.map(loader =>
        this.$store.dispatch(loader.action, loader.payload)
          .then(() => ({ module: loader.name, success: true }))
          .catch(error => ({
            module: loader.name,
            success: false,
            error: error.response?.data?.message || error.message || 'Unknown error'
          }))
      );

      const results = await Promise.allSettled(promises);

      results.forEach(result => {
        if (result.status === 'fulfilled') {
          const { module, success, error } = result.value;
          moduleCompletedCounts[module] = (moduleCompletedCounts[module] || 0) + 1;

          if (!success && this.loading.hasOwnProperty(module)) {
            this.errors[module] = error;
          }

          if (this.loading.hasOwnProperty(module) &&
              moduleCompletedCounts[module] >= moduleActionCounts[module]) {
            this.loading[module] = false;
          }
        } else {
          console.error('Failed to load module:', result.reason);
        }
      });

      // Also try to fetch projection data for goals chart
      try {
        await this.fetchProjection();
      } catch (e) {
        // Projection is optional, don't block
      }
    },
  },

  watch: {
    currentUser: {
      immediate: true,
      handler(user) {
        if (user && !this.dataLoaded) {
          this.dataLoaded = true;
          this.loadAllData();
        }
      }
    }
  },
};
</script>

<style scoped>
/* Card styling inherited from DashboardCard component */
</style>
