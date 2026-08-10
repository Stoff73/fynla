<template>
  <MobileChrome
    title="Goal details"
    :subtitle="goalName"
    :loading="loading"
    loading-label="this goal"
    :edit-details="canEdit"
    :contextual-request="contextualRequest"
    back
    @back="goBack"
  >
    <div v-if="error" class="m-card m-state">
      <p class="m-err">{{ error }}</p>
      <button class="m-btn" @click="load">Try again</button>
    </div>

    <template v-else-if="goal">
      <div class="m-card m-detail-header">
        <h1 class="m-h1">{{ goalName }}</h1>
        <p class="m-sub">{{ goal.display_goal_type || label(goal.goal_type) }}</p>
        <p v-if="goal.description" class="mgd-rationale">{{ goal.description }}</p>
      </div>

      <div class="m-card m-hero">
        <p class="m-sub m-label">Current progress</p>
        <p class="m-metric">{{ fmt(goal.current_amount) }}</p>
        <p class="m-hero-sub">{{ percent(goal.progress_percentage) }} of {{ fmt(goal.target_amount) }} target</p>
      </div>

      <div class="m-card m-detail-rows">
        <p class="m-section-label">Goal</p>
        <div v-for="row in goalRows" :key="row.key" class="m-detail-row">
          <span class="m-detail-key">{{ row.key }}</span>
          <span class="m-detail-value">{{ row.value }}</span>
        </div>
      </div>

      <div class="m-card m-detail-rows">
        <p class="m-section-label">Dates</p>
        <div v-for="row in dateRows" :key="row.key" class="m-detail-row">
          <span class="m-detail-key">{{ row.key }}</span>
          <span class="m-detail-value">{{ row.value }}</span>
        </div>
      </div>

      <div class="m-card m-detail-rows">
        <p class="m-section-label">Contributions</p>
        <div v-for="row in contributionRows" :key="row.key" class="m-detail-row">
          <span class="m-detail-key">{{ row.key }}</span>
          <span class="m-detail-value">{{ row.value }}</span>
        </div>
      </div>

      <div v-if="milestoneRows.length" class="m-card m-detail-rows">
        <p class="m-section-label">Milestones</p>
        <div v-for="row in milestoneRows" :key="row.key" class="m-detail-row">
          <span class="m-detail-key">{{ row.key }}</span>
          <span class="m-detail-value">{{ row.value }}</span>
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

export default {
  name: 'MobileGoalDetail',
  components: { MobileChrome },
  data: () => ({ loading: true, error: '', goal: null, milestones: [] }),
  computed: {
    goalId() { return Number(this.$route.params.id); },
    goalName() { return this.goal?.name || this.goal?.goal_name || 'Goal'; },
    canEdit() { return this.goal?.is_primary_owner !== false; },
    contextualRequest() {
      if (!this.canEdit || !Number.isInteger(this.goalId) || this.goalId < 1) return null;
      return buildContextualConversationRequest({
        action: 'edit',
        resourceType: 'goal',
        resourceId: this.goalId,
        currentDestination: {
          screen: 'goal_detail',
          params: { goal_id: this.goalId },
          fallback: 'goals',
        },
        origin: { kind: 'surface_action' },
      });
    },
    goalRows() {
      return [
        { key: 'Target', value: this.fmt(this.goal.target_amount) },
        { key: 'Current value', value: this.fmt(this.goal.current_amount) },
        { key: 'Progress', value: this.percent(this.goal.progress_percentage) },
        { key: 'Status', value: this.label(this.goal.status) },
        { key: 'Ownership', value: this.label(this.goal.ownership_type) },
      ];
    },
    dateRows() {
      return [
        { key: 'Created', value: this.fmtDate(this.goal.created_at) },
        { key: 'Target date', value: this.fmtDate(this.goal.target_date) },
      ];
    },
    contributionRows() {
      return [
        { key: 'Contribution', value: this.goal.monthly_contribution == null ? '—' : this.fmt(this.goal.monthly_contribution) },
        { key: 'Frequency', value: this.label(this.goal.contribution_frequency) },
        { key: 'Required monthly', value: this.goal.required_monthly_contribution == null ? '—' : this.fmt(this.goal.required_monthly_contribution) },
        { key: 'Last contribution', value: this.fmtDate(this.goal.last_contribution_date) },
      ];
    },
    milestoneRows() {
      if (this.milestones.length) {
        return this.milestones.map((milestone, index) => ({
          key: `Milestone ${index + 1}`,
          value: `${this.percent(milestone.percentage ?? milestone.progress_percentage)} · ${milestone.reached ? 'Reached' : 'Next'}`,
        }));
      }
      return [
        this.goal.current_milestone == null ? null : { key: 'Current milestone', value: this.percent(this.goal.current_milestone) },
        this.goal.next_milestone == null ? null : { key: 'Next milestone', value: this.percent(this.goal.next_milestone) },
      ].filter(Boolean);
    },
  },
  async created() { await this.load(); },
  methods: {
    fmt: formatCurrency,
    percent(value) {
      if (value == null || isNaN(Number(value))) return '—';
      return `${Number(value).toLocaleString('en-GB', { maximumFractionDigits: 1 })}%`;
    },
    label(value) {
      if (!value) return '—';
      return String(value).replace(/_/g, ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());
    },
    fmtDate(value) {
      if (!value) return '—';
      const parsed = new Date(value);
      if (isNaN(parsed.getTime())) return '—';
      return parsed.toLocaleDateString('en-GB', { day: '2-digit', month: '2-digit', year: 'numeric' });
    },
    goBack() { this.$router.push({ name: 'm-goals' }); },
    async load() {
      this.loading = true;
      this.error = '';
      this.goal = null;
      this.milestones = [];
      if (!Number.isInteger(this.goalId) || this.goalId < 1) {
        this.error = 'This goal could not be found.';
        this.loading = false;
        return;
      }
      try {
        const response = await apiGet(`/api/goals/${this.goalId}`, store.token);
        if (handleAuthExpiry(response, this.$router)) return;
        if (!response.ok) {
          this.error = response.data?.message || 'We could not load this goal.';
          return;
        }
        const data = response.data?.data || response.data || {};
        this.goal = data.goal || null;
        this.milestones = Array.isArray(data.milestones) ? data.milestones : [];
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
.mgd-rationale { margin: 12px 0 0; color: var(--neutral-600); font-size: 14px; line-height: 1.5; }
</style>
