---
name: deploy-checklist
description: Generate deployment checklist with changed files, build instructions, and upload paths for SiteGround. Run before any production deployment.
disable-model-invocation: true
---

# Deploy Checklist

Generate a complete deployment checklist for Fynla production (fynla.org on SiteGround).

## Workflow

### Step 1: Identify Changed Files

Run `git diff --name-only origin/main...HEAD` (or since last deployment tag) to get all changed files.

Categorise them:

| Category | Pattern | Action Required |
|----------|---------|-----------------|
| PHP Backend | `app/**/*.php`, `config/*.php`, `routes/*.php` | Manual upload via SiteGround File Manager |
| Frontend | `resources/js/**`, `resources/css/**` | Requires build then upload `public/build/` |
| Migrations | `database/migrations/*.php` | Upload + run `php artisan migrate` on server |
| Seeders | `database/seeders/*.php` | Upload + run specific seeder command |
| Deploy Config | `deploy/**` | Upload `.htaccess` if changed |
| Composer | `composer.json`, `composer.lock` | Run `composer install` on server (rare) |

### Step 2: Build Frontend (if needed)

If ANY file in `resources/js/` or `resources/css/` changed:

```bash
./deploy/fynla-org/build.sh
```

This creates `public/build/` directory for upload.

**NEVER run `npx vite build` or `npm run build` directly.**

### Step 3: Generate Upload List

For each changed PHP file, map local path to server path:

```
Local: app/Services/Estate/IHTCalculator.php
Server: ~/www/fynla.org/public_html/app/Services/Estate/IHTCalculator.php
```

Base server path: `~/www/fynla.org/public_html/`

If frontend was built:
```
Local: public/build/ (entire directory)
Server: ~/www/fynla.org/public_html/public/build/
```

### Step 4: Generate SSH Commands

After upload, these commands must be run on the server:

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html
```

Always run cache clear:
```bash
php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize
```

If migrations were included:
```bash
php artisan migrate --force
```

If seeders were included (list specific ones):
```bash
php artisan db:seed --class=SpecificSeeder --force
```

### Step 5: Warnings

Flag any of these:
- Migration files present (requires SSH access)
- `.env` or config changes (may need manual server update)
- New composer dependencies (requires `composer install` on server)
- Route changes (must clear route cache)
- `PreviewWriteInterceptor.php` changes (verify excluded routes)

### Output Format

```markdown
## Deployment Checklist - [date]

### Changed Files (X files)

**PHP Files to Upload:**
- [ ] `path/to/file.php` → `~/www/fynla.org/public_html/path/to/file.php`

**Frontend Build Required:** Yes/No
- [ ] Run `./deploy/fynla-org/build.sh`
- [ ] Upload `public/build/` → `~/www/fynla.org/public_html/public/build/`

**Migrations:** Yes/No
- [ ] `database/migrations/xxxx_create_table.php`

### Post-Upload Commands
```bash
[SSH and artisan commands]
```

### Warnings
- [Any warnings from Step 5]
```
