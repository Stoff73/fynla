<template>
  <div>
    <!-- Header — mirrors the module views (m-detail-header + m-back + m-h1/m-sub).
         The phone-frame chrome itself is provided by App.vue via the
         #m-app:has(.m-card) centred column, so this view is never chrome-less. -->
    <div class="m-card m-detail-header">
      <button class="m-back" @click="goBack" aria-label="Back to dashboard">Back</button>
      <h1 class="m-h1">Your progress</h1>
      <p class="m-sub">Achievements you've earned and milestones you've reached</p>

      <!-- Tabs -->
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
      </div>
    </div>

    <div v-if="loading" class="m-card m-state">
      <p class="m-sub" style="margin-bottom:0">Loading your progress…</p>
    </div>

    <div v-else-if="error" class="m-card m-state">
      <p class="m-err">{{ error }}</p>
      <button class="m-btn" @click="load">Try again</button>
    </div>

    <template v-else>
      <!-- Achievements tab -->
      <template v-if="tab === 'achievements'">
        <!-- Next — the up-to-four next actions to take. -->
        <div class="m-card">
          <p class="m-section-label" style="margin-top:0">Next</p>
          <p v-if="!next.length" class="m-sub" style="margin-bottom:0">
            You're all caught up — nothing to action right now.
          </p>
          <div v-else>
            <div
              v-for="item in next"
              :key="item.id"
              class="ma-next"
            >
              <div class="ma-next__main">
                <span class="ma-next__title">{{ item.title }}</span>
                <span v-if="item.meta" class="ma-next__meta">{{ item.meta }}</span>
              </div>
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
      <template v-else>
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
    </template>
  </div>
</template>

<script>
import { store } from '../store.js';
import { apiGet } from '../api.js';

export default {
  name: 'MobileAchievements',
  data() {
    return {
      tab: 'achievements',
      loading: true,
      error: '',
      achievements: [],
      next: [],
      milestones: [],
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
    async load() {
      this.loading = true;
      this.error = '';
      try {
        const res = await apiGet('/api/v1/mobile/achievements', store.token);
        if (!res.ok) {
          this.error = 'We could not load your progress. Please try again.';
          return;
        }
        const d = res.data?.data || {};
        this.achievements = Array.isArray(d.achievements) ? d.achievements : [];
        this.next = Array.isArray(d.next) ? d.next : [];
        this.milestones = Array.isArray(d.milestones) ? d.milestones : [];
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

/* Next-action rows */
.ma-next { padding: 12px 0; border-bottom: 1px solid var(--light-gray); }
.ma-next:first-child { padding-top: 4px; }
.ma-next:last-child { border-bottom: 0; padding-bottom: 0; }
.ma-next__main { display: flex; flex-direction: column; gap: 3px; }
.ma-next__title { font-size: 15px; font-weight: 700; color: var(--horizon-500); }
.ma-next__meta { font-size: 13px; color: var(--neutral-500); }

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
</style>
