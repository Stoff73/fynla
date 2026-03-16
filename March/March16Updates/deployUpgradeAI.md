# Deployment Guide: AI Agent Upgrade

**Branch:** `aiUpgrade`
**Rebuild required:** YES

---

## Step 1: Build locally

Run these two commands on your Mac before uploading anything:

```bash
composer install --ignore-platform-req=php
./deploy/fynla-org/build.sh
```

This installs the new `anthropic-ai/sdk` package and rebuilds the frontend.

---

## Step 2: Generate an internal agent token

Run this locally and copy the output:

```bash
php -r "echo bin2hex(random_bytes(32)) . PHP_EOL;"
```

You will need this value in Step 4.

---

## Step 3: Upload files via SiteGround File Manager

All paths are relative to `~/www/fynla.org/public_html/`.

### 3a. Upload new files (create these on server)

```
app/Services/PrerequisiteGateService.php
app/Traits/HasAiChat.php
app/Traits/HasAiGuardrails.php
app/Http/Controllers/Api/AgentInternalController.php
app/Http/Middleware/AgentTokenAuth.php
app/Services/PythonAgentBridge.php
```

### 3b. Overwrite existing files

```
app/Agents/CoordinatingAgent.php
app/Http/Controllers/Api/AiChatController.php
app/Http/Kernel.php
app/Providers/AppServiceProvider.php
app/Services/AI/AiToolDefinitions.php
config/services.php
routes/api.php
composer.json
composer.lock
```

### 3c. Upload the frontend build

Upload the entire `public/build/` directory, overwriting the existing one.

### 3d. Delete these 7 files from the server

```
app/Services/AI/AiChatService.php
app/Services/AI/AiContextBuilder.php
app/Services/AI/AiIntentMatcher.php
app/Services/AI/AiModelResolver.php
app/Services/AI/AiSimulatedResponseBuilder.php
app/Services/AI/AiSimulatedService.php
app/Services/AI/AiToolExecutor.php
```

### 3e. Upload Python sidecar

Create the `scripts/fynla_agent/` directory on the server, then upload:

```
scripts/requirements.txt
scripts/run_agent.py
scripts/fynla_agent/__init__.py
scripts/fynla_agent/agent.py
scripts/fynla_agent/config.py
scripts/fynla_agent/hooks.py
scripts/fynla_agent/schemas.py
scripts/fynla_agent/tools.py
```

---

## Step 4: Update production `.env`

SSH into the server and add these two lines to `.env`:

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html
nano .env
```

Add at the bottom:

```
ANTHROPIC_ADVANCED_CHAT_MODEL=claude-sonnet-4-6-20260320
AGENT_INTERNAL_TOKEN=<paste the token from Step 2>
```

Save and exit.

---

## Step 5: Install dependencies and clear caches

Still in the SSH session, run each command in order:

```bash
composer install --no-dev --optimize-autoloader
```

Then clear caches:

```bash
php artisan cache:clear
php artisan config:clear
php artisan view:clear
php artisan route:clear
php artisan optimize
```

Then install Python dependencies:

```bash
pip3 install --user anthropic pydantic requests
python3 -c "import anthropic; print('OK')"
```

Then seed the database:

```bash
php artisan db:seed
```

---

## Step 6: Verify

- [ ] Log in to https://fynla.org
- [ ] Open the Fyn assistant on the dashboard
- [ ] Ask "How is my retirement looking?" — should explain what data is missing, list bullet points, and navigate you to your profile
- [ ] Ask "What should I focus on?" — should give recommendations if you have data
- [ ] Confirm no quick reply chips appear after assistant messages
- [ ] Test a preview persona — chat should work, but creating records should be blocked
- [ ] Check browser dev tools for console errors — should be none

---

## Rollback

If something goes wrong, restore from git and clear caches:

```bash
git checkout main -- app/Services/AI/ app/Agents/CoordinatingAgent.php app/Http/Controllers/Api/AiChatController.php app/Http/Kernel.php app/Providers/AppServiceProvider.php config/services.php routes/api.php resources/js/components/Shared/AiChatPanel.vue
```

Then rebuild frontend, re-upload, and clear caches on the server.
