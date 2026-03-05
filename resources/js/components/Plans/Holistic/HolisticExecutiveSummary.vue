<template>
  <div class="mb-6">
    <PlanSectionHeader title="Executive Summary" subtitle="Your holistic financial plan overview" color="violet" />

    <div class="bg-white rounded-lg shadow-sm border border-light-gray p-6">
      <p v-if="personalInfo" class="text-horizon-500 mb-4">
        Dear {{ personalInfo.full_name || 'Client' }},
      </p>

      <p class="text-horizon-500 leading-relaxed mb-4">
        This holistic financial plan brings together your individual module plans into a single unified view.
        It covers {{ planListText }}, allowing you to see how recommendations across different areas of your
        financial life interact and compete for your available resources.
      </p>

      <div class="mt-4">
        <h4 class="text-xs font-semibold text-neutral-500 uppercase tracking-wider mb-2">Plans Included</h4>
        <div class="flex flex-wrap gap-2">
          <span
            v-for="plan in availablePlans"
            :key="plan"
            class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-violet-100 text-violet-800"
          >
            {{ formatPlanName(plan) }}
          </span>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import PlanSectionHeader from '@/components/Plans/Shared/PlanSectionHeader.vue';

export default {
  name: 'HolisticExecutiveSummary',

  components: { PlanSectionHeader },

  props: {
    availablePlans: {
      type: Array,
      required: true,
    },
    personalInfo: {
      type: Object,
      default: null,
    },
  },

  computed: {
    planListText() {
      const names = this.availablePlans.map(p => this.formatPlanName(p));
      if (names.length === 0) return '';
      if (names.length === 1) return names[0];
      return names.slice(0, -1).join(', ') + ' and ' + names[names.length - 1];
    },
  },

  methods: {
    formatPlanName(type) {
      const names = {
        protection: 'Protection',
        investment: 'Investment & Savings',
        retirement: 'Retirement',
        estate: 'Estate Planning',
      };
      return names[type] || type;
    },
  },
};
</script>
