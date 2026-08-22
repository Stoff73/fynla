# Financial creation tools (8)

These tools create core financial records: savings, investments, holdings, pensions, properties, mortgages, protection policies. **All are stripped from advice mode** (`AdviceFyn::WRITE_TOOLS`) and **all preview-block** via `previewBlocked()` — the LLM gets back `['blocked' => true, 'reason' => …]` for preview personas.

> Source files:
> - Schemas (Anthropic): `app/Services/AI/AiToolDefinitions.php`
> - Schemas (xAI strict): `app/Services/AI/XaiToolDefinitions.php`
> - Handlers: `app/Agents/CoordinatingAgent.php`

Common helpers used across these handlers (full source in `09-shared-helpers.md`):
- `previewBlocked(string $entityType): array`
- `validateToolInput(array $input, array $rules): ?array`
- `checkForDuplicate(string $modelClass, int $userId, string $nameField, string $nameValue): ?array`
- `invalidateUserCache(int $userId): void`

---

## 1. `create_savings_account`

**Schema** (`AiToolDefinitions::accountCreationTools()` — `AiToolDefinitions.php:344-387`):

```php
[
    'name' => 'create_savings_account',
    'description' => 'Create a savings account for the user. Use this when the user mentions a savings account, Cash Individual Savings Account, or cash deposit. You MAY call this tool multiple times in the same turn when the user mentions multiple accounts.',
    'parameters' => [
        'type' => 'object',
        'properties' => [
            'account_name' => ['type' => 'string', 'description' => 'Name of the account (e.g., "Nationwide Cash ISA", "Halifax Easy Saver")'],
            'account_type' => ['type' => 'string', 'enum' => ['easy_access', 'notice', 'fixed_term', 'regular_saver'], 'description' => 'Type of savings account. Default to "easy_access" if not specified.'],
            'institution' => ['type' => 'string', 'description' => 'Bank or building society name (e.g., "Nationwide", "Halifax")'],
            'current_balance' => ['type' => 'number', 'description' => 'Current balance in pounds'],
            'interest_rate' => ['type' => 'number', 'description' => 'Annual interest rate as a percentage (e.g., 4.5 for 4.5%)'],
            'is_isa' => ['type' => 'boolean', 'description' => 'Whether this is a Cash Individual Savings Account. Default false.'],
            'is_emergency_fund' => ['type' => 'boolean', 'description' => 'Whether this forms part of the emergency fund. Default false.'],
            'regular_contribution_amount' => ['type' => 'number', 'description' => 'Monthly contribution amount in pounds, if any'],
        ],
        'required' => ['account_name', 'current_balance'],
        'additionalProperties' => false,
    ],
],
```

**Handler** (`CoordinatingAgent.php:2023-2106`):

```php
private function handleCreateSavingsAccount(array $input, User $user, bool $isPreview): array
{
    if ($isPreview) {
        return $this->previewBlocked('savings account');
    }

    $validationError = $this->validateToolInput($input, [
        'account_name' => 'required|string|max:255',
        'current_balance' => 'required|numeric|min:0|max:999999999.99',
        'account_type' => ['nullable', Rule::in(['easy_access', 'notice', 'fixed_term', 'regular_saver', 'savings_account', 'current_account', 'instant_access', 'fixed', 'cash_isa', 'junior_isa', 'premium_bonds', 'nsi'])],
        'institution' => 'nullable|string|max:255',
        'interest_rate' => 'nullable|numeric|min:0|max:25',
        'is_isa' => 'nullable|boolean',
        'is_emergency_fund' => 'nullable|boolean',
        'regular_contribution_amount' => 'nullable|numeric|min:0|max:999999.99',
    ]);
    if ($validationError) {
        return $validationError;
    }

    $duplicateCheck = $this->checkForDuplicate(SavingsAccount::class, $user->id, 'account_name', $input['account_name']);
    if ($duplicateCheck) {
        return $duplicateCheck;
    }

    $isIsa = (bool) ($input['is_isa'] ?? false);
    $accountType = $input['account_type'] ?? 'easy_access';

    // AI tool enum → canonical DB value. `fixed_term`/`regular_saver` are
    // AI-facing conveniences that map onto existing DB categories.
    $dbAccountType = match ($accountType) {
        'fixed_term' => 'fixed',
        'regular_saver' => 'easy_access',
        default => $accountType,
    };

    // ISA inference — if flagged ISA but account_type isn't already an
    // ISA variant, promote to cash_isa so downstream ISA tracking works.
    if ($isIsa && ! in_array($dbAccountType, ['cash_isa', 'junior_isa'], true)) {
        $dbAccountType = 'cash_isa';
    }

    $accessType = match ($dbAccountType) {
        'notice' => 'notice',
        'fixed' => 'fixed',
        default => 'immediate',
    };

    $payload = [
        'user_id' => $user->id,
        'account_name' => $input['account_name'],
        'institution' => ! empty($input['institution']) ? $input['institution'] : $input['account_name'],
        'account_type' => $dbAccountType,
        'current_balance' => (float) $input['current_balance'],
        'access_type' => $accessType,
        'is_isa' => $isIsa,
        'is_emergency_fund' => (bool) ($input['is_emergency_fund'] ?? false),
        'ownership_type' => 'individual',
        'ownership_percentage' => 100.00,
    ];

    // interest_rate / regular_contribution_amount are optional on the AI
    // tool; only include them when actually supplied so DB defaults apply.
    if (isset($input['interest_rate'])) {
        $payload['interest_rate'] = (float) $input['interest_rate'];
    }
    if (isset($input['regular_contribution_amount'])) {
        $payload['regular_contribution_amount'] = (float) $input['regular_contribution_amount'];
    }

    $account = DB::transaction(fn () => SavingsAccount::create($payload));

    $this->invalidateUserCache($user->id);

    return [
        'success' => true,
        'created' => true,
        'entity_type' => 'savings_account',
        'entity_id' => $account->id,
        'name' => $account->account_name,
        'persisted_fields' => array_keys(array_diff_key($payload, ['user_id' => null])),
        'message' => "I've added your \"{$account->account_name}\" savings account.",
    ];
}
```

---

## 2. `create_investment_account`

**Schema** (`AiToolDefinitions.php:388-557`) — long schema covering ISAs, GIA, bonds, VCT, EIS, private company, crowdfunding, and employee share schemes (SAYE, CSOP, EMI, RSU, unapproved options):

```php
[
    'name' => 'create_investment_account',
    'description' => 'Create an investment account for the user. Use this when the user mentions any investment: ISA, GIA, bond, VCT, EIS, private company shares, crowdfunding, employee share schemes (SAYE, CSOP, EMI, share options, RSUs), or other investments. You MAY call this tool multiple times in the same turn when the user mentions multiple accounts.',
    'parameters' => [
        'type' => 'object',
        'properties' => [
            'account_name' => ['type' => 'string', 'description' => 'Name of the account (e.g., "Vanguard Stocks & Shares ISA", "Hargreaves Lansdown GIA", "Octopus VCT")'],
            'account_type' => [
                'type' => 'string',
                'enum' => [
                    'stocks_shares_isa', 'lifetime_isa', 'personal_investment_account',
                    'onshore_bond', 'offshore_bond', 'vct', 'eis',
                    'private_company', 'crowdfunding', 'saye', 'csop',
                    'emi', 'unapproved_options', 'rsu', 'other',
                ],
                'description' => 'Type of investment account. Use "stocks_shares_isa" for Stocks & Shares ISA, "lifetime_isa" for Lifetime ISA, "personal_investment_account" for GIA, "vct" for Venture Capital Trust, "eis" for Enterprise Investment Scheme, "private_company" for private company shares, "crowdfunding" for crowdfunding investments, "saye" for Save As You Earn/Sharesave, "csop" for Company Share Option Plan, "emi" for Enterprise Management Incentives, "unapproved_options" for unapproved share options, "rsu" for Restricted Stock Units, "other" for anything else. Default to "personal_investment_account" if not specified.',
            ],
            'provider' => ['type' => 'string', 'description' => 'Platform, provider, or company name (e.g., "Vanguard", "Hargreaves Lansdown", "Octopus Investments")'],
            'current_value' => ['type' => 'number', 'description' => 'Current value in pounds'],
            'monthly_contribution_amount' => ['type' => 'number', 'description' => 'Monthly contribution amount in pounds, if any'],
            'platform_fee_percent' => ['type' => 'number', 'description' => 'Annual platform fee as a percentage (e.g., 0.15 for 0.15%)'],
            // Bond-specific fields (onshore_bond, offshore_bond)
            'bond_purchase_date' => ['type' => 'string', 'description' => 'Bond purchase date in YYYY-MM-DD format. Only for onshore_bond or offshore_bond.'],
            'bond_withdrawal_taken' => ['type' => 'number', 'description' => 'Total 5% tax-deferred withdrawals taken to date in pounds. Only for onshore_bond or offshore_bond.'],
            // Private company / Crowdfunding fields
            'company_legal_name' => ['type' => 'string', 'description' => 'Legal name of the company. For private_company or crowdfunding types.'],
            'company_registration_number' => ['type' => 'string', 'description' => 'Companies House registration number. For private_company or crowdfunding types.'],
            'crowdfunding_platform' => ['type' => 'string', 'enum' => ['Seedrs', 'Crowdcube', 'Republic', 'Wefunder', 'other'], 'description' => 'Crowdfunding platform name. Only for crowdfunding type.'],
            'investment_date' => ['type' => 'string', 'description' => 'Date of investment in YYYY-MM-DD format. For private_company, crowdfunding, vct, eis.'],
            'investment_amount' => ['type' => 'number', 'description' => 'Original investment amount in pounds. For private_company, crowdfunding, vct, eis.'],
            'number_of_shares' => ['type' => 'number', 'description' => 'Number of shares held. For private_company, crowdfunding, vct, eis.'],
            'price_per_share' => ['type' => 'number', 'description' => 'Price per share in pounds. For private_company, crowdfunding, vct, eis.'],
            'instrument_type' => ['type' => 'string', 'enum' => ['ordinary_shares', 'preference_shares', 'convertible_loan_note', 'safe', 'revenue_share', 'fund_nominee_interest'], 'description' => 'Type of instrument held. For private_company or crowdfunding.'],
            'funding_round' => ['type' => 'string', 'enum' => ['pre_seed', 'seed', 'series_a', 'series_b', 'series_c', 'bridge', 'safe', 'other'], 'description' => 'Funding round. For private_company or crowdfunding.'],
            'share_class' => ['type' => 'string', 'description' => 'Share class (e.g., "A Ordinary", "B Preference"). For private_company or crowdfunding.'],
            'tax_relief_type' => ['type' => 'string', 'enum' => ['eis', 'seis', 'sitr', 'vct', ''], 'description' => 'Tax relief scheme applied. For private_company, crowdfunding, vct, eis.'],
            // Employee share scheme fields (saye, csop, emi, unapproved_options, rsu)
            'employer_name' => ['type' => 'string', 'description' => 'Employer company name. For employee share schemes (saye, csop, emi, unapproved_options, rsu).'],
            'employer_is_listed' => ['type' => 'boolean', 'description' => 'Whether shares are publicly listed/traded. For employee share schemes.'],
            'grant_date' => ['type' => 'string', 'description' => 'Date options/shares were granted in YYYY-MM-DD format. For employee share schemes.'],
            'units_granted' => ['type' => 'number', 'description' => 'Number of units/options granted. For employee share schemes.'],
            'exercise_price' => ['type' => 'number', 'description' => 'Exercise/strike price per share in pounds. For saye, csop, emi, unapproved_options.'],
            'market_value_at_grant' => ['type' => 'number', 'description' => 'Market value per share at grant date in pounds. For employee share schemes.'],
            'current_share_price' => ['type' => 'number', 'description' => 'Current share price in pounds. For employee share schemes.'],
            'units_vested' => ['type' => 'number', 'description' => 'Number of units currently vested. For employee share schemes.'],
            'units_unvested' => ['type' => 'number', 'description' => 'Number of units not yet vested. For employee share schemes.'],
            'vesting_type' => ['type' => 'string', 'enum' => ['cliff', 'monthly', 'quarterly', 'annual', 'performance', 'immediate'], 'description' => 'Vesting schedule type. For employee share schemes.'],
            'full_vest_date' => ['type' => 'string', 'description' => 'Date all units fully vest in YYYY-MM-DD format. For employee share schemes.'],
            'cliff_date' => ['type' => 'string', 'description' => 'Cliff vesting date in YYYY-MM-DD format. For employee share schemes with cliff vesting.'],
            'cliff_percentage' => ['type' => 'number', 'description' => 'Percentage that vests at cliff (e.g., 25). For employee share schemes with cliff vesting.'],
            // SAYE-specific fields
            'saye_monthly_savings' => ['type' => 'number', 'description' => 'Monthly savings amount (max £500). Only for saye type.'],
            'saye_current_savings_balance' => ['type' => 'number', 'description' => 'Current savings balance in pounds. Only for saye type.'],
            'scheme_start_date' => ['type' => 'string', 'description' => 'SAYE contract start date in YYYY-MM-DD format. Only for saye type.'],
            'scheme_duration_months' => ['type' => 'number', 'enum' => [36, 60], 'description' => 'SAYE contract duration: 36 (3 years) or 60 (5 years). Only for saye type.'],
        ],
        'required' => ['account_name', 'current_value'],
        'additionalProperties' => false,
    ],
],
```

**Handler** (`CoordinatingAgent.php:2108-2241`):

```php
private function handleCreateInvestmentAccount(array $input, User $user, bool $isPreview): array
{
    if ($isPreview) {
        return $this->previewBlocked('investment account');
    }

    $validationError = $this->validateToolInput($input, [
        'account_name' => 'required|string|max:255',
        'current_value' => 'required|numeric|min:0|max:999999999.99',
        'account_type' => ['nullable', Rule::in([
            'stocks_shares_isa', 'lifetime_isa', 'personal_investment_account',
            'onshore_bond', 'offshore_bond', 'vct', 'eis',
            'private_company', 'crowdfunding', 'saye', 'csop',
            'emi', 'unapproved_options', 'rsu', 'other',
            // DB-canonical values (the HTTP form posts these directly)
            'isa', 'gia',
        ])],
        'provider' => 'nullable|string|max:255',
        'monthly_contribution_amount' => 'nullable|numeric|min:0|max:999999.99',
        'platform_fee_percent' => 'nullable|numeric|min:0|max:10',
        'ownership_type' => ['nullable', Rule::in(['individual', 'joint', 'tenants_in_common', 'trust'])],
        'ownership_percentage' => 'nullable|numeric|min:0|max:100',
    ]);
    if ($validationError) {
        return $validationError;
    }

    $duplicateCheck = $this->checkForDuplicate(InvestmentAccount::class, $user->id, 'account_name', $input['account_name']);
    if ($duplicateCheck) {
        return $duplicateCheck;
    }

    $accountType = $input['account_type'] ?? 'personal_investment_account';

    // AI-facing enums → DB canonical values (mirrors the existing
    // form-fill mapping so AI direct-write and HTTP form path persist
    // identical rows).
    $dbAccountType = match ($accountType) {
        'stocks_shares_isa', 'lifetime_isa' => 'isa',
        'personal_investment_account' => 'gia',
        default => $accountType,
    };

    $isaType = match ($accountType) {
        'stocks_shares_isa' => 'stocks_shares',
        'lifetime_isa' => 'lifetime',
        default => null,
    };

    // ISAs are individually owned per UK tax rules. Mirrors the HTTP
    // controller's hard rejection.
    $ownershipType = $input['ownership_type'] ?? 'individual';
    if ($dbAccountType === 'isa' && $ownershipType !== 'individual') {
        return [
            'error' => true,
            'error_type' => 'validation_failed',
            'message' => 'ISAs can only be individually owned under UK tax rules.',
        ];
    }

    $payload = [
        'user_id' => $user->id,
        'account_name' => $input['account_name'],
        'provider' => ! empty($input['provider']) ? $input['provider'] : $input['account_name'],
        'account_type' => $dbAccountType,
        'current_value' => (float) $input['current_value'],
        'ownership_type' => $ownershipType,
        'ownership_percentage' => isset($input['ownership_percentage'])
            ? (float) $input['ownership_percentage']
            : 100.00,
    ];

    if ($isaType !== null) {
        $payload['isa_type'] = $isaType;
    }

    // Optional numeric / string fields — only persist when supplied.
    $optionalNumeric = [
        'monthly_contribution_amount', 'platform_fee_percent',
        'investment_amount', 'number_of_shares', 'price_per_share',
        'units_granted', 'exercise_price', 'market_value_at_grant',
        'current_share_price', 'units_vested', 'units_unvested',
        'cliff_percentage', 'saye_monthly_savings',
        'saye_current_savings_balance', 'scheme_duration_months',
    ];
    foreach ($optionalNumeric as $field) {
        if (isset($input[$field]) && $input[$field] !== '' && is_numeric($input[$field])) {
            $payload[$field] = (float) $input[$field];
        }
    }

    $optionalString = [
        'company_legal_name', 'company_registration_number',
        'crowdfunding_platform', 'instrument_type', 'funding_round',
        'share_class', 'tax_relief_type', 'employer_name',
        'vesting_type',
    ];
    foreach ($optionalString as $field) {
        if (isset($input[$field]) && $input[$field] !== '') {
            $payload[$field] = (string) $input[$field];
        }
    }

    $optionalDate = [
        'bond_purchase_date', 'investment_date', 'grant_date',
        'full_vest_date', 'cliff_date', 'scheme_start_date',
    ];
    foreach ($optionalDate as $field) {
        if (isset($input[$field]) && $input[$field] !== '') {
            $payload[$field] = $input[$field];
        }
    }

    if (isset($input['bond_withdrawal_taken'])) {
        $payload['bond_withdrawal_taken'] = (float) $input['bond_withdrawal_taken'];
    }
    if (isset($input['employer_is_listed'])) {
        $payload['employer_is_listed'] = (bool) $input['employer_is_listed'];
    }

    $account = DB::transaction(fn () => InvestmentAccount::create($payload));

    $this->invalidateUserCache($user->id);

    return [
        'success' => true,
        'created' => true,
        'entity_type' => 'investment_account',
        'entity_id' => $account->id,
        'name' => $account->account_name,
        'persisted_fields' => array_keys(array_diff_key($payload, ['user_id' => null])),
        'message' => "I've added your \"{$account->account_name}\" investment account.",
    ];
}
```

---

## 3. `create_holding`

**Schema** (`AiToolDefinitions.php:559-597`):

```php
[
    'name' => 'create_holding',
    'description' => 'Add a holding to an EXISTING investment account that was already created WITHOUT holdings. Use this ONLY when the user wants to add holdings to an account that already exists. If the user is creating a NEW account AND mentions holdings at the same time, use create_investment_account with the holdings parameter instead. You MAY call this tool multiple times in the same turn when the user mentions multiple holdings.',
    'parameters' => [
        'type' => 'object',
        'properties' => [
            'account_name' => ['type' => 'string', 'description' => 'Name or provider of the existing investment account to add the holding to.'],
            'security_name' => ['type' => 'string', 'description' => 'Name of the fund, ETF, or share (e.g. "Vanguard FTSE All-World").'],
            'ticker' => ['type' => 'string', 'description' => 'Ticker symbol (e.g. "VWRL", "SWDA").'],
            'asset_type' => ['type' => 'string', 'enum' => ['uk_equity', 'us_equity', 'international_equity', 'fund', 'etf', 'bond', 'cash', 'alternative', 'property'], 'description' => '"fund" for OEICs/unit trusts, "etf" for ETFs, "uk_equity" / "us_equity" / "international_equity" for shares, "bond" for fixed income, "cash", "alternative" for commodities/crypto, "property" for property funds.'],
            'allocation_percent' => ['type' => 'number', 'description' => 'Percentage of the account this holding represents (0-100).'],
            'current_price' => ['type' => 'number', 'description' => 'Current price per unit in pounds.'],
            'ocf_percent' => ['type' => 'number', 'description' => 'Ongoing Charge Figure as percentage (e.g. 0.22 for 0.22%).'],
        ],
        'required' => ['account_name', 'security_name', 'asset_type'],
        'additionalProperties' => false,
    ],
],
```

**Handler** (`CoordinatingAgent.php:2243-2323`):

```php
private function handleCreateHolding(array $input, User $user, bool $isPreview): array
{
    if ($isPreview) {
        return $this->previewBlocked('investment holding');
    }

    $validationError = $this->validateToolInput($input, [
        'account_name' => 'required|string|max:255',
        'security_name' => 'required|string|max:255',
        'ticker' => 'nullable|string|max:20',
        'asset_type' => ['required', Rule::in(['uk_equity', 'us_equity', 'international_equity', 'fund', 'etf', 'bond', 'cash', 'alternative', 'property'])],
        'allocation_percent' => 'nullable|numeric|min:0|max:100',
        'purchase_price' => 'nullable|numeric|min:0|max:999999.99',
        'current_price' => 'nullable|numeric|min:0|max:999999.99',
        'ocf_percent' => 'nullable|numeric|min:0|max:10',
    ]);
    if ($validationError) {
        return $validationError;
    }

    // Look up the investment account by name/provider for this user
    $account = \App\Models\Investment\InvestmentAccount::where('user_id', $user->id)
        ->where(function ($query) use ($input) {
            $query->where('provider', 'LIKE', '%'.$input['account_name'].'%')
                ->orWhere('account_name', 'LIKE', '%'.$input['account_name'].'%');
        })
        ->orderByDesc('id')
        ->first();

    if (! $account) {
        return [
            'error' => true,
            'message' => "I couldn't find an investment account matching \"{$input['account_name']}\". Please create the account first, then I can add holdings to it.",
        ];
    }

    $allocationPct = isset($input['allocation_percent']) ? (float) $input['allocation_percent'] : null;
    $accountCurrentValue = (float) ($account->current_value ?? 0);
    $currentValue = $allocationPct !== null
        ? round(($allocationPct / 100) * $accountCurrentValue, 2)
        : 0.0;

    $payload = [
        'holdable_id' => $account->id,
        'holdable_type' => InvestmentAccount::class,
        'security_name' => $input['security_name'],
        'asset_type' => $input['asset_type'],
        'current_value' => $currentValue,
    ];

    if ($allocationPct !== null) {
        $payload['allocation_percent'] = $allocationPct;
    }
    foreach (['ticker', 'isin'] as $field) {
        if (isset($input[$field]) && $input[$field] !== '') {
            $payload[$field] = $input[$field];
        }
    }
    foreach (['purchase_price', 'current_price', 'ocf_percent'] as $field) {
        if (isset($input[$field]) && is_numeric($input[$field])) {
            $payload[$field] = (float) $input[$field];
        }
    }
    if (isset($input['purchase_date']) && $input['purchase_date'] !== '') {
        $payload['purchase_date'] = $input['purchase_date'];
    }

    $holding = DB::transaction(fn () => Holding::create($payload));

    $this->invalidateUserCache($user->id);

    return [
        'success' => true,
        'created' => true,
        'entity_type' => 'investment_holding',
        'entity_id' => $holding->id,
        'name' => $holding->security_name,
        'persisted_fields' => array_keys($payload),
        'message' => "I've added \"{$holding->security_name}\" to your {$account->provider} account.",
    ];
}
```

---

## 4. `create_pension`

Handles BOTH Defined Contribution (DC) and Defined Benefit (DB) pensions, dispatching to the appropriate model based on `pension_category`.

**Schema** (`AiToolDefinitions.php:599-650`):

```php
[
    'name' => 'create_pension',
    'description' => 'Create a pension for the user. Handles both Defined Contribution (workplace, Self-Invested Personal Pension, personal) and Defined Benefit (final salary, career average) pensions. You MAY call this tool multiple times in the same turn when the user mentions multiple pensions.',
    'parameters' => [
        'type' => 'object',
        'properties' => [
            'pension_category' => ['type' => 'string', 'enum' => ['dc', 'db'], 'description' => 'Whether this is a Defined Contribution (dc) or Defined Benefit (db) pension. Default "dc" for workplace/SIPP/personal pensions. Use "db" for final salary or career average schemes.'],
            'scheme_name' => ['type' => 'string', 'description' => 'Name of the pension scheme (e.g., "Aviva Workplace Pension", "NHS Pension Scheme")'],
            'scheme_type' => ['type' => 'string', 'description' => 'For DC: "workplace", "sipp", or "personal_pension". For DB: "final_salary", "career_average", or "public_sector".'],
            'provider' => ['type' => 'string', 'description' => 'Pension provider (e.g., "Aviva", "Scottish Widows"). DC pensions only.'],
            'current_fund_value' => ['type' => 'number', 'description' => 'Current fund value in pounds. DC pensions only.'],
            'employee_contribution_percent' => ['type' => 'number', 'description' => 'Employee contribution as percentage of salary (e.g., 5 for 5%). DC pensions only.'],
            'employer_contribution_percent' => ['type' => 'number', 'description' => 'Employer contribution as percentage of salary (e.g., 3 for 3%). DC pensions only.'],
            'accrued_annual_pension' => ['type' => 'number', 'description' => 'Accrued annual pension in pounds. DB pensions only.'],
            'normal_retirement_age' => ['type' => 'integer', 'description' => 'Normal retirement age for the scheme. DB pensions only.'],
            'pensionable_service_years' => ['type' => 'number', 'description' => 'Years of pensionable service. DB pensions only.'],
        ],
        'required' => ['pension_category', 'scheme_name'],
        'additionalProperties' => false,
    ],
],
```

**Handler** (`CoordinatingAgent.php:2325-2435`):

```php
private function handleCreatePension(array $input, User $user, bool $isPreview): array
{
    if ($isPreview) {
        return $this->previewBlocked('pension');
    }

    $validationError = $this->validateToolInput($input, [
        'pension_category' => ['required', Rule::in(['dc', 'db'])],
        'scheme_name' => 'required|string|max:255',
        'current_fund_value' => 'nullable|numeric|min:0|max:999999999.99',
        'employee_contribution_percent' => 'nullable|numeric|min:0|max:100',
        'employer_contribution_percent' => 'nullable|numeric|min:0|max:100',
        'accrued_annual_pension' => 'nullable|numeric|min:0|max:999999.99',
        'normal_retirement_age' => 'nullable|integer|min:50|max:75',
    ]);
    if ($validationError) {
        return $validationError;
    }

    $dcDuplicate = $this->checkForDuplicate(DCPension::class, $user->id, 'scheme_name', $input['scheme_name']);
    if ($dcDuplicate) {
        return $dcDuplicate;
    }
    $dbDuplicate = $this->checkForDuplicate(DBPension::class, $user->id, 'scheme_name', $input['scheme_name']);
    if ($dbDuplicate) {
        return $dbDuplicate;
    }

    $category = $input['pension_category'] ?? 'dc';
    $schemeName = $input['scheme_name'];

    if ($category === 'db') {
        // DB pensions: scheme_type column is NOT NULL with enum
        // (final_salary|career_average|public_sector). The DB form's
        // free-text "scheme_type" field overlaps semantically — keep the
        // existing default of final_salary when the AI didn't pin it.
        $rawSchemeType = $input['scheme_type'] ?? 'final_salary';
        $schemeType = in_array($rawSchemeType, ['final_salary', 'career_average', 'public_sector'], true)
            ? $rawSchemeType
            : 'final_salary';

        $payload = [
            'user_id' => $user->id,
            'scheme_name' => $schemeName,
            'scheme_type' => $schemeType,
        ];

        foreach (['accrued_annual_pension', 'pensionable_service_years', 'pensionable_salary', 'spouse_pension_percent', 'lump_sum_entitlement'] as $f) {
            if (isset($input[$f]) && is_numeric($input[$f])) {
                $payload[$f] = (float) $input[$f];
            }
        }
        if (isset($input['normal_retirement_age']) && is_numeric($input['normal_retirement_age'])) {
            $payload['normal_retirement_age'] = (int) $input['normal_retirement_age'];
        }
        foreach (['revaluation_method', 'inflation_protection'] as $f) {
            if (isset($input[$f]) && $input[$f] !== '') {
                $payload[$f] = $input[$f];
            }
        }

        $pension = DB::transaction(fn () => DBPension::create($payload));
        $entityType = 'db_pension';
    } else {
        // DC pensions: pension_type NOT NULL enum
        // (occupational|sipp|personal|stakeholder), defaults to occupational.
        $pensionType = match ($input['scheme_type'] ?? 'workplace') {
            'workplace', 'occupational' => 'occupational',
            'sipp', 'self_invested' => 'sipp',
            'personal', 'personal_pension' => 'personal',
            'stakeholder' => 'stakeholder',
            default => 'occupational',
        };

        $payload = [
            'user_id' => $user->id,
            'scheme_name' => $schemeName,
            'pension_type' => $pensionType,
            'provider' => ! empty($input['provider']) ? $input['provider'] : $schemeName,
        ];

        foreach (['current_fund_value', 'annual_salary', 'employee_contribution_percent', 'employer_contribution_percent', 'employer_matching_limit', 'monthly_contribution_amount', 'lump_sum_contribution', 'expected_return_percent', 'platform_fee_percent', 'advisor_fee_percent'] as $f) {
            if (isset($input[$f]) && is_numeric($input[$f])) {
                $payload[$f] = (float) $input[$f];
            }
        }
        if (isset($input['retirement_age']) && is_numeric($input['retirement_age'])) {
            $payload['retirement_age'] = (int) $input['retirement_age'];
        }
        foreach (['member_number', 'investment_strategy'] as $f) {
            if (isset($input[$f]) && $input[$f] !== '') {
                $payload[$f] = $input[$f];
            }
        }

        $pension = DB::transaction(fn () => DCPension::create($payload));
        $entityType = 'dc_pension';
    }

    $this->invalidateUserCache($user->id);

    return [
        'success' => true,
        'created' => true,
        'entity_type' => $entityType,
        'entity_id' => $pension->id,
        'name' => $pension->scheme_name,
        'persisted_fields' => array_keys(array_diff_key($payload, ['user_id' => null])),
        'message' => "I've added your \"{$pension->scheme_name}\" pension.",
    ];
}
```

---

## 5. `create_property`

**Schema** (`AiToolDefinitions.php:657-708`):

```php
[
    'name' => 'create_property',
    'description' => 'Create a property for the user. If they also mention a mortgage, include the outstanding mortgage amount and it will be created automatically. You MAY call this tool multiple times in the same turn when the user mentions multiple properties — the frontend queue saves them in order. Do NOT call navigate_to_page or get_module_analysis in the same turn as create_property — those interrupt the form fill.',
    'parameters' => [
        'type' => 'object',
        'properties' => [
            'property_type' => ['type' => 'string', 'enum' => ['main_residence', 'secondary_residence', 'buy_to_let'], 'description' => 'Type of property. Default "main_residence" if this is their home.'],
            'current_value' => ['type' => 'number', 'description' => 'Current estimated value in pounds'],
            'purchase_price' => ['type' => 'number', 'description' => 'Original purchase price in pounds'],
            'purchase_date' => ['type' => 'string', 'format' => 'date', 'description' => 'Purchase date in YYYY-MM-DD format (approximate year is fine, e.g., "2018-01-01")'],
            'address_line_1' => ['type' => 'string', 'description' => 'Street address or description'],
            'postcode' => ['type' => 'string', 'description' => 'UK postcode'],
            'outstanding_mortgage' => ['type' => 'number', 'description' => 'Outstanding mortgage balance in pounds. If provided, a linked mortgage will be created automatically.'],
            'mortgage_rate' => ['type' => 'number', 'description' => 'Mortgage interest rate as a percentage (e.g., 4.2 for 4.2%). Only used if outstanding_mortgage is provided.'],
            'mortgage_lender' => ['type' => 'string', 'description' => 'Mortgage lender name. Only used if outstanding_mortgage is provided.'],
            'monthly_rental_income' => ['type' => 'number', 'description' => 'Monthly rental income in pounds. For buy-to-let properties.'],
        ],
        'required' => ['property_type', 'current_value'],
        'additionalProperties' => false,
    ],
],
```

**Handler** (`CoordinatingAgent.php:2437-2566`):

```php
private function handleCreateProperty(array $input, User $user, bool $isPreview): array
{
    if ($isPreview) {
        return $this->previewBlocked('property');
    }

    $validationError = $this->validateToolInput($input, [
        'property_type' => ['required', Rule::in(['main_residence', 'secondary_residence', 'buy_to_let'])],
        'current_value' => 'required|numeric|min:0|max:999999999.99',
        'purchase_price' => 'nullable|numeric|min:0|max:999999999.99',
        'ownership_type' => ['nullable', Rule::in(['individual', 'joint', 'tenants_in_common', 'trust'])],
        'ownership_percentage' => 'nullable|numeric|min:0|max:100',
        'tenure_type' => ['nullable', Rule::in(['freehold', 'leasehold'])],
        'lease_remaining_years' => 'nullable|integer|min:0|max:999',
        'has_mortgage' => 'nullable|boolean',
        'mortgage_outstanding_balance' => 'nullable|numeric|min:0|max:999999999.99',
        'mortgage_interest_rate' => 'nullable|numeric|min:0|max:25',
        'mortgage_monthly_payment' => 'nullable|numeric|min:0|max:999999.99',
        'mortgage_type' => ['nullable', Rule::in(['repayment', 'interest_only', 'mixed'])],
        'mortgage_rate_type' => ['nullable', Rule::in(['fixed', 'variable', 'tracker', 'discount', 'mixed'])],
        'monthly_rental_income' => 'nullable|numeric|min:0|max:999999.99',
        'monthly_council_tax' => 'nullable|numeric|min:0|max:99999.99',
        'monthly_gas' => 'nullable|numeric|min:0|max:99999.99',
        'monthly_electricity' => 'nullable|numeric|min:0|max:99999.99',
        'monthly_water' => 'nullable|numeric|min:0|max:99999.99',
        'monthly_building_insurance' => 'nullable|numeric|min:0|max:99999.99',
        'monthly_contents_insurance' => 'nullable|numeric|min:0|max:99999.99',
        'monthly_service_charge' => 'nullable|numeric|min:0|max:99999.99',
        'monthly_maintenance_reserve' => 'nullable|numeric|min:0|max:99999.99',
        'other_monthly_costs' => 'nullable|numeric|min:0|max:99999.99',
        // Legacy field names from AiToolDefinitions (Anthropic path)
        'outstanding_mortgage' => 'nullable|numeric|min:0|max:999999999.99',
        'mortgage_rate' => 'nullable|numeric|min:0|max:25',
    ]);
    if ($validationError) {
        return $validationError;
    }

    $propertyType = $input['property_type'];
    $ownershipType = $input['ownership_type'] ?? 'individual';
    $ownershipPct = isset($input['ownership_percentage'])
        ? (float) $input['ownership_percentage']
        : match ($ownershipType) {
            'joint', 'tenants_in_common' => 50.0,
            'trust' => 0.0,
            default => 100.0,
        };

    $payload = [
        'user_id' => $user->id,
        'property_type' => $propertyType,
        'current_value' => (float) $input['current_value'],
        'ownership_type' => $ownershipType,
        'ownership_percentage' => $ownershipPct,
        'address_line_1' => $input['address_line_1'] ?? ucfirst(str_replace('_', ' ', $propertyType)),
        'city' => $input['city'] ?? 'Unknown',
        'postcode' => $input['postcode'] ?? 'N/A',
    ];

    foreach (['address_line_2', 'county', 'tenure_type', 'joint_owner_name', 'tenant_name', 'managing_agent_name'] as $f) {
        if (isset($input[$f]) && $input[$f] !== '') {
            $payload[$f] = $input[$f];
        }
    }
    foreach (['purchase_date', 'valuation_date', 'lease_expiry_date'] as $f) {
        if (isset($input[$f]) && $input[$f] !== '') {
            $payload[$f] = $input[$f];
        }
    }
    foreach (['purchase_price', 'monthly_council_tax', 'monthly_gas', 'monthly_electricity', 'monthly_water', 'monthly_building_insurance', 'monthly_contents_insurance', 'monthly_service_charge', 'monthly_maintenance_reserve', 'other_monthly_costs', 'monthly_rental_income'] as $f) {
        if (isset($input[$f]) && is_numeric($input[$f])) {
            $payload[$f] = (float) $input[$f];
        }
    }
    if (isset($input['lease_remaining_years']) && is_numeric($input['lease_remaining_years'])) {
        $payload['lease_remaining_years'] = (int) $input['lease_remaining_years'];
    }

    // Mortgage auto-create — flagged via has_mortgage OR by legacy
    // outstanding_mortgage / mortgage_outstanding_balance fields.
    $mortgageBalance = $input['mortgage_outstanding_balance'] ?? $input['outstanding_mortgage'] ?? null;
    $hasMortgage = ! empty($input['has_mortgage'])
        || (is_numeric($mortgageBalance) && (float) $mortgageBalance > 0);

    $property = DB::transaction(function () use ($payload, $hasMortgage, $input, $mortgageBalance, $user, $ownershipType, $ownershipPct) {
        $property = Property::create($payload);

        if ($hasMortgage) {
            $rate = $input['mortgage_interest_rate'] ?? $input['mortgage_rate'] ?? null;
            $mortgagePayload = [
                'user_id' => $user->id,
                'property_id' => $property->id,
                'lender_name' => $input['mortgage_lender'] ?? null,
                'mortgage_type' => $input['mortgage_type'] ?? 'repayment',
                'rate_type' => $input['mortgage_rate_type'] ?? 'fixed',
                'outstanding_balance' => is_numeric($mortgageBalance) ? (float) $mortgageBalance : 0,
                'ownership_type' => $ownershipType,
                'ownership_percentage' => $ownershipPct,
            ];
            if (is_numeric($rate)) {
                $mortgagePayload['interest_rate'] = (float) $rate;
            }
            if (isset($input['mortgage_monthly_payment']) && is_numeric($input['mortgage_monthly_payment'])) {
                $mortgagePayload['monthly_payment'] = (float) $input['mortgage_monthly_payment'];
            }
            if (isset($input['mortgage_start_date']) && $input['mortgage_start_date'] !== '') {
                $mortgagePayload['start_date'] = $input['mortgage_start_date'];
            }
            if (isset($input['mortgage_maturity_date']) && $input['mortgage_maturity_date'] !== '') {
                $mortgagePayload['maturity_date'] = $input['mortgage_maturity_date'];
            }

            Mortgage::create($mortgagePayload);
        }

        return $property;
    });

    $this->invalidateUserCache($user->id);

    return [
        'success' => true,
        'created' => true,
        'entity_type' => 'property',
        'entity_id' => $property->id,
        'name' => $property->address_line_1,
        'persisted_fields' => array_keys(array_diff_key($payload, ['user_id' => null])),
        'message' => "I've added your property at \"{$property->address_line_1}\".",
    ];
}
```

---

## 6. `create_mortgage`

**Schema** (`AiToolDefinitions.php:710-753`):

```php
[
    'name' => 'create_mortgage',
    'description' => 'Create a standalone mortgage linked to an existing property. Use this when the user mentions a mortgage separately from a property, or wants to add a mortgage to an existing property. You MAY call this tool multiple times in the same turn when the user mentions multiple mortgages.',
    'parameters' => [
        'type' => 'object',
        'properties' => [
            'property_address_hint' => ['type' => 'string', 'description' => 'A hint to match the property — can be address, postcode, or description like "my main home". The system will fuzzy-match against existing properties.'],
            'lender_name' => ['type' => 'string', 'description' => 'Mortgage lender (e.g., "Halifax", "Nationwide")'],
            'outstanding_balance' => ['type' => 'number', 'description' => 'Outstanding mortgage balance in pounds'],
            'interest_rate' => ['type' => 'number', 'description' => 'Current interest rate as a percentage (e.g., 4.2 for 4.2%)'],
            'mortgage_type' => ['type' => 'string', 'enum' => ['repayment', 'interest_only', 'mixed'], 'description' => 'Mortgage repayment type. Default "repayment".'],
            'rate_type' => ['type' => 'string', 'enum' => ['fixed', 'variable', 'tracker'], 'description' => 'Interest rate type. Default "fixed".'],
            'monthly_payment' => ['type' => 'number', 'description' => 'Monthly payment amount in pounds'],
            'remaining_term_months' => ['type' => 'integer', 'description' => 'Remaining mortgage term in months'],
        ],
        'required' => ['outstanding_balance'],
        'additionalProperties' => false,
    ],
],
```

**Handler** (`CoordinatingAgent.php:2568-2657`):

```php
private function handleCreateMortgage(array $input, User $user, bool $isPreview): array
{
    if ($isPreview) {
        return $this->previewBlocked('mortgage');
    }

    $validationError = $this->validateToolInput($input, [
        'outstanding_balance' => 'required|numeric|min:0|max:999999999.99',
        'interest_rate' => 'nullable|numeric|min:0|max:25',
        'mortgage_type' => ['nullable', Rule::in(['repayment', 'interest_only', 'mixed'])],
        'rate_type' => ['nullable', Rule::in(['fixed', 'variable', 'tracker', 'discount', 'mixed'])],
        'monthly_payment' => 'nullable|numeric|min:0|max:999999.99',
        'remaining_term_months' => 'nullable|integer|min:1|max:480',
        'start_date' => 'nullable|date',
        'maturity_date' => 'nullable|date',
        'lender_name' => 'nullable|string|max:255',
        'property_address_hint' => 'nullable|string|max:500',
    ]);
    if ($validationError) {
        return $validationError;
    }

    // Resolve target property: fuzzy match on hint, else fall back to
    // the user's only property if exactly one exists.
    $hint = trim((string) ($input['property_address_hint'] ?? ''));
    $propertyQuery = Property::where('user_id', $user->id);
    if ($hint !== '') {
        $like = '%'.$hint.'%';
        $propertyQuery->where(function ($q) use ($like) {
            $q->where('address_line_1', 'LIKE', $like)
                ->orWhere('postcode', 'LIKE', $like)
                ->orWhere('city', 'LIKE', $like);
        });
    }
    $property = $propertyQuery->orderBy('id')->first();

    if (! $property) {
        $userProperties = Property::where('user_id', $user->id)->count();

        return [
            'error' => true,
            'error_type' => $userProperties === 0 ? 'missing_property' : 'property_match_failed',
            'message' => $userProperties === 0
                ? "I couldn't find a property to attach this mortgage to. Please add the property first."
                : "I couldn't match \"{$hint}\" to one of your properties. Please be more specific.",
        ];
    }

    $payload = [
        'user_id' => $user->id,
        'property_id' => $property->id,
        'mortgage_type' => $input['mortgage_type'] ?? 'repayment',
        'rate_type' => $input['rate_type'] ?? 'fixed',
        'outstanding_balance' => (float) $input['outstanding_balance'],
        'ownership_type' => $property->ownership_type,
        'ownership_percentage' => (float) $property->ownership_percentage,
    ];

    if (isset($input['lender_name']) && $input['lender_name'] !== '') {
        $payload['lender_name'] = $input['lender_name'];
    }
    if (isset($input['interest_rate']) && is_numeric($input['interest_rate'])) {
        $payload['interest_rate'] = (float) $input['interest_rate'];
    }
    if (isset($input['monthly_payment']) && is_numeric($input['monthly_payment'])) {
        $payload['monthly_payment'] = (float) $input['monthly_payment'];
    }
    if (isset($input['remaining_term_months']) && is_numeric($input['remaining_term_months'])) {
        $payload['remaining_term_months'] = (int) $input['remaining_term_months'];
    }
    foreach (['start_date', 'maturity_date'] as $f) {
        if (isset($input[$f]) && $input[$f] !== '') {
            $payload[$f] = $input[$f];
        }
    }

    $mortgage = DB::transaction(fn () => Mortgage::create($payload));

    $this->invalidateUserCache($user->id);

    return [
        'success' => true,
        'created' => true,
        'entity_type' => 'mortgage',
        'entity_id' => $mortgage->id,
        'name' => $mortgage->lender_name ?? 'Mortgage',
        'persisted_fields' => array_keys(array_diff_key($payload, ['user_id' => null])),
        'message' => "I've added the mortgage on \"{$property->address_line_1}\".",
    ];
}
```

---

## 7. `create_protection_policy`

Single tool covering THREE underlying tables (life, critical illness, income protection) — dispatched on `policy_type`.

**Schema** (`AiToolDefinitions.php:761-808`):

```php
[
    'name' => 'create_protection_policy',
    'description' => 'Create a protection insurance policy for the user. Handles life insurance, critical illness cover, and income protection policies. You MAY call this tool multiple times in the same turn when the user mentions multiple policies (e.g. life insurance AND critical illness).',
    'parameters' => [
        'type' => 'object',
        'properties' => [
            'policy_type' => ['type' => 'string', 'enum' => ['level_term', 'term', 'whole_of_life', 'decreasing_term', 'family_income_benefit', 'standalone_ci', 'accelerated_ci', 'income_protection'], 'description' => 'Type of policy. "level_term" for level term life insurance, "term" for generic term life, "whole_of_life" for whole of life, "decreasing_term" for decreasing term (e.g., mortgage protection), "family_income_benefit" for family income benefit, "standalone_ci" for standalone critical illness, "accelerated_ci" for accelerated critical illness (combined with life cover), "income_protection" for income protection.'],
            'provider' => ['type' => 'string', 'description' => 'Insurance provider (e.g., "Aviva", "Legal & General")'],
            'sum_assured' => ['type' => 'number', 'description' => 'Sum assured / cover amount in pounds. For life and critical illness policies.'],
            'benefit_amount' => ['type' => 'number', 'description' => 'Monthly benefit amount in pounds. For income protection policies only.'],
            'premium_amount' => ['type' => 'number', 'description' => 'Premium amount in pounds'],
            'premium_frequency' => ['type' => 'string', 'enum' => ['monthly', 'annually'], 'description' => 'How often premiums are paid. Default "monthly".'],
            'policy_term_years' => ['type' => 'integer', 'description' => 'Policy term in years (not applicable for whole of life)'],
            'policy_start_date' => ['type' => 'string', 'description' => 'Policy start date. Pass the user-supplied phrase verbatim (e.g. "today", "26 April 2026", "last Monday") — the server parses it deterministically.'],
            'in_trust' => ['type' => 'boolean', 'description' => 'Whether the policy is written in trust for Inheritance Tax planning. Default false.'],
        ],
        'required' => ['policy_type'],
        'additionalProperties' => false,
    ],
],
```

**Handler** (`CoordinatingAgent.php:2659-2874`):

```php
private function handleCreateProtectionPolicy(array $input, User $user, bool $isPreview): array
{
    if ($isPreview) {
        return $this->previewBlocked('protection policy');
    }

    $validationError = $this->validateToolInput($input, [
        'policy_type' => ['required', Rule::in(['level_term', 'term', 'whole_of_life', 'decreasing_term', 'family_income_benefit', 'standalone_ci', 'accelerated_ci', 'income_protection'])],
        'sum_assured' => 'nullable|numeric|min:0|max:999999999.99',
        'benefit_amount' => 'nullable|numeric|min:0|max:999999.99',
        'premium_amount' => 'nullable|numeric|min:0|max:99999.99',
        'policy_term_years' => 'nullable|integer|min:1|max:50',
    ]);
    if ($validationError) {
        return $validationError;
    }

    $policyType = $input['policy_type'];

    // Choose the right model + entity_type per category. Each protection
    // category has its own table and bespoke field set.
    $category = match ($policyType) {
        'income_protection' => 'income_protection',
        'standalone_ci', 'accelerated_ci' => 'critical_illness',
        default => 'life',
    };

    $sumAssured = isset($input['sum_assured']) ? (float) $input['sum_assured'] : 0.0;
    $benefitAmount = isset($input['benefit_amount']) ? (float) $input['benefit_amount'] : 0.0;
    $providerLabel = $input['provider'] ?? str_replace('_', ' ', $policyType);

    // Resolve any natural-language date the LLM passed through. We do NOT
    // ask the LLM to format dates as ISO 8601 — it's an unreliable thing
    // to delegate. The handler accepts whatever phrase the user said and
    // parses it deterministically (Carbon handles "today", "yesterday",
    // "26 April 2026", "last Monday", etc.). Bad strings drop to null
    // rather than 500 the request.
    foreach (['policy_start_date', 'policy_end_date'] as $dateField) {
        if (isset($input[$dateField]) && is_string($input[$dateField]) && $input[$dateField] !== '') {
            try {
                $input[$dateField] = \Carbon\Carbon::parse($input[$dateField])->toDateString();
            } catch (\Throwable $e) {
                unset($input[$dateField]);
            }
        }
    }

    if ($category === 'life') {
        $payload = [
            'user_id' => $user->id,
            // map generic 'term' onto canonical 'level_term'
            'policy_type' => $policyType === 'term' ? 'level_term' : $policyType,
            'sum_assured' => $policyType === 'family_income_benefit' && $benefitAmount > 0
                ? $benefitAmount
                : $sumAssured,
            'premium_frequency' => $input['premium_frequency'] ?? 'monthly',
        ];
        foreach (['provider', 'policy_number', 'policy_start_date', 'policy_end_date'] as $f) {
            if (isset($input[$f]) && $input[$f] !== '') {
                $payload[$f] = $input[$f];
            }
        }
        foreach (['premium_amount', 'start_value', 'decreasing_rate', 'indexation_rate'] as $f) {
            if (isset($input[$f]) && is_numeric($input[$f])) {
                $payload[$f] = (float) $input[$f];
            }
        }
        if (isset($input['policy_term_years']) && is_numeric($input['policy_term_years'])) {
            $payload['policy_term_years'] = (int) $input['policy_term_years'];
        }
        foreach (['in_trust', 'is_mortgage_protection', 'joint_life'] as $f) {
            if (isset($input[$f])) {
                $payload[$f] = (bool) $input[$f];
            }
        }

        // BS-17 in-turn idempotency: grok-4-1-fast occasionally emits
        // create_protection_policy twice for the same entity inside one
        // multi-entity message. Without this guard the second tool call
        // creates a duplicate row before the LLM has a chance to see the
        // first result. The check is scoped to a 60s window so genuine
        // separate-session adds are unaffected.
        $existing = LifeInsurancePolicy::where('user_id', $user->id)
            ->where('policy_type', $payload['policy_type'])
            ->where('sum_assured', $payload['sum_assured'])
            ->where('provider', $payload['provider'] ?? null)
            ->where('created_at', '>', now()->subMinute())
            ->first();
        if ($existing !== null) {
            $this->invalidateUserCache($user->id);

            return [
                'success' => true,
                'created' => false,
                'duplicate' => true,
                'entity_type' => 'life_insurance_policy',
                'entity_id' => $existing->id,
                'name' => (string) $providerLabel,
                'persisted_fields' => [],
                'message' => "Already added — skipped duplicate \"{$providerLabel}\" policy.",
            ];
        }

        $policy = DB::transaction(fn () => LifeInsurancePolicy::create($payload));
        $entityType = 'life_insurance_policy';
    } elseif ($category === 'critical_illness') {
        // CI table's enum uses bare values (standalone | accelerated)
        // — strip the AI tool's `_ci` suffix.
        $ciType = match ($policyType) {
            'standalone_ci' => 'standalone',
            'accelerated_ci' => 'accelerated',
            default => 'standalone',
        };
        $payload = [
            'user_id' => $user->id,
            'policy_type' => $ciType,
            'sum_assured' => $sumAssured,
            'premium_frequency' => $input['premium_frequency'] ?? 'monthly',
        ];
        foreach (['provider', 'policy_number', 'policy_start_date'] as $f) {
            if (isset($input[$f]) && $input[$f] !== '') {
                $payload[$f] = $input[$f];
            }
        }
        if (isset($input['premium_amount']) && is_numeric($input['premium_amount'])) {
            $payload['premium_amount'] = (float) $input['premium_amount'];
        }
        if (isset($input['policy_term_years']) && is_numeric($input['policy_term_years'])) {
            $payload['policy_term_years'] = (int) $input['policy_term_years'];
        }
        if (isset($input['conditions_covered']) && is_array($input['conditions_covered'])) {
            $payload['conditions_covered'] = $input['conditions_covered'];
        }

        // BS-17 in-turn idempotency — see life-branch comment above.
        $existing = CriticalIllnessPolicy::where('user_id', $user->id)
            ->where('policy_type', $payload['policy_type'])
            ->where('sum_assured', $payload['sum_assured'])
            ->where('provider', $payload['provider'] ?? null)
            ->where('created_at', '>', now()->subMinute())
            ->first();
        if ($existing !== null) {
            $this->invalidateUserCache($user->id);

            return [
                'success' => true,
                'created' => false,
                'duplicate' => true,
                'entity_type' => 'critical_illness_policy',
                'entity_id' => $existing->id,
                'name' => (string) $providerLabel,
                'persisted_fields' => [],
                'message' => "Already added — skipped duplicate \"{$providerLabel}\" policy.",
            ];
        }

        $policy = DB::transaction(fn () => CriticalIllnessPolicy::create($payload));
        $entityType = 'critical_illness_policy';
    } else {
        $payload = [
            'user_id' => $user->id,
            'benefit_amount' => $benefitAmount > 0 ? $benefitAmount : $sumAssured,
            'premium_frequency' => $input['premium_frequency'] ?? 'monthly',
            'benefit_frequency' => $input['benefit_frequency'] ?? 'monthly',
        ];
        foreach (['provider', 'policy_number', 'occupation_class', 'policy_start_date'] as $f) {
            if (isset($input[$f]) && $input[$f] !== '') {
                $payload[$f] = $input[$f];
            }
        }
        if (isset($input['premium_amount']) && is_numeric($input['premium_amount'])) {
            $payload['premium_amount'] = (float) $input['premium_amount'];
        }
        foreach (['deferred_period_weeks', 'benefit_period_months'] as $f) {
            if (isset($input[$f]) && is_numeric($input[$f])) {
                $payload[$f] = (int) $input[$f];
            }
        }

        // BS-17 in-turn idempotency — see life-branch comment above.
        $existing = IncomeProtectionPolicy::where('user_id', $user->id)
            ->where('benefit_amount', $payload['benefit_amount'])
            ->where('provider', $payload['provider'] ?? null)
            ->where('created_at', '>', now()->subMinute())
            ->first();
        if ($existing !== null) {
            $this->invalidateUserCache($user->id);

            return [
                'success' => true,
                'created' => false,
                'duplicate' => true,
                'entity_type' => 'income_protection_policy',
                'entity_id' => $existing->id,
                'name' => (string) $providerLabel,
                'persisted_fields' => [],
                'message' => "Already added — skipped duplicate \"{$providerLabel}\" policy.",
            ];
        }

        $policy = DB::transaction(fn () => IncomeProtectionPolicy::create($payload));
        $entityType = 'income_protection_policy';
    }

    $this->invalidateUserCache($user->id);

    return [
        'success' => true,
        'created' => true,
        'entity_type' => $entityType,
        'entity_id' => $policy->id,
        'name' => (string) $providerLabel,
        'persisted_fields' => array_keys(array_diff_key($payload, ['user_id' => null])),
        'message' => "I've added your \"{$providerLabel}\" protection policy.",
    ];
}
```

---

## 8. `create_business_interest`

**Schema** (`AiToolDefinitions.php:1059-1073`):

```php
[
    'name' => 'create_business_interest',
    'description' => 'Record a business interest or ownership. Use when the user mentions business ownership, partnerships, or self-employment assets. You MAY call this tool multiple times in the same turn when the user mentions multiple businesses.',
    'parameters' => [
        'type' => 'object',
        'properties' => [
            'business_name' => ['type' => 'string', 'description' => 'Name of the business'],
            'business_type' => ['type' => 'string', 'enum' => ['sole_trader', 'partnership', 'limited_company', 'llp'], 'description' => 'Type of business entity'],
            'ownership_percentage' => ['type' => 'number', 'description' => 'Percentage owned (0-100)'],
            'estimated_value' => ['type' => 'number', 'description' => 'Estimated value of the interest (£)'],
            'annual_profit' => ['type' => 'number', 'description' => 'Annual profit/drawings (£)'],
        ],
        'required' => ['business_name', 'business_type'],
        'additionalProperties' => false,
    ],
],
```

**Handler** (`CoordinatingAgent.php:3721-3779`):

```php
private function handleCreateBusinessInterest(array $input, User $user, bool $isPreview): array
{
    if ($isPreview) {
        return $this->previewBlocked('business interest');
    }

    $validationError = $this->validateToolInput($input, [
        'business_name' => 'required|string|max:255',
        'business_type' => ['required', Rule::in(['sole_trader', 'partnership', 'limited_company', 'llp', 'other'])],
        'industry_sector' => 'nullable|string|max:255',
        'ownership_percentage' => 'nullable|numeric|min:0|max:100',
        'estimated_value' => 'nullable|numeric|min:0|max:999999999.99',
        'annual_revenue' => 'nullable|numeric|min:0|max:999999999.99',
        'annual_profit' => 'nullable|numeric|min:-999999999.99|max:999999999.99',
        'annual_dividend_income' => 'nullable|numeric|min:0|max:999999999.99',
        'employee_count' => 'nullable|integer|min:0|max:99999',
    ]);
    if ($validationError) {
        return $validationError;
    }

    $payload = [
        'user_id' => $user->id,
        'business_name' => $input['business_name'],
        'business_type' => $input['business_type'],
        'current_valuation' => isset($input['estimated_value']) ? (float) $input['estimated_value'] : 0,
        'ownership_type' => 'individual',
        'ownership_percentage' => isset($input['ownership_percentage']) ? (float) $input['ownership_percentage'] : 100.00,
        'valuation_date' => now()->toDateString(),
    ];

    foreach (['annual_revenue', 'annual_profit', 'annual_dividend_income'] as $f) {
        if (isset($input[$f]) && is_numeric($input[$f])) {
            $payload[$f] = (float) $input[$f];
        }
    }
    if (isset($input['employee_count']) && is_numeric($input['employee_count'])) {
        $payload['employee_count'] = (int) $input['employee_count'];
    }
    foreach (['description', 'notes'] as $f) {
        if (isset($input[$f]) && $input[$f] !== '') {
            $payload[$f] = $input[$f];
        }
    }

    $bi = DB::transaction(fn () => BusinessInterest::create($payload));

    $this->invalidateUserCache($user->id);

    return [
        'success' => true,
        'created' => true,
        'entity_type' => 'business_interest',
        'entity_id' => $bi->id,
        'name' => $bi->business_name,
        'persisted_fields' => array_keys(array_diff_key($payload, ['user_id' => null])),
        'message' => "I've added your \"{$bi->business_name}\" business interest.",
    ];
}
```
