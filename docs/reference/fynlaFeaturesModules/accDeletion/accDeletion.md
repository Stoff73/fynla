# Account Deletion Audit — What Actually Happens

> Audit date: 2026-05-07
> Source-of-truth files cited inline.

## Flow

**Frontend** — `resources/js/views/Settings/PrivacySettings.vue` opens a 3-step wizard: choose (data vs. account) → verify (2FA or 6-digit email code) → confirm (typed phrase: "Delete my Data" / "Delete my Account").

**API** — all under `/api/auth/gdpr/erasure/*`, hitting `app/Http/Controllers/Api/GDPRController.php`:
- `initiateErasure` (L341) — issues a 15-min session token, sends verification code
- `verifyErasure` (L399) — checks code, max 3 attempts
- `executeErasure` (L475) — runs the actual deletion

**Service** — `app/Services/GDPR/DataErasureService.php` does the work in a DB transaction.

There are two paths:

| Path | Method | What it does |
|------|--------|--------------|
| Delete my Data | `deleteDataOnly()` (L120) | Wipes financial data, nulls 3 profile fields, **keeps user logged in** |
| Delete my Account | `processErasure()` (L79) | Wipes financial data, deletes documents/exports/audit logs, then `$user->forceDelete()` |

---

## What gets explicitly deleted in code (DataErasureService)

`deleteFinancialData()` (L150–205) calls `->delete()` / `->forceDelete()` on these relationships:
goals, life/critical/income protection policies, DC/DB/state pensions, investment accounts (+ holdings), savings accounts, mortgages, properties, business interests, chattels, family members, consents.

`processErasure()` additionally:
- Deletes document files from `storage` + DB rows (L210–228)
- Deletes export files from `storage` + DB rows (L233–248)
- **Hard-deletes `audit_logs`** for the user (L100)
- Hard-deletes the `erasure_requests` row (L104, otherwise FK blocks user delete)
- Calls `deleteUser()` (L259) which clears spouse links bidirectionally, deletes Sanctum tokens + sessions, then `$user->forceDelete()`

---

## What cascades via FK (verified from migrations)

All of these `cascadeOnDelete` from `users.id` and disappear automatically when `forceDelete()` runs:

`user_sessions`, `user_consents`, `data_exports`, `password_reset_sessions`, `goals` + `goal_contributions`, `subscriptions`, `payments`, `invoices`, `trial_reminder_log`, `ai_conversations` (+ `ai_messages` via conversation_id), `ai_daily_usage`, `ai_audit_events`, `ai_abort_events`, `ai_request_idempotency`, `ai_advice_log`, `device_tokens`, `notification_preferences`, `properties`, `mortgages`, `investment_accounts`, `savings_accounts`, `business_interests`, `chattels`, `family_members`, `lasting_powers_of_attorney`, `will_documents`, `life_events.user_id`, `life_event_allocations`, `tax_strategy_household_inputs`, `pension_input_history`, `feedback_responses`, `lifecycle_email_log`, `discount_code_usages`, `referrals.referrer_id`, `user_assumptions`, `plan_action_funding_selections`, `what_if_scenarios`, `client_activities` (advisor + client), `advisor_clients`, `eval_recording_tables`, `insight_articles.author_id`, `insight_article_revisions.saved_by`, `insight_templates.created_by`, `document_articles.imported_by`, `discount_codes.created_by`/`user_id`.

---

## What is `set null` (record stays, link cleared)

Per CLAUDE.md rule #7 these are correct — joint records survive single-owned by the remaining party:

`goals.joint_owner_id`, `properties.joint_owner_id`, `mortgages.joint_owner_id`, `investment_accounts.joint_owner_id`, `savings_accounts.joint_owner_id`, `business_interests.joint_owner_id`, `chattels.joint_owner_id`, `family_members.linked_user_id`, `referrals.referee_id`, `audit_logs.user_id` (then explicitly hard-deleted by code anyway).

---

## ⚠️ What's broken

### 1. `life_events.joint_owner_id` will block account deletion (HIGH severity)

`database/migrations/2026_02_03_120001_create_life_events_table.php:58`:
```php
$table->foreignId('joint_owner_id')->nullable()->constrained('users');
```
**No `onDelete()` clause.** Laravel/MySQL default is `RESTRICT`. If any `life_events` row has the user in `joint_owner_id`, `$user->forceDelete()` will throw a foreign-key constraint violation and roll back the entire deletion transaction. The user sees a 500 and their account is *still there*.

`DataErasureService` does not touch `life_events` at all (verified — `grep "lifeEvent\|life_event" DataErasureService.php` returns nothing). It only avoids hitting this bug because `life_events.user_id` cascades, so the user's *own* events get cleaned up — but events where they're the *joint owner* (i.e. their spouse created the event) do not.

This is also inconsistent with the project convention in `database/CLAUDE.md` which says joint_owner_id should be `->onDelete('set null')`.

**Fix:** new migration that drops + re-adds the FK with `->nullOnDelete()`, OR add `LifeEvent::where('joint_owner_id', $userId)->update(['joint_owner_id' => null])` to `deleteFinancialData()`.

### 2. Document/export file deletion uses default disk (MEDIUM severity)

`DataErasureService.php:216-217` and `:238-239`:
```php
Storage::exists($document->file_path);
Storage::delete($document->file_path);
```
This calls the *default* disk. If documents are stored on `private`, `s3`, or any non-default disk, the files **stay on disk forever** while DB rows disappear. Compare with `DataPurgeService` which correctly does `Storage::disk($doc->disk)->delete(...)`.

### 3. Audit-log handling is inconsistent between the two erasure paths (LOW–MEDIUM)

- **Privacy tab** (`DataErasureService::processErasure` L100) — **hard deletes** `audit_logs` for the user.
- **Grace-period purge** (`DataPurgeService`, used after subscription cancellation) — **anonymises** them (NULL the user_id, ip, user_agent, old/new values).

Pick one. For GDPR Article 17 / right-to-erasure, anonymisation is usually the safer choice (you keep the audit trail for fraud/legal forensics without holding personal data). Hard-delete also tosses the post-deletion audit context.

### 4. Payment/billing history is fully wiped (REGULATORY — needs CSJ decision)

`subscriptions.user_id`, `payments.user_id`, `invoices.user_id` all `cascadeOnDelete`. When the user is hard-deleted, MySQL cascades through them all. UK FCA / HMRC typically expect 6+ years of transaction record retention. If Fynla is the merchant of record for the Revolut subscription charges, this might be a regulatory issue. Check with whoever owns compliance — may need to switch these tables to `set null` and anonymise instead of cascading.

### 5. `audit_logs` for actions the user *performed on others* are also hard-deleted (LOW)

`AuditLog::where('user_id', $userId)->delete()` (L100) deletes all logs where the deleted user was the *actor*. If an admin (or advisor) deleted their own account, every audit entry showing what they did to other people's data is gone. Probably fine, but worth a moment's thought.

---

## What's left behind (orphaned data)

After an `account` deletion completes successfully (i.e. when bug #1 doesn't fire):

- **Nothing in the DB** is orphaned in the FK sense — every other `user_id` reference cascades or sets-null correctly.
- **Files on non-default disks** (bug #2) — if any documents/exports use a non-default disk, files persist on storage with no DB row referencing them.
- **The `tokens` table cache hit** — `$user->tokens()->delete()` runs, but if a request is mid-flight when the deletion transaction commits, that request continues with a now-invalid auth context until the next route hit.

For `data` deletion (account survives), only `employment_status`, `salary`, `national_insurance_number` are nulled on the user record. Other PII on the user row — `first_name`, `surname`, `date_of_birth`, `phone`, `address_*`, `marital_status`, `nationality`, etc. — **stays**. This may or may not match what you intended for "delete my data".

---

## Quick summary

| Concern | Status |
|---------|--------|
| User row + most related tables | ✅ Cleanly deleted |
| Joint-owned assets (properties/savings/investments/etc.) | ✅ Survive single-owned |
| Spouse relationship | ✅ Cleared bidirectionally |
| AI chat history | ✅ Cascades |
| Sessions + Sanctum tokens | ✅ Explicitly deleted |
| **Life events where user is joint owner** | ❌ **Will throw FK error and fail the whole deletion** |
| **Document files on non-default disks** | ❌ Orphaned on storage |
| Audit logs path consistency | ⚠️ Two different behaviours for two paths |
| Payment / subscription / invoice retention | ⚠️ All wiped — verify FCA/HMRC requirements |
| "Delete my Data" leaves PII on user row | ⚠️ Only 3 fields nulled, name/DOB/phone/address stay |

Recommend fixing #1 first (it's a hard bug — try deleting an account that has a joint life event and the operation will fail). #2 is a quiet data-protection leak. #4 is a CSJ/compliance call.

---

## Proposed fix list (working notes)

- [ ] **#1 Life events FK** — new migration to alter `life_events.joint_owner_id` FK to `->nullOnDelete()`. Backfill any existing orphaned rows is unnecessary (FK has been silently RESTRICT, not orphaning).
- [ ] **#2 Document/export disks** — change `DataErasureService::deleteDocuments()` and `deleteExports()` to use `Storage::disk($model->disk)` consistent with `DataPurgeService`. Add a fallback to default disk only if `disk` is null.
- [ ] **#3 Audit log policy** — decide: hard-delete in both paths, or anonymise in both paths. Update whichever service is wrong.
- [ ] **#4 Billing retention** — confirm with compliance owner. If retention required: change `subscriptions` / `payments` / `invoices` FKs to `set null`, and add an anonymisation step to `processErasure()` (null name/email/etc. on those rows, keep amounts and dates).
- [ ] **#5 "Delete my Data" PII scope** — clarify intent. If "data" is meant to mean "all financial AND personal data, but keep the login", expand the nulled-fields list in `deleteDataOnly()` to cover the rest of the PII columns. If "data" really only means financial, leave it but consider renaming the button to "Delete my financial data" so users aren't surprised.
