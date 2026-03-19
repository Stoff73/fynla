<template>
  <OnboardingStep
    title="Your Savings Accounts"
    description="Add your bank accounts and savings so we can track your progress"
    :can-go-back="true"
    :can-skip="true"
    :loading="loading"
    :error="error"
    :next-button-text="accounts.length > 0 && !showForm ? 'Continue' : 'Save Account'"
    @next="handleNext"
    @back="handleBack"
    @skip="handleSkip"
  >
    <div class="space-y-6">
      <!-- Saved Accounts List -->
      <div v-if="accounts.length > 0 && !showForm" class="space-y-3">
        <h4 class="text-body font-medium text-horizon-500">Your Accounts</h4>
        <div
          v-for="account in accounts"
          :key="account.id"
          class="flex items-center justify-between bg-eggshell-500 rounded-lg p-4"
        >
          <div>
            <p class="text-body font-medium text-horizon-500">
              {{ account.institution || 'Savings Account' }}
            </p>
            <p class="text-body-sm text-neutral-500">
              {{ formatAccountType(account.account_type) }}
            </p>
          </div>
          <p class="text-body font-semibold text-horizon-500">
            {{ formatCurrency(account.current_balance) }}
          </p>
        </div>

        <button
          type="button"
          class="flex items-center gap-2 text-body-sm font-medium text-raspberry-500 hover:text-raspberry-600 transition-colors"
          @click="showForm = true"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
          </svg>
          Add another account
        </button>
      </div>

      <!-- Account Form -->
      <div v-if="showForm" class="space-y-4">
        <!-- Institution -->
        <div>
          <label for="institution" class="label">
            Institution
          </label>
          <input
            id="institution"
            v-model="formData.institution"
            type="text"
            class="input-field"
            placeholder="e.g., Halifax, Barclays, Marcus"
          >
        </div>

        <!-- Account Type -->
        <div>
          <label for="account_type" class="label">
            Product Type
          </label>
          <select
            id="account_type"
            v-model="formData.account_type"
            class="input-field"
          >
            <option value="">Select product type...</option>
            <optgroup label="Bank Accounts">
              <option value="savings_account">Savings Account</option>
              <option value="current_account">Current Account</option>
              <option value="easy_access">Easy Access</option>
              <option value="instant_access">Instant Access</option>
              <option value="notice">Notice Account</option>
              <option value="fixed">Fixed Term</option>
            </optgroup>
            <optgroup label="ISAs">
              <option value="cash_isa">Cash ISA</option>
              <option value="junior_isa">Junior ISA</option>
            </optgroup>
            <optgroup label="NS&amp;I Products">
              <option value="premium_bonds">Premium Bonds</option>
              <option value="nsi">NS&amp;I Savings</option>
            </optgroup>
          </select>
          <p class="mt-1 text-body-sm text-neutral-500">
            Tax-free up to the <a :href="LINKS.GOV_ISA_ALLOWANCE" target="_blank" rel="noopener noreferrer" class="underline font-medium text-violet-500 hover:text-violet-700">annual ISA limit</a>
          </p>
        </div>

        <!-- Current Balance -->
        <div>
          <label for="current_balance" class="label">
            Current Balance
          </label>
          <div class="relative">
            <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-neutral-500">&pound;</span>
            <input
              id="current_balance"
              v-model.number="formData.current_balance"
              type="number"
              step="0.01"
              min="0"
              class="input-field pl-8"
              placeholder="0.00"
            >
          </div>
        </div>

        <!-- Interest Rate -->
        <div>
          <label for="interest_rate" class="label">
            Interest Rate
          </label>
          <div class="relative">
            <input
              id="interest_rate"
              v-model.number="formData.interest_rate"
              type="number"
              step="0.01"
              min="0"
              max="20"
              class="input-field pr-8"
              placeholder="0.00"
            >
            <span class="absolute right-3 top-1/2 transform -translate-y-1/2 text-neutral-500">%</span>
          </div>
        </div>

        <!-- Emergency Fund -->
        <div class="flex items-center">
          <input
            id="is_emergency_fund"
            v-model="formData.is_emergency_fund"
            type="checkbox"
            class="h-4 w-4 text-violet-600 focus:ring-violet-500 border-horizon-300 rounded"
          >
          <label for="is_emergency_fund" class="ml-2 block text-sm text-neutral-500">
            This forms part of my emergency fund
          </label>
        </div>
      </div>

      <UsefulResources :links="STEP_RESOURCES.simpleSavings" />
    </div>
  </OnboardingStep>
</template>

<script>
// DEPRECATED: Will be replaced by unified form with context="onboarding". See life-stage-journey-design.md §11.7
import { ref, onMounted } from 'vue';
import { useStore } from 'vuex';
import OnboardingStep from '../OnboardingStep.vue';
import UsefulResources from '@/components/Onboarding/UsefulResources.vue';
import { LINKS, STEP_RESOURCES } from '@/constants/onboardingLinks';
import { formatCurrency } from '@/utils/currency';
import savingsService from '@/services/savingsService';

export default {
  name: 'SimpleSavingsAccountStep',

  components: {
    OnboardingStep,
    UsefulResources,
  },

  emits: ['next', 'back', 'skip'],

  setup(props, { emit }) {
    const store = useStore();

    const formData = ref({
      institution: '',
      account_type: '',
      current_balance: null,
      interest_rate: null,
      is_emergency_fund: false,
    });

    const accounts = ref([]);
    const showForm = ref(true);
    const loading = ref(false);
    const error = ref(null);

    const accountTypeLabels = {
      savings_account: 'Savings Account',
      current_account: 'Current Account',
      easy_access: 'Easy Access',
      instant_access: 'Instant Access',
      notice: 'Notice Account',
      fixed: 'Fixed Term',
      cash_isa: 'Cash ISA',
      junior_isa: 'Junior ISA',
      premium_bonds: 'Premium Bonds',
      nsi: 'NS&I Savings',
    };

    const formatAccountType = (type) => {
      return accountTypeLabels[type] || type;
    };

    const resetForm = () => {
      formData.value = {
        institution: '',
        account_type: '',
        current_balance: null,
        interest_rate: null,
        is_emergency_fund: false,
      };
    };

    const handleNext = async () => {
      // If form is hidden (accounts already added), just continue
      if (!showForm.value) {
        emit('next');
        return;
      }

      // If form is empty and accounts exist, just continue
      if (!formData.value.institution && !formData.value.current_balance && accounts.value.length > 0) {
        emit('next');
        return;
      }

      loading.value = true;
      error.value = null;

      try {
        // Build account data with sensible defaults
        const accountData = {
          institution: formData.value.institution || 'Savings Account',
          account_type: formData.value.account_type || 'savings_account',
          current_balance: formData.value.current_balance || 0,
          interest_rate: formData.value.interest_rate || 0,
          access_type: 'immediate',
          is_emergency_fund: formData.value.is_emergency_fund,
          is_isa: ['cash_isa', 'junior_isa'].includes(formData.value.account_type),
          country: 'United Kingdom',
          ownership_type: 'individual',
        };

        const result = await savingsService.createAccount(accountData);

        if (result.success !== false) {
          accounts.value.push(result.data || result);
          resetForm();
          showForm.value = false;

          // Mark step as actioned in onboarding tracking
          await store.dispatch('onboarding/saveStepData', {
            stepName: 'simple_savings_account',
            data: { accounts_added: accounts.value.length },
          });
        }
      } catch (err) {
        error.value = err.message || 'Failed to save account. Please try again.';
      } finally {
        loading.value = false;
      }
    };

    const handleBack = () => {
      emit('back');
    };

    const handleSkip = () => {
      emit('skip');
    };

    onMounted(async () => {
      // Load existing savings accounts
      try {
        const data = await savingsService.getSavingsData();
        if (data?.data?.accounts) {
          accounts.value = data.data.accounts;
          if (accounts.value.length > 0) {
            showForm.value = false;
          }
        }
      } catch {
        // No existing accounts
      }
    });

    return {
      formData,
      accounts,
      showForm,
      loading,
      error,
      handleNext,
      handleBack,
      handleSkip,
      formatCurrency,
      formatAccountType,
      LINKS,
      STEP_RESOURCES,
    };
  },
};
</script>
