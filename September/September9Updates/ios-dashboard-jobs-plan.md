# iOS dashboard jobs — 2026-09-09

CSJ's four jobs from the native app (11:35 BST). Rule 19: /m is in scope for each. Rule 20: every Fyn behaviour is built once, server-side, and consumed by native and /m from that one home.

## Job 1 — recommendation titles too long

**Root cause.** `NextActionsService::recommendationItems()` (`app/Services/Mobile/NextActionsService.php:304`) puts the aggregator's `recommendation_text` in the action `title`, and the aggregator builds that string as `title.' — '.description` (`RecommendationsAggregatorService.php:215,310`). Every surface reading the mobile dashboard payload (native, /m, the web /actions page, achievements) shows the full sentence.

**Fix (one place).** The aggregator carries `title` and `description` as separate fields next to `recommendation_text` (which mark-done and tracking still use). `NextActionsService` sets the action `title` to the short title, cut at the first " — " (tax strategy titles carry their own tagline after a dash: "Open a pension for each child — instant £1,440 a year of free money"), and adds `detail` = everything after it (tagline + description). No client change needed for the display; native and /m already render `title`.

## Job 2 — tapping a recommendation

**Today.** Every recommendation is `action.kind = navigate` to the module screen (`NextActionsService.php:322`). Only unlock cards route to Fyn, and the capture prompt text is duplicated per surface (`AppRootView.swift:521`, `/m Dashboard.vue:772`).

**Design.**

1. **Classification lives in one PHP map** (`App\Services\Mobile\RecommendationRouting`, keyed by recommendation id). Default is `page`; the Fyn set is listed in the plan message. `NextActionsService` emits for Fyn-routed recs: `action = {kind: 'fyn_capture', payload: <module>, prompt: <server-composed message>, recommendation_id: <id>}`. Unlock cards gain `prompt` too, so both clients drop their local prompt maps (Rule 20 consolidation).
2. **Clients** (native `DashboardView`/`AppRootView`, /m `Dashboard.vue`): a `fyn_capture` tap opens Fyn and sends `action.prompt`, passing `recommendation_id` on the message POST (new optional field on `sendMessage`). No client-side wording.
3. **Server turn.** `AiChatController::sendMessage` accepts `recommendation_id`, stores it on the conversation (`metadata.pending_recommendation`). `AdviceFyn` routes a turn carrying a pending recommendation deterministically into `handleInlineCapture` with a `CaptureContext` built from the recommendation's module (same path the write-intent classifier uses), after yielding the opening line: "I can help you enter the information for <title>. <detail>". The usual capture check process (gap-fill, duplicate check, capture_complete receipt) is untouched.
4. **After the capture writes**, the server emits `quick_replies` "Is there anything else you'd like to add?" with **Yes** / **No thanks**. Both clients already render `quick_replies` and send the label back as a message.
5. **On "No thanks"** with a pending recommendation: mark it done (`RecommendationTracking::markAsCompleted`, the same path as the mark-done endpoint, so points are awarded), emit a one-line confirmation, then `navigation` to `/dashboard`. Native's `settleNavigation` (route == current) closes the sheet and refreshes; /m's `handleOnboardingNavigation` does the same. Pending recommendation cleared. On "Yes": "What would you like to add?" and the normal advice/capture flow continues; the pending recommendation is cleared so the dashboard tick-off only happens on the explicit No.
6. **Page-routed recs** keep `navigate` exactly as today.

**Open for CSJ.** The classification, and one behaviour: on refresh the dashboard currently *replaces* a completed recommendation with the next-best (CSJ 4.4) rather than leaving it ticked. After "No thanks" the user lands on a dashboard where the item has gone. Leave as is, or show it ticked until the next visit?

## Job 3 — terms and privacy links

**Root cause, reproduced against production.** `RedirectPhoneToMobile` (web middleware, `Kernel.php:121`) redirects every top-level HTML GET from a phone user agent to `/m` unless the path is in `EXCLUDED_PREFIXES`. `privacy` and `terms` are not excluded, and /m has no such routes:

```
curl -H 'Accept: text/html' -A '<iPhone UA>' https://fynla.org/privacy  ->  302 https://fynla.org/m
curl -H 'Accept: text/html' -A '<iPhone UA>' https://fynla.org/terms    ->  302 https://fynla.org/m
```

The native URLs are correct (`SettingsModel.swift:109`, `RegistrationModel.swift:210`); SFSafariViewController sends the iPhone UA and gets bounced to the /m landing page. Same for /m Settings → `openPublicWebPath('/privacy')`, and for any phone visitor tapping the homepage footer links.

**Fix (one place).** Add `privacy`, `terms` and `editorial-policy` to `EXCLUDED_PREFIXES`, with a middleware test. Native and /m need no change.

## Job 4 — campaign banner after completing SaveTax onboarding

**Native half (confirmed in code).** The pill (`AppRootView.swift:453`) reads `settingsModel.onboardingActive`, which reads the cached `coordinator.authenticatedUser`. That user is fetched only at login (`AuthenticationCoordinator.swift:629`) and Face ID unlock (`PrivacyLockController.swift:244`). The Fyn reducer records `onboarding_complete` (`FynEventReducer.swift:49`) but nothing acts on it, so the cached `onboarding_fyn_step` stays non-null and the pill stays up until the next unlock or login. /m mirrors the completion into `store.user` on the same event (`onboardingChat.js:410`).

**Fix.** On `onboarding_complete` native re-fetches the current user through the coordinator (server truth, not a local mutation) and the pill disappears with the chat close. Also re-fetch when the Fyn sheet closes after an onboarding turn, so a walk finished in a resumed conversation clears too.

**Backend half (needs CSJ).** On production, neither native-logged-in account has a completed walk: user 658 (`c.jones@csjones.co`) is at `path_choice` with one two-message conversation from 8 September; user 444 (`chris@fynla.org`) has `onboarding_completed = false`, step null, no campaign. Whether the server-side trigger failed cannot be judged without knowing which account CSJ completed SaveTax on and when.

## Verification

- Pest: `tests/Unit/Services/Mobile/`, `tests/Feature/Mobile/`, `tests/Feature/Middleware/`, the AdviceFyn and AiChatController families touched.
- Native: unit tests for the reducer/model changes; build to the connected iPhone 11; CSJ taps.
- /m: `npm run build:mobile`, Playwright click-through of a Fyn-routed and a page-routed recommendation, and the Settings legal links.
- Prod check for job 3 after release: the two curls above return 200.

## Outcome (12:55 BST)

- **Job 1** — `NextActionsService::splitHeadline`; rows show the headline only on /m (seen live: "Consider a Cash ISA", "Build Your Emergency Fund Urgently"); `detail` carries the rest. Native and web /actions read the same payload.
- **Job 2** — built on the existing contextual-conversation mechanism (`origin.kind = recommendation`, previously unused). `RecommendationRouting` is the one home: Fyn set keyed by the composed ids (personal information, income, expenditure, savings, goals, retirement, protection, estate resource types); page routes go to the exact record (`savings_account_detail`, `pension_detail`, `goal_detail`) or the product page for a tax strategy. The server composes the whole contextual request; both clients post it verbatim. `AdviceFyn::withRecommendationFollowUp` asks "anything else?" after a write, "No thanks" completes through `RecommendationCompletionService` (shared with mark-done) and navigates to the dashboard. Verified live on /m (goal capture, protection capture, No thanks → chat closes, row replaced, wheel moves; page route → Bank Accounts). Rules with no capture tool (investment preferences, care costs) open the page.
- **Job 3** — `privacy`, `terms`, `editorial-policy` excluded from `RedirectPhoneToMobile`; test added.
- **Job 4** — native refetches the session user when the Fyn cover closes mid-walk. Neither production (user 658, at `path_choice`, no user messages) nor csjones (user 149, at `campaign_verify_navigate` since 29 June) holds a completed walk for `c.jones@csjones.co`, so the banner on the phone reflects the server state.
- **Native tests** — no simulator could be opened (Xcode access not granted); the suites ran once on the iPhone 11: the new decode and refresh tests pass, the fixture-reading tests cannot run on a device (absolute Mac paths). Full `FynlaTests` still to run on a simulator.
- **Web (CSJ 13:08: all surfaces)** — `GamifiedDashboard.vue` already read the same payload (headlines were right); it now routes from it too: `aiChat/startContextualConversation` opens the recommendation-origin conversation in the dock, unlock cards use the server prompt (the web prompt map is gone), page routes resolve the semantic destination (`resolveWebDestination`), and a same-screen navigation frame collapses the dock (`fyn-close-chat`) and refetches (`fyn-screen-refresh`). Verified live as john: critical illness row → dock opens with the recommendation opening → policy saved → "anything else?" → "No thanks" → dock collapses, protection tab reads Locked (cover now in place), tally 0 of 4, Level 5; savings row → /savings.
- **"No Emergency Fund Goal" after a goal exists (CSJ 13:08)** — Fyn's `GoalStore::create` never assigned a module and the goals engine only looked in the savings bucket. The store now assigns the module from the goal type (the form's controller already did) and the engine checks by goal type across every active goal. Verified: john's list no longer carries it.
- **Still flagged, not changed** — three account-scoped savings recs share one id (`savings_zero_rate_account`), so marking one done completes all three.
- `ActionsOverviewCard.vue` is imported nowhere (dead component); left alone.
