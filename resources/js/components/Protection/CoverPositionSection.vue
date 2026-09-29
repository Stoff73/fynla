<template>
  <section v-if="rows.length" class="bg-white rounded-lg border border-light-gray p-6" aria-labelledby="cover-position-heading">
    <h3 id="cover-position-heading" class="text-lg font-semibold text-horizon-500 mb-4">Your cover</h3>
    <div class="divide-y divide-light-gray">
      <div v-for="row in rows" :key="row.key" class="py-3">
        <button type="button" class="w-full flex items-center justify-between text-left" :aria-expanded="open === row.key" @click="open = open === row.key ? null : row.key">
          <span class="font-medium text-horizon-500">{{ row.label }}</span>
          <span :class="row.tone">{{ row.status }}</span>
        </button>
        <dl v-if="open === row.key" class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-2 text-sm">
          <div><dt class="text-neutral-500">You need</dt><dd class="text-horizon-500 font-medium">{{ row.need }}</dd></div>
          <div><dt class="text-neutral-500">Your own policies</dt><dd class="text-horizon-500 font-medium">{{ row.own }}</dd></div>
          <div><dt class="text-neutral-500">Through your job (ends if you leave)</dt><dd class="text-horizon-500 font-medium">{{ row.job }}</dd></div>
        </dl>
      </div>
    </div>
  </section>
</template>

<script>
import { currencyMixin } from '@/mixins/currencyMixin';

const LABELS = { life: 'Life cover', critical_illness: 'Critical illness cover', income_protection: 'Income protection' };

/** Where the user stands per cover type (ProtectionCoverPosition, the cards' own figures). */
export default {
  name: 'CoverPositionSection',
  mixins: [currencyMixin],
  props: { position: { type: Object, default: null } },
  data: () => ({ open: null }),
  computed: {
    rows() {
      if (!this.position) return [];
      return Object.keys(LABELS).filter((key) => this.position[key]).map((key) => {
        const p = this.position[key];
        const money = (v) => this.formatCurrency(v) + (p.unit === 'monthly' ? ' a month' : '');
        const parts = [];
        if (p.short_by > 0) parts.push(`Short by ${money(p.short_by)}`);
        if (p.over_by > 0) parts.push(`Over by ${money(p.over_by)}`);
        if (p.depends_on_job) parts.push('Depends on your job');
        return {
          key,
          label: LABELS[key],
          status: parts.length ? parts.join(', ') : 'Covered',
          tone: p.short_by > 0 ? 'text-raspberry-600 font-medium' : (p.over_by > 0 || p.depends_on_job ? 'text-violet-600 font-medium' : 'text-spring-600 font-medium'),
          need: money(p.need),
          own: money(p.own_cover),
          job: money(p.employer_cover),
        };
      });
    },
  },
};
</script>
