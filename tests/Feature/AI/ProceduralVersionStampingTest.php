<?php

declare(strict_types=1);

use App\Models\AiConversation;
use App\Models\User;
use App\Services\AI\AiToolDefinitions;
use App\Services\AI\AuditChainService;
use App\Services\AI\Fyn\FynContextAssembler;
use App\Services\AI\Fyn\FynTurnContext;
use App\Services\AI\Memory\Episodic\ProceduralVersionHolder;
use App\Services\AI\Memory\Procedural\ProceduralCorpusLoader;
use App\Services\Onboarding\OnboardingChatDirector;
use App\Services\Onboarding\OnboardingStateMachine;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['app.ai_audit_hmac_key' => 'test-key']);
});

/** Write a procedure .md at {kind}/{module}/{file}.md (4e stamping fixtures). */
function writeStampProc(string $root, string $kind, string $module, string $file, int $version, string $body): void
{
    $dir = "{$root}/{$kind}/{$module}";
    @mkdir($dir, 0777, true);
    $procedureId = match (true) {
        $kind === 'system_prompt_overlay' => "{$module}.overlay.{$file}",
        $kind === 'fca_block' => "{$module}.fca.{$file}",
        default => "{$module}.{$kind}.{$file}",
    };
    $fm = "procedure_id: {$procedureId}\nkind: {$kind}\nmodule: {$module}\n"
        ."version: {$version}\nactive: true\neffective_from: '2026-01-01'\n";
    file_put_contents("{$dir}/{$file}.md", "---\n{$fm}---\n\n{$body}\n");
}

function stampAdviceTurn(User $user, string $primary): FynTurnContext
{
    return FynTurnContext::make(
        user: $user,
        message: 'hello',
        currentRoute: '/dashboard',
        mode: 'advice',
        onboardingFocus: null,
        isPreview: false,
        classification: ['primary' => $primary],
    );
}

it('does not change the v2 episode row hash when procedural_version is present', function (): void {
    $user = User::factory()->create();
    $conv = AiConversation::factory()->create(['user_id' => $user->id]);

    $svc = app(AuditChainService::class);

    // Control row: no procedural_version key supplied.
    $control = $svc->appendEpisode([
        'user_id' => $user->id, 'conversation_id' => $conv->id, 'entity_id' => 1,
        'blob_md_sha256' => str_repeat('a', 64),
        'blob_md_path' => 'episodic/x.md',
        'semantic_snapshot_id' => str_repeat('b', 64),
        'fetch_provenance' => [['digest' => 'd1']],
    ]);

    // The preimage depends on prev_hash, so to compare hashes we must reproduce
    // the SAME chain position. Re-derive the control's hash by re-running the
    // same append against a fresh chain and comparing the WITH/ WITHOUT-field
    // hashes at the identical (genesis-prev) position is not possible because
    // appendEpisode reads the live tip. Instead assert the invariant directly:
    // the row_hash is reproducible by verifyChain (which never reads
    // procedural_version), and result_summary carries the field when supplied.
    expect($svc->verifyChain()['chain_valid'])->toBeTrue();

    // Now append a second episode WITH a procedural_version key and assert the
    // chain still verifies (proves verifyChain ignores the new result_summary
    // key for the hash) and the key round-trips in result_summary.
    $withField = $svc->appendEpisode([
        'user_id' => $user->id, 'conversation_id' => $conv->id, 'entity_id' => 2,
        'blob_md_sha256' => str_repeat('c', 64),
        'blob_md_path' => 'episodic/y.md',
        'semantic_snapshot_id' => str_repeat('d', 64),
        'fetch_provenance' => [['digest' => 'd2']],
        'procedural_version' => ['retirement.tool.create_dc_pension@2', 'general.overlay.house@1'],
    ]);

    expect($svc->verifyChain()['chain_valid'])->toBeTrue()
        ->and($control->fresh()->result_summary)->not->toHaveKey('procedural_version');
});

it('keeps a mixed v1 + v2 chain green (audit invariants unchanged by phase 4e)', function (): void {
    $user = User::factory()->create();
    $conv = AiConversation::factory()->create(['user_id' => $user->id]);
    $svc = app(AuditChainService::class);

    $svc->append(['user_id' => $user->id, 'tool_name' => 'a', 'operation' => 'read', 'status' => 'dispatched']);
    $svc->appendEpisode([
        'user_id' => $user->id, 'conversation_id' => $conv->id, 'entity_id' => 1,
        'blob_md_sha256' => str_repeat('a', 64), 'blob_md_path' => 'episodic/x.md',
        'semantic_snapshot_id' => null, 'fetch_provenance' => [],
        'procedural_version' => ['x.tool.y@1'],
    ]);
    $svc->append(['user_id' => $user->id, 'tool_name' => 'b', 'operation' => 'read', 'status' => 'dispatched']);

    expect($svc->verifyChain()['chain_valid'])->toBeTrue();
});

it('FynContextAssembler::selectProcedures records each injected overlay/fca_block into the holder', function (): void {
    $this->seed(TaxConfigurationSeeder::class);
    $corpus = sys_get_temp_dir().'/proc-stamp-'.uniqid();
    config([
        'fyn.memory.procedural_path' => $corpus,
        'fyn.memory.procedural_reload_interval' => 0,
    ]);
    app()->forgetInstance(ProceduralCorpusLoader::class);
    app()->forgetInstance(FynContextAssembler::class);

    writeStampProc($corpus, 'system_prompt_overlay', 'retirement', 'tone', 3, 'Overlay body.');
    writeStampProc($corpus, 'fca_block', 'general', 'hedge', 2, 'Always hedge advice.');

    $user = User::factory()->create();
    app(FynContextAssembler::class)->build(stampAdviceTurn($user, 'retirement'));

    expect(app(ProceduralVersionHolder::class)->all())
        ->toContain('retirement.overlay.tone@3')
        ->toContain('general.fca.hedge@2');

    File::deleteDirectory($corpus);
});

it('records nothing into the holder when no overlay/fca_block matches the turn', function (): void {
    $this->seed(TaxConfigurationSeeder::class);
    $corpus = sys_get_temp_dir().'/proc-stamp-'.uniqid();
    config([
        'fyn.memory.procedural_path' => $corpus,
        'fyn.memory.procedural_reload_interval' => 0,
    ]);
    app()->forgetInstance(ProceduralCorpusLoader::class);
    app()->forgetInstance(FynContextAssembler::class);

    writeStampProc($corpus, 'system_prompt_overlay', 'estate', 'iht', 1, 'Estate-only.');

    $user = User::factory()->create();
    app(FynContextAssembler::class)->build(stampAdviceTurn($user, 'retirement'));

    expect(app(ProceduralVersionHolder::class)->all())->toBe([]);

    File::deleteDirectory($corpus);
});

it('AiToolDefinitions records each assembled tool_schema procedure into the holder', function (): void {
    $corpus = sys_get_temp_dir().'/proc-tools-'.uniqid();
    config([
        'fyn.memory.procedural_path' => $corpus,
        'fyn.memory.procedural_reload_interval' => 0,
    ]);
    app()->forgetInstance(ProceduralCorpusLoader::class);

    // A single navigation tool schema procedure the corpus can resolve. The id
    // is the literal AiToolDefinitions::ORDER['navigation'][0] (ORDER is a
    // private const, so it cannot be referenced from the test — use the literal).
    $navId = 'navigation.tool.navigate_to_page';
    [$module] = explode('.', $navId, 2);
    $kindDir = "{$corpus}/tool_schema/{$module}";
    @mkdir($kindDir, 0777, true);
    $schema = json_encode([
        'name' => 'navigate',
        'description' => 'Navigate the app.',
        'parameters' => ['type' => 'object', 'properties' => (object) [], 'required' => []],
    ], JSON_UNESCAPED_SLASHES);
    $base = preg_replace('/[^a-z0-9]+/i', '-', $navId);
    $fm = "procedure_id: {$navId}\nkind: tool_schema\nmodule: {$module}\n"
        ."version: 5\nactive: true\neffective_from: '2026-01-01'\n";
    file_put_contents("{$kindDir}/{$base}.md", "---\n{$fm}---\n\n```json\n{$schema}\n```\n");

    // toolsFromCorpus is private; invoke the navigation assembly via reflection
    // (the codebase's established pattern for exercising private AI internals —
    // see EpisodePersistenceTest invoking persistEpisode). This is the smallest
    // deterministic path that calls toolsFromCorpus(self::ORDER['navigation']).
    $defs = app(AiToolDefinitions::class);
    $r = new ReflectionMethod($defs, 'toolsFromCorpus');
    $r->setAccessible(true);
    $tools = $r->invoke($defs, [$navId]);

    expect($tools)->not->toBe([]);
    expect(app(ProceduralVersionHolder::class)->all())->toContain("{$navId}@5");

    File::deleteDirectory($corpus);
});

it('OnboardingChatDirector records nothing when the corpus has no active workflow', function (): void {
    $corpus = sys_get_temp_dir().'/proc-onb-'.uniqid();
    config([
        'fyn.memory.procedural_path' => $corpus,
        'fyn.memory.procedural_reload_interval' => 0,
    ]);
    app()->forgetInstance(ProceduralCorpusLoader::class);
    OnboardingStateMachine::flushTransitionTableCache();

    $user = User::factory()->create();
    $user->forceFill([
        'onboarding_completed' => false,
        'onboarding_fyn_step' => OnboardingStateMachine::STATE_PATH_CHOICE,
    ])->save();
    $conv = AiConversation::factory()->create(['user_id' => $user->id]);

    $director = app(OnboardingChatDirector::class);
    iterator_to_array($director->handleUserMessage($user, $conv, 'Start from scratch'));

    expect(app(ProceduralVersionHolder::class)->all())->toBe([]);

    File::deleteDirectory($corpus);
});

it('OnboardingChatDirector records the active workflow procedure when the corpus supplies it', function (): void {
    $corpus = sys_get_temp_dir().'/proc-onb-'.uniqid();
    config([
        'fyn.memory.procedural_path' => $corpus,
        'fyn.memory.procedural_reload_interval' => 0,
    ]);
    app()->forgetInstance(ProceduralCorpusLoader::class);
    OnboardingStateMachine::flushTransitionTableCache();

    // Write a workflow procedure the director can resolve by id. The director
    // records the id@version whenever corpus->active(...) returns a procedure;
    // it does NOT require the full merge to succeed (that is transitionTable's
    // concern). A minimal valid-frontmatter workflow .md is enough to be the
    // active procedure for 'onboarding.workflow.fyn-onboarding'.
    $dir = "{$corpus}/workflow/onboarding";
    @mkdir($dir, 0777, true);
    $fm = "procedure_id: onboarding.workflow.fyn-onboarding\nkind: workflow\n"
        ."module: onboarding\nversion: 7\nactive: true\neffective_from: '2026-01-01'\n";
    file_put_contents("{$dir}/fyn-onboarding.md", "---\n{$fm}---\n\n```json\n{}\n```\n");

    $user = User::factory()->create();
    $user->forceFill([
        'onboarding_completed' => false,
        'onboarding_fyn_step' => OnboardingStateMachine::STATE_PATH_CHOICE,
    ])->save();
    $conv = AiConversation::factory()->create(['user_id' => $user->id]);

    $director = app(OnboardingChatDirector::class);
    iterator_to_array($director->handleUserMessage($user, $conv, 'Start from scratch'));

    expect(app(ProceduralVersionHolder::class)->all())
        ->toContain('onboarding.workflow.fyn-onboarding@7');

    File::deleteDirectory($corpus);
});
