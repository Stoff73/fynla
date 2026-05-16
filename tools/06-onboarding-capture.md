# Onboarding capture tools (4)

These four tools are NOT exposed via the normal `getTools()` catalogue. They are surfaced **only** by `AiToolDefinitions::onboardingExtractionTools()` and passed via `toolsListOverride` from `OnboardingChatDirector` during grouped-extract turns.

That means:
- They never reach Advice Fyn (advice runs `AdviceFyn::handle` which calls `getTools()`, never `onboardingExtractionTools()`).
- They run only when the onboarding state machine invokes a grouped-extract turn (`OnboardingChatDirector::handleGroupedExtract`).
- They are also stripped from advice mode by `AdviceFyn::WRITE_TOOLS` (defence in depth — even if surfaced, advice would refuse them).

The xAI version uses `strict: false` to allow optional fields like `last_name`.

> Source:
> - Schema: `app/Services/AI/AiToolDefinitions.php` (method `onboardingExtractionTools` returns the override list).
> - Handlers: `app/Agents/CoordinatingAgent.php`.

---

## 1. `capture_personal_details`

Writes `date_of_birth` + `marital_status` to the `users` table. Accepts partial captures (FR-M10) — if the LLM only gives one field, the state machine stays on `base_personal` and the next prompt re-asks for the missing one.

**Schema** (`AiToolDefinitions.php:1273-1292`):

```php
[
    'name' => 'capture_personal_details',
    'description' => 'Capture the user\'s date of birth and/or marital status from a free-text reply during onboarding. Call this once per turn. Do not call any other tool. CRITICAL: only include a field in the arguments when the user has EXPLICITLY stated it in their reply. Do not guess, infer, or default any field. If the user only gave their date of birth, include only date_of_birth. If they only gave their marital status, include only marital_status. Omit a field entirely rather than inventing a value — the onboarding flow will re-ask for anything missing.',
    'parameters' => [
        'type' => 'object',
        'properties' => [
            'date_of_birth' => ['type' => 'string', 'description' => 'Date of birth in YYYY-MM-DD format, parsed from natural language like "12 January 1985". Only include this field if the user explicitly stated a date of birth.'],
            'marital_status' => [
                'type' => 'string',
                'enum' => ['single', 'married', 'civil_partnership', 'divorced', 'widowed'],
                'description' => 'The user\'s marital status. Only include this field if the user explicitly stated their marital status. Map phrases: "married" → married, "civil partnership" or "civil partner" → civil_partnership, "single" or "unmarried" → single, "divorced" or "separated" → divorced, "widowed" or "widow" → widowed.',
            ],
        ],
        'required' => [],
        'additionalProperties' => false,
    ],
],
```

**Handler** (`CoordinatingAgent.php:1074-1175`):

```php
private function handleCapturePersonalDetails(array $input, User $user): array
{
    $dob = trim((string) ($input['date_of_birth'] ?? ''));
    $marital = trim((string) ($input['marital_status'] ?? ''));

    Log::info('[CoordinatingAgent] handleCapturePersonalDetails called', [
        'user_id' => $user->id,
        'raw_dob' => $dob,
        'raw_marital' => $marital,
    ]);

    // FR-M10 — accept partial captures. If the LLM provided neither
    // field the tool call captured nothing, so surface the generic
    // retry. Any one field is enough: the state machine will stay on
    // base_personal and the next prompt (via buildPersonalPrompt) will
    // pre-confirm the field we have and ask for the missing one.
    if ($dob === '' && $marital === '') {
        Log::warning('[CoordinatingAgent] handleCapturePersonalDetails rejected: both fields empty', [
            'user_id' => $user->id,
        ]);

        return ['error' => true, 'message' => 'date_of_birth or marital_status is required'];
    }

    if ($dob !== '') {
        // Parse DOB — accept YYYY-MM-DD, DD/MM/YYYY, DD-MM-YYYY and
        // natural-language forms. Slashed DD/MM/YYYY is UK-ambiguous
        // with MDY, so handle it explicitly so "10/04/1985" parses as
        // the 10th of April rather than October 4.
        $carbonDob = null;
        try {
            if (preg_match('#^(\d{1,2})[/-](\d{1,2})[/-](\d{4})$#', $dob, $m)) {
                $d = (int) $m[1];
                $mo = (int) $m[2];
                $y = (int) $m[3];
                if ($d >= 1 && $d <= 31 && $mo >= 1 && $mo <= 12) {
                    $carbonDob = \Carbon\Carbon::create($y, $mo, $d, 0, 0, 0);
                }
            }
            if ($carbonDob === null) {
                $carbonDob = \Carbon\Carbon::parse($dob);
            }
        } catch (\Throwable $e) {
            Log::warning('[CoordinatingAgent] DOB parse failed', [
                'user_id' => $user->id,
                'raw_dob' => $dob,
                'error' => $e->getMessage(),
            ]);

            return ['error' => true, 'message' => 'Invalid date_of_birth — use YYYY-MM-DD, DD/MM/YYYY, or a natural-language date'];
        }

        $age = (int) $carbonDob->diffInYears(\Carbon\Carbon::now());
        if ($age < 18 || $age > 105) {
            Log::warning('[CoordinatingAgent] DOB outside age bounds', [
                'user_id' => $user->id,
                'parsed_dob' => $carbonDob->format('Y-m-d'),
                'age' => $age,
            ]);

            return ['error' => true, 'message' => 'date_of_birth gives an age outside 18–105'];
        }

        $user->date_of_birth = $carbonDob->format('Y-m-d');
    }

    if ($marital !== '') {
        $allowedMarital = ['single', 'married', 'civil_partnership', 'divorced', 'widowed'];
        if (! in_array($marital, $allowedMarital, true)) {
            Log::warning('[CoordinatingAgent] marital_status not in allowed enum', [
                'user_id' => $user->id,
                'raw_marital' => $marital,
            ]);

            return ['error' => true, 'message' => 'Invalid marital_status'];
        }

        $user->marital_status = $marital;
    }

    $user->save();

    Log::info('[CoordinatingAgent] handleCapturePersonalDetails saved', [
        'user_id' => $user->id,
        'dob' => $user->date_of_birth?->format('Y-m-d'),
        'marital_status' => $user->marital_status,
        'captured_this_turn' => [
            'date_of_birth' => $dob !== '',
            'marital_status' => $marital !== '',
        ],
    ]);

    return [
        'onboarding_capture' => true,
        'field_group' => 'personal',
        'summary' => 'Personal details saved',
        'details' => [
            'date_of_birth' => $user->date_of_birth?->format('Y-m-d'),
            'marital_status' => $user->marital_status,
        ],
    ];
}
```

---

## 2. `capture_spouse_details`

Delegates to `\App\Services\Onboarding\SpouseLinkingService::linkOrCreateSpouse` to either link to an existing user or create a new spouse user account, plus the reciprocal `FamilyMember` rows.

Distinguishes the spouse-collision case (email belongs to another household) so the director can emit a targeted error rather than the generic grouped-extract retry copy (FR-M13).

**Schema** (`AiToolDefinitions.php:1293-1322`):

```php
[
    'name' => 'capture_spouse_details',
    'description' => 'Capture the user\'s spouse or civil partner details from a free-text reply during onboarding. This creates a linked spouse user account. Call this once per turn. Do not call any other tool.',
    'parameters' => [
        'type' => 'object',
        'properties' => [
            'first_name' => ['type' => 'string', 'description' => 'The spouse or partner\'s first name. Extract from phrases like "my partner Jamie" or "called Sarah".'],
            'last_name' => ['type' => 'string', 'description' => 'The spouse or partner\'s last name, if provided. Optional.'],
            'date_of_birth' => ['type' => 'string', 'description' => 'Spouse/partner date of birth in YYYY-MM-DD format. Parse natural language dates into ISO format.'],
            'email' => ['type' => 'string', 'description' => 'The spouse or partner\'s email address. Required — this is used to create their linked account.'],
            'annual_income' => ['type' => 'number', 'description' => 'The spouse or partner\'s rough annual income in GBP, if mentioned. Optional. Strip currency symbols and commas; "75k" = 75000.'],
        ],
        'required' => ['first_name', 'date_of_birth', 'email'],
        'additionalProperties' => false,
    ],
],
```

**Handler** (`CoordinatingAgent.php:1183-1250`):

```php
private function handleCaptureSpouseDetails(array $input, User $user): array
{
    $firstName = trim((string) ($input['first_name'] ?? ''));
    $email = trim((string) ($input['email'] ?? ''));
    $dob = trim((string) ($input['date_of_birth'] ?? ''));

    if ($firstName === '' || $email === '' || $dob === '') {
        return ['error' => true, 'message' => 'first_name, date_of_birth and email are required'];
    }

    // Validate email shape before hitting the service
    if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['error' => true, 'message' => 'Invalid spouse email address'];
    }

    try {
        $dobFormatted = \Carbon\Carbon::parse($dob)->format('Y-m-d');
    } catch (\Throwable $e) {
        return ['error' => true, 'message' => 'Invalid spouse date_of_birth'];
    }

    $service = app(\App\Services\Onboarding\SpouseLinkingService::class);

    try {
        $result = $service->linkOrCreateSpouse($user, [
            'first_name' => $firstName,
            'last_name' => trim((string) ($input['last_name'] ?? '')),
            'date_of_birth' => $dobFormatted,
            'email' => $email,
            'annual_income' => isset($input['annual_income']) ? (float) $input['annual_income'] : null,
        ]);
    } catch (\App\Exceptions\SpouseCollisionException $e) {
        // FR-M13 — distinguish the "email belongs to another household"
        // case so the director can emit a targeted terminal error
        // instead of the generic grouped_extract retry copy.
        return [
            'onboarding_capture_error' => true,
            'field_group' => 'spouse',
            'error_type' => 'spouse_collision',
            'message' => "That email's already registered with another Fynla household. Want to use a different address for your partner, or ask them to link their own account?",
        ];
    } catch (\InvalidArgumentException $e) {
        return ['error' => true, 'message' => $e->getMessage()];
    } catch (\Throwable $e) {
        Log::error('[CoordinatingAgent] handleCaptureSpouseDetails failed', [
            'user_id' => $user->id,
            'error' => $e->getMessage(),
        ]);

        return ['error' => true, 'message' => 'Could not link spouse account. Please try again.'];
    }

    return [
        'onboarding_capture' => true,
        'field_group' => 'spouse',
        'summary' => $result['created_new_user']
            ? 'Spouse account created and linked'
            : 'Spouse account linked',
        'details' => [
            'family_member_id' => $result['family_member']->id,
            'spouse_user_id' => $result['spouse_user']->id,
            'created_new_user' => $result['created_new_user'],
            'already_linked' => $result['already_linked'],
            'email_sent' => $result['email_sent'],
            'first_name' => $firstName,
        ],
    ];
}
```

---

## 3. `capture_dependants`

Bulk-creates `FamilyMember` rows from an array. Age drives `relationship` (child < 18, other_dependent ≥ 18 unless `parent` was specified). DOB is inferred from age (`startOfYear` n years ago).

**Schema** (`AiToolDefinitions.php:1324-1357`):

```php
[
    'name' => 'capture_dependants',
    'description' => 'Capture a list of the user\'s dependants (children or other dependants) from a free-text reply during onboarding. Call this once per turn with an array of all dependants mentioned. Do not call any other tool.',
    'parameters' => [
        'type' => 'object',
        'properties' => [
            'dependants' => [
                'type' => 'array',
                'description' => 'One entry per dependant mentioned. Names may be omitted if the user did not provide them (use null).',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'first_name' => ['type' => 'string', 'description' => 'The dependant\'s first name if mentioned, otherwise null.'],
                        'age' => ['type' => 'integer', 'description' => 'The dependant\'s age in whole years. Required.'],
                        'relationship' => ['type' => 'string', 'enum' => ['child', 'parent', 'other_dependent'], 'description' => 'Child (son, daughter, step-child, etc.), parent (mother, father, in-law), or other_dependent (sibling, nephew, elderly relative, friend).'],
                    ],
                    'required' => ['age', 'relationship'],
                    'additionalProperties' => false,
                ],
            ],
        ],
        'required' => ['dependants'],
        'additionalProperties' => false,
    ],
],
```

**Handler** (`CoordinatingAgent.php:1257-1312`):

```php
private function handleCaptureDependants(array $input, User $user): array
{
    $dependants = is_array($input['dependants'] ?? null) ? $input['dependants'] : [];
    if (count($dependants) === 0) {
        return ['error' => true, 'message' => 'dependants list is empty'];
    }

    $created = [];
    foreach ($dependants as $dep) {
        $age = (int) ($dep['age'] ?? -1);
        if ($age < 0 || $age > 120) {
            continue;
        }

        $relationship = (string) ($dep['relationship'] ?? 'other_dependent');
        if (! in_array($relationship, ['child', 'parent', 'other_dependent'], true)) {
            $relationship = $age < 18 ? 'child' : 'other_dependent';
        }

        $firstName = trim((string) ($dep['first_name'] ?? ''));
        $resolvedName = $firstName !== '' ? $firstName : ($relationship === 'child' ? 'Child' : 'Dependant');

        $familyMember = \App\Models\FamilyMember::create([
            'user_id' => $user->id,
            'household_id' => $user->household_id,
            'relationship' => $relationship,
            'first_name' => $resolvedName,
            'name' => $resolvedName,
            'date_of_birth' => now()->subYears($age)->startOfYear()->toDateString(),
            'is_dependent' => true,
            'education_status' => $this->educationStatusForAge($age),
            'notes' => 'Captured via Fyn onboarding.',
        ]);

        $created[] = [
            'id' => $familyMember->id,
            'first_name' => $familyMember->first_name,
            'age' => $age,
            'relationship' => $relationship,
        ];
    }

    if (count($created) === 0) {
        return ['error' => true, 'message' => 'No valid dependants could be saved'];
    }

    return [
        'onboarding_capture' => true,
        'field_group' => 'dependants',
        'summary' => count($created).' dependant'.(count($created) === 1 ? '' : 's').' saved',
        'details' => [
            'count' => count($created),
            'dependants' => $created,
        ],
    ];
}
```

Helper used: `educationStatusForAge` — full source in `09-shared-helpers.md`.

---

## 4. `capture_work_details`

Accepts partial payloads — whichever non-empty fields are present get written. Reports back any still-missing fields so the director can emit a targeted retry. **Self-employed users:** income lands on `annual_self_employment_income` instead of `annual_employment_income`.

**Schema** (`AiToolDefinitions.php:1359-1380`):

```php
[
    'name' => 'capture_work_details',
    'description' => 'Capture the user\'s employer, position, and annual income from a free-text reply during onboarding. Only used when the user is employed, self-employed, or part-time. Call this once per turn. Do not call any other tool.',
    'parameters' => [
        'type' => 'object',
        'properties' => [
            'employer' => ['type' => 'string', 'description' => 'The company or employer name. For self-employed users this may be their trading name or "self-employed".'],
            'occupation' => ['type' => 'string', 'description' => 'The user\'s job title or role. e.g. "Software engineer", "Sole trader", "Consultant".'],
            'annual_income' => ['type' => 'number', 'description' => 'Gross annual income in GBP. Strip currency symbols and commas; "75k" = 75000. For self-employed users this is self-employment income.'],
        ],
        'required' => ['employer', 'occupation', 'annual_income'],
        'additionalProperties' => false,
    ],
],
```

**Handler** (`CoordinatingAgent.php:1325-1380`):

```php
private function handleCaptureWorkDetails(array $input, User $user): array
{
    $employer = trim((string) ($input['employer'] ?? ''));
    $occupation = trim((string) ($input['occupation'] ?? ''));
    $incomeRaw = $input['annual_income'] ?? null;
    $income = ($incomeRaw === null || $incomeRaw === '') ? null : (float) $incomeRaw;

    if ($income !== null && $income > 99_999_999) {
        return ['error' => true, 'message' => 'annual_income exceeds permitted range'];
    }
    if ($income !== null && $income < 0) {
        $income = null;
    }

    $incomeField = $user->employment_status === 'self_employed'
        ? 'annual_self_employment_income'
        : 'annual_employment_income';

    if ($employer !== '') {
        $user->employer = $employer;
    }
    if ($occupation !== '') {
        $user->occupation = $occupation;
    }
    if ($income !== null) {
        $user->{$incomeField} = $income;
    }

    $user->save();

    $missing = [];
    if (trim((string) ($user->employer ?? '')) === '') {
        $missing[] = 'employer';
    }
    if (trim((string) ($user->occupation ?? '')) === '') {
        $missing[] = 'occupation';
    }
    if ((float) ($user->{$incomeField} ?? 0) <= 0) {
        $missing[] = 'annual_income';
    }

    return [
        'onboarding_capture' => true,
        'field_group' => 'work',
        'summary' => count($missing) === 0
            ? 'Work details saved'
            : 'Partial work details saved — still need: '.implode(', ', $missing),
        'details' => [
            'employer' => $user->employer,
            'occupation' => $user->occupation,
            'annual_income' => (float) ($user->{$incomeField} ?? 0),
            'income_field' => $incomeField,
            'missing' => $missing,
        ],
    ];
}
```
