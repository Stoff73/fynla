# Refer a Friend — Feature Specification

**Date:** 8 April 2026
**Branch:** `referFriend`

---

## Overview

Paid subscribers can refer friends to Fynla. When a referred friend purchases a subscription, both the referrer and the friend receive bonus subscription time:

- **Monthly purchase:** +1 week for both
- **Annual purchase:** +1 month for both

No limit on the number of referrals a user can make.

---

## User Flow

### Referrer Flow

1. User must have an active paid subscription (not trialing, not cancelled)
2. "Refer a Friend" button in the **top nav** (replaces/sits alongside "Choose a Plan" for paid users)
3. Clicking opens a **modal** showing:
   - Their unique referral code (auto-generated, persistent per user)
   - An email input to send the invitation
   - "Send Invitation" button
4. On send: Fynla emails the friend with the code, a description of Fynla, and a register link
5. The referral (code + friend's email) is saved in the database
6. User can send multiple invitations (no limit)

### Friend (Referee) Flow

1. Friend receives email with referral code and a registration link (e.g., `https://fynla.org/register?ref=XXXX`)
2. Friend clicks the link, registers normally — the `ref` query param is stored against their user record
3. Friend navigates to checkout — the system auto-detects their referral code from the DB (no input field needed)
4. Friend completes payment
5. **Post-payment trigger:** both referrer and friend get their subscription `current_period_end` extended

### Bonus Logic

| Friend's purchase | Referrer gets | Friend gets |
|-------------------|---------------|-------------|
| Monthly           | +1 week       | +1 week     |
| Annual            | +1 month      | +1 month    |

The bonus is applied by extending `current_period_end` on the subscription record. For the friend, this happens immediately after their first payment is confirmed. For the referrer, it happens at the same moment.

---

## Database

### New table: `referrals`

| Column | Type | Description |
|--------|------|-------------|
| id | bigint PK | |
| referrer_id | foreignId(users) cascade | User who referred |
| referee_id | foreignId(users) nullable | User who was referred (set when they register) |
| referral_code | string(20) index | The referrer's code |
| referee_email | string(255) | Email the invitation was sent to |
| status | enum: pending, registered, converted, expired | Tracks progress |
| bonus_applied | boolean default false | Whether subscription bonus was given |
| referred_at | timestamp | When invitation was sent |
| registered_at | timestamp nullable | When referee registered |
| converted_at | timestamp nullable | When referee purchased |
| timestamps | | |

### Modified table: `users`

| Column | Type | Description |
|--------|------|-------------|
| referral_code | string(20) unique nullable | User's persistent referral code (generated on first use) |
| referred_by_code | string(20) nullable | Referral code used when this user registered |

---

## Referral Code Format

- 8 characters, uppercase alphanumeric
- Format: `FYN-XXXXX` (e.g., `FYN-A3K9M`)
- Generated once per user, persisted on the users table
- Generated lazily (first time user opens the Refer a Friend modal)

---

## Backend

### ReferralService (`app/Services/Payment/ReferralService.php`)

```
generateCode(User $user): string
    — Generate and persist referral code if user doesn't have one
    — Format: FYN- + 5 random uppercase alphanumeric chars
    — Ensure uniqueness

sendInvitation(User $referrer, string $email): Referral
    — Validate referrer has active paid subscription
    — Create referral record (status: pending)
    — Send ReferralInvitationEmail
    — Return the referral

applyReferralOnRegistration(User $newUser, string $referralCode): void
    — Find referral by code + email match
    — Set referee_id, status: registered, registered_at
    — Store referred_by_code on user record

applyReferralBonus(User $referee, string $billingCycle): void
    — Called after successful payment confirmation
    — Find referral where referee_id = user, bonus_applied = false
    — Calculate bonus: weekly = +7 days, annual = +1 month
    — Extend referee's subscription current_period_end
    — Extend referrer's subscription current_period_end
    — Set bonus_applied = true, status: converted, converted_at
    — Log the bonus application
```

### ReferralController (`app/Http/Controllers/Api/ReferralController.php`)

```
getMyCode()        — GET /api/referral/code — returns user's referral code (generates if needed)
sendInvitation()   — POST /api/referral/invite — { email } — sends invitation email
myReferrals()      — GET /api/referral/list — returns user's referral history with statuses
```

### PaymentController modification

In `confirmPayment()`, after the existing post-transaction logic:
- Check if user has `referred_by_code`
- If yes, call `ReferralService::applyReferralBonus($user, $billingCycle)`

### AuthController modification

In `register()`:
- Accept optional `referral_code` from query param
- Store as `referred_by_code` on the new user record
- Call `ReferralService::applyReferralOnRegistration()`

---

## Frontend

### Top Nav (`Navbar.vue`)

- For paid subscribers: show "Refer a Friend" button (gift icon) instead of / alongside "Choose a Plan"
- For non-subscribers / trialing: continue showing "Choose a Plan"

### ReferralModal (`resources/js/components/Payment/ReferralModal.vue`)

- User's referral code displayed prominently (monospace, copyable)
- Email input field
- "Send Invitation" button (raspberry CTA)
- Success/error feedback
- List of previous referrals with status badges:
  - Pending (neutral) — invitation sent, not yet registered
  - Registered (violet) — friend registered but hasn't paid
  - Converted (spring) — friend paid, bonus applied

### Register page (`Register.vue`)

- Read `ref` query param from URL
- Store in hidden field / component data
- Pass to registration API call

### Checkout page (`CheckoutPage.vue`)

- No visible changes — the referral is handled automatically via the backend
- The `confirmPayment` response could optionally include a "You earned a bonus week/month!" message

---

## Email

### ReferralInvitationEmail (`app/Mail/ReferralInvitationEmail.php`)

**Subject:** "[First Name] thinks you'd like Fynla"

**Content:**
- Your friend [Referrer First Name] [Referrer Surname] invited you to try Fynla
- Brief description: "Fynla is your personal financial planning companion — helping you plan savings, investments, retirement, and estate with confidence"
- "Sign up and you'll both get extra time on your subscription — an extra week with a monthly plan, or an extra month with an annual plan"
- CTA button: "Create Your Free Account" → `https://fynla.org/register?ref=[CODE]`
- Footer with Fynla branding

---

## Eligibility Rules

- **Referrer must:** have an active paid subscription (status = active, not trialing/cancelled)
- **Referee must:** be a new user (no existing account with that email)
- **Bonus triggers:** only on the referee's first subscription purchase
- **Self-referral prevention:** referrer cannot refer their own email
- **Duplicate prevention:** same email can only be referred once per referrer

---

## Edge Cases

| Scenario | Behaviour |
|----------|-----------|
| Friend registers but never pays | Referral stays as "registered", no bonus |
| Friend was already referred by someone else | First referral wins (by referred_at timestamp) |
| Referrer's subscription expires before friend pays | Bonus still applies — extends from current_period_end even if in the past |
| Friend uses code but registers with different email | Match by code on the users.referred_by_code field, not email |
| Referrer sends to same email twice | Reject with "You've already invited this person" |

---

## Files Summary

### New files (~8)

| File | Purpose |
|------|---------|
| Migration: add referral_code + referred_by_code to users | 2 new columns |
| Migration: create referrals table | Referral tracking |
| `app/Models/Referral.php` | Referral model |
| `app/Services/Payment/ReferralService.php` | Core referral logic |
| `app/Http/Controllers/Api/ReferralController.php` | API endpoints |
| `app/Mail/ReferralInvitationEmail.php` | Invitation email mailable |
| `resources/views/emails/referral-invitation.blade.php` | Email template |
| `resources/js/components/Payment/ReferralModal.vue` | Refer a Friend modal |

### Modified files (~5)

| File | Changes |
|------|---------|
| `app/Http/Controllers/Api/PaymentController.php` | Apply bonus after payment confirmation |
| `app/Http/Controllers/Api/AuthController.php` | Accept referral_code on registration |
| `resources/js/views/Register.vue` | Read ref query param, pass to API |
| `resources/js/components/Navbar.vue` | Add "Refer a Friend" button for paid users |
| `routes/api.php` | Add referral routes |
