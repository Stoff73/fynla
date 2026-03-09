<template>
  <OnboardingWizard
    :mode="mode"
    :journey-name="journeyName"
  />
</template>

<script>
import OnboardingWizard from '@/components/Onboarding/OnboardingWizard.vue';

export default {
  name: 'OnboardingView',

  components: {
    OnboardingWizard,
  },

  props: {
    mode: {
      type: String,
      default: null,
    },
    journeyName: {
      type: String,
      default: null,
    },
  },
};
</script>
