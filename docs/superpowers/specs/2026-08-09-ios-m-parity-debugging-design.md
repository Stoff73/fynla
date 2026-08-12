# Fynla iOS and `/m` Parity Debugging Design

**Status:** Approved

**Date:** 2026-08-09

**Baseline:** `dev` at `3a5a329` or later

**Delivery:** Six phased pull requests; each phase includes Laravel contracts, `/m`, native iOS, tests, and acceptance evidence

## 1. Purpose

This programme fixes the reported iOS and `/m` defects as one cross-client parity effort. The objective is not pixel-for-pixel sameness. Laravel remains the authority for financial facts, calculations, entitlements, navigation intent, and Fyn context; each client renders that shared meaning using platform-appropriate UI.

The work must leave the existing desktop application and deterministic onboarding flow intact while removing broken links, duplicate editing paths, stale Fyn conversations, divergent calculations, and incomplete mobile details.

## 2. Outcomes

The programme is successful when:

1. Every supported iOS destination has a working `/m` equivalent and vice versa, except explicitly platform-specific purchase execution.
2. Dashboard actions, recommendations, Net Worth links, settings links, and upgrade gates resolve to the intended semantic destination.
3. Product overview screens lead to canonical detail screens; entity editing occurs from the detail screen through a fresh contextual Fyn conversation.
4. Fyn receives existing financial facts only after server-side authentication, ownership checks, and canonical-data rehydration.
5. ISA allowances, protection gaps, portfolio exposure/drift, retirement projections, and Net Worth projections use shared server calculations.
6. Recorded balance history is never mixed with projected Net Worth.
7. All in-scope automated tests and user-style iOS and `/m` journeys are green.

## 3. Non-goals

- Replacing the existing desktop admin application.
- Replacing StoreKit on iOS or the existing web/Revolut payment execution on `/m`.
- Rebuilding Fyn's validated capture/write engine.
- Replacing deterministic onboarding with advice chat.
- Fabricating market history, fund look-through data, or performance when the canonical source is unavailable.
- Deleting valid records that already exceed a freemium limit.
- Writing projected values into recorded balance history.

## 4. Architectural invariants

### 4.1 Laravel is authoritative

Laravel owns:

- authenticated identity and household relationships;
- persisted financial facts and ownership;
- plan and feature entitlements;
- financial calculations and their assumptions;
- semantic navigation targets returned by actions and recommendations;
- Fyn conversation mode, resource context, and provenance;
- validation and writes.

iOS and `/m` may format and present data, but must not independently implement financial formulae or infer entitlements.

### 4.2 Semantic destinations replace fragile route strings

Server-generated actions use an allowlisted destination contract rather than client-specific paths:

```json
{
  "screen": "pension_detail",
  "params": { "pension_id": 8472 },
  "fallback": "retirement_overview"
}
```

Each client maps the semantic screen to its local router. Resource identifiers are validated by the destination endpoint. Unknown destinations fail safely to the supplied canonical overview and emit diagnostics; they do not default to the tax plan.

The registry covers primary modules, entity details, settings, subscription comparison, personal information, conversation history, holistic plan, achievements, missing-information actions, and contextual Fyn tasks.

### 4.3 Canonical presentation contracts

Server view models provide consistent field meaning, calculation metadata, ownership, dates, empty-state reasons, and relevant actions. Native iOS and `/m` render separate components against the same payload. Existing endpoints and services are extended where practical instead of creating duplicate financial stores.

### 4.4 Trusted and proposed financial facts

Clients may identify the action, resource, and navigation context, but must not supply financial values as authoritative context. All existing financial facts used by Fyn are rehydrated server-side from canonical records. User-supplied changes are treated as proposed facts until processed through the validated capture/write workflow.

This establishes the provenance rule:

```text
database -> trusted fact -> Fyn
user/chat/client -> proposed fact -> validation/write -> database -> trusted fact
```

## 5. Shared navigation and account surfaces

The primary navigation model includes:

- Dashboard
- Bank Accounts
- Protection
- Retirement
- Investments
- Estate Planning
- Goals
- Net Worth
- Holistic Plan
- Achievements
- Conversation History
- Personal Information
- Subscription
- Settings
- Admin, when authorised

“Savings” becomes “Bank Accounts” in primary navigation and matching headings. “Chattels” becomes “Valuables” throughout relevant mobile and shared server presentation copy.

### 5.1 Personal Information

Personal Information is a read-only canonical profile backed by the existing profile authority. It includes appropriate user, household, domicile, income/expenditure summary, and financial-position data without introducing duplicate editable forms. Its Edit details action creates a fresh contextual Fyn conversation.

### 5.2 Subscription and upgrade gates

Both clients display the same server-owned Free/Premium comparison, inclusions, current entitlement, and upgrade eligibility. Purchase execution remains platform-specific:

- iOS: StoreKit.
- `/m`: existing web/Revolut flow.

The server owns plan identity and feature inclusions. The active commerce provider remains authoritative for purchasable product availability, localised price, and currency; each provider product must map back to the same server plan identifier.

An upgrade gate opens the comparison directly and carries the blocked feature as context. It must not merely route to a generic settings landing page.

### 5.3 Settings and data preferences

Settings controls read persisted server state. A preference changes visually only after a successful response; on failure, the control reverts and shows an actionable error. Help, contact, privacy, and terms use canonical validated URLs. Sensitive data actions retain confirmation and re-authentication safeguards.

iOS presents external help/legal content in an in-app Safari sheet. `/m` uses normal mobile-web navigation.

### 5.4 Admin handoff

Admin access reuses the existing admin application through a short-lived, single-use, authenticated handoff. The token is scoped to the handoff, expires quickly, is consumed once, and cannot be reused as a general API bearer token.

- iOS opens the handoff in an in-app Safari sheet.
- `/m` opens the existing admin application in the parent browsing context.

Long-lived bearer tokens must not be copied into browser storage for this flow.

## 6. Contextual Fyn conversations

### 6.1 Creation contract

Every Add or Edit entry point creates a new contextual conversation using:

```json
{
  "action": "edit",
  "resource_type": "pension",
  "resource_id": 8472,
  "current_destination": {
    "screen": "pension_detail",
    "params": { "pension_id": 8472 }
  },
  "origin": {
    "kind": "surface_action",
    "recommendation_id": null
  }
}
```

The server must:

1. authenticate the user;
2. allowlist the action and resource type;
3. verify entity ownership, including validated household relationships;
4. reload current data from canonical records;
5. assemble relevant user, household, product, memory, and knowledge context;
6. create a separate conversation with typed metadata;
7. return a personalised opening message and conversation identifier.

The client-supplied route and identifier select context; they do not establish facts.

### 6.2 Conversation-mode isolation

Surface-action conversations and onboarding conversations are independent. A surface-action conversation routes through contextual advice/capture even while global onboarding is active. Returning to onboarding resumes the original deterministic onboarding conversation unchanged.

The existing validated capture/write workflow remains the only mutation engine. Advice may delegate a proposed change to that workflow; it may not write directly from unvalidated chat text.

### 6.3 Conversation History

Conversation History shows onboarding and contextual conversations separately, including title, purpose, related entity, status, created/updated times, and last-message summary where appropriate. A deleted or inaccessible related entity produces a safe explanation and route back to the canonical overview rather than a generic or stale chat.

## 7. Canonical product interaction model

### 7.1 Overview and detail rules

- Remove module-wide Edit details actions from product overview screens.
- Provide one contextual Add action at the top of an overview when creation is valid.
- Product cards open the canonical entity detail.
- Entity-specific Edit appears only on the detail screen and opens a fresh contextual Fyn conversation.
- Related entities reuse their canonical detail rather than rendering a second implementation.

### 7.2 Dashboard and recommendations

Dashboard actions and recommendations show their full title and explanation without one-line truncation or ellipsis. Each action carries a semantic destination tied to the referenced resource or missing-information task. Invalid or stale targets use a typed fallback; no target silently falls back to Tax Plan.

### 7.3 Goals

Goals has one Add goal action at the top. Cards contain no embedded Add/Edit links and open a canonical detail showing:

- purpose and user-provided rationale;
- target amount and target date;
- creation date and relevant milestones;
- current value and contribution progress;
- contribution information and status;
- related actions.

Editing exists only on the detail screen through a new contextual Fyn conversation.

### 7.4 Properties, mortgages, and liabilities

Properties receive canonical detail screens. Property cards show the outstanding mortgage amount in smaller red text when applicable. A linked mortgage opens the canonical mortgage detail, which is reused from the liabilities context. Other liabilities receive their own detail screens with ownership, balance, rate, repayment, term, and relevant metadata where available.

### 7.5 Income and expenditure

Income cards open canonical details showing source, amount, frequency, ownership, and relevant tax position.

Expenditure clearly distinguishes summary/category estimates from detailed entries. The displayed total must reconcile with the active mode. When only summary data exists, the UI explains the limitation and offers a contextual Fyn action to capture detailed expenditure for better insights.

### 7.6 Net Worth linking and editing

Net Worth uses the same canonical property, account, investment, pension, valuable, and liability destinations used by primary navigation. Its heading matches the other modules. Overview-level Edit is removed; additions occur at the appropriate module overview and entity edits occur from canonical details.

### 7.7 Holistic Plan

Holistic Plan either returns the user's real plan or an explicit typed subscription gate. Loading has a bounded failure state with retry; it cannot remain indefinitely unresolved.

## 8. Canonical financial calculations

### 8.1 Protection gaps

Protection gap cards open a personalised explanation containing calculated need, existing cover, shortfall/surplus, material inputs, assumptions, calculation time, and related policies. All clients consume the same server result.

### 8.2 ISA allowance

Current-tax-year ISA contributions are aggregated across Cash ISAs and Stocks & Shares ISAs using canonical tax-year boundaries and eligible contribution records. Account details show:

- correct ISA type;
- owner, including validated linked household members;
- current-tax-year contributions;
- prior-year contributions where available;
- contribution source/effective date.

The allowance card opens an account-by-account breakdown. The breakdown sum must equal the overview contribution total exactly. Transfers and withdrawals follow the canonical UK allowance treatment already encoded by the domain service; display logic must not reinterpret them.

### 8.3 Shared portfolio allocation and drift engine

One server-side method applies to every investment-bearing wrapper:

- defined-contribution pensions;
- general investment accounts;
- Stocks & Shares ISAs;
- future investment-bearing wrappers.

Tax wrappers change tax and allowance presentation, not the allocation/drift algorithm.

The engine distinguishes:

1. **Current portfolio:** current holdings and their latest canonical values.
2. **Entered portfolio baseline:** the look-through allocation recorded when the user entered or deliberately reset the portfolio, with an effective date and data provenance. It is a frozen exposure vector, not a list of fund labels later reinterpreted using current fund composition.
3. **Recommended allocation:** Fyn's asset-allocation map when available and accessible.

For holding `h` and asset class `a`, absolute look-through exposure is:

```text
absolute_exposure[a] = sum(current_value[h] * underlying_mix[h][a])
whole_portfolio_exposure[a] = absolute_exposure[a] / total_portfolio_value
classified_exposure[a] = absolute_exposure[a] / total_classified_value
coverage = total_classified_value / total_portfolio_value
```

Direct assets use their canonical class at 100%. Mixed funds use their underlying asset mix. A fund held at 40% of the account with 60% equity exposure therefore contributes 24 percentage points of equity exposure to the whole portfolio.

Drift is reported in percentage points:

```text
drift[a] = current_classified_exposure[a] - comparison_exposure[a]
```

The response provides whole-portfolio exposure and coverage-adjusted classified exposure separately. Drift uses the classified exposure only when coverage meets the agreed safe threshold, and the UI labels it with that coverage. The response provides both baseline drift and recommended-allocation drift when both comparisons exist. It includes source, effective date, classified value, unclassified value, and coverage percentage. Unknown look-through exposure remains explicitly unclassified; the engine must not silently classify an entire mixed fund as one class.

The same service and response schema power account detail, wrapper-level summaries, and aggregate portfolio views. Rebalancing guidance must disclose data coverage and is unavailable when coverage is below the agreed safe threshold.

### 8.4 Holdings and performance

Portfolio detail includes holding name, current value, allocation percentage, percentage of the whole relevant portfolio, asset class/exposure, fees where available, performance, and drift. Performance charts use canonical dated history and disclose their methodology and period. Missing history is labelled unavailable rather than inferred or fabricated.

### 8.5 Freemium enforcement

Freemium resource limits are enforced at authoritative server write boundaries across direct forms, APIs, imports, and Fyn capture. Clients may preflight and explain the gate but are not the enforcement boundary. Existing over-limit records remain visible and editable; creation beyond the entitlement is blocked consistently with a direct subscription-comparison action.

## 9. Projection design

### 9.1 Retirement

Individual and aggregate retirement views use the same server projection engine.

- Each pension retains its own withdrawal/commencement age.
- Defined-contribution income uses the configured 4.7% sustainable-withdrawal assumption when that pension becomes available.
- Defined-benefit and State Pension income begin at their own commencement ages.
- The aggregate timeline sums only income available at each age, producing explicit age bands when commencement ages differ.
- The aggregate total must reconcile with individual products for every age.
- The current median-projection card/label is removed. The primary planning projection uses the user's stated assumptions; uncertainty is presented separately and plainly.

Projected values and income carry an asterisk or equivalent disclosure link explaining growth, inflation, fees, contributions, retirement ages, and withdrawal assumptions. Assumptions are server-owned, editable, dated, and consistently applied.

### 9.2 Net Worth

Net Worth has two separate datasets and views:

1. **Recorded balance history:** actual saved snapshots only.
2. **Projected net worth:** a forward estimate beginning with the latest canonical balances.

The forecast applies asset-specific assumptions to property, cash, investments, pensions, valuables, mortgages, and other liabilities. It incorporates known contributions, repayments, and withdrawals where available. Assumptions expose source, effective date, nominal/real basis, and missing-input warnings.

Forecast points are never inserted into recorded balance history. Editing a forecast assumption changes future projections only; editing a real balance occurs through the canonical entity detail and contextual Fyn flow.

## 10. Personalised achievements

Achievements are generated from verified milestone events and current user progress rather than presented as a static catalogue. Each earned badge explains why and when it was earned. In-progress achievements show meaningful progress and an eligible contextual action. Locked or inapplicable achievements must not pretend that progress exists.

Both clients consume the same achievement state, milestone provenance, and next-step contract while rendering it appropriately for the platform.

## 11. Error handling and resilience

Every screen defines loading, empty, stale, unavailable, partial, retry, and unauthorised states.

- A failed secondary calculation does not hide the primary entity detail.
- Ownership and not-found failures return typed safe errors without leaking entity existence.
- Clients clear stale selected routes and return to the canonical overview when appropriate.
- Failed preference writes revert optimistic UI.
- Failed contextual-conversation creation leaves the current screen intact and offers retry.
- Unknown semantic destinations use the explicit fallback and emit structured diagnostics.
- Partial financial analyses disclose missing data and coverage.
- Additive response fields are used where practical so phased rollout does not break older supported clients.

## 12. Security and privacy

- Authorise every resource identifier server-side.
- Load Fyn financial context server-side after authorisation.
- Treat client/chat values as proposed until validated and persisted.
- Do not include unnecessary financial values in navigation payloads or analytics.
- Redact tokens, conversation contents, and sensitive financial fields from routine logs.
- Use single-use, expiring admin handoffs and validate the return target.
- Preserve StoreKit receipt validation and existing web payment security boundaries.
- Maintain audit provenance for AI-proposed and user-confirmed mutations.

## 13. Delivery phases

Cross-phase dependencies must not expose dead controls. For example, PR 1 may ship the read-only Personal Information destination, while its contextual Edit action remains hidden until the PR 2 conversation contract is available. Removal of legacy overview editing occurs only when the replacement contextual/detail path is present.

### PR 1 — Foundations, navigation, settings, admin, subscription, and bug report

- semantic destination registry and client adapters;
- primary navigation parity and naming changes;
- dashboard wrapping and recommendation routing repairs;
- Personal Information;
- canonical subscription comparison and direct upgrade routes;
- settings/help/legal/preferences repairs;
- secure admin handoff;
- reproduce and fix `testNativeBugReportReviewsMetadataBeforeSubmitting`;
- contract and E2E coverage for all above.

### PR 2 — Contextual Fyn and conversation history

- contextual-conversation contract and ownership validation;
- server-side context rehydration and provenance;
- onboarding/surface-action mode isolation;
- fresh Add/Edit conversation entry points;
- Conversation History on both clients;
- capture/write regression coverage.

### PR 3 — Canonical details, navigation reuse, and Net Worth linking

- overview/detail interaction rules;
- goals, properties, mortgages, liabilities, income, and expenditure details;
- Bank Account and Net Worth editing behaviour;
- canonical cross-module links;
- Holistic Plan loading/gate behaviour;
- accessible product-detail headings.

### PR 4 — Financial data parity

- protection-gap explanations;
- ISA ownership, tax-year contribution ledger, and allowance breakdown;
- shared portfolio look-through, baseline, recommendation, and drift engine;
- pension, investment, and Stocks & Shares ISA holdings/performance views;
- authoritative freemium enforcement audit and fixes.

### PR 5 — Projections

- retirement product/aggregate reconciliation and age-banded income;
- 4.7% income and assumption disclosures;
- removal of median presentation;
- recorded versus projected Net Worth separation;
- asset-specific editable Net Worth forecast assumptions.

### PR 6 — Personalised achievements and parity closure

- milestone-backed achievement presentation;
- dynamic progress and contextual next actions;
- full cross-client route, calculation, copy, accessibility, and regression audit;
- closure evidence for every traceability item.

Each PR includes its backend contract, `/m` implementation, iOS implementation, tests, and acceptance evidence. A client-only financial fix is not considered complete.

## 14. Verification strategy

### 14.1 Automated layers

Each phase must include, as applicable:

- Laravel unit and feature tests for policies, contracts, calculations, and write boundaries;
- contract fixtures proving iOS and `/m` consume identical semantic data;
- deterministic calculation fixtures for protection, ISA, portfolio look-through/drift, retirement, and Net Worth;
- `/m` component/integration tests;
- iOS unit and view-model tests;
- XCUITest coverage for critical native journeys;
- layout and accessibility checks for full text, colour contrast, Dynamic Type, and meaningful control labels;
- regression coverage for every reproduced bug.

### 14.2 User-style E2E loop

For every phase:

1. Start from a clean, seeded household representing free, premium, individual, and linked-household conditions as relevant.
2. Exercise the complete iOS workflow in the iOS Simulator using the installed Xcode toolchain.
3. Exercise the equivalent `/m` workflow in the user's installed Google Chrome through the Chrome connector.
4. Record each failure with route, seed/persona, expected result, actual result, screenshot, and relevant application/device logs.
5. Reproduce the failure in isolation and identify the root cause before changing production code.
6. Add or strengthen a regression test that fails for the reproduced defect.
7. Apply the smallest coherent cross-client/server fix.
8. Rerun the isolated test and the complete affected user journey.
9. Continue until automated checks and user-style acceptance journeys are green.

The evidence record distinguishes new defects, existing regressions, environment failures, and genuine external blockers. A PR is not reported as green while an in-scope failure remains unresolved.

Browser automation, screenshots, and visual `/m` acceptance use installed Google Chrome only. Chromium, bundled Playwright Chromium, and the in-app browser are not substitutes. Read-only HTTP checks may support diagnostics but do not replace Chrome acceptance evidence.

### 14.3 Cross-client reconciliation

The same seeded household is checked on iOS and `/m` for:

- navigation destinations;
- balances, ownership, and labels;
- ISA allowance totals and account breakdown;
- protection gaps and assumptions;
- portfolio exposures, coverage, and drift;
- retirement values and age-banded income;
- recorded and projected Net Worth;
- subscription state and gated destinations;
- achievements and contextual actions.

Differences are allowed only for documented platform presentation or payment execution.

## 15. Traceability ledger

| ID | Reported requirement or defect | Delivery |
|---|---|---|
| M-01 | Admin link broken; keep user in app where practical | PR 1 |
| M-02 | Missing Personal Information on iOS and `/m` | PR 1 |
| M-03 | Broken subscription/upgrade links and missing comparison | PR 1 |
| M-04 | Broken help/legal links | PR 1 |
| M-05 | Preferences/data controls wrong or broken | PR 1 |
| M-06 | Dashboard action text cut off | PR 1 |
| M-07 | Recommendations route to real data/product/missing-info targets | PR 1 |
| M-08 | Remove overview Edit; use contextual Add/Edit | PRs 2–3 |
| M-09 | Protection gaps open personalised calculation explanations | PR 4 |
| M-10 | Product-detail headings invisible on light backgrounds | PR 3 |
| M-11 | Add/Edit opens old Fyn conversation | PR 2 |
| M-12 | Retirement holdings, allocation, performance, drift, projections, and income | PRs 4–5 |
| M-13 | Rename Savings to Bank Accounts | PR 1 |
| M-14 | Bank Account overview/detail editing behaviour | PRs 2–3 |
| M-15 | Freemium allows more accounts than entitlement | PR 4 |
| M-16 | ISA type and ownership are incorrect/incomplete | PR 4 |
| M-17 | Current/prior ISA contributions and cross-account totals | PR 4 |
| M-18 | ISA allowance card opens reconciling ISA list | PR 4 |
| M-19 | Goals Add/detail/edit behaviour and richer details | PR 3 |
| M-20 | Holistic Plan indefinite loading or missing gate | PR 3 |
| M-21 | Personalised, dynamic gamification and badges | PR 6 |
| M-22 | Net Worth heading parity | PR 3 |
| M-23 | Recorded history plus asset-specific projected Net Worth | PR 5 |
| M-24 | Net Worth edit behaviour | PRs 2–3 |
| M-25 | Property detail and mortgage amount on card | PR 3 |
| M-26 | Net Worth links reuse canonical sidebar pages | PR 3 |
| M-27 | Rename Chattels to Valuables | PR 1 |
| M-28 | Liability details and correct mortgage reuse | PR 3 |
| M-29 | Income details with tax position | PR 3 |
| M-30 | Expenditure total/mode reconciliation and detail prompt | PR 3 |
| M-31 | Every Fyn action carries screen/entity context and personalisation | PR 2 |
| M-32 | Conversation History in navigation | PR 2 |
| M-33 | Native bug-report metadata review UI test failure | PR 1 |
| M-34 | One allocation/drift method across pensions, investments, and S&S ISAs | PR 4 |

## 16. Phase exit criteria

A phase can be submitted for review only when:

1. every scoped ledger item has code, automated regression coverage, and documented acceptance evidence;
2. relevant Laravel, `/m`, and iOS suites pass from fresh output;
3. the iOS Simulator and installed-Google-Chrome journeys pass using the same seeded scenario;
4. `git diff --check` is clean and unrelated user changes are absent;
5. calculation and route payloads are reconciled across clients;
6. security-sensitive changes have negative-path tests;
7. remaining deferred items are explicitly mapped to a later approved phase.

## 17. Open implementation details to resolve during planning

The implementation plan must identify the exact existing route/controller/service/view-model files to extend and verify schema compatibility before proposing migrations. In particular it must confirm:

- the safest additive location for semantic destination mapping;
- whether existing assumption storage can represent Net Worth asset-specific inputs without a new table;
- the authoritative fund look-through data sources and the minimum safe rebalancing-coverage threshold;
- the stored representation and reset semantics for entered portfolio baselines;
- the exact short-lived admin handoff implementation and return-target allowlist;
- the seeded personas and data required for all cross-client reconciliation fixtures.

These details do not alter the approved architecture. If investigation reveals a material product choice or a destructive migration, implementation pauses for explicit approval.
