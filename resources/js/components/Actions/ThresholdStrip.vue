<template>
  <section v-if="strip" class="strip" aria-labelledby="threshold-strip-title">
    <div class="flex items-start justify-between gap-4">
      <div class="min-w-0">
        <h3 id="threshold-strip-title" class="text-lg font-bold text-horizon-500">{{ strip.headline }}</h3>
        <p class="text-body-sm text-neutral-500 mt-1">{{ strip.body }}</p>
      </div>
      <button type="button" class="strip-toggle" @click="expanded = !expanded">
        {{ expanded ? 'Hide' : 'See what this costs' }}
      </button>
    </div>

    <div v-if="strip.range" class="ribbon" role="img" :aria-label="ribbonLabel">
      <div class="ribbon-track">
        <div class="ribbon-marker" :style="{ left: markerLeft }"></div>
      </div>
      <div class="flex justify-between text-caption text-neutral-500 mt-1">
        <span>{{ formatCurrency(strip.range.from) }}</span>
        <span class="font-semibold text-horizon-500">You: {{ formatCurrency(strip.position.value) }}</span>
        <span>{{ strip.range.to ? formatCurrency(strip.range.to) : '' }}</span>
      </div>
    </div>

    <div v-if="expanded" class="mt-6 space-y-6">
      <div>
        <h4 class="text-body-sm font-bold text-horizon-500">{{ strip.title }}</h4>
        <p class="text-body-sm text-neutral-500 mt-1">{{ strip.explanation }}</p>
      </div>

      <div v-if="strip.cost">
        <h4 class="text-body-sm font-bold text-horizon-500 mb-2">What being over the line costs you</h4>
        <dl class="space-y-2">
          <div v-for="row in costRows" :key="row.label" class="flex justify-between gap-4">
            <dt class="text-body-sm text-neutral-500">{{ row.label }}<span v-if="row.detail" class="block text-caption">{{ row.detail }}</span></dt>
            <dd class="text-body-sm font-semibold text-horizon-500">{{ formatCurrency(row.amount) }}</dd>
          </div>
          <div class="flex justify-between gap-4 border-t border-light-gray pt-2">
            <dt class="text-body-sm font-bold text-horizon-500">Total</dt>
            <dd class="text-body-sm font-bold text-horizon-500">{{ formatCurrency(strip.cost_total) }} a year</dd>
          </div>
        </dl>
        <p v-if="mixRows.length > 1" class="text-caption text-neutral-500 mt-3">Your income this year: {{ mixRows.join(', ') }}.</p>
      </div>

      <div v-if="strip.lever">
        <h4 class="text-body-sm font-bold text-horizon-500 mb-1">The one lever that moves it</h4>
        <p class="text-body-sm text-horizon-500">{{ strip.lever.title }}. You recover {{ formatCurrency(strip.lever.recovers) }}.</p>
        <p class="text-body-sm text-violet-700 mt-1">{{ strip.lever.downside }}</p>
        <router-link :to="strip.lever.action.route" class="inline-block mt-2 text-body-sm font-semibold text-raspberry-500 hover:underline">Model this change</router-link>
      </div>

      <div v-if="others.length">
        <h4 class="text-body-sm font-bold text-horizon-500 mb-2">Other lines that apply to you</h4>
        <ul class="space-y-2">
          <li v-for="other in others" :key="other.key" class="flex justify-between gap-4">
            <span class="text-body-sm text-horizon-500">{{ other.title }}</span>
            <span class="text-body-sm text-neutral-500">{{ other.headline }}</span>
          </li>
        </ul>
      </div>
    </div>
  </section>
</template>

<script>
import { currencyMixin } from '@/mixins/currencyMixin';

/**
 * Threshold position (artboard D, 2026-09-17): one line, not a grid. Shows the
 * nearest line the user can still act on; everything else waits behind the
 * click. Renders nothing when no line applies. All copy comes from the server.
 */
export default {
  name: 'ThresholdStrip',
  mixins: [currencyMixin],
  props: {
    data: { type: Object, default: () => ({ strip: null, lines: [] }) },
  },
  data() {
    return { expanded: false };
  },
  computed: {
    strip() {
      const lines = this.data?.lines ?? [];
      return lines.find((l) => l.key === this.data?.strip) || null;
    },
    others() {
      return (this.data?.lines ?? []).filter((l) => l.key !== this.data?.strip);
    },
    costRows() {
      const c = this.strip?.cost;
      if (!c) return [];
      const named = [
        ['Income tax', c.income_tax], ['National Insurance', c.ni_class_1], ['Class 4 National Insurance', c.ni_class_4],
        ['Dividend tax', c.dividend_tax], ['Tax on savings interest', c.interest_tax],
      ].filter(([, amount]) => amount > 0).map(([label, amount]) => ({ label, detail: '', amount }));
      return [...named, ...(c.benefits || []).filter((b) => b.amount > 0)];
    },
    mixRows() {
      const labels = { employment: 'employment', self_employment: 'self-employment', rental: 'rental profit', dividend: 'dividends', interest: 'interest', other: 'pension and other income' };
      return Object.entries(this.strip?.income_mix ?? {}).filter(([, v]) => v > 0).map(([k, v]) => `${this.formatCurrency(v)} ${labels[k] || k}`);
    },
    markerLeft() {
      const r = this.strip?.range;
      if (!r || !r.to) return '0%';
      const pct = ((this.strip.position.value - r.from) / (r.to - r.from)) * 100;
      return `${Math.min(100, Math.max(0, pct))}%`;
    },
    ribbonLabel() {
      return `${this.strip.title}: ${this.strip.headline}`;
    },
  },
};
</script>

<style scoped>
.strip { @apply bg-white rounded-card border border-light-gray p-6 mb-5; }
.strip-toggle { @apply flex-shrink-0 text-xs font-semibold text-horizon-500 border border-horizon-300 rounded-full px-3 py-1 hover:bg-eggshell-500 transition-colors; }
.ribbon { @apply mt-4; }
.ribbon-track { @apply relative h-2 rounded-full bg-violet-100; }
.ribbon-marker { @apply absolute top-1/2 w-3 h-3 -mt-1.5 -ml-1.5 rounded-full bg-raspberry-500 border-2 border-white; }
</style>
