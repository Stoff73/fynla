import { reactive } from 'vue';
import { apiGet, apiPost } from './api.js';

const KEY = 'm_scaffold_token';

// The Fyn conversation the user is in during this session (M6, 2026-09-29).
// /m has no <keep-alive>, so the chat's component state dies on every route
// change; the id lives here so the dashboard chat and every screen's docked
// Fyn bar resume the same conversation instead of greeting afresh. Per session
// only (sessionStorage — survives a reload of this tab, never a new session),
// bound to the user it belongs to, and cleared on logout so another person
// signing in on the device never sees it.
const FYN_CONVERSATION_KEY = 'm_fyn_conversation';

function readFynConversation() {
  try {
    const raw = window.sessionStorage.getItem(FYN_CONVERSATION_KEY);
    const parsed = raw ? JSON.parse(raw) : null;
    return parsed && parsed.id ? parsed : null;
  } catch {
    return null;
  }
}

function writeFynConversation(value) {
  try {
    if (value) window.sessionStorage.setItem(FYN_CONVERSATION_KEY, JSON.stringify(value));
    else window.sessionStorage.removeItem(FYN_CONVERSATION_KEY);
  } catch {
    /* storage blocked (private mode) — the in-memory copy still serves this session */
  }
}

// The /api/auth/user payload has been read three ways over time; one reader.
export function userFromResponse(res) {
  return res?.data?.data?.user || res?.data?.user || res?.data?.data || null;
}

/**
 * True while Fyn's onboarding verify flow has sent the user to a module
 * screen to check a section. Module screens hide their planning cards for
 * that visit (risk profile, retirement projection: CSJ 2026-09-22) and
 * MobileChrome shows Continue/Edit instead of the edit-details button.
 */
export function inOnboardingVerify() {
  return String(store.user?.onboarding_fyn_step || '').startsWith('campaign_verify_');
}

export const store = reactive({
  token: localStorage.getItem(KEY) || null,
  user: null,
  subscriptionStatus: null,
  fynConversation: readFynConversation(),
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
    this.setFynConversation(null);
  },
  setFynConversation(id) {
    const value = id ? { id, userId: this.user?.id ?? null } : null;
    this.fynConversation = value;
    writeFynConversation(value);
  },
  // The conversation to resume, or null. A stored id that belongs to another
  // user is never handed back.
  currentFynConversationId() {
    const current = this.fynConversation;
    if (!current?.id) return null;
    const userId = this.user?.id ?? null;
    if (current.userId != null && userId != null && current.userId !== userId) return null;
    return current.id;
  },
  // Re-read the signed-in user from the server. The Fyn stream changes the
  // onboarding flags server-side (completion, the first step being assigned),
  // and a snapshot fetched at login would otherwise keep reporting the old
  // state for the rest of the session.
  async refreshUser() {
    const token = this.token;
    if (!token) return this.user;
    try {
      const res = await apiGet('/api/auth/user', token);
      const user = res?.ok ? userFromResponse(res) : null;
      if (user && this.token === token) this.user = user;
    } catch {
      /* non-fatal — the locally mirrored flags stand until the next fetch */
    }
    return this.user;
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
