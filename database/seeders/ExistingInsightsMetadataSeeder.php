<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Insights\InsightArticle;
use App\Models\User;
use Illuminate\Database\Seeder;

class ExistingInsightsMetadataSeeder extends Seeder
{
    public function run(): void
    {
        $author = User::where('is_admin', true)->first() ?? User::first();
        if (! $author) {
            $this->command->warn('ExistingInsightsMetadataSeeder: no user found, skipping.');

            return;
        }

        $articles = [
            [
                'slug' => 'how-much-to-retire-uk',
                'title' => 'How Much Do I Need to Retire in the UK? A Realistic Guide',
                'summary' => 'Calculate your UK retirement number using 2026 PLSA living standards. Pension pot sizes needed and how to bridge the State Pension gap.',
                'category' => 'pensions',
                'tags' => ['Pensions'],
                'bespoke_component' => 'HowMuchToRetireUkPage',
                'published_at' => '2026-04-14 00:00:00',
            ],
            [
                'slug' => 'stocks-shares-isa-uk',
                'title' => 'What Is a Stocks and Shares ISA? How It Works, Benefits & Risks',
                'summary' => 'A complete guide to Stocks and Shares ISAs — how they work, what you can invest in, tax benefits, risks, fees, and how to choose a platform.',
                'category' => 'savings-isa',
                'tags' => ['Savings & ISA'],
                'bespoke_component' => 'StocksSharesIsaUkPage',
                'published_at' => '2026-04-13 00:00:00',
            ],
            [
                'slug' => 'isa-guide-uk',
                'title' => 'The Ultimate Guide to ISAs in the UK: Types, Rules & Best Options',
                'summary' => 'Everything you need to know about ISAs in 2026 — types, allowances, rules, and how to choose the right one for your goals.',
                'category' => 'savings-isa',
                'tags' => ['Savings & ISA'],
                'bespoke_component' => 'IsaGuideUkPage',
                'published_at' => '2026-04-08 00:00:00',
            ],
            [
                'slug' => 'retirement-planning-uk',
                'title' => 'The Complete Guide to Retirement Planning in the UK',
                'summary' => 'Plan a retirement that lasts — pensions, State Pension, ISAs, drawdown strategies, tax and how to estimate what you will need.',
                'category' => 'pensions',
                'tags' => ['Pensions'],
                'bespoke_component' => 'RetirementPlanningUkPage',
                'published_at' => '2026-04-08 00:00:00',
            ],
            [
                'slug' => 'inheritance-tax-uk',
                'title' => 'Inheritance Tax Explained: Thresholds, Rules & How to Calculate IHT',
                'summary' => "Understand UK inheritance tax with our 2026 guide. Learn IHT thresholds, nil rate bands, calculation methods and strategies to reduce your estate's tax bill.",
                'category' => 'estate-planning',
                'tags' => ['Estate planning'],
                'bespoke_component' => 'InheritanceTaxExplainedPage',
                'published_at' => '2026-04-01 00:00:00',
            ],
            [
                'slug' => 'pension-contribution-limits-uk',
                'title' => 'Pension Contribution Limits UK 2026/27: How Much Can You Pay In?',
                'summary' => 'Find out the 2026/27 pension contribution limits, annual allowance, tax relief rates and carry forward rules. Updated guide for UK savers.',
                'category' => 'pensions',
                'tags' => ['Pensions'],
                'bespoke_component' => 'PensionContributionLimitsPage',
                'published_at' => '2026-04-01 00:00:00',
            ],
            [
                'slug' => 'pension-iht-changes-2027',
                'title' => 'Pension Inheritance Tax Changes: April 2027',
                'summary' => "From April 2027, unused pension pots will be included in your estate for Inheritance Tax. Here's what's changing and what you can do.",
                'category' => 'pensions',
                'tags' => ['Pensions', 'Estate planning'],
                'bespoke_component' => 'PensionIhtChanges2027Page',
                'published_at' => '2026-03-01 00:00:00',
            ],
            [
                'slug' => 'isa-allowance-2025-26',
                'title' => 'ISA Allowance: Make the Most of Your Tax-Free Allowance',
                'summary' => 'The ISA allowance remains at the annual limit. Types, deadlines, and strategies for maximising your tax-free savings.',
                'category' => 'savings-isa',
                'tags' => ['Savings & ISA'],
                'bespoke_component' => 'IsaAllowance202526Page',
                'published_at' => '2025-04-01 00:00:00',
            ],
        ];

        foreach ($articles as $data) {
            InsightArticle::updateOrCreate(
                ['slug' => $data['slug']],
                array_merge($data, [
                    'status' => 'published',
                    'is_bespoke' => true,
                    'is_featured' => false,
                    'body_blocks' => [],
                    'author_id' => $author->id,
                ]),
            );
        }

        $this->command->info('ExistingInsightsMetadataSeeder: '.count($articles).' bespoke articles seeded.');
    }
}
