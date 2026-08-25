# Fyn Campaign & Journey Schema — Draft v0.1

> **BRANCH: `feature/fyn-persona-split`.** Builds on the canonical contract in `00-canonical.md`. Does not modify the contract; adds a declarative authoring layer above it.

> **STATUS: DRAFT FOR REVIEW.** Owned by CSJ. Field names and enum values must match the codebase verbatim — corrections welcome before this is promoted from draft.

---

## 1. Purpose

Make campaigns and journey variants **declarative artefacts** so adding a new one is a config-file PR plus a scenario, not a code change in `OnboardingStateMachine`. The agent that helps author these configs (the "Campaign Composer") runs at authoring time only; the runtime executes the YAML deterministically through the existing `OnboardingChatDirector`. No LLM in the capture hot path.

This schema must express:

1. **Where** in the existing onboarding flow the campaign diverges (referenced by an existing `STATE_*` constant).
2. **What** campaign-specific steps follow the divergence (matching the PHP state shape exactly).
3. **What** dashboard the user sees once onboarded.
4. **What** proactive insights fire while they're on this campaign.
5. **How** Fyn Agent (advice-side) biases its recommendations for users on this campaign.
6. **Which** personas / segments the campaign applies to.
7. **What** post-onboarding next steps surface as CTAs.

Two worked examples at the end (`default.yaml`, `save_tax_strategy.yaml`) prove the schema covers the current code state without loss.

---

## 2. Storage and discovery

- One file per campaign at `campaigns/<id>.yaml`. Filename `<id>` must equal `campaign.id` field.
- A reserved `campaigns/default.yaml` describes the base flow for users who don't enter via a campaign.
- A reserved `campaigns/_schema/campaign.schema.json` (JSON Schema 2020-12) is the authoritative validator. CI fails any campaign file that does not validate.
- Loaded at boot by a new `CampaignRegistry` singleton (`app/Services/Campaign/CampaignRegistry.php`). No DB table required for the configs themselves; the registry caches parsed YAML in `Cache::store('config')`.
- A new `users.campaign_id` column (nullable `varchar(64)`, indexed) records which campaign a user is on. `null` = `default`.

Open question (Q1, see §15): should this be a `campaigns` table seeded from the YAMLs at deploy, or pure config-on-disk? Recommendation: pure config-on-disk to keep campaigns versioned with code; `users.campaign_id` is the only DB addition.

---

## 3. Top-level schema

```yaml
campaign:
  id: <string>                  # required, kebab-case, ^[a-z0-9-]{3,64}$
  display_name: <string>        # required, human-readable, used in admin and audit
  description: <string>         # required, internal description for compositors
  status: draft | active | archived   # required
  version: <string>             # required, semver (e.g. "1.0.0")
  owner: <string>               # required, github handle of the human owner
  created_at: <ISO date>        # required
  superseded_by: <campaign id>  # optional, set on archived campaigns

entry:                          # how a user enters this campaign
  triggers: [<trigger>, ...]    # required, at least one
  bubble:                       # optional; presence means "shown at path_choice"
    label: <string>
    description: <string>
    order: <int>                # 0..n, controls bubble position
  diverges_from: <STATE_*>      # required, must exist in OnboardingStateMachine::states()
  resumes_to: <STATE_*>         # optional, where to return if campaign exited mid-way

onboarding:
  steps: [<step>, ...]          # required, at least one; see §4

dashboard:                      # see §6
  cards: [<card-id>, ...]       # required, ordered top→bottom
  hero_metric: <card-id>        # optional; which card gets hero treatment
  hide_modules: [<module>, ...] # optional; modules suppressed entirely
  inserts: [<card-id>, ...]     # optional; campaign-specific cards from inserts catalogue

fyn_agent:                      # see §7
  priority_topics: [<topic>]    # optional, biases planner
  deprioritise: [<topic>]       # optional
  regulatory_disclosures: [<disclosure-id>]  # optional, must reference disclosure registry
  memory_seed: <string>         # optional, initial entry written to fyn_session_memory on enrolment

proactive_insights:             # see §8
  triggers: [<insight-trigger>] # optional

post_onboarding:                # see §9
  terminal_state: <STATE_*>     # optional, defaults to STATE_DONE
  next_steps: [<next-step>]     # optional, surfaced as CTAs on dashboard
  congratulation_text: <string> # optional, shown on completion

constraints:                    # see §10
  personas:
    enabled: [<persona>, ...]   # optional whitelist
    disabled: [<persona>, ...]  # optional blacklist
  segments:
    require: [<segment-id>]     # optional; user must be in all
    forbid: [<segment-id>]      # optional; user must be in none
  exclusive: <bool>             # default true; if true, user can only be on one campaign at a time
  expires_at: <ISO date>        # optional; campaign auto-archives after this
```

All `<...>` placeholders are typed; see §11 for full type definitions.

---

## 4. Step shape

Step fields mirror the PHP state shape in `OnboardingStateMachine::states()` verbatim. The runtime maps the YAML to that array on registry load. **No new turn types are introduced.**

```yaml
- id: <string>                  # required, kebab-case unique within campaign
                                # auto-prefixed with "<campaign_id>:" at load time
                                # to avoid collision with base STATE_* constants
  turn_type: bubbles | free_text | grouped_extract | delegated | terminal   # required

  prompt_text: <string>         # required for non-terminal turns
                                # supports {first_name}, {selection}, {spouse_first_name} tokens
                                # MUST NOT contain icons, emoji, unicode glyphs (rule §14)
                                # MUST spell out acronyms except ISA (rule §10)

  retry_text: <string>          # optional; re-prompt on parse failure (free_text)

  bubbles:                      # required iff turn_type == bubbles
    - id: <string>              # required; user-clickable identifier
      label: <string>           # required; display text, no icons
      description: <string>     # optional; subtitle under label
      next: <step-id | STATE_*> # optional; per-bubble routing (overrides step.next)

  capture_field: <field-path>   # optional; bubbles + free_text only
                                # format: "users.<col>" | "family_members.<col>"
                                #         | "expenditure_profiles.<col>" | "context.<key>"
                                # value resolved from bubble.id (bubbles) or value_parser (free_text)

  value_parser: <parser-name>   # required iff turn_type == free_text
                                # must be a method on OnboardingValueInterpreter
                                # known: parseDateOfBirth, parseRetirementDate,
                                #        parseAnnualIncome, parseMonthlyExpenditure,
                                #        parseLocation, parseFreeFormName

  extraction_tool: <tool-name>  # required iff turn_type == grouped_extract
                                # must exist in AiToolDefinitions::onboardingExtractionTools()

  delegated_tools:              # required iff turn_type == delegated
    - <tool-name>               # filtered tool list passed to CoordinatingAgent::chat()
                                # all must be onboarding-allowed (not in AdviceFyn::WRITE_TOOLS forbid set)

  next: <next-spec>             # required for non-terminal; see §5

  skip_if:                      # optional; skip this step if predicate true
    when: <predicate>           # see §11 predicate type

  skip_link:                    # optional; "skip" affordance shown alongside step
    label: <string>             # e.g. "Skip for now"
    target: <step-id | STATE_*> # where skip jumps to

  layout: standard | review | terminal   # optional, default "standard"
                                # "review" = profile-review styling (Phase 11+)
                                # "terminal" = end-of-flow card

  navigate_to: <route-path>     # optional, terminal turns only
                                # SPA route to redirect to after step yields done
```

### 4.1 Step ID namespacing

Campaign step IDs are prefixed with `<campaign_id>:` at registry load. So a YAML step `id: intro` in `save-tax-strategy.yaml` becomes `save-tax-strategy:intro` in the running state machine. This keeps campaign IDs short and human-readable in YAML while preventing collision with base `STATE_*` constants. The reserved prefix `base:` aliases the existing `STATE_*` constants for cross-references in `next`.

### 4.2 Field rules carried over from CLAUDE.md

These are enforced by the validator, not optional:

- `prompt_text`, `bubble.label`, `bubble.description`, `retry_text`, `next_steps[].label`, `congratulation_text` — must not contain emoji, unicode glyphs, icon font codepoints, banned-acronym strings (rules §10, §14). Validator runs the same regex set used by Pest architecture tests.
- All user-facing strings — must use British spellings (`optimisation` not `optimization`).
- `capture_field` — must reference a column in the writable allowlist (§5.2). Forbidden columns (`password`, `email_verified_at`, `remember_token`, anything in `$hidden` on User model) cannot be captured.
- Disclosure IDs in `fyn_agent.regulatory_disclosures` and trigger references in `proactive_insights` — must resolve at validate time.

---

## 5. Routing (`next`) and persistence

### 5.1 The `next` spec

`next` resolves to a single state ID. Three forms allowed:

**Static** — most common.
```yaml
next: occupational-scheme
# or to base flow:
next: base:STATE_ASSET_CAPTURE
```

**Per-bubble** — declared inline on each bubble; takes precedence over step-level `next`.
```yaml
turn_type: bubbles
bubbles:
  - id: yes
    label: "Continue"
    next: occupational-scheme
  - id: skip
    label: "Skip for now"
    next: base:STATE_ASSET_CAPTURE
```

**Decision** — branching by user state, context, or last bubble.
```yaml
next:
  decision:
    by: <selector>          # bubble.id | context.<key> | user.<col> | family_members.<col>
    cases:
      <value>: <step-id | STATE_*>
    default: <step-id | STATE_*>   # required
```

The validator rejects unreachable steps (no path leads to them) and dead-end non-terminal steps (no path out).

### 5.2 Persistence model

A step writes to the database in exactly one of three ways. The validator rejects steps that mix mechanisms.

| Mechanism | Used by | Effect |
|---|---|---|
| `capture_field: users.<col>` | `bubbles`, `free_text` | `OnboardingChatDirector::persistCapture()` writes the bubble id (bubbles) or parsed value (free_text) directly to the column. |
| `extraction_tool: <name>` | `grouped_extract` | The named tool is called by Claude with the user's free text; the tool handler in `CoordinatingAgent` persists. Director does not persist again. |
| `delegated_tools: [...]` | `delegated` | Multi-turn capture loop with the listed tools. Each tool's handler persists its own writes. |

A step may also declare `context_key: <key>` (alongside `capture_field`) to additionally write the captured value into `users.onboarding_fyn_context` JSON for later reference by `skip_if` or `decision.by` predicates.

### 5.3 Writable column allowlist

The validator references a generated allowlist file `campaigns/_schema/writable_columns.yaml` (regenerated by `php artisan campaigns:refresh-writable-columns`). It enumerates every column on `users`, `family_members`, `expenditure_profiles`, `dc_pensions`, `tax_strategy_household_inputs` that capture flows are permitted to write. Anything outside this list fails validation.

This is how the schema stays honest as the schema migrates. New columns require an explicit allowlist entry, with a one-line annotation on which capture tool or step wrote them.

---

## 6. Dashboard

### 6.1 Card catalogue

Cards are referenced by string IDs that must exist in a registered catalogue (currently hardcoded in `Dashboard.vue`; see Q3 in §15). Schema introduces an explicit `dashboard.cards` catalogue file `campaigns/_schema/card_catalogue.yaml` with one entry per card:

```yaml
- id: net-worth
  component: NetWorthCard
  module: coordination
  available_to_personas: [all]
- id: protection
  component: ProtectionCard
  module: protection
- id: tax-allowances
  component: TaxAllowancesCard
  module: coordination
  required_features: [tax_strategy]   # e.g. only available when feature flag on
```

A campaign's `dashboard.cards` lists card IDs in display order. Cards not in the catalogue fail validation.

### 6.2 Module hide / hero

```yaml
dashboard:
  cards: [tax-allowances, protection, savings, retirement, goals]
  hero_metric: tax-allowances
  hide_modules: [estate]    # entirely suppress estate module nav
  inserts: [partner-broker-cta]   # campaign-specific card
```

`hide_modules` references the seven canonical module IDs: `protection | savings | investment | retirement | estate | goals | coordination`. Hidden modules are removed from the side nav and their cards filtered out of the dashboard. The user can still reach them via Fyn Agent if they ask, unless they're also in `fyn_agent.deprioritise`.

### 6.3 Mobile vs desktop

Mobile (`mobileDashboard.js`) and desktop (`Dashboard.vue`) share the catalogue but may diverge on layout. Schema permits separate sections:

```yaml
dashboard:
  desktop:
    cards: [...]
    hero_metric: ...
    grid_cols: 3
  mobile:
    cards: [...]      # often shorter list
    hero_metrics_visible: true
```

If only the unscoped `dashboard.cards` is present, both surfaces use the same list. Recommended for v1 — keep it simple.

---

## 7. Fyn Agent context

The campaign's runtime contribution to Advice Fyn is *context*, not behaviour. The agent itself stays campaign-agnostic; the planner reads campaign metadata as soft priors.

```yaml
fyn_agent:
  priority_topics: [tax_optimisation, marriage_allowance, isa, pension_input]
  deprioritise: [estate_planning, retirement_drawdown]
  regulatory_disclosures: [tax_guidance_not_advice]
  memory_seed: |
    User entered Fynla via the Tax Strategy campaign on {enrolled_at}.
    Their household calculation mode is {household_calculation_mode}.
    They are exploring spouse-asset-splitting and pension input optimisation.
```

`priority_topics` and `deprioritise` are free-form strings the planner injects into its system prompt as guidance. The list of recognised topics lives in `app/Constants/FynAgentTopics.php` and is validated against. New topics require a one-line PR adding them to the constant.

`regulatory_disclosures` references entries in `app/Constants/RegulatoryDisclosures.php`. Each disclosure has `id`, `text`, `surfaces` (where it must appear: chat header, dashboard footer, post-onboarding modal). The planner / synthesiser are required to include the text where indicated. The critic checks compliance.

`memory_seed` is written to a new `fyn_session_memory` row at campaign enrolment, scoped to topic = the campaign id. Token substitution happens at write time. This is the campaign's voice into long-term memory.

---

## 8. Proactive insights

Two trigger families: **observer-driven** (model save events) and **scheduled** (cron). Both produce a job that runs the agentic loop and writes to `fyn_proactive_insights`.

```yaml
proactive_insights:
  triggers:

    # Observer-driven
    - kind: observer
      model: User                  # must be a registered observer model
      event: saved
      when:                        # optional predicate, evaluated against changes
        column_changed: annual_employment_income
      prompt_template: ftb_income_change_review
      priority: medium             # low | medium | high
      throttle:
        per_user_per: 7d           # max one of this trigger per user per 7 days

    - kind: observer
      model: SavingsAccount
      event: created
      when:
        attr_value: { account_type: lisa }
      prompt_template: ftb_lisa_acknowledgement
      priority: high
      throttle: { per_user_per: 24h }

    # Scheduled
    - kind: scheduled
      cadence: monthly             # daily | weekly | monthly | quarterly
      day_of_month: 1              # required for monthly
      prompt_template: ftb_monthly_review
      priority: low
      throttle: { per_user_per: 30d }

    # Scheduled with predicate
    - kind: scheduled
      cadence: yearly
      anchor: tax_year_start       # tax_year_start | calendar_year_start | user_anniversary
      offset: -14d                 # 14 days before anchor
      prompt_template: tax_year_rollover_review
      priority: high
```

`prompt_template` references a file in `resources/prompts/proactive/<id>.txt`, validated at registry load. Template gets the user, household, and campaign metadata as context.

Each trigger produces an item in `fyn_proactive_insights(user_id, campaign_id, headline, body, priority, dismissed_at, generated_at)`. Frontend reads the table to render dashboard cards, chat openers, and digest emails.

---

## 9. Post-onboarding

```yaml
post_onboarding:
  terminal_state: campaign-terminal
  congratulation_text: |
    That's everything I need for the tax strategy review.
    I'll work through your numbers and surface what I find.
  next_steps:
    - id: review-tax-strategy
      label: "Review your tax strategy"
      target: route:/tax-strategy
      priority: 1
    - id: explore-isa
      label: "Explore ISA options"
      target: route:/savings/isa
      priority: 2
    - id: chat-with-fyn
      label: "Ask Fyn anything"
      target: chat-opener:tax_strategy_intro
      priority: 3
```

`target` formats:

- `route:/<spa-path>` — SPA route push.
- `chat-opener:<id>` — opens chat with a registered opener prompt that primes Fyn Agent.
- `proactive:<insight-id>` — surfaces a specific pre-generated insight.

`priority` is display order, not weight; lower number = higher in the list.

---

## 10. Constraints

```yaml
constraints:
  personas:
    enabled: [peak_earners, entrepreneur, young_family]
    disabled: [student, retired_couple]
  segments:
    require: [has_spouse, household_income_over_100k]
    forbid: [is_preview_only_demo]
  exclusive: true
  expires_at: 2026-12-31T23:59:59Z
```

- `personas` — only meaningful for preview users (`is_preview_user = true`). Real users have no persona; runtime treats `personas` as informational for those.
- `segments` — soft criteria evaluated at enrolment. `CampaignResolver` rejects enrolment if `require` fails or `forbid` matches. Segment IDs come from `app/Constants/UserSegments.php` (registry of evaluators each implementing `bool evaluate(User $user)`).
- `exclusive: true` — user may be on only one campaign at a time. Default. Setting `false` requires explicit conflict-resolution rules in `fyn_agent.priority_topics` (validator warns).
- `expires_at` — campaign auto-archives. Already-enrolled users continue; new enrolments rejected.

---

## 11. Type definitions

| Type | Definition |
|---|---|
| `STATE_*` | An existing constant in `OnboardingStateMachine`. Validator loads `OnboardingStateMachine::states()` array keys and checks. |
| `step-id` | `<campaign_id>:<local-id>` after namespacing. References within a campaign use bare `<local-id>`. |
| `<tool-name>` | Must exist in `AiToolDefinitions::onboardingExtractionTools()` or `AiToolDefinitions::captureCampaignTools()`. |
| `<parser-name>` | Method name on `OnboardingValueInterpreter`. Validator uses reflection. |
| `<field-path>` | `<table>.<column>`. Validated against `campaigns/_schema/writable_columns.yaml`. |
| `<predicate>` | `{ user_field: <col>, op: <eq\|ne\|gt\|lt\|in>, value: <any> }` or `{ context_key: <key>, op: ..., value: ... }` or boolean composition `{ all: [...] }` / `{ any: [...] }` / `{ not: ... }`. |
| `<persona>` | `young_family \| peak_earners \| entrepreneur \| young_saver \| retired_couple \| student`. From `PreviewUserSeeder::PERSONAS`. |
| `<module>` | `protection \| savings \| investment \| retirement \| estate \| goals \| coordination`. |
| `<topic>` | Member of `FynAgentTopics::ALL`. |
| `<disclosure-id>` | Member of `RegulatoryDisclosures::ALL`. |
| `<segment-id>` | Member of `UserSegments::ALL`. |
| `<card-id>` | Entry in `campaigns/_schema/card_catalogue.yaml`. |
| `<trigger>` | One of: `path_choice_bubble`, `utm: <source>`, `intent: <name>`, `manual`, `partner_handoff: <partner>`. |

---

## 12. Validation rules (implemented in `php artisan campaigns:validate`)

Validator runs both at CI time (against all campaign files) and at `CampaignRegistry` boot in non-production environments (silently fails the boot if invalid; production-cached configs are pre-validated).

**Structural**

1. JSON Schema 2020-12 against `campaign.schema.json`.
2. `campaign.id` must equal filename stem.
3. All `step.id` values unique within campaign.
4. All `next` references resolvable to a step in the same campaign or a `STATE_*` in base flow.
5. No unreachable steps. No dead-end non-terminal steps.
6. Per-step persistence mechanism is exactly one of {`capture_field`, `extraction_tool`, `delegated_tools`} (or none, for pure-routing steps).

**Referential**

7. Every `STATE_*` reference exists in `OnboardingStateMachine::states()`.
8. Every `<tool-name>` exists in tool catalogue.
9. Every `<parser-name>` is a public method on `OnboardingValueInterpreter`.
10. Every `<field-path>` is in the writable column allowlist.
11. Every `<topic>`, `<disclosure-id>`, `<segment-id>`, `<persona>`, `<module>`, `<card-id>` exists in its registry.
12. Every `prompt_template` file exists.

**Content**

13. No emoji / unicode glyphs / icon codepoints in any user-facing string. Same regex as `tests/Architecture/NoIconsTest.php`.
14. No banned acronyms in user-facing strings (rule §10), with explicit exception for `ISA`. Validator has acronym dictionary.
15. British spelling check on user-facing strings.
16. No banned colour names anywhere (`amber`, `orange`, etc.).
17. `prompt_text` and `retry_text` token references (`{first_name}` etc.) resolve against a registered token allowlist.

**Semantic**

18. If `bubble.label` is shown at `path_choice`, the bubble's behaviour follows the existing `path_choice` contract (sets `users.onboarding_fyn_path = 'campaign'`, `users.onboarding_fyn_selection = <campaign_id>`).
19. If `constraints.exclusive: false`, validator warns and requires `fyn_agent.priority_topics` to be present (otherwise overlapping campaigns leave the agent ambiguous).
20. `expires_at` must be in the future for `status: active`.
21. `superseded_by` must be a valid campaign id; required for `status: archived` (else warning).

**Browser scenarios**

22. Every `status: active` campaign must have at least one Pest browser scenario at `tests/Browser/scenarios/Campaign-<id>-*.php` that walks the divergence end-to-end. CI fails if missing.

---

## 13. Worked example: `default.yaml`

The reverse-engineered base flow. Proves the schema can express what's already in code. (Companion file written separately at `campaigns/default.yaml`.)

Summary shape:

```yaml
campaign:
  id: default
  display_name: "Standard onboarding"
  description: "Base flow used when user has no campaign assignment."
  status: active
  version: "1.0.0"
  owner: "Stoff73"
  created_at: 2026-04-15T00:00:00Z

entry:
  triggers: [manual]
  diverges_from: base:STATE_PATH_CHOICE   # this IS the base flow

onboarding:
  steps:
    - id: path-choice
      turn_type: bubbles
      prompt_text: "How would you like to start, {first_name}?"
      bubbles:
        - id: journey
          label: "Tell me about your life stage"
          next: base:STATE_JOURNEY_SELECTION
        - id: focus
          label: "Pick a financial topic"
          next: base:STATE_FOCUS_SELECTION
      capture_field: users.onboarding_fyn_path
    # ... rest of states cross-reference base STATE_* and live in PHP for now ...

dashboard:
  cards: [net-worth, protection, cash-savings, investments, retirement, estate, goals, life-timeline]
  hero_metric: net-worth

fyn_agent:
  priority_topics: []
  regulatory_disclosures: [general_guidance_not_advice]

constraints:
  personas: { enabled: [young_family, peak_earners, entrepreneur, young_saver, retired_couple, student] }
  exclusive: true
```

The `default` campaign is special: `diverges_from` points at the very first state, and the bulk of its onboarding is the existing PHP `OnboardingStateMachine` — the YAML is a thin descriptor over what's already coded. This is intentional. Migrating base steps into YAML is a follow-up; v1 only requires the YAML for *new* campaigns to express their divergence and extension.

---

## 14. Worked example: `save-tax-strategy.yaml`

The existing SaveTax campaign reverse-engineered into the schema. Proves the schema covers the most non-trivial real case.

```yaml
campaign:
  id: save-tax-strategy
  display_name: "Tax Strategy"
  description: |
    Optimised household tax planning. Captures spouse and pension data
    needed to surface marriage-allowance, asset-splitting, and
    pension-input recommendations.
  status: active
  version: "1.0.0"
  owner: "Stoff73"
  created_at: 2026-03-01T00:00:00Z

entry:
  triggers:
    - path_choice_bubble
    - utm: savetax-q2-2026
  bubble:
    label: "Optimise my household tax"
    description: "If you want a focused tax-strategy review, start here."
    order: 2
  diverges_from: base:STATE_PROFILE_REVIEW_EXPENDITURE
  resumes_to: base:STATE_ASSET_CAPTURE

onboarding:
  steps:
    - id: intro
      turn_type: bubbles
      prompt_text: |
        Before I dive in, I want to confirm I have the right things to look at.
        Are you happy for me to ask a few extra questions about your spouse and pensions?
      bubbles:
        - id: yes
          label: "Yes, let's start"
          next: occupational-scheme
        - id: skip
          label: "Not right now"
          next: base:STATE_ASSET_CAPTURE
      capture_field: context.tax_strategy_consent

    - id: occupational-scheme
      turn_type: grouped_extract
      prompt_text: |
        Tell me about your workplace pension. Are you in a salary sacrifice
        arrangement? If so, does your employer return any National Insurance saving?
      extraction_tool: capture_salary_sacrifice
      next: isa-holdings
      retry_text: "I didn't catch that — could you tell me whether your pension is salary-sacrifice and whether your employer rebates the National Insurance saving?"

    - id: isa-holdings
      turn_type: free_text
      prompt_text: "What's the total balance across your existing ISAs?"
      value_parser: parseAnnualIncome    # reuses currency parser
      capture_field: users.existing_isa_balance
      next: bank-accounts
      retry_text: "I need a number — roughly what's the total ISA balance?"

    - id: bank-accounts
      turn_type: free_text
      prompt_text: "And your total cash savings outside of ISAs?"
      value_parser: parseAnnualIncome
      capture_field: users.existing_savings_balance
      next: investment-accounts

    - id: investment-accounts
      turn_type: free_text
      prompt_text: "Total balance in General Investment Accounts (excluding ISA and pension)?"
      value_parser: parseAnnualIncome
      capture_field: users.existing_investment_balance
      next: pension-contribs

    - id: pension-contribs
      turn_type: free_text
      prompt_text: "Annual pension input — your contributions plus your employer's, including salary sacrifice?"
      value_parser: parseAnnualIncome
      capture_field: users.current_year_pension_input
      next: pension-history

    - id: pension-history
      turn_type: grouped_extract
      prompt_text: |
        For carry-forward, I need pension input over the last three tax years.
        Tell me each year's total contribution.
      extraction_tool: capture_pension_history
      next:
        decision:
          by: user.marital_status
          cases:
            married: spouse-work
            civil_partnership: spouse-work
          default: charitable-giving

    - id: spouse-work
      turn_type: bubbles
      prompt_text: "Does {spouse_first_name} work?"
      bubbles:
        - id: yes
          label: "Yes"
          next: spouse-household
        - id: no
          label: "No"
          next: spouse-non-working-assets
      extraction_tool: capture_spouse_work_status

    - id: spouse-household
      turn_type: grouped_extract
      prompt_text: |
        Tell me about {spouse_first_name}'s income, ISA balance, savings,
        annual dividends, and pension input.
      extraction_tool: capture_spouse_household_data
      next: charitable-giving

    - id: spouse-non-working-assets
      turn_type: grouped_extract
      prompt_text: |
        What does {spouse_first_name} hold — ISA balance, cash savings,
        investments, dividend holdings, pension balance?
      extraction_tool: capture_spouse_non_working_assets
      next: charitable-giving

    - id: charitable-giving
      turn_type: free_text
      prompt_text: "Roughly how much do you donate to charity each year?"
      value_parser: parseAnnualIncome
      extraction_tool: capture_charitable_giving
      next: terminal

    - id: terminal
      turn_type: terminal
      prompt_text: |
        That's everything for the tax strategy review.
        I'll work through your numbers and surface what I find.
      navigate_to: /tax-strategy
      layout: terminal

dashboard:
  cards: [tax-allowances, retirement, savings, investments, protection, goals, net-worth]
  hero_metric: tax-allowances
  inserts: [marriage-allowance-card, carry-forward-summary-card]
  hide_modules: []

fyn_agent:
  priority_topics:
    - tax_optimisation
    - marriage_allowance
    - pension_input
    - asset_splitting
    - isa
  deprioritise:
    - estate_planning
    - retirement_drawdown
  regulatory_disclosures:
    - tax_guidance_not_advice
    - pension_carry_forward_caveat
  memory_seed: |
    User entered Fynla via the Tax Strategy campaign on {enrolled_at}.
    Household calculation mode: {household_calculation_mode}.
    Spouse status captured: {has_spouse_data}.

proactive_insights:
  triggers:
    - kind: observer
      model: User
      event: saved
      when: { column_changed: annual_employment_income }
      prompt_template: tax_income_change_review
      priority: medium
      throttle: { per_user_per: 30d }

    - kind: scheduled
      cadence: yearly
      anchor: tax_year_start
      offset: -28d
      prompt_template: tax_year_rollover_review
      priority: high

post_onboarding:
  terminal_state: terminal
  congratulation_text: |
    Thanks, {first_name}. I have what I need to look at your household tax position.
  next_steps:
    - id: review
      label: "Review your tax strategy"
      target: route:/tax-strategy
      priority: 1
    - id: marriage-allowance
      label: "See if you qualify for marriage allowance"
      target: route:/tax-strategy/marriage-allowance
      priority: 2
    - id: ask-fyn
      label: "Ask Fyn about your pension carry-forward"
      target: chat-opener:tax_carry_forward_intro
      priority: 3

constraints:
  personas:
    enabled: [peak_earners, entrepreneur, young_family]
    disabled: [student]
  segments:
    require: []
    forbid: []
  exclusive: true
  expires_at: 2027-04-05T23:59:59Z
```

Coverage check against the existing `STATE_CAMPAIGN_*` constants:

| Existing PHP state | YAML step | Match |
|---|---|---|
| `STATE_CAMPAIGN_INTRO` | `intro` | ok |
| `STATE_CAMPAIGN_OCCUPATIONAL_SCHEME` | `occupational-scheme` | ok |
| `STATE_CAMPAIGN_ISA_HOLDINGS` | `isa-holdings` | ok |
| `STATE_CAMPAIGN_BANK_ACCOUNTS` | `bank-accounts` | ok |
| `STATE_CAMPAIGN_INVESTMENT_ACCOUNTS` | `investment-accounts` | ok |
| `STATE_CAMPAIGN_PENSION_CONTRIBS` | `pension-contribs` | ok |
| `STATE_CAMPAIGN_PENSION_HISTORY` | `pension-history` | ok |
| `STATE_CAMPAIGN_CHARITABLE_GIVING` | `charitable-giving` | ok |
| `STATE_CAMPAIGN_SPOUSE_WORK` | `spouse-work` | ok |
| `STATE_CAMPAIGN_SPOUSE_HOUSEHOLD` | `spouse-household` | ok |
| `STATE_CAMPAIGN_SPOUSE_NON_WORKING_ASSETS` | `spouse-non-working-assets` | ok |
| `STATE_CAMPAIGN_TERMINAL` | `terminal` | ok |

All twelve states map. The schema covers the existing campaign without loss. Two PHP-side details I need confirmed during implementation:

- The `isa-holdings`, `bank-accounts`, `investment-accounts`, `pension-contribs` capture columns in the YAML write to `users.existing_*_balance` — confirm column names against the actual migrations during the runtime work; if they live on a separate row (e.g. `tax_strategy_household_inputs`) the `capture_field` becomes `tax_strategy_household_inputs.<col>` and the YAML changes to `extraction_tool` form.
- The `pension-history` step uses `capture_pension_history`, which expects `history[]` of `{tax_year, pension_input_amount}`. Free-text parsing of three years of data may need a richer prompt and retry path; flagged as Q5.

---

## 15. Open questions for review

| Q | Question | Recommendation |
|---|---|---|
| Q1 | Campaigns table or pure config-on-disk? | Pure config-on-disk. `users.campaign_id` is the only DB addition. Versioned with code, no migration per campaign, fast PR cycle. |
| Q2 | Migrate base STATE_* into YAML, or keep them in PHP forever? | Keep in PHP for v1. Migrate iteratively as we touch states. Schema must support cross-references between YAML and PHP states from day one (which it does via `base:STATE_*`). |
| Q3 | Where does the dashboard card catalogue live — code or config? | Config (`campaigns/_schema/card_catalogue.yaml`). Each card maps to a Vue component name. Adding a new card is a YAML PR + one Vue file. |
| Q4 | One YAML per campaign, or one big `campaigns.yaml`? | One per campaign. Cleaner diffs, cleaner ownership, cleaner archival. |
| Q5 | Multi-year free-text capture (pension history) — bubble grid, free text, or structured form? | Out of scope for the schema. Schema permits whichever via `extraction_tool` or `delegated_tools`. Decide per-campaign. |
| Q6 | Should the schema express the **agent eval scenarios** for a campaign, or stay separate? | Separate. `tests/FynAgent/scenarios/<campaign_id>.yaml` is its own file with its own schema (golden conversations, expected tool calls, judge rubrics). Cross-reference by `campaign_id`. |
| Q7 | Versioning of campaigns when a user is mid-flow and the YAML changes? | `users.campaign_version` (snapshot). User finishes on the version they enrolled into. New enrolments use `latest`. Migration of in-flight users is opt-in via admin. |
| Q8 | Personas: enforce as constraint, or treat as informational? | Enforce only for preview users. Real users have `is_preview_user = false` and the persona constraint is informational. `CampaignResolver` ignores it for real users; admin tooling shows it. |
| Q9 | Should `path_choice` bubbles be auto-generated from active campaigns, or hand-curated? | Hand-curated for v1. The marketing surface is too important to auto-generate. Schema `entry.bubble` declares whether a campaign *wants* to appear at `path_choice`; an admin UI selects which to actually show and in what order. |
| Q10 | Regulatory disclosure registry — code, config, or compliance-owned data store? | Code, in `app/Constants/RegulatoryDisclosures.php`. Compliance-owned but reviewed in PR. Schema validator enforces references. |

---

## 16. Implementation order

This schema is the spec, not the implementation. Suggested smallest-useful-slice order:

1. **Land the schema doc.** This file. Review and iterate. No code yet.
2. **Reverse-engineer `default.yaml`** as a forced exercise to expose schema gaps. Not loaded at runtime yet.
3. **Reverse-engineer `save-tax-strategy.yaml`.** Same.
4. **Build `CampaignRegistry` + validator** (`php artisan campaigns:validate`). CI gate. No runtime use yet.
5. **Add `users.campaign_id` migration.** Default `null` = `default` campaign.
6. **Wire `CampaignResolver`** to set `users.campaign_id` from path-choice bubble or UTM. No runtime behaviour change yet.
7. **Wire `OnboardingChatDirector` to read campaign-extension steps** at the divergence point. Existing PHP states still drive base flow. Ship `save-tax-strategy.yaml` as the first runtime config and verify it produces the same behaviour as the current hardcoded campaign.
8. **Wire dashboard layout** — read `dashboard.cards` from registry per `users.campaign_id`. Default if none.
9. **Wire Fyn Agent context.** Planner reads `fyn_agent` block as soft priors.
10. **Wire proactive insights triggers.** Observer + scheduled.
11. **Wire post-onboarding next steps.**
12. **Build the Composer plugin** (cowork-plugin-management:create-cowork-plugin) only after steps 1–11 give it real exemplars to learn from.

Each step is reversible and shippable. Most run behind feature flags.

---

*Draft v0.1 — owned by CSJ. Comments inline or as PR review on the spec PR.*
