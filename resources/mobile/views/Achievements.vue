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
        <!-- Next up — milestones the user can achieve, with the step that
             gets them there (WP-5b). -->
        <div v-if="upcoming.length" class="m-card">
          <p class="m-section-label" style="margin-top:0">Next up</p>
          <div class="ma-badges">
            <div v-for="(up, i) in upcoming" :key="'up-' + i" class="ma-badge">
              <span class="ma-badge__title">{{ up.title }}</span>
              <span class="ma-badge__desc">{{ up.steps }}</span>
            </div>
          </div>
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
      milestones: [],
      upcoming: [],
      activity: [],
    };
  },
  async created() {
    await this.load();
  },
  methods: {
    goBack() {
      this.$router.push({ name: 'dashboard' });
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
        this.milestones = Array.isArray(d.milestones) ? d.milestones : [];
        this.upcoming = Array.isArray(d.upcoming) ? d.upcoming : [];
        this.activity = act.ok && Array.isArray(act.data?.data) ? act.data.data : [];
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
