<?php

declare(strict_types=1);

/**
 * Pure file-assertion test (mirrors tests/Architecture/DesignSystemInvariantsTest's
 * __DIR__-relative file reading) — needs no bootstrapped Laravel container, so it
 * runs even though tests/Unit/Frontend is not bound to TestCase in Pest.php.
 */
function projectPath(string $relative): string
{
    return realpath(__DIR__.'/../../../').'/'.$relative;
}

it('ships the procedural corpus viewer view wrapped in AppLayout', function (): void {
    $path = projectPath('resources/js/views/Admin/ProceduralCorpusViewer.vue');
    expect(file_exists($path))->toBeTrue();

    $contents = (string) file_get_contents($path);
    expect($contents)->toContain('<AppLayout>')
        ->and($contents)->toContain('</AppLayout>')
        ->and($contents)->toContain("import AppLayout from '@/layouts/AppLayout.vue'");
});

it('ships the procedural corpus API service with the two read methods', function (): void {
    $path = projectPath('resources/js/services/proceduralCorpusService.js');
    expect(file_exists($path))->toBeTrue();

    $contents = (string) file_get_contents($path);
    expect($contents)->toContain('getCorpus')
        ->and($contents)->toContain('getProcedure')
        ->and($contents)->toContain('/admin/procedural-corpus');
});

it('registers the procedural corpus admin route as a lazy admin route', function (): void {
    $contents = (string) file_get_contents(projectPath('resources/js/router/index.js'));
    expect($contents)->toContain('/admin/procedural-corpus')
        ->and($contents)->toContain('ProceduralCorpusViewer')
        ->and($contents)->toContain("import('@/views/Admin/ProceduralCorpusViewer.vue')");
});

it('the procedural corpus viewer contains no icon-font, emoji, Unicode-as-icon, or pseudo-element glyph (Rule #16)', function (): void {
    $contents = (string) file_get_contents(projectPath('resources/js/views/Admin/ProceduralCorpusViewer.vue'));

    // Icon fonts / mascot inline icons.
    expect($contents)->not->toContain('fa-')
        ->and($contents)->not->toContain('material-icons')
        ->and($contents)->not->toContain('::before')
        ->and($contents)->not->toContain('::after');

    // No emoji or Unicode-as-icon glyphs anywhere in the file.
    $banned = ['★', '✓', '✗', '→', '←', '⚠', 'ℹ', '▲', '▼', '🔥', '🎯', '📈', '⭐', '🏆'];
    foreach ($banned as $glyph) {
        expect($contents)->not->toContain($glyph);
    }
    // Catch any other emoji via a broad pictographic/dingbat/arrow range.
    expect(preg_match('/[\x{2190}-\x{21FF}\x{2300}-\x{27BF}\x{1F000}-\x{1FAFF}\x{2600}-\x{26FF}]/u', $contents))
        ->toBe(0);
});

it('the procedural corpus viewer surfaces no numeric score in user-facing copy (Rule #13)', function (): void {
    $contents = (string) file_get_contents(projectPath('resources/js/views/Admin/ProceduralCorpusViewer.vue'));
    // No "/100" score formatting and no "score" label in the template copy.
    expect($contents)->not->toContain('/100')
        ->and(strtolower($contents))->not->toContain('score');
});
