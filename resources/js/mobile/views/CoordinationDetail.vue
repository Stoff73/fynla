<template>
  <div class="px-4 pt-4 pb-6">
    <div v-if="loading" class="space-y-3">
      <div class="bg-white rounded-xl p-6 animate-pulse">
        <div class="w-24 h-8 bg-savannah-100 rounded mx-auto"></div>
      </div>
      <div v-for="n in 3" :key="n" class="bg-white rounded-xl p-4 animate-pulse">
        <div class="w-40 h-4 bg-savannah-100 rounded"></div>
      </div>
    </div>

    <template v-else-if="hasData">
      <!-- Hero -->
      <div class="bg-white rounded-xl border border-light-gray p-6 text-center mb-4">
        <span class="text-3xl block mb-2">{{'🔗'}}</span>
        <h2 class="text-lg font-bold text-horizon-500">Coordination</h2>
        <p class="text-2xl font-black text-horizon-500 mt-3">{{ formatCurrency(netWorthTotal) }}</p>
        <p class="text-xs text-neutral-500 mt-1">Net worth</p>
      </div>

      <!-- Fyn -->
      <div class="bg-horizon-500 rounded-xl p-4 flex items-start gap-3 mb-4">
        <img src="/images/logos/favicon.png" alt="Fyn" class="w-8 h-8 rounded-full flex-shrink-0" />
        <p class="text-white text-sm leading-relaxed">{{ fynSummary }}</p>
      </div>

      <!-- Financial Plans -->
      <MobileAccordionSection
        title="Financial plans"
        icon="📋"
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
        icon="💡"
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
      <MobileAccordionSection title="Net worth breakdown" icon="📊" class="mb-3">
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

    <div v-else class="text-center py-16">
      <span class="text-4xl block mb-3">{{'🔗'}}</span>
      <h3 class="text-base font-bold text-horizon-500 mb-1">No coordination data yet</h3>
      <p class="text-sm text-neutral-500">Your holistic financial picture will appear here</p>
    </div>
  </div>
</template>

<script>
import { mapGetters } from 'vuex';
import { currencyMixin } from '@/mixins/currencyMixin';
import MobileAccordionSection from '@/mobile/components/MobileAccordionSection.vue';
import MobileDataRow from '@/mobile/components/MobileDataRow.vue';

export default {
  name: 'CoordinationDetail',

  components: { MobileAccordionSection, MobileDataRow },

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
