# Fynla v0.9.2 — User Testing Manual

**Date:** 16 March 2026
**Covers changes from:** 13 March – 16 March 2026 (v0.8.3 → v0.9.0 → v0.9.1 → v0.9.2)
**Testing URL:** https://fynla.org
**Mobile App:** Fynla iOS (TestFlight)
**Status:** DEPLOYED TO PRODUCTION — 16 March 2026

This manual is for non-technical testers. Follow each section step by step. For each test, the expected outcome is described — if what you see doesn't match, note the difference.

---

## How to Access Preview Personas

1. Open https://fynla.org in an **incognito/private browser window**
2. On the landing page, scroll down to the persona selector
3. Click a persona name to log in as that person
4. You'll see a purple "Preview Mode" banner at the top of every page

**Available personas:**

| Persona | Who They Are | Key Features to Test |
|---------|-------------|---------------------|
| David & Sarah Mitchell | Married couple, late 40s, high earners | Will (mirror), 4 LPAs, investments, multiple properties |
| Margaret Thompson | Widow, 70 | Simple will, 2 LPAs (1 registered, 1 draft), estate planning |
| James & Emily Carter | Young family, mid-30s | No will (banner should show), mortgage, workplace pensions |
| Patricia & Harold Bennett | Retired couple, 70s | Mirror will, retirement income analysis |
| Alex Chen | Entrepreneur, 39 | Business interests, SIPP |
| John Morgan | Young saver, 25 | Emergency fund, first-time savings |

---

## Part 1: Will Builder

### Test 1.1 — View a Completed Will (David Mitchell)

1. Log in as **David Mitchell** (Peak Earners persona)
2. In the left sidebar, click **"Will"** under the Family section
3. **Expected:** You arrive at `/estate/will-builder` showing David's completed will
4. **Check the following:**
   - No progress tracker/step indicator at the top — just the will document
   - The heading says **"Your Will"** (not "Will Preview")
   - The will is displayed in legal format with serif font on a light background
   - Title reads **"LAST WILL AND TESTAMENT of David Mitchell"**
   - **Executors:** Sarah Mitchell (Spouse) and Barclays Wealth Management (Professional Executor)
   - **Guardians:** James Mitchell (Brother) and Claire Henderson (Sister-in-law) — because the Mitchell children are under 18
   - **Specific Gifts:** £10,000 to Cancer Research UK
   - **Residuary Estate:** 100% to Sarah Mitchell, with substitution to children in equal shares
   - **Funeral Wishes:** Cremation, celebration of life at St Nicholas Church, Sevenoaks
   - **Digital Assets:** Sarah Mitchell as digital executor
   - **Attestation:** David Mitchell's signature in cursive script, dated 20 March 2024
   - **Witnesses:** Robert Hartley (Solicitor) and Amanda Pearson (Legal Secretary), both dated 20 March 2024, with full addresses and occupations filled in
5. **Check buttons:**
   - **"Print / Save PDF"** button should be visible and clickable — opens a new window with the formatted will
   - **"Edit Will"** button should appear but be **disabled/blocked** (you're in preview mode)
6. If this is a mirror will, you should see tabs for **"Your Will"** and **"Spouse's Will"** — click "Spouse's Will" to see Sarah's version with beneficiaries swapped

### Test 1.2 — View a Simple Will (Margaret Thompson)

1. Log in as **Margaret Thompson** (Widow persona)
2. Click **"Will"** in the sidebar
3. **Expected:** Margaret's simple will displayed in legal format
4. **Check:**
   - Title: **"LAST WILL AND TESTAMENT of Margaret Thompson"**
   - Executors: Andrew Thompson (Son) and Smithson Solicitors LLP
   - **No Guardians section** (no minor children)
   - Specific Gifts: £25,000 to Cotswold Care Hospice, £5,000 to St Lawrence Church, jewellery to Catherine Williams
   - Residuary: Andrew 40%, Catherine 40%, Grandchildren Education Trust 15%, Richard 5%
   - Funeral: **Burial** alongside late husband Harold at St Lawrence Churchyard
   - Signed 15 June 2023, witnessed by Dr Helen Cross (GP) and Mary Jenkins (Retired Nurse)
   - No "Spouse's Will" tab (this is a simple will, not a mirror)

### Test 1.3 — Will Builder Banner (James Carter)

1. Log in as **James Carter** (Young Family persona)
2. In the sidebar, click **"Estate Planning"**
3. **Expected:** The estate dashboard shows a **"Build Your Will"** banner at the top
4. Click the banner — you should arrive at the Will Builder wizard starting at **Step 1 (Introduction)**
5. The progress tracker should be visible at the top showing all 10 steps
6. **Check:** The banner does NOT appear for David Mitchell (who already has a will)

### Test 1.4 — Will Not in Old Locations

1. While logged in as any persona:
   - Click the **user dropdown** (top-right corner) — there should be **no "Will" link** in the dropdown menu
   - Navigate to the "Valuable Info" page (if accessible via Income or Expenditure links) — the tabs should be: **Letter, Income, Expenditure, Risk Profile** — no "Will" tab

---

## Part 2: Lasting Power of Attorney

### Test 2.1 — LPA Dashboard (David Mitchell)

1. Log in as **David Mitchell**
2. In the sidebar, click **"Power of Attorney"** under the Family section
3. **Expected:** You arrive at `/estate/power-of-attorney` — a standalone page (not a tab on the estate dashboard)
4. **Check:**
   - Page heading: **"Lasting Power of Attorney"**
   - No "Back to Estate Planning" link at the top
   - Introduction text explaining what an LPA is and why it matters
   - **4 LPA summary cards** displayed in a grid:
     - David's Property & Financial Affairs — status badge "Registered"
     - David's Health & Welfare — status badge "Registered"
     - Sarah's Property & Financial Affairs — status badge "Registered"
     - Sarah's Health & Welfare — status badge "Registered"
   - Each card shows: attorney names, decision type (jointly and severally), OPG reference number, green "Registered with the Office of the Public Guardian" indicator
   - No "Create" buttons visible (all types are covered)

### Test 2.2 — LPA Legal Document View (David Mitchell)

1. On the LPA dashboard, click **"View Details"** on David's **Property & Financial Affairs** card
2. **Expected:** A legal document in formal Office of the Public Guardian format
3. **Check the following sections:**
   - **Back button** at the top: styled as a rounded button reading "Back to Lasting Powers of Attorney" (not an inline text link)
   - Header: **"LASTING POWER OF ATTORNEY — Property and Financial Affairs"**
   - OPG Reference: **LP-2024-0847291**
   - **Section 1 — The Donor:** David Mitchell, date of birth, address in Sevenoaks
   - **Section 2 — The Attorneys:** Sarah Mitchell (Spouse, with date of birth) and James Mitchell (Brother, with date of birth), acting jointly and severally
   - **Section 4 — When Attorneys Can Act:** Only when the donor has lost mental capacity
   - **Section 5 — Preferences and Instructions:**
     - Preferences: consulting financial adviser for decisions over £50,000, main residence not sold unless necessary
     - Instructions: gifts limited to £500/person/year, property sale requires both attorneys
   - **Certificate Provider:** Robert Hartley, Family Solicitor, known for 12 years, with certification statements (a, b, c)
   - **People to Notify:** Elizabeth Mitchell, 8 The Crescent, Tonbridge
   - **Signatures section:** All in cursive script with dates:
     - Signed by the Donor: "David Mitchell"
     - Signed by Attorney 1: "Sarah Mitchell"
     - Signed by Attorney 2: "James Mitchell"
     - Signed by the Certificate Provider: "Robert Hartley"
   - **Registration stamp** (bordered box): Registered on 15 June 2024, reference LP-2024-0847291
4. Click **"Print / Save PDF"** — a new window should open with the formatted legal document
5. Click **"Edit"** — should be **blocked** (preview mode)
6. Click **"Back to Lasting Powers of Attorney"** — returns to the dashboard

### Test 2.3 — LPA with Draft Status (Margaret Thompson)

1. Log in as **Margaret Thompson**
2. Click **"Power of Attorney"** in the sidebar
3. **Expected:** 2 LPA cards:
   - Property & Financial Affairs — **"Registered"** badge (green)
   - Health & Welfare — **"Draft"** badge (grey)
4. Click "View Details" on the **Health & Welfare** (draft) card
5. **Check:**
   - Legal document format still shows, but no signatures (blank signature lines)
   - No registration stamp
   - **Compliance Checks** section should appear below the document (only for drafts)
   - Preferences and instructions should be filled in
6. Click "View Details" on the **Property & Financial Affairs** (registered) card
7. **Check:**
   - Signatures filled in, registration stamp present
   - Attorney: Richard Thompson (Son), Replacement: Catherine Thompson (Daughter)
   - No compliance section (registered documents don't need it)

### Test 2.4 — LPA via Estate Dashboard Card

1. Log in as **David Mitchell**
2. Click **"Estate Planning"** in the sidebar
3. On the IHT calculation page, look for the navigation cards at the top (Will, Gifting, Life Policy, Charitable Bequest, **Power of Attorney**)
4. Click the **Power of Attorney** card
5. **Expected:** Navigates to `/estate/power-of-attorney` — the same standalone LPA page

### Test 2.5 — LPA Create Buttons (James Carter)

1. Log in as **James Carter** (has no LPAs)
2. Click **"Power of Attorney"** in the sidebar
3. **Expected:** Empty state message, plus two rows with **"Create"** and **"Upload"** buttons:
   - Property & Financial Affairs — Create / Upload
   - Health & Welfare — Create / Upload
4. Click **"Create"** on Property & Financial Affairs — should navigate to the LPA wizard (but be blocked from saving in preview mode)

---

## Part 3: Estate Planning Dashboard

### Test 3.1 — Estate Dashboard Layout

1. Log in as **David Mitchell**
2. Click **"Estate Planning"** in the sidebar
3. **Expected:** The IHT calculation page with navigation cards at the top:
   - Inheritance Tax Summary card
   - **Will** card — shows status (Complete/Incomplete), executor name
   - **Gifting** card — shows annual exemption, IHT liability
   - **Life Policy** card — shows cover needed
   - **Charitable Bequest** card — toggle for 10%+ to charity
   - **Power of Attorney** card — shows "4 Registered"
   - Trust card (only if taxable estate > £2M)
4. **Check:** There is **no "Power of Attorney" tab** — it's a card that links to the standalone page
5. **Check:** The "Build Your Will" banner is **not visible** (David has a will)

### Test 3.2 — Will Card Click

1. On the estate dashboard, click the **Will** card
2. **Expected:** Navigates to `/estate/will-builder` showing David's completed will

### Test 3.3 — Gifting Card Click

1. Click the **Gifting** card
2. **Expected:** Switches to the Gifting Strategy view within the estate dashboard

---

## Part 4: Sidebar Navigation

### Test 4.1 — Sidebar Links

Check each link in the Family section of the sidebar:

| Sidebar Item | Expected Destination |
|-------------|---------------------|
| Will | `/estate/will-builder` — Will Builder (shows will or wizard) |
| Letter to Spouse | `/valuable-info?section=letter` — Letter/Expression of Wishes |
| Trusts | `/trusts` — Trusts dashboard |
| Estate Planning | `/estate` — IHT calculation with navigation cards |
| Power of Attorney | `/estate/power-of-attorney` — Standalone LPA page |

### Test 4.2 — Active Highlighting

1. Click **"Will"** — only the Will item should be highlighted in the sidebar
2. Click **"Estate Planning"** — only Estate Planning should be highlighted (not Will or Power of Attorney)
3. Click **"Power of Attorney"** — only Power of Attorney should be highlighted

---

## Part 5: Actions & Decision Trees (v0.9.1)

### Test 5.1 — Actions Dashboard

1. Log in as **David Mitchell**
2. In the sidebar under Planning, click **"Actions"**
3. **Expected:** Actions grouped by module in a grid layout:
   - Protection actions (if any)
   - Investment actions
   - Retirement actions
   - Estate actions
   - Savings actions (newly added)
4. Each action shows a title, description, impact level, and is clickable

### Test 5.2 — Decision Tree Detail

1. Click on any action (e.g., a retirement recommendation)
2. **Expected:** A detail page with two panels:
   - **Left panel — Decision Tree:** A visual flowchart with green (passed) and red (failed) nodes showing how the recommendation was reached
   - **Right panel — Decision Trace:** A vertical timeline walking through each step with your actual financial data
3. **Check:** The trace shows real data — your name, age, pension values, contribution rates — not generic placeholders
4. Click **"Back to Actions"** to return

### Test 5.3 — Savings Actions Present

1. On the Actions dashboard, check that **Savings** actions appear (this was previously missing — the system was returning a 404 for savings plans)
2. If David Mitchell has savings recommendations, they should appear alongside the other modules

---

## Part 6: Dashboard Data (v0.9.2)

### Test 6.1 — Dashboard Shows Real Data

1. Log in as **David Mitchell**
2. Navigate to the main **Dashboard**
3. **Check module summary cards show real values** (not identical numbers across all personas):
   - Savings: should show approximately £102,000
   - Investments: should show approximately £220,000
   - Retirement: should show projected income around £46,000
   - Estate: should show net worth around £1.46M
4. Log in as a **different persona** (e.g., James Carter)
5. **Check:** The dashboard values are **different** from David Mitchell's

### Test 6.2 — Retired Couple Retirement

1. Log in as **Patricia Bennett** (Retired Couple persona)
2. Navigate to **Retirement** (via Net Worth > Retirement in the sidebar)
3. **Expected:** Retirement analysis shows real income data:
   - Defined Benefit pension: £18,500/year (NHS Pension)
   - State Pension: £11,500/year
   - Total projected income: approximately £30,000
   - Target income: £35,000
   - Income gap: approximately £5,000
4. **Previously:** This showed all zeros — now it should show real pension income

---

## Part 7: Fyn Assistant (v0.9.2)

### Test 7.1 — Streaming Responses

1. Log in as any persona
2. Open the **Fyn chat** (chat icon, usually bottom-right or via sidebar)
3. Type a question, e.g.: "How are my savings looking?"
4. **Expected:**
   - Response appears **word by word** as it streams (not all at once after a long wait)
   - A **"Stop generating"** button appears while the response is streaming
   - Click "Stop generating" mid-response — the response should stop immediately

### Test 7.2 — Financial Context

1. Ask Fyn: "Give me an overview of my finances"
2. **Expected:** Fyn references your actual data — savings balances, investment values, pension details, property — not generic advice
3. **Check:** Fyn uses British English spelling (e.g., "optimise" not "optimize")

### Test 7.3 — Compliance

1. Ask Fyn: "Should I invest in Tesla?"
2. **Expected:** Fyn should NOT recommend a specific product. It should redirect to general investment principles or suggest speaking to a financial adviser

---

## Part 8: Mobile App (iOS)

### Test 8.1 — Mobile Dashboard

1. Open the Fynla app on your iPhone
2. Log in (or use Face ID if set up)
3. **Expected:** Module summary cards on the dashboard show real data (not identical stubs)

### Test 8.2 — Estate Module

1. Tap the **Estate Planning** module card
2. **Expected:** Estate summary with net worth and IHT liability based on the logged-in user's actual data

### Test 8.3 — Fyn Chat

1. Tap the **Fyn** tab at the bottom
2. Ask a question
3. **Expected:** Streaming response (words appear progressively)

---

## Part 9: General Checks

### Test 9.1 — No Scores Visible

Across all pages, check that no numerical scores appear in the UI (e.g., "75/100", score badges, score metric cards). Descriptive text like "Good", "Fair", "Critical" is fine. Numerical scores should never be shown to users.

### Test 9.2 — No Amber/Orange Colours

Check that no amber or orange colours appear anywhere in the application. Warnings should use **violet/purple**. Errors should use **raspberry/pink-red**. Success should use **spring/green**.

### Test 9.3 — British Spelling in User-Facing Text

Check that user-facing text uses British spelling: "Optimisation" not "Optimization", "Customise" not "Customize", "Analyse" not "Analyze".

### Test 9.4 — No Acronyms

Check that acronyms are spelled out: "Annual Allowance" not "AA", "Stocks & Shares" not "S&S", "Defined Benefit" not "DB". The only exception is **ISA** which may remain abbreviated.

---

## Reporting Issues

When reporting an issue, please include:
1. **Which persona** you were logged in as
2. **Which page** you were on (copy the URL if possible)
3. **What you expected** to see
4. **What you actually saw** (a screenshot is ideal)
5. **Browser** (Chrome, Safari, Firefox) and whether mobile or desktop
