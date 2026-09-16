# Task 1: CaptureForms — Report

## Implementation Summary

Created the `CaptureForms` schema class as the single home for structured capture forms used in Fyn chat during Save Tax onboarding. This task implements Task 1 of the property capture feature.

**Files created:**
- `app/Services/Onboarding/CaptureForms.php` (191 lines)
- `tests/Unit/Services/Onboarding/CaptureFormsTest.php` (68 lines)

## TDD Evidence

### Step 1 & 2: Write Failing Test (RED)
```bash
./vendor/bin/pest tests/Unit/Services/Onboarding/CaptureFormsTest.php

FAIL  Tests\Unit\Services\Onboarding\CaptureFormsTest
⨯ it lists the property form and returns null for an unknown form
⨯ it marks the required fields and the conditional share
⨯ it builds one create_property input per filled kind with nothing un…
⨯ it drops a share sent for an individual owner and defaults a missin…
⨯ it composes the transcript line in plain words
⨯ it produces validation rules per kind and field

FAILED  Tests\Unit\Services\Onboarding\CaptureFormsTest > it lists…  Error   
Class "App\Services\Onboarding\CaptureForms" not found
```

### Step 3 & 4: Write Class and Pass Tests (GREEN)
```bash
./vendor/bin/pest tests/Unit/Services/Onboarding/CaptureFormsTest.php

PASS  Tests\Unit\Services\Onboarding\CaptureFormsTest
  ✓ it lists the property form and returns null for an unknown form     18.10s  
  ✓ it marks the required fields and the conditional share               0.06s  
  ✓ it builds one create_property input per filled kind with nothing un… 0.05s  
  ✓ it drops a share sent for an individual owner and defaults a missin… 0.05s  
  ✓ it composes the transcript line in plain words                       0.05s  
  ✓ it produces validation rules per kind and field                      0.04s  

Tests:    6 passed (22 assertions)
Duration: 18.67s
```

All 6 tests pass with 22 assertions.

## Implementation Details

### CaptureForms Class Structure

**Public Static Methods:**
1. `names(): list<string>` — Returns `['property']`, the sole capture form
2. `schema(string $name): ?array|null` — Returns the full schema array for 'property' or null for unknown names
3. `kindLabel(string $name, string $kind): string` — Looks up human-readable label for a kind (e.g. 'main_residence' → 'Home')
4. `rules(string $name): array<string, list<string>>` — Generates Laravel validation rules keyed as `kind.fieldKey` (e.g. 'main_residence.current_value' → ['required_with:main_residence', 'numeric', 'min:0', 'max:999999999.99'])
5. `toolInputs(array $form): array<string, array>` — Transforms form answers into create_property tool inputs, handling:
   - Conversion to floats
   - Mortgage boolean logic (null or 0 = no mortgage)
   - Dropping unused fields (e.g. monthly_rental_income for main_residence)
   - Stripping ownership_percentage for individual owners
   - Defaulting missing ownership_percentage to 50 for joint/tenants_in_common
6. `summarise(array $form): string` — Generates plain-English transcript line for the chat (e.g. "Home worth £750,000, mortgage £325,000, joint, my share 50%. Buy to let worth £450,000, no mortgage, rent £1,000 a month, individual.")

**Private Methods:**
- `property(): array` — Returns the complete schema definition for the property form
- `pounds(float $amount): string` — Formats currency as £X,XXX with trailing zeros stripped

**Constants:**
- `PROPERTY = 'property'`
- `MONEY_MAX = '999999999.99'` — Max value for full property value
- `MONTHLY_MAX = '999999.99'` — Max value for monthly rental income

### Key Design Decisions

1. **No field renaming** — Answer keys match the create_property tool's own field names; nothing renamed between form and store.
2. **Mortgage null semantics** — `mortgage_outstanding_balance: null` means "No mortgage" (has_mortgage = false); 0 or positive number means has_mortgage = true.
3. **Optional fields omitted** — Unstated optional fields never sent to tool, never null (PropertyNormaliser NOT NULL trap avoidance).
4. **Ownership share logic:**
   - Individual ownership never carries a share percentage
   - Joint/tenants_in_common always carry a share (defaults to 50 if missing)
   - A share sent for an individual owner is dropped (test: "drops a share sent for an individual owner")
5. **British copy** — All user-facing strings use British English (e.g. "Your share %" not "Your share").
6. **Accessibility for renderers** — Schema shape is consumed by web and /m renderers which know nothing about property; they draw from the schema generically.

## Formatting & Commit

- Ran `./vendor/bin/pint` on both files — passed with no changes needed.
- Commit: `3b4e2e29d` with exact message from brief plus attribution lines
- Branch: `feat/savetax-property-capture-form`

## Self-Review Findings

### Code Quality
✓ All methods use proper type hints and return types  
✓ All docblocks accurately describe behavior and parameters  
✓ `declare(strict_types=1)` present in both files  
✓ Final class prevents accidental subclassing  
✓ All public methods are static (singleton semantics)  
✓ Private constants follow uppercase naming for readability  

### Test Coverage
✓ 6 tests covering 22 assertions  
✓ Tests validate all six public methods  
✓ Tests cover edge cases: null mortgage, missing share, individual vs shared ownership, money formatting  
✓ Test for unknown form name returns null  
✓ Test for validation rules includes all field types and max values  

### Logic Correctness
✓ `mortgage_outstanding_balance: null` correctly maps to `has_mortgage: false` and omits the balance from output  
✓ Monthly rental income only included for buy_to_let (not main_residence)  
✓ Ownership percentage correctly omitted for individual and included for joint/tenants_in_common  
✓ Currency formatting strips trailing zeros (£750,000 not £750,000.00)  
✓ Money formatting uses two decimal places internally, strips trailing zeros on output  
✓ Validation rules correctly distinguish between required fields (with `required_with`), present-but-nullable (mortgage), and optional (share percentage)  

### No Issues Found
No concerns, warnings, or deviations from the brief.

## Later Tasks

The six public methods (`names()`, `schema()`, `rules()`, `toolInputs()`, `summarise()`, `kindLabel()`) with these exact names and signatures are ready for consumption by Tasks 2+.
