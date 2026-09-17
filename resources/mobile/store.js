import { reactive } from 'vue';
import { apiGet, apiPost } from './api.js';

const KEY = 'm_scaffold_token';

export const store = reactive({
  token: localStorage.getItem(KEY) || null,
  user: null,
  subscriptionStatus: null,
  // Gamification (shared engine — GET /api/gamification/status). The banked
  // climb is a RANGE: every level above celebrateFrom, up to celebrateTo, is
  // owed and gets spent on the dashboard hero circle. The full-screen
  // celebration this replaced could hold only one level (CSJ 2026-09-17).
  gamification: {
    level: 1,
    levelName: 'Starter',
    progressPercent: 0,
    nextLevelName: null,
    nextActions: [],
    celebrateFrom: 1,
    celebrateTo: 1,
  },
  // Same-route verify refresh: bumped when the onboarding chat applies an
  // edit and re-verifies the screen the user is already on — the module
  // screens watch this and refetch, since no remount happens without a
  // route change.
  screenRefreshTick: 0,
  bumpScreenRefresh() {
    this.screenRefreshTick += 1;
  },
  // Bug-report sheet context. Opened from the floating FAB (no conversation) or
  // from the Fyn chat header (carries the active conversationId so the report
  // captures the transcript). Shared here because the sheet lives in App.vue
  // while the chat lives in Dashboard.vue.
  bugReport: { open: false, conversationId: null },
  openBugReport(conversationId = null) {
    this.bugReport.conversationId = conversationId ?? null;
    this.bugReport.open = true;
  },
  closeBugReport() {
    this.bugReport.open = false;
    this.bugReport.conversationId = null;
  },
  setToken(t) {
    if (this.token !== t) this.subscriptionStatus = null;
    this.token = t;
    if (t) localStorage.setItem(KEY, t);
    else localStorage.removeItem(KEY);
  },
  logout() {
    this.setToken(null);
    this.user = null;
    this.subscriptionStatus = null;
  },
  // Pull the latest gamification status. A climb banked while the user was
  // elsewhere is surfaced here so it delivers on next open.
  async fetchStatus() {
    if (!this.token) return;
    try {
      const res = await apiGet('/api/gamification/status', this.token);
      if (!res.ok) return;
      const d = res.data?.data || res.data || {};
      this.gamification.level = d.level ?? 1;
      this.gamification.levelName = d.level_name ?? 'Starter';
      this.gamification.progressPercent = d.progress_percent ?? 0;
      this.gamification.nextLevelName = d.next_level_name ?? null;
      this.gamification.nextActions = d.next_actions || [];
      this.gamification.celebrateFrom = d.celebrate_from ?? d.level ?? 1;
      this.gamification.celebrateTo = d.celebrate_to ?? d.level ?? 1;
    } catch {
      /* non-fatal — gamification must never break the dashboard */
    }
  },
  // Bank the climb the dashboard has just shown so it never replays.
  async ackCelebration(level) {
    this.gamification.celebrateFrom = level;
    this.gamification.celebrateTo = Math.max(level, this.gamification.celebrateTo);
    try {
      if (this.token) await apiPost('/api/gamification/celebration/ack', { level }, this.token);
    } catch {
      /* non-fatal */
    }
  },
});
