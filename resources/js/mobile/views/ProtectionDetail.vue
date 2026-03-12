<template>
  <div class="px-4 pt-4 pb-6">
    <!-- Loading -->
    <div v-if="loading" class="space-y-3">
      <div class="bg-white rounded-xl p-6 animate-pulse">
        <div class="w-24 h-8 bg-savannah-100 rounded mx-auto"></div>
        <div class="w-32 h-4 bg-savannah-100 rounded mx-auto mt-2"></div>
      </div>
      <div v-for="n in 3" :key="n" class="bg-white rounded-xl p-4 animate-pulse">
        <div class="w-40 h-4 bg-savannah-100 rounded"></div>
      </div>
    </div>

    <template v-else-if="hasData">
      <!-- Hero card -->
      <div class="bg-white rounded-xl border border-light-gray p-6 text-center mb-4">
        <span class="text-3xl block mb-2">{{'🛡️'}}</span>
        <h2 class="text-lg font-bold text-horizon-500">Protection</h2>
        <p class="text-2xl font-black text-horizon-500 mt-3">{{ formatCurrency(totalCoverage) }}</p>
        <p class="text-xs text-neutral-500 mt-1">Total cover</p>
        <div v-if="coverageGaps.length" class="mt-2">
          <span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-violet-50 text-violet-500">
            {{ coverageGaps.length }} gap{{ coverageGaps.length > 1 ? 's' : '' }} identified
          </span>
        </div>
      </div>

      <!-- Fyn summary -->
      <div class="bg-horizon-500 rounded-xl p-4 flex items-start gap-3 mb-4">
        <img src="/images/logos/favicon.png" alt="Fyn" class="w-8 h-8 rounded-full flex-shrink-0" />
        <p class="text-white text-sm leading-relaxed">{{ fynSummary }}</p>
      </div>

      <!-- Policies -->
      <MobileAccordionSection
        title="Policies"
        icon="📋"
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
              :policy-type="policy._type"
            />
          </div>
        </template>
        <p v-else class="px-4 py-6 text-sm text-neutral-500 text-center">No policies added yet</p>
      </MobileAccordionSection>

      <!-- Coverage Analysis -->
      <MobileAccordionSection title="Coverage analysis" icon="📊" class="mb-3">
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
        icon="⚠️"
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

    <!-- Empty state -->
    <div v-else class="text-center py-16">
      <span class="text-4xl block mb-3">{{'🛡️'}}</span>
      <h3 class="text-base font-bold text-horizon-500 mb-1">No protection data yet</h3>
      <p class="text-sm text-neutral-500">Your protection policies will appear here</p>
    </div>
  </div>
</template>

<script>
import { mapGetters } from 'vuex';
import { currencyMixin } from '@/mixins/currencyMixin';
import MobileAccordionSection from '@/mobile/components/MobileAccordionSection.vue';
import MobileDataRow from '@/mobile/components/MobileDataRow.vue';
import MobilePolicyCard from '@/mobile/components/MobilePolicyCard.vue';

export default {
  name: 'ProtectionDetail',

  components: { MobileAccordionSection, MobileDataRow, MobilePolicyCard },

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
      'coverageGaps',
      'hasIncomeProtection',
      'hasCriticalIllness',
      'priorityRecommendations',
    ]),

    recommendations() {
      return this.priorityRecommendations || [];
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
