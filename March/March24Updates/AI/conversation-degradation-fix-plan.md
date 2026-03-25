# Conversation Length Degradation — Fix Plan

**Date:** 24 March 2026
**Issue:** AI form fills fail silently after ~3-7 fills in a single chat session
**Affects:** Both Anthropic and Grok (xAI)
**Root cause:** Frontend timing, NOT LLM context window (xAI has 2M token window + session caching)

---

## Current Architecture

```
1. AI streams fill_form event  →  aiChat.js receives it
2. 500ms setTimeout            →  dispatches aiFormFill/startFill
3. startFill                   →  sets pendingFill + starts 10s fallback timer
4. InvestmentList watches      →  sets showAccountForm = true (opens modal)
5. AccountForm mounts          →  pendingFill watcher (immediate) fires
6. beginFieldSequence          →  clears fallback timer, starts filling
7. Fields fill at 250ms each   →  highlightedField watcher sets formData
8. filling = false             →  250-500ms → submitForm() → emit save
9. handleAccountSave           →  API call → completeFill → "Done" message
```

## Why It Degrades

**Hypothesis (most likely):** The 10-second fallback timer expires before step 5.

In longer conversations:
- The SSE stream carries more data (full conversation history in streaming chunks)
- The `fill_form` event arrives later in the stream because the AI generates a longer text response before the tool call
- By the time `startFill` fires (step 3), the form may not mount in time (step 5)
- The 10s fallback clears `pendingFill` → form's watcher never sees it → silent failure

**Evidence:**
- Debug logs show `pendingFill watcher fired` and all fields setting correctly for early fills
- Later fills show NO debug logs at all — the form watcher never fires
- Starting a new conversation resets everything and fills work again

**NOT the cause:**
- LLM context window (xAI has 2M tokens, degradation hits at ~3-7 fills which is maybe 5-10k tokens)
- Backend validation (all types pass when they reach the backend)
- Frontend validation (validateForm passes in debug logs)

---

## Fix Options (Priority Order)

### OPTION 1: Event-Based Handshake (RECOMMENDED)
**Effort:** Medium | **Impact:** High | **Reliability:** Best

Replace the timing-based approach with an event-based handshake:

```javascript
// aiFormFill.js — startFill action
startFill({ commit, state: s }, payload) {
  commit('SET_PENDING_FILL', payload);
  // No timer — pendingFill persists until form confirms receipt
}

// AccountForm.vue — pendingFill watcher
pendingFill: {
  handler(fill) {
    if (fill && fill.entityType === 'investment_account' && fill.fields) {
      // Acknowledge receipt — this confirms the form is ready
      this.$store.dispatch('aiFormFill/acknowledgeFormReady');
      // Then begin filling
      if (fill.fields.account_type) {
        this.formData.account_type = fill.fields.account_type;
      }
      const fieldOrder = Object.keys(fill.fields).filter(k => fill.fields[k] !== null && fill.fields[k] !== '');
      this.$store.dispatch('aiFormFill/beginFieldSequence', fieldOrder);
    }
  },
  immediate: true,
}

// aiFormFill.js — new action
acknowledgeFormReady({ state: s }) {
  clearTimeout(fallbackTimer);  // Form is ready, cancel fallback
}
```

**Key change:** The fallback timer only clears when the form explicitly acknowledges. If the form never mounts, the fallback still fires — but we can make it longer (30s or 60s) since it's truly a last resort.

### OPTION 2: Increase Fallback Timer + Retry
**Effort:** Low | **Impact:** Medium | **Reliability:** Good

```javascript
// aiFormFill.js — startFill
startFill({ commit, state: s, dispatch }, payload) {
  commit('SET_PENDING_FILL', payload);

  clearTimeout(fallbackTimer);
  let attempts = 0;

  const checkFilling = () => {
    attempts++;
    if (s.filling) return; // Form picked it up
    if (attempts >= 6) {
      // 30 seconds total — give up
      commit('CLEAR');
      // Report to chat
      commit('aiChat/ADD_MESSAGE', {
        id: 'fill_timeout_' + Date.now(),
        role: 'assistant',
        content: 'The form didn\'t load in time. Please try again or add the account manually.',
        created_at: new Date().toISOString(),
      }, { root: true });
      return;
    }
    fallbackTimer = setTimeout(checkFilling, 5000);
  };

  fallbackTimer = setTimeout(checkFilling, 5000);
}
```

**Pros:** Simple change, backwards compatible
**Cons:** Doesn't fix root cause, just extends the window

### OPTION 3: Clear Previous Fill State Before New Fill
**Effort:** Low | **Impact:** Medium | **Reliability:** Good

The issue might be stale state from the previous fill interfering:

```javascript
// aiChat.js — fill_form event handler
case 'fill_form':
  // Clear any stale fill state from previous fill
  dispatch('aiFormFill/cancelFill', null, { root: true });

  if (event.route) {
    commit('SET_PENDING_NAVIGATION', event.route);
  }
  setTimeout(() => {
    dispatch('aiFormFill/startFill', { ... }, { root: true });
  }, 500);
  break;
```

### OPTION 4: Navigation-Aware Fill Dispatch
**Effort:** Medium | **Impact:** High | **Reliability:** Good

Instead of a fixed 500ms delay before `startFill`, wait for the actual route change:

```javascript
// aiChat.js — fill_form handler
case 'fill_form':
  if (event.route) {
    commit('SET_PENDING_NAVIGATION', event.route);
  }

  // Wait for route to actually change, then dispatch
  const router = this._vm.$router || rootState.route;
  const targetRoute = event.route;

  const waitForRoute = () => {
    return new Promise(resolve => {
      const checkRoute = setInterval(() => {
        if (window.location.pathname === targetRoute ||
            window.location.pathname.includes(targetRoute)) {
          clearInterval(checkRoute);
          resolve();
        }
      }, 100);
      // Max wait 5 seconds
      setTimeout(() => { clearInterval(checkRoute); resolve(); }, 5000);
    });
  };

  waitForRoute().then(() => {
    // Page is loaded, now dispatch fill
    dispatch('aiFormFill/startFill', { ... }, { root: true });
  });
  break;
```

---

## Recommended Fix: Combine Options 1 + 3

1. **Clear stale state** before each new fill (Option 3) — prevents interference
2. **Event-based handshake** (Option 1) — removes timing dependency
3. **Increase fallback to 30s** with user-facing timeout message — safety net

This combination:
- Eliminates the race condition entirely
- Works regardless of conversation length
- Works with both Anthropic and xAI streaming speeds
- Provides user feedback if something truly fails

## Implementation Plan

### Files to Change:
1. `resources/js/store/modules/aiFormFill.js` — new `acknowledgeFormReady` action, increase fallback to 30s, add timeout message
2. `resources/js/store/modules/aiChat.js` — clear stale state before dispatching new fill
3. `resources/js/components/Investment/AccountForm.vue` — dispatch `acknowledgeFormReady` in pendingFill watcher
4. `resources/js/components/Protection/PolicyFormModal.vue` — same acknowledgeFormReady
5. All other form components with `pendingFill` watchers — same pattern

### Testing:
- Test 7+ consecutive fills in a single conversation with both providers
- Verify the 30s timeout message appears if form truly can't mount
- Verify no regression on short conversations

## xAI Session Caching Advantage

Since xAI caches the session server-side:
- Subsequent requests in the same session are cheaper (cache hit)
- The 2M token window means we never need to truncate conversation history
- Longer conversations are actually CHEAPER per-token with xAI than starting fresh
- This makes the "break into batches" workaround LESS desirable for xAI — better to fix the frontend so long conversations work properly

For Anthropic:
- No session caching, so longer conversations = more input tokens = more cost
- The fix still helps, but batching is also valid as a cost-saving measure
