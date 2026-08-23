<template>
  <MobileChrome title="Retirement" subtitle="Your projected retirement income, pensions and projections" :loading="loading" loading-label="your retirement" :contextual-request="contextualRequest">
    <div v-if="loading" class="m-card m-state">
      <p class="m-sub">Loading your retirement position…</p>
    </div>

    <div v-else-if="error" class="m-card m-state">
      <p class="m-err">{{ error }}</p>
      <button class="m-btn" @click="load">Try again</button>
    </div>

    <template v-else>
      <!-- Projected income vs target hero -->
      <div class="m-card m-hero">
        <p class="m-sub m-label">{{ heroHeadline.label }}</p>
        <p class="m-metric">{{ fmt(heroHeadline.value) }}<span class="mr-hero-per">a year</span></p>
        <p class="m-hero-sub">{{ gapNarrative }}</p>
        <div class="mr-hero-split">
          <div class="mr-hero-stat">
            <span class="mr-hero-stat__cap">Target income</span>
            <span class="mr-hero-stat__val">{{ fmt(targetIncome) }}</span>
          </div>
          <div class="mr-hero-stat">
            <span class="mr-hero-stat__cap">{{ incomeComparison.label }}</span>
            <span class="mr-hero-stat__val" :class="incomeComparison.tone">{{ incomeComparison.value }}</span>
          </div>
        </div>
      </div>

      <!-- Retirement target (W-0035). Same endpoint as the web card, same store
           behind it — /m does not get its own write path (Rule 20). -->
      <section class="m-card mr-target">
        <div class="mr-target__head">
          <p class="m-section-label" style="margin-top:0">Your retirement target</p>
          <button
            v-if="!editingTarget"
            type="button"
            class="m-btn-ghost mr-target__edit"
            data-testid="retirement-target-edit"
            @click="startEditingTarget"
          >{{ targetIsStated ? 'Change' : 'Set' }}</button>
        </div>

        <p v-if="targetError" class="m-err" role="alert" data-testid="retirement-target-error">{{ targetError }}</p>

        <template v-if="!editingTarget">
          <div class="m-detail-row">
            <span class="m-detail-key">Income you want each year</span>
            <span class="m-detail-value" data-testid="retirement-target-income">{{ hasTargetIncome ? fmt(targetIncome) : 'Not set' }}</span>
          </div>
          <div class="m-detail-row">
            <span class="m-detail-key">Age you want to retire</span>
            <span class="m-detail-value" data-testid="retirement-target-age">{{ targetRetirementAge || 'Not set' }}</span>
          </div>
          <p class="m-sub mr-target__caption">{{ targetCaption }}</p>
        </template>

        <form v-else class="mr-target__form" @submit.prevent="saveTarget">
          <label class="mr-target__field">
            <span class="mr-target__label">Income you want each year</span>
            <input
              v-model="targetForm.target_retirement_income"
              type="number"
              min="0"
              step="500"
              inputmode="numeric"
              class="mr-target__input"
              data-testid="retirement-target-income-input"
            />
          </label>

          <label class="mr-target__field">
            <span class="mr-target__label">Age you want to retire</span>
            <input
              v-model="targetForm.target_retirement_age"
              type="number"
              min="50"
              max="100"
              inputmode="numeric"
              class="mr-target__input"
              data-testid="retirement-target-age-input"
            />
          </label>

          <p class="m-sub mr-target__caption">
            Every figure on this screen is built on this target.
          </p>

          <div class="mr-target__actions">
            <button type="button" class="m-btn-ghost" :disabled="savingTarget" @click="cancelEditingTarget">Cancel</button>
            <button type="submit" class="m-btn" :disabled="savingTarget" data-testid="retirement-target-save">
              {{ savingTarget ? 'Saving…' : 'Save target' }}
            </button>
          </div>
        </form>
      </section>

      <!-- Pensions list (CSJ: pension account cards near the top, under the hero) -->
      <div class="m-card">
        <div class="m-cap-head" style="margin-top:0">
          <p class="m-section-label">Your pensions</p>
          <div v-if="accountLimit" class="m-cap">
            <span class="m-cap__count" :class="{ 'm-cap__count--full': atCap }">{{ accountCount }} of {{ accountLimit }} pensions used</span>
            <button v-if="paidUpgradeAvailable" type="button" class="m-cap__upgrade" @click="goUpgrade">Upgrade</button>
          </div>
        </div>
        <p v-if="!pensions.length" class="m-sub" style="margin-bottom:0">
          No pensions recorded yet. Add a pension to see your full retirement picture.
        </p>
        <div v-else>
          <button
            v-for="p in pensions"
            :key="p.routeKey"
            type="button"
            class="mr-pension"
            @click="openPension(p)"
          >
            <span class="mr-pension__left">
              <span class="mr-pension__name">{{ p.name }}</span>
              <span class="mr-pension__type">{{ p.typeLabel }}</span>
            </span>
            <span class="mr-pension__right">
              <span class="mr-pension__value">{{ p.valueLabel }}</span>
              <span class="mr-pension__view">View</span>
            </span>
          </button>
        </div>
      </div>

      <!-- Headline figures -->
      <div class="m-card m-detail-rows">
        <p class="m-section-label" style="margin-top:0">Overview</p>
        <div class="m-detail-row">
          <span class="m-detail-key">Defined Contribution pension value</span>
          <span class="m-detail-value">{{ fmt(totalPensionWealth) }}</span>
        </div>
        <div class="m-detail-row">
          <span class="m-detail-key">Years to retirement</span>
          <span class="m-detail-value">{{ yearsToRetirement != null ? yearsToRetirement : '—' }}</span>
        </div>
        <div class="m-detail-row">
          <span class="m-detail-key">Target retirement age</span>
          <span class="m-detail-value">{{ targetRetirementAge || '—' }}</span>
        </div>
      </div>

      <!-- Server-owned product reconciliation and age-banded projection -->
      <div class="m-card m-detail-rows">
        <p class="m-section-label" style="margin-top:0">Retirement income projection</p>
        <p v-if="projError" class="m-sub" style="margin-bottom:0">{{ projError }}</p>
        <template v-else-if="planningProjection">
          <p class="mr-proj-intro">
            Planning projection at age {{ planningProjection.target_retirement_age }}:
            <strong>{{ fmt(planningProjection.planning_total_at_target_age) }} a year</strong>
          </p>

          <p class="mr-proj-subhead">Income sources</p>
          <div
            v-for="product in planningProducts"
            :key="`${product.resource_type}-${product.resource_id}`"
            class="m-detail-row"
          >
            <span class="m-detail-key">{{ product.name }} from age {{ product.commencement_age }}</span>
            <span class="m-detail-value">{{ fmt(product.annual_income) }} a year</span>
          </div>

          <p class="mr-proj-subhead">Income by age</p>
          <div
            v-for="band in planningAgeBands"
            :key="`${band.start_age}-${band.end_age}`"
            class="m-detail-row"
          >
            <span class="m-detail-key">Age {{ band.start_age }}–{{ band.end_age }}</span>
            <span class="m-detail-value">{{ fmt(band.annual_income) }} a year</span>
          </div>

          <p class="mr-proj-note">
            This planning projection uses a {{ planningAssumptions.sustainable_withdrawal_rate?.percent }}%
            sustainable withdrawal rate for Defined Contribution pensions, {{ planningAssumptions.growth_rate_percent }}%
            growth, {{ planningAssumptions.fee_rate_percent }}% fees ({{ planningAssumptions.net_growth_rate_percent }}%
            net growth), {{ planningAssumptions.inflation_rate_percent }}% inflation, and the contributions recorded on
            each pension. Figures are {{ planningAssumptions.basis || 'nominal' }}. Uncertainty ranges are separate from
            this primary planning projection.
          </p>
        </template>
        <template v-else-if="pot">
          <div class="m-detail-row">
            <span class="m-detail-key">Current pot value</span>
            <span class="m-detail-value">{{ fmt(pot.current_value) }}</span>
          </div>
          <div class="m-detail-row">
            <span class="m-detail-key">Monthly contributions</span>
            <span class="m-detail-value">{{ fmt(pot.monthly_contribution) }}</span>
          </div>
          <p class="mr-proj-note">The reconciled planning projection is not available yet.</p>
        </template>
        <p v-else class="m-sub" style="margin-bottom:0">No projection available yet.</p>
      </div>

      <!-- Recommendations -->
      <div v-if="recommendations.length" class="m-card">
        <p class="m-section-label" style="margin-top:0">Recommended actions</p>
        <article v-for="(rec, i) in recommendations" :key="rec.type || rec.title || i" class="mr-rec">
          <h3 class="mr-rec__title">{{ rec.title || rec.action || 'Recommendation' }}</h3>
          <p v-if="rec.description" class="mr-rec__desc">{{ rec.description }}</p>
        </article>
      </div>
    </template>
  </MobileChrome>
</template>

<script>
import { store } from '../../store.js';
import { apiGet, apiPost, apiPut } from '../../api.js';
import { handleAuthExpiry } from '../../authExpiry.js';
import MobileChrome from '../../components/MobileChrome.vue';
import { buildContextualConversationRequest } from '../../fyn/contextualConversation.js';
import { upgradeMixin } from '../../mixins/upgrade.js';
import { retirementIncomeHeadline } from '../../../js/utils/retirementHeadline.js';

function formatCurrency(value) {
  if (value == null || value === '' || isNaN(Number(value))) return '—';
  return new Intl.NumberFormat('en-GB', { style: 'currency', currency: 'GBP', maximumFractionDigits: 0 }).format(Number(value));
}

const TYPE_LABELS = {
  dc: 'Defined Contribution',
  db: 'Defined Benefit',
  state: 'State Pension',
};

export default {
  name: 'MobileRetirement',
  components: { MobileChrome },
  mixins: [upgradeMixin],
  data: () => ({
    loading: true,
    error: '',
    data: null,
    analysis: null,
    analysisReady: false,
    pot: null,
    incomeDrawdown: null,
    planningProjection: null,
    projError: '',
    loadGeneration: 0,
    // W-0035 — the retirement target, and whether the user chose it.
    requiredCapital: null,
    editingTarget: false,
    savingTarget: false,
    targetError: '',
    targetForm: { target_retirement_income: null, target_retirement_age: null },
  }),
  computed: {
    profile() { return this.data?.profile || null; },
    dcPensions() { return this.data?.dc_pensions || []; },
    dbPensions() { return this.data?.db_pensions || []; },
    statePension() { return this.data?.state_pension || null; },
    // Free-tier cap nudge (5.1). Gate counts DC+DB only (state pension excluded);
    // account_limit null = unlimited tier → hide nudge.
    accountCount() { return this.data?.account_count ?? (this.dcPensions.length + this.dbPensions.length); },
    accountLimit() { return this.data?.account_limit ?? null; },
    atCap() { return this.accountLimit != null && this.accountCount >= this.accountLimit; },
    contextualRequest() {
      if (this.atCap) return null;
      return buildContextualConversationRequest({
        action: 'add',
        resourceType: 'retirement',
        currentDestination: { screen: 'retirement', params: {}, fallback: 'dashboard' },
        origin: { kind: 'surface_action' },
      });
    },
    projectedIncome() {
      const planningTotal = this.planningProjection?.planning_total_at_target_age;
      if (planningTotal != null && !isNaN(Number(planningTotal))) return Number(planningTotal);
      if (this.analysisReady) {
        const analyzed = this.analysis?.projected_income;
        return analyzed != null && !isNaN(Number(analyzed)) ? Number(analyzed) : null;
      }
      if ((this.dbPensions?.length || 0) > 0) return null;
      const projected = this.incomeDrawdown?.yearly_income?.[0]?.total_income;
      if (projected != null && !isNaN(Number(projected))) return Number(projected);
      return null;
    },
    targetIncome() {
      const target = Number(this.analysisReady
        ? this.analysis?.target_income
        : this.profile?.target_retirement_income);
      if (Number.isFinite(target) && target > 0) return target;

      // W-0035. The analysis reads retirement_profiles directly with no fallback,
      // so /m showed "—" where the web app showed a derived figure. Same source as
      // the web app now — RequiredCapitalCalculator, one endpoint — and the caption
      // below says which of the two it is.
      const derived = Number(this.requiredCapital?.required_income);
      return Number.isFinite(derived) && derived > 0 ? derived : null;
    },
    hasTargetIncome() { return this.targetIncome != null; },
    /**
     * 'profile' when the user stated a target, 'calculated' when the calculator
     * fell back to a proportion of their income. Presenting the second as the
     * user's own figure is the defect W-0035 fixed.
     */
    targetIsStated() {
      return this.requiredCapital?.income_source === 'profile'
        || Number(this.profile?.target_retirement_income) > 0;
    },
    targetCaption() {
      if (!this.hasTargetIncome) {
        return 'Tell us what you want to retire on — every projection here is built on it.';
      }
      return this.targetIsStated
        ? 'The figure you told us you want.'
        : 'Worked out from your income, because you have not set a target yet.';
    },
    incomeGap() {
      // Measured against whatever the hero leads with, so a final-salary household
      // is compared on its secured income rather than on a pot projection of zero.
      if (!this.hasTargetIncome || this.heroHeadline.value == null) return null;
      return this.targetIncome - this.heroHeadline.value;
    },
    isSurplus() { return this.hasTargetIncome && this.incomeGap != null && this.incomeGap <= 0; },
    incomeComparison() {
      if (!this.hasTargetIncome || this.incomeGap == null) {
        return { label: 'Comparison', value: '—', tone: '' };
      }
      return {
        label: this.isSurplus ? 'Surplus' : 'Shortfall',
        value: this.fmt(Math.abs(this.incomeGap)),
        tone: this.isSurplus ? 'mr-pos' : 'mr-neg',
      };
    },
    totalPensionWealth() {
      return this.dcPensions.reduce((sum, pension) => (
        sum + (Number(pension.current_fund_value) || 0)
      ), 0);
    },
    yearsToRetirement() {
      if (!this.targetRetirementAge) return null;
      if (Number(this.pot?.retirement_age) === Number(this.targetRetirementAge)) {
        return this.pot?.years_to_retirement ?? null;
      }
      const y = this.analysisReady ? this.analysis?.years_to_retirement : null;
      return y != null ? y : null;
    },
    targetRetirementAge() { return this.profile?.target_retirement_age || null; },
    projectionAgeLabel() {
      const source = this.pot?.retirement_age_source;
      if (source === 'user_profile' || source === 'retirement_profile') return 'your target retirement age';
      if (source === 'pension') return 'the retirement age recorded on your pension';
      return 'an assumed retirement age';
    },
    currentAgeAssumption() {
      return this.pot?.current_age_source === 'assumed'
        ? `This projection uses an assumed current age of ${this.pot.current_age}.`
        : '';
    },
    planningProducts() { return this.planningProjection?.products || []; },
    planningAgeBands() { return this.planningProjection?.age_bands || []; },
    planningAssumptions() { return this.planningProjection?.assumptions || {}; },
    recommendations() { return this.analysis?.recommendations || []; },
    /**
     * What the hero leads with, from the ONE home shared with the dashboard card
     * and named after the rule the web module page already applies: a household
     * with no defined contribution pot leads with the income its schemes have
     * already secured, not with a projection of a pot it does not have.
     *
     * Before this, the hero preferred `planning_total_at_target_age`, which models
     * pots only and returns a literal 0 for a final-salary-only household — so the
     * page read "Projected retirement income £0 a year" to a user holding an NHS
     * scheme paying £35,000 (W-0244). `guaranteed_annual_income` is computed once
     * in `RetirementAgent` and never re-derived here.
     */
    heroHeadline() {
      return retirementIncomeHeadline({
        potValue: this.totalPensionWealth,
        guaranteedIncome: this.analysisReady ? this.analysis?.guaranteed_annual_income : null,
        projectedIncome: this.projectedIncome,
      });
    },
    gapNarrative() {
      if (this.heroHeadline.isGuaranteed && !this.hasTargetIncome) {
        return 'This is the income your defined benefit schemes and State Pension have already secured. Add a target retirement income to see how it compares.';
      }
      if (!this.hasTargetIncome) return 'Add a target retirement income to see how your projection compares.';
      if (this.heroHeadline.value == null) return 'A projected income is not available yet.';
      if (this.isSurplus) {
        return `You are on track to exceed your target by ${this.fmt(Math.abs(this.incomeGap))} a year.`;
      }
      return `You have a shortfall of ${this.fmt(this.incomeGap)} a year against your target.`;
    },
    pensions() {
      const list = [];
      for (const p of this.dcPensions) {
        list.push({
          routeKey: `dc-${p.id}`,
          type: 'dc',
          id: p.id,
          typeLabel: TYPE_LABELS.dc,
          name: p.scheme_name || p.provider || 'Defined Contribution Pension',
          valueLabel: this.fmt(p.current_fund_value),
        });
      }
      for (const p of this.dbPensions) {
        list.push({
          routeKey: `db-${p.id}`,
          type: 'db',
          id: p.id,
          typeLabel: TYPE_LABELS.db,
          name: p.scheme_name || 'Defined Benefit Pension',
          valueLabel: `${this.fmt(p.accrued_annual_pension)} a year`,
        });
      }
      if (this.statePension) {
        const annual = Number(this.statePension.state_pension_forecast_annual || 0);
        list.push({
          routeKey: `state-${this.statePension.id || 'self'}`,
          type: 'state',
          id: this.statePension.id || 'self',
          typeLabel: TYPE_LABELS.state,
          name: 'State Pension',
          valueLabel: `${this.fmt(annual)} a year`,
        });
      }
      return list;
    },
  },
  created() {
    // Same-route verify refresh: the onboarding chat bumps this after
    // applying an edit on this very screen — refetch so the page shows the
    // just-edited figures (no remount happens without a route change).
    this.$watch(() => store.screenRefreshTick, () => { this.load(); });
    this.load();
  },
  methods: {
    fmt(v) { return formatCurrency(v); },
    goBack() { this.$router.push({ name: 'dashboard' }); },
    openPension(p) {
      this.$router.push({ name: 'm-retirement-pension', params: { type: p.type, id: String(p.id) } });
    },

    startEditingTarget() {
      this.targetError = '';
      this.targetForm = {
        // Only a stated figure pre-fills. Putting the derived one in the box would
        // turn "we worked this out" into "you chose this" the moment they save.
        target_retirement_income: Number(this.profile?.target_retirement_income) > 0
          ? Number(this.profile.target_retirement_income)
          : null,
        target_retirement_age: this.targetRetirementAge ?? null,
      };
      this.editingTarget = true;
    },

    cancelEditingTarget() {
      this.editingTarget = false;
      this.targetError = '';
    },

    /**
     * Same endpoint and same store as the desktop card — PUT /api/retirement/goals
     * -> RetirementProfileStore (Rule 20). Omitted values are left alone rather
     * than cleared, so only what the user answered is sent.
     */
    async saveTarget() {
      const income = this.toNumberOrNull(this.targetForm.target_retirement_income);
      const age = this.toNumberOrNull(this.targetForm.target_retirement_age);

      if (income === null && age === null) {
        this.targetError = 'Enter a target income, a target retirement age, or both.';
        return;
      }

      const payload = {};
      if (income !== null) payload.target_retirement_income = income;
      if (age !== null) payload.target_retirement_age = age;

      this.savingTarget = true;
      this.targetError = '';
      try {
        const { ok, status, data } = await apiPut('/api/retirement/goals', payload, store.token);
        if (handleAuthExpiry({ status }, this.$router)) return;
        if (!ok) {
          this.targetError = data?.message || 'We could not save your retirement target.';
          return;
        }
        this.editingTarget = false;
        // Every figure on this screen derives from the target, so reload the lot.
        await this.load();
      } catch {
        this.targetError = 'Network error. Please try again.';
      } finally {
        this.savingTarget = false;
      }
    },

    toNumberOrNull(value) {
      if (value === null || value === undefined || value === '') return null;
      const parsed = Number(value);
      return Number.isFinite(parsed) ? parsed : null;
    },
    async load() {
      const generation = ++this.loadGeneration;
      this.loading = true;
      this.error = '';
      this.data = null;
      this.analysis = null;
      this.analysisReady = false;
      this.pot = null;
      this.incomeDrawdown = null;
      this.planningProjection = null;
      this.projError = '';
      this.requiredCapital = null;
      try {
        const [indexRes, analyzeRes, requiredCapitalRes] = await Promise.all([
          apiGet('/api/retirement', store.token),
          apiPost('/api/retirement/analyze', {}, store.token),
          // W-0035. Supplies the derived target and, crucially, `income_source` —
          // the flag that says whether the user chose the figure or we did.
          apiGet('/api/retirement/required-capital', store.token),
        ]);
        if (generation !== this.loadGeneration) return;
        if (requiredCapitalRes.ok) {
          this.requiredCapital = requiredCapitalRes.data?.data || null;
        }
        if (handleAuthExpiry(indexRes, this.$router)) return;
        if (indexRes.ok) {
          this.data = indexRes.data?.data || indexRes.data || {};
        } else {
          this.error = indexRes.data?.message || 'We could not load your retirement data.';
          return;
        }
        const analysisEnvelope = analyzeRes.data || {};
        if (analyzeRes.ok && analysisEnvelope.success !== false) {
          this.analysis = analysisEnvelope.data || analysisEnvelope;
          this.analysisReady = true;
        }
        await this.loadProjections(generation);
      } catch {
        if (generation !== this.loadGeneration) return;
        this.error = 'Network error. Please try again.';
      } finally {
        if (generation === this.loadGeneration) this.loading = false;
      }
    },
    async loadProjections(generation) {
      try {
        const { ok, data } = await apiGet('/api/retirement/projections', store.token);
        if (generation !== this.loadGeneration) return;
        if (ok) {
          const payload = data?.data || data || {};
          this.pot = payload.pension_pot_projection || null;
          this.incomeDrawdown = payload.income_drawdown || null;
          this.planningProjection = payload.planning_projection || null;
        } else {
          this.projError = 'Projections are not available right now.';
        }
      } catch {
        if (generation !== this.loadGeneration) return;
        this.projError = 'Projections are not available right now.';
      }
    },
  },
};
</script>

<style scoped>
/* Retirement target (W-0035) — mirrors the profile screen's inline edit pattern. */
.mr-target__head { display: flex; align-items: center; justify-content: space-between; gap: 12px; }
.mr-target__edit { flex: 0 0 auto; padding: 6px 12px; }
.mr-target__caption { margin: 10px 0 0; }
.mr-target__form { display: flex; flex-direction: column; gap: 14px; margin-top: 12px; }
.mr-target__field { display: block; }
.mr-target__label { display: block; font-size: 13px; color: var(--horizon-300); margin-bottom: 6px; }
.mr-target__input { width: 100%; padding: 12px; border: 1px solid var(--horizon-200); border-radius: 8px; background: var(--white); color: var(--horizon-500); font-size: 15px; }
.mr-target__actions { display: flex; justify-content: flex-end; gap: 10px; }

.mr-hero-per { font-size: 14px; font-weight: 600; color: var(--horizon-300); margin-left: 6px; }
.mr-hero-split { display: flex; gap: 16px; margin-top: 16px; padding-top: 16px; border-top: 1px solid var(--horizon-400); }
.mr-hero-stat { flex: 1; }
.mr-hero-stat__cap { display: block; font-size: 12px; color: var(--horizon-300); margin-bottom: 2px; }
.mr-hero-stat__val { display: block; font-size: 18px; font-weight: 900; color: var(--white); }
.mr-pos { color: var(--spring-400); }
.mr-neg { color: var(--raspberry-300); }

.mr-pension { display: flex; align-items: center; justify-content: space-between; gap: 12px; width: 100%; text-align: left; background: transparent; border: 0; border-bottom: 1px solid var(--horizon-100); padding: 14px 0; cursor: pointer; }
.mr-pension:last-child { border-bottom: 0; padding-bottom: 0; }
.mr-pension:first-of-type { padding-top: 4px; }
.mr-pension:active { opacity: 0.7; }
.mr-pension__left { display: flex; flex-direction: column; gap: 2px; min-width: 0; }
.mr-pension__name { font-size: 15px; font-weight: 700; color: var(--horizon-500); }
.mr-pension__type { font-size: 12px; color: var(--neutral-500); }
.mr-pension__right { display: flex; flex-direction: column; align-items: flex-end; gap: 2px; flex-shrink: 0; }
.mr-pension__value { font-size: 14px; font-weight: 700; color: var(--horizon-500); white-space: nowrap; }
.mr-pension__view { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: var(--raspberry-500); }

.mr-proj-note { font-size: 12px; color: var(--neutral-500); line-height: 1.5; margin-top: 12px; }
.mr-proj-intro { margin: 0 0 12px; font-size: 14px; color: var(--horizon-500); line-height: 1.5; }
.mr-proj-subhead { margin: 14px 0 2px; font-size: 11px; font-weight: 800; letter-spacing: 0.5px; text-transform: uppercase; color: var(--neutral-500); }

.mr-rec { border: 1px solid var(--light-gray); border-radius: var(--radius-lg); padding: 14px; margin-bottom: 10px; }
.mr-rec:last-child { margin-bottom: 0; }
.mr-rec__title { font-size: 14px; font-weight: 700; color: var(--horizon-500); line-height: 1.3; }
.mr-rec__desc { font-size: 13px; color: var(--neutral-600); line-height: 1.5; margin-top: 4px; }
</style>
