<?php

declare(strict_types=1);

$root = realpath(__DIR__.'/../../');

it('every preview write-block site checks the bypass-preview-mode token ability', function () use ($root): void {
    $sites = [
        'app/Http/Middleware/PreviewWriteInterceptor.php',
        'app/Traits/HasAiChat.php',
        'app/Agents/CoordinatingAgent.php',
    ];

    foreach ($sites as $site) {
        $contents = (string) file_get_contents($root.'/'.$site);
        expect(
            $contents,
            "{$site} does NOT check bypass-preview-mode ability — eval writes will be silently swallowed"
        )->toContain('bypass-preview-mode');
    }
});
