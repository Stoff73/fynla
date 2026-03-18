<template>
  <OnboardingStep
    title="Assets & Wealth"
    description="Add your properties, investments, and savings accounts"
    :can-go-back="true"
    :can-skip="true"
    :loading="loading"
    :error="error"
    @next="handleNext"
    @back="handleBack"
    @skip="handleSkip"
  >
    <div class="space-y-6">
      <!-- Tabs for different asset types -->
      <div class="border-b border-light-gray">
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
        <!-- Why Retirement Info Matters -->
        <div class="bg-violet-50 border border-violet-200 rounded-lg p-4">
          <div class="flex">
            <svg class="h-5 w-5 text-violet-400 mt-0.5 mr-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
            </svg>
            <div>
              <p class="text-body-sm text-violet-800">
                <strong>Why this matters:</strong> Your pension information is essential for accurate financial planning. It directly impacts your net worth calculations and retirement readiness projections. We also provide future calculations, income projections and tax optimised drawdown plans for retirement.
              </p>
            </div>
          </div>
        </div>

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
                    <span class="detail-value">{{ pension.retirement_age || 67 }}</span>
                  </div>

                  <div class="detail-row">
                    <span class="detail-label">Monthly Contribution</span>
                    <span class="detail-value">{{ formatCurrency(pension.monthly_contribution_amount || 0) }}</span>
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

        <!-- Add Pension Buttons -->
        <div class="flex flex-wrap gap-2">
          <button
            type="button"
            class="btn-secondary"
            @click="openPensionForm('dc')"
          >
            + Add Money Purchase Pension
          </button>
          <button
            type="button"
            class="btn-secondary"
            @click="openPensionForm('db')"
          >
            + Add Final Salary Pension
          </button>
          <button
            type="button"
            class="btn-secondary"
            @click="openPensionForm('state')"
          >
            + Add State Pension
          </button>
          <button
            v-preview-disabled="'upload'"
            type="button"
            class="inline-flex items-center px-4 py-2 border-2 border-violet-600 text-violet-600 bg-white rounded-button hover:bg-violet-50 transition-colors text-sm font-medium"
            @click="openUploadModal('pension_statement')"
          >
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
            </svg>
            Upload Statement
          </button>
        </div>

        <p v-if="pensions.dc.length === 0 && pensions.db.length === 0 && !pensions.state" class="text-body-sm text-neutral-500 italic">
          You can skip this step and add pensions later from your dashboard.
        </p>
      </div>

      <!-- Properties Tab -->
      <div v-show="activeTab === 'properties'" class="space-y-4">
        <div class="bg-violet-50 border border-violet-200 rounded-lg p-4">
          <div class="flex">
            <svg class="h-5 w-5 text-violet-400 mt-0.5 mr-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
            </svg>
            <div>
              <p class="text-body-sm text-violet-800">
                <strong>Why this matters:</strong> Properties are usually the largest component of an estate. Adding property details helps us calculate your potential Inheritance Tax liability. Rental income can also be used for retirement planning, and releasing capital from properties may form part of your financial strategy.
              </p>
            </div>
          </div>
        </div>

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
          class="btn-secondary w-full md:w-auto"
          @click="showPropertyForm = true"
        >
          + Add Property
        </button>

        <p v-if="properties.length === 0" class="text-body-sm text-neutral-500 italic">
          You can skip this step and add properties later from your dashboard.
        </p>
      </div>

      <!-- Investments Tab -->
      <div v-show="activeTab === 'investments'" class="space-y-4">
        <!-- Why Investment Info Matters -->
        <div class="bg-violet-50 border border-violet-200 rounded-lg p-4">
          <div class="flex">
            <svg class="h-5 w-5 text-violet-400 mt-0.5 mr-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
            </svg>
            <div>
              <p class="text-body-sm text-violet-800">
                <strong>Why this matters:</strong> Your investment information feeds into our comprehensive analysis including future value calculations, risk assessment, fee comparisons, and tax optimisation strategies. This data also informs your net worth calculations and estate planning.
              </p>
            </div>
          </div>
        </div>

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
        <div class="flex flex-wrap gap-2">
          <button
            type="button"
            class="btn-secondary"
            @click="showInvestmentForm = true"
          >
            + Add Investment Account
          </button>
          <button
            v-preview-disabled="'upload'"
            type="button"
            class="inline-flex items-center px-4 py-2 border-2 border-violet-600 text-violet-600 bg-white rounded-button hover:bg-violet-50 transition-colors text-sm font-medium"
            @click="openUploadModal('investment_statement')"
          >
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
            </svg>
            Upload Statement
          </button>
        </div>

        <p v-if="investments.length === 0" class="text-body-sm text-neutral-500 italic">
          You can skip this step and add investments later from your dashboard.
        </p>
      </div>

      <!-- Cash Tab -->
      <div v-show="activeTab === 'cash'" class="space-y-4">
        <div class="bg-violet-50 border border-violet-200 rounded-lg p-4">
          <div class="flex">
            <svg class="h-5 w-5 text-violet-400 mt-0.5 mr-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
            </svg>
            <div>
              <p class="text-body-sm text-violet-800">
                <strong>Why this matters:</strong> We use your cash and savings information to inform affordability calculations, provide budget tracking and assess emergency cash needs. Include all cash and bank accounts, including current accounts, Cash ISAs, easy access savings, and fixed-term deposits.
              </p>
            </div>
          </div>
        </div>

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
        <div class="flex flex-wrap gap-2">
          <button
            type="button"
            class="btn-secondary"
            @click="showSavingsForm = true"
          >
            + Add Account
          </button>
          <button
            v-preview-disabled="'upload'"
            type="button"
            class="inline-flex items-center px-4 py-2 border-2 border-violet-600 text-violet-600 bg-white rounded-button hover:bg-violet-50 transition-colors text-sm font-medium"
            @click="openUploadModal('savings_statement')"
          >
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
            </svg>
            Upload Statement
          </button>
        </div>

        <p v-if="savingsAccounts.length === 0" class="text-body-sm text-neutral-500 italic">
          You can skip this step and add accounts later from your dashboard.
        </p>
      </div>
    </div>

    <!-- Property Form Modal -->
    <PropertyForm
      v-if="showPropertyForm"
      :property="editingProperty"
      :user-address="userAddress"
      @close="closePropertyForm"
      @save="handlePropertySaved"
    />

    <!-- Investment Account Form Modal -->
    <AccountForm
      v-if="showInvestmentForm"
      :show="showInvestmentForm"
      :account="editingInvestment"
      :is-onboarding="true"
      @close="closeInvestmentForm"
      @save="handleInvestmentSaved"
    />

    <!-- Savings Account Form Modal -->
    <SaveAccountModal
      v-if="showSavingsForm"
      :account="editingSavings"
      @close="closeSavingsForm"
      @save="handleSavingsSaved"
    />

    <!-- Pension Form Modals -->
    <DCPensionForm
      v-if="showPensionForm && pensionFormType === 'dc'"
      :pension="editingPension"
      :is-onboarding="true"
      @close="closePensionForm"
      @save="handlePensionSaved"
    />

    <DBPensionForm
      v-if="showPensionForm && pensionFormType === 'db'"
      :pension="editingPension"
      @close="closePensionForm"
      @save="handlePensionSaved"
    />

    <StatePensionForm
      v-if="showPensionForm && pensionFormType === 'state'"
      :state-pension="editingPension"
      @close="closePensionForm"
      @save="handlePensionSaved"
    />

    <!-- Document Upload Modal -->
    <DocumentUploadModal
      v-if="showUploadModal"
      :document-type="uploadDocumentType"
      @close="closeUploadModal"
      @saved="handleDocumentSaved"
      @manual-entry="closeUploadModal"
    />
  </OnboardingStep>
</template>

<script>
// DEPRECATED: Will be replaced by unified form with context="onboarding". See life-stage-journey-design.md §11.7
import { ref, computed, onMounted } from 'vue';
import OnboardingStep from '../OnboardingStep.vue';
import PropertyForm from '@/components/NetWorth/Property/PropertyForm.vue';
import PropertyCard from '@/components/NetWorth/PropertyCard.vue';
import AccountForm from '@/components/Investment/AccountForm.vue';
import SaveAccountModal from '@/components/Savings/SaveAccountModal.vue';
import DCPensionForm from '@/components/Retirement/DCPensionForm.vue';
import DBPensionForm from '@/components/Retirement/DBPensionForm.vue';
import StatePensionForm from '@/components/Retirement/StatePensionForm.vue';
import DocumentUploadModal from '@/components/Shared/DocumentUploadModal.vue';
import propertyService from '@/services/propertyService';
import investmentService from '@/services/investmentService';
import savingsService from '@/services/savingsService';
import retirementService from '@/services/retirementService';
import userProfileService from '@/services/userProfileService';
import { formatCurrency } from '@/utils/currency';

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
  },

  emits: ['next', 'back', 'skip'],

  setup(_props, { emit }) {
    const activeTab = ref('retirement');

    // Properties state
    const properties = ref([]);
    const showPropertyForm = ref(false);
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

    // Tab counts
    const assetTabs = computed(() => [
      { id: 'retirement', name: 'Retirement', count: pensions.value.dc.length + pensions.value.db.length + (pensions.value.state ? 1 : 0) },
      { id: 'properties', name: 'Properties', count: properties.value.length },
      { id: 'investments', name: 'Investments', count: investments.value.length },
      { id: 'cash', name: 'Cash', count: savingsAccounts.value.length },
    ]);

    // Load existing data
    onMounted(async () => {
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
        console.error('Failed to load pensions', err);
      }
    }

    function openPensionForm(type, pension = null) {
      pensionFormType.value = type;
      editingPension.value = pension;
      showPensionForm.value = true;
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

        // Get property ID from response (API returns property directly, not wrapped in data)
        const propertyId = editingProperty.value?.id || propertyResponse.data?.id || propertyResponse.id;

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
        error.value = 'Failed to save investment account. Please try again.';
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
      closeUploadModal();

      // Reload the appropriate data based on document type
      if (uploadDocumentType.value === 'pension_statement') {
        await loadPensions();
      } else if (uploadDocumentType.value === 'investment_statement') {
        await loadInvestments();
      } else if (uploadDocumentType.value === 'savings_statement') {
        await loadSavingsAccounts();
      }
    }

    // Navigation
    function handleNext() {
      // Define tab order for Assets & Wealth screen
      const tabOrder = ['retirement', 'properties', 'investments', 'cash'];
      const currentIndex = tabOrder.indexOf(activeTab.value);

      // If not on the last tab, go to next tab
      if (currentIndex < tabOrder.length - 1) {
        activeTab.value = tabOrder[currentIndex + 1];
      } else {
        // On last tab (cash), proceed to next step (Liabilities)
        emit('next');
      }
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
      activeTab,
      assetTabs,
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
