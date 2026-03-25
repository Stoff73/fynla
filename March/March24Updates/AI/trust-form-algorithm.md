# Trust Form Algorithm — Complete Field-by-Field Map

**Date:** 25 March 2026
**Source:** `resources/js/components/Trusts/TrustFormModal.vue`
**Parent:** `resources/js/views/Trusts/TrustsDashboard.vue`
**Route:** `/trusts`
**Entity type:** `trust`
**API:** `POST /api/estate/trusts`

## Form Structure — Single Step

One modal form with all fields visible. No sub-types or conditional sections (except `other` trust type which shows extra fields for description and country).

## AI Tool → Form Field Map

### AI Tool: `create_trust`

| AI param | formData key | Type | Required | Notes |
|----------|-------------|------|----------|-------|
| `trust_name` | `trust_name` | string | Yes | e.g. "Smith Family Discretionary Trust" |
| `trust_type` | `trust_type` | string enum | Yes | See trust types below |
| `initial_value` | `initial_value` | number | Yes | Amount initially settled into trust (£) |
| `current_value` | `current_value` | number | Yes | Current value of trust assets (£) |
| `trust_creation_date` | `trust_creation_date` | string (YYYY-MM-DD) | Yes | Date trust was established |
| `beneficiaries` | `beneficiaries` | string/null | No | Comma-separated list of beneficiaries |
| `trustees` | `trustees` | string/null | No | Comma-separated list of trustees |
| `purpose` | `purpose` | string/null | No | Purpose of the trust |

## Trust Type Options

| Value | Label | Description |
|-------|-------|-------------|
| `bare` | Bare Trust | Assets held by trustee for named beneficiary. Beneficiary has absolute right to capital and income. |
| `interest_in_possession` | Interest in Possession | Beneficiary has right to income but not capital. Often used for spouse on death. |
| `discretionary` | Discretionary Trust | Trustees have discretion over income and capital distribution. Relevant Property Trust (RPT). |
| `accumulation_maintenance` | Accumulation & Maintenance | For beneficiaries under 25. Income can be accumulated or distributed. |
| `life_insurance` | Life Insurance Trust | Holds life insurance policy outside estate for IHT purposes. |
| `discounted_gift` | Discounted Gift Trust | Combines gift with retained income rights. Reduces IHT-liable estate. |
| `loan` | Loan Trust | Settlor lends money to trust, retaining right to repayment. Growth outside estate. |
| `mixed` | Mixed Trust | Combination of trust types within one structure. |
| `settlor_interested` | Settlor-Interested Trust | Settlor or spouse can benefit from the trust. Special tax rules apply. |

**Note:** The form also has `other` option (shows description + country fields), but the backend validation does NOT include `other` in its enum. The AI should use the closest matching type from the list above.

## Validation

### Frontend (TrustFormModal.vue)
- `trust_name` — required
- `trust_type` — required
- `trust_creation_date` — required
- `initial_value` — required, number, min 0
- `current_value` — required, number, min 0
- `beneficiaries` — optional
- `trustees` — optional
- `purpose` — optional
- `is_active` — checkbox, default true

### Backend (TrustController.php)
- `trust_name` — required, string, max 255
- `trust_type` — required, in: bare, interest_in_possession, discretionary, accumulation_maintenance, life_insurance, discounted_gift, loan, mixed, settlor_interested
- `trust_creation_date` — required, date
- `initial_value` — required, numeric, min 0
- `current_value` — required, numeric, min 0
- `beneficiaries` — nullable, string
- `trustees` — nullable, string
- `purpose` — nullable, string

## Navigation Flow for AI Fill

1. CoordinatingAgent `handleCreateTrust()` receives input
2. Maps AI params to form field names
3. Returns `fill_form` with `entity_type: 'trust'`, `route: '/trusts'`
4. `TrustsDashboard.vue` opens `TrustFormModal`
5. `pendingFill` watcher fires `beginFieldSequence`
6. `highlightedField` watcher sets each `formData[key] = value`
7. `filling` watcher auto-submits after 250ms
8. Dashboard calls `createTrust` Vuex action → `POST /api/estate/trusts`
9. On success, `completeFill()` sends "Done" to chat

## Pre-set Requirements

None — trust form has no conditional select fields that need early setting (unlike property/pension). All fields are always visible.

## Test Scenarios for Grok

### Scenario 1: Discretionary Trust (most common)
"I have a discretionary trust called the Smith Family Trust. It was set up in June 2018 with £325,000 and is now worth about £450,000. The trustees are myself and my wife, and the beneficiaries are our three children James, Emily and Sophie. The purpose is estate planning."

**Expected:** trust_type: discretionary, all fields populated

### Scenario 2: Life Insurance Trust
"I have a life insurance trust holding my Royal London whole of life policy. It was set up in January 2020 with a sum assured of £500,000. My wife and children are the beneficiaries, and myself and my solicitor are the trustees."

**Expected:** trust_type: life_insurance, initial_value: 500000

### Scenario 3: Bare Trust for grandchild
"I set up a bare trust for my grandson in September 2022 with £50,000. It's now worth about £58,000. My daughter is the trustee and my grandson Tom is the sole beneficiary."

**Expected:** trust_type: bare, beneficiaries: "Tom"

### Scenario 4: Discounted Gift Trust
"I have a discounted gift trust with Prudential set up in March 2021. I put in £200,000 initially and I take £10,000 a year income from it. It's currently worth about £185,000. The beneficiaries are my two children."

**Expected:** trust_type: discounted_gift

### Scenario 5: Loan Trust
"I have a loan trust with St. James's Place. I loaned £150,000 to the trust in April 2019. The trust invests the money and any growth is outside my estate. Currently worth about £175,000."

**Expected:** trust_type: loan

### Scenario 6: Interest in Possession Trust
"My late father left an interest in possession trust in his will. My mother receives the income during her lifetime. It holds about £300,000 in investments. It was established when he died in November 2015. The trustees are my brother and the family solicitor."

**Expected:** trust_type: interest_in_possession

### Scenario 7: Accumulation & Maintenance Trust
"I set up an accumulation and maintenance trust for my children's education in 2020 with £100,000. It's grown to about £120,000. The trustees are myself and my sister."

**Expected:** trust_type: accumulation_maintenance

### Scenario 8: Mixed Trust
"I have a mixed trust that combines discretionary and interest in possession elements. Set up in 2017 with £250,000, now worth £310,000."

**Expected:** trust_type: mixed

### Scenario 9: Settlor-Interested Trust
"I have a settlor-interested trust that I set up in 2016 with £180,000. My wife and I can benefit from it. Currently worth about £220,000."

**Expected:** trust_type: settlor_interested

## Files Involved

| File | Role |
|------|------|
| `app/Services/AI/XaiToolDefinitions.php` | Tool definition — `create_trust` |
| `app/Agents/CoordinatingAgent.php` | `handleCreateTrust()` — validation + field mapping |
| `app/Http/Controllers/Api/Estate/TrustController.php` | `createTrust()` — backend validation + DB save |
| `resources/js/components/Trusts/TrustFormModal.vue` | Form with AI fill watchers |
| `resources/js/views/Trusts/TrustsDashboard.vue` | Parent — opens modal, handles save, completeFill |
| `resources/js/store/modules/trusts.js` | Vuex — `createTrust` action → POST /api/estate/trusts |
