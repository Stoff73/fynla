# Shared helpers, dispatcher, and orchestrator

This file collects every shared piece of code that's not a single tool but is invoked by the tool handlers. If you're testing a tool you will eventually hit one of these — they are the plumbing.

> Source: `app/Agents/CoordinatingAgent.php` and `app/Services/AI/AdviceFyn.php`.

---

## 1. The dispatcher (`CoordinatingAgent::executeTool`)

**Source** (`CoordinatingAgent.php:770-941`):

```php
/**
 * Execute a tool call with prerequisite gate enforcement.
 */
public function executeTool(string $toolName, array $input, User $user, ?int $conversationId = null): array
{
    // xAI strict mode may return the string "null" instead of actual null for nullable fields
    // Also decode HTML entities (xAI sometimes encodes & as &amp; in tool arguments)
    $input = array_map(function ($v) {
        if ($v === 'null') {
            return null;
        }
        if (is_string($v)) {
            return html_entity_decode($v, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        return $v;
    }, $input);

    // Bypass when the active token EXPLICITLY lists `bypass-preview-mode`
    // (eval flow) AND the X-Eval-Run-Id header is set
    // (April30Updates F-12 — defence-in-depth on the eval bypass).
    $hasEvalBypass = \App\Services\Eval\EvalBypassGate::isActive($user);
    $isPreviewUser = (bool) $user->is_preview_user && ! $hasEvalBypass;

    // S0.12 — append a chain row at dispatch. Replaces the [AI-AUDIT] file
    // log that used to fire after the result returned (which dropped any
    // tool that threw before the log line was reached).
    $this->appendAuditEvent([
        'user_id' => $user->id,
        'conversation_id' => $conversationId,
        'tool_name' => $toolName,
        'operation' => self::operationFor($toolName),
        'status' => 'dispatched',
        'input_summary' => self::summariseInput($toolName, $input, $isPreviewUser),
    ]);

    // Eval trace — capture every tool dispatch with name + args.
    event(new \App\Events\Eval\AgentDecision(
        agent: 'CoordinatingAgent',
        decisionPoint: 'tool_dispatch',
        outcome: $toolName,
        context: [
            'args' => $input,
            'user_id' => $user->id,
            'conversation_id' => $conversationId,
            'is_preview_user' => $isPreviewUser,
        ],
        atMicrotime: microtime(true),
    ));

    // Prerequisite gate check
    $gate = $this->prerequisiteGate->canExecuteTool($toolName, $input, $user);
    if (! $gate['can_proceed']) {
        $firstAction = $gate['required_actions'][0] ?? null;

        return [
            'blocked' => true,
            'reason' => $gate['guidance'],
            'missing_data' => $gate['missing'],
            'suggested_action' => $firstAction,
            'instruction' => 'Explain to the user exactly what data is missing and why it is needed. '
                .'List each missing item clearly. '
                .($firstAction ? "Then use the navigate_to_page tool to take them to \"{$firstAction['route']}\" where they can add the missing information." : ''),
        ];
    }

    try {
        $result = match ($toolName) {
            'navigate_to_page' => $this->handleNavigation($input),
            // Handoff tools — stubbed so HasAiChat doesn't error. The
            // synthetic 'handoff' SSE event yielded downstream from this
            // result is consumed by OnboardingChatDirector::handleInlineCapture.
            'delegate_to_capture' => [
                'action' => 'handoff',
                'handoff_type' => 'delegate_to_capture',
                'payload' => $input,
            ],
            'capture_complete' => [
                'action' => 'handoff',
                'handoff_type' => 'capture_complete',
                'payload' => $input,
            ],
            'capture_personal_details' => $this->handleCapturePersonalDetails($input, $user),
            'capture_spouse_details' => $this->handleCaptureSpouseDetails($input, $user),
            'capture_dependants' => $this->handleCaptureDependants($input, $user),
            'capture_work_details' => $this->handleCaptureWorkDetails($input, $user),
            'list_records' => $this->handleListRecords($input, $user),
            'list_goals' => $this->handleListGoals($user),
            'list_life_events' => $this->handleListLifeEvents($user),
            'get_module_analysis' => $this->handleModuleAnalysis($input, $user),
            'search_conversation_index' => $this->handleSearchConversationIndex($input, $user, $conversationId),
            'create_what_if_scenario' => $this->handleCreateWhatIfScenario($input, $user),
            'get_recommendations' => $this->handleRecommendations($user),
            'get_subscription_status' => $this->handleGetSubscriptionStatus($user),
            'list_invoices' => $this->handleListInvoices($user),
            'get_current_plan' => $this->handleGetCurrentPlan($user),
            'get_tax_information' => $this->handleTaxInformation($input, $user),
            'generate_financial_plan' => $this->handleFinancialPlan($user),
            'create_goal' => $this->handleCreateGoal($input, $user, $isPreviewUser),
            'create_life_event' => $this->handleCreateLifeEvent($input, $user, $isPreviewUser),
            'create_savings_account' => $this->handleCreateSavingsAccount($input, $user, $isPreviewUser),
            'create_investment_account' => $this->handleCreateInvestmentAccount($input, $user, $isPreviewUser),
            'create_holding' => $this->handleCreateHolding($input, $user, $isPreviewUser),
            'create_pension' => $this->handleCreatePension($input, $user, $isPreviewUser),
            'create_property' => $this->handleCreateProperty($input, $user, $isPreviewUser),
            'create_mortgage' => $this->handleCreateMortgage($input, $user, $isPreviewUser),
            'create_protection_policy' => $this->handleCreateProtectionPolicy($input, $user, $isPreviewUser),
            'create_asset' => $this->handleCreateEstateAsset($input, $user, $isPreviewUser),
            'create_liability' => $this->handleCreateEstateLiability($input, $user, $isPreviewUser),
            'create_estate_gift' => $this->handleCreateEstateGift($input, $user, $isPreviewUser),
            'create_will' => $this->handleCreateWill($input, $user, $isPreviewUser),
            'update_will' => $this->handleUpdateWill($input, $user, $isPreviewUser),
            'create_power_of_attorney' => $this->handleCreatePowerOfAttorney($input, $user, $isPreviewUser),
            'update_power_of_attorney' => $this->handleUpdatePowerOfAttorney($input, $user, $isPreviewUser),
            'create_family_member' => $this->handleCreateFamilyMember($input, $user, $isPreviewUser),
            'create_trust' => $this->handleCreateTrust($input, $user, $isPreviewUser),
            'create_business_interest' => $this->handleCreateBusinessInterest($input, $user, $isPreviewUser),
            'create_chattel' => $this->handleCreateChattel($input, $user, $isPreviewUser),
            'set_expenditure' => $this->handleSetExpenditure($input, $user, $isPreviewUser),
            'update_record' => $this->handleUpdateRecord($input, $user, $isPreviewUser),
            'delete_record' => $this->handleDeleteRecord($input, $user, $isPreviewUser),
            'update_profile' => $this->handleUpdateProfile($input, $user, $isPreviewUser),
            // SaveTax campaign — sections 4-6
            'capture_salary_sacrifice' => $this->handleCaptureSalarySacrifice($input, $user, $isPreviewUser),
            'capture_spouse_work_status' => $this->handleCaptureSpouseWorkStatus($input, $user, $isPreviewUser),
            'capture_spouse_household_data' => $this->handleCaptureSpouseHouseholdData($input, $user, $isPreviewUser),
            'capture_spouse_non_working_assets' => $this->handleCaptureSpouseNonWorkingAssets($input, $user, $isPreviewUser),
            'capture_pension_history' => $this->handleCapturePensionHistory($input, $user, $isPreviewUser),
            'capture_charitable_giving' => $this->handleCaptureCharitableGiving($input, $user, $isPreviewUser),
            default => ['error' => true, 'error_type' => 'unknown_tool', 'message' => "Unknown tool: {$toolName}"],
        };

        // S0.12 — chain-append the completion row. `persisted` for any
        // result without an `error` key; `failed` otherwise. Replaces the
        // [AI-AUDIT] file log entirely.
        $this->appendAuditCompletion($user, $conversationId, $toolName, $input, $result);

        return $result;
    } catch (\Illuminate\Validation\ValidationException $e) {
        $this->appendAuditEvent([
            'user_id' => $user->id,
            'conversation_id' => $conversationId,
            'tool_name' => $toolName,
            'operation' => self::operationFor($toolName),
            'status' => 'failed',
            'result_summary' => ['error_type' => 'validation_failed', 'message' => $e->validator->errors()->first()],
        ]);

        return ['error' => true, 'error_type' => 'validation_failed', 'message' => $e->validator->errors()->first()];
    } catch (\Illuminate\Database\QueryException $e) {
        Log::error('[CoordinatingAgent] Database error', ['tool' => $toolName, 'user_id' => $user->id, 'error' => $e->getMessage()]);
        $this->appendAuditEvent([
            'user_id' => $user->id,
            'conversation_id' => $conversationId,
            'tool_name' => $toolName,
            'operation' => self::operationFor($toolName),
            'status' => 'failed',
            'result_summary' => ['error_type' => 'database_error', 'message' => $e->getMessage()],
        ]);

        return ['error' => true, 'error_type' => 'database_error', 'message' => 'Unable to save the record. Please try again.'];
    } catch (\Exception $e) {
        Log::error('[CoordinatingAgent] Tool execution failed', ['tool' => $toolName, 'user_id' => $user->id, 'error' => $e->getMessage()]);
        $this->appendAuditEvent([
            'user_id' => $user->id,
            'conversation_id' => $conversationId,
            'tool_name' => $toolName,
            'operation' => self::operationFor($toolName),
            'status' => 'failed',
            'result_summary' => ['error_type' => 'execution_failed', 'message' => $e->getMessage()],
        ]);

        return ['error' => true, 'error_type' => 'execution_failed', 'message' => 'An unexpected error occurred. Please try again.'];
    }
}
```

---

## 2. Audit-chain helper (`appendAuditEvent`)

S0.12 wraps `AuditChainService::append` so failures inside the chain (e.g. the migration not run yet on a worker that holds an old schema cache) cannot bring the chat down. The chain is forensic, not load-bearing.

**Source** (`CoordinatingAgent.php:949-961`):

```php
private function appendAuditEvent(array $event): void
{
    try {
        app(AuditChainService::class)->append($event);
    } catch (\Throwable $e) {
        Log::warning('[CoordinatingAgent] Audit chain append failed', [
            'tool' => $event['tool_name'] ?? null,
            'status' => $event['status'] ?? null,
            'error' => $e->getMessage(),
        ]);
    }
}
```

---

## 3. `previewBlocked`

Returns the canonical preview-block response shape. Every write tool calls this when `$isPreview === true`.

**Source** (`CoordinatingAgent.php:3287-3290`):

```php
private function previewBlocked(string $entityType): array
{
    return ['blocked' => true, 'reason' => "You are in preview mode. Creating a {$entityType} is not available — please create a real account to save data."];
}
```

---

## 4. `validateToolInput`

Single-line `Validator::make` wrapper. Returns `null` on success, the error array on failure (so callers can early-return on the result).

**Source** (`CoordinatingAgent.php:3292-3300`):

```php
private function validateToolInput(array $input, array $rules): ?array
{
    $validator = Validator::make($input, $rules);
    if ($validator->fails()) {
        return ['error' => true, 'error_type' => 'validation_failed', 'message' => $validator->errors()->first()];
    }

    return null;
}
```

---

## 5. `checkForDuplicate`

SQL-injection-safe duplicate check — used by `create_savings_account`, `create_investment_account`, `create_pension`. The whitelist of column names is critical: `whereRaw` is unsafe with arbitrary `$nameField` strings.

**Source** (`CoordinatingAgent.php:3407-3425`):

```php
private function checkForDuplicate(string $modelClass, int $userId, string $nameField, string $nameValue): ?array
{
    // SECURITY: $allowedColumns is a whitelist preventing SQL injection in the whereRaw below.
    // NEVER add user-supplied strings to this array — only hardcoded column names.
    $allowedColumns = ['first_name', 'surname', 'name', 'email', 'asset_name', 'liability_name', 'trust_name', 'scheme_name', 'provider', 'account_name', 'policy_name', 'gift_type'];
    if (! in_array($nameField, $allowedColumns, true)) {
        throw new \InvalidArgumentException("Invalid column name: {$nameField}");
    }

    $existing = $modelClass::where('user_id', $userId)
        ->whereRaw('LOWER('.$nameField.') = ?', [strtolower($nameValue)])
        ->first();

    if ($existing) {
        return ['warning' => true, 'message' => "A similar record '{$existing->{$nameField}}' already exists. The new record was not created to avoid duplication.", 'existing_id' => $existing->id];
    }

    return null;
}
```

---

## 6. `resolveModel`

Maps `entity_type` → Eloquent model class, scopes by `user_id`. Used by `update_record` and `delete_record`. Returns either the model instance or an error array (callers check `is_array($model) && isset($model['error'])`).

**Source** (`CoordinatingAgent.php:4262-4297`):

```php
/**
 * Resolve a model instance by entity type and ID, ensuring it belongs to the user.
 */
private function resolveModel(string $entityType, int $entityId, int $userId): mixed
{
    $modelClass = match ($entityType) {
        'goal' => Goal::class,
        'life_event' => LifeEvent::class,
        'savings_account' => SavingsAccount::class,
        'investment_account' => InvestmentAccount::class,
        'dc_pension' => DCPension::class,
        'db_pension' => DBPension::class,
        'property' => Property::class,
        'mortgage' => Mortgage::class,
        'life_insurance' => LifeInsurancePolicy::class,
        'critical_illness' => CriticalIllnessPolicy::class,
        'income_protection' => IncomeProtectionPolicy::class,
        'estate_asset' => Asset::class,
        'estate_liability' => Liability::class,
        'estate_gift' => Gift::class,
        'family_member' => FamilyMember::class,
        'trust' => Trust::class,
        'business_interest' => BusinessInterest::class,
        'chattel' => Chattel::class,
        default => null,
    };

    if (! $modelClass) {
        return ['error' => true, 'error_type' => 'invalid_entity', 'message' => "Unknown entity type: {$entityType}"];
    }

    $model = $modelClass::where('id', $entityId)->where('user_id', $userId)->first();

    if (! $model) {
        return ['error' => true, 'error_type' => 'not_found', 'message' => ucfirst(str_replace('_', ' ', $entityType)).' not found or does not belong to you.'];
    }

    return $model;
}
```

---

## 7. `resolveFamilyNames`

Resolves natural-language references like "my wife" / "myself" / "my children" / "my solicitor" to actual names from the user's `FamilyMember` records, falling back to `(Spouse) name to be confirmed` placeholders. Used by `create_estate_gift` and `create_trust`.

**Source** (`CoordinatingAgent.php:3311-3405`):

```php
/**
 * Resolve generic family/role references to actual names.
 *
 * "my wife" → "Jane Smith" or "(Wife) name to be confirmed"
 * "myself" / "me" / "I" → "John Smith"
 * "my children" → "Tom Smith, Emily Smith" or "(Children) names to be confirmed"
 * "my solicitor" → "(Solicitor) name to be confirmed" (unless a name follows)
 * "my brother" → "(Brother) name to be confirmed" (unless a name follows)
 */
private function resolveFamilyNames(?string $text, User $user): ?string
{
    if (! $text) {
        return null;
    }

    $userName = trim($user->first_name.' '.$user->surname);
    $spouse = $user->spouse;
    $spouseFullName = $spouse ? trim($spouse->first_name.' '.$spouse->surname) : null;

    $children = \App\Models\FamilyMember::where('user_id', $user->id)
        ->where('relationship', 'child')
        ->get();
    $childNames = $children->count() > 0
        ? $children->map(fn ($c) => trim($c->first_name.' '.($c->last_name ?? '')))->implode(', ')
        : null;

    // Split on comma and " and " to handle "my wife and children", "myself, solicitor"
    $parts = preg_split('/\s*,\s*|\s+and\s+/i', $text);
    $parts = array_map('trim', $parts);
    $resolved = [];

    foreach ($parts as $part) {
        $lower = strtolower($part);

        // Self references — full name
        if (in_array($lower, ['myself', 'me', 'i', 'self']) || $lower === strtolower($user->first_name)) {
            $resolved[] = $userName;

            continue;
        }

        // Spouse references — full name or placeholder
        if (preg_match('/^(my\s+)?(wife|husband|partner|spouse)$/i', $lower)) {
            $resolved[] = $spouseFullName ?? '(Spouse) name to be confirmed';

            continue;
        }

        // "my wife Jane" / "wife Sarah" — spouse role + name, keep the name
        if (preg_match('/^(my\s+)?(wife|husband|partner|spouse)\s+(.+)$/i', $part, $m)) {
            $resolved[] = trim($m[3]);

            continue;
        }

        // Children references — expand to individual names or placeholder
        if (preg_match('/^(my\s+|our\s+)?(children|kids)$/i', $lower)) {
            $resolved[] = $childNames ?? '(Children) names to be confirmed';

            continue;
        }

        // "wife and children" / "wife and kids" combo
        if (preg_match('/^(my\s+|our\s+)?(wife|husband|partner|spouse)\s+and\s+(my\s+|our\s+)?(children|kids)$/i', $lower)) {
            $spousePart = $spouseFullName ?? '(Spouse) name to be confirmed';
            $childPart = $childNames ?? '(Children) names to be confirmed';
            $resolved[] = $spousePart.', '.$childPart;

            continue;
        }

        // Role references without a proper name — add placeholder
        if (preg_match('/^(my\s+|the\s+|the\s+family\s+|our\s+)?(solicitor|executor|accountant|brother|sister|mother|father|son|daughter)$/i', $lower, $m)) {
            $role = ucfirst(strtolower($m[2]));
            $resolved[] = '('.$role.') name to be confirmed';

            continue;
        }

        // "my brother David" / "my solicitor Mr Hughes" — strip "my"/"the" prefix, keep the name + role
        if (preg_match('/^(my|the|our|the\s+family)\s+(solicitor|executor|accountant|brother|sister|mother|father|son|daughter)\s+(.+)$/i', $part, $m)) {
            $role = ucfirst(strtolower($m[2]));
            $name = trim($m[3]);
            $resolved[] = $name.' ('.$role.')';

            continue;
        }

        // Generic "my X" — strip "my" prefix, keep the rest
        if (preg_match('/^(my|the|our)\s+(.+)$/i', $part, $m)) {
            $resolved[] = trim($m[2]);

            continue;
        }

        // Already a name or specific text — keep as-is
        $resolved[] = $part;
    }

    // Clean up: remove empty entries, trim whitespace
    $result = implode(', ', array_filter(array_map('trim', $resolved)));

    return $result ?: null;
}
```

---

## 8. `summariseToolAnalysis`

Validates raw module analysis against `ToolResultContract` (S1.6.b). Returns the verbatim payload as the tool result. On contract violation, the LLM receives an explicit error tool result so it degrades by asking the user for clarification rather than fabricating around missing data.

**Source** (`CoordinatingAgent.php:3501-3535`):

```php
/**
 * Validate raw module analysis against the per-agent contract (S1.6.b)
 * and return the verbatim payload as a tool_result. The previous
 * implementation silently dropped everything outside a 15-key whitelist;
 * the new implementation hands the LLM the structured agent output it
 * was supposed to see all along.
 *
 * On contract violation the LLM receives an explicit error tool result
 * — not a malformed shape — so it degrades by asking the user for
 * clarification rather than fabricating around missing data.
 */
private function summariseToolAnalysis(string $module, array $analysis): array
{
    if (isset($analysis['error'])) {
        return ['module' => $module] + $analysis;
    }

    try {
        return app(\App\Services\AI\ToolResultContract::class)->validate($module, $analysis);
    } catch (\App\Services\AI\ToolResultContractException $e) {
        \Illuminate\Support\Facades\Log::error('[CoordinatingAgent] Tool result contract violation', [
            'module' => $module,
            'context' => $e->context,
            'missing_keys' => $e->missingKeys,
            'present_keys' => $e->presentKeys,
            'message' => $e->getMessage(),
        ]);

        return [
            'module' => $module,
            'error' => 'module_analysis_contract_violation',
            'detail' => 'The module analysis returned a malformed shape and was blocked. Ask the user for clarification rather than relying on this result.',
        ];
    }
}
```

---

## 9. `educationStatusForAge`

Used by `capture_dependants` to map dependant age to the education_status enum. Mirrors `OnboardingChatDirector::educationStatusForAge`.

**Source** (`CoordinatingAgent.php:1387-1396`):

```php
/**
 * Map a dependant age to the closest education_status enum value on
 * family_members. Mirrors OnboardingChatDirector::educationStatusForAge
 * so the legacy regex path and the new LLM path are consistent.
 */
private function educationStatusForAge(int $age): string
{
    return match (true) {
        $age < 5 => 'pre_school',
        $age < 11 => 'primary',
        $age < 18 => 'secondary',
        $age < 25 => 'higher_education',
        default => 'not_applicable',
    };
}
```

---

## 10. `getRouteForEntityType`

Maps entity types to their frontend page routes. Used by some create handlers (notably the legacy form-fill path) and not by every tool. Documented for completeness.

**Source** (`CoordinatingAgent.php:4180-4198`):

```php
/**
 * Map entity types to their frontend page routes.
 */
private function getRouteForEntityType(string $entityType): string
{
    return match ($entityType) {
        'savings_account' => '/net-worth/cash',
        'investment_account' => '/net-worth/investments',
        'dc_pension', 'db_pension' => '/net-worth/retirement',
        'property', 'mortgage' => '/net-worth/property',
        'life_insurance', 'critical_illness', 'income_protection', 'protection_policy' => '/protection',
        'goal' => '/goals',
        'life_event' => '/goals?tab=events',
        'family_member' => '/profile',
        'trust' => '/trusts',
        'business_interest' => '/net-worth/business',
        'chattel' => '/net-worth/chattels',
        'estate_asset', 'estate_gift' => '/estate',
        'estate_liability' => '/net-worth/liabilities',
        default => '/dashboard',
    };
}
```

---

## 11. `resolvePropertyId`

Property fuzzy-match used by some property-adjacent flows (e.g. when a tool needs to attach to "my main home" without a property_id).

**Source** (`CoordinatingAgent.php:3450-3499`):

```php
private function resolvePropertyId(User $user, ?string $hint): ?int
{
    $properties = Property::where('user_id', $user->id)->get();

    if ($properties->isEmpty()) {
        return null;
    }

    if ($properties->count() === 1) {
        return $properties->first()->id;
    }

    if (! $hint) {
        $main = $properties->firstWhere('property_type', 'main_residence');

        return $main?->id ?? $properties->first()->id;
    }

    $hintLower = Str::lower($hint);

    if (Str::contains($hintLower, ['main', 'home', 'primary', 'residence'])) {
        $match = $properties->firstWhere('property_type', 'main_residence');
        if ($match) {
            return $match->id;
        }
    }

    if (Str::contains($hintLower, ['buy to let', 'btl', 'rental', 'let'])) {
        $match = $properties->firstWhere('property_type', 'buy_to_let');
        if ($match) {
            return $match->id;
        }
    }

    if (Str::contains($hintLower, ['second', 'holiday'])) {
        $match = $properties->firstWhere('property_type', 'secondary_residence');
        if ($match) {
            return $match->id;
        }
    }

    foreach ($properties as $property) {
        $address = Str::lower(($property->address_line_1 ?? '').' '.($property->postcode ?? ''));
        if (Str::contains($address, $hintLower) || Str::contains($hintLower, trim($address))) {
            return $property->id;
        }
    }

    return $properties->first()->id;
}
```

---

## 12. `invalidateModuleCache`

Per-module cache invalidation. Most write handlers call the broader `invalidateUserCache($user->id)` instead, which clears all module caches. This per-module variant exists for future fine-grained invalidation.

**Source** (`CoordinatingAgent.php:3427-3448`):

```php
private function invalidateModuleCache(int $userId, string $module): void
{
    $this->netWorthService->invalidateCache($userId);

    $cachePatterns = [
        'savings' => ["v1_savings_{$userId}"],
        'investment' => ["v1_investment_{$userId}"],
        'retirement' => ["v1_retirement_{$userId}"],
        'property' => ["v1_property_{$userId}"],
        'protection' => ["v1_protection_{$userId}"],
        'estate' => ["v1_estate_{$userId}"],
    ];

    foreach ($cachePatterns[$module] ?? [] as $key) {
        Cache::forget($key);
        Cache::forget("{$key}_analysis");
        Cache::forget("{$key}_recommendations");
    }

    Cache::forget("v1_coordinating_{$userId}_analysis");
    Cache::forget("ai_financial_context_{$userId}");
}
```

---

## 13. `AdviceFyn::WRITE_TOOLS` constant

The single source of truth for which tools are stripped from advice mode.

**Source** (`AdviceFyn.php:145-175`):

```php
/**
 * Tools that mutate persistent records — stripped from the advice
 * tool list. Every entry here has a corresponding handler in
 * OnboardingChatDirector::captureToolSet so the handoff path can
 * dispatch it.
 */
private const WRITE_TOOLS = [
    'create_savings_account', 'create_investment_account', 'create_holding',
    'create_pension', 'create_property', 'create_mortgage',
    'create_protection_policy', 'create_asset', 'create_liability',
    'create_estate_gift', 'create_chattel', 'create_business_interest',
    'create_trust', 'create_family_member', 'create_will', 'update_will',
    'create_power_of_attorney', 'update_power_of_attorney',
    'update_record', 'delete_record', 'update_profile', 'set_expenditure',
    'capture_personal_details', 'capture_spouse_details',
    'capture_dependants', 'capture_work_details',
    // S0.5.r — every persistent record-creation tool now flows through
    // the delegate_to_capture handoff. No analytics carve-out:
    // create_what_if_scenario persists a WhatIfScenario row and must
    // therefore be written by Onboarding Fyn like every other create_*.
    'create_goal', 'create_life_event', 'create_what_if_scenario',
    // S0.5.t — strip navigate_to_page from advice mode. BS-14 caught the
    // LLM repeatedly choosing navigate_to_page as an escape hatch for
    // write intents (sending the user to a page so they fill the form
    // themselves), then fabricating "I've added X" success text. The
    // hardened <handoff_guidance> prompt did not deter it. Removing the
    // tool entirely eliminates the escape hatch — write intents now
    // only have one viable path: delegate_to_capture. Page navigation
    // remains user-initiated via the menu.
    'navigate_to_page',
];
```

The stripping happens in `AdviceFyn` (around line 534):

```php
return array_values(array_diff($names, self::WRITE_TOOLS));
```
