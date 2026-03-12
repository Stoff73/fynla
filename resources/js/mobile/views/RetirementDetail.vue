<template>
  <div class="px-4 pt-4 pb-6">
    <div v-if="loading" class="space-y-3">
      <div class="bg-white rounded-xl p-6 animate-pulse">
        <div class="w-24 h-8 bg-savannah-100 rounded mx-auto"></div>
      </div>
      <div v-for="n in 5" :key="n" class="bg-white rounded-xl p-4 animate-pulse">
        <div class="w-40 h-4 bg-savannah-100 rounded"></div>
      </div>
    </div>

    <template v-else-if="hasData">
      <!-- Hero -->
      <div class="bg-white rounded-xl border border-light-gray p-6 text-center mb-4">
        <span class="text-3xl block mb-2">{{'🏦'}}</span>
        <h2 class="text-lg font-bold text-horizon-500">Retirement</h2>
        <p class="text-2xl font-black text-horizon-500 mt-3">{{ formatCurrency(projectedIncome) }}</p>
        <p class="text-xs text-neutral-500 mt-1">Projected retirement income</p>
        <p v-if="yearsToRetirement" class="text-xs text-neutral-400 mt-1">{{ yearsToRetirement }} years to retirement</p>
      </div>

      <!-- Fyn -->
      <div class="bg-horizon-500 rounded-xl p-4 flex items-start gap-3 mb-4">
        <img src="/images/logos/favicon.png" alt="Fyn" class="w-8 h-8 rounded-full flex-shrink-0" />
        <p class="text-white text-sm leading-relaxed">{{ fynSummary }}</p>
      </div>

      <!-- DC Pensions -->
      <MobileAccordionSection
        v-if="dcPensions.length"
        title="Defined contribution pensions"
        icon="💼"
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
        icon="🏛️"
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
        icon="🇬🇧"
        class="mb-3"
      >
        <div class="divide-y divide-light-gray">
          <MobilePensionCard :pension="statePension" pension-type="state" />
        </div>
      </MobileAccordionSection>

      <!-- Projections -->
      <MobileAccordionSection title="Projections" icon="📊" class="mb-3">
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
      <MobileAccordionSection title="Annual allowance" icon="📋" class="mb-3">
        <div v-if="annualAllowance" class="divide-y divide-light-gray">
          <MobileDataRow label="Standard allowance" :value="annualAllowance.standard_allowance || 60000" type="currency" />
          <MobileDataRow label="Used this year" :value="annualAllowance.used || 0" type="currency" />
          <MobileDataRow
            label="Remaining"
            :value="(annualAllowance.standard_allowance || 60000) - (annualAllowance.used || 0)"
            type="currency"
            :status="(annualAllowance.standard_allowance || 60000) - (annualAllowance.used || 0) > 0 ? 'good' : 'warning'"
          />
          <MobileDataRow v-if="annualAllowance.carry_forward" label="Carry forward available" :value="annualAllowance.carry_forward" type="currency" />
        </div>
        <p v-else class="px-4 py-6 text-sm text-neutral-500 text-center">No annual allowance data</p>
      </MobileAccordionSection>
    </template>

    <div v-else class="text-center py-16">
      <span class="text-4xl block mb-3">{{'🏦'}}</span>
      <h3 class="text-base font-bold text-horizon-500 mb-1">No retirement data yet</h3>
      <p class="text-sm text-neutral-500">Your pensions and projections will appear here</p>
    </div>
  </div>
</template>

<script>
import { mapState, mapGetters } from 'vuex';
import { currencyMixin } from '@/mixins/currencyMixin';
import MobileAccordionSection from '@/mobile/components/MobileAccordionSection.vue';
import MobileDataRow from '@/mobile/components/MobileDataRow.vue';
import MobilePensionCard from '@/mobile/components/MobilePensionCard.vue';

export default {
  name: 'RetirementDetail',

  components: { MobileAccordionSection, MobileDataRow, MobilePensionCard },

  mixins: [currencyMixin],

  data() {
    return { loading: false };
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
