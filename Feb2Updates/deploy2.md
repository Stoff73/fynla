# Deployment Notes - February 2, 2026

**Deployment Status:** Pending

---

## Retirement Income Planner - Agentic AI Development Note

**Branch:** main

**Status:** Ready for deployment

### Description

Added an informational banner to the Retirement Income Planner explaining that the current implementation is scaffolding for future Agentic AI integration. This sets user expectations that the symbolic AI implementation may produce imperfect results until the LLM-based agent is connected.

### Changes

#### New Informational Banner

Added a blue info banner at the top of the Retirement Income Planner with the following message:

> The retirement planner you see here is the base scaffolding that will be used by our Agentic AI (not implemented yet) running on a domain-specific, deep knowledge LLM, to provide actionable, deterministic and traceable optimisation strategies for drawdown. So if what you see is not perfect, or does not make sense, this is expected behaviour due to the nature of symbolic AI (which is implemented). Once we connect the agent, the AI will adjust the parameters accordingly.

The banner uses the existing `.info-banner` styling (blue background, blue border) consistent with other informational messages in the component.

---

## Files Changed (1 file)

### Frontend (1 file - Included in Build)

```text
resources/js/components/Retirement/RetirementIncomeTab.vue
```

---

## Rebuild Required: YES

Frontend Vue component changed. Full rebuild required:

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

1. **Info Banner Display**
   - Navigate to Retirement > Income Planner tab
   - Verify blue informational banner appears at the top (below the header)
   - Verify the message about Agentic AI scaffolding is displayed correctly

2. **Existing Functionality**
   - Verify all existing features still work (summary cards, income sources, toggles, charts)

---

## Rollback

If issues occur:

1. Restore previous `public/build/` directory from backup
2. Clear cache:
   ```bash
   php artisan cache:clear && php artisan config:clear && php artisan view:clear
   ```

---
