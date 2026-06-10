<template>
  <div class="gamified-dash">

    <div v-if="loading" class="gd-loading">Loading your dashboard…</div>
    <div v-else-if="error" class="gd-loading">{{ error }}</div>

    <template v-else>
      <!-- ===================== MOBILE (mirrors the mobile app) ===================== -->
      <div class="gd-mobile">
        <div class="md-scroll-hero">
          <section class="md-level" aria-labelledby="gdm-level-h">
            <div class="md-level__pie" role="img" :aria-label="`Level ${level}, ${progressPercent} percent complete`">
              <svg class="md-level__pie-svg" viewBox="0 0 100 100" aria-hidden="true">
                <circle class="md-level__pie-track" cx="50" cy="50" r="44" />
                <circle class="md-level__pie-arc" cx="50" cy="50" r="44" :style="{ '--progress': progressPercent }" />
              </svg>
              <div class="md-level__pie-inner">
                <p class="md-level__pie-label">Level</p>
                <p class="md-level__pie-num">{{ level }}</p>
              </div>
            </div>
            <div>
              <h2 class="md-level__heading" id="gdm-level-h">{{ actionsCompleted }} of {{ actionsTotal }} actions complete</h2>
              <p class="md-level__sub">Complete actions to reach <strong>Level {{ level + 1 }}</strong>.</p>
            </div>
          </section>
        </div>

        <div class="md-callout" role="note">
          <div class="md-callout__top">
            <p class="md-callout__levelup">LEVEL<br>UP</p>
            <div class="md-callout__top-copy">
              <p class="md-callout__lead">You're ahead of <strong>{{ percentile }}%</strong> of people</p>
              <p class="md-callout__sub">Complete your actions to get further ahead, level up and change your financial future</p>
            </div>
          </div>

          <div class="md-callout__carousel">
            <div class="md-accordion" role="tablist" aria-label="Focus areas">
              <button
                v-for="(cat, ci) in cats" :key="'acc-' + cat.key" type="button"
                class="md-accordion__card" :class="{ 'is-active': activeCat === ci }"
                role="tab" :aria-selected="activeCat === ci ? 'true' : 'false'" :aria-label="cat.label"
                @click="activeCat = ci"
              >
                <span class="md-accordion__icon" aria-hidden="true" v-html="cat.icon"></span>
                <span class="md-accordion__content">
                  <span class="md-callout__slide-stat">{{ statFor(ci) }}</span>
                  <span class="md-callout__slide-area">{{ cat.label }}</span>
                  <span class="md-callout__slide-info">{{ cat.info }}</span>
                </span>
              </button>
            </div>

            <div class="md-callout__dots" role="tablist" aria-label="Focus area navigation">
              <button
                v-for="(cat, ci) in cats" :key="'dot-' + cat.key" type="button"
                class="md-callout__dot" :class="{ 'is-active': activeCat === ci }"
                :aria-label="cat.label" @click="activeCat = ci"
              ></button>
            </div>

            <section class="md-recs" aria-labelledby="gdm-recs-h">
              <div class="md-section-head">
                <h3 class="md-section-head__title" id="gdm-recs-h">Recommendations</h3>
                <span class="md-section-head__count">{{ doneCount }} / {{ totalCount }}</span>
              </div>
              <ul class="md-recs__list">
                <li v-if="!activeRecs.length" class="md-rec md-rec--empty">
                  <span class="md-rec__action"><span class="md-rec__text"><span class="md-rec__title">No recommendations here right now.</span></span></span>
                </li>
                <li v-for="(rec, idx) in activeRecs" :key="rec.id" class="md-rec" :class="{ 'is-done': rec.done }">
                  <button type="button" class="md-rec__check-btn" :aria-pressed="rec.done ? 'true' : 'false'" :aria-label="rec.done ? 'Mark as not done' : 'Mark complete'" @click="toggleRec(rec)">
                    <span class="md-rec__check" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" width="14" height="14"><path stroke="#fff" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg></span>
                  </button>
                  <a href="#" class="md-rec__action" @click.prevent="openRec(rec)">
                    <span class="md-rec__text">
                      <span class="md-rec__title">{{ rec.title }}</span>
                      <span class="md-rec__meta">{{ rec.meta }}</span>
                    </span>
                    <svg class="md-rec__chevron" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24" width="18" height="18"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                  </a>
                  <button type="button" class="md-rec__skip" aria-label="Skip this recommendation" @click="skipRec(rec)">Skip</button>
                </li>
              </ul>
              <a href="#" class="md-recs__view-all" @click.prevent="goto(cats[activeCat].route)">View all recommendations</a>
            </section>
          </div>
        </div>

        <section class="md-panels" aria-labelledby="gdm-fin-h">
          <div class="md-section-head"><h3 class="md-section-head__title" id="gdm-fin-h">Your finances</h3></div>
          <div class="md-panels__list">
            <component :is="'a'" v-for="p in finances" :key="'m-' + p.key" href="#" class="md-panel" :class="[`md-panel--${p.tone}`, { 'md-panel--wide': p.wide }]" @click.prevent="goto(p.route)">
              <div v-if="p.viz === 'bar'" class="md-panel__viz md-panel__viz--bar" aria-hidden="true">
                <div class="md-panel__bar"><div class="md-panel__bar-fill" :style="{ width: p.barFill + '%' }"></div></div>
                <p class="md-panel__bar-label"><strong>{{ p.barValue }}</strong> {{ p.barUnit }}</p>
              </div>
              <div v-else class="md-panel__viz" :style="{ '--progress': p.progress }" aria-hidden="true">
                <div class="md-panel__viz-inner"><span class="md-panel__viz-num">{{ p.vizNum }}</span><span class="md-panel__viz-cap">{{ p.vizCap }}</span></div>
              </div>
              <div class="md-panel__body">
                <p class="md-panel__label"><span class="md-panel__title-icon" aria-hidden="true" v-html="p.icon"></span>{{ p.label }}</p>
                <p class="md-panel__value">{{ p.value }}</p>
                <p class="md-panel__caption">{{ p.caption }}</p>
              </div>
            </component>
          </div>
        </section>
      </div>

      <!-- ===================== DESKTOP (expanded) ===================== -->
      <div class="gd-desktop">
        <section class="gd-header">
          <div class="gd-header__top">
            <span class="gd-header__level">Level {{ level }}</span>
            <span class="gd-header__meta">{{ actionsCompleted }} of {{ actionsTotal }} actions complete — reach <strong>Level {{ level + 1 }}</strong></span>
          </div>
          <div class="gd-bar" role="img" :aria-label="`Level progress ${progressPercent} percent`"><div class="gd-bar__fill" :style="{ width: Math.max(progressPercent, 3) + '%' }"></div></div>
          <div class="gd-header__levelup">
            <span class="gd-header__lu-badge">LEVEL UP</span>
            <div>
              <p class="gd-header__lu-lead">You're ahead of <strong>{{ percentile }}%</strong> of people</p>
              <p class="gd-header__lu-sub">Complete your actions to get further ahead, level up and change your financial future</p>
            </div>
          </div>
        </section>

        <section class="gd-card" aria-labelledby="gdd-focus-h">
          <h2 id="gdd-focus-h" class="sr-only" style="position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0);">Where to focus</h2>
          <div class="gd-tabs" role="tablist" aria-label="Where to focus">
            <button
              v-for="(cat, ci) in cats" :key="'tab-' + cat.key" type="button"
              class="gd-tab" :class="{ 'is-active': activeCat === ci }"
              role="tab" :aria-selected="activeCat === ci ? 'true' : 'false'" @click="activeCat = ci"
            >
              <span class="gd-tab__stat">{{ statFor(ci) }}</span>
              <span class="gd-tab__area">{{ cat.label }}</span>
            </button>
          </div>

          <div class="gd-recs">
            <div class="gd-recs__head">
              <h3>Recommendations</h3>
              <span class="count">{{ doneCount }} / {{ totalCount }}</span>
            </div>
            <p class="gd-recs__info">{{ cats[activeCat].info }}</p>
            <ul class="md-recs__list">
              <li v-if="!activeRecs.length" class="md-rec md-rec--empty">
                <span class="md-rec__action"><span class="md-rec__text"><span class="md-rec__title">No recommendations here right now.</span></span></span>
              </li>
              <li v-for="rec in activeRecs" :key="'d-' + rec.id" class="md-rec" :class="{ 'is-done': rec.done }">
                <button type="button" class="md-rec__check-btn" :aria-pressed="rec.done ? 'true' : 'false'" :aria-label="rec.done ? 'Mark as not done' : 'Mark complete'" @click="toggleRec(rec)">
                  <span class="md-rec__check" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" width="14" height="14"><path stroke="#fff" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg></span>
                </button>
                <a href="#" class="md-rec__action" @click.prevent="openRec(rec)">
                  <span class="md-rec__text">
                    <span class="md-rec__title">{{ rec.title }}</span>
                    <span class="md-rec__meta">{{ rec.meta }}</span>
                  </span>
                  <svg class="md-rec__chevron" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24" width="18" height="18"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                </a>
                <button type="button" class="md-rec__skip" aria-label="Skip this recommendation" @click="skipRec(rec)">Skip</button>
              </li>
            </ul>
          </div>
        </section>

        <section aria-labelledby="gdd-fin-h">
          <div class="gd-section-head"><h2 id="gdd-fin-h">Your finances</h2></div>
          <div class="gd-finances__grid">
            <a v-for="p in finances" :key="'d-' + p.key" href="#" class="md-panel" :class="`md-panel--${p.tone}`" @click.prevent="goto(p.route)">
              <div v-if="p.viz === 'bar'" class="md-panel__viz md-panel__viz--bar" aria-hidden="true">
                <div class="md-panel__bar"><div class="md-panel__bar-fill" :style="{ width: p.barFill + '%' }"></div></div>
                <p class="md-panel__bar-label"><strong>{{ p.barValue }}</strong> {{ p.barUnit }}</p>
              </div>
              <div v-else class="md-panel__viz" :style="{ '--progress': p.progress }" aria-hidden="true">
                <div class="md-panel__viz-inner"><span class="md-panel__viz-num">{{ p.vizNum }}</span><span class="md-panel__viz-cap">{{ p.vizCap }}</span></div>
              </div>
              <div class="md-panel__body">
                <p class="md-panel__label"><span class="md-panel__title-icon" aria-hidden="true" v-html="p.icon"></span>{{ p.label }}</p>
                <p class="md-panel__value">{{ p.value }}</p>
                <p class="md-panel__caption">{{ p.caption }}</p>
              </div>
            </a>
          </div>
        </section>
      </div>
    </template>
  </div>
</template>

<script>
import api from '@/services/api';
import logger from '@/utils/logger';

const ICON = {
  saveTax: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="22" height="22"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>',
  retirement: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="22" height="22"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>',
  savings: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="22" height="22"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>',
  netWorth: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>',
  shield: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>',
  card: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>',
  clock: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>',
  investment: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><path stroke-linecap="round" stroke-linejoin="round" d="M7 12l3-3 3 3 4-4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z"/></svg>',
};

export default {
  name: 'GamifiedDashboard',
  data() {
    return {
      loading: true,
      error: '',
      data: null,
      activeCat: 0,
      buckets: { save_tax: [], retirement: [], savings: [] },
      level: 1,
      progressPercent: 0,
      actionsCompleted: 0,
      actionsTotal: 0,
      percentile: 57,
      cats: [
        { key: 'save_tax', route: '/tax-strategy', label: 'Save tax', icon: ICON.saveTax, info: 'Use your full ISA and pension allowances to keep more of what you earn.' },
        { key: 'retirement', route: '/retirement', label: 'Retirement', icon: ICON.retirement, info: 'Close your projected income gap — small increases now compound.' },
        { key: 'savings', route: '/savings', label: 'Savings', icon: ICON.savings, info: 'Build your emergency fund and earn more on your cash.' },
      ],
    };
  },
  computed: {
    activeRecs() {
      return this.buckets[this.cats[this.activeCat].key] || [];
    },
    totalCount() {
      return this.activeRecs.length;
    },
    doneCount() {
      return this.activeRecs.filter((r) => r.done).length;
    },
    finances() {
      const d = this.data || {};
      const modsRaw = d.modules || {};
      const find = (k) => (Array.isArray(modsRaw) ? (modsRaw.find((m) => m.key === k) || {}) : (modsRaw[k] || {}));
      const num = (v) => Number(v) || 0;
      const nw = d.net_worth || {};
      const prot = find('protection');
      const sav = find('savings');
      const ret = find('retirement');
      const inv = find('investment');
      const trend = num(nw.trend);

      const efMonths = num(sav.emergency_fund_months);
      const efTarget = 6;
      const savValue = sav.total_savings != null ? sav.total_savings : sav.value;

      const projected = num(ret.projected_income);
      const target = num(ret.target_income);
      const retPct = target > 0 ? Math.min(100, Math.round((projected / target) * 100)) : 0;
      const retValue = ret.income_gap != null ? ret.income_gap : ret.value;

      const invValue = num(inv.portfolio_value != null ? inv.portfolio_value : inv.value);
      const invAccounts = num(inv.accounts_count);
      const invHoldings = num(inv.holdings_count);
      const protVal = prot.value != null ? prot.value : prot.total_coverage;

      return [
        { key: 'net_worth', label: 'Net worth', tone: 'horizon', icon: ICON.netWorth, value: this.fmt(nw.total), route: '/net-worth', viz: 'donut', progress: 72, vizNum: (trend >= 0 ? '+' : '') + trend + '%', vizCap: 'Trend', caption: this.fmt(nw.assets) + ' assets' },
        { key: 'protection', label: 'Protection', tone: 'raspberry', icon: ICON.shield, value: this.fmt(protVal), route: '/protection', viz: 'donut', progress: protVal > 0 ? 85 : 0, vizNum: protVal > 0 ? 'Active' : 'None', vizCap: 'Cover', caption: protVal > 0 ? 'Cover in place' : 'Add your cover' },
        { key: 'savings', label: 'Savings', tone: 'spring', icon: ICON.card, value: this.fmt(savValue), route: '/savings', viz: 'bar', barFill: efTarget > 0 ? Math.min(100, Math.round((efMonths / efTarget) * 100)) : 0, barValue: efMonths ? (Math.round(efMonths * 10) / 10) : '0', barUnit: '/ ' + efTarget + ' months', caption: efMonths >= efTarget ? 'Emergency fund on track' : (efMonths > 0 ? 'Building your fund' : 'Start your emergency fund') },
        { key: 'retirement', label: 'Retirement', tone: 'violet', icon: ICON.clock, value: this.fmt(retValue), route: '/retirement', viz: 'bar', barFill: retPct, barValue: retPct + '%', barUnit: 'of target', caption: target > 0 ? 'Towards your target' : 'Plan your retirement' },
        { key: 'investment', label: 'Investment', tone: 'horizon', icon: ICON.investment, wide: true, value: this.fmt(invValue), route: '/investment', viz: 'donut', progress: invValue > 0 ? 72 : 0, vizNum: invValue > 0 ? String(invAccounts) : '0', vizCap: invAccounts === 1 ? 'Account' : 'Accounts', caption: invValue > 0 ? `${invHoldings} ${invHoldings === 1 ? 'holding' : 'holdings'}` : 'Add your investments' },
      ];
    },
  },
  methods: {
    fmt(n) {
      return '£' + Math.round(Number(n) || 0).toLocaleString('en-GB');
    },
    statFor(ci) {
      const list = this.buckets[this.cats[ci].key] || [];
      const top = list.filter((r) => !r.done).sort((a, b) => (b.value || 0) - (a.value || 0))[0];
      return top && top.value ? this.fmt(top.value) : '—';
    },
    goto(route) {
      if (route && this.$route.path !== route) this.$router.push(route);
    },
    openRec(rec) {
      // Open Fyn with the recommendation as a question (mirrors mobile).
      this.$store.dispatch('aiChat/open');
      window.dispatchEvent(new Event('fyn-open-chat'));
    },
    toggleRec(rec) {
      if (rec.done) { rec.done = false; return; }
      rec.done = true;
      // Persist completion so the gamification engine awards points (best-effort).
      if (rec.id && String(rec.id).indexOf('rec_') !== 0) {
        api.post(`/recommendations/${rec.id}/mark-done`, {
          module: rec.module || 'general',
          recommendation_text: rec.title || '',
        }).then(() => {
          this.$store.dispatch('gamification/fetchStatus').catch(() => {});
        }).catch(() => { /* non-fatal */ });
      }
    },
    skipRec(rec) {
      const list = this.buckets[this.cats[this.activeCat].key];
      const i = list.indexOf(rec);
      if (i !== -1) list.splice(i, 1);
    },
    async load() {
      this.loading = true;
      this.error = '';
      try {
        const res = await api.get('/v1/mobile/dashboard');
        const d = res.data?.data || res.data || {};
        this.data = d;
        const recs = d.recommendations || {};
        ['save_tax', 'retirement', 'savings'].forEach((k) => {
          this.buckets[k] = (recs[k] || []).map((r) => ({
            id: r.id, title: r.title, meta: r.meta, value: Number(r.value) || 0,
            module: r.module, done: !!r.done,
          }));
        });
        const lv = d.level || {};
        this.level = lv.level ?? 1;
        this.progressPercent = lv.progress_percent ?? 0;
        this.actionsCompleted = lv.actions_completed ?? 0;
        this.actionsTotal = lv.actions_total ?? 0;
        this.percentile = d.percentile ?? 57;
      } catch (e) {
        logger.error?.('[GamifiedDashboard] load failed', e);
        this.error = 'We could not load your dashboard. Please refresh to try again.';
      } finally {
        this.loading = false;
      }
    },
  },
  mounted() {
    this.load();
  },
};
</script>

<style src="./gamified-dashboard.css"></style>
