<template>
  <div class="px-4 pt-4 pb-6">
    <div v-if="loading" class="space-y-3">
      <div class="bg-white rounded-xl p-6 animate-pulse">
        <div class="w-24 h-8 bg-savannah-100 rounded mx-auto"></div>
      </div>
      <div v-for="n in 4" :key="n" class="bg-white rounded-xl p-4 animate-pulse">
        <div class="w-40 h-4 bg-savannah-100 rounded"></div>
      </div>
    </div>

    <template v-else-if="hasData">
      <!-- Hero -->
      <div class="bg-white rounded-xl border border-light-gray p-6 text-center mb-4">
        <span class="text-3xl block mb-2">{{'🏠'}}</span>
        <h2 class="text-lg font-bold text-horizon-500">Estate Planning</h2>
        <p class="text-2xl font-black text-horizon-500 mt-3">{{ formatCurrency(netWorthValue) }}</p>
        <p class="text-xs text-neutral-500 mt-1">Net estate value</p>
        <div v-if="ihtLiability > 0" class="mt-2">
          <span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-violet-50 text-violet-500">
            IHT: {{ formatCurrency(ihtLiability) }}
          </span>
        </div>
      </div>

      <!-- Fyn -->
      <div class="bg-horizon-500 rounded-xl p-4 flex items-start gap-3 mb-4">
        <img src="/images/logos/favicon.png" alt="Fyn" class="w-8 h-8 rounded-full flex-shrink-0" />
        <p class="text-white text-sm leading-relaxed">{{ fynSummary }}</p>
      </div>

      <!-- Estate Assets -->
      <MobileAccordionSection
        title="Estate assets"
        icon="🏘️"
        :badge="assets.length || null"
        :default-open="true"
        class="mb-3"
      >
        <template v-if="assets.length">
          <div class="divide-y divide-light-gray">
            <MobileEstateAssetCard v-for="asset in assets" :key="asset.id" :asset="asset" />
          </div>
        </template>
        <p v-else class="px-4 py-6 text-sm text-neutral-500 text-center">No estate assets added yet</p>
      </MobileAccordionSection>

      <!-- IHT Analysis -->
      <MobileAccordionSection title="Inheritance tax analysis" icon="📊" class="mb-3">
        <div class="divide-y divide-light-gray">
          <MobileDataRow label="Gross estate" :value="grossEstate" type="currency" />
          <MobileDataRow label="Nil-rate band" :value="325000" type="currency" />
          <MobileDataRow label="Residence nil-rate band" :value="175000" type="currency" />
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
        icon="🎁"
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
        icon="📜"
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
    </template>

    <div v-else class="text-center py-16">
      <span class="text-4xl block mb-3">{{'🏠'}}</span>
      <h3 class="text-base font-bold text-horizon-500 mb-1">No estate data yet</h3>
      <p class="text-sm text-neutral-500">Your estate planning details will appear here</p>
    </div>
  </div>
</template>

<script>
import { mapState, mapGetters } from 'vuex';
import { currencyMixin } from '@/mixins/currencyMixin';
import MobileAccordionSection from '@/mobile/components/MobileAccordionSection.vue';
import MobileDataRow from '@/mobile/components/MobileDataRow.vue';
import MobileEstateAssetCard from '@/mobile/components/MobileEstateAssetCard.vue';
import MobileGiftCard from '@/mobile/components/MobileGiftCard.vue';
import MobileTrustCard from '@/mobile/components/MobileTrustCard.vue';

export default {
  name: 'EstateDetail',

  components: { MobileAccordionSection, MobileDataRow, MobileEstateAssetCard, MobileGiftCard, MobileTrustCard },

  mixins: [currencyMixin],

  data() {
    return { loading: false };
  },

  computed: {
    ...mapState('estate', ['assets', 'trusts']),
    ...mapGetters('estate', [
      'netWorthValue',
      'ihtLiability',
      'grossEstate',
      'taxableEstate',
      'giftsWithin7Years',
    ]),

    hasData() {
      return this.assets?.length > 0 || this.trusts?.length > 0 || this.netWorthValue > 0;
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
      await this.$store.dispatch('estate/fetchEstateData');
    } catch {
      // Data unavailable
    } finally {
      this.loading = false;
    }
  },
};
</script>
