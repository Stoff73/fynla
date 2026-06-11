<template>
  <MobileChrome title="Protection" subtitle="Your insurance cover and the gaps that remain" :loading="loading" loading-label="your protection">
    <div v-if="loading" class="m-card m-state">
      <p class="m-sub">Loading your protection position…</p>
    </div>

    <div v-else-if="error" class="m-card m-state">
      <p class="m-err">{{ error }}</p>
      <button class="m-btn" @click="load">Try again</button>
    </div>

    <template v-else>
      <!-- Total cover hero -->
      <div class="m-card m-hero">
        <p class="m-sub m-label">Total lump-sum cover</p>
        <p class="m-metric">{{ fmt(totalLumpSumCover) }}</p>
        <p class="m-hero-sub">
          Across {{ totalPolicyCount }} {{ totalPolicyCount === 1 ? 'policy' : 'policies' }}.
          <template v-if="annualIncomeCover > 0">Plus {{ fmt(annualIncomeCover) }} a year of income-style cover.</template>
        </p>
      </div>

      <!-- Coverage gaps -->
      <div class="m-card">
        <p class="m-section-label" style="margin-top:0">Coverage gaps</p>
        <p v-if="!openGaps.length" class="m-sub" style="margin-bottom:0">
          No shortfalls identified against your debts and income. Your cover looks well-matched to your needs.
        </p>
        <div v-else>
          <div v-for="gap in openGaps" :key="gap.key" class="mp-gap">
            <div class="mp-gap__head">
              <span class="mp-gap__label">{{ gap.label }}</span>
              <span class="mp-gap__tag" :class="`mp-gap__tag--${gap.severity}`">{{ gap.severityLabel }}</span>
            </div>
            <div class="mp-gap__foot">
              <span class="mp-gap__shortfall">{{ fmt(gap.shortfall) }}{{ gap.perYear ? ' a year' : '' }} short</span>
              <span class="mp-gap__detail">{{ fmt(gap.have) }} of {{ fmt(gap.need) }}{{ gap.perYear ? ' p.a.' : '' }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Policies -->
      <div class="m-card">
        <p class="m-section-label" style="margin-top:0">Policies</p>
        <p v-if="!policies.length" class="m-sub" style="margin-bottom:0">
          You have no protection policies recorded. Adding cover protects your family's income and debts if something happens to you.
        </p>
        <div v-else>
          <button
            v-for="p in policies"
            :key="`${p.policyType}-${p.id}`"
            type="button"
            class="mp-policy"
            @click="openPolicy(p)"
          >
            <div class="mp-policy__main">
              <span class="mp-policy__provider">{{ p.provider || 'Unknown provider' }}</span>
              <span class="mp-policy__type">{{ p.typeLabel }}</span>
            </div>
            <div class="mp-policy__right">
              <span class="mp-policy__cover">{{ p.coverDisplay }}</span>
              <span class="mp-policy__premium">{{ p.premiumDisplay }}</span>
            </div>
          </button>
        </div>
      </div>
    </template>
  </MobileChrome>
</template>

<script>
import { store } from '../../store.js';
import { apiGet } from '../../api.js';
import MobileChrome from '../../components/MobileChrome.vue';

function formatCurrency(value) {
  if (value == null || value === '' || isNaN(Number(value))) return '—';
  return new Intl.NumberFormat('en-GB', { style: 'currency', currency: 'GBP', maximumFractionDigits: 0 }).format(Number(value));
}

const TYPE_LABELS = {
  life: 'Life Insurance',
  criticalIllness: 'Critical Illness',
  incomeProtection: 'Income Protection',
  disability: 'Disability',
  sicknessIllness: 'Sickness/Illness',
};

// Annualise a benefit given its frequency.
function annualise(amount, frequency) {
  const v = parseFloat(amount || 0);
  if (frequency === 'weekly') return v * 52;
  if (frequency === 'annually' || frequency === 'annual' || frequency === 'yearly') return v;
  // monthly is the default for benefit amounts
  return v * 12;
}

export default {
  name: 'MobileProtection',
  components: { MobileChrome },
  data: () => ({ loading: true, error: '', payload: null }),
  computed: {
    profile() { return this.payload?.profile || {}; },

    // Flatten the index payload's grouped policies into a single tappable list.
    policies() {
      const groups = this.payload?.policies || {};
      const out = [];
      const push = (arr, policyType) => {
        (arr || []).forEach((raw) => {
          const isLumpSum = policyType === 'life' || policyType === 'criticalIllness';
          const cover = isLumpSum
            ? parseFloat(raw.sum_assured || 0)
            : parseFloat(raw.benefit_amount || 0);
          out.push({
            ...raw,
            policyType,
            typeLabel: TYPE_LABELS[policyType] || 'Policy',
            isLumpSum,
            coverDisplay: isLumpSum
              ? formatCurrency(cover)
              : `${formatCurrency(cover)} / ${this.shortFrequency(raw.benefit_frequency)}`,
            premiumDisplay: raw.premium_amount
              ? `${formatCurrency(raw.premium_amount)} / ${this.shortFrequency(raw.premium_frequency)}`
              : 'No premium recorded',
          });
        });
      };
      // Controller keys are snake_case; map each to the route policyType slug.
      push(groups.life_insurance, 'life');
      push(groups.critical_illness, 'criticalIllness');
      push(groups.income_protection, 'incomeProtection');
      push(groups.disability, 'disability');
      push(groups.sickness_illness, 'sicknessIllness');
      // Highest cover first.
      return out.sort((a, b) => {
        const av = a.isLumpSum ? parseFloat(a.sum_assured || 0) : annualise(a.benefit_amount, a.benefit_frequency);
        const bv = b.isLumpSum ? parseFloat(b.sum_assured || 0) : annualise(b.benefit_amount, b.benefit_frequency);
        return bv - av;
      });
    },

    totalPolicyCount() { return this.policies.length; },

    // Lump-sum cover = life + critical illness sum assured.
    totalLumpSumCover() {
      return this.policies
        .filter((p) => p.isLumpSum)
        .reduce((sum, p) => sum + parseFloat(p.sum_assured || 0), 0);
    },

    // Annualised income-replacement style cover (income protection + sickness + disability).
    annualIncomeCover() {
      return this.policies
        .filter((p) => !p.isLumpSum)
        .reduce((sum, p) => sum + annualise(p.benefit_amount, p.benefit_frequency), 0);
    },

    annualIncome() { return parseFloat(this.profile.annual_income || 0); },
    totalDebt() {
      return parseFloat(this.profile.mortgage_balance || 0) + parseFloat(this.profile.other_debts || 0);
    },

    lifeCover() {
      return this.policies
        .filter((p) => p.policyType === 'life')
        .reduce((sum, p) => sum + parseFloat(p.sum_assured || 0), 0);
    },
    criticalIllnessCover() {
      return this.policies
        .filter((p) => p.policyType === 'criticalIllness')
        .reduce((sum, p) => sum + parseFloat(p.sum_assured || 0), 0);
    },
    incomeProtectionCover() {
      return this.policies
        .filter((p) => p.policyType === 'incomeProtection')
        .reduce((sum, p) => sum + annualise(p.benefit_amount, p.benefit_frequency), 0);
    },

    // Mirrors the web overview's gap targets: debt fully covered, income 75%,
    // critical illness 2x income, income-protection target 50% of income.
    gaps() {
      const list = [
        {
          key: 'debt',
          label: 'Debt protection',
          have: this.lifeCover,
          need: this.totalDebt,
          shortfall: Math.max(0, this.totalDebt - this.lifeCover),
          perYear: false,
        },
        {
          key: 'income',
          label: 'Income replacement',
          have: this.incomeProtectionCover,
          need: this.annualIncome * 0.75,
          shortfall: Math.max(0, this.annualIncome * 0.75 - this.incomeProtectionCover),
          perYear: true,
        },
        {
          key: 'ci',
          label: 'Critical illness',
          have: this.criticalIllnessCover,
          need: this.annualIncome * 2,
          shortfall: Math.max(0, this.annualIncome * 2 - this.criticalIllnessCover),
          perYear: false,
        },
      ];
      return list.map((g) => ({ ...g, ...this.severity(g.shortfall) }));
    },

    openGaps() { return this.gaps.filter((g) => g.shortfall > 0 && g.need > 0); },
  },
  async created() { await this.load(); },
  methods: {
    fmt(v) { return formatCurrency(v); },
    shortFrequency(freq) {
      const map = { monthly: 'mo', weekly: 'wk', quarterly: 'qtr', annually: 'yr', annual: 'yr', yearly: 'yr' };
      return map[freq] || (freq || 'mo');
    },
    severity(shortfall) {
      if (shortfall <= 0) return { severity: 'none', severityLabel: 'Covered' };
      if (shortfall < 50000) return { severity: 'violet', severityLabel: 'Low' };
      if (shortfall < 150000) return { severity: 'violet', severityLabel: 'Medium' };
      return { severity: 'raspberry', severityLabel: 'High' };
    },
    openPolicy(p) {
      this.$router.push(`/protection/policy/${p.policyType}/${p.id}`);
    },
    goBack() { this.$router.push({ name: 'dashboard' }); },
    async load() {
      this.loading = true;
      this.error = '';
      this.payload = null;
      try {
        const { ok, data } = await apiGet('/api/protection', store.token);
        if (ok) this.payload = data?.data || data || {};
        else this.error = data?.message || 'We could not load your protection cover.';
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
.mp-gap { padding: 12px 0; border-bottom: 1px solid var(--horizon-100); }
.mp-gap:first-of-type { padding-top: 4px; }
.mp-gap:last-of-type { border-bottom: 0; padding-bottom: 0; }
.mp-gap__head { display: flex; align-items: baseline; justify-content: space-between; gap: 12px; margin-bottom: 6px; }
.mp-gap__label { font-size: 14px; font-weight: 700; color: var(--horizon-500); }
.mp-gap__tag { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; padding: 2px 8px; border-radius: var(--radius-sm); }
.mp-gap__tag--violet { color: var(--violet-500); background: var(--light-blue-100); }
.mp-gap__tag--raspberry { color: var(--white); background: var(--raspberry-500); }
.mp-gap__foot { display: flex; align-items: baseline; justify-content: space-between; gap: 12px; }
.mp-gap__shortfall { font-size: 13px; font-weight: 700; color: var(--raspberry-500); }
.mp-gap__detail { font-size: 12px; color: var(--neutral-500); white-space: nowrap; }

.mp-policy { display: flex; align-items: center; justify-content: space-between; gap: 12px; width: 100%; text-align: left; background: transparent; border: 0; border-bottom: 1px solid var(--horizon-100); padding: 14px 0; cursor: pointer; }
.mp-policy:first-of-type { padding-top: 4px; }
.mp-policy:last-of-type { border-bottom: 0; padding-bottom: 0; }
.mp-policy:active { opacity: 0.6; }
.mp-policy__main { min-width: 0; }
.mp-policy__provider { display: block; font-size: 15px; font-weight: 700; color: var(--horizon-500); }
.mp-policy__type { display: block; font-size: 12px; color: var(--neutral-500); margin-top: 2px; }
.mp-policy__right { text-align: right; flex-shrink: 0; }
.mp-policy__cover { display: block; font-size: 15px; font-weight: 700; color: var(--horizon-500); white-space: nowrap; }
.mp-policy__premium { display: block; font-size: 12px; color: var(--neutral-500); margin-top: 2px; white-space: nowrap; }
</style>
