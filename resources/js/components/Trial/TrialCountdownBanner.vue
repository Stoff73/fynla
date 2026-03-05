<template>
  <div
    v-if="shouldShow"
    class="bg-violet-50 border-b border-violet-200"
  >
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-3">
      <div class="flex items-center justify-between gap-4 flex-wrap">
        <div class="flex items-center gap-3 flex-1 min-w-0">
          <div class="flex-shrink-0">
            <svg class="w-5 h-5 text-violet-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </div>
          <div class="min-w-0">
            <p class="text-sm font-medium text-violet-800">
              Your {{ planName }} trial ends in {{ daysRemaining }} {{ daysRemaining === 1 ? 'day' : 'days' }}
            </p>
            <div class="mt-1 w-full max-w-xs">
              <div class="bg-violet-200 rounded-full h-1.5">
                <div
                  class="bg-violet-500 h-1.5 rounded-full transition-all duration-500"
                  :style="{ width: progress + '%' }"
                ></div>
              </div>
            </div>
          </div>
        </div>

        <div class="flex items-center gap-2">
          <button
            @click="showPlanModal = true"
            class="inline-flex items-center px-4 py-1.5 text-sm font-semibold text-white bg-violet-500 rounded-lg hover:bg-violet-600 transition-colors"
          >
            Upgrade Now
          </button>
          <button
            v-if="canDismiss"
            @click="dismiss"
            class="p-1 text-violet-400 hover:text-violet-600 transition-colors"
            aria-label="Dismiss"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>
      </div>
    </div>
    <PlanSelectionModal
      v-if="showPlanModal"
      @select="handlePlanSelect"
      @close="showPlanModal = false"
    />
  </div>
</template>

<script>
import api from '@/services/api';
import PlanSelectionModal from '@/components/Payment/PlanSelectionModal.vue';

export default {
  name: 'TrialCountdownBanner',

  components: {
    PlanSelectionModal,
  },

  data() {
    return {
      trialData: null,
      dismissed: false,
      loading: false,
      showPlanModal: false,
    };
  },

  computed: {
    shouldShow() {
      if (!this.trialData || this.trialData.status !== 'trialing') return false;
      if (this.dismissed && this.canDismiss) return false;
      return true;
    },

    planName() {
      if (!this.trialData) return '';
      return this.trialData.plan.charAt(0).toUpperCase() + this.trialData.plan.slice(1);
    },

    daysRemaining() {
      return this.trialData?.days_remaining ?? 0;
    },

    progress() {
      return this.trialData?.progress ?? 0;
    },

    canDismiss() {
      // Cannot dismiss in final 2 days
      return this.daysRemaining > 2;
    },
  },

  watch: {
    '$route.query.payment'(val) {
      if (val === 'success') {
        this.fetchTrialStatus();
      }
    },
  },

  mounted() {
    this.fetchTrialStatus();
  },

  methods: {
    async fetchTrialStatus() {
      this.loading = true;
      try {
        const response = await api.get('/payment/trial-status');
        this.trialData = response.data;
      } catch {
        // Silently fail — banner just won't show
      } finally {
        this.loading = false;
      }
    },

    dismiss() {
      this.dismissed = true;
    },

    handlePlanSelect({ plan, billingCycle }) {
      this.showPlanModal = false;
      this.$router.push(`/checkout?plan=${plan}&cycle=${billingCycle}`);
    },
  },
};
</script>
