# Wave 3 report — unique row ids, /m error style

## Item 1 — duplicate message ids

Change: `pushCaptureFormTurn()` in `resources/js/store/modules/aiChat.js` (line 74) — the
prompt-text row's id now builds from `'cf_prompt_' + Date.now()` instead of reusing the flush
row's `'cf_text_' + Date.now()`. `captureFormMessage`'s `'cf_'` prefix (line 44) confirmed
non-colliding with both.

RED: added a case to `tests/frontend/store/aiChatCaptureForm.test.js` (content delta -> streamingText
set -> capture_form with prompt_text) asserting the flush row, prompt row and form row all get
distinct ids. Failed pre-fix: `expected 2 to be 3`.
GREEN: post-fix, `npx vitest run tests/frontend/store` — 6 files, 35 tests passed.

Files: `resources/js/store/modules/aiChat.js`, `tests/frontend/store/aiChatCaptureForm.test.js`.
Commit: acf533009 `fix(web): capture-form flush and prompt rows get distinct message ids`.

## Item 2 — /m error line boxed

Change: `resources/mobile/views/dashboard.css` — added `.md-fyn__form-error` to the `:not()`
exclusions on the two bubble rules (`.md-fyn__msg p...`, `.md-fyn__msg--fyn p...`, ~lines
1346/1357), and gave `.md-fyn__form-error` a full margin reset (`margin: 4px 0 0`) matching the
pattern already used for `.md-fyn__form-hint`, since it no longer inherits the bubble rule's
`margin: 0`. Template untouched.

RED: added a case to `resources/mobile/components/__tests__/FynCaptureForm.spec.js` reading
`dashboard.css` as text (same pattern as `Dashboard.spec.js`'s CSS-rule regex checks) and
asserting both bubble rules exclude `.md-fyn__form-error` and its own rule carries no
border/padding/background. Failed pre-fix on the bubble-rule assertion.
GREEN: post-fix, `npx vitest run resources/mobile` — 35 files, 210 tests passed.

Files: `resources/mobile/views/dashboard.css`, `resources/mobile/components/__tests__/FynCaptureForm.spec.js`.
Commit: 650b599b5 `fix(m): the capture-form error line no longer renders as a boxed bubble`.

## Concerns

None — both fixes isolated, no template changes, no scope beyond the two items.
