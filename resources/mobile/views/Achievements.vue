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
      <!-- Achievements tab -->
      <template v-if="tab === 'achievements'">
        <!-- Next — the up-to-four next actions to take. Tapping any one opens Fyn
             to collect the information that action needs. -->
        <div class="m-card">
          <p class="m-section-label" style="margin-top:0">Next</p>
          <p v-if="!next.length" class="m-sub" style="margin-bottom:0">
            You're all caught up — nothing to action right now.
          </p>
          <div v-else>
            <button
              v-for="item in next"
              :key="item.id"
              type="button"
              class="ma-next"
              :class="{ 'is-unlock': item.type === 'unlock' }"
              @click="openNext(item)"
            >
              <span class="ma-next__lead" aria-hidden="true" v-html="item.type === 'unlock' ? KEY_ICON : BULB_ICON"></span>
              <div class="ma-next__main">
                <span class="ma-next__title">{{ item.title }}</span>
                <span v-if="item.meta" class="ma-next__meta">{{ item.meta }}</span>
              </div>
              <svg class="ma-next__chevron" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24" width="18" height="18"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
            </button>
          </div>
        </div>

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
        <div class="m-card">
          <p class="m-section-label" style="margin-top:0">Financial milestones</p>
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

// Leading glyphs distinguishing the two action types — grey key for locked
// "unlock" prompts, coloured bulb for actionable recommendations.
const KEY_ICON = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path stroke-linecap="round" stroke-linejoin="round" d="M15 7a2 2 0 012 2m4-2a6 6 0 01-7.743 5.743L11 14H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>';
const BULB_ICON = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path stroke-linecap="round" stroke-linejoin="round" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>';

export default {
  name: 'MobileAchievements',
  components: { MobileChrome },
  data() {
    return {
      KEY_ICON,
      BULB_ICON,
      tab: 'achievements',
      loading: true,
      error: '',
      achievements: [],
      next: [],
      completed: [],
      milestones: [],
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
    // Tapping a next-action opens Fyn to collect the information that action
    // needs. Unlock / data-capture actions ask Fyn to gather the module details;
    // a real recommendation asks Fyn how to action it.
    openNext(item) {
      const chrome = this.$refs.chrome;
      if (!chrome || !item) return;
      const capture = {
        protection: 'Help me add my protection cover details',
        savings: 'Help me add my savings details',
        investment: 'Help me add my investment details',
        retirement: 'Help me add my pension details',
        estate: 'Help me add my estate planning details',
        goals: 'Help me set a financial goal',
      };
      const kind = item.action && item.action.kind;
      if (kind === 'fyn_capture' || item.type === 'unlock') {
        chrome.openFynWith(capture[item.module] || 'Help me add my financial details');
      } else {
        chrome.openFynWith(`How do I "${item.title}"?`);
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
        this.next = Array.isArray(d.next) ? d.next : [];
        this.completed = Array.isArray(d.completed) ? d.completed : [];
        this.milestones = Array.isArray(d.milestones) ? d.milestones : [];
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

/* Next-action rows — tappable eggshell cards (open Fyn). Hover lifts to white
   with a light-pink border, matching the dashboard recommendation rows. */
.ma-next {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  width: 100%;
  text-align: left;
  cursor: pointer;
  background: var(--eggshell-500);
  border: 1px solid transparent;
  border-radius: 12px;
  padding: 12px 14px;
  margin-bottom: 8px;
  transition: background 0.15s ease, border-color 0.15s ease;
}
.ma-next:last-child { margin-bottom: 0; }
.ma-next:hover { background: var(--white); border-color: var(--light-pink-200); }
/* Unlock prompts read differently: light-grey dotted border + grey key glyph. */
.ma-next.is-unlock { border: 1px dotted var(--horizon-300); }
.ma-next.is-unlock:hover { background: var(--white); border-color: var(--horizon-400); }
.ma-next__lead { flex-shrink: 0; display: flex; align-items: center; color: var(--raspberry-500); }
.ma-next__lead svg { display: block; }
.ma-next.is-unlock .ma-next__lead { color: var(--neutral-400); }
.ma-next__main { display: flex; flex-direction: column; gap: 3px; min-width: 0; flex: 1; }
.ma-next__title { font-size: 15px; font-weight: 700; color: var(--horizon-500); }
.ma-next__meta { font-size: 13px; color: var(--neutral-500); }
.ma-next__chevron { color: var(--raspberry-500); flex-shrink: 0; }

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
