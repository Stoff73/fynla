# Fyn / AI Prompting Playbook — how to point Claude at this work

Companion to `fyn-ai-blindspot-map.md`. The map is the shared ground truth; every prompt below assumes Claude reads it first. Copy-paste and edit the templates.

---

## 1. The general shape of a good Fyn prompt

Every effective prompt for this codebase has four parts:

1. **Anchor** — name the map section + the files, so no rediscovery: *"Blindspot map §2.1-2.2, `HasAiChat.php:283-836`."*
2. **Decision** — state the D-number ruling so Claude doesn't re-ask: *"D3 = yes, one PR."*
3. **Scope fence** — what NOT to touch: *"Don't touch the Anthropic branch / don't rewrite the classifier / corpus edits need golden-master regen in the same PR."*
4. **Verification gate** — what GREEN means: *"Pest suite + a live browser turn on web AND /m that reproduces the old failure and shows the fix + wire-level SSE capture."*

Rule of thumb: **one cluster per prompt.** The map's clusters (§1-§8) are sized to be one branch/PR each. Bundling clusters mixes verification gates and makes review impossible.

## 2. Ready-to-use prompts per cluster

### P0 — the repetition family
> Read `July/July7Updates/fyn-ai-blindspot-map.md` §0-§2. Decisions: D1=[primary-only / goals-carve], D2=[yes/no], D3=yes, D5=[yes/no].
> Branch off dev. Fix, in one PR: (a) the GOALS_PROGRESS pattern / KYC module scope per D1; (b) the navigate_to_page contradiction (either stop instructing it in advice mode or allow a read-only navigation affordance — propose, don't invent); (c) xAI per-iteration history rebuild to match the Anthropic branch (`HasAiChat.php:556-558`); (d) identical tool-call dedupe in the loop; (e) repetition collapse in `StructuredResponseValidator::sanitise`; (f) if D2=yes, flip a1/a2 active and regen the PromptOverlay golden masters in the same PR.
> GREEN = new Pest tests for each guard + the julycsj3 repro question ("Am I on track for retirement?", goal-less user) live on csjones returning ONE clean gate-refusal-or-answer on web AND /m + a graded eval scenario added under 09-canonical.

### Gate routes + completeness truth
> Map §1.3-1.4, decision D6=[canonical route list], D8=[yes/no]. Align every DataReadiness form_link and KYC route to real router paths, define the /m equivalents, extend `assessAll` to 7 modules (kill the fabricated "Goals 100%"). GREEN = a Pest test asserting every gate route resolves in web router AND /m router + buildCompletenessContext snapshot test.

### #615 follow-up (state hygiene)
> Map §4.1 + §3.5, decision D9. Null active_campaign/fyn_path/selection/paused_at_step in the three OnboardingService completion methods (mirror `emitDoneTurn`); add the fyn_step term to /m `onboardingActive`. GREEN = extend `WizardCompletionTest` + live /m check that a wizard-finisher with a stale campaign sees NO onboarding chrome.

### /m SSE parity
> Map §3.1-3.4, decision D10/D11. Wire /m handlers for token_limit/consent_required/handoff_error/entity_created/capture_complete (+ web level_up if ruled in). Add deterministic failure surfacing to handleInlineCapture per D11. GREEN = wire-level SSE fixture tests + live browser: force each event (tinker cache lock for queue, token-budget exhaustion, malformed handoff) and see the specific message on /m.

### Cache truth
> Map §5.1, §5.3-5.6, decision D13. Add investment_analysis_ to CacheInvalidationService; decide prompt-cache invalidation on UI writes; fix the observer no-op keys (§5.4); fix the TTL docblock lie. GREEN = Pest: Fyn-created investment account → get_module_analysis(investment) reflects it same-turn; UI salary edit → next Fyn turn quotes the new figure.

### Retirement engine canon
> Map §5.2, decision D12. [State which engine wins per surface.] Then make every surface read the ruled engine (or label the difference). GREEN = same user shows the same £ figure on web dashboard card, web /retirement, /m card, and in a Fyn answer — verified live with a seeded user whose DB/DC mix previously diverged.

### Compliance backstops
> Map §7.1-7.3, decision D14. Implement the ruled backstops only (e.g. append adviser line server-side when absent; product-name detector log→block per ruling; violations admin queue). Do NOT invent enforcement beyond the ruling. GREEN = Pest per rule + eval scenarios in 07-regulatory (fill the empty category as part of this PR).

### Erasure composition
> Map §7.4-7.6, decision D15. This is a data-destruction path — plan mode first, migration-free, dry-run flags mandatory, never run against local dev data without reseeding after. GREEN = per-store erasure matrix test.

### Corpus/eval integrity
> Map §8.2-8.4, decisions D4, D17. Re-record cassettes under the ruled model, unskip provenance, add content-level corpus parity (params/defaults/required), route onboarding tools to .xai.md under xAI. GREEN = provenance test unskipped and green + a parity test that fails on the current current_account divergence before the fix.

## 3. Verification gates to demand by name

- **"Loop until correct per the map section"** — invokes Rule 14 against a specific §.
- **"Wire-level SSE capture"** — curl the messages endpoint with a token, assert event-type histogram (this is how the /m advice state was proven without a UI build).
- **"Same user, same answer, web AND /m"** — the parity gate; requires csjones deploy or local server-swap.
- **"Golden-master regen in the same PR"** — any corpus/prompt byte change; never let snapshots drift a PR behind.
- **"Graded eval scenario added"** — for any model-behaviour fix; the fix isn't done until the failure class has a scenario that would have caught it.
- **"Reproduce first"** — for any model-misbehaviour report: capture the stored ai_message + metadata + classification BEFORE changing code (the ×80 diagnosis worked because the evidence was in ai_messages.metadata).

## 4. Landmines to name in prompts (so Claude doesn't trip them)

- `FYN_PROMPT_ARCH=legacy` is rollback-only and breaks advice→capture (memory: legacy refuses the write journey).
- grok-4-1-fast is a deliberate unit-economics choice — lift quality via prompts/evals, never "upgrade the model" as a fix (pending D4 on naming).
- The savetax verify sequence and synthesis-mirrors-dashboard are CANONICAL — don't "fix" them.
- Gamification level/percentile fields are approved (Rule 12 carve-out) — never strip them; only financial-quality scores are banned.
- Emoji/icons: banned in all Fyn output — any new SSE/UI work must not introduce them.
- Corpus `.xai.md` descriptions drive model defaults — schema edits need golden-master regen + live-model spot check.
- Never `optimize`/`route:cache` on servers; csjones deploys via git pull + local build.sh; /m verifies per the verify-m skill.
- Preview users: any new Fyn write path must respect PreviewWriteInterceptor.

## 5. Standing session pattern for this programme

1. Start session → point at the map: *"We're working the Fyn blindspot map, cluster X, decisions D-n=…"*
2. One cluster = one branch = one PR to dev; lean cadence (Rule 17) applies BETWEEN clusters, full loop-until-correct WITHIN one.
3. After each cluster ships: tick it off in the map file (edit in place, date-stamped) so the map stays the single source of truth.
4. Re-run the relevant blindspot agent (prompt in map §source) after big refactors — the map decays as the code moves.
