<template>
  <div class="max-w-3xl mx-auto text-center">
    <div class="mb-8">
      <h2 class="text-h1 font-display text-horizon-500 mb-4">
        {{ hasSkippedSections ? 'Setup Partially Complete' : 'Setup Complete!' }}
      </h2>
      <p v-if="!hasSkippedSections" class="text-body text-neutral-500 mb-2">
        Thank you for providing your information. Here's what we captured during onboarding:
      </p>
      <p v-else class="text-body text-neutral-500 mb-2">
        Some sections were skipped. You can complete them later from your profile or the relevant dashboard.
      </p>
    </div>

    <!-- Onboarding Summary -->
    <div class="bg-white rounded-lg shadow-sm border border-light-gray p-6 mb-8 text-left">
      <h3 class="text-h4 font-display text-horizon-500 mb-4">
        Your Setup Summary
      </h3>

      <div v-if="loading" class="text-center py-8">
        <p class="text-neutral-500">Loading your information...</p>
      </div>

      <div v-else class="space-y-2">
        <!-- Personal Information -->
        <button
          @click="goToStep('personal_info')"
          class="flex items-start w-full text-left p-3 rounded-lg hover:bg-eggshell-500 transition-colors group"
        >
          <svg class="h-5 w-5 text-spring-600 mt-0.5 mr-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
          </svg>
          <div class="flex-1">
            <p class="text-body font-medium text-horizon-500 group-hover:text-raspberry-500">Personal Information</p>
            <p class="text-body-sm text-neutral-500">Profile setup complete</p>
          </div>
          <svg class="h-5 w-5 text-horizon-400 group-hover:text-raspberry-500 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
          </svg>
        </button>

        <!-- Protection Policies -->
        <button
          @click="goToStep('protection_policies')"
          class="flex items-start w-full text-left p-3 rounded-lg hover:bg-eggshell-500 transition-colors group"
        >
          <svg
            v-if="summary.policies > 0"
            class="h-5 w-5 text-spring-600 mt-0.5 mr-3 flex-shrink-0"
            fill="currentColor"
            viewBox="0 0 20 20"
          >
            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
          </svg>
          <svg
            v-else
            class="h-5 w-5 text-horizon-400 mt-0.5 mr-3 flex-shrink-0"
            fill="currentColor"
            viewBox="0 0 20 20"
          >
            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 9.586 8.707 8.293z" clip-rule="evenodd" />
          </svg>
          <div class="flex-1">
            <p class="text-body font-medium text-horizon-500 group-hover:text-raspberry-500">Protection Policies</p>
            <p v-if="summary.policies > 0" class="text-body-sm text-neutral-500">
              {{ summary.policies }} {{ summary.policies === 1 ? 'policy' : 'policies' }} added
            </p>
            <p v-else class="text-body-sm text-neutral-500">Skipped - you can add policies later</p>
          </div>
          <svg class="h-5 w-5 text-horizon-400 group-hover:text-raspberry-500 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
          </svg>
        </button>

        <!-- Assets & Wealth (Properties, Investments, Savings) -->
        <button
          @click="goToStep('assets')"
          class="flex items-start w-full text-left p-3 rounded-lg hover:bg-eggshell-500 transition-colors group"
        >
          <svg
            v-if="summary.properties > 0 || summary.investments > 0 || summary.savings > 0"
            class="h-5 w-5 text-spring-600 mt-0.5 mr-3 flex-shrink-0"
            fill="currentColor"
            viewBox="0 0 20 20"
          >
            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
          </svg>
          <svg
            v-else
            class="h-5 w-5 text-horizon-400 mt-0.5 mr-3 flex-shrink-0"
            fill="currentColor"
            viewBox="0 0 20 20"
          >
            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 9.586 8.707 8.293z" clip-rule="evenodd" />
          </svg>
          <div class="flex-1">
            <p class="text-body font-medium text-horizon-500 group-hover:text-raspberry-500">Assets & Wealth</p>
            <div class="text-body-sm text-neutral-500 space-y-0.5">
              <p v-if="summary.properties > 0">
                {{ summary.properties }} {{ summary.properties === 1 ? 'property' : 'properties' }} ({{ formatCurrency(summary.propertyValue) }})
              </p>
              <p v-if="summary.investments > 0">
                {{ summary.investments }} investment {{ summary.investments === 1 ? 'account' : 'accounts' }} ({{ formatCurrency(summary.investmentValue) }})
              </p>
              <p v-if="summary.savings > 0">
                {{ summary.savings }} savings {{ summary.savings === 1 ? 'account' : 'accounts' }} ({{ formatCurrency(summary.savingsValue) }})
              </p>
              <p v-if="summary.properties === 0 && summary.investments === 0 && summary.savings === 0" class="text-neutral-500">
                Skipped - you can add assets later
              </p>
            </div>
          </div>
          <svg class="h-5 w-5 text-horizon-400 group-hover:text-raspberry-500 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
          </svg>
        </button>

        <!-- Liabilities -->
        <button
          @click="goToStep('liabilities')"
          class="flex items-start w-full text-left p-3 rounded-lg hover:bg-eggshell-500 transition-colors group"
        >
          <svg
            v-if="summary.liabilities > 0"
            class="h-5 w-5 text-spring-600 mt-0.5 mr-3 flex-shrink-0"
            fill="currentColor"
            viewBox="0 0 20 20"
          >
            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
          </svg>
          <svg
            v-else
            class="h-5 w-5 text-horizon-400 mt-0.5 mr-3 flex-shrink-0"
            fill="currentColor"
            viewBox="0 0 20 20"
          >
            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 9.586 8.707 8.293z" clip-rule="evenodd" />
          </svg>
          <div class="flex-1">
            <p class="text-body font-medium text-horizon-500 group-hover:text-raspberry-500">Liabilities</p>
            <p v-if="summary.liabilities > 0" class="text-body-sm text-neutral-500">
              {{ summary.liabilities }} {{ summary.liabilities === 1 ? 'liability' : 'liabilities' }} added (Total: {{ formatCurrency(summary.liabilityValue) }})
            </p>
            <p v-else class="text-body-sm text-neutral-500">Skipped - you can add liabilities later</p>
          </div>
          <svg class="h-5 w-5 text-horizon-400 group-hover:text-raspberry-500 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
          </svg>
        </button>

        <!-- Family Members -->
        <button
          @click="goToStep('family_info')"
          class="flex items-start w-full text-left p-3 rounded-lg hover:bg-eggshell-500 transition-colors group"
        >
          <svg
            v-if="summary.familyMembers > 0"
            class="h-5 w-5 text-spring-600 mt-0.5 mr-3 flex-shrink-0"
            fill="currentColor"
            viewBox="0 0 20 20"
          >
            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
          </svg>
          <svg
            v-else
            class="h-5 w-5 text-horizon-400 mt-0.5 mr-3 flex-shrink-0"
            fill="currentColor"
            viewBox="0 0 20 20"
          >
            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 9.586 8.707 8.293z" clip-rule="evenodd" />
          </svg>
          <div class="flex-1">
            <p class="text-body font-medium text-horizon-500 group-hover:text-raspberry-500">Family Members</p>
            <p v-if="summary.familyMembers > 0" class="text-body-sm text-neutral-500">
              {{ summary.familyMembers}} {{ summary.familyMembers === 1 ? 'family member' : 'family members' }} added
            </p>
            <p v-else class="text-body-sm text-neutral-500">Skipped - you can add family members later</p>
          </div>
          <svg class="h-5 w-5 text-horizon-400 group-hover:text-raspberry-500 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
          </svg>
        </button>
      </div>
    </div>

    <!-- What Happens Next -->
    <div class="bg-white rounded-lg shadow-sm border border-light-gray p-6 mb-8 text-left">
      <h3 class="text-h4 font-display text-horizon-500 mb-4">
        What happens next?
      </h3>
      <ul class="space-y-3">
        <li class="flex items-start">
          <svg class="h-5 w-5 text-raspberry-500 mt-0.5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          <span class="text-body text-horizon-500">
            Your dashboard will show your current financial planning position
          </span>
        </li>
        <li class="flex items-start">
          <svg class="h-5 w-5 text-raspberry-500 mt-0.5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          <span class="text-body text-horizon-500">
            We'll calculate your potential Inheritance Tax liability
          </span>
        </li>
        <li class="flex items-start">
          <svg class="h-5 w-5 text-raspberry-500 mt-0.5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          <span class="text-body text-horizon-500">
            You'll receive personalised strategies to optimise your finances
          </span>
        </li>
        <li class="flex items-start">
          <svg class="h-5 w-5 text-raspberry-500 mt-0.5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          <span class="text-body text-horizon-500">
            You can add more detailed information at any time in your profile
          </span>
        </li>
      </ul>
    </div>

    <div v-if="error" class="mb-6 p-4 bg-raspberry-50 border border-raspberry-200 rounded-lg">
      <p class="text-body-sm text-raspberry-700">{{ error }}</p>
    </div>

    <button
      @click="handleComplete"
      :disabled="completionLoading"
      class="btn-primary btn-lg"
    >
      {{ completionLoading ? 'Completing...' : 'Go to Dashboard' }}
    </button>
  </div>
</template>

<script>
import { ref, computed, onMounted } from 'vue';
import { useStore } from 'vuex';
import { useRouter } from 'vue-router';
import propertyService from '@/services/propertyService';
import investmentService from '@/services/investmentService';
import savingsService from '@/services/savingsService';
import protectionService from '@/services/protectionService';
import estateService from '@/services/estateService';
import userProfileService from '@/services/userProfileService';
import { formatCurrency } from '@/utils/currency';

import logger from '@/utils/logger';
export default {
  name: 'CompletionStep',

  setup() {
    const store = useStore();
    const router = useRouter();

    const loading = ref(true);
    const completionLoading = ref(false);
    const error = ref(null);

    const summary = ref({
      properties: 0,
      propertyValue: 0,
      investments: 0,
      investmentValue: 0,
      savings: 0,
      savingsValue: 0,
      liabilities: 0,
      liabilityValue: 0,
      policies: 0,
      familyMembers: 0,
    });

    // Check if any sections were skipped based on summary data
    const hasSkippedSections = computed(() => {
      // A section is considered "skipped" if it has no data
      // Personal info is always required, so we don't check it
      const s = summary.value;
      const hasNoProtection = s.policies === 0;
      const hasNoAssets = s.properties === 0 && s.investments === 0 && s.savings === 0;
      const hasNoLiabilities = s.liabilities === 0;
      const hasNoFamily = s.familyMembers === 0;

      return hasNoProtection || hasNoAssets || hasNoLiabilities || hasNoFamily;
    });

    onMounted(async () => {
      await loadSummary();
    });

    async function loadSummary() {
      loading.value = true;

      try {
        // Load all data in parallel
        const [
          propertyResponse,
          investmentResponse,
          savingsResponse,
          protectionResponse,
          estateResponse,
          profileResponse,
        ] = await Promise.all([
          propertyService.getProperties().catch(() => ({ data: [] })),
          investmentService.getInvestmentData().catch(() => ({ data: { accounts: [] } })),
          savingsService.getSavingsData().catch(() => ({ data: { accounts: [] } })),
          protectionService.getProtectionData().catch(() => ({ data: {} })),
          estateService.getEstateData().catch(() => ({ data: { liabilities: [] } })),
          userProfileService.getProfile().catch(() => ({ data: { family_members: [] } })),
        ]);

        // Calculate property summary
        const properties = propertyResponse.data || [];
        summary.value.properties = properties.length;
        summary.value.propertyValue = properties.reduce((sum, p) => sum + (parseFloat(p.current_value) || 0), 0);

        // Calculate investment summary
        const investments = investmentResponse.data?.accounts || [];
        summary.value.investments = investments.length;
        summary.value.investmentValue = investments.reduce((sum, i) => sum + (parseFloat(i.current_value) || 0), 0);

        // Calculate savings summary
        const savings = savingsResponse.data?.accounts || [];
        summary.value.savings = savings.length;
        summary.value.savingsValue = savings.reduce((sum, s) => sum + (parseFloat(s.current_balance) || 0), 0);

        // Calculate liabilities summary
        const liabilities = estateResponse.data?.liabilities || [];
        summary.value.liabilities = liabilities.length;
        summary.value.liabilityValue = liabilities.reduce((sum, l) => sum + (parseFloat(l.current_balance) || 0), 0);

        // Calculate protection policy summary
        // API returns snake_case keys: life_insurance, critical_illness, etc.
        const protectionData = protectionResponse.data || {};
        const policies = protectionData.policies || {};
        summary.value.policies =
          (policies.life_insurance?.length || 0) +
          (policies.critical_illness?.length || 0) +
          (policies.income_protection?.length || 0) +
          (policies.disability?.length || 0) +
          (policies.sickness_illness?.length || 0);

        // Family members summary
        summary.value.familyMembers = profileResponse.data?.family_members?.length || 0;

      } catch (err) {
        logger.error('Failed to load summary', err);
      } finally {
        loading.value = false;
      }
    }

    const handleComplete = async () => {
      completionLoading.value = true;
      error.value = null;

      try {
        await store.dispatch('onboarding/completeOnboarding');
        await store.dispatch('auth/fetchUser'); // Refresh user data

        router.push({ name: 'Dashboard' });
      } catch (err) {
        error.value = err.message || 'Failed to complete. Please try again.';
      } finally {
        completionLoading.value = false;
      }
    };

    const goToStep = async (stepName) => {
      try {
        // Find the step index by name
        const steps = store.state.onboarding.steps;
        const stepIndex = steps.findIndex(step => step.name === stepName);

        if (stepIndex !== -1) {
          // Navigate to that step
          await store.dispatch('onboarding/goToStep', stepIndex);
        }
      } catch (err) {
        logger.error('Failed to navigate to step:', err);
      }
    };

    return {
      loading,
      completionLoading,
      error,
      summary,
      hasSkippedSections,
      handleComplete,
      goToStep,
      formatCurrency,
    };
  },
};
</script>
