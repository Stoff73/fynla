<?php

declare(strict_types=1);

const PRE_REMEDIATION_TRIAL_COPY_INVENTORY = [
    'fynlaDesignGuide.md' => 'historical-only',
    'public/pages/about.php' => 'changed',
    'public/pages/calculators.php' => 'changed',
    'public/pages/faq.php' => 'changed',
    'public/pages/features.php' => 'changed',
    'public/pages/features/ice-letters.php' => 'changed',
    'public/pages/features/iht-planning.php' => 'changed',
    'public/pages/features/monte-carlo.php' => 'changed',
    'public/pages/features/net-worth-dashboard.php' => 'changed',
    'public/pages/features/pension-tracker.php' => 'changed',
    'public/pages/features/protection-gap.php' => 'changed',
    'public/pages/features/when-can-i-retire.php' => 'changed',
    'public/pages/help.php' => 'changed',
    'public/pages/how-it-works.php' => 'changed',
    'public/pages/partials/modules/cta-band.php' => 'changed',
    'public/pages/why-fynla/alternatives.php' => 'changed',
    'public/pages/why-fynla/independent.php' => 'changed',
    'public/pages/why-fynla/one-platform.php' => 'changed',
    'public/pages/why-fynla/our-approach.php' => 'changed',
    'resources/js/components/Public/CalculatorCard.vue' => 'changed',
    'resources/js/components/Public/FeaturePageLayout.vue' => 'changed',
    'resources/js/constants/faqData.js' => 'changed',
    'resources/js/utils/subscriptionPresentation.js' => 'compatibility',
    'resources/js/views/Public/AboutPage.vue' => 'changed',
    'resources/js/views/Public/CalculatorsPage.vue' => 'changed',
    'resources/js/views/Public/CampaignPage.vue' => 'changed',
    'resources/js/views/Public/FeaturesPage.vue' => 'changed',
    'resources/js/views/Public/HowItWorksPage.vue' => 'changed',
    'resources/js/views/Public/NewsArticlePage.vue' => 'changed',
    'resources/js/views/Public/QuickStartPage.vue' => 'changed',
    'resources/js/views/Public/insights/HowMuchToRetireUkPage.vue' => 'changed',
    'resources/js/views/Public/insights/IsaGuideUkPage.vue' => 'changed',
    'resources/js/views/Public/insights/RetirementPlanningUkPage.vue' => 'changed',
    'resources/js/views/Public/insights/StocksSharesIsaUkPage.vue' => 'changed',
    'resources/views/emails/lifecycle/countdown.blade.php' => 'deleted',
    'resources/views/emails/lifecycle/subscribe-in-progress.blade.php' => 'deleted',
    'resources/views/emails/lifecycle/subscribe-max-discount.blade.php' => 'deleted',
    'resources/views/emails/modules/counter.blade.php' => 'changed',
    'resources/views/emails/modules/notice.blade.php' => 'changed',
];

function obsoleteTrialCopyPattern(): string
{
    return '/\bfree trial\b|7-day trial|trial has ended|trial ends|trial expires|status\s*===\s*[\'\"]trialing[\'\"]/i';
}

it('classifies every pre-remediation active-surface match', function () {
    expect(PRE_REMEDIATION_TRIAL_COPY_INVENTORY)->toHaveCount(39)
        ->and(array_count_values(PRE_REMEDIATION_TRIAL_COPY_INVENTORY))->toBe([
            'historical-only' => 1,
            'changed' => 34,
            'compatibility' => 1,
            'deleted' => 3,
        ]);

    foreach (PRE_REMEDIATION_TRIAL_COPY_INVENTORY as $relativePath => $classification) {
        $exists = is_file(base_path($relativePath));

        if ($classification === 'deleted') {
            expect($exists, $relativePath)->toBeFalse();
        } else {
            expect($exists, $relativePath)->toBeTrue();
        }
    }
});

it('contains no obsolete trial claims in active source files', function () {
    $sourceRoots = [
        app_path(),
        config_path(),
        database_path('migrations'),
        base_path('routes'),
        public_path('pages'),
        resource_path('js'),
        resource_path('mobile'),
        resource_path('views/emails'),
    ];
    $compatibilityMatches = [];
    $violations = [];

    foreach ($sourceRoots as $root) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        );

        foreach ($files as $file) {
            if (! $file->isFile() || str_ends_with($file->getFilename(), '.bak')) {
                continue;
            }

            $extension = $file->getExtension();
            if (! in_array($extension, ['php', 'vue', 'js'], true)) {
                continue;
            }

            $relativePath = str_replace('\\', '/', substr($file->getPathname(), strlen(base_path()) + 1));
            $contents = (string) file_get_contents($file->getPathname());
            preg_match_all(obsoleteTrialCopyPattern(), $contents, $matches);

            foreach ($matches[0] as $match) {
                if (in_array($match, $compatibilityMatches[$relativePath] ?? [], true)) {
                    continue;
                }

                $violations[] = "{$relativePath}: {$match}";
            }
        }
    }

    expect($violations)->toBe([]);
});

it('removes orphaned trial lifecycle mail classes and templates', function () {
    foreach ([
        'app/Mail/Lifecycle/CountdownMail.php',
        'app/Mail/Lifecycle/SubscribeInProgressMail.php',
        'app/Mail/Lifecycle/SubscribeMaxDiscountMail.php',
        'resources/views/emails/lifecycle/countdown.blade.php',
        'resources/views/emails/lifecycle/subscribe-in-progress.blade.php',
        'resources/views/emails/lifecycle/subscribe-max-discount.blade.php',
    ] as $relativePath) {
        expect(is_file(base_path($relativePath)), $relativePath)->toBeFalse();
    }

    $runtime = '';
    foreach ([app_path(), config_path(), base_path('routes')] as $root) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        );

        foreach ($files as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $runtime .= file_get_contents($file->getPathname());
            }
        }
    }

    expect($runtime)
        ->not->toMatch('/lifecycle\.(countdown|subscribe-in-progress|subscribe-max-discount)/')
        ->not->toMatch('/emails\.lifecycle\.(countdown|subscribe-in-progress|subscribe-max-discount)/');
});

it('describes permanent Free and the implemented paid-churn retention path', function () {
    $terms = (string) file_get_contents(resource_path('js/views/Public/TermsOfServicePage.vue'));
    $privacy = (string) file_get_contents(resource_path('js/views/Public/PrivacyPolicyPage.vue'));

    expect($terms)
        ->toContain('Free access with no time limit')
        ->toContain('30-day read-only grace period')
        ->toContain('currently seven years')
        ->and($privacy)
        ->toContain('seven-year regulatory retention period')
        ->not->toContain('Deleted immediately on account closure')
        ->not->toContain('Duration of account plus 30 days');
});
