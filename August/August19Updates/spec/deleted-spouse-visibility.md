# Spec — Deleted spouse visibility

**Status:** open. Partially implemented (PR #706); the sweep below is not started.
**Owner:** CSJ. **Written:** 2026-08-19. **Branch at time of writing:** `fyn/m-view-link-and-expenditure-gate`.

Read this whole file before touching anything. It contains the inventory, so
you do not need to re-derive it — and it names the things that must **not** be
changed, which is the easier mistake to make here.

---

## 1. The rule

CSJ, 2026-08-19, verbatim intent:

> All information must be retained on deletion of an account for regulatory
> purposes, so that includes all dependants. If there is a spouse account, this
> will remain, with the spouse being able to log in, but not see the other
> spouse's information.

Two obligations, pulling in opposite directions:

| | Requirement |
|---|---|
| **Retention** | Nothing is deleted. The `users` row (soft-deleted), their `family_members`, their financial records, their `spouse_permissions` — all stay. Purging happens later and separately (`RetentionPurgeService`, `fyn:episodic:purge`, FCA SYSC 9.1 six years). |
| **Visibility** | From the moment of deletion, the surviving linked account must not see the deleted partner's information. |

So the fix is **always at read time, never at delete time.** Any proposal that
nulls `spouse_id`, deletes `spouse_permissions`, or cascades a soft delete to
family members is wrong — it satisfies visibility by breaking retention.

### Settled, do not revisit

- **Joint records stay visible to the surviving joint owner.** CSJ ruled this
  2026-08-19. `HasJointOwnership` scopes on `user_id = ? OR joint_owner_id = ?`
  with no live-account condition, and that is correct: the survivor part-owns
  the asset and their share is their own information. Do not "fix" this trait.
- **Deletion must not unlink the survivor.** `AccountDeletionService` does not
  touch `spouse_id` and must not start.

---

## 2. Already done — do not redo

Landed on `fyn/m-view-link-and-expenditure-gate` (PR #706), deployed to csjones
and verified live.

- **`User::liveSpouseId(): ?int`** (`app/Models/User.php`, below the `spouse()`
  relation). Returns `$this->spouse?->id`. `User` uses `SoftDeletes`, so the
  relation already filters a deleted account — this is the one place that asks
  "may I show their information" rather than "were they ever linked".
- Three readers moved onto it:
  - `UserProfileService::getFamilyMembersWithSharing()` — **the confirmed leak.**
  - `ProfileCompletenessChecker` (spouse-has-children inference).
  - `FamilyMembersController` create-time duplicate check (also guarded so it
    no longer runs a `user_id IS NULL` query when there is no live spouse).
- `tests/Feature/UserProfile/DeletedSpouseVisibilityTest.php` — 4 tests,
  including one asserting the records are **retained**, so the regulatory half
  cannot be "fixed" away by a later change.

Evidence, csjones, user 17 (spouse = user 16, soft-deleted 2026-05-24):

```
before:  profile.spouse=null  |  shared-from-spouse rows=3   (Amelia, Oscar, Freya)
after:   liveSpouseId=NULL    |  shared-from-spouse rows=0
retention intact: user 16 still in DB = yes; their 4 family rows still present
```

---

## 3. Still open — the confirmed leak

### 3.1 `hasAcceptedSpousePermission()` returns `true` for a deleted spouse

`app/Models/User.php`, around line 745. This is **the app-wide data-sharing
gate** — 13 consumers.

Its primary path uses the `spouse` relation and so fails closed. It then falls
through to a legacy fallback that queries `spouse_permissions` by the **raw**
`spouse_id` column. The permission rows are retained (correctly), so the
fallback finds an `accepted` row and the gate says sharing is on.

Measured on csjones:

```
spouse_permissions touching a deleted account: 6   (all status=accepted)
user 17 hasAcceptedSpousePermission=true  marital='married'
user 22 hasAcceptedSpousePermission=true  marital='married'
user 25 hasAcceptedSpousePermission=true  marital='married'
```

All three of those partners are deleted. This is the highest-value item in the
sweep: fixing this one gate closes most of the 13 consumers at once.

**Consumers.** Three already pair the gate with a live-spouse check and are
therefore safe as-is — leave them:

- `Estate/TrustController.php:202` (`$spouse && ...`)
- `Estate/ComprehensiveEstatePlanService.php:72` (`$spouse && ...`)
- `Savings/ISATracker.php:106` (`$user->spouse && ...`)

The rest rely on the gate alone or on the raw column, and are exposed:

- `Api/GoalsController.php:567`
- `Api/Estate/IHTController.php:52`
- `Api/PersonalAccountsController.php:90`
- `Coordination/HouseholdPlanningService.php:62`, `:166`, `:248`
- `Goals/GoalsProjectionService.php:58`
- `Goals/LifeEventService.php:35`
- `Plans/EstatePlanService.php:386`
- `Protection/CoverageGapAnalyzer.php:325`

### 3.2 `UserResource` publishes the raw link to every client

`app/Http/Resources/UserResource.php:45` — `'spouse_id' => $this->spouse_id`.
19 references in `resources/js` branch on it (`ExpenditureOverview.vue`,
`IHTPlanning.vue`, `WillPlanning.vue`, `ExpenditureForm.vue` and others), so a
survivor's web UI still offers spouse views and spouse fetches for an account
that no longer exists. `resources/mobile` has **zero** references — `/m` is
unaffected and needs no client change.

### 3.3 A survivor can still edit the retained record

`Api/UserProfileController.php:268` and `:382` authorise editing a spouse's
profile with `if ($currentUser->spouse_id !== $userId) { 403 }` — the raw
column. A survivor therefore retains write access to a deleted partner's
retained profile. That is a retention-integrity problem as much as a privacy
one: retained records should not be mutable by someone who can no longer see
them.

---

## 4. Inventory — 159 references in `app/`, bucketed

Do not treat the raw count as the work. Most of it is correct as written.

| Bucket | Count | Verdict |
|---|---|---|
| **A. Loads the `User`** — `User::find($user->spouse_id)`, `$user->spouse` | ~30 | **Safe, no change.** `SoftDeletes` filters the lookup; these already fail closed. e.g. `WillDocumentService:270`, `AdvisorDashboardService:50/88/126`, `SavingsActionDefinitionService:3075`, `ComprehensiveEstatePlanService:71`. |
| **B. Link management** — assigning the column, linking, unlinking, collision checks | ~35 | **Safe, no change.** These manage the link itself and *must* see the raw column: `SpouseLinkingService`, `OnboardingService:325-338`, `FamilyMembersController:154-225/374/614-626`, `ResetPreviewData:130`, `RetentionPurgeService:191`. |
| **C. Joint ownership** | — | **Settled, no change** (§1). |
| **D. Cache invalidation** — `invalidateForUserAndSpouse($user->id, $user->spouse_id)` ×4 in `UserProfileController` | 4 | **Safe, no change.** Clearing a cache key for a deleted account is harmless. |
| **E. Sharing gate** — `hasAcceptedSpousePermission()` and its 13 consumers | 13 | **In scope** (§3.1). |
| **F. Bare existence checks** — `if (! $user->spouse_id) return ...` | ~26 | **In scope, needs D4.** Listed below. |
| **G. Authorization** — `UserProfileController:268/382` | 2 | **In scope** (§3.3). |
| **H. Client exposure** — `UserResource:45` + 19 `resources/js` refs | 20 | **In scope, needs D3** (§3.2). |
| **I. `spouse_permissions` queries** — `SpousePermissionController` ×5, `User.php:763-766` | 7 | **In scope, needs D2.** |

### Bucket F in full

Each gates spouse-aware planning on the raw column, then usually loads the
spouse and gets `null`. Two risks: a degraded-or-crashing branch, and an
inference leak (a boolean that says something about the deleted partner).

```
app/Services/Tax/TaxOptimisationService.php:384
app/Services/Tax/TaxActionDefinitionService.php:170
app/Services/Protection/CoverageGapAnalyzer.php:323
app/Services/Protection/ProtectionDataReadinessService.php:426
app/Services/Retirement/RetirementDataReadinessService.php:331
app/Services/Investment/Recommendation/UserContextBuilder.php:447
app/Services/Investment/Recommendation/DataReadinessService.php:277
app/Services/Coordination/HouseholdPlanningService.php:381
app/Services/Savings/SavingsActionDefinitionService.php:2989, :3060
app/Services/Mobile/MilestoneDetectionService.php:527
app/Services/UserProfile/ProfileCompletenessChecker.php:60, :159
app/Services/UserProfile/ModuleDataRequirementsService.php:787
app/Services/Estate/IntestacyCalculator.php:27
app/Services/Plans/EstatePlanService.php:384
app/Http/Controllers/Api/PersonalAccountsController.php:90
app/Http/Controllers/Api/EstateController.php:193
app/Http/Controllers/Api/LetterToSpouseController.php:99
app/Http/Controllers/Api/FamilyMembersController.php:73, :505, :543, :603
app/Http/Controllers/Api/Estate/LifePolicyController.php:45   (:74 is bucket A — leave)
app/Http/Controllers/Api/Estate/IHTController.php:50          (feeds :52, see §3.1)
```

---

## 5. Decisions — all settled by CSJ, 2026-08-19

- **D1 — The sharing gate: require a live spouse.** `hasAcceptedSpousePermission()`
  returns `false` once the partner's account is deleted, regardless of a retained
  `accepted` permission row. Follows directly from the rule in §1 — a retained
  row keeping sharing alive contradicts "can log in, but not see the other
  spouse's information". Closes ten of the thirteen consumers.
- **D2 — `spouse_permissions`: retain, ignore at read time.** The rows stay
  untouched for the regulatory record; the gate simply requires a live spouse
  before consulting them. No write at delete time.
- **D3 — Clients get a live-spouse field, `spouse_id` stays.** `UserResource`
  publishes both; the 19 `resources/js` branches move onto the live field.
  Nothing breaks for a consumer that still needs the historical link. `/m` reads
  neither and needs no change.
- **D4 — Planning switches to the live spouse.** The bucket-F branches use
  `liveSpouseId()`, so a deleted partner stops driving married-couple tax,
  protection gap, retirement, estate and intestacy output. A survivor is planned
  as a single person. Note `marital_status` stays `married` and is not touched —
  that may still be the truth of their life.
- **D5 — Write access is blocked.** A survivor can no longer edit the deleted
  partner's retained profile (§3.3). Retention integrity: a retained record
  should not be mutable by someone who can no longer see it.

---

## 6. Acceptance criteria

1. For a user whose linked spouse account is soft-deleted:
   - `hasAcceptedSpousePermission()` is `false`;
   - no endpoint returns any record owned by the deleted partner, except joint
     records the survivor co-owns (§1);
   - no boolean, count or completeness flag reveals the deleted partner's data;
   - write access to the deleted partner's records is refused.
2. Retention is provably untouched: the soft-deleted `users` row, their
   `family_members`, their financial records and their `spouse_permissions` all
   still exist afterwards. **Assert this explicitly in tests** — every change
   here is one careless step from satisfying privacy by destroying evidence.
3. Buckets A, B, C and D are unchanged. A diff that touches
   `HasJointOwnership`, `SpouseLinkingService` or `AccountDeletionService`
   needs justifying against §1.
4. Green: the profile, spouse, estate, protection, retirement, savings, tax and
   coordination suites. Full suite at the consolidation point, not per change
   (CLAUDE.md Rule 17).
5. Verified live on csjones against users 17, 22 and 25 — all three already
   have deleted partners, so no fixture-building is needed.

---

## 7. Verification recipe

csjones SSH (never the `ssh-fynla` MCP — that is production):

```bash
ssh -p 18765 -i ~/.ssh/fynlaDev u163-ptanegf9edny@ssh.csjones.co
cd ~/www/csjones.co/fynla-app
```

The three known survivors and the gate:

```php
foreach ([17, 22, 25] as $id) {
    $u = \App\Models\User::find($id);
    $p = app(\App\Services\UserProfile\UserProfileService::class)->getCompleteProfile($u);
    echo "user $id"
        . " | spouse_id=" . $u->spouse_id
        . " | liveSpouseId=" . var_export($u->liveSpouseId(), true)
        . " | sharing=" . var_export($u->hasAcceptedSpousePermission(), true)
        . " | shared-from-spouse=" . collect($p['family_members'])->where('owner', 'spouse')->count()
        . PHP_EOL;
}
```

Expected after the sweep: `liveSpouseId=NULL`, `sharing=false`,
`shared-from-spouse=0` for all three, with `spouse_id` still populated.

Retention check, must stay non-zero:

```php
echo \App\Models\User::withTrashed()->find(16) ? "user retained\n" : "USER GONE\n";
echo \App\Models\FamilyMember::where('user_id', 16)->count() . " family rows retained\n";
echo \Illuminate\Support\Facades\DB::table('spouse_permissions')
        ->where('user_id', 16)->orWhere('spouse_id', 16)->count() . " permission rows retained\n";
```

Current values: user retained, **4** family rows, **2** permission rows.

---

## 8. Notes for whoever picks this up

- `User` uses `SoftDeletes` (`app/Models/User.php:29`) even though the model has
  no `deleted_at` cast in the obvious place. That single fact is why the
  relation-based lookups are safe and the raw-column ones are not. Everything in
  this spec follows from it.
- The three survivors on csjones are test accounts
  (`rebecca.bennett.devtest@example.com`, `test2@phailanx.co.uk`,
  `laura-apr20@example.com`). Production has not been measured — the equivalent
  count there is unknown and worth establishing before deciding urgency.
- Related and deliberately out of scope: `family_members.name` defaulting to
  `'Unknown'` (fixed separately in PR #706), and the `/m` "now.Recorded" missing
  space (open, cosmetic, `/m` only).
