<template>
  <MobileChrome
    ref="chrome"
    title="Your progress"
    subtitle="Achievements you've earned and milestones you've reached"
    :edit-details="false"
    :loading="loading"
    loading-label="your progress"
    back
    @back="goBack"
  >
    <!-- Tabs -->
    <div class="m-card">
      <div class="ma-tabs" role="tablist" aria-label="Progress sections">
        <button
          type="button"
          class="ma-tab"
          :class="{ 'is-active': tab === 'achievements' }"
          role="tab"
          :aria-selected="tab === 'achievements' ? 'true' : 'false'"
          @click="tab = 'achievements'"
        >Achievements</button>
        <button
          type="button"
          class="ma-tab"
          :class="{ 'is-active': tab === 'milestones' }"
          role="tab"
          :aria-selected="tab === 'milestones' ? 'true' : 'false'"
          data-progress-tab="milestones"
          @click="tab = 'milestones'"
        >Milestones</button>
        <button
          type="button"
          class="ma-tab"
          :class="{ 'is-active': tab === 'history' }"
          role="tab"
          :aria-selected="tab === 'history' ? 'true' : 'false'"
          @click="tab = 'history'"
        >History</button>
      </div>
    </div>

    <div v-if="error" class="m-card m-state">
      <p class="m-err">{{ error }}</p>
      <button class="m-btn" @click="load">Try again</button>
    </div>

    <template v-else>
      <!-- Achievements tab. WP-4: the old "Next" section (the dashboard's
           top-4 actions repeated) is gone — actions live on the dashboard;
           this page is what the user has DONE and earned. -->
      <template v-if="tab === 'achievements'">
        <!-- Done — completed actions (WP-2: completed work was saved in
             recommendation_tracking but shown nowhere). Newest first. -->
        <div v-if="completed.length" class="m-card">
          <p class="m-section-label" style="margin-top:0">Done</p>
          <div class="ma-badges">
            <div v-for="item in completed" :key="item.id" class="ma-badge is-earned">
              <span class="ma-badge__title">{{ item.title }}</span>
              <span class="ma-badge__status">{{ completedLabel(item) }}</span>
            </div>
          </div>
          <!-- WP-5c-ii — done work is never truncated; load the rest on demand. -->
          <button
            v-if="completed.length < completedTotal"
            class="m-btn ma-more"
            :disabled="loadingMoreCompleted"
            @click="loadMoreCompleted"
          >{{ loadingMoreCompleted ? 'Loading…' : 'Show more' }}</button>
        </div>

        <!-- Earned — badges; earned ones prominent, unearned muted. -->
        <div class="m-card">
          <p class="m-section-label" style="margin-top:0">Earned</p>
          <p v-if="!achievements.length" class="m-sub" style="margin-bottom:0">
            No achievements yet — keep building your plan to earn your first.
          </p>
          <div v-else class="ma-badges">
            <div
              v-for="badge in achievements"
              :key="badge.key"
              class="ma-badge"
              :class="{ 'is-earned': badge.state === 'earned' }"
              data-achievement-item
            >
              <span class="ma-badge__emblem" aria-hidden="true">Badge</span>
              <span class="ma-badge__title">{{ badge.title }}</span>
              <span class="ma-badge__desc">{{ badge.description }}</span>
              <span class="ma-badge__status">{{ achievementStateLabel(badge) }}</span>
              <span v-if="badge.state === 'earned' && provenanceLabel(badge)" class="ma-badge__provenance">{{ provenanceLabel(badge) }}</span>
            </div>
          </div>
        </div>
      </template>

      <!-- Milestones tab -->
      <template v-else-if="tab === 'milestones'">
        <!-- Next up — the next milestone from every family that applies,
             grouped, each with the step that gets there (WP-5b + WP-5c-ii). -->
        <div v-if="upcoming.length" class="m-card">
          <p class="m-section-label" style="margin-top:0">Next up</p>
          <template v-for="(section, sectionIndex) in groupedUpcoming" :key="'grp-' + section.group">
            <p class="ma-group">{{ section.group }}</p>
            <div class="ma-badges">
              <div
                v-for="(up, i) in section.items"
                :key="section.group + '-' + i"
                class="ma-badge"
                data-achievement-item
              >
                <span :id="`upcoming-title-${sectionIndex}-${i}`" class="ma-badge__title">{{ up.title }}</span>
                <span class="ma-badge__desc">{{ up.steps }}</span>
                <span class="ma-badge__status">{{ achievementStateLabel(up) }}</span>
                <span v-if="showsProgress(up)" class="ma-progress">
                  <span class="ma-progress__label">{{ up.progress.label }}</span>
                  <span
                    class="ma-progress__track"
                    role="progressbar"
                    :aria-labelledby="`upcoming-title-${sectionIndex}-${i}`"
                    :aria-valuemin="0"
                    :aria-valuemax="100"
                    :aria-valuenow="up.progress.percent"
                    :aria-valuetext="up.progress.label"
                  ><span class="ma-progress__fill" :style="{ width: `${up.progress.percent}%` }"></span></span>
                </span>
                <button
                  v-if="upcomingActionLabel(up)"
                  type="button"
                  class="ma-badge__action ma-badge__action--button"
                  @click="goToUpcoming(up)"
                >{{ upcomingActionLabel(up) }}</button>
              </div>
            </div>
          </template>
        </div>

        <div class="m-card">
          <p class="m-section-label" style="margin-top:0">Reached</p>
          <p v-if="!milestones.length" class="m-sub" style="margin-bottom:0">
            No milestones reached yet — keep building your plan.
          </p>
          <div v-else class="ma-badges">
            <div
              v-for="ms in milestones"
              :key="ms.key"
              class="ma-badge"
              :class="{ 'is-earned': ms.state === 'earned' }"
              data-achievement-item
              data-reached-milestone
            >
              <span class="ma-badge__emblem" aria-hidden="true">Badge</span>
              <span class="ma-badge__title">{{ ms.title }}</span>
              <span class="ma-badge__status">{{ achievementStateLabel(ms) }}</span>
              <span v-if="ms.state === 'earned' && provenanceLabel(ms)" class="ma-badge__provenance">{{ provenanceLabel(ms) }}</span>
            </div>
          </div>
          <button
            v-if="milestoneCursor && !milestoneLoadError"
            type="button"
            class="m-btn ma-more"
            :disabled="loadingMoreMilestones"
            data-load-more-milestones
            @click="loadMoreMilestones"
          >{{ loadingMoreMilestones ? 'Loading…' : 'Load more reached milestones' }}</button>
          <div v-if="milestoneLoadError" class="m-state ma-load-error" aria-live="polite" data-milestone-load-error>
            <p class="m-err">{{ milestoneLoadError }}</p>
            <button type="button" class="m-btn" data-retry-milestones @click="loadMoreMilestones">Try again</button>
          </div>
        </div>
      </template>

      <!-- History tab — everything you've done, newest first (WP-3). -->
      <template v-else>
        <div class="m-card">
          <p class="m-section-label" style="margin-top:0">Your activity</p>
          <p v-if="!activity.length" class="m-sub" style="margin-bottom:0">
            Nothing here yet — your answers, records, and completed actions will show up as you go.
          </p>
          <div v-else class="ma-history">
            <div v-for="(ev, i) in activity" :key="i" class="ma-history__row">
              <span class="ma-history__label">{{ ev.label }}</span>
              <span class="ma-history__date">{{ formatDate(ev.occurred_at) }}</span>
            </div>
          </div>
          <!-- WP-5c-ii — infinite scroll: the sentinel loads the next page of
               the ledger as it comes into view; cursor null = fully loaded. -->
          <div v-if="activityCursor" ref="historySentinel" class="ma-sentinel">
            <span v-if="loadingMoreActivity" class="m-sub">Loading more…</span>
          </div>
        </div>
      </template>
    </template>
  </MobileChrome>
</template>

<script>
import { store } from '../store.js';
import { apiGet } from '../api.js';
import { handleAuthExpiry } from '../authExpiry.js';
import { resolveMobileDestination } from '../navigation/semanticDestinations.js';
import MobileChrome from '../components/MobileChrome.vue';

const legacyMilestoneActions = Object.freeze({
  dashboard: 'Go to dashboard',
  'm-net-worth': 'Review net worth',
  'm-goals': 'Review goals',
  'm-estate': 'Review estate plan',
  'm-savings': 'Review savings',
  'm-retirement': 'Review retirement',
  'm-protection': 'Review protection',
  'tax-strategy': 'Review tax strategy',
});

function validMilestoneCursor(cursor) {
  return cursor === null || (typeof cursor === 'string' && cursor.length > 0);
}

export default {
  name: 'MobileAchievements',
  components: { MobileChrome },
  data() {
    return {
      tab: 'achievements',
      loading: true,
      error: '',
      achievements: [],
      completed: [],
      completedTotal: 0,
      completedPage: 1,
      loadingMoreCompleted: false,
      milestones: [],
      milestoneCursor: null,
      loadingMoreMilestones: false,
      milestoneLoadError: '',
      upcoming: [],
      activity: [],
      activityCursor: null,
      loadingMoreActivity: false,
      historyObserver: null,
    };
  },
  computed: {
    // WP-5c-ii — group the upcoming list by its group field, preserving the
    // backend's order (Wealth, Goals, Tax year, Savings, Retirement,
    // Protection & estate, Property, Journey).
    groupedUpcoming() {
      const sections = [];
      for (const up of this.upcoming) {
        const group = up.group || 'Milestones';
        const last = sections[sections.length - 1];
        if (last && last.group === group) {
          last.items.push(up);
        } else {
          sections.push({ group, items: [up] });
        }
      }
      return sections;
    },
  },
  watch: {
    // The history sentinel only exists while its tab is active — re-arm the
    // observer on each switch in.
    tab(next) {
      if (next === 'history') {
        this.$nextTick(() => this.armHistoryObserver());
      } else {
        this.disarmHistoryObserver();
      }
    },
  },
  async created() {
    await this.load();
  },
  beforeUnmount() {
    this.disarmHistoryObserver();
  },
  methods: {
    goBack() {
      this.$router.push({ name: 'dashboard' });
    },
    // WP-5c-iii — an upcoming milestone deep-links to where the user acts.
    goToUpcoming(up) {
      if (!up) return;
      const destination = up.next_action?.label ? resolveMobileDestination(up.next_action) : null;
      if (destination) {
        const navigation = this.$router.push(destination);
        if (navigation?.catch) navigation.catch(() => {});
      } else if (this.upcomingActionLabel(up) && this.legacyMilestoneRoute(up.route)) {
        const navigation = this.$router.push({ name: up.route });
        if (navigation?.catch) navigation.catch(() => {});
      }
    },
    legacyMilestoneRoute(route) {
      return Object.hasOwn(legacyMilestoneActions, route);
    },
    upcomingActionLabel(item) {
      if (typeof item?.next_action?.label === 'string' && item.next_action.label.trim()) return item.next_action.label;
      return this.legacyMilestoneRoute(item?.route) ? legacyMilestoneActions[item.route] : '';
    },
    formatDate(iso) {
      if (!iso) return '';
      const d = new Date(iso);
      if (isNaN(d.getTime())) return '';
      return d.toLocaleDateString('en-GB');
    },
    earnedLabel(badge) {
      const date = this.formatDate(badge.earned_at);
      return date ? `Earned ${date}` : 'Earned';
    },
    achievementStateLabel(item) {
      if (item?.state === 'earned') return 'Earned';
      if (item?.state === 'in_progress') return 'In progress';
      if (item?.state === 'inapplicable') return 'Not applicable';
      return 'Locked';
    },
    provenanceLabel(item) {
      const provenance = item?.provenance;
      if (!provenance?.occurred_at) return '';
      const date = this.formatDate(provenance.occurred_at);
      return date ? `Earned on ${date}` : 'Earned';
    },
    showsProgress(item) {
      return item?.state === 'in_progress' && item.progress !== null && typeof item.progress === 'object';
    },
    achievedLabel(ms) {
      const date = this.formatDate(ms.achieved_at);
      return date ? `Reached ${date}` : 'Reached';
    },
    completedLabel(item) {
      const date = this.formatDate(item.completed_at);
      return date ? `Done ${date}` : 'Done';
    },
    async load() {
      this.loading = true;
      this.error = '';
      try {
        const [res, act] = await Promise.all([
          apiGet('/api/v1/mobile/achievements/v2', store.token),
          apiGet('/api/gamification/activity', store.token),
        ]);
        if (handleAuthExpiry(res, this.$router)) return;
        if (!res.ok) {
          this.error = 'We could not load your progress. Please try again.';
          return;
        }
        const d = res.data?.data || {};
        this.achievements = Array.isArray(d.achievements) ? d.achievements : [];
        this.completed = Array.isArray(d.completed) ? d.completed : [];
        this.completedTotal = Number(d.completed_total) || this.completed.length;
        this.completedPage = 1;
        this.milestones = this.mergeMilestones([], d.milestones);
        this.milestoneCursor = validMilestoneCursor(d.next_cursor) ? d.next_cursor : null;
        this.upcoming = Array.isArray(d.upcoming) ? d.upcoming : [];
        this.activity = act.ok && Array.isArray(act.data?.data) ? act.data.data : [];
        this.activityCursor = act.ok ? (act.data?.next_cursor ?? null) : null;
      } catch {
        this.error = 'Network error. Please try again.';
      } finally {
        this.loading = false;
      }
    },
    async loadMoreMilestones() {
      if (this.loadingMoreMilestones || !this.milestoneCursor) return;
      this.loadingMoreMilestones = true;
      this.milestoneLoadError = '';
      try {
        const cursor = this.milestoneCursor;
        const res = await apiGet(`/api/v1/mobile/achievements/v2/milestones?cursor=${encodeURIComponent(cursor)}`, store.token);
        if (handleAuthExpiry(res, this.$router)) return;
        const data = res.data?.data;
        if (!res.ok || !Array.isArray(data?.milestones) || !Object.hasOwn(data, 'next_cursor') || !validMilestoneCursor(data.next_cursor)) throw new Error('continuation_failed');
        this.milestones = this.mergeMilestones(this.milestones, data.milestones);
        this.milestoneCursor = data.next_cursor;
      } catch {
        this.milestoneLoadError = 'Could not load more reached milestones. Please try again.';
      } finally {
        this.loadingMoreMilestones = false;
      }
    },
    mergeMilestones(existing, incoming) {
      const merged = [];
      const keys = new Set();
      for (const milestone of [...(Array.isArray(existing) ? existing : []), ...(Array.isArray(incoming) ? incoming : [])]) {
        if (typeof milestone?.key !== 'string' || keys.has(milestone.key)) continue;
        keys.add(milestone.key);
        merged.push(milestone);
      }
      return merged;
    },
    // WP-5c-ii — next page of completed actions, appended.
    async loadMoreCompleted() {
      if (this.loadingMoreCompleted) return;
      this.loadingMoreCompleted = true;
      try {
        const next = this.completedPage + 1;
        const res = await apiGet(`/api/v1/mobile/achievements/completed?page=${next}`, store.token);
        const d = res.ok ? (res.data?.data || {}) : {};
        if (Array.isArray(d.completed) && d.completed.length) {
          this.completed = this.completed.concat(d.completed);
          this.completedPage = next;
          this.completedTotal = Number(d.completed_total) || this.completedTotal;
        }
      } finally {
        this.loadingMoreCompleted = false;
      }
    },
    // WP-5c-ii — next page of the activity ledger (cursor on the last row).
    async loadMoreActivity() {
      if (this.loadingMoreActivity || !this.activityCursor) return;
      this.loadingMoreActivity = true;
      try {
        const res = await apiGet(`/api/gamification/activity?before=${this.activityCursor}`, store.token);
        if (res.ok) {
          const events = Array.isArray(res.data?.data) ? res.data.data : [];
          this.activity = this.activity.concat(events);
          this.activityCursor = res.data?.next_cursor ?? null;
        }
      } finally {
        this.loadingMoreActivity = false;
      }
    },
    armHistoryObserver() {
      this.disarmHistoryObserver();
      const sentinel = this.$refs.historySentinel;
      if (!sentinel || typeof IntersectionObserver === 'undefined') return;
      this.historyObserver = new IntersectionObserver((entries) => {
        if (entries.some((e) => e.isIntersecting)) {
          this.loadMoreActivity();
        }
      }, { rootMargin: '200px' });
      this.historyObserver.observe(sentinel);
    },
    disarmHistoryObserver() {
      if (this.historyObserver) {
        this.historyObserver.disconnect();
        this.historyObserver = null;
      }
    },
  },
};
</script>

<style scoped>
/* Tabs */
.ma-tabs { display: flex; gap: 8px; margin-top: 16px; }
.ma-tab {
  flex: 1 1 auto;
  background: var(--horizon-100);
  border: 1px solid var(--horizon-200);
  border-radius: var(--radius-md);
  padding: 10px 12px;
  font-size: 14px;
  font-weight: 700;
  color: var(--neutral-500);
  cursor: pointer;
}
.ma-tab.is-active {
  background: var(--horizon-500);
  border-color: var(--horizon-500);
  color: var(--white);
}
.ma-tab:active { opacity: 0.8; }

/* Badge cards — earned prominent, unearned muted */
.ma-badges { display: flex; flex-direction: column; gap: 10px; }
.ma-badge {
  display: flex;
  flex-direction: column;
  gap: 4px;
  padding: 14px;
  border: 1px solid var(--horizon-200);
  border-radius: var(--radius-md);
  background: var(--savannah-100);
  opacity: 0.55;
}
.ma-badge.is-earned {
  opacity: 1;
  border-color: var(--spring-500);
  background: color-mix(in srgb, var(--spring-500) 8%, var(--white));
}
.ma-badge__title { font-size: 15px; font-weight: 700; color: var(--horizon-500); }
.ma-badge__desc { font-size: 13px; color: var(--neutral-500); line-height: 1.4; }
.ma-badge__status { font-size: 12px; font-weight: 700; color: var(--neutral-500); }
.ma-badge.is-earned .ma-badge__status { color: var(--spring-600); }
.ma-badge__emblem {
  align-self: flex-start;
  padding: 3px 7px;
  border-radius: 999px;
  background: var(--horizon-100);
  color: var(--horizon-500);
  font-size: 11px;
  font-weight: 800;
  letter-spacing: 0.04em;
  text-transform: uppercase;
}
.ma-badge.is-earned .ma-badge__emblem { background: var(--spring-500); color: var(--white); }
.ma-badge__provenance { font-size: 12px; color: var(--neutral-500); }
.ma-badge__action { font-size: 13px; font-weight: 700; color: var(--horizon-500); }
.ma-badge__action--button {
  align-self: flex-start;
  border: 0;
  min-width: 44px;
  min-height: 44px;
  padding: 10px 12px;
  background: transparent;
  font-family: inherit;
  cursor: pointer;
  text-decoration: underline;
}
.ma-badge__action--button:focus-visible { outline: 3px solid var(--horizon-500); outline-offset: 2px; }
.ma-progress { display: flex; flex-direction: column; gap: 5px; }
.ma-progress__label { font-size: 12px; color: var(--neutral-500); }
.ma-progress__track { display: block; overflow: hidden; height: 7px; border-radius: 999px; background: var(--horizon-100); }
.ma-progress__fill { display: block; height: 100%; background: var(--horizon-500); }

/* WP-5c-iii — upcoming steps are tappable deep-links. */
.ma-badge--link {
  display: block;
  width: 100%;
  text-align: left;
  font-family: inherit;
  cursor: pointer;
  -webkit-appearance: none;
  appearance: none;
}

/* WP-5c-ii — grouped Next up, load-more, history sentinel. */
.ma-group {
  margin: 14px 0 6px;
  font-size: 12px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: var(--neutral-500);
}
.ma-group:first-of-type { margin-top: 8px; }
.ma-more { width: 100%; margin-top: 12px; }
.ma-sentinel { min-height: 24px; text-align: center; padding: 6px 0; }
.ma-load-error { margin-top: 12px; }

/* WP-3 — activity history rows. */
.ma-history { display: flex; flex-direction: column; }
.ma-history__row {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: 12px;
  padding: 10px 0;
  border-bottom: 1px solid var(--horizon-100);
}
.ma-history__row:last-child { border-bottom: 0; padding-bottom: 0; }
.ma-history__label { font-size: 14px; font-weight: 600; color: var(--horizon-500); line-height: 1.4; }
.ma-history__date { font-size: 12px; color: var(--neutral-500); white-space: nowrap; }
</style>
