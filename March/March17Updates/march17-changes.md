# March 17 Changes — Life Stage Journey System

**Date:** 2026-03-17
**Branch:** `feature/life-stage-journey`
**Commits:** 28

---

## Summary

Implemented the Life Stage Journey system — the entire Fynla UX now adapts based on 5 UK financial planning life stages. This was a full-day effort covering brainstorming, design specification, implementation planning, and execution with iterative testing and fixes.

---

## What Was Built

### 1. Life Stage Configuration (`lifeStageConfig.js` — 922 lines)

Central configuration for all 5 stages:
- **Starting Out** (university, 18-25, violet) — 6 onboarding steps
- **Building Foundations** (early career, 23-35, spring) — 7 steps
- **Protecting What Matters** (mid-career, 31-50, raspberry) — 8 steps
- **Planning Your Future** (peak, 46-65, light-blue) — 7 steps
- **Enjoying Your Wealth** (retirement, 65+, horizon) — 6 steps

Each stage defines: sidebar items, dashboard cards, onboarding steps, learning milestones (educational content per step), suggested goals, and form field visibility.

### 2. Backend

- `LifeStageService.php` — stage management, data completeness checks using same DB queries as PrerequisiteGateService and DataReadiness services, stage transition suggestions
- `LifeStageController.php` — API endpoints for progress, set stage, complete step
- `AuthController.php` — now returns `data_completed_steps` in the auth user response so frontend has accurate progress immediately
- Database migration adding `life_stage` and `life_stage_completed_steps` to users table
- 4 feature tests passing

### 3. Adaptive Sidebar

- Stage badge and journey progress bar below logo (expanded mode)
- SVG circular progress ring around favicon (collapsed mode)
- Primary sidebar items driven by stage config
- "Explore more" section — collapsed mode shows inline icon toggle (click dots to show/hide)
- Dynamic module promotion — if user adds data for an "explore" module, it moves to primary
- Stage-colour active states (violet/spring/raspberry/light-blue/horizon)
- Falls back to full legacy layout when no stage is set

### 4. Onboarding Flow

- **Welcome screen** — "Where are you in your financial journey?" with 5 stage cards + 4 focus area shortcuts
- **Journey map** — inline SVG meandering path matching approved v6 mockup:
  - 6 steps: exact v6 coordinates (viewBox 0 0 900 540)
  - 7 steps: extends DOWN-LEFT from node 6, no path crossing
  - 8 steps: extends DOWN-LEFT then curves back UP-LEFT, smooth beziers
  - Stage-coloured hero header, gradient path, decreasing opacity nodes, green destination flag
  - Node labels with 28px gap, positioned opposite to path direction
  - Tap nodes to see learning milestone detail
- **Two-column step layout** — unified form (left) + LearningMilestoneSidebar (right)
- **Unified forms** — same form in onboarding and module pages, `context="onboarding"` prop controls field visibility
- **Stage-adaptive fields** — students skip address/occupation; all others see address
- **Learning content** — "Did you know?", "Why we ask this", "How this fits your journey" — all specific to the actual fields shown on each stage's form

### 5. Dashboard

- **JourneyProgressHero** — greeting, stage label, progress %, next step CTA with educational context
- **Progress calculation** — uses backend DataReadiness checks (same as agents), delivered via auth response, no broken frontend guessing
- **Stage-curated cards** — only relevant module cards shown per stage
- **Goals projection chart** spanning 2 columns + suggested goals card in 3rd column
- **Life Timeline** horizontal spanning 3 columns
- **Removed** Cross-Module Insights section

### 6. Preview Mode & Landing Page

- Landing page "Find Your Stage" section with 5 stage cards
- Preview mode auto-sets life stage from persona
- PersonaSelector groups personas by stage
- Preview banner colours fixed — all personas match their banner colour
- Widow persona removed from all systems

### 7. Persona Changes

- **Removed:** Margaret Thompson (widow)
- **Amended:** John Morgan aged to 28, income £38k, expanded savings/pension data
- **Added** `life_stage` field to all 6 remaining persona JSON files
- Updated PreviewUserSeeder, PreviewController, ResetPreviewData command

---

## Known Remaining Items

- Vue template warnings (5) from inline icon components — cosmetic, no functional impact
- Onboarding step 2+ forms need browser testing (only step 1 About You tested)
- Mobile onboarding flow not tested
- Other personas (peak_earners, retired_couple) dashboard not tested
- Form Cancel/Save buttons still show for non-PersonalInfo forms in onboarding context

---

## Documents Created

| Document | Purpose |
|----------|---------|
| `life-stage-journey-design.md` | Full design specification (14 sections) |
| `life-stage-implementation-plan.md` | 6-phase implementation plan |
| `deployOnboard.md` | Deployment guide with file list and SSH commands |
| `march17-changes.md` | This file |
| `journey-map-fix-notes.md` | Working notes for journey map coordinate system |
