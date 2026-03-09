<template>
  <div class="card">
    <h3 class="text-lg font-bold text-horizon-500 mb-4">What If Scenario</h3>

    <!-- Toggle -->
    <div class="flex rounded-lg bg-eggshell-500 p-1 mb-6">
      <button
        @click="setScenario('primary')"
        class="flex-1 py-2 px-3 text-sm font-semibold rounded-md transition-all"
        :class="selectedSpouse === 'primary'
          ? 'bg-white text-horizon-500 shadow-sm'
          : 'text-neutral-500 hover:text-horizon-500'"
      >
        If you pass away
      </button>
      <button
        @click="setScenario('partner')"
        class="flex-1 py-2 px-3 text-sm font-semibold rounded-md transition-all"
        :class="selectedSpouse === 'partner'
          ? 'bg-white text-horizon-500 shadow-sm'
          : 'text-neutral-500 hover:text-horizon-500'"
      >
        If your partner passes away
      </button>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="flex justify-center py-8">
      <div class="w-10 h-10 border-4 border-horizon-200 border-t-raspberry-500 rounded-full animate-spin"></div>
    </div>

    <!-- Error -->
    <div v-else-if="error" class="text-center py-6">
      <p class="text-neutral-500 mb-2">{{ error }}</p>
      <button @click="fetchScenario" class="text-sm text-raspberry-500 hover:text-raspberry-600">Retry</button>
    </div>

    <!-- Scenario data -->
    <div v-else-if="scenario" class="space-y-5">
      <!-- Surviving spouse position -->
      <div>
        <p class="text-sm font-bold text-horizon-500 mb-3">Surviving Partner's Position</p>
        <div class="grid grid-cols-2 gap-3">
          <div class="bg-eggshell-500 rounded-lg p-3">
            <p class="text-xs text-neutral-500">Total Assets</p>
            <p class="text-lg font-bold text-horizon-500">{{ formatCurrency(scenario.surviving_spouse_assets) }}</p>
          </div>
          <div class="bg-eggshell-500 rounded-lg p-3">
            <p class="text-xs text-neutral-500">Net Position</p>
            <p class="text-lg font-bold" :class="scenario.surviving_spouse_net_position >= 0 ? 'text-spring-500' : 'text-raspberry-500'">
              {{ formatCurrency(scenario.surviving_spouse_net_position) }}
            </p>
          </div>
        </div>
      </div>

      <!-- Inheritance Tax Impact -->
      <div>
        <p class="text-sm font-bold text-horizon-500 mb-3">Inheritance Tax Impact</p>
        <div class="space-y-2">
          <div class="flex justify-between py-1">
            <span class="text-sm text-neutral-500">Inheritance Tax on first death</span>
            <span class="text-sm font-semibold" :class="scenario.iht_first_death === 0 ? 'text-spring-500' : 'text-raspberry-500'">
              {{ scenario.iht_first_death === 0 ? 'Nil (spousal exemption)' : formatCurrency(scenario.iht_first_death) }}
            </span>
          </div>
          <div class="flex justify-between py-1">
            <span class="text-sm text-neutral-500">Nil Rate Band transferred</span>
            <span class="text-sm font-semibold text-horizon-500">{{ formatCurrency(scenario.nrb_transferred) }}</span>
          </div>
          <div class="flex justify-between py-1">
            <span class="text-sm text-neutral-500">Residence Nil Rate Band transferred</span>
            <span class="text-sm font-semibold text-horizon-500">{{ formatCurrency(scenario.rnrb_transferred) }}</span>
          </div>
          <div class="flex justify-between py-1 border-t border-light-gray pt-2">
            <span class="text-sm text-neutral-500">Total allowances on second death</span>
            <span class="text-sm font-bold text-horizon-500">{{ formatCurrency(scenario.total_allowances_on_second_death) }}</span>
          </div>
          <div v-if="scenario.iht_second_death > 0" class="flex justify-between py-1">
            <span class="text-sm text-neutral-500">Estimated Inheritance Tax on second death</span>
            <span class="text-sm font-bold text-raspberry-500">{{ formatCurrency(scenario.iht_second_death) }}</span>
          </div>
        </div>
      </div>

      <!-- Income Impact -->
      <div v-if="scenario.income_impact">
        <p class="text-sm font-bold text-horizon-500 mb-3">Income Impact</p>
        <div class="space-y-2">
          <div class="flex justify-between py-1">
            <span class="text-sm text-neutral-500">Household income before</span>
            <span class="text-sm font-semibold text-horizon-500">{{ formatCurrency(scenario.income_impact.income_before) }}/year</span>
          </div>
          <div class="flex justify-between py-1">
            <span class="text-sm text-neutral-500">Surviving partner's income after</span>
            <span class="text-sm font-semibold text-horizon-500">{{ formatCurrency(scenario.income_impact.income_after) }}/year</span>
          </div>
          <div v-if="scenario.income_impact.income_lost > 0" class="flex justify-between py-1 border-t border-light-gray pt-2">
            <span class="text-sm text-neutral-500">Income shortfall</span>
            <span class="text-sm font-bold text-raspberry-500">{{ formatCurrency(scenario.income_impact.income_lost) }}/year</span>
          </div>
          <div v-if="scenario.income_impact.db_spouse_benefit > 0" class="flex justify-between py-1">
            <span class="text-sm text-neutral-500">Defined Benefit spouse's pension</span>
            <span class="text-sm font-semibold text-spring-500">{{ formatCurrency(scenario.income_impact.db_spouse_benefit) }}/year</span>
          </div>
        </div>
      </div>

      <!-- Pension Death Benefits -->
      <div v-if="scenario.pension_death_benefits && scenario.pension_death_benefits.dc_total > 0">
        <p class="text-sm font-bold text-horizon-500 mb-3">Pension Death Benefits</p>
        <div class="space-y-2">
          <div class="flex justify-between py-1">
            <span class="text-sm text-neutral-500">Defined Contribution pension funds</span>
            <span class="text-sm font-semibold text-horizon-500">{{ formatCurrency(scenario.pension_death_benefits.dc_total) }}</span>
          </div>
          <p class="text-xs text-neutral-400">Pension funds are typically outside the estate for Inheritance Tax purposes when nomination forms are completed.</p>
        </div>
      </div>

      <!-- Life Insurance -->
      <div v-if="scenario.life_insurance && scenario.life_insurance.total_payout > 0">
        <p class="text-sm font-bold text-horizon-500 mb-3">Life Insurance</p>
        <div class="space-y-2">
          <div class="flex justify-between py-1">
            <span class="text-sm text-neutral-500">Total payout</span>
            <span class="text-sm font-semibold text-spring-500">{{ formatCurrency(scenario.life_insurance.total_payout) }}</span>
          </div>
          <div v-if="scenario.life_insurance.in_trust > 0" class="flex justify-between py-1">
            <span class="text-sm text-neutral-500">Written in trust (outside estate)</span>
            <span class="text-sm font-semibold text-spring-500">{{ formatCurrency(scenario.life_insurance.in_trust) }}</span>
          </div>
          <div v-if="scenario.life_insurance.not_in_trust > 0" class="flex justify-between py-1">
            <span class="text-sm text-neutral-500">Not in trust (included in estate)</span>
            <span class="text-sm font-semibold text-violet-500">{{ formatCurrency(scenario.life_insurance.not_in_trust) }}</span>
          </div>
        </div>
      </div>

      <!-- Protection Gaps -->
      <div v-if="scenario.protection_gaps && scenario.protection_gaps.length > 0">
        <p class="text-sm font-bold text-horizon-500 mb-3">Protection Gaps</p>
        <div class="space-y-2">
          <div
            v-for="(gap, index) in scenario.protection_gaps"
            :key="index"
            class="rounded-lg border border-raspberry-200 bg-light-pink-50 p-3"
          >
            <p class="text-sm text-horizon-500">{{ gap.description }}</p>
            <p v-if="gap.shortfall > 0" class="text-xs text-raspberry-500 font-semibold mt-1">
              Shortfall: {{ formatCurrency(gap.shortfall) }}
            </p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { currencyMixin } from '@/mixins/currencyMixin';
import householdService from '@/services/householdService';

export default {
  name: 'DeathOfSpouseScenario',
  mixins: [currencyMixin],
  data() {
    return {
      selectedSpouse: 'primary',
      scenario: null,
      loading: false,
      error: null,
    };
  },
  mounted() {
    this.fetchScenario();
  },
  methods: {
    setScenario(spouse) {
      if (this.selectedSpouse === spouse) return;
      this.selectedSpouse = spouse;
      this.fetchScenario();
    },
    async fetchScenario() {
      this.loading = true;
      this.error = null;
      try {
        const response = await householdService.getDeathScenario(this.selectedSpouse);
        if (response.success) {
          this.scenario = response.data;
        } else {
          this.error = response.message || 'Failed to load scenario';
        }
      } catch (err) {
        this.error = 'Unable to load scenario analysis';
      } finally {
        this.loading = false;
      }
    },
  },
};
</script>
