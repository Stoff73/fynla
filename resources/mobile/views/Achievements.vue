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
              :class="{ 'is-earned': badge.earned }"
            >
              <span class="ma-badge__title">{{ badge.title }}</span>
              <span class="ma-badge__desc">{{ badge.description }}</span>
              <span class="ma-badge__status">{{ badge.earned ? earnedLabel(badge) : 'Not yet earned' }}</span>
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
          <template v-for="section in groupedUpcoming" :key="'grp-' + section.group">
            <p class="ma-group">{{ section.group }}</p>
            <div class="ma-badges">
              <!-- WP-5c-iii — each step deep-links to the surface where the
                   user acts on it. -->
              <button
                v-for="(up, i) in section.items"
                :key="section.group + '-' + i"
                type="button"
                class="ma-badge ma-badge--link"
                @click="goToUpcoming(up)"
              >
                <span class="ma-badge__title">{{ up.title }}</span>
                <span class="ma-badge__desc">{{ up.steps }}</span>
              </button>
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
              :class="{ 'is-earned': ms.achieved }"
            >
              <span class="ma-badge__title">{{ ms.title }}</span>
              <span class="ma-badge__status">{{ ms.achieved ? achievedLabel(ms) : 'Not yet reached' }}</span>
            </div>
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
import MobileChrome from '../components/MobileChrome.vue';

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
      if (up && up.route) {
        this.$router.push({ name: up.route }).catch(() => {});
      }
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
          apiGet('/api/v1/mobile/achievements', store.token),
          apiGet('/api/gamification/activity', store.token),
        ]);
        if (!res.ok) {
          this.error = 'We could not load your progress. Please try again.';
          return;
        }
        const d = res.data?.data || {};
        this.achievements = Array.isArray(d.achievements) ? d.achievements : [];
        this.completed = Array.isArray(d.completed) ? d.completed : [];
        this.completedTotal = Number(d.completed_total) || this.completed.length;
        this.completedPage = 1;
        this.milestones = Array.isArray(d.milestones) ? d.milestones : [];
        this.upcoming = Array.isArray(d.upcoming) ? d.upcoming : [];
        this.activity = act.ok && Array.isArray(act.data?.data) ? act.data.data : [];
        this.activityCursor = act.ok ? (act.data?.next_cursor ?? null) : null;
      } catch (e) {
        this.error = 'Network error. Please try again.';
      } finally {
        this.loading = false;
      }
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
