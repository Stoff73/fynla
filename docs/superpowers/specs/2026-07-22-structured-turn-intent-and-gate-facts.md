# Structured Turn Intent + Gate Confirmed-Facts (Design)

**Status:** CSJ-approved direction (2026-07-22, "yes" to the structured gate
signal); this spec broadens it to the whole seam after the night's live
evidence. Not yet implemented — the plan follows on approval of this document.

## Problem — the text-sniffing seam

The onboarding director decides what the PREVIOUS assistant turn *meant* by
sniffing its content, and the CaptureAccuracyGate decides whether a fact is
*confirmed* by regexing recent user text. Five live failures on 2026-07-21/22
came from exactly this seam, each patched tactically:

1. A model re-ask counted as "an answer already voiced", suppressing real
   interruption answers (fixed `5a3989a` — content heuristic refined).
2. An advice answer closing with "Would you like me to help you add those
   now?" armed the capture-clarification followup, hijacking the next
   on-script answer (fixed `d12ac27` — persona-gated + `is_interruption_answer`
   tag; poisoned stored payloads discarded on read in `c4058f4`).
3. The interruption store offer, stamped `onboarding_step`, silently became
   the gate's evidence-window boundary, cutting the entity sentence out of
   ownership evidence (fixed `eda2062` — stamp removed).
4. "Owned by me" — natural individual-ownership phrasing — missed the gate's
   regex list; ISAs were interrogated about ownership at all (fixed
   `a2e926d`/`8eb3f8c` — ISA ownership automatic; the general phrasing gap
   remains for non-ISA assets).
5. The gate's per-entity evidence chaining breaks on interposed affirmations
   ("Yes, save it") and across the 6-message window (Santander-class misses,
   logged not fixed).

Every fix is correct; the architecture that required five of them is not.

## Design

### 1. `turn_intent` — one enum, persisted on every assistant message

`ai_messages.metadata.turn_intent` (string enum), written at persist time by
the code that KNOWS what it is emitting — no inference ever:

| intent | emitted by |
|---|---|
| `step_prompt` | emitTurnForState / emitFirstTurn |
| `capture_clarification` | handleInlineCapture failure voice, emitRetry, gate-blocked asks |
| `interruption_answer` | question-branch inline answers, A1 answers |
| `interruption_offer` | handleInformationInterruption store offer |
| `deferred_promise` / `deferred_raise` | deferQuestion / emitDeferredQuestions |
| `resume_greeting` | handleResumeAction (subsumes `is_resume_greeting`) |
| `verify_prompt` / `verify_ack` | verify-navigate/announce machinery |
| `celebration` / `terminal_note` | done/terminal turns |
| `advice_answer` | advice-state answers (post-completion) |

Read-side rule: **consumers branch on `turn_intent` when present and fall back
to today's heuristics only when absent** (legacy rows). The existing
`is_resume_greeting`, `is_retry`, `capture_write_failed`,
`is_interruption_answer` flags stay as written (backward compatibility) but
new code never adds a new boolean — it sets the enum.

Replaces, over time: `captureResponseRequestsClarification` sniffing for
followup arming; the resume-prune query (`turn_intent = resume_greeting`);
the evidence-window boundary predicate (`turn_intent = step_prompt` instead
of "has onboarding_step and isn't a failure/greeting").

### 2. Gate confirmed-facts — the director tells the gate, not the transcript

`CaptureAccuracyGate::inspect(tool, arguments, latestUserText)` gains a
fourth parameter: `array $confirmedFacts` (default `[]`), e.g.
`['ownership_type' => 'individual', 'isa_subtype' => 'cash']`. A fact present
in `$confirmedFacts` is satisfied without text evidence. Callers populate it
from STRUCTURED sources only:

- The pending-interruption detail turn: when the awaiting-detail reply parses
  an ownership/subtype (extractor's deterministic patterns), the director
  passes it as a confirmed fact — no window heuristics, no chaining.
- ISA writes: `ownership_type=individual` is a law-level confirmed fact
  (already automatic since `a2e926d`; the parameter formalises it).
- Campaign scripted answers: when a step's own question asked for the fact,
  its parsed answer is confirmed by construction.

The text-evidence path remains for genuinely free-form first attempts. The
gate's regex lists stop being the only door; their gaps ("owned by me") stop
being user-facing dead ends.

### 3. Explicitly out of scope

- No prompt changes; no provider/corpus changes.
- The delegated-step A1 answer-now behaviour vs defer-and-track at other step
  types (CSJ to rule separately — see the walk report).
- Backfilling `turn_intent` onto historical rows (legacy fallback covers it).

## Rollout

Additive and safe: enum writes land first (no behaviour change), read-side
switches follow one consumer at a time, each with the fallback intact and a
pinning test that the structured path is preferred. Gate facts land behind
its default-empty parameter. Full Pest coverage per consumer; live walk on
csjones per Rule 14 before dev merge.
