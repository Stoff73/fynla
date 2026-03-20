<template>
  <OnboardingStep
    title="Your Investments"
    description="Add your investment accounts so we can analyse your portfolio"
    :can-go-back="true"
    :can-skip="true"
    :loading="loading"
    :error="error"
    :next-button-text="accounts.length > 0 && !showForm ? 'Continue' : undefined"
    @next="handleNext"
    @back="handleBack"
    @skip="handleSkip"
  >
    <div class="space-y-6">
      <!-- Saved Accounts List -->
      <div v-if="accounts.length > 0 && !showForm" class="space-y-3">
        <h4 class="text-body font-medium text-horizon-500">Your Investment Accounts</h4>
        <div
          v-for="account in accounts"
          :key="account.id"
          class="bg-eggshell-500 rounded-lg p-4"
        >
          <div class="flex items-start justify-between">
            <div>
              <div class="flex items-center gap-2 mb-1">
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-violet-100 text-violet-700">
                  {{ formatAccountType(account.account_type) }}
                </span>
              </div>
              <p class="text-body font-medium text-horizon-500">
                {{ account.provider || account.platform_name || 'Investment Account' }}
              </p>
              <p v-if="account.platform_name && account.provider" class="text-body-sm text-neutral-500">
                {{ account.platform_name }}
              </p>
            </div>
            <p class="text-body font-semibold text-horizon-500">
              {{ formatCurrency(account.current_value) }}
            </p>
          </div>
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
      <div v-if="showForm || accounts.length === 0">
        <AccountForm
          :show="true"
          context="onboarding"
          @save="handleAccountSaved"
          @close="handleFormClose"
        />
      </div>
    </div>
  </OnboardingStep>
</template>

<script>
import { ref, onMounted } from 'vue';
import { useStore } from 'vuex';
import { currencyMixin } from '@/mixins/currencyMixin';
import OnboardingStep from '../OnboardingStep.vue';
import AccountForm from '@/components/Investment/AccountForm.vue';
import investmentService from '@/services/investmentService';

export default {
  name: 'InvestmentStep',

  components: {
    OnboardingStep,
    AccountForm,
  },

  mixins: [currencyMixin],

  emits: ['next', 'back', 'skip'],

  setup(props, { emit }) {
    const store = useStore();
    const accounts = ref([]);
    const showForm = ref(true);
    const loading = ref(false);
    const error = ref(null);

    const accountTypeLabels = {
      stocks_and_shares_isa: 'Stocks & Shares ISA',
      general_investment: 'General Investment Account',
      onshore_bond: 'Onshore Bond',
      offshore_bond: 'Offshore Bond',
      vct: 'Venture Capital Trust',
      eis: 'Enterprise Investment Scheme',
      private_company: 'Private Company',
      crowdfunding: 'Crowdfunding',
      saye: 'SAYE / Sharesave',
      csop: 'Company Share Option Plan',
      emi: 'Enterprise Management Incentives',
      unapproved_options: 'Unapproved Share Options',
      rsus: 'Restricted Stock Units',
      other: 'Other',
    };

    const formatAccountType = (type) => {
      return accountTypeLabels[type] || type?.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase()) || 'Investment';
    };

    const loadAccounts = async () => {
      try {
        const data = await investmentService.getInvestmentData();
        if (data?.data?.accounts) {
          accounts.value = data.data.accounts;
        } else if (Array.isArray(data?.data)) {
          accounts.value = data.data;
        }
        if (accounts.value.length > 0) {
          showForm.value = false;
        }
      } catch {
        // No existing accounts
      }
    };

    const handleAccountSaved = async (formData) => {
      loading.value = true;
      error.value = null;

      try {
        await investmentService.createAccount(formData);
        await loadAccounts();
        showForm.value = false;

        await store.dispatch('onboarding/saveStepData', {
          stepName: 'investments',
          data: { accounts_added: accounts.value.length },
        });
      } catch (err) {
        error.value = err.message || 'Failed to save account. Please try again.';
      } finally {
        loading.value = false;
      }
    };

    const handleFormClose = () => {
      if (accounts.value.length > 0) {
        showForm.value = false;
      }
    };

    const handleNext = () => {
      if (!showForm.value && accounts.value.length > 0) {
        emit('next');
      }
    };

    const handleBack = () => {
      emit('back');
    };

    const handleSkip = () => {
      emit('skip');
    };

    onMounted(async () => {
      await loadAccounts();
    });

    return {
      accounts,
      showForm,
      loading,
      error,
      formatAccountType,
      handleAccountSaved,
      handleFormClose,
      handleNext,
      handleBack,
      handleSkip,
    };
  },
};
</script>
