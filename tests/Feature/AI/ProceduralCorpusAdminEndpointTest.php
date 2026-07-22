<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->corpus = sys_get_temp_dir().'/proc-4f-'.uniqid();
    config(['fyn.memory.procedural_path' => $this->corpus]);
    Cache::flush();
});

afterEach(function (): void {
    File::deleteDirectory($this->corpus);
    Cache::flush();
});

/** Write a procedure .md at {kind}/{module}/{file}.md with the given frontmatter + body. */
function writeCorpusProc(string $root, string $kind, string $module, string $file, array $frontmatter, string $body = 'Procedure body.'): void
{
    $dir = "{$root}/{$kind}/{$module}";
    @mkdir($dir, 0777, true);
    $fm = '';
    foreach ($frontmatter as $k => $v) {
        $fm .= $k.': '.(is_bool($v) ? ($v ? 'true' : 'false') : $v)."\n";
    }
    file_put_contents("{$dir}/{$file}.md", "---\n{$fm}---\n\n{$body}\n");
}

function frontmatter4f(array $overrides = []): array
{
    return array_merge([
        'procedure_id' => 'retirement.tool.create_dc_pension',
        'kind' => 'tool_schema',
        'module' => 'retirement',
        'version' => 1,
        'active' => true,
        'effective_from' => '2026-06-02',
    ], $overrides);
}

describe('procedural corpus admin endpoints — auth', function (): void {
    it('rejects an unauthenticated request with 401', function (): void {
        $this->getJson('/api/admin/procedural-corpus')->assertUnauthorized();
    });

    it('rejects a non-admin user with 403', function (): void {
        $plain = User::factory()->create(['is_admin' => false, 'is_advisor' => false]);
        $this->actingAs($plain)->getJson('/api/admin/procedural-corpus')->assertForbidden();
    });
});

describe('procedural corpus admin index', function (): void {
    it('returns an empty groups array for a missing/empty corpus (clean 200)', function (): void {
        $admin = User::factory()->create(['is_admin' => true]);

        $response = $this->actingAs($admin)->getJson('/api/admin/procedural-corpus');

        $response->assertOk()
            ->assertJsonStructure(['success', 'data' => ['groups']]);
        expect($response->json('success'))->toBeTrue()
            ->and($response->json('data.groups'))->toBe([]);
    });

    it('groups procedures by kind then module then procedure_id with a frontmatter-only summary', function (): void {
        $admin = User::factory()->create(['is_admin' => true]);

        // Two versions of one tool_schema procedure: v1 inactive, v2 active.
        writeCorpusProc($this->corpus, 'tool_schema', 'retirement', 'create_dc_pension_v1', frontmatter4f([
            'version' => 1, 'active' => false, 'effective_to' => '2026-06-01',
        ]));
        writeCorpusProc($this->corpus, 'tool_schema', 'retirement', 'create_dc_pension_v2', frontmatter4f([
            'version' => 2, 'active' => true, 'effective_from' => '2026-06-02',
        ]));
        // A different kind/module procedure.
        writeCorpusProc($this->corpus, 'fca_block', 'protection', 'cover_disclaimer', frontmatter4f([
            'procedure_id' => 'protection.fca.cover_disclaimer',
            'kind' => 'fca_block', 'module' => 'protection', 'version' => 1, 'active' => true,
        ]));

        $response = $this->actingAs($admin)->getJson('/api/admin/procedural-corpus');

        $response->assertOk()->assertJsonStructure([
            'success',
            'data' => [
                'groups' => [
                    [
                        'kind',
                        'modules' => [
                            [
                                'module',
                                'procedures' => [
                                    [
                                        'procedure_id',
                                        'active_version',
                                        'version_count',
                                        'versions' => [['version', 'active', 'effective_from', 'effective_to']],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $groups = $response->json('data.groups');
        // Kinds sorted: fca_block before tool_schema.
        expect($groups[0]['kind'])->toBe('fca_block')
            ->and($groups[1]['kind'])->toBe('tool_schema');

        // The tool_schema retirement procedure has two versions, active=2.
        $retirement = collect($groups[1]['modules'])->firstWhere('module', 'retirement');
        expect($retirement)->not->toBeNull();
        $proc = collect($retirement['procedures'])->firstWhere('procedure_id', 'retirement.tool.create_dc_pension');
        expect($proc['version_count'])->toBe(2)
            ->and($proc['active_version'])->toBe(2)
            ->and($proc['versions'][0]['version'])->toBe(1)   // ascending
            ->and($proc['versions'][1]['version'])->toBe(2)
            ->and($proc['versions'][0]['active'])->toBeFalse()
            ->and($proc['versions'][1]['active'])->toBeTrue()
            ->and($proc['versions'][0]['effective_to'])->toBe('2026-06-01');

        // index summary carries NO body field.
        expect($proc['versions'][0])->not->toHaveKey('body');
    });

    it('serialises dates as ISO date strings and null effective_to as null', function (): void {
        $admin = User::factory()->create(['is_admin' => true]);
        writeCorpusProc($this->corpus, 'workflow', 'onboarding', 'transition', frontmatter4f([
            'procedure_id' => 'onboarding.workflow.transition',
            'kind' => 'workflow', 'module' => 'onboarding', 'version' => 1, 'active' => true,
            'effective_from' => '2026-06-02',
        ]));

        $response = $this->actingAs($admin)->getJson('/api/admin/procedural-corpus');

        $version = $response->json('data.groups.0.modules.0.procedures.0.versions.0');
        expect($version['effective_from'])->toBe('2026-06-02')
            ->and($version['effective_to'])->toBeNull();
    });
});

describe('procedural corpus admin show', function (): void {
    it('returns every version (frontmatter + body) of one dotted procedure id', function (): void {
        $admin = User::factory()->create(['is_admin' => true]);
        writeCorpusProc($this->corpus, 'tool_schema', 'retirement', 'create_dc_pension_v1', frontmatter4f([
            'version' => 1, 'active' => false, 'effective_to' => '2026-06-01',
        ]), 'BODY ONE');
        writeCorpusProc($this->corpus, 'tool_schema', 'retirement', 'create_dc_pension_v2', frontmatter4f([
            'version' => 2, 'active' => true,
        ]), 'BODY TWO');

        $response = $this->actingAs($admin)
            ->getJson('/api/admin/procedural-corpus/retirement.tool.create_dc_pension');

        $response->assertOk()->assertJsonStructure([
            'success',
            'data' => [
                'procedure_id',
                'versions' => [['kind', 'module', 'version', 'active', 'effective_from', 'effective_to', 'body']],
            ],
        ]);
        expect($response->json('data.procedure_id'))->toBe('retirement.tool.create_dc_pension');
        $versions = $response->json('data.versions');
        expect($versions)->toHaveCount(2)
            ->and($versions[0]['version'])->toBe(1)        // ascending
            ->and($versions[0]['body'])->toBe('BODY ONE')
            ->and($versions[1]['version'])->toBe(2)
            ->and($versions[1]['body'])->toBe('BODY TWO');
    });

    it('returns an empty versions array (200, not 404) for an unknown procedure id', function (): void {
        $admin = User::factory()->create(['is_admin' => true]);

        $response = $this->actingAs($admin)
            ->getJson('/api/admin/procedural-corpus/does.not.exist');

        $response->assertOk();
        expect($response->json('success'))->toBeTrue()
            ->and($response->json('data.procedure_id'))->toBe('does.not.exist')
            ->and($response->json('data.versions'))->toBe([]);
    });

    it('rejects a non-admin user on the detail endpoint with 403', function (): void {
        $plain = User::factory()->create(['is_admin' => false, 'is_advisor' => false]);
        $this->actingAs($plain)
            ->getJson('/api/admin/procedural-corpus/retirement.tool.create_dc_pension')
            ->assertForbidden();
    });
});
