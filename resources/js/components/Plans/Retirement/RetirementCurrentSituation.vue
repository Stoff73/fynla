<template>
  <div class="mb-6">
    <PlanSectionHeader title="Your Pension Plans" subtitle="Your pension and retirement overview" color="horizon" />

    <div class="space-y-4">
      <!-- Pensions -->
      <div v-if="situation.dc_pensions && situation.dc_pensions.length" class="bg-white rounded-lg shadow-sm border border-light-gray p-5">
        <h3 class="text-sm font-semibold text-horizon-500 mb-3">Pensions</h3>
        <div class="space-y-2">
          <div
            v-for="pension in situation.dc_pensions"
            :key="pension.id"
            class="flex items-center justify-between py-2 border-b border-light-gray last:border-b-0"
          >
            <div>
              <p class="text-sm font-medium text-horizon-500">{{ pension.scheme_name }}</p>
              <p class="text-xs text-neutral-500">
                {{ pension.provider || '' }}
                <span v-if="pension.monthly_contribution"> &middot; {{ formatCurrency(pension.monthly_contribution) }}/month</span>
                <span v-if="pension.employer_contribution"> + {{ formatCurrency(pension.employer_contribution) }} employer</span>
              </p>
            </div>
            <p class="text-sm font-semibold text-horizon-500">{{ formatCurrency(pension.current_value) }}</p>
          </div>
        </div>
      </div>

      <!-- Defined Benefit Pensions -->
      <div v-if="situation.db_pensions && situation.db_pensions.length" class="bg-white rounded-lg shadow-sm border border-light-gray p-5">
        <h3 class="text-sm font-semibold text-horizon-500 mb-3">Defined Benefit Pensions</h3>
        <div class="space-y-2">
          <div
            v-for="pension in situation.db_pensions"
            :key="pension.id"
            class="flex items-center justify-between py-2 border-b border-light-gray last:border-b-0"
          >
            <div>
              <p class="text-sm font-medium text-horizon-500">{{ pension.scheme_name }}</p>
              <p v-if="pension.normal_retirement_age" class="text-xs text-neutral-500">
                Retirement age: {{ pension.normal_retirement_age }}
              </p>
            </div>
            <p class="text-sm font-semibold text-horizon-500">{{ formatCurrency(pension.projected_annual_pension) }}/year</p>
          </div>
        </div>
      </div>

      <!-- State Pension -->
      <div v-if="situation.state_pension" class="bg-white rounded-lg shadow-sm border border-light-gray p-5">
        <h3 class="text-sm font-semibold text-horizon-500 mb-3">State Pension</h3>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
          <div class="bg-savannah-100 rounded-lg p-3">
            <p class="text-xs text-neutral-500">Weekly Amount</p>
            <p class="text-sm font-bold text-horizon-500">{{ formatCurrency(situation.state_pension.weekly_amount) }}</p>
          </div>
          <div class="bg-savannah-100 rounded-lg p-3">
            <p class="text-xs text-neutral-500">Annual Amount</p>
            <p class="text-sm font-bold text-horizon-500">{{ formatCurrency(situation.state_pension.annual_amount) }}</p>
          </div>
          <div class="bg-savannah-100 rounded-lg p-3">
            <p class="text-xs text-neutral-500">National Insurance Years</p>
            <p class="text-sm font-bold text-horizon-500">{{ situation.state_pension.ni_years }}</p>
          </div>
          <div v-if="situation.state_pension.state_pension_age" class="bg-savannah-100 rounded-lg p-3">
            <p class="text-xs text-neutral-500">State Pension Age</p>
            <p class="text-sm font-bold text-horizon-500">{{ situation.state_pension.state_pension_age }}</p>
          </div>
        </div>
      </div>

      <!-- Key metrics -->
      <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
        <div class="bg-white rounded-lg border border-light-gray p-4 text-center">
          <p class="text-xs text-neutral-500 uppercase">Years to Retirement</p>
          <p class="text-lg font-bold text-horizon-500">{{ situation.summary?.years_to_retirement ?? 'N/A' }}</p>
        </div>
        <div class="bg-white rounded-lg border border-light-gray p-4 text-center">
          <p class="text-xs text-neutral-500 uppercase">Income Gap at Retirement</p>
          <p class="text-lg font-bold" :class="incomeGapColor">
            {{ formatCurrency(Math.max(0, situation.summary?.income_gap || 0)) }}/year
          </p>
          <p v-if="retiresBeforeSPA && incomeGapAfterSPA !== null" class="text-xs text-neutral-500 mt-1">
            {{ formatCurrency(incomeGapAfterSPA) }}/year from age {{ situation.summary?.state_pension_age }}
          </p>
        </div>
        <div class="bg-white rounded-lg border border-light-gray p-4 text-center">
          <p class="text-xs text-neutral-500 uppercase">Pension Value at Retirement</p>
          <p class="text-lg font-bold text-horizon-500">{{ formatCurrency(situation.summary?.total_dc_value || 0) }}</p>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { currencyMixin } from '@/mixins/currencyMixin';
import PlanSectionHeader from '@/components/Plans/Shared/PlanSectionHeader.vue';

export default {
  name: 'RetirementCurrentSituation',
  components: { PlanSectionHeader },
  mixins: [currencyMixin],
  props: {
    situation: { type: Object, required: true },
  },
  computed: {
    retiresBeforeSPA() {
      return this.situation.summary?.retires_before_spa || false;
    },
    incomeGapAfterSPA() {
      return this.situation.summary?.income_gap_after_spa ?? null;
    },
    incomeGapColor() {
      const gap = this.situation.summary?.income_gap || 0;
      return gap <= 0 ? 'text-spring-700' : 'text-raspberry-700';
    },
  },
};
</script>
