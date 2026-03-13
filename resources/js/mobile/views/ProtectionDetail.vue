<template>
  <div class="px-4 pt-4 pb-6">
    <MobileDetailSkeleton v-if="loading" :rows="3" />

    <template v-else-if="hasData">
      <MobileHeroCard title="Protection" :value="formatCurrency(totalCoverage)" subtitle="Total cover">
        <div v-if="coverageGaps.length" class="mt-2">
          <span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-violet-50 text-violet-500">
            {{ coverageGaps.length }} gap{{ coverageGaps.length > 1 ? 's' : '' }} identified
          </span>
        </div>
      </MobileHeroCard>
      <MobileFynCard :summary="fynSummary" />

      <!-- Policies -->
      <MobileAccordionSection
        title="Policies"
        :badge="allPolicies.length || null"
        :default-open="true"
        class="mb-3"
      >
        <template v-if="allPolicies.length">
          <div class="divide-y divide-light-gray">
            <MobilePolicyCard
              v-for="policy in allPolicies"
              :key="policy.id"
              :policy="policy"
              :policy-type="policy.policy_type"
            />
          </div>
        </template>
        <p v-else class="px-4 py-6 text-sm text-neutral-500 text-center">No policies added yet</p>
      </MobileAccordionSection>

      <!-- Coverage Analysis -->
      <MobileAccordionSection title="Coverage analysis" class="mb-3">
        <div class="divide-y divide-light-gray">
          <MobileDataRow label="Total cover" :value="totalCoverage" type="currency" />
          <MobileDataRow label="Monthly premiums" :value="totalPremium" type="currency" />
          <MobileDataRow
            label="Income protection"
            :value="hasIncomeProtection ? 'Yes' : 'No'"
            :status="hasIncomeProtection ? 'good' : 'warning'"
          />
          <MobileDataRow
            label="Critical illness cover"
            :value="hasCriticalIllness ? 'Yes' : 'No'"
            :status="hasCriticalIllness ? 'good' : 'warning'"
          />
        </div>
      </MobileAccordionSection>

      <!-- Gaps & Recommendations -->
      <MobileAccordionSection
        v-if="coverageGaps.length || recommendations.length"
        title="Gaps & recommendations"
        :badge="coverageGaps.length + recommendations.length || null"
        class="mb-3"
      >
        <div class="divide-y divide-light-gray">
          <div v-for="gap in coverageGaps" :key="'gap-' + gap.type" class="px-4 py-3">
            <div class="flex items-center gap-2 mb-1">
              <span class="w-2 h-2 rounded-full bg-raspberry-500"></span>
              <p class="text-sm font-medium text-horizon-500">{{ gap.description || gap.type }}</p>
            </div>
            <p v-if="gap.recommendation" class="text-xs text-neutral-500 ml-4">{{ gap.recommendation }}</p>
          </div>
          <div v-for="rec in recommendations" :key="'rec-' + rec.id" class="px-4 py-3">
            <div class="flex items-center gap-2 mb-1">
              <span
                class="px-1.5 py-0.5 rounded text-[10px] font-bold uppercase"
                :class="rec.priority === 'high' ? 'bg-raspberry-50 text-raspberry-500' : 'bg-violet-50 text-violet-500'"
              >
                {{ rec.priority || 'medium' }}
              </span>
              <p class="text-sm font-medium text-horizon-500">{{ rec.title || rec.description }}</p>
            </div>
            <p v-if="rec.description && rec.title" class="text-xs text-neutral-500 ml-4">{{ rec.description }}</p>
          </div>
        </div>
      </MobileAccordionSection>
    </template>

    <MobileEmptyState v-else title="No protection data yet" subtitle="Your protection policies will appear here" />
  </div>
</template>

<script>
import { mapGetters } from 'vuex';
import { currencyMixin } from '@/mixins/currencyMixin';
import MobileAccordionSection from '@/mobile/components/MobileAccordionSection.vue';
import MobileDataRow from '@/mobile/components/MobileDataRow.vue';
import MobilePolicyCard from '@/mobile/components/MobilePolicyCard.vue';
import MobileHeroCard from '@/mobile/components/MobileHeroCard.vue';
import MobileFynCard from '@/mobile/components/MobileFynCard.vue';
import MobileDetailSkeleton from '@/mobile/components/MobileDetailSkeleton.vue';
import MobileEmptyState from '@/mobile/components/MobileEmptyState.vue';

export default {
  name: 'ProtectionDetail',

  components: { MobileAccordionSection, MobileDataRow, MobilePolicyCard, MobileHeroCard, MobileFynCard, MobileDetailSkeleton, MobileEmptyState },

  mixins: [currencyMixin],

  data() {
    return {
      loading: false,
    };
  },

  computed: {
    ...mapGetters('protection', [
      'allPolicies',
      'totalCoverage',
      'totalPremium',
      'hasIncomeProtection',
      'hasCriticalIllness',
      'priorityRecommendations',
    ]),

    recommendations() {
      return this.priorityRecommendations || [];
    },

    // coverageGaps getter returns object — convert to array for template
    coverageGaps() {
      const gaps = this.$store.getters['protection/coverageGaps'];
      if (!gaps || typeof gaps !== 'object') return [];
      if (Array.isArray(gaps)) return gaps;
      return Object.entries(gaps)
        .filter(([, data]) => data.gap > 0)
        .map(([type, data]) => ({ type, ...data }));
    },

    hasData() {
      return this.allPolicies?.length > 0 || this.totalCoverage > 0;
    },

    fynSummary() {
      if (this.coverageGaps?.length > 0) {
        return `You have ${this.coverageGaps.length} protection gap${this.coverageGaps.length > 1 ? 's' : ''} that may need attention.`;
      }
      return 'Your protection cover looks solid.';
    },
  },

  async created() {
    await this.loadData();
  },

  methods: {
    async loadData() {
      this.loading = true;
      try {
        await this.$store.dispatch('protection/fetchProtectionData');
      } catch {
        // Data unavailable
      } finally {
        this.loading = false;
      }
    },
  },
};
</script>
