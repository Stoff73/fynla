<template>
  <div>
    <div class="m-card m-detail-header">
      <button class="m-back" @click="goBack" aria-label="Back to protection">Back</button>
      <h1 class="m-h1">{{ headerProvider }}</h1>
      <p class="m-sub">{{ typeLabel }}</p>
    </div>

    <div v-if="loading" class="m-card m-state">
      <p class="m-sub">Loading policy details…</p>
    </div>

    <div v-else-if="error" class="m-card m-state">
      <p class="m-err">{{ error }}</p>
      <button class="m-btn" @click="load">Try again</button>
    </div>

    <template v-else-if="policy">
      <!-- Cover hero -->
      <div class="m-card m-hero">
        <p class="m-sub m-label">{{ coverageLabel }}</p>
        <p class="m-metric">{{ fmt(coverageAmount) }}</p>
        <p class="m-hero-sub">{{ fmt(policy.premium_amount) }} {{ policy.premium_frequency || 'monthly' }} premium, {{ fmt(annualCost) }} a year</p>
      </div>

      <!-- Policy details -->
      <div class="m-card m-detail-rows">
        <p class="m-section-label">Policy details</p>
        <div class="m-detail-row">
          <span class="m-detail-key">Provider</span>
          <span class="m-detail-value">{{ policy.provider || '—' }}</span>
        </div>
        <div class="m-detail-row">
          <span class="m-detail-key">Policy number</span>
          <span class="m-detail-value">{{ policy.policy_number || '—' }}</span>
        </div>
        <div class="m-detail-row">
          <span class="m-detail-key">Policy type</span>
          <span class="m-detail-value">{{ typeLabel }}</span>
        </div>
        <div v-if="lifeSubtypeLabel" class="m-detail-row">
          <span class="m-detail-key">Life policy type</span>
          <span class="m-detail-value">{{ lifeSubtypeLabel }}</span>
        </div>
      </div>

      <!-- Coverage -->
      <div class="m-card m-detail-rows">
        <p class="m-section-label">Coverage</p>
        <div class="m-detail-row">
          <span class="m-detail-key">{{ coverageLabel }}</span>
          <span class="m-detail-value">{{ fmt(coverageAmount) }}</span>
        </div>
        <div v-if="!isLumpSum && policy.benefit_frequency" class="m-detail-row">
          <span class="m-detail-key">Benefit frequency</span>
          <span class="m-detail-value">{{ frequencyLabel(policy.benefit_frequency) }}</span>
        </div>
        <div v-if="policy.deferred_period_weeks" class="m-detail-row">
          <span class="m-detail-key">Deferred period</span>
          <span class="m-detail-value">{{ policy.deferred_period_weeks }} weeks</span>
        </div>
        <div v-if="policy.benefit_period_months" class="m-detail-row">
          <span class="m-detail-key">Benefit period</span>
          <span class="m-detail-value">{{ policy.benefit_period_months }} months</span>
        </div>
        <div v-if="policy.occupation_class" class="m-detail-row">
          <span class="m-detail-key">Occupation class</span>
          <span class="m-detail-value">{{ policy.occupation_class }}</span>
        </div>
        <div v-if="policy.coverage_type" class="m-detail-row">
          <span class="m-detail-key">Coverage type</span>
          <span class="m-detail-value">{{ policy.coverage_type }}</span>
        </div>
        <div v-if="policy.is_mortgage_protection" class="m-detail-row">
          <span class="m-detail-key">Mortgage protection</span>
          <span class="m-detail-value">Yes</span>
        </div>
        <div v-if="policy.in_trust" class="m-detail-row">
          <span class="m-detail-key">Held in trust</span>
          <span class="m-detail-value">Yes</span>
        </div>
      </div>

      <!-- Premium -->
      <div class="m-card m-detail-rows">
        <p class="m-section-label">Premium</p>
        <div class="m-detail-row">
          <span class="m-detail-key">Premium amount</span>
          <span class="m-detail-value">{{ fmt(policy.premium_amount) }}</span>
        </div>
        <div class="m-detail-row">
          <span class="m-detail-key">Frequency</span>
          <span class="m-detail-value">{{ capitalise(policy.premium_frequency) }}</span>
        </div>
        <div class="m-detail-row">
          <span class="m-detail-key">Annual cost</span>
          <span class="m-detail-value">{{ fmt(annualCost) }}</span>
        </div>
      </div>

      <!-- Dates -->
      <div v-if="policy.policy_start_date || policy.policy_term_years || policy.policy_end_date" class="m-card m-detail-rows">
        <p class="m-section-label">Important dates</p>
        <div v-if="policy.policy_start_date" class="m-detail-row">
          <span class="m-detail-key">Start date</span>
          <span class="m-detail-value">{{ formatDate(policy.policy_start_date) }}</span>
        </div>
        <div v-if="policy.policy_term_years" class="m-detail-row">
          <span class="m-detail-key">Term</span>
          <span class="m-detail-value">{{ policy.policy_term_years }} years</span>
        </div>
        <div v-if="policy.policy_end_date" class="m-detail-row">
          <span class="m-detail-key">End date</span>
          <span class="m-detail-value">{{ formatDate(policy.policy_end_date) }}</span>
        </div>
      </div>

      <!-- Beneficiaries (life only) -->
      <div v-if="policyType === 'life' && policy.beneficiaries" class="m-card m-detail-rows">
        <p class="m-section-label">Beneficiaries</p>
        <p class="mpd-text">{{ policy.beneficiaries }}</p>
      </div>

      <!-- Conditions covered -->
      <div v-if="conditions.length" class="m-card m-detail-rows">
        <p class="m-section-label">Covered conditions</p>
        <ul class="mpd-list">
          <li v-for="(c, i) in conditions" :key="i" class="mpd-list__item">{{ c }}</li>
        </ul>
      </div>
    </template>

    <div v-else class="m-card m-state">
      <p class="m-sub" style="margin-bottom:12px">We could not find that policy.</p>
      <button class="m-btn" @click="goBack">Back to protection</button>
    </div>
  </div>
</template>

<script>
import { store } from '../../store.js';
import { apiGet } from '../../api.js';

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

const LIFE_SUBTYPES = {
  decreasing_term: 'Decreasing Term',
  level_term: 'Level Term',
  whole_of_life: 'Whole of Life',
  term: 'Term',
  family_income_benefit: 'Family Income Benefit',
};

// Maps the route policyType slug to the controller's grouped index key.
const GROUP_KEYS = {
  life: 'life_insurance',
  criticalIllness: 'critical_illness',
  incomeProtection: 'income_protection',
  disability: 'disability',
  sicknessIllness: 'sickness_illness',
};

export default {
  name: 'MobileProtectionPolicy',
  data() {
    return {
      loading: true,
      error: '',
      policy: null,
      policyType: this.$route.params.policyType,
      policyId: parseInt(this.$route.params.id, 10),
    };
  },
  computed: {
    typeLabel() { return TYPE_LABELS[this.policyType] || 'Policy'; },
    headerProvider() { return this.policy?.provider || 'Policy'; },
    isLumpSum() { return this.policyType === 'life' || this.policyType === 'criticalIllness'; },
    coverageLabel() { return this.isLumpSum ? 'Sum assured' : 'Benefit amount'; },
    coverageAmount() {
      if (!this.policy) return 0;
      return this.isLumpSum
        ? parseFloat(this.policy.sum_assured || 0)
        : parseFloat(this.policy.benefit_amount || 0);
    },
    lifeSubtypeLabel() {
      if (this.policyType !== 'life') return null;
      const sub = this.policy?.policy_type;
      if (!sub) return null;
      return LIFE_SUBTYPES[sub] || sub;
    },
    annualCost() {
      if (!this.policy?.premium_amount) return 0;
      const amount = parseFloat(this.policy.premium_amount);
      switch (this.policy.premium_frequency) {
        case 'quarterly': return amount * 4;
        case 'annually': case 'annual': case 'yearly': return amount;
        default: return amount * 12;
      }
    },
    conditions() {
      const raw = this.policy?.conditions_covered;
      if (!raw) return [];
      if (Array.isArray(raw)) return raw;
      try {
        const parsed = JSON.parse(raw);
        return Array.isArray(parsed) ? parsed : [];
      } catch (e) {
        return String(raw).split(',').map((s) => s.trim()).filter(Boolean);
      }
    },
  },
  async created() { await this.load(); },
  methods: {
    fmt(v) { return formatCurrency(v); },
    capitalise(v) {
      if (!v) return '—';
      return String(v).charAt(0).toUpperCase() + String(v).slice(1);
    },
    frequencyLabel(freq) {
      const map = { monthly: 'Monthly', weekly: 'Weekly', annually: 'Annually', lump_sum: 'Lump sum' };
      return map[freq] || this.capitalise(freq);
    },
    formatDate(date) {
      if (!date) return '—';
      const d = new Date(date);
      if (isNaN(d.getTime())) return '—';
      return d.toLocaleDateString('en-GB', { day: '2-digit', month: '2-digit', year: 'numeric' });
    },
    goBack() { this.$router.push('/protection'); },
    async load() {
      this.loading = true;
      this.error = '';
      this.policy = null;
      try {
        const { ok, data } = await apiGet('/api/protection', store.token);
        if (!ok) {
          this.error = data?.message || 'We could not load this policy.';
          return;
        }
        const payload = data?.data || data || {};
        const groups = payload.policies || {};
        const arr = groups[GROUP_KEYS[this.policyType]] || [];
        this.policy = arr.find((p) => p.id === this.policyId) || null;
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
.mpd-text { font-size: 14px; line-height: 1.5; color: var(--horizon-500); white-space: pre-line; }
.mpd-list { list-style: none; margin: 0; padding: 0; }
.mpd-list__item { font-size: 14px; color: var(--horizon-500); padding: 8px 0; border-bottom: 1px solid var(--horizon-100); }
.mpd-list__item:last-child { border-bottom: 0; }
</style>
