<?php

declare(strict_types=1);

/**
 * Architecture guardrails for the Admin Insights CMS.
 *
 * 1. Vue Router: /insights/:slug catch-all must sit AFTER the 8 named bespoke
 *    routes so Vue Router picks the bespoke component first.
 * 2. Laravel Web: /insights/{slug} middleware route must sit BEFORE the SPA
 *    /{any} catch-all so InsightsSeoMetaInjector runs.
 * 3. No hardcoded tax years or ISA allowances in insights code — everything
 *    flows through TaxConfigService (backend) or taxConfig.js (frontend).
 */

$root = realpath(__DIR__.'/../../');

it('declares the /insights/:slug catch-all after every named Insight route in Vue Router', function () use ($root) {
    $router = file_get_contents($root.'/resources/js/router/index.js');

    preg_match_all("/name:\s*'(Insight[A-Za-z]*)'/", $router, $matches);
    $names = $matches[1];

    expect($names)->not->toBeEmpty();
    expect($names)->toContain('InsightArticle');
    expect(end($names))->toBe('InsightArticle');
});

it('declares /insights/{slug} in routes/web.php before the SPA catch-all', function () use ($root) {
    $web = file_get_contents($root.'/routes/web.php');

    $insightPos = strpos($web, "'/insights/{slug}'");
    $catchAllPos = strpos($web, "'/{any}'");

    expect($insightPos)->not->toBeFalse();
    expect($catchAllPos)->not->toBeFalse();
    expect($insightPos)->toBeLessThan($catchAllPos);
});

it('does not hardcode tax years or ISA allowances anywhere in insights code', function () use ($root) {
    $paths = [
        $root.'/app/Services/Insights',
        $root.'/app/Http/Controllers/Api/Admin/InsightArticleController.php',
        $root.'/app/Http/Controllers/Api/Admin/InsightTemplateController.php',
        $root.'/app/Http/Controllers/Api/Admin/InsightImageController.php',
        $root.'/app/Http/Controllers/Api/Public/InsightController.php',
        $root.'/app/Http/Middleware/InsightsSeoMetaInjector.php',
        $root.'/app/Http/Requests/Admin/Insights',
        $root.'/app/Http/Resources/Insights',
        $root.'/app/Models/Insights',
        $root.'/app/Observers/InsightArticleObserver.php',
        $root.'/app/Jobs/PublishScheduledInsightsJob.php',
        $root.'/resources/js/components/Insights',
        $root.'/resources/js/components/Admin/Insights',
        $root.'/resources/js/views/Public/insights/InsightArticlePage.vue',
        $root.'/resources/js/views/Public/insights/InsightsHubPage.vue',
        $root.'/resources/js/views/Admin/Insights',
        $root.'/resources/js/services/insightsService.js',
        $root.'/resources/js/store/modules/insights.js',
    ];

    // Only catch STANDALONE quoted tax-year literals (e.g. `'2026/27'`, `"2026/27"`)
    // or bare numeric amounts. Article titles and summaries can legitimately contain
    // "2026/27" as part of prose — those are content, not config.
    $patterns = [
        "/['\"]20\\d\\d\\/\\d\\d['\"]/",   // '2026/27' as a standalone string value
        '/(?<!\w)20000(?!\w)/',             // ISA allowance as a bare int (not inside e.g. "200000")
        '/(?<!\w)60000(?!\w)/',             // Pension annual allowance
        '/(?<!\w)325000(?!\w)/',            // IHT NRB
        '/(?<!\w)12570(?!\w)/',             // Personal allowance
    ];

    $violations = [];
    foreach ($paths as $path) {
        if (! file_exists($path)) {
            continue;
        }

        $files = is_dir($path)
            ? iterator_to_array(new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path)))
            : [new \SplFileInfo($path)];

        foreach ($files as $file) {
            if (! ($file instanceof \SplFileInfo) || ! $file->isFile()) {
                continue;
            }
            if (! in_array($file->getExtension(), ['php', 'vue', 'js'], true)) {
                continue;
            }

            $contents = file_get_contents($file->getPathname());
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $contents, $m)) {
                    $violations[] = basename($file->getPathname()).': '.$m[0];
                }
            }
        }
    }

    expect($violations)->toBe([]);
});

it('type-hints constructor parameters on Insights services', function () use ($root) {
    $files = glob($root.'/app/Services/Insights/*.php');

    expect($files)->not->toBeEmpty();

    foreach ($files as $file) {
        $content = file_get_contents($file);

        if (preg_match('/public function __construct\s*\(\s*\)/', $content)) {
            continue;
        }

        if (preg_match('/public function __construct\s*\((.+?)\)/s', $content, $m)) {
            expect($m[1])->toMatch('/\\\\?[A-Z][A-Za-z]+\s+\$/');
        }
    }
});
