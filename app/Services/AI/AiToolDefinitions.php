<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Services\AI\Memory\Procedural\ProceduralCorpusLoader;
use App\Services\AI\Memory\Procedural\Procedure;
use App\Services\AI\Pointers\Pointer;
use App\Services\AI\Pointers\PointerRegistry;
use Illuminate\Support\Facades\Cache;

class AiToolDefinitions
{
    /**
     * Get all tool definitions for the Anthropic Messages API.
     */
    public function getTools(bool $isPreviewMode = false): array
    {
        $tools = [
            ...$this->navigationTools(),
            ...$this->analysisTools(),
            ...$this->taxTools(),
            ...$this->planGenerationTools(),
            ...$this->billingTools(),
        ];

        if (! $isPreviewMode) {
            $tools = array_merge(
                $tools,
                $this->whatIfTools(),
                $this->dataCreationTools(),
                $this->additionalCreationTools(),
                $this->dataModificationTools(),
                $this->profileTools(),
                $this->expenditureTools(),
                $this->campaignSaveTaxTools(),
            );
        }

        // CoALA pointer tools — each tool/both-mode pointer becomes a
        // `fetch_{pointer_id}` tool whose description is the pointer body.
        // These are read-only fetches (provenance + degrade-on-failure
        // shared with the pre-fetch path) so they are exposed in preview
        // mode too. Wrapped defensively in pointerTools() — a corrupt
        // corpus must never break the catalogue.
        $tools = array_merge($tools, $this->pointerTools());

        // Return tools in native format (name, description, parameters)
        // The HasAiChat trait handles provider-specific wrapping:
        // - xAI/OpenAI: wraps in {type: "function", function: {name, description, parameters}}
        // - Anthropic: converts parameters → input_schema
        if (Cache::get('ai_provider', config('services.ai_provider', 'anthropic')) === 'xai') {
            return $tools; // Already in the right shape for OpenAI wrapping
        }

        // Anthropic format: parameters → input_schema
        return array_map(fn (array $tool) => [
            'name' => $tool['name'],
            'description' => $tool['description'],
            'input_schema' => $tool['parameters'],
        ], $tools);
    }

    /**
     * Ordered procedure_id lists per grouping method. The assembly order here
     * is the contract guarded by ToolSchemaGoldenMasterTest — do not reorder.
     */
    private const ORDER = [
        'navigation' => ['navigation.tool.navigate_to_page'],
        'analysis' => [
            'analysis.tool.list_records',
            'analysis.tool.list_goals',
            'analysis.tool.list_life_events',
            'analysis.tool.get_module_analysis',
            'analysis.tool.get_recommendations',
            'analysis.tool.search_conversation_index',
        ],
        'tax' => ['tax.tool.get_tax_information'],
        'plans' => ['plans.tool.generate_financial_plan'],
        'billing' => [
            'billing.tool.get_subscription_status',
            'billing.tool.list_invoices',
            'billing.tool.get_current_plan',
        ],
        'whatif' => ['whatif.tool.create_what_if_scenario'],
        'goals' => ['goals.tool.create_goal', 'goals.tool.create_life_event'],
        'savings' => [
            'savings.tool.create_savings_account',
            'savings.tool.create_investment_account',
            'savings.tool.create_holding',
            'savings.tool.create_pension',
        ],
        'property' => ['property.tool.create_property', 'property.tool.create_mortgage'],
        'protection' => ['protection.tool.create_protection_policy'],
        'estate' => [
            'estate.tool.create_asset',
            'estate.tool.create_liability',
            'estate.tool.create_estate_gift',
            'estate.tool.create_will',
            'estate.tool.update_will',
            'estate.tool.create_power_of_attorney',
            'estate.tool.update_power_of_attorney',
        ],
        'additional' => [
            'estate.tool.create_family_member',
            'estate.tool.create_trust',
            'estate.tool.create_business_interest',
            'estate.tool.create_chattel',
        ],
        'modification' => ['data.tool.update_record', 'data.tool.delete_record'],
        'profile' => ['data.tool.update_profile'],
        'expenditure' => ['expenditure.tool.set_expenditure'],
        'campaign' => [
            'campaign.tool.capture_salary_sacrifice',
            'campaign.tool.capture_spouse_work_status',
            'campaign.tool.capture_spouse_household_data',
            'campaign.tool.capture_spouse_non_working_assets',
            'campaign.tool.capture_pension_history',
            'campaign.tool.capture_charitable_giving',
        ],
        'handoff' => ['handoff.tool.delegate_to_capture', 'handoff.tool.capture_complete'],
        'onboarding' => [
            'onboarding.tool.capture_personal_details',
            'onboarding.tool.capture_spouse_details',
            'onboarding.tool.capture_dependants',
            'onboarding.tool.capture_work_details',
        ],
    ];

    /**
     * Assemble a list of native {name,description,parameters} tools from the
     * procedural corpus, in the given procedure_id order. Degrades at runtime:
     * a missing/undecodable schema is skipped + report()ed so a corrupt corpus
     * cannot empty the whole catalogue mid-turn. The golden-master test +
     * fyn:procedural:validate are the real completeness guards.
     *
     * @param  list<string>  $procedureIds
     * @return list<array<string, mixed>>
     */
    private function toolsFromCorpus(array $procedureIds): array
    {
        $corpus = app(ProceduralCorpusLoader::class)->load();
        $tools = [];

        foreach ($procedureIds as $procedureId) {
            $tool = $this->toolFromCorpus($corpus->active($procedureId));
            if ($tool !== null) {
                $tools[] = $tool;
            }
        }

        return $tools;
    }

    /**
     * Decode one tool_schema procedure body (a fenced ```json block) into the
     * native {name,description,parameters} shape. Replaces the
     * {"$allowlist":"update_record"} sentinel with the live updateRecordSchema().
     * Returns null (and report()s) on any failure so the catalogue degrades.
     *
     * @return array<string, mixed>|null
     */
    private function toolFromCorpus(?Procedure $procedure): ?array
    {
        if ($procedure === null) {
            report(new \RuntimeException('Tool schema corpus: missing active procedure.'));

            return null;
        }

        // Strip the leading ```json fence and trailing ``` fence.
        $body = trim($procedure->body);
        $body = preg_replace('/^```json\s*\n/', '', $body);
        $body = preg_replace('/\n```\s*$/', '', (string) $body);

        $decoded = json_decode((string) $body, true);
        if (! is_array($decoded) || ! isset($decoded['name'], $decoded['description'], $decoded['parameters'])) {
            report(new \RuntimeException("Tool schema corpus: undecodable body for '{$procedure->procedureId}'."));

            return null;
        }

        if (($decoded['parameters']['$allowlist'] ?? null) === 'update_record') {
            $decoded['parameters'] = $this->updateRecordSchema();
        }

        // Restore empty-object shape: json_decode($body, true) turns `{}` into
        // `[]`, which would re-encode as `[]` and break byte-identity with the
        // original literal `(object) []`. Re-objectify any empty `properties`.
        $params = $decoded['parameters'];
        if (isset($params['properties']) && $params['properties'] === []) {
            $params['properties'] = (object) [];
        }
        $decoded['parameters'] = $params;

        return [
            'name' => $decoded['name'],
            'description' => $decoded['description'],
            'parameters' => $decoded['parameters'],
        ];
    }

    /**
     * CoALA pointer tools in native (name/description/parameters) shape.
     *
     * One tool per tool/both-mode pointer in the registry:
     *   - name: `fetch_{pointer_id}` with dashes → underscores
     *   - description: the pointer body (the LLM-facing "when to use")
     *   - parameters: minimal empty object (the handler reads the user/query)
     *
     * Degrades to no pointer tools on any registry error — the catalogue
     * must never break because the pointer corpus is malformed.
     *
     * @return list<array<string, mixed>>
     */
    private function pointerTools(): array
    {
        try {
            $pointers = app(PointerRegistry::class)->toolPointers();
        } catch (\Throwable $e) {
            return [];
        }

        return array_map(static fn (Pointer $pointer): array => [
            'name' => 'fetch_'.str_replace('-', '_', $pointer->pointerId),
            'description' => $pointer->body,
            'parameters' => [
                'type' => 'object',
                'properties' => (object) [],
                'additionalProperties' => false,
            ],
        ], $pointers);
    }

    private function navigationTools(): array
    {
        return $this->toolsFromCorpus(self::ORDER['navigation']);
    }

    private function analysisTools(): array
    {
        return $this->toolsFromCorpus(self::ORDER['analysis']);
    }

    private function taxTools(): array
    {
        return $this->toolsFromCorpus(self::ORDER['tax']);
    }

    private function planGenerationTools(): array
    {
        return $this->toolsFromCorpus(self::ORDER['plans']);
    }

    private function whatIfTools(): array
    {
        return $this->toolsFromCorpus(self::ORDER['whatif']);
    }

    private function dataCreationTools(): array
    {
        return [
            // Goals & life events
            ...$this->goalAndEventTools(),
            // Financial accounts
            ...$this->accountCreationTools(),
            // Property & mortgage
            ...$this->propertyCreationTools(),
            // Protection policies
            ...$this->protectionCreationTools(),
            // Estate planning
            ...$this->estateCreationTools(),
        ];
    }

    private function goalAndEventTools(): array
    {
        return $this->toolsFromCorpus(self::ORDER['goals']);
    }

    private function accountCreationTools(): array
    {
        return $this->toolsFromCorpus(self::ORDER['savings']);
    }

    private function propertyCreationTools(): array
    {
        return $this->toolsFromCorpus(self::ORDER['property']);
    }

    private function protectionCreationTools(): array
    {
        return $this->toolsFromCorpus(self::ORDER['protection']);
    }

    private function estateCreationTools(): array
    {
        return $this->toolsFromCorpus(self::ORDER['estate']);
    }

    private function additionalCreationTools(): array
    {
        return $this->toolsFromCorpus(self::ORDER['additional']);
    }

    private function dataModificationTools(): array
    {
        return $this->toolsFromCorpus(self::ORDER['modification']);
    }

    /**
     * Build the `update_record` parameter schema as a oneOf of per-entity
     * branches, sourcing allowed fields from {@see UpdateRecordAllowlist}.
     *
     * Each branch pins `entity_type` to a const and restricts `fields` to
     * the per-entity allowlist with `additionalProperties: false`. The
     * runtime handler still re-checks the allowlist (defence-in-depth)
     * because the LLM occasionally ignores schema constraints.
     */
    private function updateRecordSchema(): array
    {
        $oneOf = [];
        foreach (\App\Constants\UpdateRecordAllowlist::MAP as $entityType => $allowedFields) {
            $properties = [];
            foreach ($allowedFields as $field) {
                $properties[$field] = ['type' => ['string', 'number', 'boolean', 'null']];
            }

            $oneOf[] = [
                'type' => 'object',
                'properties' => [
                    'entity_type' => ['const' => $entityType],
                    'entity_id' => ['type' => 'integer', 'description' => 'The ID of the record to update.'],
                    'fields' => [
                        'type' => 'object',
                        'description' => 'Key-value pairs to update. Only the fields listed for this entity_type are accepted.',
                        'properties' => $properties,
                        'additionalProperties' => false,
                    ],
                ],
                'required' => ['entity_type', 'entity_id', 'fields'],
                'additionalProperties' => false,
            ];
        }

        return ['oneOf' => $oneOf];
    }

    /**
     * Handoff tools — surfaced to the LLM only during the onboarding
     * inline-capture turn. Not included in getTools() so they never leak
     * into AdviceFyn's chat turn. OnboardingChatDirector::handleInlineCapture
     * opts into them via toolsListOverride.
     *
     * Tool names are defined as constants on HandoffContract so a typo
     * fails at parse time rather than silently mis-routing at runtime.
     *
     * @param  string  $provider  'anthropic' or 'xai' — picks output format.
     * @return list<array<string, mixed>>
     */
    public function handoffTools(string $provider = 'anthropic'): array
    {
        $tools = $this->toolsFromCorpus(self::ORDER['handoff']);

        if ($provider === 'xai') {
            return array_map(fn (array $tool) => [
                'type' => 'function',
                'function' => [
                    'name' => $tool['name'],
                    'description' => $tool['description'],
                    'parameters' => $tool['parameters'],
                ],
            ], $tools);
        }

        return array_map(fn (array $tool) => [
            'name' => $tool['name'],
            'description' => $tool['description'],
            'input_schema' => $tool['parameters'],
        ], $tools);
    }

    /**
     * Onboarding extraction tools — used ONLY by OnboardingChatDirector
     * during grouped_extract turns. Not included in getTools() so they
     * never leak into the normal Fyn chat token budget. The director
     * passes this list as a `toolsListOverride` when delegating the
     * grouped turn to the coordinating agent.
     *
     * Each tool extracts a named group of onboarding fields from a
     * free-text reply and returns a structured payload. Handlers in
     * CoordinatingAgent write the fields to the database and return
     * a capture receipt that HasAiChat yields as an SSE
     * `onboarding_field_captured` event.
     *
     * @param  string  $provider  'anthropic' or 'xai' — picks the output
     *                            format so the director can route the
     *                            same tools to either API.
     * @return list<array<string, mixed>>
     */
    public function onboardingExtractionTools(string $provider = 'anthropic'): array
    {
        $tools = $this->toolsFromCorpus(self::ORDER['onboarding']);

        // SaveTax campaign extraction tools live alongside the base tools so
        // the grouped_extract path in OnboardingChatDirector can find them
        // when STATE_CAMPAIGN_SPOUSE_HOUSEHOLD / SPOUSE_NON_WORKING_ASSETS
        // run. Without this, the LLM is offered the tool by the prompt but
        // the director's filter rejects the tool call as "not found".
        $tools = array_merge($tools, $this->campaignSaveTaxTools());

        if ($provider === 'xai') {
            // xAI / OpenAI function-calling format: wrap in function object
            // with strict mode. Matches the shape XaiToolDefinitions emits.
            return array_map(function (array $tool): array {
                $params = $tool['parameters'];
                // strict mode requires all properties in `required` — for
                // extraction tools we already mark required fields, so we
                // just copy the flag through.
                $params['additionalProperties'] = false;

                return [
                    'type' => 'function',
                    'function' => [
                        'name' => $tool['name'],
                        'description' => $tool['description'],
                        'parameters' => $params,
                        'strict' => false, // strict=false to allow optional fields like last_name
                    ],
                ];
            }, $tools);
        }

        // Anthropic format: parameters → input_schema
        return array_map(fn (array $tool) => [
            'name' => $tool['name'],
            'description' => $tool['description'],
            'input_schema' => $tool['parameters'],
        ], $tools);
    }

    /**
     * SaveTax campaign — sections 4-6 capture tools.
     *
     * Used in the campaign-only state-machine branch after expenditure capture
     * (path=campaign users only). All 4 tools write to existing tables (dc_pensions,
     * users, tax_strategy_household_inputs).
     */
    private function campaignSaveTaxTools(): array
    {
        return $this->toolsFromCorpus(self::ORDER['campaign']);
    }

    private function expenditureTools(): array
    {
        return $this->toolsFromCorpus(self::ORDER['expenditure']);
    }

    /**
     * Billing / subscription tools. All read-only and zero-parameter, so they
     * are exposed in both preview and live mode (preview personas have no
     * subscription rows, so they get the canonical "none" shape back).
     */
    private function billingTools(): array
    {
        return $this->toolsFromCorpus(self::ORDER['billing']);
    }

    private function profileTools(): array
    {
        return $this->toolsFromCorpus(self::ORDER['profile']);
    }
}
