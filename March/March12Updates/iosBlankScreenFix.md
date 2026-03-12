# iOS Blank Screen Fix — WKWebView MIME Type Error

**Date:** 12 March 2026
**Branch:** `feature/mobile-app-phase0`

---

## Problem

iOS Capacitor app builds and installs but shows a blank screen. Xcode console shows:

```
[App Init] Step 8-ERR: Router failed: 'image/png' is not a valid JavaScript MIME type.
```

The app loads, executes JavaScript (all init steps 1-7 complete), but Vue Router fails at `router.isReady()` when trying to resolve the initial route `/m/login`.

## Root Cause

Vue's template compiler converts static `<img src="/images/logos/favicon.png">` attributes into ES module imports:

```js
// Compiled output in built JS chunk:
import v from "/images/logos/favicon.png"
```

The Rollup `external: [/^\/images\//]` config (added in Phase 2b commit `6223d79`) left these imports as-is in the bundle output instead of processing them through Vite's asset pipeline.

When WKWebView tries to execute `import "/images/logos/favicon.png"`, it fetches the file, receives `Content-Type: image/png`, and rejects it with the MIME type error. This crashes router initialisation because the lazy-loaded `MobileLoginScreen.vue` chunk (and others) fail to load.

**Chain of events:**
1. `<img src="/images/logos/favicon.png">` in Vue template
2. Vue SFC compiler transforms to `import _imports_0 from '/images/logos/favicon.png'`
3. Rollup `external` config keeps import as-is in output
4. WKWebView rejects PNG import: `'image/png' is not a valid JavaScript MIME type`
5. Chunk fails to load, `router.isReady()` rejects
6. Blank screen

## Fix

### 1. Changed static `src` to dynamic `:src` binding (14 files)

Dynamic `:src` bindings are treated as runtime string expressions by the Vue compiler, not build-time imports. The image path stays as a plain string in the output.

**Before:** `<img src="/images/logos/favicon.png" />`
**After:** `<img :src="'/images/logos/favicon.png'" />`

### 2. Removed Rollup `external` workaround

Removed `external: [/^\/images\//]` from `vite.config.js` — this was the Phase 2b workaround that caused the MIME type error. With dynamic bindings, there are no `/images/` imports for Rollup to process.

## Files Changed

### vite.config.js
Removed Rollup `external` config for `/images/` paths.

### Mobile Components (14 files)
All changed `src="/images/logos/favicon.png"` to `:src="'/images/logos/favicon.png'"`:

| File | Line |
|------|------|
| `resources/js/mobile/ToolExecutionStatus.vue` | img src |
| `resources/js/mobile/FynInsightCard.vue` | img src |
| `resources/js/mobile/TypingIndicator.vue` | img src |
| `resources/js/mobile/ChatBubble.vue` | img src |
| `resources/js/mobile/SuggestedPrompts.vue` | img src |
| `resources/js/mobile/PushPermissionPrompt.vue` | img src |
| `resources/js/mobile/views/MobileLoginScreen.vue` | img src |
| `resources/js/mobile/views/MobileDashboard.vue` | img src |
| `resources/js/mobile/views/LearnHub.vue` | img src |
| `resources/js/mobile/views/LearnTopicDetail.vue` | 2 img src attributes |
| `resources/js/mobile/views/ModuleSummary.vue` | img src |
| `resources/js/mobile/goals/MilestoneOverlay.vue` | img src |
| `resources/js/mobile/learn/LearnInfoPopup.vue` | img src |

## Rebuild & Verify

```bash
# Rebuild iOS app
./deploy/mobile/build-ios.sh

# Verify no image imports in built JS
grep -rl 'from"/images/' ios/App/App/public/assets/ && echo "BROKEN" || echo "CLEAN"

# Open Xcode, build and run
open ios/App/App.xcworkspace
```

## Lesson Learned

**Never use static `src` for public directory images in Vue templates when targeting Capacitor/native.**

The Vue SFC compiler transforms static `src` attributes on `<img>` tags into ES module imports. This works on web (Vite resolves them) but breaks in Capacitor's WKWebView because:
- Capacitor serves from `capacitor://localhost`
- WKWebView enforces strict MIME type checking for module imports
- PNG files served with `image/png` MIME type are rejected as JavaScript modules

**Rule:** Always use `:src="'/path/to/image.png'"` (dynamic binding) for images in `public/` directory. This keeps the path as a runtime string, not a build-time import.

Alternatively, import images through Vite's asset pipeline (`import logo from '@/assets/logo.png'`) which hashes and inlines them into the bundle.
