<template>
  <section v-if="strip" class="m-card mt-strip" aria-labelledby="m-threshold-title">
    <h2 id="m-threshold-title" class="mt-title">{{ strip.headline }}</h2>
    <p class="mt-body">{{ strip.body }}</p>
    <div class="mt-row">
      <span class="mt-label">Costs you</span>
      <span class="mt-figure">{{ fmt(strip.cost_total) }} a year</span>
    </div>
    <button type="button" class="mt-toggle" @click="expanded = !expanded">{{ expanded ? 'Hide' : 'See what moves it' }}</button>
    <div v-if="expanded" class="mt-more">
      <p v-if="strip.lever" class="mt-lever">{{ strip.lever.title }}. You recover {{ fmt(strip.lever.recovers) }}.</p>
      <p v-if="strip.lever" class="mt-downside">{{ strip.lever.downside }}</p>
      <router-link v-if="strip.lever" :to="strip.lever.action.route" class="mt-link">Model this change</router-link>
      <ul v-if="others.length" class="mt-others">
        <li v-for="other in others" :key="other.key" class="mt-other">
          <span class="mt-other__title">{{ other.title }}</span>
          <span class="mt-other__meta">{{ other.headline }}</span>
        </li>
      </ul>
    </div>
  </section>
</template>

<script>
/**
 * Rule 19 parity for the web ThresholdStrip: same payload, end figures only
 * (CSJ 2026-09-21). The full cost breakdown stays on web.
 */
export default {
  name: 'MobileThresholdStrip',
  props: { data: { type: Object, default: () => ({ strip: null, lines: [] }) } },
  data() {
    return { expanded: false };
  },
  computed: {
    strip() {
      return (this.data?.lines ?? []).find((l) => l.key === this.data?.strip) || null;
    },
    others() {
      return (this.data?.lines ?? []).filter((l) => l.key !== this.data?.strip);
    },
  },
  methods: {
    fmt(n) {
      return '£' + Math.round(Number(n) || 0).toLocaleString('en-GB');
    },
  },
};
</script>

<style scoped>
.mt-title { margin: 0; font-size: 16px; font-weight: 800; color: var(--horizon-500); }
.mt-body { margin: 6px 0 10px; font-size: 13px; line-height: 1.5; color: var(--neutral-600); }
.mt-row { display: flex; justify-content: space-between; align-items: baseline; padding: 10px 0; border-top: 1px solid var(--horizon-200); }
.mt-label { font-size: 12px; color: var(--neutral-600); }
.mt-figure { font-size: 15px; font-weight: 800; color: var(--horizon-500); }
.mt-toggle { width: 100%; min-height: 44px; margin-top: 6px; border: 1px solid var(--horizon-200); border-radius: 999px; background: var(--white); font-size: 13px; font-weight: 700; color: var(--horizon-500); }
.mt-more { margin-top: 12px; }
.mt-lever { margin: 0; font-size: 14px; font-weight: 700; color: var(--horizon-500); }
.mt-downside { margin: 6px 0 0; font-size: 13px; line-height: 1.45; color: var(--violet-500); }
.mt-link { display: inline-block; margin-top: 8px; font-size: 13px; font-weight: 700; color: var(--raspberry-500); }
.mt-others { list-style: none; margin: 12px 0 0; padding: 0; }
.mt-other { display: flex; justify-content: space-between; gap: 10px; padding: 8px 0; border-top: 1px solid var(--horizon-200); }
.mt-other__title { font-size: 13px; font-weight: 700; color: var(--horizon-500); }
.mt-other__meta { font-size: 12px; color: var(--neutral-600); text-align: right; }
</style>
