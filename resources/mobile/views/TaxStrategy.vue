<template>
  <MobileChrome title="Tax Strategy" :subtitle="taxYear ? `Tax year ${taxYear}` : 'Your allowances and tax-saving actions'">
    <div v-if="loading" class="m-card m-state">
      <p class="m-sub">Loading your tax position…</p>
    </div>

    <div v-else-if="error" class="m-card m-state">
      <p class="m-err">{{ error }}</p>
      <button class="m-btn" @click="load">Try again</button>
    </div>

    <template v-else>
      <!-- Headroom hero -->
      <div class="m-card m-hero">
        <p class="m-sub m-label">Allowance headroom available</p>
        <p class="m-metric">{{ fmt(totalHeadroom) }}</p>
        <p class="m-hero-sub">Across {{ headroomCount }} {{ headroomCount === 1 ? 'allowance' : 'allowances' }} you haven't fully used this year.</p>
      </div>

      <!-- User allowances -->
      <div class="m-card">
        <p class="m-section-label" style="margin-top:0">{{ isHousehold ? 'Your allowances' : 'Allowances' }}</p>
        <div v-for="a in userAllowances" :key="a.key" class="mts-allow">
          <div class="mts-allow__head">
            <span class="mts-allow__label">{{ a.label }}</span>
            <span class="mts-allow__cap">of {{ fmt(a.amount) }}</span>
          </div>
          <div class="mts-bar">
            <div class="mts-bar__fill" :class="`mts-bar__fill--${a.status}`" :style="{ width: barWidth(a) }"></div>
          </div>
          <div class="mts-allow__foot">
            <span class="mts-allow__remain" :class="`mts-allow__remain--${a.status}`">{{ remainingLabel(a) }}</span>
            <span class="mts-allow__used">{{ fmt(a.used) }} used</span>
          </div>
        </div>
      </div>

      <!-- Spouse allowances (household mode) -->
      <div v-if="isHousehold && spouseAllowances && spouseAllowances.length" class="m-card">
        <p class="m-section-label" style="margin-top:0">Your spouse's allowances</p>
        <div v-for="a in spouseAllowances" :key="a.key" class="mts-allow">
          <div class="mts-allow__head">
            <span class="mts-allow__label">{{ a.label }}</span>
            <span class="mts-allow__cap">of {{ fmt(a.amount) }}</span>
          </div>
          <div class="mts-bar">
            <div class="mts-bar__fill" :class="`mts-bar__fill--${a.status}`" :style="{ width: barWidth(a) }"></div>
          </div>
          <div class="mts-allow__foot">
            <span class="mts-allow__remain" :class="`mts-allow__remain--${a.status}`">{{ remainingLabel(a) }}</span>
            <span class="mts-allow__used">{{ fmt(a.used) }} used</span>
          </div>
        </div>
      </div>

      <!-- Household coordination (married / joint — only in household mode) -->
      <div v-if="isHousehold && householdRecommendations.length" class="m-card mts-household">
        <p class="m-section-label" style="margin-top:0">{{ householdHeading }}</p>
        <p class="mts-household__intro">{{ householdIntro }}</p>
        <article v-for="rec in householdRecommendations" :key="rec.type" class="mts-rec">
          <div class="mts-rec__top">
            <div class="mts-rec__title-wrap">
              <h3 class="mts-rec__title">{{ rec.title }}</h3>
            </div>
            <div v-if="rec.estimated_annual_tax_saved" class="mts-rec__save">
              <span class="mts-rec__save-cap">Saves</span>
              <span class="mts-rec__save-amt">{{ fmt(Math.round(rec.estimated_annual_tax_saved)) }}</span>
              <span class="mts-rec__save-cap">a year</span>
            </div>
          </div>
          <p v-if="rec.description" class="mts-rec__desc">{{ rec.description }}</p>
        </article>
      </div>

      <!-- Recommendations -->
      <div class="m-card">
        <p class="m-section-label" style="margin-top:0">Recommended actions</p>
        <p v-if="!individualRecommendations.length" class="m-sub" style="margin-bottom:0">
          Nothing to act on right now — your allowances are well-utilised and there's no tax-band optimisation to make at your current income.
        </p>
        <div v-else>
          <article v-for="rec in individualRecommendations" :key="rec.type" class="mts-rec" :class="{ 'mts-rec--warning': rec.category === 'warning' }">
            <div class="mts-rec__top">
              <div class="mts-rec__title-wrap">
                <span v-if="rec.category === 'warning'" class="mts-rec__flag">Watch out</span>
                <h3 class="mts-rec__title">{{ rec.title }}</h3>
              </div>
              <div v-if="rec.estimated_annual_tax_saved" class="mts-rec__save">
                <span class="mts-rec__save-cap">Saves</span>
                <span class="mts-rec__save-amt">{{ fmt(Math.round(rec.estimated_annual_tax_saved)) }}</span>
                <span class="mts-rec__save-cap">a year</span>
              </div>
            </div>
            <p v-if="rec.description" class="mts-rec__desc">{{ rec.description }}</p>
            <div v-if="rec.requires_advice || nextStep(rec)" class="mts-rec__foot">
              <button v-if="nextStep(rec)" type="button" class="mts-rec__cta" @click="goToNextStep(rec)">
                {{ nextStep(rec).label }}
              </button>
              <span v-if="rec.requires_advice" class="mts-rec__advice">Speak to an adviser</span>
            </div>
          </article>
        </div>
      </div>
    </template>
  </MobileChrome>
</template>

<script>
import { store } from '../store.js';
import { apiGet } from '../api.js';
import MobileChrome from '../components/MobileChrome.vue';

function formatCurrency(value) {
  if (value == null || value === '' || isNaN(Number(value))) return '—';
  return new Intl.NumberFormat('en-GB', { style: 'currency', currency: 'GBP', maximumFractionDigits: 0 }).format(Number(value));
}

// Maps a recommendation type to the mobile module screen that helps act on it.
// Mirrors the desktop StrategyRecommendationList NEXT_STEPS, repointed at the
// real mobile module detail routes.
const NEXT_STEPS = {
  pa_taper_rescue: { label: 'Open retirement', route: '/retirement' },
  additional_rate_avoidance: { label: 'Open retirement', route: '/retirement' },
  pension_aa_carry_forward: { label: 'Open retirement', route: '/retirement' },
  salary_sacrifice_ni: { label: 'Open retirement', route: '/retirement' },
  isa_topup_vs_psa: { label: 'Open savings', route: '/savings' },
  bed_and_isa: { label: 'Open investment', route: '/investment' },
  dividend_allowance: { label: 'Open investment', route: '/investment' },
};

export default {
  name: 'MobileTaxStrategy',
  components: { MobileChrome },
  data: () => ({ loading: true, error: '', dashboard: null }),
  computed: {
    taxYear() { return this.dashboard?.tax_year || ''; },
    calculationMode() { return this.dashboard?.calculation_mode || 'single'; },
    isHousehold() { return ['dual_earner', 'single_earner_couple'].includes(this.calculationMode); },
    userAllowances() { return this.dashboard?.user_allowances || []; },
    spouseAllowances() { return this.dashboard?.spouse_allowances || null; },
    recommendations() { return this.dashboard?.recommendations || []; },
    individualRecommendations() { return this.recommendations.filter((r) => r.category !== 'household'); },
    householdRecommendations() { return this.recommendations.filter((r) => r.category === 'household'); },
    householdIntro() {
      return this.calculationMode === 'single_earner_couple'
        ? 'The non-working spouse has a full set of unused tax-free allowances. Moving assets into their name uses those allowances without giving up household ownership — spousal transfers between UK-domiciled spouses are exempt from both Capital Gains Tax and Inheritance Tax.'
        : 'These actions only work because both partners contribute. Spousal transfers between UK-domiciled spouses are exempt from Capital Gains Tax and Inheritance Tax.';
    },
    householdHeading() {
      return this.calculationMode === 'single_earner_couple'
        ? 'Move assets to use spouse allowances'
        : 'Coordinate as a household';
    },
    headroom() { return this.userAllowances.filter((a) => Number(a.utilisation_pct) < 90); },
    headroomCount() { return this.headroom.length; },
    totalHeadroom() { return this.headroom.reduce((sum, a) => sum + (Number(a.remaining) || 0), 0); },
  },
  async created() { await this.load(); },
  methods: {
    fmt(v) { return formatCurrency(v); },
    barWidth(a) { return `${Math.min(Number(a.utilisation_pct) || 0, 100)}%`; },
    remainingLabel(a) {
      if (Number(a.utilisation_pct) >= 100 || Number(a.remaining) <= 0) return 'Fully used';
      return `${this.fmt(a.remaining)} of headroom`;
    },
    nextStep(rec) { return NEXT_STEPS[rec.type] || null; },
    goToNextStep(rec) {
      const step = this.nextStep(rec);
      if (step) this.$router.push(step.route);
    },
    goBack() { this.$router.push({ name: 'dashboard' }); },
    async load() {
      this.loading = true;
      this.error = '';
      this.dashboard = null;
      try {
        const { ok, data } = await apiGet('/api/tax-strategy', store.token);
        if (ok) this.dashboard = data?.data || data || {};
        else this.error = data?.message || 'We could not load your tax position.';
      } catch (e) {
        this.error = 'Network error. Please try again.';
      } finally {
        this.loading = false;
      }
    },
  },
};
</script>

<style scoped>
.mts-allow { padding: 12px 0; border-bottom: 1px solid var(--horizon-100); }
.mts-allow:first-of-type { padding-top: 4px; }
.mts-allow:last-of-type { border-bottom: 0; padding-bottom: 0; }
.mts-allow__head { display: flex; align-items: baseline; justify-content: space-between; gap: 12px; margin-bottom: 6px; }
.mts-allow__label { font-size: 14px; font-weight: 700; color: var(--horizon-500); }
.mts-allow__cap { font-size: 12px; color: var(--neutral-500); white-space: nowrap; }
.mts-bar { width: 100%; height: 6px; background: var(--horizon-200); border-radius: var(--radius-full); overflow: hidden; margin-bottom: 6px; }
.mts-bar__fill { height: 6px; border-radius: var(--radius-full); transition: width 0.3s ease; }
.mts-bar__fill--spring { background: var(--spring-500); }
.mts-bar__fill--violet { background: var(--violet-500); }
.mts-bar__fill--raspberry { background: var(--raspberry-500); }
.mts-allow__foot { display: flex; align-items: baseline; justify-content: space-between; gap: 12px; }
.mts-allow__remain { font-size: 12px; font-weight: 700; }
.mts-allow__remain--spring { color: var(--spring-600); }
.mts-allow__remain--violet { color: var(--violet-500); }
.mts-allow__remain--raspberry { color: var(--raspberry-500); }
.mts-allow__used { font-size: 12px; color: var(--neutral-500); white-space: nowrap; }

.mts-household { background: var(--eggshell-500); }
.mts-household__intro { font-size: 13px; color: var(--neutral-600); line-height: 1.5; margin-bottom: 12px; }
.mts-rec { border: 1px solid var(--light-gray); border-radius: var(--radius-lg); padding: 16px; margin-bottom: 12px; background: var(--white); }
.mts-rec:last-child { margin-bottom: 0; }
.mts-rec--warning { border-color: var(--violet-500); }
.mts-rec__top { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; margin-bottom: 6px; }
.mts-rec__title-wrap { flex: 1; min-width: 0; }
.mts-rec__flag { display: inline-block; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: var(--violet-500); background: color-mix(in srgb, var(--violet-500) 12%, var(--white)); padding: 2px 8px; border-radius: var(--radius-sm); margin-bottom: 6px; }
.mts-rec__title { font-size: 15px; font-weight: 700; color: var(--horizon-500); line-height: 1.3; }
.mts-rec__save { text-align: right; flex-shrink: 0; }
.mts-rec__save-cap { display: block; font-size: 11px; color: var(--neutral-500); line-height: 1.1; }
.mts-rec__save-amt { display: block; font-size: 18px; font-weight: 900; color: var(--spring-600); line-height: 1.2; }
.mts-rec__desc { font-size: 13px; color: var(--neutral-600); line-height: 1.5; margin-top: 4px; }
.mts-rec__foot { display: flex; align-items: center; gap: 12px; margin-top: 12px; padding-top: 12px; border-top: 1px solid var(--horizon-100); flex-wrap: wrap; }
.mts-rec__cta { background: transparent; border: 0; padding: 0; font-size: 13px; font-weight: 700; color: var(--raspberry-500); cursor: pointer; }
.mts-rec__advice { font-size: 11px; font-weight: 700; color: var(--violet-500); background: color-mix(in srgb, var(--violet-500) 12%, var(--white)); padding: 2px 8px; border-radius: var(--radius-sm); }
</style>
