# Deploy Notes — Logo Update (2026-03-14)

## Summary

Logo resized on public-facing pages (nav: h-20 → h-12 via `.nav-logo` class). Dev environment updated to use Vite port 5174 and Windows MySQL PATH.

## Files to Upload

### PHP Backend
| Local path | Server path |
|------------|-------------|
| `app/Http/Middleware/SecurityHeaders.php` | `~/www/fynla.org/public_html/app/Http/Middleware/SecurityHeaders.php` |

### Frontend (rebuild required first)
Run `./deploy/fynla-org/build.sh` then upload `public/build/` directory.

Changed frontend files:
- `resources/css/app.css` — adds `.nav-logo` global class
- `resources/js/layouts/PublicLayout.vue` — nav logo uses `.nav-logo` instead of inline `h-20 w-auto`

## Local-Only Changes (do NOT upload)
- `dev.sh` — Windows MySQL PATH + Vite port 5174 (local dev only)
- `package-lock.json` — auto-generated

## SSH Commands (post-upload)

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html
php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize
```

## Notes
- No migrations
- No seeders
- No new dependencies
- CSP updated: Vite dev port references changed from 5173 → 5174 (local dev only, no production impact)
