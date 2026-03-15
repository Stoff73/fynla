<template>
  <div class="px-4 pt-4 pb-6">
    <MobileDetailSkeleton v-if="loading" :rows="4" />

    <template v-else-if="hasData">
      <MobileHeroCard title="Estate Planning" :value="formatCurrency(netWorthValue)" subtitle="Net estate value">
        <div v-if="ihtLiability > 0" class="mt-2">
          <span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-violet-50 text-violet-500">
            Inheritance tax: {{ formatCurrency(ihtLiability) }}
          </span>
        </div>
      </MobileHeroCard>
      <MobileFynCard :summary="fynSummary" />

      <!-- IHT Analysis -->
      <MobileAccordionSection title="Inheritance tax analysis" :default-open="true" class="mb-3">
        <div class="divide-y divide-light-gray">
          <MobileDataRow label="Gross estate" :value="grossEstate" type="currency" />
          <MobileDataRow label="Nil-rate band" :value="nrb" type="currency" />
          <MobileDataRow label="Residence nil-rate band" :value="rnrb" type="currency" />
          <MobileDataRow label="Taxable estate" :value="taxableEstate" type="currency" />
          <MobileDataRow
            label="Inheritance tax liability"
            :value="ihtLiability"
            type="currency"
            :status="ihtLiability > 0 ? 'warning' : 'good'"
          />
        </div>
      </MobileAccordionSection>

      <!-- Gifts -->
      <MobileAccordionSection
        title="Gifts (within 7 years)"
        :badge="giftsWithin7Years.length || null"
        class="mb-3"
      >
        <template v-if="giftsWithin7Years.length">
          <div class="divide-y divide-light-gray">
            <MobileGiftCard v-for="gift in giftsWithin7Years" :key="gift.id" :gift="gift" />
          </div>
        </template>
        <p v-else class="px-4 py-6 text-sm text-neutral-500 text-center">No gifts recorded in the last 7 years</p>
      </MobileAccordionSection>

      <!-- Trusts -->
      <MobileAccordionSection
        title="Trusts"
        :badge="trusts.length || null"
        class="mb-3"
      >
        <template v-if="trusts.length">
          <div class="divide-y divide-light-gray">
            <MobileTrustCard v-for="trust in trusts" :key="trust.id" :trust="trust" />
          </div>
        </template>
        <p v-else class="px-4 py-6 text-sm text-neutral-500 text-center">No trusts set up yet</p>
      </MobileAccordionSection>

      <!-- Protection -->
      <MobileAccordionSection
        title="Protection"
        :badge="protectionPolicies.length || null"
        class="mb-3"
      >
        <template v-if="protectionPolicies.length">
          <div class="divide-y divide-light-gray">
            <MobilePolicyCard
              v-for="policy in protectionPolicies"
              :key="policy.id"
              :policy="policy"
              :policy-type="policy.policy_type"
            />
          </div>
          <div class="px-4 py-3 border-t border-light-gray">
            <div class="flex justify-between text-xs">
              <span class="text-neutral-500">Total cover</span>
              <span class="text-horizon-500 font-semibold">{{ formatCurrency(totalProtectionCoverage) }}</span>
            </div>
            <div class="flex justify-between text-xs mt-1">
              <span class="text-neutral-500">Monthly premiums</span>
              <span class="text-horizon-500 font-semibold">{{ formatCurrency(totalProtectionPremium) }}/mo</span>
            </div>
          </div>
        </template>
        <p v-else class="px-4 py-6 text-sm text-neutral-500 text-center">No protection policies added yet</p>
      </MobileAccordionSection>
    </template>

    <MobileEmptyState v-else title="No estate data yet" subtitle="Your estate planning details will appear here" />
  </div>
</template>

<script>
import { mapState, mapGetters } from 'vuex';
import { currencyMixin } from '@/mixins/currencyMixin';
import { IHT_NIL_RATE_BAND, IHT_RESIDENCE_NIL_RATE_BAND } from '@/constants/taxConfig';
import MobileAccordionSection from '@/mobile/components/MobileAccordionSection.vue';
import MobileDataRow from '@/mobile/components/MobileDataRow.vue';
import MobileGiftCard from '@/mobile/components/MobileGiftCard.vue';
import MobileTrustCard from '@/mobile/components/MobileTrustCard.vue';
import MobilePolicyCard from '@/mobile/components/MobilePolicyCard.vue';
import MobileHeroCard from '@/mobile/components/MobileHeroCard.vue';
import MobileFynCard from '@/mobile/components/MobileFynCard.vue';
import MobileDetailSkeleton from '@/mobile/components/MobileDetailSkeleton.vue';
import MobileEmptyState from '@/mobile/components/MobileEmptyState.vue';

export default {
  name: 'EstateDetail',

  components: { MobileAccordionSection, MobileDataRow, MobileGiftCard, MobileTrustCard, MobilePolicyCard, MobileHeroCard, MobileFynCard, MobileDetailSkeleton, MobileEmptyState },

  mixins: [currencyMixin],

  data() {
    return { loading: false };
  },

  computed: {
    ...mapState('estate', ['trusts']),
    ...mapGetters('estate', [
      'netWorthValue',
      'ihtLiability',
      'grossEstate',
      'taxableEstate',
      'giftsWithin7Years',
    ]),
    ...mapGetters('protection', {
      protectionPolicies: 'allPolicies',
      totalProtectionCoverage: 'totalCoverage',
      totalProtectionPremium: 'totalPremium',
    }),

    // IHT allowances from the calculation response or defaults
    nrb() {
      const planning = this.$store.state.estate.secondDeathPlanning;
      return planning?.iht_summary?.current?.nil_rate_band
        || planning?.user_iht_calculation?.nil_rate_band
        || IHT_NIL_RATE_BAND;
    },

    rnrb() {
      const planning = this.$store.state.estate.secondDeathPlanning;
      return planning?.iht_summary?.current?.residence_nil_rate_band
        || planning?.user_iht_calculation?.residence_nil_rate_band
        || IHT_RESIDENCE_NIL_RATE_BAND;
    },

    hasData() {
      return this.trusts?.length > 0 || this.netWorthValue > 0 || this.ihtLiability > 0;
    },

    fynSummary() {
      if (this.ihtLiability > 0) {
        return `Your estate has an estimated inheritance tax liability of ${this.formatCurrency(this.ihtLiability)}.`;
      }
      return 'Your estate currently has no inheritance tax liability.';
    },
  },

  async created() {
    this.loading = true;
    try {
      await Promise.all([
        this.$store.dispatch('estate/fetchEstateData'),
        this.$store.dispatch('protection/fetchProtectionData').catch(() => {}),
      ]);
      // Fetch IHT calculation to populate ihtLiability, taxableEstate, grossEstate
      await this.$store.dispatch('estate/calculateIHTPlanning').catch(() => {});
    } catch {
      // Data unavailable
    } finally {
      this.loading = false;
    }
  },
};
</script>
