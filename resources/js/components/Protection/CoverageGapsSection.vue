<template>
  <section v-if="breakdown" class="bg-white rounded-lg border border-light-gray p-6" aria-labelledby="coverage-gaps-heading">
    <h3 id="coverage-gaps-heading" class="text-lg font-semibold text-horizon-500 mb-4">Coverage gaps</h3>
    <p v-if="!openGaps.length" class="text-sm text-neutral-500">
      No shortfalls identified against your debts and income. Your cover looks well-matched to your needs.
    </p>
    <div v-else class="divide-y divide-light-gray">
      <div v-for="gap in openGaps" :key="gap.key" class="py-3" :data-test="`protection-gap-${gap.key}`">
        <button type="button" class="w-full flex items-center justify-between gap-4 text-left" :aria-expanded="open === gap.key" @click="open = open === gap.key ? null : gap.key">
          <span class="font-medium text-horizon-500">{{ gap.label }}</span>
          <span class="text-right">
            <span class="block text-raspberry-600 font-medium">{{ money(gap, gap.shortfall) }} short</span>
            <span class="block text-xs text-neutral-500">{{ formatCurrency(gap.cover) }} of {{ money(gap, gap.need) }}</span>
          </span>
        </button>
        <div v-if="open === gap.key" class="mt-3 space-y-3 text-sm">
          <p class="text-neutral-500">{{ gap.explanation }}</p>
          <p v-if="breakdown.calculated_at" class="text-xs text-horizon-400">
            Calculated {{ displayDate(breakdown.calculated_at) }} from your recorded information
          </p>
          <dl v-if="Object.keys(gap.inputs || {}).length" class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-2">
            <div v-for="(value, key) in gap.inputs" :key="key">
              <dt class="text-neutral-500">{{ fieldLabel(key) }}</dt>
              <dd class="text-horizon-500 font-medium">{{ displayInput(value, key) }}</dd>
            </div>
          </dl>
          <div v-if="gap.assumptions?.length">
            <p class="font-medium text-horizon-500 mb-1">Assumptions</p>
            <p v-for="assumption in gap.assumptions" :key="assumption.key" class="text-neutral-500">
              {{ fieldLabel(assumption.key) }}: {{ displayAssumption(assumption) }}
            </p>
          </div>
          <div v-if="gap.relevant_policies?.length">
            <p class="font-medium text-horizon-500 mb-1">Cover used in this calculation</p>
            <p v-for="policy in gap.relevant_policies" :key="`${policy.type}-${policy.id}`" class="text-neutral-500">
              {{ policy.provider || policy.name || 'Recorded policy' }}: {{ formatCurrency(policy.cover) }}
            </p>
          </div>
        </div>
      </div>
    </div>
  </section>
</template>

<script>
import { currencyMixin } from '@/mixins/currencyMixin';

/**
 * The need-component breakdown under "Your cover": GET /api/protection
 * `coverage_gaps` (ProtectionGapPresentationService), the same payload /m
 * renders. The page used to work these figures out in the browser from its own
 * literals (75% of income, 2x income, a 4.7% draw, 50%) and disagreed with
 * "Your cover" and the cards (fynla.org, 2026-09-29).
 */
export default {
  name: 'CoverageGapsSection',
  mixins: [currencyMixin],
  props: { breakdown: { type: Object, default: null } },
  data: () => ({ open: null }),
  computed: {
    openGaps() {
      return (this.breakdown?.categories || []).filter((gap) => gap.status === 'gap' && Number(gap.shortfall) > 0);
    },
  },
  methods: {
    // Income protection is held as a yearly amount; every other need is a lump sum.
    money(gap, value) {
      return this.formatCurrency(value) + (gap.key === 'income_protection' ? ' a year' : '');
    },
    fieldLabel(value) {
      return String(value || '').replace(/_/g, ' ').replace(/\b\w/g, (char) => char.toUpperCase());
    },
    displayInput(value, key) {
      if (Array.isArray(value)) return value.length ? value.join(', ') : 'None recorded';
      if (key === 'number_of_dependants') return String(value);
      if (typeof value === 'number') return this.formatCurrency(value);
      if (value && typeof value === 'object') {
        return Object.entries(value)
          .map(([itemKey, item]) => `${this.fieldLabel(itemKey)}: ${typeof item === 'number' ? this.formatCurrency(item) : item}`)
          .join(', ');
      }
      return value ?? 'Not recorded';
    },
    displayDate(value) {
      const parsed = new Date(value);
      return Number.isNaN(parsed.getTime())
        ? value
        : parsed.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
    },
    displayAssumption(assumption) {
      if (assumption.unit === 'GBP') return this.formatCurrency(assumption.value);
      if (assumption.unit === 'percent') return `${assumption.value}%`;
      if (assumption.unit === 'years') return `${assumption.value} years`;
      return assumption.value;
    },
  },
};
</script>
