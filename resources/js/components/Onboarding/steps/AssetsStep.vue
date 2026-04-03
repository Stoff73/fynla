<template>
  <OnboardingStep
    :title="stepTitle"
    :description="stepDescription"
    :can-go-back="true"
    :can-skip="true"
    :loading="loading"
    :error="error"
    @next="handleNext"
    @back="handleBack"
    @skip="handleSkip"
  >
    <div class="space-y-6">
      <!-- Tabs for different asset types (hidden when single tab) -->
      <div v-if="assetTabs.length > 1" class="border-b border-light-gray">
        <nav class="-mb-px flex space-x-8" aria-label="Asset types">
          <button
            v-for="tab in assetTabs"
            :key="tab.id"
            @click="activeTab = tab.id"
            :class="[
              activeTab === tab.id
                ? 'border-raspberry-500 text-raspberry-500'
                : 'border-transparent text-neutral-500 hover:text-horizon-500 hover:border-horizon-300',
              'whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm'
            ]"
          >
            {{ tab.name }}
            <span v-if="tab.count > 0" class="ml-2 py-0.5 px-2 rounded-full text-xs bg-savannah-100">
              {{ tab.count }}
            </span>
          </button>
        </nav>
      </div>

      <!-- Retirement Tab -->
      <div v-show="activeTab === 'retirement'" class="space-y-4">
        <!-- Pensions Grid -->
        <div v-if="pensions.dc.length > 0 || pensions.db.length > 0 || pensions.state" class="pensions-grid">
          <!-- DC Pensions -->
          <div
            v-for="pension in pensions.dc"
            :key="'dc-' + pension.id"
            class="pension-card"
            @click="openPensionForm('dc', pension)"
          >
            <div class="card-header">
              <span class="badge badge-dc">
                {{ formatDCPensionType(pension.pension_type || pension.scheme_type) }}
              </span>
            </div>

            <div class="card-content">
              <h4 class="pension-scheme">{{ pension.scheme_name || 'Defined Contribution' }}</h4>
              <p class="pension-provider-text">{{ pension.provider || '' }}</p>

              <div class="pension-details">
                <div class="value-rows">
                  <div class="detail-row">
                    <span class="detail-label">Current Value</span>
                    <span class="detail-value">{{ formatCurrency(pension.current_fund_value) }}</span>
                  </div>

                  <div class="detail-row">
                    <span class="detail-label">Retirement Age</span>
                    <span class="detail-value">{{ pension.retirement_age || currentUser?.target_retirement_age || 67 }}</span>
                  </div>

                  <div class="detail-row">
                    <span class="detail-label">Monthly Contribution</span>
                    <span class="detail-value">{{ formatCurrency(getPensionMonthlyContribution(pension)) }}</span>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- DB Pensions -->
          <div
            v-for="pension in pensions.db"
            :key="'db-' + pension.id"
            class="pension-card"
            @click="openPensionForm('db', pension)"
          >
            <div class="card-header">
              <span class="badge badge-db">
                {{ formatDBPensionType(pension.scheme_type) }}
              </span>
            </div>

            <div class="card-content">
              <h4 class="pension-scheme">{{ pension.scheme_name || 'Defined Benefit' }}</h4>
              <p class="pension-provider-text">{{ pension.provider || '' }}</p>

              <div class="pension-details">
                <div class="value-rows">
                  <div class="detail-row">
                    <span class="detail-label">Annual Income</span>
                    <span class="detail-value">{{ formatCurrency(pension.annual_income) }}<span class="text-xs text-neutral-500">/yr</span></span>
                  </div>

                  <div class="detail-row">
                    <span class="detail-label">Payment Start Age</span>
                    <span class="detail-value">{{ pension.payment_start_age || 67 }}</span>
                  </div>

                  <div class="detail-row">
                    <span class="detail-label">Lump Sum</span>
                    <span class="detail-value">{{ formatCurrency(pension.lump_sum_entitlement || 0) }}</span>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- State Pension -->
          <div
            v-if="pensions.state"
            class="pension-card"
            @click="openPensionForm('state', pensions.state)"
          >
            <div class="card-header">
              <span class="badge badge-state">
                State Pension
              </span>
            </div>

            <div class="card-content">
              <h4 class="pension-scheme">UK State Pension</h4>
              <p class="pension-provider-text">State Retirement Pension</p>

              <div class="pension-details">
                <div class="value-rows">
                  <div class="detail-row">
                    <span class="detail-label">Forecast</span>
                    <span class="detail-value">{{ formatCurrency(pensions.state.state_pension_forecast_annual) }}<span class="text-xs text-neutral-500">/yr</span></span>
                  </div>

                  <div class="detail-row">
                    <span class="detail-label">National Insurance Years</span>
                    <span class="detail-value">{{ pensions.state.ni_years_completed || 0 }} / 35</span>
                  </div>

                  <div class="detail-row">
                    <span class="detail-label">Payment Age</span>
                    <span class="detail-value">{{ pensions.state.state_pension_age || 67 }}</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Add Pension + Upload (hidden when pension form is open) -->
        <div v-if="!showPensionForm" class="flex flex-wrap gap-2">
          <button
            type="button"
            class="inline-flex items-center px-4 py-2 bg-horizon-500 text-white rounded-button hover:bg-horizon-600 transition-colors text-sm font-medium"
            @click="showPensionTypeSelector = !showPensionTypeSelector"
          >
            + Add Pension
          </button>
          <button
            v-preview-disabled="'upload'"
            type="button"
            class="inline-flex items-center px-4 py-2 bg-light-blue-200 text-horizon-500 rounded-button hover:bg-light-blue-300 transition-colors text-sm font-medium"
            @click="openUploadModal('pension_statement')"
          >
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
            </svg>
            Upload Statement
          </button>
        </div>

        <!-- Pension Type Selector Cards -->
        <div v-if="showPensionTypeSelector && !showPensionForm" class="grid grid-cols-1 sm:grid-cols-3 gap-3">
          <button type="button" class="border border-light-gray rounded-lg p-4 bg-white hover:border-horizon-300 hover:bg-eggshell-500 transition-colors text-left cursor-pointer" @click="showPensionTypeSelector = false; openPensionForm('dc')">
            <h5 class="text-sm font-semibold text-horizon-500 mb-1">Money Purchase Pension</h5>
            <p class="text-xs text-neutral-500 mb-2">Defined Contribution — your pot grows based on contributions and investment returns.</p>
            <span class="text-sm font-medium text-horizon-500">Add pension &rarr;</span>
          </button>
          <button type="button" class="border border-light-gray rounded-lg p-4 bg-white hover:border-horizon-300 hover:bg-eggshell-500 transition-colors text-left cursor-pointer" @click="showPensionTypeSelector = false; openPensionForm('db')">
            <h5 class="text-sm font-semibold text-horizon-500 mb-1">Final Salary Pension</h5>
            <p class="text-xs text-neutral-500 mb-2">Defined Benefit — pays a guaranteed income based on your salary and years of service.</p>
            <span class="text-sm font-medium text-horizon-500">Add pension &rarr;</span>
          </button>
          <button type="button" class="border border-light-gray rounded-lg p-4 bg-white hover:border-horizon-300 hover:bg-eggshell-500 transition-colors text-left cursor-pointer" @click="showPensionTypeSelector = false; openPensionForm('state')">
            <h5 class="text-sm font-semibold text-horizon-500 mb-1">State Pension</h5>
            <p class="text-xs text-neutral-500 mb-2">UK State Pension — based on your National Insurance record and qualifying years.</p>
            <span class="text-sm font-medium text-horizon-500">Add pension &rarr;</span>
          </button>
        </div>


      </div>

      <!-- Properties Tab -->
      <div v-show="activeTab === 'properties'" class="space-y-4">
        <!-- Added Properties List -->
        <div v-if="properties.length > 0" class="space-y-3">
          <h4 class="text-body font-medium text-horizon-500">
            Properties ({{ properties.length }})
          </h4>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <PropertyCard
              v-for="property in properties"
              :key="property.id"
              :property="property"
              @select-property="editProperty"
            />
          </div>
        </div>

        <!-- Add Property Button -->
        <button
          type="button"
          class="inline-flex items-center px-4 py-2 bg-horizon-500 text-white rounded-button hover:bg-horizon-600 transition-colors text-sm font-medium w-full md:w-auto justify-center mt-4"
          @click="showPropertyForm = true; $nextTick(() => window.scrollTo({ top: 0, behavior: 'smooth' }))"
        >
          + Add Property
        </button>


      </div>

      <!-- Investments Tab -->
      <div v-show="activeTab === 'investments'" class="space-y-4">
        <!-- Investments Grid -->
        <div v-if="investments.length > 0" class="accounts-grid">
          <div
            v-for="investment in investments"
            :key="investment.id"
            class="account-card"
            @click="editInvestment(investment)"
          >
            <div class="card-header">
              <span
                :class="getOwnershipBadgeClass(investment.ownership_type)"
                class="ownership-badge"
              >
                {{ formatOwnershipType(investment.ownership_type) }}
              </span>
              <span
                class="badge"
                :class="getInvestmentTypeBadgeClass(investment.account_type)"
              >
                {{ formatInvestmentAccountType(investment.account_type) }}
              </span>
            </div>

            <div class="card-content">
              <h4 class="account-institution">{{ investment.provider }}</h4>
              <p class="account-type">{{ investment.account_name || investment.platform || '' }}</p>

              <div class="account-details">
                <!-- Joint account: current_value IS the full value -->
                <div v-if="investment.ownership_type === 'joint'">
                  <div class="detail-row">
                    <span class="detail-label">Full Value</span>
                    <span class="detail-value">{{ formatCurrency(investment.current_value) }}</span>
                  </div>
                  <div class="detail-row">
                    <span class="detail-label">Your Share ({{ investment.ownership_percentage || 50 }}%)</span>
                    <span class="detail-value text-purple-600">{{ formatCurrency(investment.current_value * ((investment.ownership_percentage || 50) / 100)) }}</span>
                  </div>
                </div>

                <!-- Individual account shows just current value -->
                <div v-else class="detail-row">
                  <span class="detail-label">Current Value</span>
                  <span class="detail-value">{{ formatCurrency(investment.current_value) }}</span>
                </div>

                <div class="detail-row">
                  <span class="detail-label">Holdings</span>
                  <span class="detail-value">{{ investment.holdings?.length || 0 }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Add Investment Button -->
        <div class="flex flex-wrap gap-2 mt-4">
          <button
            type="button"
            class="inline-flex items-center px-4 py-2 bg-horizon-500 text-white rounded-button hover:bg-horizon-600 transition-colors text-sm font-medium"
            @click="showInvestmentForm = true; $nextTick(() => window.scrollTo({ top: 0, behavior: 'smooth' }))"
          >
            + Add Investment Account
          </button>
          <button
            v-preview-disabled="'upload'"
            type="button"
            class="inline-flex items-center px-4 py-2 bg-light-blue-200 text-horizon-500 rounded-button hover:bg-light-blue-300 transition-colors text-sm font-medium"
            @click="openUploadModal('investment_statement')"
          >
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
            </svg>
            Upload Statement
          </button>
        </div>


      </div>

      <!-- Cash Tab -->
      <div v-show="activeTab === 'cash'" class="space-y-4">
        <!-- Cash Accounts Grid -->
        <div v-if="savingsAccounts.length > 0" class="accounts-grid">
          <div
            v-for="savings in savingsAccounts"
            :key="savings.id"
            class="account-card"
            @click="editSavings(savings)"
          >
            <div class="card-header">
              <span
                :class="getOwnershipBadgeClass(savings.ownership_type)"
                class="ownership-badge"
              >
                {{ formatOwnershipType(savings.ownership_type) }}
              </span>
              <div class="badge-group">
                <span v-if="savings.is_emergency_fund" class="badge badge-emergency">
                  Emergency Fund
                </span>
                <span v-if="savings.is_isa" class="badge badge-isa">
                  ISA
                </span>
              </div>
            </div>

            <div class="card-content">
              <h4 class="account-institution">{{ savings.institution }}</h4>
              <p class="account-type">{{ formatSavingsAccountType(savings.account_type) }}</p>

              <div class="account-details">
                <div class="detail-row">
                  <span class="detail-label">{{ savings.ownership_type === 'joint' ? 'Full Balance' : 'Balance' }}</span>
                  <span class="detail-value">{{ formatCurrency(getFullSavingsBalance(savings)) }}</span>
                </div>

                <div v-if="savings.ownership_type === 'joint'" class="detail-row">
                  <span class="detail-label">Your Share ({{ savings.ownership_percentage }}%)</span>
                  <span class="detail-value text-purple-600">{{ formatCurrency(savings.current_balance) }}</span>
                </div>

                <div v-if="savings.interest_rate > 0" class="detail-row">
                  <span class="detail-label">Interest Rate</span>
                  <span class="detail-value interest">{{ formatInterestRate(savings.interest_rate) }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Add Account Button -->
        <div class="flex flex-wrap gap-2 mt-4">
          <button
            type="button"
            class="inline-flex items-center px-4 py-2 bg-horizon-500 text-white rounded-button hover:bg-horizon-600 transition-colors text-sm font-medium"
            @click="showSavingsForm = true; $nextTick(() => window.scrollTo({ top: 0, behavior: 'smooth' }))"
          >
            + Add Account
          </button>
          <button
            v-preview-disabled="'upload'"
            type="button"
            class="inline-flex items-center px-4 py-2 bg-light-blue-200 text-horizon-500 rounded-button hover:bg-light-blue-300 transition-colors text-sm font-medium"
            @click="openUploadModal('savings_statement')"
          >
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
            </svg>
            Upload Statement
          </button>
        </div>

        <!-- Empty state with helpful suggestions -->
        <div v-if="savingsAccounts.length === 0" class="mt-4 p-4 bg-savannah-100 rounded-lg border border-light-gray">
          <p class="text-sm font-semibold text-horizon-500 mb-2">Common accounts to consider adding:</p>
          <ul class="text-sm text-neutral-500 space-y-1.5 mb-3">
            <li class="flex items-center gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-spring-500 flex-shrink-0"></span>
              Emergency fund — aim for 3-6 months of expenses
            </li>
            <li class="flex items-center gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-violet-500 flex-shrink-0"></span>
              Cash ISA — tax-free savings up to £20,000 per year
            </li>
            <li class="flex items-center gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-raspberry-500 flex-shrink-0"></span>
              Current or savings accounts
            </li>
          </ul>
          <p class="text-xs text-neutral-500 italic">You can skip this step and add accounts later from your dashboard.</p>
        </div>

      </div>

    </div>

    <!-- Inline Property Form (replaces cards when open) -->
    <PropertyForm
      v-if="showPropertyForm"
      context="onboarding"
      :property="editingProperty"
      :user-address="userAddress"
      @close="closePropertyForm"
      @save="handlePropertySaved"
    />

    <!-- Inline Investment Form -->
    <AccountForm
      v-if="showInvestmentForm"
      context="onboarding"
      :show="true"
      :account="editingInvestment"
      :is-onboarding="true"
      @close="closeInvestmentForm"
      @save="handleInvestmentSaved"
    />

    <!-- Inline Savings Form -->
    <SaveAccountModal
      v-if="showSavingsForm"
      context="onboarding"
      :account="editingSavings"
      @close="closeSavingsForm"
      @save="handleSavingsSaved"
    />

    <!-- Inline Pension Forms -->
    <DCPensionForm
      v-if="showPensionForm && pensionFormType === 'dc'"
      context="onboarding"
      :pension="editingPension"
      :is-onboarding="true"
      @close="closePensionForm"
      @save="handlePensionSaved"
    />

    <DBPensionForm
      v-if="showPensionForm && pensionFormType === 'db'"
      context="onboarding"
      :pension="editingPension"
      @close="closePensionForm"
      @save="handlePensionSaved"
    />

    <StatePensionForm
      v-if="showPensionForm && pensionFormType === 'state'"
      context="onboarding"
      :state-pension="editingPension"
      @close="closePensionForm"
      @save="handlePensionSaved"
    />

    <!-- Tab navigation removed — Continue button cycles through tabs -->

    <!-- Document Upload Modal -->
    <DocumentUploadModal
      v-if="showUploadModal"
      :document-type="uploadDocumentType"
      @close="closeUploadModal"
      @saved="handleDocumentSaved"
      @manual-entry="closeUploadModal"
    />

    <!-- Skip Assets Modal -->
    <div v-if="showAssetSkipModal" class="fixed inset-0 z-50 overflow-y-auto">
      <div class="flex min-h-screen items-center justify-center p-4">
        <div class="fixed inset-0 bg-black/50 transition-opacity" @click="showAssetSkipModal = false"></div>
        <div class="relative bg-white rounded-lg shadow-xl max-w-lg w-full p-6">
          <div class="flex items-start gap-3 mb-4">
            <div class="w-10 h-10 rounded-full bg-violet-100 flex items-center justify-center flex-shrink-0">
              <svg class="w-5 h-5 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z" />
              </svg>
            </div>
            <div>
              <h3 class="text-lg font-bold text-horizon-500">Skip Assets & Wealth?</h3>
              <p class="text-sm text-neutral-500 mt-1">You haven't completed all the information for Assets & Wealth. You can always add this information later from your dashboard.</p>
            </div>
          </div>
          <div class="flex justify-end gap-3">
            <button type="button" class="px-4 py-2 text-sm font-medium text-neutral-500 hover:text-horizon-500 transition-colors" @click="showAssetSkipModal = false">Go Back</button>
            <button type="button" class="px-4 py-2 text-sm font-medium text-white bg-raspberry-500 hover:bg-raspberry-600 rounded-button transition-colors" @click="confirmAssetSkip">Skip & Continue</button>
          </div>
        </div>
      </div>
    </div>
  </OnboardingStep>
</template>

<script>
// DEPRECATED: Will be replaced by unified form with context="onboarding". See life-stage-journey-design.md §11.7
import { ref, computed, watch, onMounted } from 'vue';
import { useStore } from 'vuex';
import OnboardingStep from '../OnboardingStep.vue';
import PropertyForm from '@/components/NetWorth/Property/PropertyForm.vue';
import PropertyCard from '@/components/NetWorth/PropertyCard.vue';
import AccountForm from '@/components/Investment/AccountForm.vue';
import SaveAccountModal from '@/components/Savings/SaveAccountModal.vue';
import DCPensionForm from '@/components/Retirement/DCPensionForm.vue';
import DBPensionForm from '@/components/Retirement/DBPensionForm.vue';
import StatePensionForm from '@/components/Retirement/StatePensionForm.vue';
import DocumentUploadModal from '@/components/Shared/DocumentUploadModal.vue';
import UsefulResources from '@/components/Onboarding/UsefulResources.vue';
import { LINKS, STEP_RESOURCES } from '@/constants/onboardingLinks';
import propertyService from '@/services/propertyService';
import investmentService from '@/services/investmentService';
import savingsService from '@/services/savingsService';
import retirementService from '@/services/retirementService';
import userProfileService from '@/services/userProfileService';
import { formatCurrency } from '@/utils/currency';
import { getCurrentTaxYear } from '@/utils/dateFormatter';

import logger from '@/utils/logger';
export default {
  name: 'AssetsStep',

  components: {
    OnboardingStep,
    PropertyForm,
    PropertyCard,
    AccountForm,
    SaveAccountModal,
    DCPensionForm,
    DBPensionForm,
    StatePensionForm,
    DocumentUploadModal,
    UsefulResources,
  },

  emits: ['next', 'back', 'skip', 'sidebar-update'],

  setup(props, { emit }) {
    const store = useStore();
    const currentTaxYear = getCurrentTaxYear();

    // Read which tabs to show from life stage config
    const assetsConfig = computed(() => store.getters['lifeStage/formFields']?.('assets'));
    const allowedTabs = computed(() => assetsConfig.value?.visibleTabs || null);
    const currentUser = computed(() => store.getters['auth/currentUser']);

    const activeTab = ref('retirement');

    // Sidebar content — defined here, wired up after refs are declared below
    const SIDEBAR_CONTENT = {
      'cash-list': {
        didYouKnow: 'A six-month emergency fund is the single most important financial protection you can have. It means a redundancy, car breakdown, or boiler failure does not derail your longer-term plans.',
        whyWeAsk: 'Knowing your savings lets us calculate exactly how many months of expenses you currently have covered, track progress towards your emergency fund target, and flag whether you are earning the best available interest rate.',
        quickStat: { value: '£20,000', label: `Your annual ISA allowance (${currentTaxYear})` },
      },
      'cash-form': {
        didYouKnow: 'The best easy access savings accounts currently pay over 4.5% AER. If your money is sitting in a current account earning 0%, moving it could earn you hundreds per year.',
        whyWeAsk: 'The institution, account type, and balance let us track your emergency fund progress, flag if you are missing ISA tax benefits, and monitor whether your rate is competitive.',
        quickStat: { value: '4.5%+', label: 'Best easy access rates available right now' },
      },
      'retirement-list': {
        didYouKnow: 'Auto-enrolment means your employer must contribute to your pension if you earn above £10,000 per year. The minimum total contribution is 8% of qualifying earnings. Opting out is almost always a mistake — you are walking away from free money.',
        whyWeAsk: 'Your pension details let us project your retirement income, assess whether you are on track, and calculate how increasing contributions now compounds into significant extra income in retirement.',
        quickStat: { value: '£60,000', label: `Annual pension allowance (${currentTaxYear})` },
      },
      'retirement-form': {
        didYouKnow: 'Every £1 of salary sacrifice into your pension saves income tax AND National Insurance. At the basic rate, that is 32p saved per £1 contributed. Your employer contributions are free money on top.',
        whyWeAsk: 'Your scheme name, provider, fund value, and contribution percentages let us calculate your projected pension pot at retirement and identify if you should increase contributions.',
        quickStat: { value: '32p', label: 'Saved per £1 via salary sacrifice at basic rate' },
      },
      'investments-list': {
        didYouKnow: 'A Stocks and Shares ISA lets you invest up to £20,000 per year with all growth and income completely free of tax — forever. Investing £200/month from age 28 at a 7% average annual return would be worth over £500,000 by age 65.',
        whyWeAsk: 'Knowing your existing investments lets us assess diversification, flag tax inefficiency, and incorporate your portfolio into net worth and retirement projections.',
        quickStat: { value: '£20,000', label: `Annual ISA allowance — all growth tax-free (${currentTaxYear})` },
      },
      'investments-form': {
        didYouKnow: 'Platform fees compound over time just like returns. A 0.5% difference in annual fees on a £100,000 portfolio costs over £50,000 over 30 years.',
        whyWeAsk: 'Your provider, account type, value, and fees let us calculate the true cost of your investments and identify where fee reductions could significantly improve long-term returns.',
        quickStat: { value: '£50,000', label: 'Cost of 0.5% extra fees on £100k over 30 years' },
      },
      'properties-list': {
        didYouKnow: 'Most homeowners overpay their mortgage by not reviewing their rate every two years. Even a 0.5% rate reduction on a £250,000 mortgage saves over £1,200 per year.',
        whyWeAsk: 'Your property and mortgage details let us calculate your equity, net worth, potential remortgage savings, and whether a decreasing term life policy should be tied to your outstanding balance.',
        quickStat: { value: '2 years', label: 'How often you should review your mortgage rate' },
      },
      'properties-form': {
        didYouKnow: 'Your main residence is exempt from Capital Gains Tax when sold. Buy-to-let properties are not — and the rate is 24% for higher-rate taxpayers. Ownership structure matters.',
        whyWeAsk: 'The address, value, ownership type, and mortgage details feed into your net worth, estate planning, protection needs calculation, and rental yield analysis.',
        quickStat: { value: '24%', label: 'Capital Gains Tax on residential property for higher-rate taxpayers' },
      },
    };

    const stepTitle = computed(() => {
      if (allowedTabs.value && allowedTabs.value.length === 1) {
        const titles = { cash: 'Bank Accounts', retirement: 'Pensions', investments: 'Investments', properties: 'Properties' };
        return titles[allowedTabs.value[0]] || 'Assets & Wealth';
      }
      return 'Assets & Wealth';
    });
    const stepDescription = computed(() => {
      if (allowedTabs.value && allowedTabs.value.length === 1) {
        const descs = { cash: 'Add your bank and savings accounts', retirement: 'Add your pension schemes so we can project your retirement income', investments: 'Add your investment accounts so we can analyse your portfolio', properties: 'Add your properties and any mortgages' };
        return descs[allowedTabs.value[0]] || 'Add your properties, investments, and savings accounts';
      }
      return 'Add your properties, investments, and savings accounts';
    });

    // Properties state
    const properties = ref([]);
    const showPropertyForm = ref(false);
    const showPensionTypeSelector = ref(false);
    const editingProperty = ref(null);

    // Investments state
    const investments = ref([]);
    const showInvestmentForm = ref(false);
    const editingInvestment = ref(null);

    // Savings state
    const savingsAccounts = ref([]);
    const showSavingsForm = ref(false);
    const editingSavings = ref(null);

    const loading = ref(false);
    const error = ref(null);
    const userAddress = ref(null);

    // Document upload state
    const showUploadModal = ref(false);
    const uploadDocumentType = ref(null);

    // Pensions state
    const pensions = ref({ dc: [], db: [], state: null });
    const showPensionForm = ref(false);
    const pensionFormType = ref(null); // 'dc', 'db', or 'state'
    const editingPension = ref(null);

    // Sidebar context tracking
    const isFormOpen = computed(() => showPropertyForm.value || showInvestmentForm.value || showSavingsForm.value || showPensionForm.value);

    const emitSidebarContent = () => {
      const suffix = isFormOpen.value ? 'form' : 'list';
      const key = `${activeTab.value}-${suffix}`;
      const content = SIDEBAR_CONTENT[key] || SIDEBAR_CONTENT[`${activeTab.value}-list`];
      if (content) {
        emit('sidebar-update', content);
      }
    };

    watch(activeTab, () => {
      // Close all open forms when switching tabs
      showPropertyForm.value = false;
      showInvestmentForm.value = false;
      showSavingsForm.value = false;
      showPensionForm.value = false;
      editingProperty.value = null;
      editingInvestment.value = null;
      editingSavings.value = null;
      editingPension.value = null;
      error.value = null;
      emitSidebarContent();
      // Scroll to top on tab change (mobile stacked layout)
      window.scrollTo({ top: 0, behavior: 'smooth' });
    });
    watch(isFormOpen, () => emitSidebarContent());

    // Tab counts
    const allTabs = [
      { id: 'retirement', name: 'Retirement' },
      { id: 'properties', name: 'Properties' },
      { id: 'investments', name: 'Investments' },
      { id: 'cash', name: 'Cash' },
    ];

    const assetTabs = computed(() => {
      const tabs = allowedTabs.value
        ? allTabs.filter(t => allowedTabs.value.includes(t.id))
        : allTabs;

      return tabs.map(t => ({
        ...t,
        count: t.id === 'retirement' ? pensions.value.dc.length + pensions.value.db.length + (pensions.value.state ? 1 : 0)
          : t.id === 'properties' ? properties.value.length
          : t.id === 'investments' ? investments.value.length
          : t.id === 'cash' ? savingsAccounts.value.length
          : 0,
      }));
    });

    const currentTabIndex = computed(() => {
      return assetTabs.value.findIndex(t => t.id === activeTab.value);
    });

    const goToNextTab = () => {
      const idx = currentTabIndex.value;
      if (idx < assetTabs.value.length - 1) {
        activeTab.value = assetTabs.value[idx + 1].id;
      }
    };

    const goToPreviousTab = () => {
      const idx = currentTabIndex.value;
      if (idx > 0) {
        activeTab.value = assetTabs.value[idx - 1].id;
      }
    };

    // Load existing data
    onMounted(async () => {
      // Set default tab to first allowed tab
      if (allowedTabs.value && allowedTabs.value.length > 0) {
        activeTab.value = allowedTabs.value[0];
      }

      try {
        await Promise.all([
          loadPensions(),
          loadProperties(),
          loadInvestments(),
          loadSavingsAccounts(),
          loadUserAddress(),
        ]);
      } catch (err) {
        // Data loading errors are handled in individual methods
      }

      // Emit initial sidebar content
      emitSidebarContent();
    });

    // Pensions methods
    async function loadPensions() {
      try {
        const response = await retirementService.getRetirementData();
        // retirementService returns response.data which has structure: { success, message, data: { dc_pensions, db_pensions, state_pension } }
        const retirementData = response.data || response;
        pensions.value = {
          dc: retirementData.dc_pensions || [],
          db: retirementData.db_pensions || [],
          state: retirementData.state_pension || null,
        };
      } catch (err) {
        logger.error('Failed to load pensions', err);
      }
    }

    function openPensionForm(type, pension = null) {
      pensionFormType.value = type;
      editingPension.value = pension;
      showPensionForm.value = true;
      window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    async function deletePension(type, id) {
      const confirmMessage = `Are you sure you want to delete this ${type === 'dc' ? 'Defined Contribution' : 'Defined Benefit'} pension?`;
      if (confirm(confirmMessage)) {
        try {
          if (type === 'dc') {
            await retirementService.deleteDCPension(id);
          } else if (type === 'db') {
            await retirementService.deleteDBPension(id);
          }
          await loadPensions();
        } catch (err) {
          error.value = 'Failed to delete pension';
        }
      }
    }

    function closePensionForm() {
      showPensionForm.value = false;
      pensionFormType.value = null;
      editingPension.value = null;
    }

    async function handlePensionSaved(data) {
      try {
        if (pensionFormType.value === 'dc') {
          if (editingPension.value) {
            await retirementService.updateDCPension(editingPension.value.id, data);
          } else {
            await retirementService.createDCPension(data);
          }
        } else if (pensionFormType.value === 'db') {
          if (editingPension.value) {
            await retirementService.updateDBPension(editingPension.value.id, data);
          } else {
            await retirementService.createDBPension(data);
          }
        } else if (pensionFormType.value === 'state') {
          await retirementService.updateStatePension(data);
        }

        closePensionForm();
        await loadPensions();
      } catch (err) {
        error.value = 'Failed to save pension. Please try again.';
      }
    }

    // Properties methods
    async function loadProperties() {
      try {
        const response = await propertyService.getProperties();
        properties.value = Array.isArray(response) ? response : (response.data?.properties || response.data || []);
      } catch (err) {
        // Properties loading failed silently - will show empty list
      }
    }

    async function editProperty(property) {
      // Reload property from API to get fresh data (not cached)
      try {
        const response = await propertyService.getProperty(property.id);
        // API returns { success, data: { property } }
        editingProperty.value = response.data?.property || response.property || response;
        showPropertyForm.value = true;
      } catch (err) {
        // Fallback to cached data if API fails
        editingProperty.value = property;
        showPropertyForm.value = true;
      }
      window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    async function deleteProperty(id) {
      if (confirm('Are you sure you want to delete this property?')) {
        try {
          await propertyService.deleteProperty(id);
          await loadProperties();
        } catch (err) {
          error.value = 'Failed to delete property';
        }
      }
    }

    function closePropertyForm() {
      showPropertyForm.value = false;
      editingProperty.value = null;
    }

    async function handlePropertySaved(data) {
      try {
        // Save property first
        const propertyResponse = editingProperty.value
          ? await propertyService.updateProperty(editingProperty.value.id, data.property)
          : await propertyService.createProperty(data.property);

        // Get property ID from response (API returns { data: { property: { id } } })
        const propertyId = editingProperty.value?.id || propertyResponse.data?.property?.id || propertyResponse.data?.id || propertyResponse.id;

        // If mortgage data provided and property was saved successfully, save/update mortgage
        if (data.mortgage && propertyId) {
          // Check if property already has a mortgage (when editing)
          const existingMortgage = editingProperty.value?.mortgages?.[0];

          if (existingMortgage) {
            // Try to update existing mortgage
            try {
              await propertyService.updatePropertyMortgage(propertyId, existingMortgage.id, data.mortgage);
            } catch (updateError) {
              // If mortgage not found (404), create a new one instead
              if (updateError.response?.status === 404) {
                await propertyService.createPropertyMortgage(propertyId, data.mortgage);
              } else {
                throw updateError;
              }
            }
          } else {
            // Create new mortgage
            await propertyService.createPropertyMortgage(propertyId, data.mortgage);
          }
        }

        closePropertyForm();
        await loadProperties();
      } catch (err) {
        error.value = 'Failed to save property. Please try again.';
      }
    }

    // Investments methods
    async function loadInvestments() {
      try {
        const response = await investmentService.getInvestmentData();
        investments.value = response.data?.accounts || [];
      } catch (err) {
        // Investments loading failed silently - will show empty list
      }
    }

    function editInvestment(investment) {
      editingInvestment.value = investment;
      showInvestmentForm.value = true;
      window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    async function deleteInvestment(id) {
      if (confirm('Are you sure you want to delete this investment account?')) {
        try {
          await investmentService.deleteAccount(id);
          await loadInvestments();
        } catch (err) {
          error.value = 'Failed to delete investment account';
        }
      }
    }

    function closeInvestmentForm() {
      showInvestmentForm.value = false;
      editingInvestment.value = null;
    }

    async function handleInvestmentSaved(data) {
      try {
        // Save investment account
        if (editingInvestment.value) {
          await investmentService.updateAccount(editingInvestment.value.id, data);
        } else {
          await investmentService.createAccount(data);
        }

        closeInvestmentForm();
        await loadInvestments();
      } catch (err) {
        if (err.response?.data?.errors) {
          const fieldErrors = Object.values(err.response.data.errors).flat();
          error.value = 'Failed to save investment account: ' + fieldErrors.join('. ');
        } else {
          error.value = 'Failed to save investment account. Please try again.';
        }
      }
    }

    // Savings methods
    async function loadSavingsAccounts() {
      try {
        const response = await savingsService.getSavingsData();
        savingsAccounts.value = response.data?.accounts || [];
      } catch (err) {
        // Savings loading failed silently - will show empty list
      }
    }

    async function loadUserAddress() {
      try {
        const response = await userProfileService.getProfile();
        const profile = response.data || response;
        // Address is nested under personal_info.address in the API response
        const address = profile.personal_info?.address || {};
        userAddress.value = {
          address_line_1: address.line_1 || '',
          address_line_2: address.line_2 || '',
          city: address.city || '',
          county: address.county || '',
          postcode: address.postcode || '',
        };
      } catch (err) {
        // Address loading failed silently - auto-populate won't work
      }
    }

    function editSavings(savings) {
      editingSavings.value = savings;
      showSavingsForm.value = true;
      window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    async function deleteSavings(id) {
      if (confirm('Are you sure you want to delete this savings account?')) {
        try {
          await savingsService.deleteAccount(id);
          await loadSavingsAccounts();
        } catch (err) {
          error.value = 'Failed to delete savings account';
        }
      }
    }

    function closeSavingsForm() {
      showSavingsForm.value = false;
      editingSavings.value = null;
    }

    async function handleSavingsSaved(data) {
      try {
        // Save savings account
        if (editingSavings.value) {
          await savingsService.updateAccount(editingSavings.value.id, data);
        } else {
          await savingsService.createAccount(data);
        }

        closeSavingsForm();
        await loadSavingsAccounts();
      } catch (err) {
        error.value = 'Failed to save savings account. Please try again.';
      }
    }

    // Document upload functions
    function openUploadModal(documentType) {
      uploadDocumentType.value = documentType;
      showUploadModal.value = true;
    }

    function closeUploadModal() {
      showUploadModal.value = false;
      uploadDocumentType.value = null;
    }

    async function handleDocumentSaved(savedData) {
      // Capture type before closing (closeUploadModal nulls it)
      const type = uploadDocumentType.value;
      closeUploadModal();

      // Reload the appropriate data based on document type
      if (type === 'pension_statement') {
        await loadPensions();
      } else if (type === 'investment_statement') {
        await loadInvestments();
      } else if (type === 'savings_statement') {
        await loadSavingsAccounts();
      }
    }

    const showAssetSkipModal = ref(false);

    // Navigation — Continue cycles through tabs before advancing to next step
    function handleNext() {
      // If more tabs to view, advance to next tab
      if (currentTabIndex.value < assetTabs.value.length - 1) {
        activeTab.value = assetTabs.value[currentTabIndex.value + 1].id;
        window.scrollTo({ top: 0, behavior: 'smooth' });
        return;
      }

      // All tabs viewed — check if any tabs have no data entered
      const incompleteTabs = [];
      const tabOrder = allowedTabs.value || ['retirement', 'properties', 'investments', 'cash'];
      const tabLabels = { retirement: 'Retirement', properties: 'Properties', investments: 'Investments', cash: 'Cash' };

      for (const tab of tabOrder) {
        if (tab === 'retirement' && pensions.value.dc.length === 0 && pensions.value.db.length === 0 && !pensions.value.state) {
          incompleteTabs.push(tabLabels[tab]);
        } else if (tab === 'properties' && properties.value.length === 0) {
          incompleteTabs.push(tabLabels[tab]);
        } else if (tab === 'investments' && investments.value.length === 0) {
          incompleteTabs.push(tabLabels[tab]);
        } else if (tab === 'cash' && savingsAccounts.value.length === 0) {
          incompleteTabs.push(tabLabels[tab]);
        }
      }

      if (incompleteTabs.length > 0 && incompleteTabs.length === tabOrder.length) {
        // All tabs empty — show skip prompt
        showAssetSkipModal.value = true;
      } else {
        // At least some data entered, proceed
        emit('next');
      }
    }

    function confirmAssetSkip() {
      showAssetSkipModal.value = false;
      emit('next');
    }

    function handleBack() {
      emit('back');
    }

    function handleSkip() {
      emit('skip', 'assets');
    }

    const formatDCPensionType = (type) => {
      const types = {
        occupational: 'Occupational',
        sipp: 'Self-Invested Personal Pension',
        personal: 'Personal',
        stakeholder: 'Stakeholder',
        workplace: 'Workplace',
      };
      return types[type] || 'Defined Contribution Pension';
    };

    const getPensionMonthlyContribution = (pension) => {
      if (pension.monthly_contribution_amount > 0) {
        return pension.monthly_contribution_amount;
      }
      const salary = parseFloat(pension.annual_salary || 0);
      if (salary > 0) {
        const employeePercent = parseFloat(pension.employee_contribution_percent || 0);
        const employerPercent = parseFloat(pension.employer_contribution_percent || 0);
        const totalPercent = employeePercent + employerPercent;
        if (totalPercent > 0) {
          return (salary * (totalPercent / 100)) / 12;
        }
      }
      return 0;
    };

    const formatDBPensionType = (type) => {
      const types = {
        final_salary: 'Final Salary',
        career_average: 'Career Average',
        public_sector: 'Public Sector',
      };
      return types[type] || 'Defined Benefit Pension';
    };

    // Investment account helper functions
    const formatInvestmentAccountType = (type) => {
      const types = {
        'isa': 'ISA',
        'sipp': 'Self-Invested Personal Pension',
        'gia': 'General Investment Account',
        'pension': 'Pension',
        'nsi': 'National Savings & Investments',
        'onshore_bond': 'Onshore Bond',
        'offshore_bond': 'Offshore Bond',
        'vct': 'Venture Capital Trust',
        'eis': 'Enterprise Investment Scheme',
        'other': 'Other',
      };
      return types[type] || type;
    };

    const getInvestmentTypeBadgeClass = (type) => {
      const classes = {
        isa: 'bg-spring-100 text-spring-800',
        gia: 'bg-violet-100 text-violet-800',
        sipp: 'bg-purple-100 text-purple-800',
        pension: 'bg-purple-100 text-purple-800',
        nsi: 'bg-violet-100 text-violet-800',
        onshore_bond: 'bg-spring-100 text-spring-800',
        offshore_bond: 'bg-spring-100 text-spring-800',
        vct: 'bg-pink-100 text-pink-800',
        eis: 'bg-pink-100 text-pink-800',
        other: 'bg-savannah-100 text-horizon-500',
      };
      return classes[type] || 'bg-savannah-100 text-horizon-500';
    };

    // Savings account helper functions
    const formatSavingsAccountType = (type) => {
      const types = {
        savings_account: 'Savings Account',
        current_account: 'Current Account',
        easy_access: 'Easy Access',
        notice: 'Notice Account',
        fixed: 'Fixed Term',
      };
      return types[type] || type;
    };

    const getFullSavingsBalance = (account) => {
      // Single-record pattern: DB stores FULL balance
      // Use full_balance from API if available, otherwise current_balance is already full
      return account.full_balance ?? account.current_balance ?? 0;
    };

    const getUserSavingsShare = (account) => {
      // Single-record pattern: Use user_share from API if available
      if (account.user_share !== undefined) {
        return account.user_share;
      }
      // Fallback: calculate from full balance
      const fullBalance = getFullSavingsBalance(account);
      if (account.ownership_type === 'joint' && account.ownership_percentage) {
        return fullBalance * (account.ownership_percentage / 100);
      }
      return fullBalance;
    };

    const formatInterestRate = (rate) => {
      // Rate is stored as a percentage (e.g., 4.55 = 4.55%)
      // Display directly without multiplying
      return `${parseFloat(rate || 0).toFixed(2)}%`;
    };

    // Common ownership helper functions
    const formatOwnershipType = (type) => {
      const types = {
        individual: 'Individual',
        joint: 'Joint',
        trust: 'Trust',
      };
      return types[type] || 'Individual';
    };

    const getOwnershipBadgeClass = (type) => {
      const classes = {
        individual: 'bg-savannah-100 text-horizon-500',
        joint: 'bg-purple-100 text-purple-800',
        trust: 'bg-violet-100 text-violet-800',
      };
      return classes[type] || 'bg-savannah-100 text-horizon-500';
    };

    return {
      stepTitle,
      stepDescription,
      activeTab,
      assetTabs,
      currentTabIndex,
      goToNextTab,
      goToPreviousTab,
      // Pensions
      pensions,
      showPensionForm,
      pensionFormType,
      editingPension,
      openPensionForm,
      deletePension,
      closePensionForm,
      handlePensionSaved,
      // Properties
      properties,
      showPropertyForm,
      showPensionTypeSelector,
      showAssetSkipModal,
      confirmAssetSkip,
      editingProperty,
      editProperty,
      deleteProperty,
      closePropertyForm,
      handlePropertySaved,
      // Investments
      investments,
      showInvestmentForm,
      editingInvestment,
      editInvestment,
      deleteInvestment,
      closeInvestmentForm,
      handleInvestmentSaved,
      // Savings
      savingsAccounts,
      showSavingsForm,
      editingSavings,
      editSavings,
      deleteSavings,
      closeSavingsForm,
      handleSavingsSaved,
      // Document upload
      showUploadModal,
      uploadDocumentType,
      openUploadModal,
      closeUploadModal,
      handleDocumentSaved,
      // User
      currentUser,
      // Common
      loading,
      error,
      userAddress,
      handleNext,
      handleBack,
      handleSkip,
      formatCurrency,
      formatDCPensionType,
      formatDBPensionType,
      getPensionMonthlyContribution,
      // Investment helpers
      formatInvestmentAccountType,
      getInvestmentTypeBadgeClass,
      // Savings helpers
      formatSavingsAccountType,
      getFullSavingsBalance,
      formatInterestRate,
      // Common helpers
      formatOwnershipType,
      getOwnershipBadgeClass,
      // Resource links
      LINKS,
      STEP_RESOURCES,
    };
  },
};
</script>

<style scoped>
/* Pension Cards Grid */
.pensions-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
  gap: 20px;
}

.pension-card {
  background: white;
  border-radius: 12px;
  @apply border border-light-gray;
  padding: 20px;
  cursor: pointer;
  transition: all 0.2s ease;
}

.pension-card:hover {
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
  transform: translateY(-2px);
  @apply border-violet-500;
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 16px;
  flex-wrap: wrap;
  gap: 8px;
}

.badge {
  display: inline-block;
  padding: 4px 10px;
  font-size: 11px;
  font-weight: 600;
  border-radius: 6px;
}

.badge-dc {
  @apply bg-violet-100;
  @apply text-violet-800;
}

.badge-db {
  @apply bg-purple-100;
  @apply text-purple-800;
}

.badge-state {
  @apply bg-spring-100;
  @apply text-spring-800;
}

.card-content {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.pension-scheme {
  font-size: 18px;
  font-weight: 700;
  @apply text-horizon-500;
  margin: 0;
  line-height: 1.3;
}

.pension-provider-text {
  font-size: 14px;
  @apply text-neutral-500;
  margin: 0;
  min-height: 20px;
}

.pension-details {
  display: flex;
  flex-direction: column;
  margin-top: 4px;
  padding-top: 12px;
  @apply border-t border-light-gray;
}

.value-rows {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.detail-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.detail-label {
  font-size: 14px;
  @apply text-neutral-500;
  font-weight: 500;
}

.detail-value {
  font-size: 16px;
  @apply text-horizon-500;
  font-weight: 700;
}

/* Account Cards Grid (Investments & Savings) */
.accounts-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
  gap: 20px;
}

.account-card {
  background: white;
  border-radius: 12px;
  @apply border border-light-gray;
  padding: 20px;
  cursor: pointer;
  transition: all 0.2s ease;
}

.account-card:hover {
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
  transform: translateY(-2px);
  @apply border-violet-500;
}

.ownership-badge {
  display: inline-block;
  padding: 4px 12px;
  font-size: 12px;
  font-weight: 600;
  border-radius: 6px;
}

.badge-group {
  display: flex;
  gap: 6px;
  flex-wrap: wrap;
}

.badge-emergency {
  @apply bg-spring-100;
  @apply text-spring-800;
}

.badge-isa {
  @apply bg-violet-100;
  @apply text-violet-800;
}

.account-institution {
  font-size: 18px;
  font-weight: 700;
  @apply text-horizon-500;
  margin: 0;
}

.account-type {
  font-size: 14px;
  @apply text-neutral-500;
  margin: 0;
  min-height: 20px;
}

.account-details {
  display: flex;
  flex-direction: column;
  gap: 10px;
  margin-top: 4px;
  padding-top: 12px;
  @apply border-t border-light-gray;
}

.detail-value.interest {
  @apply text-spring-500;
}

@media (max-width: 768px) {
  .pensions-grid,
  .accounts-grid {
    grid-template-columns: 1fr;
  }
}
</style>
