# Decision Engine Upgrade: Understanding of Requirements

**Date:** 2026-03-14
**Status:** Pre-Research Phase

---

## What You Are Asking For

You want to upgrade the entire decision engine across Fynla so that every module has a comprehensive, structured decision tree — similar to the investment module's existing `investment-decision-tree.md` document. This is a system-wide upgrade, not a tweak.

---

## The Five Modules That Need Decision Trees

1. **Protection** — Life cover, critical illness, income protection, family income benefit, private medical, relevant life policies
2. **Investment** — Already has a detailed decision tree (the reference document). Needs review for completeness but serves as the template for the others
3. **Retirement** — Workplace pensions, SIPPs, DB pensions, State Pension, drawdown vs annuity, tax-free lump sums, decumulation
4. **Cash (Savings)** — Emergency funds, savings accounts, Cash ISAs, fixed-term deposits, NS&I, interest rate optimisation
5. **Estate Planning** — IHT liability, nil-rate bands, trusts, gifting, wills, powers of attorney, business relief, agricultural relief

---

## How the Decision Engine Works (The Flow)

```
1. User enters their information
   ↓
2. Data is stored in the database
   ↓
3. The module Agent reads the user's data from the database
   ↓
4. The Agent applies the data against the module's decision tree
   ↓
5. Two possible outcomes from the tree:

   5a. SUFFICIENT DATA → Generate decisions/outcomes
       - Display each outcome with:
         - The decision itself (what the user should do)
         - The thinking (which data points were used, which branches were taken)
         - The user's actual values that drove the decision
         - The full decision path (like breadcrumbs through the tree)

   5b. INSUFFICIENT DATA → Notify the user
       - Surface through the "What Drives This" view
       - Show WHAT information is missing
       - Show WHY it is needed (what decisions it unlocks)
       - Make the card clickable → links directly to where the user enters that data
       - This gives the user insight into why the information matters
```

---

## Key Design Principles I Understand

### 1. Cascading Waterfall, Not Either/Or
Each module produces **multiple** decision outcomes for a single user. It is not "you need A or B" — it is "based on your information, here are all the decisions that apply to you, in priority order." Like the investment tree's 9-step waterfall + transfer scans + spouse optimisation, each module should have a cascading set of analyses that all run and produce their own outcomes.

### 2. Show Your Working
Every outcome must be traceable. The user should see:
- "We used your retirement age (67), your current pension pots (£X in workplace, £Y in SIPP), and your employment status (employed)"
- "We checked: Do you have an employer matching your pension? → Yes, 5% match"
- "We checked: Are you maximising the match? → No, you contribute 3%"
- "Outcome: Increase your workplace pension contribution to at least 5% to capture the full employer match"

### 3. Data Readiness Gate Per Module
Each module has its own set of required and optional data. If required data is missing, the engine cannot proceed (block). If optional data is missing, the engine can proceed but flags what would improve the analysis (warn/info).

### 4. What Drives This = Missing Data Notifications
The "What Drives This" view becomes the bridge between incomplete data and completed analysis. Each card in this view:
- States what information is missing
- Explains what analysis it would unlock
- Links directly to the input form for that data
- Gives the user a reason to complete their profile

---

## The Research Phase (What We Need First)

Before writing any code or planning any implementation, we need to research and document **for each of the five modules**:

### A. User Information Required
- What data fields does the engine need from the user?
- Which are mandatory (blocks) vs optional (improves analysis)?
- Where does each data field come from in the current database schema?

### B. Analysis / Decision Logic
- What analyses need to be performed on the user's data?
- What are all the decision branches?
- What UK financial planning rules, regulations, and best practices drive each decision?
- What thresholds and constants are used?

### C. Outcomes
- What are all the possible outcomes/recommendations?
- What triggers each outcome?
- What is the priority ordering?
- What user-facing messages should be shown?
- What "thinking" / decision path should be exposed?

---

## How This Relates to the Existing Investment Tree

The investment decision tree (`investmentTree/investment-decision-tree.md`) is the **gold standard template**. It covers:

- **Phase 1:** Data readiness gate (6 sequential checks)
- **Phase 2a:** Life event assessment (17 event types with modifiers)
- **Phase 2b:** Goal assessment (6 goal types with wrapper mapping)
- **Phase 3:** Safety checks (debt, emergency fund, protection gaps)
- **Phase 4:** Contribution waterfall (9 steps in strict priority order)
- **Phase 5:** Transfer scans (7 independent scans)
- **Phase 6:** Spouse optimisation (6 strategies)
- **Phase 7:** Conflict resolution (6 conflict types)

Each of the other four modules needs a document of equivalent depth and comprehensiveness, tailored to its own domain.

---

## What Happens After Research

Once we have the research documents for all five modules, we move to planning:
1. Compare research findings against existing codebase implementation
2. Identify gaps (decisions that should exist but don't)
3. Design the decision tree structure for each module
4. Plan the implementation (services, database changes, API changes, UI changes)
5. Build incrementally, module by module

---

## Summary

This is a significant, multi-phase upgrade. The first phase is **research only** — no code, no implementation. We need to understand every possible financial planning decision across all five UK financial planning domains, document the user data that drives each decision, and map out the complete decision trees before touching any code.
