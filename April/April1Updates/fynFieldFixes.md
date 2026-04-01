# Fyn AI Field Name Fixes — 1 April 2026

## Problem

Fyn was reading wrong field names from database models, causing it to see £0 / blank for mortgages, DC pensions, business interests, chattels, and life insurance policy types. This meant Fyn told users "no mortgage recorded" when they had a £200,000 mortgage, showed £0 for pension values, and couldn't identify life insurance policy types.

## Root Cause

The AI context-building code (`SystemPromptBuilder`, `HasAiChat`, `CoordinatingAgent`) used incorrect field names that don't exist on the Eloquent models. Laravel returns `null` for non-existent attributes, so `sum('current_balance')` on Mortgage (which uses `outstanding_balance`) always returned 0.

## Files Changed

| File | Changes |
|------|---------|
| `app/Services/AI/SystemPromptBuilder.php` | 5 field name fixes (mortgage, DC pension, business, chattel value + type, life insurance type) |
| `app/Traits/HasAiChat.php` | Same 5 field name fixes (duplicate code path) |
| `app/Agents/CoordinatingAgent.php` | 6 field name fixes in read paths + 1 write fix (life insurance policy_type) + field alias mapping in update_record handler |

## All Fixes

| Model | Wrong Field | Correct Field | Impact |
|-------|------------|---------------|--------|
| Mortgage | `current_balance` | `outstanding_balance` | Mortgages always showed £0 |
| DCPension | `current_value` | `current_fund_value` | Pension values always £0 |
| BusinessInterest | `estimated_value` | `current_valuation` | Business values always £0 |
| Chattel | `estimated_value` | `current_value` | Chattel values always £0 |
| Chattel | `category` | `chattel_type` | Chattel type always blank |
| LifeInsurancePolicy | `life_policy_type` | `policy_type` | Policy type always blank |
| LifeInsurancePolicy | `monthly_premium` | `premium_amount` | Premium always £0 |

Additionally, `handleUpdateRecord()` now maps AI tool field names to actual DB field names before the `getFillable()` filter, so updates via Fyn no longer silently drop fields.

## Deploy

Upload these 3 PHP files:
- `app/Services/AI/SystemPromptBuilder.php`
- `app/Traits/HasAiChat.php`
- `app/Agents/CoordinatingAgent.php`

Then clear caches:
```bash
php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize
```

## Testing

After deploy, ask Fyn on production:
1. "what is my total property asset and equity value" — should now show mortgage balance and correct equity
2. "what are my pensions worth" — should show actual DC pension fund values
3. "tell me about my business interests" — should show actual valuations
