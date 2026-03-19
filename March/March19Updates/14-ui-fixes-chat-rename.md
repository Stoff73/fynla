# 14 — UI Fixes: Info Guide Button, Chat Rename, Session Lifecycle

**PR #143 — uiFix → main**
**Date:** 19 March 2026 (session 2)

## Summary

Moved the info guide (question mark) button from floating bottom-right to the top navbar. Renamed "Fynla Assistant" to "Fyn". Fixed chat session lifecycle so sessions don't persist across opens.

## Changes

### Info Guide Button (Navbar.vue + AppLayout.vue)
- Moved from `InfoGuideButton.vue` (floating, bottom-right, z-40) to inline in `Navbar.vue` (top nav, left of user name)
- Raspberry-600 background with white icon, matching original colour
- Green badge showing missing item count
- Toggle opens/closes the InfoGuidePanel (panel unchanged)
- Removed `InfoGuideButton` component from `AppLayout.vue` (import + registration + template)
- `InfoGuideButton.vue` kept for potential mobile use

### Chat Rename (AiChatPanel.vue)
- "Fynla Assistant" → "Fyn" in both floating panel header and docked panel header

### Chat Session Lifecycle (AiChatPanel.vue + aiChat.js)
- `onOpen()`: always starts a fresh conversation (was resuming stale session)
- `closePanel()`: clears conversation, messages, history state, streaming text
- `startNew()`: clears all state before creating new conversation
- `toggleHistory()`: fetches fresh conversations from API each time (was showing cached stale list)

## Files Changed

| File | Lines Changed |
|------|--------------|
| `resources/js/components/Navbar.vue` | +56 |
| `resources/js/components/Shared/AiChatPanel.vue` | +20 / -8 |
| `resources/js/layouts/AppLayout.vue` | +1 / -4 |
| `resources/js/store/modules/aiChat.js` | +10 / -2 |
