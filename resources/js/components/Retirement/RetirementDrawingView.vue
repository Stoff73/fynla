<template>
  <!-- The Retirement page for someone drawing their pension (TODO item 6; CSJ
       2026-10-01). Every figure comes from the server's drawdown_position, the
       same block /m reads (Rule 20). -->
  <div class="space-y-4">
    <p v-if="position.retired_since" class="text-sm text-neutral-500">
      Retired since {{ retiredSinceLabel }}<template v-if="position.retired_since.age !== null">, at {{ position.retired_since.age }}</template>
    </p>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
      <div class="card">
        <h3 class="text-lg font-semibold text-horizon-500 mb-4">Your income this year</h3>
        <dl class="space-y-2 text-sm">
          <div v-for="line in position.income.lines" :key="line.key" class="flex justify-between gap-4">
            <dt class="text-neutral-500">{{ line.label }}</dt>
            <dd class="font-medium text-horizon-500">{{ formatCurrency(line.amount) }}</dd>
          </div>
          <div v-if="position.income.state_pension_missing" class="flex justify-between items-center gap-4">
            <dt class="text-neutral-500">State Pension</dt>
            <dd>
              <button type="button" class="text-sm font-semibold text-raspberry-500 hover:text-raspberry-600" @click="$emit('add-state-pension')">Add it</button>
            </dd>
          </div>
          <div class="flex justify-between gap-4 pt-2 border-t border-light-gray">
            <dt class="text-neutral-500">Income Tax</dt>
            <dd class="font-medium text-horizon-500">{{ formatCurrency(position.income.income_tax) }}</dd>
          </div>
          <div v-if="position.income.national_insurance > 0" class="flex justify-between gap-4">
            <dt class="text-neutral-500">National Insurance</dt>
            <dd class="font-medium text-horizon-500">{{ formatCurrency(position.income.national_insurance) }}</dd>
          </div>
          <div class="flex justify-between gap-4 pt-2 border-t border-light-gray">
            <dt class="font-semibold text-horizon-500">Take-home</dt>
            <dd class="font-bold text-horizon-500">{{ formatCurrency(position.income.take_home) }}</dd>
          </div>
        </dl>
      </div>

      <div v-if="pot" class="card">
        <h3 class="text-lg font-semibold text-horizon-500 mb-4">How long your pension lasts</h3>
        <p class="text-sm text-neutral-500 mb-3">
          <template v-if="pot.drawing_per_year > 0">Drawing {{ formatCurrency(pot.drawing_per_year) }} a year from {{ formatCurrency(pot.value) }}</template>
          <template v-else>No drawdown recorded from your {{ formatCurrency(pot.value) }}</template>
        </p>
        <dl class="space-y-2 text-sm">
          <template v-if="pot.drawing_per_year > 0">
            <div class="flex justify-between gap-4">
              <dt class="text-neutral-500">Middle outcome</dt>
              <dd class="font-medium text-horizon-500">{{ lastsLabel(pot.lasts_to_age.middle) }}</dd>
            </div>
            <div class="flex justify-between gap-4">
              <dt class="text-neutral-500">Lower outcome (4 in 5 do better)</dt>
              <dd class="font-medium text-horizon-500">{{ lastsLabel(pot.lasts_to_age.lower) }}</dd>
            </div>
          </template>
          <div class="flex justify-between gap-4">
            <dt class="text-neutral-500">Life expectancy</dt>
            <dd class="font-medium text-horizon-500">{{ pot.life_expectancy.age }} ({{ lifeExpectancySource }})</dd>
          </div>
          <div v-if="pot.income_to_last_to_life_expectancy !== null" class="flex justify-between gap-4 pt-2 border-t border-light-gray">
            <dt class="font-semibold text-horizon-500">To last to {{ pot.life_expectancy.age }}</dt>
            <dd class="font-bold text-horizon-500">about {{ formatCurrency(pot.income_to_last_to_life_expectancy) }} a year</dd>
          </div>
        </dl>
        <p v-if="pot.income_to_last_to_life_expectancy !== null" class="text-xs text-neutral-500 mt-3">
          The yearly income that still lasts to {{ pot.life_expectancy.age }} in 4 out of 5 outcomes.
        </p>
      </div>
    </div>
  </div>
</template>

<script>
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'RetirementDrawingView',
  mixins: [currencyMixin],
  props: {
    position: { type: Object, required: true },
  },
  emits: ['add-state-pension'],
  computed: {
    pot() {
      return this.position.pot || null;
    },
    retiredSinceLabel() {
      const date = new Date(this.position.retired_since.date);
      return date.toLocaleDateString('en-GB', { month: 'long', year: 'numeric' });
    },
    lifeExpectancySource() {
      return this.pot.life_expectancy.source === 'ons' ? 'Office for National Statistics' : 'your figure';
    },
  },
  methods: {
    lastsLabel(age) {
      return age === null ? `lasts beyond ${this.pot.end_age}` : `lasts to age ${age}`;
    },
  },
};
</script>
