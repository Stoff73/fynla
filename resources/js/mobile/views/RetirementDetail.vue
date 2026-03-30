<template>
  <div class="px-4 pt-4 pb-6">
    <MobileDetailSkeleton v-if="loading" :rows="5" />

    <template v-else-if="hasData">
      <MobileHeroCard title="Retirement" :value="formatCurrency(projectedIncome)" subtitle="Projected retirement income">
        <p v-if="yearsToRetirement" class="text-xs text-neutral-400 mt-1">{{ yearsToRetirement }} years to retirement</p>
      </MobileHeroCard>
      <MobileFynCard :summary="fynSummary" />

      <!-- DC Pensions -->
      <MobileAccordionSection
        v-if="dcPensions.length"
        title="Defined contribution pensions"
        :badge="dcPensions.length"
        :default-open="true"
        class="mb-3"
      >
        <div class="divide-y divide-light-gray">
          <MobilePensionCard
            v-for="pension in dcPensions"
            :key="pension.id"
            :pension="pension"
            pension-type="dc"
          />
        </div>
      </MobileAccordionSection>

      <!-- DB Pensions -->
      <MobileAccordionSection
        v-if="dbPensions.length"
        title="Defined benefit pensions"
        :badge="dbPensions.length"
        class="mb-3"
      >
        <div class="divide-y divide-light-gray">
          <MobilePensionCard
            v-for="pension in dbPensions"
            :key="pension.id"
            :pension="pension"
            pension-type="db"
          />
        </div>
      </MobileAccordionSection>

      <!-- State Pension -->
      <MobileAccordionSection
        v-if="statePension"
        title="State pension"
        class="mb-3"
      >
        <div class="divide-y divide-light-gray">
          <MobilePensionCard :pension="statePension" pension-type="state" />
        </div>
      </MobileAccordionSection>

      <!-- Projections -->
      <MobileAccordionSection title="Projections" class="mb-3">
        <div class="divide-y divide-light-gray">
          <MobileDataRow label="Projected annual income" :value="projectedIncome" type="currency" />
          <MobileDataRow label="Target income" :value="targetIncome" type="currency" />
          <MobileDataRow
            label="Income gap"
            :value="incomeGap > 0 ? incomeGap : 0"
            type="currency"
            :status="incomeGap > 0 ? 'warning' : 'good'"
          />
          <MobileDataRow label="Total pension wealth" :value="totalPensionWealth" type="currency" />
          <MobileDataRow label="Years to retirement" :value="yearsToRetirement" />
        </div>
      </MobileAccordionSection>

      <!-- Annual Allowance -->
      <MobileAccordionSection title="Annual allowance" class="mb-3">
        <div v-if="annualAllowance" class="divide-y divide-light-gray">
          <MobileDataRow label="Standard allowance" :value="annualAllowance.standard_allowance || ANNUAL_ALLOWANCE" type="currency" />
          <MobileDataRow label="Used this year" :value="annualAllowance.used || 0" type="currency" />
          <MobileDataRow
            label="Remaining"
            :value="(annualAllowance.standard_allowance || ANNUAL_ALLOWANCE) - (annualAllowance.used || 0)"
            type="currency"
            :status="(annualAllowance.standard_allowance || ANNUAL_ALLOWANCE) - (annualAllowance.used || 0) > 0 ? 'good' : 'warning'"
          />
          <MobileDataRow v-if="annualAllowance.carry_forward" label="Carry forward available" :value="annualAllowance.carry_forward" type="currency" />
        </div>
        <p v-else class="px-4 py-6 text-sm text-neutral-500 text-center">No annual allowance data</p>
      </MobileAccordionSection>
    </template>

    <MobileEmptyState v-else title="No retirement data yet" subtitle="Your pensions and projections will appear here" />
  </div>
</template>

<script>
import { mapState, mapGetters } from 'vuex';
import { currencyMixin } from '@/mixins/currencyMixin';
import { ANNUAL_ALLOWANCE } from '@/constants/taxConfig';
import MobileAccordionSection from '@/mobile/components/MobileAccordionSection.vue';
import MobileDataRow from '@/mobile/components/MobileDataRow.vue';
import MobilePensionCard from '@/mobile/components/MobilePensionCard.vue';
import MobileHeroCard from '@/mobile/components/MobileHeroCard.vue';
import MobileFynCard from '@/mobile/components/MobileFynCard.vue';
import MobileDetailSkeleton from '@/mobile/components/MobileDetailSkeleton.vue';
import MobileEmptyState from '@/mobile/components/MobileEmptyState.vue';

export default {
  name: 'RetirementDetail',

  components: { MobileAccordionSection, MobileDataRow, MobilePensionCard, MobileHeroCard, MobileFynCard, MobileDetailSkeleton, MobileEmptyState },

  mixins: [currencyMixin],

  data() {
    return { loading: false, ANNUAL_ALLOWANCE };
  },

  computed: {
    ...mapGetters('retirement', [
      'dcPensions',
      'dbPensions',
      'totalPensionWealth',
      'projectedIncome',
      'targetIncome',
      'incomeGap',
      'yearsToRetirement',
    ]),

    statePension() {
      return this.$store.state.retirement.statePension;
    },

    annualAllowance() {
      return this.$store.state.retirement.annualAllowance;
    },

    hasData() {
      return this.dcPensions?.length > 0 || this.dbPensions?.length > 0 || this.statePension;
    },

    fynSummary() {
      if (this.incomeGap > 0) {
        return `Your projected retirement income is ${this.formatCurrency(this.incomeGap)} below your target.`;
      }
      return 'Your projected retirement income meets your target.';
    },
  },

  async created() {
    this.loading = true;
    try {
      await this.$store.dispatch('retirement/fetchRetirementData');
      // Fetch analysis (projected income, target, gap) and annual allowance in parallel
      await Promise.all([
        this.$store.dispatch('retirement/analyseRetirement', {}).catch(() => {}),
        this.$store.dispatch('retirement/fetchAnnualAllowance').catch(() => {}),
      ]);
    } catch {
      // Data unavailable
    } finally {
      this.loading = false;
    }
  },
};
</script>
