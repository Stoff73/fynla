<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Services\AI\Memory\Episodic\ProceduralVersionHolder;
use App\Services\AI\Memory\Procedural\ProceduralCorpusLoader;
use App\Services\AI\Memory\Procedural\Procedure;
use App\Services\AI\Pointers\Pointer;
use App\Services\AI\Pointers\PointerRegistry;

/**
 * xAI-optimised tool definitions with strict function calling.
 *
 * Thin corpus-driven assembler (Phase 4b-xai): reads provider=xai tool_schema
 * procedures from the procedural corpus, decodes each fenced ```json``` body,
 * and re-applies the OpenAI {type:function, function:{…}} wrapper. The strict
 * schema shapes (strict mode, anyOf nullable enums, enriched property schemas,
 * bespoke gathering instructions) live in the corpus bodies, NOT in code.
 *
 * The byte-for-byte contract is XaiToolSchemaGoldenMasterTest.
 */
class XaiToolDefinitions
{
    /**
     * Get all tool definitions in OpenAI function-calling format with strict mode.
     * Tools are pre-wrapped — no further wrapping needed in HasAiChat.
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
                $this->campaignSaveTaxTools(),
            );
        }

        // CoALA pointer tools — read-only `fetch_{pointer_id}` tools mirroring
        // the Anthropic catalogue so tool-name parity holds across providers.
        // Exposed in preview mode too (read-only). Degrades to none on error.
        $tools = array_merge($tools, $this->pointerTools());

        return $tools;
    }

    /**
     * Ordered procedure_id lists per grouping method, in the xAI emission order.
     * Guarded byte-for-byte by XaiToolSchemaGoldenMasterTest — do not reorder.
     * NB: set_expenditure is nested at the TAIL of dataCreation (xAI ordering),
     * which differs from the Anthropic ORDER map.
     */
    private const ORDER = [
        'navigation' => ['navigation.tool.navigate_to_page'],
        'analysis' => [
            'analysis.tool.list_records',
            'analysis.tool.list_goals',
            'analysis.tool.list_life_events',
            'analysis.tool.get_module_analysis',
            'analysis.tool.search_conversation_index',
            'analysis.tool.get_recommendations',
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
        'expenditure' => ['expenditure.tool.set_expenditure'],
        'additional' => [
            'estate.tool.create_family_member',
            'estate.tool.create_trust',
            'estate.tool.create_business_interest',
            'estate.tool.create_chattel',
        ],
        'modification' => ['data.tool.update_record', 'data.tool.delete_record'],
        'profile' => ['data.tool.update_profile'],
        'campaign' => [
            'campaign.tool.capture_salary_sacrifice',
            'campaign.tool.capture_spouse_work_status',
            'campaign.tool.capture_spouse_household_data',
            'campaign.tool.capture_spouse_non_working_assets',
            'campaign.tool.capture_pension_history',
            'campaign.tool.capture_charitable_giving',
        ],
        'handoff' => ['handoff.tool.delegate_to_capture', 'handoff.tool.capture_complete'],
    ];

    /**
     * Assemble pre-wrapped OpenAI function tools from the xAI procedural corpus,
     * in the given procedure_id order. Degrades at runtime (missing/undecodable
     * schema skipped + report()ed). Records each resolved procedure_id@version
     * into ProceduralVersionHolder so Phase 4e stamping fires on xAI turns.
     *
     * @param  list<string>  $procedureIds
     * @return list<array<string, mixed>>
     */
    private function toolsFromCorpus(array $procedureIds): array
    {
        $corpus = app(ProceduralCorpusLoader::class)->load();
        $versions = app(ProceduralVersionHolder::class);
        $tools = [];

        foreach ($procedureIds as $procedureId) {
            $procedure = $corpus->active($procedureId, 'xai');
            $tool = $this->toolFromCorpus($procedure);
            if ($tool !== null) {
                $tools[] = $tool;
                $versions->add($procedure->procedureId, $procedure->version);
            }
        }

        return $tools;
    }

    /**
     * Decode one xAI tool_schema procedure body (a fenced ```json block holding
     * the inner {name, description, parameters, [strict]} function object) and
     * re-wrap it into the OpenAI {type:function, function:{…}} shape. The strict
     * key is preserved iff present in the body. Returns null (and report()s) on
     * any failure so the catalogue degrades rather than emptying mid-turn.
     *
     * @return array<string, mixed>|null
     */
    private function toolFromCorpus(?Procedure $procedure): ?array
    {
        if ($procedure === null) {
            report(new \RuntimeException('xAI tool schema corpus: missing active procedure.'));

            return null;
        }

        $body = trim($procedure->body);
        $body = preg_replace('/^```json\s*\n/', '', $body);
        $body = preg_replace('/\n```\s*$/', '', (string) $body);

        $decoded = json_decode((string) $body, true);
        if (! is_array($decoded) || ! isset($decoded['name'], $decoded['description'], $decoded['parameters'])) {
            report(new \RuntimeException("xAI tool schema corpus: undecodable body for '{$procedure->procedureId}'."));

            return null;
        }

        // Restore empty-object shape: json_decode(..., true) turns `{}` into `[]`,
        // which re-encodes as `[]` and breaks byte-identity with wrapTool's
        // `(object) []`. Re-objectify empty `properties`.
        $params = $decoded['parameters'];
        if (isset($params['properties']) && $params['properties'] === []) {
            $params['properties'] = (object) [];
        }
        $decoded['parameters'] = $params;

        return ['type' => 'function', 'function' => $decoded];
    }

    /**
     * CoALA pointer tools in OpenAI function-calling shape. One tool per
     * tool/both-mode pointer in the registry. Degrades to none on any error.
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
            'type' => 'function',
            'function' => [
                'name' => 'fetch_'.str_replace('-', '_', $pointer->pointerId),
                'description' => $pointer->body,
                'parameters' => [
                    'type' => 'object',
                    'properties' => (object) [],
                    'required' => [],
                    'additionalProperties' => false,
                ],
                'strict' => true,
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
            ...$this->goalAndEventTools(),
            ...$this->accountCreationTools(),
            ...$this->propertyCreationTools(),
            ...$this->protectionCreationTools(),
            ...$this->estateCreationTools(),
            ...$this->expenditureTools(),
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

    private function expenditureTools(): array
    {
        return $this->toolsFromCorpus(self::ORDER['expenditure']);
    }

    private function additionalCreationTools(): array
    {
        return $this->toolsFromCorpus(self::ORDER['additional']);
    }

    private function dataModificationTools(): array
    {
        return $this->toolsFromCorpus(self::ORDER['modification']);
    }

    private function profileTools(): array
    {
        return $this->toolsFromCorpus(self::ORDER['profile']);
    }

    private function campaignSaveTaxTools(): array
    {
        return $this->toolsFromCorpus(self::ORDER['campaign']);
    }

    private function billingTools(): array
    {
        return $this->toolsFromCorpus(self::ORDER['billing']);
    }

    /**
     * Handoff tools — surfaced only during the onboarding inline-capture turn.
     * Mirrors the Anthropic handoff list, in OpenAI function-calling shape.
     *
     * @return list<array<string, mixed>>
     */
    public function handoffTools(): array
    {
        return $this->toolsFromCorpus(self::ORDER['handoff']);
    }
}
