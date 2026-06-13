<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\AI\Fyn\FynContextAssembler;
use App\Services\AI\Fyn\FynTurnContext;
use App\Services\AI\Memory\Episodic\SemanticSnapshotHolder;
use App\Services\AI\Memory\SemanticRetriever;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(TaxConfigurationSeeder::class);
    $this->corpus = sys_get_temp_dir().'/sem-'.uniqid();
    config(['fyn.memory.semantic_path' => $this->corpus, 'fyn.memory.semantic_top_k' => 4]);
    @mkdir("{$this->corpus}/fca", 0777, true);
    file_put_contents(
        "{$this->corpus}/fca/dbtransfer.md",
        "---\nfact_id: fca-db-transfer\ncategory: fca\ntitle: Defined benefit transfer\nsource: COBS 19\nversion: 1\nvalid_from: 2020-01-01\n---\n\nA defined benefit pension transfer almost always needs regulated advice.\n"
    );
    $this->user = User::factory()->create();
});

afterEach(fn () => File::deleteDirectory($this->corpus));

it('emits a <knowledge> block when the corpus matches the user message', function (): void {
    $ctx = FynTurnContext::make(
        user: $this->user,
        message: 'Should I do a defined benefit pension transfer?',
        currentRoute: '/dashboard',
        mode: 'advice',
        onboardingFocus: null,
        isPreview: false,
        classification: ['primary' => 'general'],
    );

    $out = app(FynContextAssembler::class)->build($ctx);

    expect($out)->toContain('<knowledge>')
        ->and($out)->toContain('Defined benefit transfer')
        ->and($out)->toContain('almost always needs regulated advice');
});

it('stamps the semantic snapshot id into the request scope when the corpus matches', function (): void {
    $ctx = FynTurnContext::make(
        user: $this->user,
        message: 'Should I do a defined benefit pension transfer?',
        currentRoute: '/dashboard',
        mode: 'advice',
        onboardingFocus: null,
        isPreview: false,
        classification: ['primary' => 'general'],
    );

    app(FynContextAssembler::class)->build($ctx);

    $retriever = app(SemanticRetriever::class);
    $expected = $retriever->snapshotId($retriever->retrieve($ctx->message, Carbon::now()));

    expect($expected)->toHaveLength(64)
        ->and(app(SemanticSnapshotHolder::class)->get())->toBe($expected);
});

it('leaves the semantic snapshot id null when nothing matches', function (): void {
    $ctx = FynTurnContext::make(
        user: $this->user,
        message: 'hello there',
        currentRoute: '/dashboard',
        mode: 'advice',
        onboardingFocus: null,
        isPreview: false,
        classification: ['primary' => 'general'],
    );

    app(FynContextAssembler::class)->build($ctx);

    expect(app(SemanticSnapshotHolder::class)->get())->toBeNull();
});

it('omits <knowledge> when nothing matches', function (): void {
    $ctx = FynTurnContext::make(
        user: $this->user,
        message: 'hello there',
        currentRoute: '/dashboard',
        mode: 'advice',
        onboardingFocus: null,
        isPreview: false,
        classification: ['primary' => 'general'],
    );

    expect(app(FynContextAssembler::class)->build($ctx))->not->toContain('<knowledge>');
});

it('degrades to no knowledge block when the corpus is malformed (turn still builds)', function (): void {
    // beforeEach already wrote a valid fca fact; add a broken file so loader->all() throws.
    file_put_contents("{$this->corpus}/fca/broken.md", "no frontmatter at all\n");
    $ctx = FynTurnContext::make(
        user: $this->user,
        message: 'Should I do a defined benefit pension transfer?',
        currentRoute: '/dashboard',
        mode: 'advice',
        onboardingFocus: null,
        isPreview: false,
        classification: ['primary' => 'general'],
    );

    $out = app(FynContextAssembler::class)->build($ctx);

    expect($out)->not->toContain('<knowledge>')   // degraded, not thrown
        ->and($out)->toContain('<context>');        // the rest of the prompt still built
});

it('omits the source suffix for a source-less fact (no "(source: )" noise)', function (): void {
    @mkdir("{$this->corpus}/house_view", 0777, true);
    file_put_contents(
        "{$this->corpus}/house_view/efund.md",
        "---\nfact_id: hv-emergency-fund\ncategory: house_view\ntitle: Emergency fund stance\nversion: 1\nvalid_to: null\n---\n\nFynla suggests building an emergency fund before investing.\n"
    );
    $user = User::factory()->create();
    $ctx = FynTurnContext::make(
        user: $user,
        message: 'Tell me about an emergency fund',
        currentRoute: '/dashboard',
        mode: 'advice',
        onboardingFocus: null,
        isPreview: false,
        classification: ['primary' => 'general'],
    );

    $out = app(FynContextAssembler::class)->build($ctx);

    expect($out)->toContain('<knowledge>')
        ->and($out)->toContain('### Emergency fund stance')
        ->and($out)->not->toContain('(source: )');
});
