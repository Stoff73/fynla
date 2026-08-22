---
id: W-0041
title: Every chattel delete succeeds and then returns 500 — the user is shown an error for a completed action
mission: M-0002-persona-fidelity
owner: build-lead
status: handoff
claimed: 2026-08-21T13:00:00Z
handoff_to: quality-lead
branch: workforce/branches/fixes/F-0002-batch-a-ownership-net-worth.md
severity: high
surfaces: [web, m, ios]
source: found by fix-batch-A during W-0025, 2026-08-21; pre-existing, reported not fixed per scope discipline
prior_art_checked: 2026-08-21
prior_art_outcome: none
---

## Intent

`app/Http/Controllers/Api/ChattelController::destroy():206` returns
`response()->noContent()` against a declared `: JsonResponse` return type. The row is
deleted, then the return-type mismatch throws — so **every chattel delete removes the
record and then 500s.**

The user sees an error for an action that actually succeeded. They will reasonably
retry, or assume the chattel is still there.

## Why it is high severity despite being a one-line fix

It is the inverse of W-0026 (policy end date accepted, then silently dropped) and
W-0032 (`scheme_status` collected, then discarded): those tell the user something
worked when it did not. This tells the user something failed when it did. Both destroy
the user's ability to trust what the screen says.

## Acceptance

1. A successful chattel delete returns a success response and the user sees success.
2. Check the same mismatch elsewhere — a controller declaring `: JsonResponse` while
   returning `noContent()` is a pattern, not a one-off. Sweep the API controllers for
   it rather than fixing only this line.
3. Reaches web, `/m` and iOS — all three call the same endpoint.

## Working notes

Pre-existing, outside all nine Batch A items. fix-batch-A reported rather than fixed
it, which was correct under its scope, and flagged the one-line nature.

- 2026-08-21 build-lead: **Acceptance 1 FIXED. Acceptance 2 SWEPT AND ENUMERATED — one further instance found, and it is NOT mine to fix.**

  ### 1. The chattel delete (fixed)

  `ChattelController::destroy()` now returns the house-standard delete body
  instead of `noContent()`:

  ```php
  return response()->json([
      'success' => true,
      'message' => 'Chattel deleted successfully',
  ]);
  ```

  200 with `{success, message}` is the convention every sibling already follows —
  `SavingsController::destroyAccount:~405`, `PropertyController::destroy:331`, and
  `app/Http/CLAUDE.md` ("200 | GET success, updates, deletes"). It is also the
  shape the frontend already reads: `chattels.js` `deleteChattel` surfaces
  `error.response?.data?.message`. So this is a return to the house rule, not a
  new contract — and nothing could have depended on the old success path, because
  there wasn't one: it 500'd every time.

  Tests: `tests/Feature/Chattels/JointChattelCounterpartyTest.php` — **9 passed**,
  two new. Both the individual and the joint delete path are covered; the joint
  path takes a different branch (it invalidates the co-owner's cache) before
  reaching the same return, so a passing individual case would not have proved it.

  ### 2. The sweep (enumerated — this is the durable artefact)

  Scanned **all 146 files** under `app/Http/Controllers/**` for methods whose
  declared return type does not admit what they return. Two passes: first for
  `noContent()` / `download()` / `stream()` / `file()`, then for
  `redirect()` / `view()` / `make()` / `back()` inside a strict `: JsonResponse`.

  **Result: exactly one genuine remaining mismatch in the entire codebase.**

  | File:line | Method | Declared | Returns | Verdict |
  |---|---|---|---|---|
  | `app/Http/Controllers/Api/Estate/WillController.php:221` | `deleteBequest()` | `JsonResponse` | `noContent()` | **LIVE BUG — identical to the chattel one. Every bequest delete removes the row and then 500s.** |
  | `app/Http/Controllers/Api/Admin/DocumentArticleController.php:73` | `destroy()` | `Response` | `noContent()` | OK — the type admits it |
  | `app/Http/Controllers/Api/Admin/NewsSubscriberController.php:70` | `export()` | `StreamedResponse` | `stream()` | OK |
  | `app/Http/Controllers/Api/GDPRController.php:179` | `downloadExport()` | `JsonResponse\|StreamedResponse` | `download()` | OK — union type |
  | `app/Http/Controllers/Pipeline/SignedClipDownloadController.php:33` | `download()` | `BinaryFileResponse` | `file()` | OK |

  So the pattern is real but **rare**: two instances, both `noContent()` against
  `: JsonResponse`, both on a delete endpoint. Your instinct to sweep was right;
  the tail is short.

  ### 3. Why I did not fix `WillController::deleteBequest`

  **`Estate/Wills` is Batch B's area.** My dispatch says to stop and report rather
  than reach into it, and recent commits (`fix/bequest-edit-controls`,
  `feat(m): show bequests on mobile`) show live work on exactly that surface — an
  edit from me risks a conflict for a one-line change they can make safely.

  The fix is identical to the one above, and their sibling convention is the same:

  ```php
  return response()->json([
      'success' => true,
      'message' => 'Bequest deleted successfully',
  ]);
  ```

  **This is likely to be user-visible right now**, because the `/m` bequest screen
  and the web bequest Edit/Delete controls both shipped in the last few days. It
  should be routed to Batch B or raised as its own item — not left in this one,
  since this item is being handed off.

  ### 4. Note on the scanner

  `ChattelController.php:208` still matches a naive `noContent` grep — that hit is
  the **explanatory comment** on the fixed line, not a return. Anyone re-running
  the sweep should match `return response()->noContent()` rather than the bare
  word, or they will re-report this as unfixed.

- 2026-08-21 build-lead (Batch B): **acceptance 2 — second instance found and
  fixed**, recorded here rather than as a new item so both live together.

  `app/Http/Controllers/Api/Estate/WillController.php:221` `deleteBequest()`
  declared `: JsonResponse` and returned `response()->noContent()` — identical to
  the chattel case. The bequest was deleted and the response then threw a
  TypeError, so the user saw an error for an action that had succeeded.

  Fixed to the house standard (200 + `{success, message}`), matching Batch A's
  chattel fix, `SavingsController::destroyAccount`, `PropertyController::destroy`
  and `app/Http/CLAUDE.md`. Nothing could depend on the old success path because
  there was no success path.

  **On Batch A's warning about a second branch:** `deleteBequest` has no joint or
  cascade branch — it is a single `firstOrFail` + `delete` + cache invalidation,
  so one path reaches the return. I covered the will-document-linked row
  separately anyway, since `will_document_id` rows are the ones W-0023 creates
  and the ones the newly shipped `/m` and web controls delete.

  **Sweep of my own area confirms the tail is closed.**
  `grep -rn "response()->noContent()" app/Http/Controllers/` now returns exactly
  one hit — `Admin/DocumentArticleController.php:73` — which Batch A already
  determined is correct for its declared return type.

  **Adjacent behaviour worth knowing, reported not fixed:** deleting a bequest
  that carries a `will_document_id` removes the row, but a later re-completion of
  that will recreates it from the document's gifts. That is arguably right — the
  document is the source of truth and the gift is still written in it, so the
  place to remove it is the will builder's Gifts step — but it will surprise a
  user who deletes from the Estate screen. Raise as its own item if it should
  behave differently.

  Tests: `tests/Feature/Estate/BequestDeleteResponseTest.php` — 200 + body,
  will-document-linked row, and cross-user isolation.
