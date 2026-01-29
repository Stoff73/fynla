### The main UK employee equity formats

1. **SAYE / Sharesave (tax-advantaged, all-employee)**
   Save monthly (up to **£500**), then **option** to buy shares after **3 or 5 years** at a fixed price. No Income Tax/NIC on discount at exercise. ([GOV.UK][1])

2. **CSOP (tax-advantaged options, selective)**
   Options over shares up to **£60,000** value at grant (from **6 April 2023**). If exercised **3–10 years** after grant, normally no Income Tax/NIC on exercise gain; CGT may apply on sale. ([GOV.UK][2])

3. **EMI (tax-advantaged options, startups/scaleups if eligible)**
   Not your core ask, but it’s the most common UK private-company option scheme; worth including for completeness and comparisons.

4. **Unapproved (non-tax-advantaged) share options**
   Common in UK and especially for **US parent** plans rolled into the UK. Generally **Income Tax** on exercise gain (and often PAYE/NIC if “readily convertible assets”). ([GOV.UK][3])

5. **RSUs / “restricted share units” (typically non-tax-advantaged)**
   Usually taxed when they **vest / shares are delivered** (Income Tax + NIC; often via payroll if shares are “readily convertible”). HMRC notes RSUs are common in LTIPs and “facts not label” decides treatment. ([GOV.UK][4])

---

## 2) Key definitions (vested, vesting, unvested) in UK terms

### RSUs (typical)

* **Grant date:** you receive a promise to deliver shares later (usually **no tax at grant** in the typical structure).
* **Vesting date:** conditions satisfied; shares are delivered (or become deliverable). **This is usually the UK tax point** for RSUs. HMRC describes RSUs as awards that vest once conditions are met. ([GOV.UK][4])
* **Vested RSUs:** already vested and delivered (you now own shares) — future tax is normally **CGT** on sale (subject to your circumstances and cost basis).
* **Vesting RSUs:** between grant and vest — not yet owned, but you’re tracking towards vesting conditions.
* **Unvested RSUs:** still subject to forfeiture (leaver/performance) — generally no current tax, but you need them in your schedule.

### Options (SAYE / CSOP / unapproved)

* **Grant date:** you get the right (option) to buy shares later at a fixed price.
* **Vesting date (if applicable):** the option becomes exercisable (many plans have time/performance vesting).
* **Exercise date:** you buy the shares. For many non-tax-advantaged options, **exercise is the tax point**.
* HMRC states the amount charged (for typical option gains) is broadly **market value at acquisition minus amount paid**. ([GOV.UK][3])

---

## 3) Tax & legal: what matters most (UK)

### 3.1 “Readily Convertible Assets” (RCAs) drives payroll withholding

If the shares are **readily convertible assets**, employers generally must operate **PAYE** (and account for NICs) on the relevant employment income. HMRC’s ERS manuals give examples and PAYE/NIC decision logic around RCAs and trading arrangements. ([GOV.UK][5])

**Practical implications**

* **Listed shares** are often RCAs (especially where there’s a market to sell).
* **Private company shares** might become RCAs when there’s a **sale event/secondary**, or arrangements to sell.
* If not RCAs, the employee may pay via self-assessment in some cases (but employers still have reporting duties).

### 3.2 HMRC registration & annual reporting (legal/compliance)

If you operate employee share plans, you typically need to:

* **Register** employment-related securities schemes with HMRC (timing differs by scheme type/events). ([GOV.UK][6])
* File ERS returns by **6 July after the end of the tax year** (even nil returns). ([GOV.UK][7])

### 3.3 Valuations (especially private companies)

For certain tax-advantaged options (notably EMI) and many private-company contexts, share valuation processes matter. HMRC has a valuation process (e.g., EMI via **VAL231**) and states how long agreements remain valid. ([GOV.UK][8])

---

## 4) Scheme-by-scheme deep dive (tax + legal + scheduling)

# A) Sharesave / SAYE (UK “share save scheme”)

### What it is

* All-employee savings contract with an option to buy shares after **3 or 5 years**.
* You can save up to **£500/month**. ([GOV.UK][1])

### UK tax treatment (headline)

* **Interest/bonus** at maturity is tax-free. ([GOV.UK][1])
* **No Income Tax or NIC** on the difference between what you pay and what the shares are worth when you buy them (exercise). ([GOV.UK][1])
* **CGT may apply** when you later sell the shares (normal CGT rules).

### Typical conditions

* Must be offered to **all eligible employees** (all-employee nature).
* Leaver rules: “good leavers” often allowed to exercise early under plan rules; “bad leavers” may lose the option (details are plan-specific).

### SAYE year-by-year schedule (template)

Assume: Grant in **Year 0**, 3-year savings contract.

| Year | Status                         | Employee action                            | Company/admin action              | Typical tax point                                                |
| ---- | ------------------------------ | ------------------------------------------ | --------------------------------- | ---------------------------------------------------------------- |
| 0    | **Granted**                    | Enrol + start monthly savings              | Grant options; set option price   | None (typically)                                                 |
| 1    | **Vesting/Saving**             | Continue monthly savings                   | Ongoing admin                     | None                                                             |
| 2    | **Vesting/Saving**             | Continue monthly savings                   | Ongoing admin                     | None                                                             |
| 3    | **Maturity → Exercise choice** | Exercise (buy shares) OR take cash savings | Process exercise + share delivery | **No Income Tax/NIC on discount** (SAYE advantage) ([GOV.UK][1]) |
| 3+   | **Post-exercise holding**      | Hold or sell shares                        | Reporting + platform support      | CGT on disposal (if gain)                                        |

**For your schedule tracking**, SAYE is simple: it’s basically a **maturity date** driven plan.

---

# B) RSUs (Restricted Stock Units)

### What they are (in UK practice)

* A promise to deliver shares (or sometimes cash) once conditions are met.
* Widely used in **large/listed companies** and multinationals.
* HMRC explicitly notes RSUs are used in LTIPs and vest when conditions are satisfied (time, employment, performance). ([GOV.UK][4])

### UK tax treatment (typical)

* **Tax usually arises at vesting/delivery**: value delivered becomes employment income; **Income Tax + NIC** commonly apply, often via payroll when shares are RCAs. ([GOV.UK][5])
* After vesting, when you own the shares, future change in value is usually under **CGT** when sold.

### Common vesting patterns (UK market)

* **4-year with 1-year cliff**, then monthly/quarterly vest.
* **3-year performance cycle** (e.g., 0–50% vest based on EPS/TSR metrics), sometimes plus holding/deferral.

### RSU year-by-year schedule (template: 4-year with 1-year cliff)

Assume: 1,000 RSUs granted in Year 0; 25% cliff at Year 1, then 1/48 per month thereafter.

| Year | Status            | What you track                                     | Typical leaver/performance conditions                                      | Typical tax point                                                    |
| ---- | ----------------- | -------------------------------------------------- | -------------------------------------------------------------------------- | -------------------------------------------------------------------- |
| 0    | **Unvested**      | Granted units + vest schedule + performance period | Usually forfeited if leave before cliff (plan-specific)                    | None                                                                 |
| 1    | **Vesting event** | Cliff vest (e.g., 250) + remainder start vesting   | Employment/service condition satisfied; performance may start to “lock in” | **Income Tax + NIC often via payroll (RCA dependent)** ([GOV.UK][5]) |
| 2    | **Vesting**       | Continued monthly/quarterly vest                   | Ongoing service; performance check at end of cycle                         | Same (on each vest)                                                  |
| 3    | **Vesting**       | Continued vest                                     | Ongoing                                                                    | Same                                                                 |
| 4    | **Fully vested**  | All vested; now holding shares                     | Post-vesting holding requirements may apply                                | Only CGT on sale                                                     |

**Payroll mechanics to expect**

* “Sell to cover” or withholding shares for tax is common when vesting creates Income Tax/NIC due.

---

# C) Employee share options (UK): CSOP, SAYE options, and Unapproved options

## C1) CSOP (Company Share Option Plan) — tax-advantaged

### What it is

* A selective option plan.
* Limit per employee: **£60,000** (market value at grant) for options granted from **6 April 2023**. ([GOV.UK][2])

### Tax treatment (headline)

* If you exercise **3 to 10 years** after grant, normally **no Income Tax or NIC** on the difference between exercise price and market value at exercise. ([GOV.UK][2])
* **CGT may apply** on subsequent sale.

### CSOP year-by-year schedule (template: 3-year vest, 10-year life)

Assume: Year 0 grant; vest 1/3 each year; exercise any time from Year 3 to Year 10 (or later per rules).

| Year | Status                                             | Employee                     | Company/admin                                         | Tax point                                                               |
| ---- | -------------------------------------------------- | ---------------------------- | ----------------------------------------------------- | ----------------------------------------------------------------------- |
| 0    | Granted (unvested)                                 | Receive options              | HMRC-compliant CSOP documentation; valuation at grant | None                                                                    |
| 1    | Vesting                                            | 1/3 becomes exercisable      | Admin vesting                                         | None                                                                    |
| 2    | Vesting                                            | 2/3 exercisable              | Admin                                                 | None                                                                    |
| 3    | Vested + earliest “tax-advantaged” exercise window | Can exercise                 | Process exercise                                      | **No Income Tax/NIC if exercised 3–10 years after grant** ([GOV.UK][2]) |
| 3–10 | Exercisable                                        | Choose when to exercise/sell | Ongoing reporting                                     | CGT on sale                                                             |

---

## C2) Unapproved (non-tax-advantaged) options — very common

### Tax treatment (core rule)

* UK tax typically arises on **exercise** (or other chargeable events like assignment/release).
* HMRC states the taxable amount is broadly the **difference between market value at acquisition and what’s paid**, plus any amount paid for the option. ([GOV.UK][3])
* PAYE/NIC often apply if shares are RCAs. ([GOV.UK][5])

### Unapproved options year-by-year schedule (template: 4-year vest, 10-year life)

Assume: 4-year vest; exercise any time after vest until expiry.

| Year | Status          | What you track                       | Typical conditions                             | Typical tax point                                                 |
| ---- | --------------- | ------------------------------------ | ---------------------------------------------- | ----------------------------------------------------------------- |
| 0    | Granted         | Strike price, number, expiry         | Leaver/performance rules set in plan           | None                                                              |
| 1    | Vesting         | Cliff/first tranche vest             | Service/performance                            | None                                                              |
| 2    | Vesting         | More vests                           | Service/performance                            | None                                                              |
| 3    | Vesting         | More vests                           | Service/performance                            | None                                                              |
| 4    | Fully vested    | All exercisable                      | Often 90 days post-termination exercise window | **On exercise:** employment income; PAYE/NIC if RCA ([GOV.UK][3]) |
| 4–10 | Exercise window | Exercise timing vs cash/tax planning | Expiry at end                                  | CGT on later sale (on post-exercise growth)                       |

---

## 5) How to build the schedule you asked for (vested, vesting, unvested)

### Minimum fields for a robust UK employee equity schedule

Use one schedule for all award types (RSUs + options + SAYE) with these columns:

**Award identity**

* Employer group entity (important for UK payroll responsibility)
* Plan type (SAYE / CSOP / Unapproved Option / RSU)
* Grant date, grant ID
* Award currency / share class / ticker (if listed)

**Vesting & exercise**

* Vest start date
* Vesting events (date + quantity)
* Conditions (service / performance / exit event)
* Exercise window start/end (for options)
* Expiry date (for options)
* Leaver terms (good/bad + post-termination exercise period)

**Tax & payroll flags**

* “Likely RCA?” (Y/N/Unknown) + rationale
* “PAYE/NIC via payroll?” (Expected yes/no)
* Cost basis notes (especially if taxed at vest/exercise)

**Reporting & compliance**

* UK ERS scheme registration required? (Y/N)
* ERS annual return required by 6 July? (Y/N) ([GOV.UK][7])

---

## 6) Real-world examples (UK market patterns)

### Example 1 — SAYE at a large UK employer (Tesco/BT/Next-style pattern)

* Annual or semi-annual invitation; employee saves monthly for 3 or 5 years; option price often set with a discount (within scheme rules).
* At maturity, employee chooses to exercise or take savings back (downside-protected on savings).

### Example 2 — UK listed company RSU LTIP (senior staff)

* 3-year performance period: e.g., 0–100% vest based on TSR vs peers; plus additional 2-year holding requirement.
* Tax hit occurs at vest/delivery; payroll typically withholds.

### Example 3 — UK subsidiary receiving US parent options/RSUs

* Even if the grant is from the US parent, UK payroll and PAYE/NIC issues can still land with the UK employing entity when income arises and shares are RCAs. HMRC’s RCA concept is central here. ([GOV.UK][5])

---
