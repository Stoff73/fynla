<template>
  <section>
    <div class="flex items-baseline justify-between mb-4">
      <h2 class="text-h3 font-bold text-horizon-500">Recommended actions</h2>
      <span v-if="items.length" class="text-caption text-neutral-500">
        Sorted by potential saving
      </span>
    </div>

    <div v-if="!items.length" class="rounded-card bg-white border border-light-gray p-6 text-body-sm text-neutral-500">
      Nothing to act on right now — allowances are well-utilised and there's no
      tax-band optimisation to make at the current income.
    </div>

    <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <article
        v-for="rec in items"
        :key="rec.type"
        class="rounded-card border bg-white transition-shadow hover:shadow-md flex flex-col"
        :class="cardBorderClass(rec)"
      >
        <div class="p-5 flex flex-col flex-1">
          <div class="flex items-start justify-between gap-4 mb-2">
            <div class="flex-1 min-w-0">
              <span
                v-if="rec.category === 'warning'"
                class="inline-block text-caption font-bold uppercase tracking-wide text-violet-600 bg-violet-100 px-2 py-0.5 rounded mb-2"
              >
                Watch out
              </span>
              <h3 class="font-bold text-horizon-500 leading-snug text-body">
                {{ rec.title }}
              </h3>
            </div>
            <div v-if="rec.estimated_annual_tax_saved" class="text-right shrink-0">
              <div class="text-caption text-neutral-500 leading-none">Saves</div>
              <div class="font-black text-spring-600 leading-tight text-h5">
                {{ formatCurrency(Math.round(rec.estimated_annual_tax_saved)) }}
              </div>
              <div class="text-caption text-neutral-500 leading-none">a year</div>
            </div>
          </div>

          <p class="text-neutral-500 leading-relaxed text-body-sm flex-1">
            {{ rec.description }}
          </p>

          <div class="flex items-center gap-3 mt-4 pt-3 border-t border-light-gray">
            <button
              v-if="nextStep(rec)"
              type="button"
              class="text-body-sm font-semibold text-raspberry-500 hover:text-raspberry-600 transition-colors"
              @click="goToNextStep(rec)"
            >
              {{ nextStep(rec).label }}
            </button>
            <!-- WP-2 — the same mark-done the dashboard actions carry, keyed
                 on the same stable recommendation_id so completion syncs
                 across every surface. -->
            <button
              type="button"
              class="text-body-sm font-semibold text-spring-700 border border-spring-500 rounded-full px-3 py-1 hover:bg-spring-50 transition-colors disabled:opacity-60"
              :disabled="marking === rec.recommendation_id"
              @click="markDone(rec)"
            >
              Mark as done
            </button>
            <span
              v-if="rec.requires_advice"
              class="text-caption font-semibold text-violet-600 bg-violet-100 px-2 py-0.5 rounded"
            >
              Speak to an adviser
            </span>
          </div>
        </div>
      </article>
    </div>

    <!-- Done — completed strategies stay visible on the tax page (the
         dashboard replaces them; this page keeps the overview). -->
    <div v-if="completedItems.length" class="mt-6">
      <h3 class="text-h5 font-bold text-horizon-500 mb-3">Done</h3>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <article
          v-for="rec in completedItems"
          :key="rec.type"
          class="rounded-card border border-light-gray bg-white opacity-75 p-5 flex items-start justify-between gap-4"
        >
          <h3 class="font-bold text-horizon-500 leading-snug text-body">{{ rec.title }}</h3>
          <span class="text-caption font-bold text-spring-700 whitespace-nowrap">Done{{ doneDate(rec) }}</span>
        </article>
      </div>
    </div>
  </section>
</template>

<script>
import { mapGetters } from 'vuex';
import { currencyMixin } from '@/mixins/currencyMixin';

const NEXT_STEPS = {
  pa_taper_rescue: { label: 'Open a pension', path: '/pension' },
  additional_rate_avoidance: { label: 'Open a pension', path: '/pension' },
  pension_aa_carry_forward: { label: 'Open a pension', path: '/pension' },
  salary_sacrifice_ni: { label: 'Open a pension', path: '/pension' },
  isa_topup_vs_psa: { label: 'Open savings & ISAs', path: '/savings' },
  bed_and_isa: { label: 'Open investments', path: '/investments' },
  dividend_allowance_harvest: { label: 'Open investments', path: '/investments' },
  gift_aid_higher_rate_relief: { label: 'See income & tax', path: '/profile' },
  joint_savings_split: { label: 'Open savings & ISAs', path: '/savings' },
  marriage_allowance_transfer: { label: 'See income & tax', path: '/profile' },
  asset_shifting_savings: { label: 'Open savings & ISAs', path: '/savings' },
  asset_shifting_isa: { label: 'Open savings & ISAs', path: '/savings' },
  cross_spouse_dividends: { label: 'Open investments', path: '/investments' },
  non_earner_spouse_pension: { label: 'Open a pension', path: '/pension' },
  lifetime_isa: { label: 'Open savings & ISAs', path: '/savings' },
  junior_isa: { label: 'Open savings & ISAs', path: '/savings' },
  junior_pension: { label: 'Open a pension', path: '/pension' },
  tapered_annual_allowance: { label: 'Open a pension', path: '/pension' },
};

export default {
  name: 'StrategyRecommendationList',
  mixins: [currencyMixin],
  props: {
    excludeCategories: {
      type: Array,
      default: () => ['household'],
    },
  },
  data() {
    return { marking: null };
  },
  computed: {
    ...mapGetters('taxStrategy', ['recommendations']),
    // Open (not completed) items only — completed ones move to the Done
    // group so the page keeps the overview (WP-2).
    items() {
      const filtered = this.recommendations.filter(
        (rec) => !this.excludeCategories.includes(rec.category ?? 'household') && !rec.completed,
      );
      return [...filtered].sort((a, b) => {
        if (a.category === 'warning' && b.category !== 'warning') return -1;
        if (b.category === 'warning' && a.category !== 'warning') return 1;
        const ax = Number(a.estimated_annual_tax_saved) || 0;
        const bx = Number(b.estimated_annual_tax_saved) || 0;
        return bx - ax;
      });
    },
    completedItems() {
      return this.recommendations.filter(
        (rec) => !this.excludeCategories.includes(rec.category ?? 'household') && rec.completed,
      );
    },
  },
  methods: {
    nextStep(rec) {
      return NEXT_STEPS[rec.type] || null;
    },
    goToNextStep(rec) {
      const step = this.nextStep(rec);
      if (step?.path) this.$router.push(step.path);
    },
    cardBorderClass(rec) {
      if (rec.category === 'warning') return 'border-violet-200';
      return 'border-light-gray';
    },
    doneDate(rec) {
      if (!rec.completed_at) return '';
      const d = new Date(rec.completed_at);
      return isNaN(d.getTime()) ? '' : ` ${d.toLocaleDateString('en-GB')}`;
    },
    // WP-2 — same completion write the dashboard uses (stable aggregator id),
    // then refresh the tax dashboard so the item moves to Done.
    async markDone(rec) {
      if (!rec.recommendation_id || this.marking) return;
      this.marking = rec.recommendation_id;
      try {
        await this.$store.dispatch('taxStrategy/markRecommendationDone', {
          recommendationId: rec.recommendation_id,
          recommendationText: rec.title || rec.type,
        });
      } finally {
        this.marking = null;
      }
    },
  },
};
</script>
