<template>
  <OnboardingWizard mode="module" :module-steps="steps" />
</template>

<script>
import OnboardingWizard from '@/components/Onboarding/OnboardingWizard.vue';

const moduleStepMap = {
  protection: [
    { name: 'protection_policies', title: 'Protection Policies' },
  ],
  estate: [
    { name: 'will_info', title: 'Will Information' },
    { name: 'trust_info', title: 'Trust Information' },
  ],
  investments: [
    { name: 'assets', title: 'Investment Accounts' },
  ],
  pensions: [
    { name: 'assets', title: 'Pension Accounts' },
  ],
  savings: [
    { name: 'assets', title: 'Savings Accounts' },
  ],
  family: [
    { name: 'family_info', title: 'Family Information' },
  ],
};

export default {
  name: 'OnboardingModuleView',

  components: {
    OnboardingWizard,
  },

  props: {
    moduleName: {
      type: String,
      required: true,
    },
  },

  computed: {
    steps() {
      return moduleStepMap[this.moduleName] || [];
    },
  },
};
</script>
