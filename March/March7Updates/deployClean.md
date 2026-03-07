# Deploy — March 7 (Investment Cleanup)

**Date:** 7 March 2026

---

## Summary

- **Removed Strategy Card** from Investment detailed account view (`AccountPerformancePanel.vue`)
  - Removed `AccountStrategyCard` component usage, import, and registration
  - Drift analysis and rebalancing data remain intact (used elsewhere in the panel)

---

## Files to Upload

### Frontend Files (included in build — no separate upload needed)

```
resources/js/views/Investment/AccountPerformancePanel.vue
```

---

## Deploy Steps

### 1. Build locally

```bash
./deploy/fynla-org/build.sh
```

### 2. Upload to SiteGround

Upload the built frontend:
```
public/build/ → ~/www/fynla.org/public_html/public/build/
```

### 3. Clear caches via SSH

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html
php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize
```

---

## Verification

After deploy, verify:

1. **Investment module** — Open a detailed account view, confirm the strategy card no longer appears
2. **Drift analysis** — Rebalancing drift display still shows correctly in the performance panel

---

## File Counts

| Category | Count |
|----------|-------|
| Frontend (in build) | 1 |
| **Total files to upload** | **0** (frontend-only, included in build) |
| Build output | `public/build/` |
