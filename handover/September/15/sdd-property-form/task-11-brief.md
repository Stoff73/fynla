### Task 11: Deploy the branch to csjones and verify in CSJ's Chrome, web then `/m`

**Files:** none new. Build outputs `public/build/` and `public/m-build/`.

- [ ] **Step 1: Run the full affected suites once, alone**

Run: `./vendor/bin/pest tests/Feature/Onboarding tests/Unit/Services/Onboarding tests/Feature/AI tests/Unit/Services/AI tests/Feature/Property tests/Feature/Tiers tests/Architecture && npx vitest run`
Expected: 0 failed. Record the counts for the PR.

- [ ] **Step 2: Build both bundles and deploy the branch to csjones**

```bash
./deploy/csjones-fynla/build.sh
git push -u origin feat/savetax-property-capture-form
rsync -az -e "ssh -p 18765 -i ~/.ssh/fynlaDev" public/build/ u163-ptanegf9edny@ssh.csjones.co:~/www/csjones.co/fynla-app/public/build/
rsync -az -e "ssh -p 18765 -i ~/.ssh/fynlaDev" public/m-build/ u163-ptanegf9edny@ssh.csjones.co:~/www/csjones.co/fynla-app/public/m-build/
ssh -p 18765 -i ~/.ssh/fynlaDev u163-ptanegf9edny@ssh.csjones.co 'cd ~/www/csjones.co/fynla-app && git fetch -q origin && git checkout -q feat/savetax-property-capture-form && git pull -q origin feat/savetax-property-capture-form && php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan config:cache && php artisan fyn:procedural:validate | grep onboarding.workflow'
```

`rsync` without `--delete` so an in-flight session keeps its old chunks (warn CSJ before replacing the bundle if they are mid-test).

- [ ] **Step 3: Web walk in CSJ's Chrome (claude-in-chrome, never headless)**

Register a fresh account on `https://csjones.co/fynla` (or reset an existing gate-0915 test user to `campaign_property`), verify the code from the server, walk the Save Tax funnel to the property step. Confirm, with CSJ watching: the form appears with Home and Buy to let; asterisks on the required fields; Joint reveals the share at 50; No mortgage disables the amount; Save is disabled until valid; Save posts; the transcript shows the plain-words line; the walk continues to the verify page where both records show the right value, mortgage, share and rent; the DOB prompt follows. Then a second account for the error path: three properties, the third refused on its box with the plan-limit message and the two saved ones intact.

- [ ] **Step 4: `/m` walk** (per the `verify-m` skill — cold navigation to `/m` on csjones with the desktop token bridge does not fire; use the documented path)

Same checks in the `/m` chat on the dashboard and in the docked bar on a module screen.

- [ ] **Step 5: Native untouched**

`curl` the messages endpoint with a native-style request (no `X-Fynla-Forms` header) for a user at the property step and confirm the stream carries the typed prompt and no `capture_form` event.

- [ ] **Step 6: Commit nothing; record the evidence**

Screenshots to `.playwright-mcp/gate-<date>/` or the Chrome captures; note test users and conversation ids for the PR body.

---

