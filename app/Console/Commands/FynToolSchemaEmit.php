<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\AI\AiToolDefinitions;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * THROWAWAY one-shot (CoALA Phase 4b). Emits fyn-memory/procedural/tool_schema/
 * {module}/{tool_name}.md from the CURRENT in-PHP tool definitions. Deleted
 * once the corpus is committed — the golden-master test, not this command, is
 * the source of truth for correctness.
 */
final class FynToolSchemaEmit extends Command
{
    protected $signature = 'fyn:tool-schema:emit {--root=}';

    protected $description = 'THROWAWAY: emit tool_schema procedure .md files from the current PHP definitions.';

    /** tool_name => module slug (the inventory table from the plan). */
    private const MODULE = [
        'navigate_to_page' => 'navigation',
        'list_records' => 'analysis',
        'list_goals' => 'analysis',
        'list_life_events' => 'analysis',
        'get_module_analysis' => 'analysis',
        'get_recommendations' => 'analysis',
        'search_conversation_index' => 'analysis',
        'get_tax_information' => 'tax',
        'generate_financial_plan' => 'plans',
        'get_subscription_status' => 'billing',
        'list_invoices' => 'billing',
        'get_current_plan' => 'billing',
        'create_what_if_scenario' => 'whatif',
        'create_goal' => 'goals',
        'create_life_event' => 'goals',
        'create_savings_account' => 'savings',
        'create_investment_account' => 'savings',
        'create_holding' => 'savings',
        'create_pension' => 'savings',
        'create_property' => 'property',
        'create_mortgage' => 'property',
        'create_protection_policy' => 'protection',
        'create_asset' => 'estate',
        'create_liability' => 'estate',
        'create_estate_gift' => 'estate',
        'create_will' => 'estate',
        'update_will' => 'estate',
        'create_power_of_attorney' => 'estate',
        'update_power_of_attorney' => 'estate',
        'create_family_member' => 'estate',
        'create_trust' => 'estate',
        'create_business_interest' => 'estate',
        'create_chattel' => 'estate',
        'update_record' => 'data',
        'delete_record' => 'data',
        'update_profile' => 'data',
        'set_expenditure' => 'expenditure',
        'capture_salary_sacrifice' => 'campaign',
        'capture_spouse_work_status' => 'campaign',
        'capture_spouse_household_data' => 'campaign',
        'capture_spouse_non_working_assets' => 'campaign',
        'capture_pension_history' => 'campaign',
        'capture_charitable_giving' => 'campaign',
        'delegate_to_capture' => 'handoff',
        'capture_complete' => 'handoff',
        'capture_personal_details' => 'onboarding',
        'capture_spouse_details' => 'onboarding',
        'capture_dependants' => 'onboarding',
        'capture_work_details' => 'onboarding',
    ];

    public function handle(AiToolDefinitions $defs): int
    {
        $root = (string) ($this->option('root') ?: config('fyn.memory.procedural_path'));

        // Gather every static tool in native {name,description,parameters} shape.
        // getTools(false) under xai returns native shape (no input_schema wrap).
        Cache::put('ai_provider', 'xai');
        $native = [];

        foreach ($defs->getTools(false) as $tool) {
            if (str_starts_with($tool['name'], 'fetch_')) {
                continue; // pointer tools — out of scope
            }
            $native[$tool['name']] = $tool;
        }

        // handoff + onboarding entry points are not in getTools(); de-wrap them.
        foreach ($defs->handoffTools('xai') as $w) {
            $f = $w['function'];
            $native[$f['name']] = ['name' => $f['name'], 'description' => $f['description'], 'parameters' => $f['parameters']];
        }
        foreach ($defs->onboardingExtractionTools('xai') as $w) {
            $f = $w['function'];
            // onboardingExtractionTools strict=false wrap; drop the strict key,
            // the native body is parameters as-is.
            $params = $f['parameters'];
            $native[$f['name']] = ['name' => $f['name'], 'description' => $f['description'], 'parameters' => $params];
        }

        $written = 0;
        foreach (self::MODULE as $name => $module) {
            if (! isset($native[$name])) {
                $this->error("MISSING from current definitions: {$name}");

                return self::FAILURE;
            }

            $tool = $native[$name];
            $body = $tool;

            // update_record: replace the computed parameters with the live sentinel.
            if ($name === 'update_record') {
                $body['parameters'] = ['$allowlist' => 'update_record'];
            }

            $json = json_encode(
                ['name' => $body['name'], 'description' => $body['description'], 'parameters' => $body['parameters']],
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT,
            );

            $procedureId = $module.'.tool.'.$name;
            $md = "---\n"
                ."procedure_id: '{$procedureId}'\n"
                ."kind: tool_schema\n"
                ."module: {$module}\n"
                ."version: 1\n"
                ."active: true\n"
                ."effective_from: 2026-06-02\n"
                ."---\n\n"
                ."```json\n{$json}\n```\n";

            $dir = "{$root}/tool_schema/{$module}";
            if (! is_dir($dir)) {
                mkdir($dir, 0777, true);
            }
            file_put_contents("{$dir}/{$name}.md", $md);
            $written++;
        }

        $this->info("Wrote {$written} tool_schema procedure files to {$root}/tool_schema/.");

        return self::SUCCESS;
    }
}
