# Deployment Notes - February 2, 2026

---

## 1. Retirement Income Planner - Agentic AI Development Note

**Status:** ✅ DEPLOYED

### Description

Added an informational banner to the Retirement Income Planner explaining that the current implementation is scaffolding for future Agentic AI integration. This sets user expectations that the symbolic AI implementation may produce imperfect results until the LLM-based agent is connected.

### Changes

Added a blue info banner at the top of the Retirement Income Planner with the following message:

> The retirement planner you see here is the base scaffolding that will be used by our Agentic AI (not implemented yet) running on a domain-specific, deep knowledge LLM, to provide actionable, deterministic and traceable optimisation strategies for drawdown. So if what you see is not perfect, or does not make sense, this is expected behaviour due to the nature of symbolic AI (which is implemented). Once we connect the agent, the AI will adjust the parameters accordingly.

### Files Changed

```text
resources/js/components/Retirement/RetirementIncomeTab.vue
```

---

## 2. Persona Selection Modal - Register Button Message Update

**Status:** ✅ DEPLOYED

### Description

Updated the message in the persona selection modal (shown when users click "Get Started", "Interactive Demo", or "Try Demo") to encourage users to explore personas before registering.

### Changes

Updated the register section message from:
> "We strongly encourage you to explore the personas above first to see what Fynla can do."

To:
> "We strongly recommend looking through a persona to see the full power of the platform."

### Files Changed

```text
resources/js/components/Preview/PersonaSelectionModal.vue
```

---

## 3. Capital Adequacy Tab - New Feature

**Status:** ✅ DEPLOYED

### Description

Created a new Capital Adequacy Tab accessible from the Capital Adequacy Planner card on the Retirement dashboard. This provides users with a comprehensive view of their pension allowance usage, carry forward availability, and a what-if contribution slider.

### Features

1. **Summary Cards (4 cards)**
   - Required Capital at Retirement
   - Projected Capital at Retirement (color-coded green/red based on status)
   - Annual Allowance Used (current tax year)
   - Carry Forward Available (breakdown by last 3 tax years with total)

2. **Annual Allowance Progress Section**
   - Progress bar showing allowance used vs remaining
   - Breakdown: Remaining Allowance + Carry Forward = Total Available
   - Monthly equivalent display
   - Affordability limitation note (when applicable)

3. **What-If Contribution Slider**
   - Current monthly contribution display
   - Interactive slider to model additional contributions
   - Constrained by minimum of affordability OR remaining allowance
   - Shows which constraint is limiting (affordability or allowance)
   - Impact panel showing:
     - New annual contribution
     - Additional capital at retirement (compound growth calculation)
     - New projected capital
     - Capital gap/surplus

4. **Capital Progress Section**
   - Progress bar showing projected vs required capital
   - Status banner (On Track / Nearly There / Capital Shortfall)

### Technical Details

- Contributions calculation includes both percentage-based (occupational pensions) and flat monthly amounts
- Carry forward assumes same contribution level for previous 3 tax years
- Compound growth calculation matches backend RequiredCapitalCalculator formula
- What-if slider is visualization only (no persistence)

### Files Changed

```text
resources/js/components/Retirement/CapitalAdequacyTab.vue  (NEW)
resources/js/components/NetWorth/PensionList.vue
```

---

## Files Changed Summary (4 files)

### Frontend (4 files - Included in Build)

```text
resources/js/components/Retirement/RetirementIncomeTab.vue      ✅ Deployed
resources/js/components/Preview/PersonaSelectionModal.vue       ✅ Deployed
resources/js/components/Retirement/CapitalAdequacyTab.vue       ✅ Deployed (NEW)
resources/js/components/NetWorth/PensionList.vue                ✅ Deployed
```

---

## Rebuild Required: YES

Frontend Vue components changed. Full rebuild required:

```bash
./deploy/fynla-org/build.sh
```

---

## Upload Checklist

### Step 1: Run Build

```bash
cd /Users/Chris/Desktop/fynla
./deploy/fynla-org/build.sh
```

### Step 2: Upload Built Assets

Upload the entire `public/build/` directory to:

```text
~/www/fynla.org/public_html/public/build/
```

### Step 3: Clear Cache (SSH)

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html
php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear
```

---

## Verification

After deployment, verify:

1. **Agentic AI Info Banner** ✅
   - Navigate to Retirement > Income Planner tab
   - Verify blue informational banner appears at the top (below the header)
   - Verify the message about Agentic AI scaffolding is displayed correctly

2. **Persona Modal Register Message** ✅
   - Click "Get Started" or "Try Demo" on the landing page
   - Verify the message below the persona cards reads: "We strongly recommend looking through a persona to see the full power of the platform."
   - Verify the "Create Your Account" button links to /register

3. **Capital Adequacy Tab** ✅
   - Navigate to Retirement (Pensions tab)
   - Click on the "Capital Adequacy Planner" card
   - Verify the tab opens with back button, summary cards, allowance progress, slider, and capital progress
   - Verify carry forward shows 3 year breakdown with total
   - Verify contributions display correctly (including percentage-based occupational pensions)
   - Move the slider and verify impact calculations update
   - Verify slider constraint note shows whether limited by affordability or allowance

4. **Existing Functionality**
   - Verify all existing features still work

---

## Rollback

If issues occur:

1. Restore previous `public/build/` directory from backup
2. Clear cache:
   ```bash
   php artisan cache:clear && php artisan config:clear && php artisan view:clear
   ```

---
