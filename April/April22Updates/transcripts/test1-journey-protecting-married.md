# Test 1 — Follow Journey → Protecting What Matters (Married, Multi-Entity Protection)

**Date:** 2026-04-22
**User:** john@example.com (id=19), marital_status=married (seeded)
**Entry point:** `/dashboard?openFyn=journey`
**Status:** IN PROGRESS

---

## Turn 1 — path_choice (Fyn)

**Fyn:** Hi John, I'm Fyn — welcome to Fynla. I'll help you set up your financial plan. To start, do you want to follow a life-stage journey or pick a single module focus?

**Bubbles:**
- Follow a journey
- Pick a focus

**Observations:**
- Fyn panel opens at the correct width (docked, right side).
- Dashboard blurred behind (FR-M21 correct).
- "Suggestions" bar visible at bottom — this is the *post-onboarding* chat's chrome. During onboarding bubble states it should be hidden. **[POTENTIAL ISSUE #1 — flag for later]**
- Text input box "Ask Fyn..." is visible. Acceptable — user can type free text in any state.

**Action:** Click "Follow a journey"

---

## Turn 2 — user selected path (User)

**User bubble:** Follow a journey (shown on right, raspberry background, white text)

---

## Turn 3 — journey_choice (Fyn)

**Fyn:** Which journey fits your situation best?

**Bubbles:**
- **Starting out** — Build strong financial habits for your future.
- **Building foundations** — Save, invest, and grow your wealth with confidence.
- **Protecting and growing** — Ensure your family and assets are fully covered.
- **Planning your future** — Retirement projections, pension tracking, and estate planning.
- **Enjoying your wealth** — Make the most of your financial success.

**Observations:**
- Bubbles render full-width with a two-line structure (title + subtitle). Looks clean.
- Hover state visible on "Starting out" in the screenshot (light raspberry fill).

**[BUG #1 — FLAGGED, not yet investigated]** Journey labels differ between this flow ("Protecting and growing", "Planning your future") and the `/onboarding/welcome` page ("Protecting What Matters", "Planning Your Future"). Capitalisation + wording drift. Decide later whether to align or whether this is intentional.

**[BUG #2 — FLAGGED]** "SUGGESTIONS" bar still visible at the bottom of the chat during onboarding. This is post-onboarding chrome leaking through. The bubbles-state UI should probably hide it.

**Action:** Click "Protecting and growing".

---

## Turn 4 — user selected journey (User)

**User bubble:** Protecting and growing

---

## Turn 5 — base_spouse (Fyn)

**Fyn:** Great — let's add your spouse's details. Can you share their first name, date of birth, and email address? I'll create an account and link the two of you so you can plan together.

**Affordances:**
- Free-text input
- **"Skip this for now"** raspberry-underlined inline link above the input (FR-M16 — spouse skip)

**State machine inference:** The director skipped `base_personal`, `base_marital`, and any date-of-birth prompts because John is already seeded with `date_of_birth` + `marital_status='married'`. Went straight to `base_spouse`. Correct behaviour.

**Observation — BUG #3 (candidate):** In the first pause turn, the previous bubble chain is not visible above the spouse prompt — only "Protecting and growing" user bubble and the spouse ack are shown. Earlier bubbles (path_choice response + journey prompt + journey response) are off-screen / hidden. Scroll behaviour is acceptable as long as scrolling up reveals them. Will verify later.

**[BUG #2 continues]** — "Suggestions" + skip link render at the bottom of the chat. Skip link position is correct (FR-M16), but "Suggestions" bar shouldn't be here during onboarding.

**Action:** Type "My wife is Jane, born 15 July 1983, email jane@example.com"

---

## Turn 6 — user spouse details (User)

**User bubble:** My wife is Jane, born 15 July 1983, email jane@example.com

---

## Turn 7 — base_dependants (Fyn)

**Fyn:** Any children or dependants to add?

**Bubbles:**
- Yes
- No

**DB verification (post-spouse):**
- `users.spouse_id` = 20 (Jane)
- `users.household_id` = 1 (preserved)
- `family_members`: Jane, DOB 1983-07-15, relationship=spouse, linked_user_id=20, household_id=1 ✓

**[BUG #4 — candidate]** No visible Fyn acknowledgment text between spouse capture and the dependants question. UX feels terse — "Got Jane added." or similar would reassure. Flag for later.

**Action:** Click "Yes".

---

## Turn 8 — user confirmed dependants (User)
**User bubble:** Yes

---

## Turn 9 — base_dependants capture prompt (Fyn)
**Fyn:** Lovely. Tell me their first names, ages, and how they are related to you (child, parent, or other dependant). You can list several in one go.

**Action:** Type "Sam aged 8 and Emily aged 5, both children"

---

## Turn 10 — user dependants (User)
**User bubble:** Sam aged 8 and Emily aged 5, both children

---

## Turn 11 — profile_review_family pause (Fyn)

**Layout flip:** wide → standard (712px → 356px).
**Dashboard:** unblurred.
**Router push:** `/dashboard` → `/profile` (FR-M21 correct).

**Fyn:** Does your family and personal information look right? Tap the bubble to confirm — or just tell me what needs changing.

**Bubble:** Looks correct

**Profile page showing (left side):**
- Full Name: John Smith
- DOB: 15 May 1980
- Marital Status: married
- Employment: Software Engineer at Tech Corp Ltd (Employed)
- Domicile: UK Domiciled
- Data completeness: 6 of 9 items (67%)

**DB verification (family_members):**
```
Jane  spouse  DOB=1983-07-15  age=42  household_id=1
Sam   child   DOB=2018-01-01  age=8   household_id=1
Emily child   DOB=2021-01-01  age=5   household_id=1
```

**Verified live:**
- **B-2** (household_id NULL fix): all three rows show household_id=1. Not NULL. ✓
- **B-3** (age accessor): 42/8/5 computed correctly from DOB. ✓
- **B-4** (Jan-1 fallback): Sam DOB 2018-01-01 and Emily DOB 2021-01-01 — age ints match "aged 8" / "aged 5" input. ✓

**Action:** Click "Looks correct".

---

## Turn 12 — user confirmed family (User)
**User bubble:** Looks correct

---

## Turn 13 — base_employment (Fyn)

**Layout flip:** standard → wide. Dashboard blurred again.
**Router push:** `/profile` → `/dashboard` (via stored pre-profile route, FR-M21).

**Fyn:** Brilliant. Share the company you work for, your position, and your gross annual income — all in one go is fine.

**Free-text input.**

**Observation:** John already had employment data seeded (Software Engineer at Tech Corp Ltd). State machine asks anyway — this is expected behaviour because `onboarding_fyn_step=null` means the state machine walks through all onboarding steps regardless of pre-existing user data. The capture will likely overwrite existing values with what the user types.

**Action:** Type "Tech Corp Ltd, Software Engineer, £75,000 gross salary"




