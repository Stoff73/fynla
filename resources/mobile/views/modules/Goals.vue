<template>
  <MobileChrome title="Goals and life events" subtitle="Your financial milestones and how they're tracking">
    <div v-if="loading" class="m-card m-state">
      <p class="m-sub">Loading your goals…</p>
    </div>

    <div v-else-if="error" class="m-card m-state">
      <p class="m-err">{{ error }}</p>
      <button class="m-btn" @click="load">Try again</button>
    </div>

    <template v-else>
      <!-- On-track hero -->
      <div class="m-card m-hero">
        <p class="m-sub m-label">Goals on track</p>
        <p class="m-metric">{{ onTrackCount }} of {{ totalGoals }}</p>
        <p class="m-hero-sub">{{ savedLabel }}</p>
      </div>

      <!-- Overall progress -->
      <div v-if="totalGoals" class="m-card">
        <p class="m-section-label" style="margin-top:0">Overall progress</p>
        <div class="mts-allow" style="border-bottom:0;padding-bottom:0">
          <div class="mts-allow__head">
            <span class="mts-allow__label">Saved towards your goals</span>
            <span class="mts-allow__cap">of {{ fmt(totalTarget) }}</span>
          </div>
          <div class="mts-bar">
            <div class="mts-bar__fill" :class="`mts-bar__fill--${overallStatus}`" :style="{ width: overallBarWidth }"></div>
          </div>
          <div class="mts-allow__foot">
            <span class="mts-allow__remain" :class="`mts-allow__remain--${overallStatus}`">{{ overallProgress }}% complete</span>
            <span class="mts-allow__used">{{ fmt(totalCurrent) }} saved</span>
          </div>
        </div>
      </div>

      <!-- Goals list -->
      <div class="m-card">
        <p class="m-section-label" style="margin-top:0">Your goals</p>
        <p v-if="!goals.length" class="m-sub" style="margin-bottom:0">
          You haven't set any goals yet.
        </p>
        <div v-else>
          <div v-for="goal in goals" :key="goal.id" class="mg-goal">
            <div class="mg-goal__head">
              <div class="mg-goal__title-wrap">
                <span class="mg-goal__name">{{ goal.name || goal.goal_name }}</span>
                <span class="mg-goal__type">{{ goal.display_goal_type }}</span>
              </div>
              <span class="mg-goal__status" :class="`mg-goal__status--${statusOf(goal)}`">{{ statusLabel(goal) }}</span>
            </div>
            <div class="mts-bar" style="margin:8px 0 6px">
              <div class="mts-bar__fill" :class="`mts-bar__fill--${statusOf(goal)}`" :style="{ width: barWidth(goal) }"></div>
            </div>
            <div class="mg-goal__foot">
              <span class="mg-goal__amounts">{{ fmt(goal.current_amount) }} of {{ fmt(goal.target_amount) }}</span>
              <span class="mg-goal__remaining">{{ remainingLabel(goal) }}</span>
            </div>
          </div>
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

export default {
  name: 'MobileGoals',
  components: { MobileChrome },
  data: () => ({ loading: true, error: '', goals: [], overview: null }),
  computed: {
    totalGoals() { return this.overview?.total_goals ?? this.goals.length; },
    onTrackCount() { return this.overview?.on_track_count ?? this.goals.filter((g) => g.is_on_track).length; },
    totalTarget() { return Number(this.overview?.total_target ?? this.goals.reduce((s, g) => s + (Number(g.target_amount) || 0), 0)); },
    totalCurrent() { return Number(this.overview?.total_current ?? this.goals.reduce((s, g) => s + (Number(g.current_amount) || 0), 0)); },
    overallProgress() {
      if (this.overview?.overall_progress != null) return Math.round(Number(this.overview.overall_progress));
      return this.totalTarget > 0 ? Math.round((this.totalCurrent / this.totalTarget) * 100) : 0;
    },
    overallBarWidth() { return `${Math.min(this.overallProgress, 100)}%`; },
    overallStatus() {
      if (this.overallProgress >= 100) return 'spring';
      if (this.overallProgress >= 50) return 'violet';
      return 'raspberry';
    },
    savedLabel() {
      if (!this.totalGoals) return 'No goals set yet.';
      return `${this.fmt(this.totalCurrent)} of ${this.fmt(this.totalTarget)} saved so far.`;
    },
  },
  async created() { await this.load(); },
  methods: {
    fmt(v) { return formatCurrency(v); },
    barWidth(goal) {
      const pct = Number(goal.progress_percentage) || 0;
      return `${Math.min(pct, 100)}%`;
    },
    statusOf(goal) {
      if ((Number(goal.progress_percentage) || 0) >= 100 || goal.status === 'completed') return 'spring';
      if (goal.is_on_track) return 'spring';
      const pct = Number(goal.progress_percentage) || 0;
      return pct >= 50 ? 'violet' : 'raspberry';
    },
    statusLabel(goal) {
      if ((Number(goal.progress_percentage) || 0) >= 100 || goal.status === 'completed') return 'Complete';
      return goal.is_on_track ? 'On track' : 'Behind';
    },
    remainingLabel(goal) {
      const months = Number(goal.months_remaining);
      if (!isNaN(months) && months > 0) return `${months} ${months === 1 ? 'month' : 'months'} left`;
      const days = Number(goal.days_remaining);
      if (!isNaN(days) && days > 0) return `${days} ${days === 1 ? 'day' : 'days'} left`;
      return 'Target date passed';
    },
    goBack() { this.$router.push({ name: 'dashboard' }); },
    async load() {
      this.loading = true;
      this.error = '';
      this.goals = [];
      this.overview = null;
      try {
        const [listRes, overviewRes] = await Promise.all([
          apiGet('/api/goals', store.token),
          apiGet('/api/goals/dashboard-overview', store.token),
        ]);
        if (listRes.ok) {
          this.goals = listRes.data?.data?.goals || listRes.data?.goals || [];
        } else {
          this.error = listRes.data?.message || 'We could not load your goals.';
          return;
        }
        if (overviewRes.ok) {
          this.overview = overviewRes.data?.data || overviewRes.data || null;
        }
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
.mts-bar { width: 100%; height: 6px; background: var(--horizon-200); border-radius: var(--radius-full); overflow: hidden; }
.mts-bar__fill { height: 6px; border-radius: var(--radius-full); transition: width 0.3s ease; }
.mts-bar__fill--spring { background: var(--spring-500); }
.mts-bar__fill--violet { background: var(--violet-500); }
.mts-bar__fill--raspberry { background: var(--raspberry-500); }

.mts-allow__head { display: flex; align-items: baseline; justify-content: space-between; gap: 12px; margin-bottom: 6px; }
.mts-allow__label { font-size: 14px; font-weight: 700; color: var(--horizon-500); }
.mts-allow__cap { font-size: 12px; color: var(--neutral-500); white-space: nowrap; }
.mts-allow__foot { display: flex; align-items: baseline; justify-content: space-between; gap: 12px; margin-top: 6px; }
.mts-allow__remain { font-size: 12px; font-weight: 700; }
.mts-allow__remain--spring { color: var(--spring-600); }
.mts-allow__remain--violet { color: var(--violet-500); }
.mts-allow__remain--raspberry { color: var(--raspberry-500); }
.mts-allow__used { font-size: 12px; color: var(--neutral-500); white-space: nowrap; }

.mg-goal { padding: 14px 0; border-bottom: 1px solid var(--light-gray); }
.mg-goal:first-child { padding-top: 4px; }
.mg-goal:last-child { border-bottom: 0; padding-bottom: 0; }
.mg-goal__head { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; }
.mg-goal__title-wrap { min-width: 0; flex: 1; }
.mg-goal__name { display: block; font-size: 15px; font-weight: 700; color: var(--horizon-500); }
.mg-goal__type { display: block; font-size: 12px; color: var(--neutral-500); margin-top: 2px; }
.mg-goal__status { flex-shrink: 0; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; padding: 2px 8px; border-radius: var(--radius-sm); }
.mg-goal__status--spring { color: var(--spring-600); background: color-mix(in srgb, var(--spring-500) 12%, var(--white)); }
.mg-goal__status--violet { color: var(--violet-500); background: color-mix(in srgb, var(--violet-500) 12%, var(--white)); }
.mg-goal__status--raspberry { color: var(--raspberry-500); background: color-mix(in srgb, var(--raspberry-500) 12%, var(--white)); }
.mg-goal__foot { display: flex; align-items: baseline; justify-content: space-between; gap: 12px; }
.mg-goal__amounts { font-size: 13px; font-weight: 700; color: var(--horizon-500); }
.mg-goal__remaining { font-size: 12px; color: var(--neutral-500); white-space: nowrap; }
</style>
