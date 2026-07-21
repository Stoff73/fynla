<?php

declare(strict_types=1);
use Tests\TestCase;

uses(TestCase::class);

it('no class references remain for deleted persona machinery', function (): void {
    $patterns = [
        'FynPersonaOrchestrator',
        'FynPersonaInvoker',
        'FynPersonaRegistry',
        'DataCapturePromptBuilder',
    ];
    $roots = [app_path(), config_path(), base_path('tests')];
    $hits = [];
    foreach ($roots as $root) {
        $iter = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
        foreach ($iter as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            // Two tests legitimately mention the deleted class names as
            // string literals: this architecture test itself and
            // DispatchRoutingTest's negative assertions.
            $basename = basename($file->getPathname());
            if (in_array($basename, ['PersonaMachineryAbsentTest.php', 'DispatchRoutingTest.php'], true)) {
                continue;
            }
            $content = file_get_contents($file->getPathname());
            foreach ($patterns as $pat) {
                if (str_contains($content, $pat)) {
                    $hits[] = "{$file->getPathname()}: {$pat}";
                }
            }
        }
    }
    expect($hits)->toBeEmpty();
});
