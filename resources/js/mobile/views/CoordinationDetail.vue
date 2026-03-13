<template>
  <div class="px-4 pt-4 pb-6">
    <MobileDetailSkeleton v-if="loading" :rows="3" />

    <template v-else-if="hasData">
      <MobileHeroCard title="Coordination" :value="formatCurrency(netWorthTotal)" subtitle="Net worth" />
      <MobileFynCard :summary="fynSummary" />

      <!-- Financial Plans -->
      <MobileAccordionSection
        title="Financial plans"
        :default-open="true"
        class="mb-3"
      >
        <template v-if="plansList.length">
          <div class="divide-y divide-light-gray">
            <div v-for="plan in plansList" :key="plan.type" class="px-4 py-3">
              <div class="flex items-center justify-between mb-1.5">
                <h4 class="text-sm font-medium text-horizon-500">{{ plan.label }}</h4>
                <span class="text-xs font-semibold" :class="plan.completeness >= 80 ? 'text-spring-500' : 'text-violet-500'">
                  {{ plan.completeness }}%
                </span>
              </div>
              <div class="w-full h-1.5 bg-savannah-100 rounded-full overflow-hidden">
                <div
                  class="h-full rounded-full transition-all duration-300"
                  :class="plan.completeness >= 80 ? 'bg-spring-500' : 'bg-violet-500'"
                  :style="{ width: plan.completeness + '%' }"
                ></div>
              </div>
            </div>
          </div>
        </template>
        <p v-else class="px-4 py-6 text-sm text-neutral-500 text-center">No financial plans generated yet</p>
      </MobileAccordionSection>

      <!-- Cross-Module Insights -->
      <MobileAccordionSection
        title="Recommendations"
        :badge="topRecommendations.length || null"
        class="mb-3"
      >
        <template v-if="topRecommendations.length">
          <div class="divide-y divide-light-gray">
            <div v-for="rec in topRecommendations" :key="rec.id" class="px-4 py-3">
              <div class="flex items-center gap-2 mb-1">
                <span
                  class="px-1.5 py-0.5 rounded text-[10px] font-bold uppercase"
                  :class="rec.priority === 'high' ? 'bg-raspberry-50 text-raspberry-500' : 'bg-violet-50 text-violet-500'"
                >
                  {{ rec.priority || 'medium' }}
                </span>
                <span v-if="rec.module" class="text-xs text-neutral-400">{{ rec.module }}</span>
              </div>
              <p class="text-sm text-horizon-500">{{ rec.title || rec.description }}</p>
              <p v-if="rec.potential_benefit" class="text-xs text-spring-500 mt-0.5">
                Potential benefit: {{ formatCurrency(rec.potential_benefit) }}
              </p>
            </div>
          </div>
        </template>
        <p v-else class="px-4 py-6 text-sm text-neutral-500 text-center">No recommendations available</p>
      </MobileAccordionSection>

      <!-- Net Worth Breakdown -->
      <MobileAccordionSection title="Net worth breakdown" class="mb-3">
        <div class="divide-y divide-light-gray">
          <MobileDataRow label="Total assets" :value="totalAssets" type="currency" />
          <MobileDataRow label="Total liabilities" :value="totalLiabilities" type="currency" />
          <MobileDataRow label="Net worth" :value="netWorthTotal" type="currency" status="good" />
          <template v-if="assetBreakdown">
            <div class="px-4 py-2 bg-savannah-100">
              <p class="text-xs font-semibold text-neutral-500 uppercase">Asset breakdown</p>
            </div>
            <MobileDataRow
              v-for="(item, key) in assetBreakdown"
              :key="key"
              :label="formatBreakdownLabel(key)"
              :value="item.total_value || item"
              type="currency"
            />
          </template>
        </div>
      </MobileAccordionSection>
    </template>

    <MobileEmptyState v-else title="No coordination data yet" subtitle="Your holistic financial picture will appear here" />
  </div>
</template>

<script>
import { mapGetters } from 'vuex';
import { currencyMixin } from '@/mixins/currencyMixin';
import MobileAccordionSection from '@/mobile/components/MobileAccordionSection.vue';
import MobileDataRow from '@/mobile/components/MobileDataRow.vue';
import MobileHeroCard from '@/mobile/components/MobileHeroCard.vue';
import MobileFynCard from '@/mobile/components/MobileFynCard.vue';
import MobileDetailSkeleton from '@/mobile/components/MobileDetailSkeleton.vue';
import MobileEmptyState from '@/mobile/components/MobileEmptyState.vue';

export default {
  name: 'CoordinationDetail',

  components: { MobileAccordionSection, MobileDataRow, MobileHeroCard, MobileFynCard, MobileDetailSkeleton, MobileEmptyState },

  mixins: [currencyMixin],

  data() {
    return { loading: false };
  },

  computed: {
    ...mapGetters('netWorth', {
      netWorthTotal: 'netWorth',
      totalAssets: 'totalAssets',
      totalLiabilities: 'totalLiabilities',
      assetBreakdown: 'assetBreakdown',
    }),

    planStatuses() {
      return this.$store.getters['plans/planStatuses'];
    },

    topRecommendations() {
      return this.$store.state.recommendations?.topRecommendations || [];
    },

    plansList() {
      if (!this.planStatuses) return [];
      const labels = {
        investment: 'Investment plan',
        protection: 'Protection plan',
        retirement: 'Retirement plan',
        estate: 'Estate plan',
        savings: 'Savings plan',
      };
      return Object.entries(this.planStatuses)
        .filter(([, status]) => status != null)
        .map(([type, status]) => ({
          type,
          label: labels[type] || type,
          completeness: status.completeness || status.progress || 0,
        }));
    },

    hasData() {
      return this.netWorthTotal > 0 || this.plansList.length > 0 || this.topRecommendations.length > 0;
    },

    fynSummary() {
      return 'Coordination brings together all your financial modules for a complete picture.';
    },
  },

  async created() {
    this.loading = true;
    try {
      await Promise.all([
        this.$store.dispatch('netWorth/fetchOverview').catch(() => {}),
        this.$store.dispatch('plans/fetchDashboardStatuses').catch(() => {}),
        this.$store.dispatch('recommendations/fetchTopRecommendations').catch(() => {}),
      ]);
    } catch {
      // Data unavailable
    } finally {
      this.loading = false;
    }
  },

  methods: {
    formatBreakdownLabel(key) {
      const labels = {
        pensions: 'Pensions',
        property: 'Property',
        investments: 'Investments',
        cash: 'Cash & savings',
        business: 'Business interests',
        chattels: 'Chattels & collectibles',
      };
      return labels[key] || key.charAt(0).toUpperCase() + key.slice(1);
    },
  },
};
</script>
