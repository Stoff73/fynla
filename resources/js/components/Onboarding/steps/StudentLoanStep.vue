<template>
  <OnboardingStep
    title="Your Student Loan"
    description="Track your student loan to see how it affects your finances"
    :can-go-back="true"
    :can-skip="true"
    :loading="loading"
    :error="error"
    @next="handleNext"
    @back="handleBack"
    @skip="handleSkip"
  >
    <div class="space-y-6">
      <!-- Student Loan Plan Type -->
      <div>
        <label for="plan_type" class="label">
          Repayment Plan <span class="q-icon" @click="emitWhyField($event,'plan_type')" title="Why we ask this">?</span>
        </label>
        <select
          id="plan_type"
          v-model="planType"
          class="input-field"
          @focus="emitWhyField($event,'plan_type')"
        >
          <option value="">Select your plan...</option>
          <option value="plan_1">Plan 1 (started before September 2012)</option>
          <option value="plan_2">Plan 2 (started September 2012 or later)</option>
          <option value="plan_4">Plan 4 (Scotland)</option>
          <option value="plan_5">Plan 5 (started August 2023 or later)</option>
          <option value="postgraduate">Postgraduate Loan</option>
        </select>
        <p v-if="repaymentThreshold" class="mt-1 text-body-sm text-neutral-500">
          Repayment threshold: {{ formatCurrency(repaymentThreshold) }} per year
        </p>
        <p class="mt-1 text-body-sm text-neutral-500">
          Not sure which plan? Check your <a :href="LINKS.GOV_STUDENT_LOAN_REPAY" target="_blank" rel="noopener noreferrer" class="underline font-medium text-violet-500 hover:text-violet-700">repayment plan type</a>
        </p>
      </div>

      <!-- Outstanding Balance -->
      <div>
        <label for="balance" class="label">
          Outstanding Balance <span class="q-icon" @click="emitWhyField($event,'balance')" title="Why we ask this">?</span>
        </label>
        <div class="relative">
          <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-neutral-500">&pound;</span>
          <input
            id="balance"
            v-model.number="balance"
            type="number"
            min="0"
            step="100"
            class="input-field pl-8"
            placeholder="42,000"
            @focus="emitWhyField($event,'balance')"
          >
        </div>
      </div>

      <!-- Interest Rate -->
      <div>
        <label for="interest_rate" class="label">
          Interest Rate (% per year) <span class="q-icon" @click="emitWhyField($event,'interest_rate')" title="Why we ask this">?</span>
        </label>
        <div class="relative">
          <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-neutral-500">%</span>
          <input
            id="interest_rate"
            v-model.number="interestRate"
            type="number"
            min="0"
            max="20"
            step="0.1"
            class="input-field pl-8"
            :placeholder="defaultInterestRate || '7.3'"
            @focus="emitWhyField($event,'interest_rate')"
          >
        </div>
        <p v-if="planType" class="mt-1 text-body-sm text-neutral-500">
          Current rate for your plan: {{ defaultInterestRate }}%
        </p>
      </div>

      <!-- Monthly Payment (optional) -->
      <div>
        <label for="monthly_payment" class="label">
          Monthly Repayment (if known) <span class="q-icon" @click="emitWhyField($event,'monthly_payment')" title="Why we ask this">?</span>
        </label>
        <div class="relative">
          <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-neutral-500">&pound;</span>
          <input
            id="monthly_payment"
            v-model.number="monthlyPayment"
            type="number"
            min="0"
            step="10"
            class="input-field pl-8"
            placeholder="0"
            @focus="emitWhyField($event,'monthly_payment')"
          >
        </div>
        <p class="mt-1 text-body-sm text-neutral-500">
          Leave blank if not yet repaying (e.g., still studying)
        </p>
      </div>

      <!-- Summary -->
      <div v-if="balance > 0" class="bg-eggshell-500 rounded-lg p-4">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-body-sm text-neutral-500">Outstanding Student Loan</p>
            <p class="text-h3 font-display text-raspberry-500">
              {{ formatCurrency(balance) }}
            </p>
          </div>
          <div v-if="interestRate" class="text-right">
            <p class="text-body-sm text-neutral-500">Interest Rate</p>
            <p class="text-lg font-semibold text-horizon-500">{{ interestRate }}%</p>
          </div>
        </div>
      </div>

    </div>
  </OnboardingStep>
</template>

<script>
import { ref, computed } from 'vue';
import OnboardingStep from '../OnboardingStep.vue';
import UsefulResources from '@/components/Onboarding/UsefulResources.vue';
import { LINKS, STEP_RESOURCES } from '@/constants/onboardingLinks';
import { formatCurrency } from '@/utils/currency';

import logger from '@/utils/logger';
export default {
  name: 'StudentLoanStep',

  components: {
    OnboardingStep,
    UsefulResources,
  },

  props: {
    savedData: { type: Object, default: null },
    context: { type: String, default: null },
  },

  emits: ['next', 'back', 'skip', 'save', 'sidebar-update'],

  setup(props, { emit }) {
    const planType = ref('');
    const balance = ref(null);
    const interestRate = ref(null);
    const monthlyPayment = ref(null);
    const loading = ref(false);
    const error = ref(null);

    const WHY_FIELD_DATA = {
      plan_type: { whyWeAsk: 'Your repayment plan type determines the repayment threshold and interest rate applied to your student loan.' },
      balance: { whyWeAsk: 'Your outstanding balance helps us include student loan repayments in your overall financial picture and net worth.' },
      interest_rate: { whyWeAsk: 'The interest rate affects how quickly your balance grows and your total repayment cost over time.' },
      monthly_payment: { whyWeAsk: 'Your monthly repayment amount helps us calculate your disposable income and forecast when the loan will be cleared.' },
    };

    let lastEmittedField = null;

    const emitWhyField = (event, fieldName) => {
      const data = WHY_FIELD_DATA[fieldName];
      if (!data) return;

      if (event?.type === 'focus' && lastEmittedField === fieldName) {
        lastEmittedField = null;
        return;
      }

      const el = event?.target;
      const fieldDiv = el?.closest?.('div') || null;
      const inputEl = fieldDiv?.querySelector('input:not(.prepop-input), select');

      if (event?.type === 'click' && inputEl && !inputEl.disabled) {
        lastEmittedField = fieldName;
        inputEl.focus();
      }

      let fieldOffsetY = 0;
      if (inputEl) {
        const formCol = inputEl.closest('.flex-1');
        if (formCol) {
          const colRect = formCol.getBoundingClientRect();
          const inputRect = inputEl.getBoundingClientRect();
          fieldOffsetY = inputRect.top - colRect.top + (inputRect.height / 2);
        }
      }

      emit('sidebar-update', { whyWeAsk: data.whyWeAsk, fieldOffsetY });
    };

    // Restore form data from cached step data (back navigation)
    if (props.savedData) {
      const planMatch = (props.savedData.liability_name || '').match(/Plan\s*(\d)/i);
      if (planMatch) {
        planType.value = `plan_${planMatch[1]}`;
      }
      balance.value = props.savedData.current_balance || null;
      interestRate.value = props.savedData.interest_rate || null;
      monthlyPayment.value = props.savedData.monthly_payment || null;
    }

    // UK student loan plan details (current tax year)
    const planDetails = {
      plan_1: { threshold: 24990, rate: '6.25' },
      plan_2: { threshold: 27295, rate: '7.3' },
      plan_4: { threshold: 31395, rate: '6.25' },
      plan_5: { threshold: 25000, rate: '7.3' },
      postgraduate: { threshold: 21000, rate: '7.3' },
    };

    const repaymentThreshold = computed(() => {
      if (!planType.value || !planDetails[planType.value]) return null;
      return planDetails[planType.value].threshold;
    });

    const defaultInterestRate = computed(() => {
      if (!planType.value || !planDetails[planType.value]) return null;
      return planDetails[planType.value].rate;
    });

    const handleNext = async () => {
      if (!balance.value || balance.value <= 0) {
        // No loan data — just continue
        emit('next');
        return;
      }

      loading.value = true;
      error.value = null;

      try {
        // Build liability name from plan type
        const planLabels = {
          plan_1: 'Student Loan (Plan 1)',
          plan_2: 'Student Loan (Plan 2)',
          plan_4: 'Student Loan (Plan 4)',
          plan_5: 'Student Loan (Plan 5)',
          postgraduate: 'Postgraduate Loan',
        };

        const formData = {
          liability_type: 'student_loan',
          liability_name: planLabels[planType.value] || 'Student Loan',
          current_balance: balance.value,
          monthly_payment: monthlyPayment.value || 0,
          interest_rate: interestRate.value || parseFloat(defaultInterestRate.value) || 0,
        };

        emit('save', formData);
      } catch (err) {
        error.value = 'Failed to save student loan details. Please try again.';
        logger.error('StudentLoanStep save error:', err);
      } finally {
        loading.value = false;
      }
    };

    const handleBack = () => emit('back');
    const handleSkip = () => emit('skip');

    return {
      planType,
      balance,
      interestRate,
      monthlyPayment,
      loading,
      error,
      repaymentThreshold,
      defaultInterestRate,
      handleNext,
      handleBack,
      handleSkip,
      emitWhyField,
      formatCurrency,
      LINKS,
      STEP_RESOURCES,
    };
  },
};
</script>
