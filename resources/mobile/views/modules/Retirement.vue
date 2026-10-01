<template>
  <MobileChrome ref="chrome" title="Retirement" :subtitle="drawing ? 'Your retirement income and pensions' : 'Your projected retirement income, pensions and projections'" :loading="loading" loading-label="your retirement" :contextual-request="contextualRequest">
    <div v-if="loading" class="m-card m-state">
      <p class="m-sub">Loading your retirement position…</p>
    </div>

    <div v-else-if="error" class="m-card m-state">
      <p class="m-err">{{ error }}</p>
      <button class="m-btn" @click="load">Try again</button>
    </div>

    <template v-else>
      <!-- Projected income vs target hero. Hidden on the onboarding verify
           visit (CSJ 2026-09-22): that visit checks the pensions entered. -->
      <!-- Someone drawing their pension: this year's income and how long the pot
           lasts, from the server's drawdown_position, the same block the web page
           reads (TODO item 6, CSJ 2026-10-01; Rule 20). -->
      <template v-if="drawing && !verifying">
        <div class="m-card m-hero" data-testid="retirement-drawing-hero">
          <p class="m-sub m-label">Your take-home this year</p>
          <p class="m-metric">{{ fmt(drawing.income.take_home) }}<span class="mr-hero-per">a year</span></p>
          <p v-if="drawing.retired_since" class="m-hero-sub">Retired since {{ retiredSinceLabel }}<template v-if="drawing.retired_since.age !== null">, at {{ drawing.retired_since.age }}</template></p>
        </div>

        <div class="m-card m-detail-rows" data-testid="retirement-drawing-income">
          <p class="m-section-label" style="margin-top:0">Your income this year</p>
          <div v-for="line in drawing.income.lines" :key="line.key" class="m-detail-row">
            <span class="m-detail-key">{{ line.label }}</span>
            <span class="m-detail-value">{{ fmt(line.amount) }}</span>
          </div>
          <div v-if="statePensionNote !== null" class="m-detail-row">
            <span class="m-detail-key">State Pension{{ statePensionNote }}</span>
            <button type="button" class="m-btn-ghost" @click="addStatePension">{{ drawing.income.state_pension_status === 'missing' ? 'Add it' : 'Update' }}</button>
          </div>
          <div class="m-detail-row">
            <span class="m-detail-key">Income Tax</span>
            <span class="m-detail-value">{{ fmt(drawing.income.income_tax) }}</span>
          </div>
          <div v-if="drawing.income.national_insurance > 0" class="m-detail-row">
            <span class="m-detail-key">National Insurance</span>
            <span class="m-detail-value">{{ fmt(drawing.income.national_insurance) }}</span>
          </div>
          <div class="m-detail-row">
            <span class="m-detail-key"><strong>Take-home</strong></span>
            <span class="m-detail-value"><strong>{{ fmt(drawing.income.take_home) }}</strong></span>
          </div>
        </div>
      </template>

      <div v-if="!verifying && !drawing" class="m-card m-hero">
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
      <section v-if="!verifying && !drawing" class="m-card mr-target">
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

      <!-- Care costs (CSJ 2026-10-01). Same endpoint and store as the web card;
           shown to savers and to anyone drawing. Needs a retirement profile. -->
      <section v-if="!verifying && profile" class="m-card mr-target" data-testid="care-costs">
        <div class="mr-target__head">
          <p class="m-section-label" style="margin-top:0">Care costs in retirement</p>
          <button
            v-if="!editingCare"
            type="button"
            class="m-btn-ghost mr-target__edit"
            data-testid="care-costs-edit"
            @click="startEditingCare"
          >{{ careAnswered ? 'Change' : 'Add' }}</button>
        </div>

        <p v-if="careError" class="m-err" role="alert" data-testid="care-costs-error">{{ careError }}</p>

        <template v-if="!editingCare">
          <template v-if="careAnswered">
            <div class="m-detail-row">
              <span class="m-detail-key">Each year</span>
              <span class="m-detail-value" data-testid="care-costs-annual">{{ careAnnual > 0 ? fmt(careAnnual) + ' a year' : 'None planned' }}</span>
            </div>
            <div v-if="careAnnual > 0" class="m-detail-row">
              <span class="m-detail-key">From age</span>
              <span class="m-detail-value" data-testid="care-costs-start-age">{{ profile.care_start_age || 'Not set' }}</span>
            </div>
            <p class="m-sub mr-target__caption">Your decumulation analysis includes this.</p>
          </template>
          <p v-else class="m-sub mr-target__caption">Add what you plan for care in later life, or say you plan for none, and your decumulation analysis will include it.</p>
        </template>

        <form v-else class="mr-target__form" @submit.prevent="saveCare">
          <label class="mr-target__field">
            <span class="mr-target__label">Care costs each year</span>
            <input
              v-model="careForm.care_cost_annual"
              type="number"
              min="0"
              step="500"
              inputmode="numeric"
              class="mr-target__input"
              data-testid="care-costs-annual-input"
            />
          </label>

          <label class="mr-target__field">
            <span class="mr-target__label">Age care might start</span>
            <input
              v-model="careForm.care_start_age"
              type="number"
              min="50"
              max="125"
              inputmode="numeric"
              class="mr-target__input"
              data-testid="care-costs-start-age-input"
            />
          </label>

          <p class="m-sub mr-target__caption">Enter 0 if you plan for no care costs.</p>

          <div class="mr-target__actions">
            <button type="button" class="m-btn-ghost" :disabled="savingCare" @click="cancelEditingCare">Cancel</button>
            <button type="submit" class="m-btn" :disabled="savingCare" data-testid="care-costs-save">
              {{ savingCare ? 'Saving…' : 'Save care costs' }}
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
        <template v-if="!drawing">
          <div class="m-detail-row">
            <span class="m-detail-key">Years to retirement</span>
            <span class="m-detail-value">{{ yearsToRetirement != null ? yearsToRetirement : '—' }}</span>
          </div>
          <div class="m-detail-row">
            <span class="m-detail-key">Target retirement age</span>
            <span class="m-detail-value">{{ targetRetirementAge || '—' }}</span>
          </div>
        </template>
      </div>

      <!-- Server-owned product reconciliation and age-banded projection.
           Hidden on the onboarding verify visit with the hero above. -->
      <div v-if="drawing && drawing.pot && !verifying" class="m-card m-detail-rows" data-testid="retirement-drawing-lasts">
        <p class="m-section-label" style="margin-top:0">How long your pension lasts</p>
        <p class="mr-proj-intro">
          <template v-if="drawing.pot.drawing_per_year > 0">Drawing {{ fmt(drawing.pot.drawing_per_year) }} a year from {{ fmt(drawing.pot.value) }}</template>
          <template v-else>No drawdown recorded from your {{ fmt(drawing.pot.value) }}</template>
        </p>
        <template v-if="drawing.pot.drawing_per_year > 0">
          <div class="m-detail-row">
            <span class="m-detail-key">Middle outcome (half do better)</span>
            <span class="m-detail-value">{{ lastsLabel(drawing.pot.lasts_to_age.middle) }}</span>
          </div>
          <div class="m-detail-row">
            <span class="m-detail-key">Lower outcome (4 in 5 do better)</span>
            <span class="m-detail-value">{{ lastsLabel(drawing.pot.lasts_to_age.lower) }}</span>
          </div>
        </template>
        <div class="m-detail-row">
          <!-- The source sits with the label: at phone width the value column
               cannot hold it (walked at 390px, 2026-10-01). -->
          <span class="m-detail-key">Life expectancy ({{ drawing.pot.life_expectancy.source === 'ons' ? 'Office for National Statistics' : 'your figure' }})</span>
          <span class="m-detail-value">{{ drawing.pot.life_expectancy.age }}{{ drawing.pot.life_expectancy.source === 'ons' ? ' on average' : '' }}</span>
        </div>
        <div v-if="drawing.pot.income_to_last_to_life_expectancy !== null" class="m-detail-row">
          <span class="m-detail-key"><strong>To last to {{ drawing.pot.life_expectancy.age }}</strong></span>
          <span class="m-detail-value"><strong>about {{ fmt(drawing.pot.income_to_last_to_life_expectancy) }} a year</strong></span>
        </div>
        <p class="mr-proj-note">
          <template v-if="drawing.pot.life_expectancy.source === 'ons'">Many people live longer than the average. </template><template v-if="drawing.pot.income_to_last_to_life_expectancy !== null">The last figure is the yearly income that still lasts to {{ drawing.pot.life_expectancy.age }} in 4 out of 5 outcomes. </template>These are projections, not guarantees. They assume the same {{ fmt(drawing.pot.drawing_per_year) }} each year at your {{ drawing.pot.risk_level_label }} risk level's returns ({{ drawing.pot.expected_return }}% a year), with no charges or inflation.
        </p>
      </div>

      <div v-if="!verifying && !drawing" class="m-card m-detail-rows">
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
      <div v-if="recommendations.length && !verifying" class="m-card">
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
import { store, inOnboardingVerify } from '../../store.js';
import { formatCurrency } from '../../utils/currency.js';
import { apiGet, apiPost, apiPut } from '../../api.js';
import { handleAuthExpiry } from '../../authExpiry.js';
import MobileChrome from '../../components/MobileChrome.vue';
import { buildContextualConversationRequest } from '../../fyn/contextualConversation.js';
import { upgradeMixin } from '../../mixins/upgrade.js';

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
    // RetirementHeadline: every retirement figure this screen shows, computed
    // once on the server (one figure, every surface; CSJ 2026-10-01).
    headline: null,
    // TODO item 6: the server's view for someone drawing their pension, or null.
    drawing: null,
    projError: '',
    loadGeneration: 0,
    // W-0035 — the retirement target, and whether the user chose it.
    requiredCapital: null,
    editingTarget: false,
    savingTarget: false,
    targetError: '',
    editingCare: false,
    savingCare: false,
    careError: '',
    careForm: { care_cost_annual: null, care_start_age: null },
    targetForm: { target_retirement_income: null, target_retirement_age: null },
  }),
  computed: {
    verifying() { return inOnboardingVerify(); },
    // Past State Pension age, when it is not counted: what is missing (null when counted or not due).
    statePensionNote() {
      return { missing: '', not_paid: ': not recorded as being paid', no_amount: ': amount not recorded' }[this.drawing?.income?.state_pension_status] ?? null;
    },
    retiredSinceLabel() {
      return new Date(this.drawing.retired_since.date).toLocaleDateString('en-GB', { month: 'long', year: 'numeric' });
    },
    profile() { return this.data?.profile || null; },
    // null is "never asked", 0 is "none planned" (CSJ 2026-10-01).
    careAnswered() { return this.profile?.care_cost_annual !== null && this.profile?.care_cost_annual !== undefined; },
    careAnnual() { return Number(this.profile?.care_cost_annual) || 0; },
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
      return this.headline ? Number(this.headline.projected_income) : null;
    },
    targetIncome() {
      const target = this.headline?.target_income;
      return target == null ? null : Number(target);
    },
    hasTargetIncome() { return this.targetIncome != null; },
    /**
     * 'profile' when the user stated a target, 'calculated' when the calculator
     * fell back to a proportion of their income. Presenting the second as the
     * user's own figure is the defect W-0035 fixed.
     */
    targetIsStated() {
      return this.headline?.target_source === 'profile';
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
      // Signed, from the server: positive is short, negative is over.
      const gap = this.headline?.income_gap;
      return gap == null ? null : Number(gap);
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
      return Number(this.headline?.dc_value_today) || 0;
    },
    yearsToRetirement() {
      const years = this.headline?.years_to_retirement;
      return years == null ? null : years;
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
      const kind = this.headline?.kind;
      return {
        value: this.headline ? Number(this.headline.value) : null,
        isGuaranteed: kind === 'guaranteed',
        label: kind === 'guaranteed' ? 'Guaranteed retirement income' : 'Projected retirement income',
      };
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
    lastsLabel(age) {
      return age === null ? `lasts beyond ${this.drawing.pot.end_age}` : `runs out by about age ${age}`;
    },
    // On /m a pension is added or changed through Fyn: adding as the screen's
    // own add button does, changing the recorded State Pension as its detail
    // screen does.
    addStatePension() {
      const recorded = ['not_paid', 'no_amount'].includes(this.drawing?.income?.state_pension_status) && this.statePension?.id;
      this.$refs.chrome?.openContextualFyn(buildContextualConversationRequest(recorded
        ? {
          action: 'edit',
          resourceType: 'state_pension',
          resourceId: Number(this.statePension.id),
          // An entity edit names the record in its destination, exactly as the
          // pension detail screen sends it (CreateContextualConversationRequest).
          currentDestination: {
            screen: 'pension_detail',
            params: { pension_id: Number(this.statePension.id), pension_type: 'state' },
            fallback: 'retirement',
          },
          origin: { kind: 'surface_action' },
        }
        : {
          action: 'add',
          resourceType: 'retirement',
          currentDestination: { screen: 'retirement', params: {}, fallback: 'dashboard' },
          origin: { kind: 'surface_action' },
        }));
    },
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

    startEditingCare() {
      this.careError = '';
      this.careForm = {
        care_cost_annual: this.careAnswered ? this.careAnnual : null,
        care_start_age: this.profile?.care_start_age ?? null,
      };
      this.editingCare = true;
    },

    cancelEditingCare() {
      this.editingCare = false;
      this.careError = '';
    },

    /** Same endpoint and store as the web card and Fyn: PUT /api/retirement/goals. */
    async saveCare() {
      const annual = this.toNumberOrNull(this.careForm.care_cost_annual);
      const startAge = this.toNumberOrNull(this.careForm.care_start_age);
      if (annual === null) {
        this.careError = 'Enter a yearly amount, or 0 if you plan for none.';
        return;
      }
      const payload = { care_cost_annual: annual };
      if (startAge !== null) payload.care_start_age = startAge;

      this.savingCare = true;
      this.careError = '';
      try {
        const { ok, status, data } = await apiPut('/api/retirement/goals', payload, store.token);
        if (handleAuthExpiry({ status }, this.$router)) return;
        if (!ok) {
          this.careError = data?.message || 'We could not save your care costs.';
          return;
        }
        this.editingCare = false;
        await this.load();
      } catch {
        this.careError = 'Network error. Please try again.';
      } finally {
        this.savingCare = false;
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
      this.drawing = null;
      this.incomeDrawdown = null;
      this.planningProjection = null;
      this.headline = null;
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
          this.headline = payload.headline || null;
          this.drawing = payload.drawdown_position || null;
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
