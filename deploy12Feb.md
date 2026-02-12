# Deployment Notes - 12 February 2026

## Branch: `gif`

## Changes

| File | Type | Description |
|------|------|-------------|
| `resources/js/views/Public/LandingPage.vue` | Frontend (build) | Added animated dashboard walkthrough GIF section below hero; changed hero stats cards to 2x2 grid layout |
| `resources/js/assets/logoTransparent.png` | Frontend (build) | Removed white background from logo for clean display on dark footer |
| `public/images/fynla-dashboard-walkthrough.gif` | Static asset | 15-second animated GIF showing Mitchell persona dashboard walkthrough |

## Build Required: Yes

The Vue component and logo asset changes require a frontend build.

```bash
./deploy/fynla-org/build.sh
```

## Files to Upload

| File | Destination |
|------|-------------|
| `public/build/` | `~/www/fynla.org/public_html/public/build/` |
| `public/images/fynla-dashboard-walkthrough.gif` | `~/www/fynla.org/public_html/public/images/fynla-dashboard-walkthrough.gif` |

## Post-Upload

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html
php artisan cache:clear && php artisan route:clear && php artisan config:clear
```
