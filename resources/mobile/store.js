import { reactive } from 'vue';
import { apiGet, apiPost } from './api.js';

const KEY = 'm_scaffold_token';

export const store = reactive({
  token: localStorage.getItem(KEY) || null,
  user: null,
  subscriptionStatus: null,
  // Gamification (shared engine — GET /api/gamification/status). pendingCelebration
  // is the level-up to celebrate; set from a level_up SSE frame mid-chat or from a
  // missed-celebration delivered by fetchStatus on next app open.
  gamification: {
    level: 1,
    levelName: 'Starter',
    progressPercent: 0,
    nextLevelName: null,
    nextActions: [],
  },
  pendingCelebration: null, // { level, level_name, next_actions } | null
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
  // Pull the latest gamification status. A pending_celebration from a missed
  // level-up is surfaced here so it delivers on next open.
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
      if (d.pending_celebration) {
        this.pendingCelebration = {
          level: d.pending_celebration.level,
          level_name: d.pending_celebration.level_name,
          next_actions: d.pending_celebration.next_actions || [],
        };
      }
    } catch {
      /* non-fatal — gamification must never break the dashboard */
    }
  },
  // Queue a celebration from an in-turn level_up SSE frame.
  queueCelebration(frame) {
    if (!frame) return;
    this.pendingCelebration = {
      level: frame.level,
      level_name: frame.level_name,
      next_actions: frame.next_actions || [],
    };
  },
  // Dismiss + clear the server-side pending flag so it isn't redelivered.
  async ack() {
    this.pendingCelebration = null;
    try {
      if (this.token) await apiPost('/api/gamification/celebration/ack', {}, this.token);
    } catch {
      /* non-fatal */
    }
  },
});
