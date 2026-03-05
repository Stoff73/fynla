<template>
  <div class="mb-6">
    <PlanSectionHeader title="Current Situation" subtitle="Your protection coverage overview" color="spring" />

    <div class="space-y-4">
      <!-- Coverage Analysis: Need vs Have vs Gap -->
      <div v-if="coverageAnalysis" class="bg-white rounded-lg shadow-sm border border-light-gray p-5">
        <h3 class="text-sm font-semibold text-horizon-500 mb-3">Coverage Analysis</h3>
        <div class="space-y-4">
          <!-- Life Insurance -->
          <div class="bg-eggshell-500 rounded-lg p-4">
            <div class="flex items-center justify-between mb-2">
              <p class="text-sm font-medium text-horizon-500">Life Insurance</p>
              <span class="text-xs font-medium px-2 py-0.5 rounded-full" :class="statusBadge(coverageAnalysis.life_insurance?.status)">
                {{ coverageAnalysis.life_insurance?.status || 'Unknown' }}
              </span>
            </div>
            <div class="grid grid-cols-3 gap-2 text-center">
              <div>
                <p class="text-xs text-neutral-500">Need</p>
                <p class="text-sm font-semibold text-horizon-500">{{ formatCurrency(coverageAnalysis.life_insurance?.need || 0) }}</p>
              </div>
              <div>
                <p class="text-xs text-neutral-500">Have</p>
                <p class="text-sm font-semibold text-horizon-500">{{ formatCurrency(coverageAnalysis.life_insurance?.coverage || 0) }}</p>
              </div>
              <div>
                <p class="text-xs text-neutral-500">Gap</p>
                <p class="text-sm font-semibold" :class="gapColor(coverageAnalysis.life_insurance?.gap)">
                  {{ formatCurrency(coverageAnalysis.life_insurance?.gap || 0) }}
                </p>
              </div>
            </div>
            <div class="mt-2 bg-horizon-200 rounded-full h-2">
              <div
                class="h-2 rounded-full transition-all"
                :class="progressBarColor(coverageAnalysis.life_insurance?.coverage_percentage)"
                :style="{ width: Math.min(100, coverageAnalysis.life_insurance?.coverage_percentage || 0) + '%' }"
              ></div>
            </div>
            <!-- How we calculated the need -->
            <div v-if="needsBreakdown" class="mt-3 pt-3 border-t border-light-gray">
              <p class="text-xs font-medium text-neutral-500 uppercase mb-2">How we calculated your need</p>
              <div class="space-y-1">
                <div v-if="needsBreakdown.human_capital > 0" class="flex justify-between text-xs">
                  <span class="text-neutral-500">Income replacement capital ({{ incomeAnalysis.net_income_difference > 0 ? formatCurrency(incomeAnalysis.net_income_difference) + '/year' : 'net income' }} at 4.7% drawdown)</span>
                  <span class="text-horizon-500 font-medium">{{ formatCurrency(needsBreakdown.human_capital) }}</span>
                </div>
                <div v-if="needsBreakdown.debt_protection > 0" class="flex justify-between text-xs">
                  <span class="text-neutral-500">Outstanding debts (mortgage + other)</span>
                  <span class="text-horizon-500 font-medium">{{ formatCurrency(needsBreakdown.debt_protection) }}</span>
                </div>
                <div v-if="needsBreakdown.education_funding > 0" class="flex justify-between text-xs">
                  <span class="text-neutral-500">Education funding for dependants</span>
                  <span class="text-horizon-500 font-medium">{{ formatCurrency(needsBreakdown.education_funding) }}</span>
                </div>
                <div v-if="needsBreakdown.final_expenses > 0" class="flex justify-between text-xs">
                  <span class="text-neutral-500">Final expenses (funeral and administration)</span>
                  <span class="text-horizon-500 font-medium">{{ formatCurrency(needsBreakdown.final_expenses) }}</span>
                </div>
                <div class="flex justify-between text-xs pt-1 border-t border-savannah-100 font-medium">
                  <span class="text-horizon-500">Total need</span>
                  <span class="text-horizon-500">{{ formatCurrency(coverageAnalysis.life_insurance?.need || 0) }}</span>
                </div>
                <div v-if="coverageAnalysis.life_insurance?.coverage > 0" class="flex justify-between text-xs">
                  <span class="text-neutral-500">Less: existing life cover</span>
                  <span class="text-spring-700 font-medium">-{{ formatCurrency(coverageAnalysis.life_insurance.coverage) }}</span>
                </div>
                <div v-if="coverageAnalysis.life_insurance?.gap > 0" class="flex justify-between text-xs pt-1 border-t border-savannah-100 font-semibold">
                  <span class="text-raspberry-700">Shortfall</span>
                  <span class="text-raspberry-700">{{ formatCurrency(coverageAnalysis.life_insurance.gap) }}</span>
                </div>
              </div>
            </div>
          </div>

          <!-- Critical Illness -->
          <div class="bg-eggshell-500 rounded-lg p-4">
            <div class="flex items-center justify-between mb-2">
              <p class="text-sm font-medium text-horizon-500">Critical Illness</p>
              <span class="text-xs font-medium px-2 py-0.5 rounded-full" :class="statusBadge(coverageAnalysis.critical_illness?.status)">
                {{ coverageAnalysis.critical_illness?.status || 'Unknown' }}
              </span>
            </div>
            <div class="grid grid-cols-3 gap-2 text-center">
              <div>
                <p class="text-xs text-neutral-500">Need</p>
                <p class="text-sm font-semibold text-horizon-500">{{ formatCurrency(coverageAnalysis.critical_illness?.need || 0) }}</p>
              </div>
              <div>
                <p class="text-xs text-neutral-500">Have</p>
                <p class="text-sm font-semibold text-horizon-500">{{ formatCurrency(coverageAnalysis.critical_illness?.coverage || 0) }}</p>
              </div>
              <div>
                <p class="text-xs text-neutral-500">Gap</p>
                <p class="text-sm font-semibold" :class="gapColor(coverageAnalysis.critical_illness?.gap)">
                  {{ formatCurrency(coverageAnalysis.critical_illness?.gap || 0) }}
                </p>
              </div>
            </div>
            <div class="mt-2 bg-horizon-200 rounded-full h-2">
              <div
                class="h-2 rounded-full transition-all"
                :class="progressBarColor(coverageAnalysis.critical_illness?.coverage_percentage)"
                :style="{ width: Math.min(100, coverageAnalysis.critical_illness?.coverage_percentage || 0) + '%' }"
              ></div>
            </div>
            <!-- How we calculated the need -->
            <div v-if="incomeAnalysis.gross_income > 0" class="mt-3 pt-3 border-t border-light-gray">
              <p class="text-xs font-medium text-neutral-500 uppercase mb-2">How we calculated your need</p>
              <div class="space-y-1">
                <div class="flex justify-between text-xs">
                  <span class="text-neutral-500">3 &times; your gross annual income of {{ formatCurrency(incomeAnalysis.gross_income) }}</span>
                  <span class="text-horizon-500 font-medium">{{ formatCurrency(coverageAnalysis.critical_illness?.need || 0) }}</span>
                </div>
                <div class="flex justify-between text-xs pt-1 border-t border-savannah-100 font-medium">
                  <span class="text-horizon-500">Total need</span>
                  <span class="text-horizon-500">{{ formatCurrency(coverageAnalysis.critical_illness?.need || 0) }}</span>
                </div>
                <div v-if="coverageAnalysis.critical_illness?.coverage > 0" class="flex justify-between text-xs">
                  <span class="text-neutral-500">Less: existing critical illness cover</span>
                  <span class="text-spring-700 font-medium">-{{ formatCurrency(coverageAnalysis.critical_illness.coverage) }}</span>
                </div>
                <div v-if="coverageAnalysis.critical_illness?.gap > 0" class="flex justify-between text-xs pt-1 border-t border-savannah-100 font-semibold">
                  <span class="text-raspberry-700">Shortfall</span>
                  <span class="text-raspberry-700">{{ formatCurrency(coverageAnalysis.critical_illness.gap) }}</span>
                </div>
              </div>
              <p class="text-xs text-horizon-400 mt-2">A lump sum to cover living costs and treatment if diagnosed with a serious illness.</p>
            </div>
          </div>

          <!-- Income Protection -->
          <div class="bg-eggshell-500 rounded-lg p-4">
            <div class="flex items-center justify-between mb-2">
              <p class="text-sm font-medium text-horizon-500">Income Protection</p>
              <span class="text-xs font-medium px-2 py-0.5 rounded-full" :class="statusBadge(coverageAnalysis.income_protection?.status)">
                {{ coverageAnalysis.income_protection?.status || 'Unknown' }}
              </span>
            </div>
            <div class="grid grid-cols-3 gap-2 text-center">
              <div>
                <p class="text-xs text-neutral-500">Need</p>
                <p class="text-sm font-semibold text-horizon-500">{{ formatCurrency(coverageAnalysis.income_protection?.need || 0) }}/month</p>
              </div>
              <div>
                <p class="text-xs text-neutral-500">Have</p>
                <p class="text-sm font-semibold text-horizon-500">{{ formatCurrency(coverageAnalysis.income_protection?.coverage || 0) }}/month</p>
              </div>
              <div>
                <p class="text-xs text-neutral-500">Gap</p>
                <p class="text-sm font-semibold" :class="gapColor(coverageAnalysis.income_protection?.gap)">
                  {{ formatCurrency(coverageAnalysis.income_protection?.gap || 0) }}/month
                </p>
              </div>
            </div>
            <div class="mt-2 bg-horizon-200 rounded-full h-2">
              <div
                class="h-2 rounded-full transition-all"
                :class="progressBarColor(coverageAnalysis.income_protection?.coverage_percentage)"
                :style="{ width: Math.min(100, coverageAnalysis.income_protection?.coverage_percentage || 0) + '%' }"
              ></div>
            </div>
            <!-- How we calculated the need -->
            <div v-if="incomeAnalysis.net_income > 0" class="mt-3 pt-3 border-t border-light-gray">
              <p class="text-xs font-medium text-neutral-500 uppercase mb-2">How we calculated your need</p>
              <div class="space-y-1">
                <div class="flex justify-between text-xs">
                  <span class="text-neutral-500">70% of your net monthly income ({{ formatCurrency(incomeAnalysis.net_income / 12) }}/month)</span>
                  <span class="text-horizon-500 font-medium">{{ formatCurrency(coverageAnalysis.income_protection?.need || 0) }}/month</span>
                </div>
                <div class="flex justify-between text-xs pt-1 border-t border-savannah-100 font-medium">
                  <span class="text-horizon-500">Monthly need</span>
                  <span class="text-horizon-500">{{ formatCurrency(coverageAnalysis.income_protection?.need || 0) }}/month</span>
                </div>
                <div v-if="coverageAnalysis.income_protection?.coverage > 0" class="flex justify-between text-xs">
                  <span class="text-neutral-500">Less: existing income protection</span>
                  <span class="text-spring-700 font-medium">-{{ formatCurrency(coverageAnalysis.income_protection.coverage) }}/month</span>
                </div>
                <div v-if="coverageAnalysis.income_protection?.gap > 0" class="flex justify-between text-xs pt-1 border-t border-savannah-100 font-semibold">
                  <span class="text-raspberry-700">Monthly shortfall</span>
                  <span class="text-raspberry-700">{{ formatCurrency(coverageAnalysis.income_protection.gap) }}/month</span>
                </div>
              </div>
              <p class="text-xs text-horizon-400 mt-2">Replaces your income if you are unable to work due to illness or injury.</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Existing Policies -->
      <div v-if="hasPolicies" class="bg-white rounded-lg shadow-sm border border-light-gray p-5">
        <h3 class="text-sm font-semibold text-horizon-500 mb-3">Existing Policies</h3>
        <div class="space-y-2">
          <!-- Life Insurance Policies -->
          <div v-for="policy in currentCoverage.life_insurance?.policies || []" :key="'life-' + policy.provider" class="flex items-center justify-between py-2 border-b border-savannah-100 last:border-b-0">
            <div>
              <p class="text-sm font-medium text-horizon-500">Life Insurance - {{ policy.type }}</p>
              <p class="text-xs text-neutral-500">{{ policy.provider }}</p>
            </div>
            <p class="text-sm font-semibold text-horizon-500">{{ formatCurrency(policy.sum_assured || 0) }}</p>
          </div>
          <!-- Critical Illness Policies -->
          <div v-for="policy in currentCoverage.critical_illness?.policies || []" :key="'ci-' + policy.provider" class="flex items-center justify-between py-2 border-b border-savannah-100 last:border-b-0">
            <div>
              <p class="text-sm font-medium text-horizon-500">Critical Illness - {{ policy.type }}</p>
              <p class="text-xs text-neutral-500">{{ policy.provider }}</p>
            </div>
            <p class="text-sm font-semibold text-horizon-500">{{ formatCurrency(policy.sum_assured || 0) }}</p>
          </div>
          <!-- Income Protection Policies -->
          <div v-for="policy in currentCoverage.income_protection?.policies || []" :key="'ip-' + policy.provider" class="flex items-center justify-between py-2 border-b border-savannah-100 last:border-b-0">
            <div>
              <p class="text-sm font-medium text-horizon-500">Income Protection</p>
              <p class="text-xs text-neutral-500">{{ policy.provider }}</p>
            </div>
            <p class="text-sm font-semibold text-horizon-500">{{ formatCurrency(policy.benefit_amount || 0) }}/month</p>
          </div>
        </div>
        <div v-if="currentCoverage.total_monthly_premiums > 0" class="mt-3 pt-3 border-t border-light-gray flex justify-between">
          <span class="text-sm font-medium text-horizon-500">Total Monthly Premiums</span>
          <span class="text-sm font-bold text-horizon-500">{{ formatCurrency(currentCoverage.total_monthly_premiums) }}</span>
        </div>
      </div>

      <!-- Debt Breakdown -->
      <div v-if="situation.debt_breakdown && situation.debt_breakdown.total > 0" class="bg-white rounded-lg shadow-sm border border-light-gray p-5">
        <h3 class="text-sm font-semibold text-horizon-500 mb-3">Debt Exposure</h3>
        <div class="space-y-2">
          <div class="flex justify-between py-1">
            <span class="text-sm text-neutral-500">Mortgage</span>
            <span class="text-sm font-medium text-horizon-500">{{ formatCurrency(situation.debt_breakdown.mortgage) }}</span>
          </div>
          <div class="flex justify-between py-1">
            <span class="text-sm text-neutral-500">Other Debts</span>
            <span class="text-sm font-medium text-horizon-500">{{ formatCurrency(situation.debt_breakdown.other) }}</span>
          </div>
          <div class="flex justify-between py-1 border-t border-light-gray pt-2">
            <span class="text-sm font-medium text-horizon-500">Total Debt</span>
            <span class="text-sm font-bold text-horizon-500">{{ formatCurrency(situation.debt_breakdown.total) }}</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { currencyMixin } from '@/mixins/currencyMixin';
import PlanSectionHeader from '@/components/Plans/Shared/PlanSectionHeader.vue';

export default {
  name: 'ProtectionCurrentSituation',
  components: { PlanSectionHeader },
  mixins: [currencyMixin],
  props: {
    situation: { type: Object, required: true },
  },
  computed: {
    coverageAnalysis() {
      return this.situation.coverage_analysis || null;
    },
    currentCoverage() {
      return this.situation.current_coverage || {};
    },
    needsBreakdown() {
      return this.situation.needs?.breakdown || null;
    },
    incomeAnalysis() {
      return this.situation.needs?.income_analysis || {};
    },
    hasPolicies() {
      const cc = this.currentCoverage;
      return (cc.life_insurance?.policy_count || 0) > 0
        || (cc.critical_illness?.policy_count || 0) > 0
        || (cc.income_protection?.policy_count || 0) > 0;
    },
  },
  methods: {
    gapColor(gap) {
      if (!gap || gap <= 0) return 'text-spring-700';
      return 'text-raspberry-700';
    },
    statusBadge(status) {
      const s = (status || '').toLowerCase();
      if (s === 'excellent') return 'bg-spring-100 text-spring-800';
      if (s === 'good') return 'bg-violet-100 text-violet-800';
      if (s === 'fair') return 'bg-violet-100 text-violet-800';
      return 'bg-raspberry-100 text-raspberry-800';
    },
    progressBarColor(percentage) {
      if (percentage >= 80) return 'bg-spring-500';
      if (percentage >= 60) return 'bg-violet-500';
      if (percentage >= 40) return 'bg-violet-500';
      return 'bg-raspberry-500';
    },
  },
};
</script>
