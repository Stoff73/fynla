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
        <p v-if="open === row.key && row.basis" class="mt-2 text-sm text-neutral-500">{{ row.basis }}</p>
      </div>
    </div>
  </section>
</template>

<script>
const ORDER = ['life', 'critical_illness', 'income_protection'];
const TONES = { short: 'text-raspberry-600 font-medium', attention: 'text-violet-600 font-medium', covered: 'text-spring-600 font-medium' };

/**
 * Where the user stands per cover type (ProtectionCoverPosition, the cards' own
 * figures). Every figure and word is the server's, the same /m and iOS print
 * (CSJ 2026-10-01: one figure, every surface).
 */
export default {
  name: 'CoverPositionSection',
  props: { position: { type: Object, default: null } },
  data: () => ({ open: null }),
  computed: {
    rows() {
      if (!this.position) return [];
      return ORDER.filter((key) => this.position[key]).map((key) => {
        const p = this.position[key];
        return {
          key,
          label: p.label,
          status: p.status_label,
          tone: TONES[p.tone] || TONES.covered,
          need: p.need_label,
          own: p.own_cover_label,
          job: p.employer_cover_label,
          basis: p.basis || null,
        };
      });
    },
  },
};
</script>
