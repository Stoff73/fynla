# Gift Form Algorithm — Complete Field-by-Field Map

**Date:** 25 March 2026
**Source:** `resources/js/components/Estate/GiftForm.vue`
**Parent:** Estate Planning dashboard (Gifting section)
**Route:** `/estate` (Gifting card → expanded view)
**Entity type:** `estate_gift`
**API:** `POST /api/estate/gifts`

## Form Structure — Single Step

Simple inline form with 5 fields. No conditional sections.

## AI Tool → Form Field Map

| AI param | formData key | Type | Required | Notes |
|----------|-------------|------|----------|-------|
| `gift_date` | `gift_date` | string (YYYY-MM-DD) | Yes | Must be in the past. Affects 7-year rule. |
| `recipient` | `recipient` | string | Yes | Full name. Resolved via `resolveFamilyNames`. |
| `gift_value` | `gift_value` | number | Yes | Value in £ |
| `gift_type` | `gift_type` | string enum | Yes | See types below |
| `notes` | `notes` | string/null | No | Context about the gift |

## Gift Type Options

| Value | Label | Description |
|-------|-------|-------------|
| `pet` | Potentially Exempt Transfer | Most common. Gift to individual. Tax-free after 7 years. |
| `clt` | Chargeable Lifetime Transfer | Gift to trust/company. Immediately taxable at 20%. |
| `exempt` | Exempt Gift | To spouse, charities, political parties, or for marriage. |
| `small_gift` | Small Gift Exemption | Up to £250 per person per year (immediately exempt). |
| `annual_exemption` | Annual Exemption | First £3,000 of gifts each tax year (immediately exempt). |

## Test Scenarios for Grok

### Scenario 1: Large PET to daughter
"I gave my daughter £50,000 in June 2023 as a house deposit"

### Scenario 2: Annual exemption gift
"I gave my son £3,000 last Christmas as his annual exemption gift"

### Scenario 3: Small gift
"I gave my nephew £200 for his birthday in March 2025"

### Scenario 4: Charitable gift (exempt)
"I donated £10,000 to the British Heart Foundation in January 2024"

### Scenario 5: Gift to trust (CLT)
"I settled £100,000 into the family trust in April 2022"
