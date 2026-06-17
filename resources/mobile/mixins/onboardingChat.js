// Shared Fyn onboarding-chat client for the /m surface.
//
// The /m app has NO <keep-alive> (App.vue uses a plain <router-view/>), so the
// SaveTax campaign onboarding chat cannot live in a single screen: navigating
// from the dashboard to a section's verify screen (e.g. /income) unmounts the
// dashboard and destroys the in-progress chat + the waiting Gate-2 bubble. So
// both the dashboard's first-run chat AND the docked Fyn bar on every module
// screen (MobileChrome) must speak the same onboarding protocol. This mixin is
// that one client — the SSE event router, the bubble click handler, the send /
// stream loop, the conversation bookkeeping, and the cross-screen resume — so
// the dock can pick up exactly where the dashboard left off, and vice versa.
//
// Components keep their own first-run entry point (`initFyn`) and any surface-
// specific affordances (the dashboard's level wheel / `pulseWheel`); everything
// shared lives here. `pulseWheel` is a no-op by default and overridden by the
// dashboard, so the level-up frame is safe to handle from either surface.
import { apiPost, apiStream } from '../api.js';
import { store } from '../store.js';

// The routes the onboarding chat may navigate the /m surface to: the per-section
// verify destinations (campaign_verify_navigate → the section's screen) plus the
// terminal turn (campaign → /tax-strategy). Anything outside this set is a
// desktop-only route with no /m screen and is ignored (the chat thread still
// carries the result). Keep in step with OnboardingStateMachine::campaignVerifyConfig().
export const ONBOARDING_NAV_ROUTES = [
  '/tax-strategy', '/income', '/expenditure', '/savings', '/investment', '/retirement',
];

export default {
  data() {
    return {
      conversationId: null,
      resumeId: null,
      messages: [],
      draft: '',
      sending: false,
      fynStarted: false,
    };
  },
  computed: {
    // Onboarding is "active" only when explicitly not completed (null/undefined
    // — e.g. the user is not loaded yet — must NOT trigger the onboarding chat).
    onboardingActive() {
      return store.user?.onboarding_completed === false;
    },
  },
  methods: {
    // Brief level-wheel pulse on a level-up. No-op here; the dashboard overrides
    // it with the real wheel animation. The full fireworks takeover is driven
    // separately by the shared GamificationCelebration via store.pendingCelebration.
    pulseWheel() {},

    scrollFyn() {
      const b = this.$refs.fynBody;
      if (b) b.scrollTop = b.scrollHeight;
    },

    async ensureConversation() {
      if (this.conversationId) return this.conversationId;
      const res = await apiPost('/api/ai-chat/conversations', {}, store.token);
      this.conversationId = res.data?.data?.id ?? res.data?.id ?? res.data?.conversation?.id ?? null;
      return this.conversationId;
    },

    // First turn of the onboarding chat (dashboard entry). Onboarding-incomplete
    // users (incl. funnel arrivals) start the onboarding conversation; a returning
    // mid-flow user gets the welcome-back resume (summary + Continue / Something
    // else), not the full transcript.
    async startOnboarding() {
      if (this.sending) return;
      this.sending = true;
      this.resumeId = null;
      const cursor = { reply: { role: 'fyn', text: '', bubbles: [] }, got: false, navigation: null };
      this.messages.push(cursor.reply);
      this.$nextTick(this.scrollFyn);
      try {
        await apiStream(
          '/api/ai-chat/onboarding/start',
          {},
          store.token,
          (piece) => { if (piece) cursor.got = true; cursor.reply.text += piece; this.$nextTick(this.scrollFyn); },
          (ev) => this.handleFynEvent(cursor, ev),
        );
        if (this.resumeId) {
          await this.streamFynAction(this.resumeId, 'resume', cursor);
        } else if (!cursor.got && !(cursor.reply.bubbles && cursor.reply.bubbles.length)) {
          cursor.reply.text = `Hi ${this.firstName}. Let's get your plan started — what would you like to look at?`;
        }
        if (cursor.levelUp) { store.queueCelebration(cursor.levelUp); this.pulseWheel(); }
      } catch (e) {
        cursor.reply.text = 'Sorry, I had trouble starting just now. Please try again.';
      } finally {
        this.sending = false;
        this.$nextTick(this.scrollFyn);
      }
    },

    // Resume the onboarding conversation on the docked Fyn bar (a module screen).
    // The campaign verify flow navigates the user to a section's screen mid-chat;
    // when they reopen the dock here, re-emit the CURRENT pending onboarding turn
    // (e.g. the Gate-2 "Is this information correct?" + Yes/No bubbles) instead of
    // a fresh advice greeting. Mechanism: /onboarding/start returns the existing
    // conversation id (resume event) for a mid-flow user; the `continue` action
    // re-emits the saved step's turn into the same conversation.
    async resumeOnboardingInDock() {
      if (this.sending) return;
      this.sending = true;
      this.resumeId = null;
      const cursor = { reply: { role: 'fyn', text: '', bubbles: [] }, got: false, navigation: null };
      this.messages.push(cursor.reply);
      this.$nextTick(this.scrollFyn);
      try {
        await apiStream(
          '/api/ai-chat/onboarding/start',
          {},
          store.token,
          (piece) => { if (piece) cursor.got = true; cursor.reply.text += piece; this.$nextTick(this.scrollFyn); },
          (ev) => this.handleFynEvent(cursor, ev),
        );
        if (this.conversationId) {
          // Re-emit the saved step so the waiting verify turn (and its bubbles)
          // render here. streamFynAction reuses this placeholder reply.
          await this.streamFynAction(this.conversationId, 'continue', cursor);
          // The verify-navigate turn re-emits its navigation event; the guard in
          // handleOnboardingNavigation no-ops when we're already on that screen,
          // so the dock stays open with the Gate-2 bubbles.
          if (cursor.navigation) this.handleOnboardingNavigation(cursor.navigation);
        } else if (!cursor.got && !(cursor.reply.bubbles && cursor.reply.bubbles.length)) {
          cursor.reply.text = `Hi ${this.firstName}. What would you like to look at?`;
        }
        if (cursor.levelUp) { store.queueCelebration(cursor.levelUp); this.pulseWheel(); }
      } catch (e) {
        if (!cursor.got && !(cursor.reply.bubbles && cursor.reply.bubbles.length)) {
          cursor.reply.text = 'Sorry, I had trouble loading that just now. Please try again.';
        }
      } finally {
        this.sending = false;
        this.$nextTick(this.scrollFyn);
      }
    },

    // Stream a director action (resume / continue / something_else) into the
    // given cursor's reply, rendering the turn it produces — e.g. the welcome-
    // back summary + Continue / Something else bubbles on resume, or the saved
    // step's turn on continue.
    async streamFynAction(conversationId, action, cursor) {
      cursor.got = false;
      cursor.reply.text = '';
      cursor.reply.bubbles = [];
      try {
        await apiStream(
          `/api/ai-chat/conversations/${conversationId}/action`,
          { action },
          store.token,
          (piece) => { if (piece) cursor.got = true; cursor.reply.text += piece; this.$nextTick(this.scrollFyn); },
          (ev) => this.handleFynEvent(cursor, ev),
        );
      } catch (e) {
        if (!cursor.got && !(cursor.reply.bubbles && cursor.reply.bubbles.length)) {
          cursor.reply.text = 'Sorry, I had trouble loading that just now. Please try again.';
        }
      }
    },

    // Run a resume action bubble (Continue / Something else): stream the turn it
    // produces into a fresh Fyn message.
    async runFynAction(action) {
      if (this.sending || !this.conversationId) return;
      this.sending = true;
      const cursor = { reply: { role: 'fyn', text: '', bubbles: [] }, got: false, navigation: null };
      this.messages.push(cursor.reply);
      this.$nextTick(this.scrollFyn);
      try {
        await this.streamFynAction(this.conversationId, action, cursor);
        if (cursor.navigation) this.handleOnboardingNavigation(cursor.navigation);
      } finally {
        this.sending = false;
        this.$nextTick(this.scrollFyn);
      }
    },

    // Non-text onboarding SSE events. Text arrives via the onDelta path into
    // `cursor.reply`. A single user message can stream MULTIPLE onboarding turns
    // (a capture acknowledgement, then the next prompt) separated by an
    // `onboarding_advance` event — so we split into a fresh bubble on each
    // advance, otherwise the ack and the next question merge into one message.
    handleFynEvent(cursor, ev) {
      if (!ev || !ev.type) return;
      if ((ev.type === 'conversation_created' || ev.type === 'resume') && ev.conversation_id) {
        this.conversationId = ev.conversation_id;
        // 'resume' means the user is mid-onboarding from a prior session.
        if (ev.type === 'resume') this.resumeId = ev.conversation_id;
        return;
      }
      if (ev.type === 'onboarding_advance') {
        // New onboarding turn — open a fresh bubble so the just-streamed
        // acknowledgement and the upcoming prompt render as separate messages.
        if (cursor.reply.text || (cursor.reply.bubbles && cursor.reply.bubbles.length)) {
          cursor.reply = { role: 'fyn', text: '', bubbles: [] };
          this.messages.push(cursor.reply);
        }
        return;
      }
      if (ev.type === 'navigation' && ev.route_path) {
        // Mid-campaign verify navigate or the terminal turn. Captured here; the
        // caller decides how the surface presents it after the stream.
        cursor.navigation = ev.route_path;
        return;
      }
      if (ev.type === 'level_up') {
        // A write this turn crossed a level threshold. The frame arrives AFTER
        // `done`, so the reply is already on screen. Stash it; the caller fires
        // the celebration once the stream settles so we never interrupt mid-reply.
        cursor.levelUp = {
          level: ev.level,
          level_name: ev.level_name,
          next_actions: ev.next_actions || [],
        };
        return;
      }
      if (ev.type === 'quick_replies') {
        // A bubbles turn. If the current bubble already carries streamed text
        // (an acknowledgement from the prior capture), open a fresh bubble for
        // the prompt + choices.
        if (cursor.reply.text) {
          cursor.reply = { role: 'fyn', text: '', bubbles: [] };
          this.messages.push(cursor.reply);
        }
        cursor.got = true;
        if (ev.prompt_text) cursor.reply.text = ev.prompt_text;
        cursor.reply.bubbles = Array.isArray(ev.bubbles) ? ev.bubbles : [];
        // Resume re-engagement bubbles (Continue / Something else) are director
        // actions, not onboarding answers — flag them so chooseBubble routes
        // them to the action endpoint instead of sending the label as a message.
        cursor.reply.actionBubbles = ev.action_bubbles === true;
        this.$nextTick(this.scrollFyn);
      }
    },

    chooseBubble(bubble, message) {
      if (this.sending || !bubble) return;
      // Resume re-engagement bubbles (Continue / Something else) are director
      // actions — route to the action endpoint and consume the bubbles so they
      // can't be re-tapped. Regular onboarding bubbles send their label.
      if (message && message.actionBubbles) {
        message.bubbles = [];
        this.runFynAction(bubble.id);
        return;
      }
      this.send(bubble.label || bubble.id);
    },

    async send(preset) {
      const text = (preset || this.draft || '').trim();
      if (!text || this.sending) return;
      this.sending = true;
      this.draft = '';
      // Prior bubbles are now answered — remove them so they can't be re-tapped.
      this.messages.forEach((m) => { if (m.bubbles) m.bubbles = []; });
      this.messages.push({ role: 'user', text });
      const cursor = { reply: { role: 'fyn', text: '', bubbles: [] }, got: false, navigation: null };
      this.messages.push(cursor.reply);
      this.$nextTick(this.scrollFyn);
      try {
        const cid = await this.ensureConversation();
        if (!cid) {
          cursor.reply.text = 'Sorry, I could not start a conversation just now.';
          return;
        }
        await apiStream(
          `/api/ai-chat/conversations/${cid}/messages`,
          { message: text, current_route: (this.$route && this.$route.path) || '/dashboard' },
          store.token,
          (piece) => {
            if (piece) cursor.got = true;
            cursor.reply.text += piece;
            this.$nextTick(this.scrollFyn);
          },
          (ev) => this.handleFynEvent(cursor, ev),
        );
        if (!cursor.got && !(cursor.reply.bubbles && cursor.reply.bubbles.length)) {
          cursor.reply.text = 'Sorry, I had trouble responding just now.';
        } else if (!cursor.reply.text && !(cursor.reply.bubbles && cursor.reply.bubbles.length)) {
          // Trailing empty bubble (e.g. an advance opened one but the turn ended
          // on a navigation) — drop it so no blank message lingers.
          const idx = this.messages.indexOf(cursor.reply);
          if (idx !== -1) this.messages.splice(idx, 1);
        }
        if (cursor.navigation) this.handleOnboardingNavigation(cursor.navigation);
        // Celebrate AFTER the reply has rendered (the level_up frame arrives
        // after `done`), so the fireworks never interrupt Fyn mid-reply.
        if (cursor.levelUp) { store.queueCelebration(cursor.levelUp); this.pulseWheel(); }
      } catch (e) {
        cursor.reply.text = 'Sorry, something went wrong. Please try again.';
      } finally {
        this.sending = false;
        this.$nextTick(this.scrollFyn);
      }
    },

    // Onboarding-driven navigation on the /m surface: the campaign verify flow
    // routes to a section's screen (campaign_verify_navigate) and the terminal
    // turn routes to /tax-strategy. Close the Fyn overlay first so the screen is
    // in front, then route. When the chat re-emits a navigation for the screen
    // we're ALREADY on (the dock re-showing the Gate-2 turn), keep the chat open
    // so the bubbles stay visible. Unknown desktop-only routes are ignored.
    handleOnboardingNavigation(routePath) {
      if (!ONBOARDING_NAV_ROUTES.includes(routePath)) return;
      if (this.$route && this.$route.path === routePath) return;
      this.closeFyn();
      this.$nextTick(() => {
        if (this.$route.path !== routePath) this.$router.push(routePath);
      });
    },
  },
};
