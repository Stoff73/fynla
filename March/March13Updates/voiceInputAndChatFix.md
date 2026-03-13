---
tags:
  - mobile
  - ios
  - voice-input
  - chat
  - bug-fix
  - march-2026
---

# Voice Input Rewrite & Chat Error Display

**Date:** 2026-03-13
**Branch:** `uiImprovements`
**Commit:** `d54a2ae`

## Voice Input — Continuous Listening Mode

### Problem
- Mic icon activated then immediately deactivated
- App hung after voice input — no speech recognized
- Swift crash (fatal nil unwrap at Plugin.swift:81) when calling stop() then start()
- Old dictation text re-appeared in input after deactivating mic

### Root Cause Analysis
Read native `@capacitor-community/speech-recognition` Plugin.swift source:
- **Line 35-39:** Checks `audioEngine.isRunning`, rejects with "Ongoing speech recognition" if true
- **Line 81:** `self.recognitionRequest!` force-unwrap — CRASH if nil (happens when stop() nils it while start() is running)
- **Line 128-130:** `if partialResults { call.resolve() }` — start() resolves IMMEDIATELY, NOT when recognition finishes
- **Line 101-107:** On `isFinal`, notifies `listeningState` with "stopped"

### Solution — VoiceInputButton.vue Rewrite
1. **Continuous listening mode** — mic stays active across utterances, only deactivates on explicit tap
2. **`listeningState` listener** for safe auto-restart (NOT calling stop/start which causes the race condition)
3. **`partialResults` listener** emits text to input in real-time
4. **No transcript on deactivate** — text is already in input from partial events, prevents duplicate text
5. **500ms delay after fresh permission grant** — iOS needs time after permission dialog
6. **`forceStop()` removes listeners FIRST** — prevents ghost restart loops
7. **Web Speech API fallback** also uses continuous mode with auto-restart on end/error

### Key Files
- `resources/js/mobile/VoiceInputButton.vue` — Complete rewrite (275 lines)
- `resources/js/mobile/views/MobileFynChat.vue` — Changed voice handlers

## Chat Error Display

### Problem
Chat errors from the AI service were invisible — `error` getter was mapped but never rendered in template.

### Solution
- Added error display in MobileFynChat.vue (pink box with dismiss button)
- Added error watcher for auto-scroll
- AiChatService.php now logs actual OpenAI error details (conversation_id, user_id, error message)
- API key-specific user-facing hint ("Configuration issue" vs generic message)

### Production Chat Issue
The Fyn chat is failing on production (fynla.org). Tested locally — OpenAI API key and model (`gpt-5-mini-2025-08-07`) both work. Production returns SSE error event. Need to SSH and check:
```bash
grep "AiChatService" storage/logs/laravel.log | tail -20
grep OPENAI_API_KEY .env
```

## Viewport Zoom Fix

### Problem
iOS auto-zoomed when speech recognition activated or textarea (14px font) got focus.

### Solution
Added `maximum-scale=1.0, user-scalable=no` to viewport meta in `deploy/mobile/build-ios.sh`.

## Files Changed
| File | Change |
|------|--------|
| `resources/js/mobile/VoiceInputButton.vue` | Complete rewrite — continuous listening |
| `resources/js/mobile/views/MobileFynChat.vue` | Error display, voice handlers, async component fix |
| `app/Services/AI/AiChatService.php` | Better error logging |
| `deploy/mobile/build-ios.sh` | Viewport meta zoom prevention |

## Technical Notes — Capacitor Speech Recognition Plugin

**CRITICAL gotchas for future reference:**
- `SpeechRecognition.start()` with `partialResults: true` resolves IMMEDIATELY — do NOT use `.then()` for results
- Results come via `partialResults` listener, NOT the promise
- NEVER call `stop()` then `start()` — causes fatal Swift crash (nil unwrap race condition)
- Use `listeningState` `{status: "stopped"}` as the ONLY safe restart point
- "No speech detected" is normal iOS behavior on silence timeout — handled by auto-restart
- "Ongoing speech recognition" error means previous session still running — wait and retry
