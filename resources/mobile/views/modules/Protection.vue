<template>
  <MobileChrome title="Protection" subtitle="Your insurance cover and the gaps that remain" :loading="loading" loading-label="your protection" :contextual-request="contextualRequest">
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
          <button
            v-for="gap in openGaps"
            :key="gap.key"
            type="button"
            class="mp-gap"
            :data-test="`protection-gap-${gap.key}`"
            :aria-expanded="expandedGap === gap.key"
            @click="toggleGap(gap.key)"
          >
            <div class="mp-gap__head">
              <span class="mp-gap__label">{{ gap.label }}</span>
              <span class="mp-gap__tag" :class="`mp-gap__tag--${gap.severity}`">{{ gap.severityLabel }}</span>
            </div>
            <div class="mp-gap__foot">
              <span class="mp-gap__shortfall">{{ fmt(gap.shortfall) }}{{ gap.perYear ? ' a year' : '' }} short</span>
              <span class="mp-gap__detail">{{ fmt(gap.cover) }} of {{ fmt(gap.need) }}{{ gap.perYear ? ' p.a.' : '' }}</span>
            </div>
            <div v-if="expandedGap === gap.key" class="mp-gap__explanation">
              <p>{{ gap.explanation }}</p>
              <p v-if="coverageGaps?.calculated_at" class="mp-gap__calculated">
                Calculated {{ displayDate(coverageGaps.calculated_at) }} from your recorded information
              </p>
              <dl v-if="Object.keys(gap.inputs || {}).length" class="mp-gap__list">
                <template v-for="(value, key) in gap.inputs" :key="key">
                  <dt>{{ fieldLabel(key) }}</dt>
                  <dd>{{ displayInput(value, key) }}</dd>
                </template>
              </dl>
              <div v-if="gap.assumptions?.length" class="mp-gap__assumptions">
                <p class="mp-gap__subhead">Assumptions</p>
                <p v-for="assumption in gap.assumptions" :key="assumption.key">
                  {{ fieldLabel(assumption.key) }}: {{ displayAssumption(assumption) }}
                </p>
              </div>
              <div v-if="gap.relevant_policies?.length" class="mp-gap__policies">
                <p class="mp-gap__subhead">Cover used in this calculation</p>
                <p v-for="policy in gap.relevant_policies" :key="`${policy.type}-${policy.id}`">
                  {{ policy.provider || policy.name || 'Recorded policy' }} · {{ fmt(policy.cover) }}
                </p>
              </div>
            </div>
          </button>
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
              <span v-if="p.sharedNote" class="mp-policy__type">{{ p.sharedNote }}</span>
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
import { handleAuthExpiry } from '../../authExpiry.js';
import MobileChrome from '../../components/MobileChrome.vue';
import { buildContextualConversationRequest } from '../../fyn/contextualConversation.js';

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

export default {
  name: 'MobileProtection',
  components: { MobileChrome },
  data: () => ({ loading: true, error: '', payload: null, expandedGap: null }),
  computed: {
    profile() { return this.payload?.profile || {}; },
    coverageGaps() { return this.payload?.coverage_gaps || null; },

    contextualRequest() {
      return buildContextualConversationRequest({
        action: 'add',
        resourceType: 'protection',
        currentDestination: { screen: 'protection', params: {}, fallback: 'dashboard' },
        origin: { kind: 'surface_action' },
      });
    },

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
            // A joint-life policy covers both spouses and is recorded once, on the
            // account that entered it. Name the other life assured, and say whose
            // record it is when it is not this one's (W-0186).
            sharedNote: raw.joint_life
              ? (raw.is_own_policy === false
                // W-0200. The name is inferred from the spouse link, not recorded on
                // the policy, so the list says "assumed" rather than asserting it.
                ? `Joint life — we have assumed this is ${raw.joint_life_with || 'your spouse'}, recorded on their account`
                : `Joint life — we have assumed the other life assured is ${raw.joint_life_with || 'your spouse'}`)
              : null,
          });
        });
      };
      // Controller keys are snake_case; map each to the route policyType slug.
      push(groups.life_insurance, 'life');
      push(groups.critical_illness, 'criticalIllness');
      push(groups.income_protection, 'incomeProtection');
      push(groups.disability, 'disability');
      push(groups.sickness_illness, 'sicknessIllness');
      return out;
    },

    totalPolicyCount() { return this.policies.length; },

    totalLumpSumCover() {
      return Number(this.coverageGaps?.totals?.cover || 0);
    },

    annualIncomeCover() {
      const category = (this.coverageGaps?.categories || []).find((gap) => gap.key === 'income_protection');
      return Number(category?.cover || 0);
    },

    gaps() {
      return (this.coverageGaps?.categories || []).map((gap) => ({
        ...gap,
        perYear: gap.key === 'income_protection',
        severityLabel: gap.status === 'covered'
          ? 'Covered'
          : String(gap.severity || 'gap').replace(/\b\w/g, (char) => char.toUpperCase()),
      }));
    },

    openGaps() { return this.gaps.filter((gap) => gap.status === 'gap' && Number(gap.shortfall) > 0); },
  },
  async created() { await this.load(); },
  methods: {
    fmt(v) { return formatCurrency(v); },
    shortFrequency(freq) {
      const map = { monthly: 'mo', weekly: 'wk', quarterly: 'qtr', annually: 'yr', annual: 'yr', yearly: 'yr' };
      return map[freq] || (freq || 'mo');
    },
    toggleGap(key) { this.expandedGap = this.expandedGap === key ? null : key; },
    fieldLabel(value) {
      return String(value || '').replace(/_/g, ' ').replace(/\b\w/g, (char) => char.toUpperCase());
    },
    displayInput(value, key) {
      if (Array.isArray(value)) return value.length ? value.join(', ') : 'None recorded';
      if (key === 'number_of_dependants') return String(value);
      if (typeof value === 'number') return this.fmt(value);
      if (value && typeof value === 'object') {
        return Object.entries(value)
          .map(([itemKey, item]) => `${this.fieldLabel(itemKey)}: ${typeof item === 'number' ? this.fmt(item) : item}`)
          .join(', ');
      }
      return value ?? 'Not recorded';
    },
    displayDate(value) {
      const parsed = new Date(value);
      return Number.isNaN(parsed.getTime())
        ? value
        : parsed.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
    },
    displayAssumption(assumption) {
      if (assumption.unit === 'GBP') return this.fmt(assumption.value);
      if (assumption.unit === 'percent') return `${assumption.value}%`;
      if (assumption.unit === 'years') return `${assumption.value} years`;
      return assumption.value;
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
        const { ok, status, data } = await apiGet('/api/protection', store.token);
        if (handleAuthExpiry({ status }, this.$router)) return;
        if (ok) this.payload = data?.data || data || {};
        else this.error = data?.message || 'We could not load your protection cover.';
      } catch {
        this.error = 'Network error. Please try again.';
      } finally {
        this.loading = false;
      }
    },
  },
};
</script>

<style scoped>
.mp-gap { display: block; width: 100%; padding: 12px 0; border: 0; border-bottom: 1px solid var(--horizon-100); background: transparent; text-align: left; cursor: pointer; }
.mp-gap:first-of-type { padding-top: 4px; }
.mp-gap:last-of-type { border-bottom: 0; padding-bottom: 0; }
.mp-gap__head { display: flex; align-items: baseline; justify-content: space-between; gap: 12px; margin-bottom: 6px; }
.mp-gap__label { font-size: 14px; font-weight: 700; color: var(--horizon-500); }
.mp-gap__tag { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; padding: 2px 8px; border-radius: var(--radius-sm); }
.mp-gap__tag--violet { color: var(--violet-500); background: var(--light-blue-100); }
.mp-gap__tag--raspberry { color: var(--white); background: var(--raspberry-500); }
.mp-gap__tag--low { color: var(--spring-600); background: color-mix(in srgb, var(--spring-500) 12%, var(--white)); }
.mp-gap__tag--medium { color: var(--violet-500); background: var(--light-blue-100); }
.mp-gap__tag--high { color: var(--white); background: var(--raspberry-500); }
.mp-gap__foot { display: flex; align-items: baseline; justify-content: space-between; gap: 12px; }
.mp-gap__shortfall { font-size: 13px; font-weight: 700; color: var(--raspberry-500); }
.mp-gap__detail { font-size: 12px; color: var(--neutral-500); white-space: nowrap; }
.mp-gap__explanation { margin-top: 12px; padding: 10px; border-radius: var(--radius-sm); background: var(--horizon-50); color: var(--horizon-500); font-size: 12px; line-height: 1.5; }
.mp-gap__explanation p { margin: 0 0 7px; }
.mp-gap__explanation p:last-child { margin-bottom: 0; }
.mp-gap__calculated { color: var(--neutral-500); font-size: 11px; }
.mp-gap__list { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 4px 12px; margin: 8px 0; }
.mp-gap__list dt { color: var(--neutral-500); }
.mp-gap__list dd { margin: 0; font-weight: 700; text-align: right; }
.mp-gap__subhead { font-weight: 700; color: var(--horizon-500); }

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
