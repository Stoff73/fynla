<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TaxProductReferenceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Seeds UK tax treatment reference data for investment and savings products.
     */
    public function run(): void
    {
        // Clear existing data
        DB::table('tax_product_reference')->truncate();

        $taxData = $this->getTaxReferenceData();

        foreach ($taxData as $data) {
            DB::table('tax_product_reference')->insert(array_merge($data, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }

        $this->command->info('Tax product reference data seeded successfully.');
    }

    /**
     * Get all tax reference data for UK investment and savings products.
     */
    private function getTaxReferenceData(): array
    {
        return array_merge(
            $this->getInvestmentTaxData(),
            $this->getSavingsTaxData()
        );
    }

    /**
     * Investment product tax reference data.
     */
    private function getInvestmentTaxData(): array
    {
        return [
            // ISA (Stocks & Shares)
            [
                'product_category' => 'investment',
                'product_type' => 'isa',
                'tax_aspect' => 'income_tax',
                'title' => 'Income Tax',
                'summary' => 'All income (dividends and interest) is completely tax-free. Does not use your dividend allowance or Personal Savings Allowance.',
                'status' => 'exempt',
                'status_icon' => 'check',
                'display_order' => 1,
                'is_active' => true,
            ],
            [
                'product_category' => 'investment',
                'product_type' => 'isa',
                'tax_aspect' => 'cgt',
                'title' => 'Capital Gains Tax',
                'summary' => 'All gains are exempt from CGT. Losses cannot be offset against gains outside the ISA.',
                'status' => 'exempt',
                'status_icon' => 'check',
                'display_order' => 2,
                'is_active' => true,
            ],
            [
                'product_category' => 'investment',
                'product_type' => 'isa',
                'tax_aspect' => 'iht',
                'title' => 'Inheritance Tax',
                'summary' => 'Forms part of your estate for IHT purposes. Spouse/civil partner can inherit an additional ISA allowance (APS).',
                'status' => 'taxable',
                'status_icon' => 'alert',
                'display_order' => 3,
                'is_active' => true,
            ],
            [
                'product_category' => 'investment',
                'product_type' => 'isa',
                'tax_aspect' => 'allowances',
                'title' => 'Annual Allowance',
                'summary' => 'Annual subscription limit of £20,000 across all ISA types. Unused allowance cannot be carried forward.',
                'status' => 'limit',
                'status_icon' => 'info',
                'display_order' => 4,
                'is_active' => true,
            ],

            // GIA (General Investment Account)
            [
                'product_category' => 'investment',
                'product_type' => 'gia',
                'tax_aspect' => 'dividends',
                'title' => 'Dividend Tax',
                'summary' => 'Dividends taxable above £500 allowance. Rates: 8.75% (basic), 33.75% (higher), 39.35% (additional).',
                'status' => 'taxable',
                'status_icon' => 'alert',
                'display_order' => 1,
                'is_active' => true,
            ],
            [
                'product_category' => 'investment',
                'product_type' => 'gia',
                'tax_aspect' => 'income_tax',
                'title' => 'Interest Income',
                'summary' => 'Interest taxable above Personal Savings Allowance (£1,000 basic rate, £500 higher rate).',
                'status' => 'taxable',
                'status_icon' => 'alert',
                'display_order' => 2,
                'is_active' => true,
            ],
            [
                'product_category' => 'investment',
                'product_type' => 'gia',
                'tax_aspect' => 'cgt',
                'title' => 'Capital Gains Tax',
                'summary' => 'Gains taxable above £3,000 annual exemption. Rates: 10%/18% (basic), 20%/24% (higher). Losses can offset gains.',
                'status' => 'taxable',
                'status_icon' => 'alert',
                'display_order' => 3,
                'is_active' => true,
            ],
            [
                'product_category' => 'investment',
                'product_type' => 'gia',
                'tax_aspect' => 'iht',
                'title' => 'Inheritance Tax',
                'summary' => 'Forms part of your estate for IHT purposes. Death resets CGT cost basis to market value.',
                'status' => 'taxable',
                'status_icon' => 'alert',
                'display_order' => 4,
                'is_active' => true,
            ],

            // Onshore Bond
            [
                'product_category' => 'investment',
                'product_type' => 'onshore_bond',
                'tax_aspect' => 'income_tax',
                'title' => 'Income Tax on Gains',
                'summary' => 'Gains taxed as income with a 20% basic rate credit (already paid by the life fund). Basic rate taxpayers have no further tax to pay.',
                'status' => 'deferred',
                'status_icon' => 'clock',
                'display_order' => 1,
                'is_active' => true,
            ],
            [
                'product_category' => 'investment',
                'product_type' => 'onshore_bond',
                'tax_aspect' => 'withdrawals',
                'title' => '5% Tax-Deferred Withdrawals',
                'summary' => 'Withdraw up to 5% of original investment annually without immediate tax. Allowance rolls over if unused.',
                'status' => 'deferred',
                'status_icon' => 'clock',
                'display_order' => 2,
                'is_active' => true,
            ],
            [
                'product_category' => 'investment',
                'product_type' => 'onshore_bond',
                'tax_aspect' => 'special_features',
                'title' => 'Top-Slicing Relief',
                'summary' => 'Large gains spread over years held to determine effective tax rate, preventing unfair higher rate charges.',
                'status' => 'relief',
                'status_icon' => 'trending-down',
                'display_order' => 3,
                'is_active' => true,
            ],
            [
                'product_category' => 'investment',
                'product_type' => 'onshore_bond',
                'tax_aspect' => 'iht',
                'title' => 'Inheritance Tax',
                'summary' => 'Forms part of estate for IHT. Gain on death taxed as income. Often used with trusts for IHT planning.',
                'status' => 'taxable',
                'status_icon' => 'alert',
                'display_order' => 4,
                'is_active' => true,
            ],

            // Offshore Bond
            [
                'product_category' => 'investment',
                'product_type' => 'offshore_bond',
                'tax_aspect' => 'income_tax',
                'title' => 'Income Tax on Gains',
                'summary' => 'Gains taxed at full marginal rate with no tax credit. Gross roll-up means no tax during accumulation.',
                'status' => 'deferred',
                'status_icon' => 'clock',
                'display_order' => 1,
                'is_active' => true,
            ],
            [
                'product_category' => 'investment',
                'product_type' => 'offshore_bond',
                'tax_aspect' => 'withdrawals',
                'title' => '5% Tax-Deferred Withdrawals',
                'summary' => 'Same 5% annual withdrawal allowance as onshore bonds. Cumulative unused allowance available.',
                'status' => 'deferred',
                'status_icon' => 'clock',
                'display_order' => 2,
                'is_active' => true,
            ],
            [
                'product_category' => 'investment',
                'product_type' => 'offshore_bond',
                'tax_aspect' => 'special_features',
                'title' => 'Time Apportionment Relief',
                'summary' => 'Gains reduced proportionally for periods of non-UK residence. Useful for those who have lived abroad.',
                'status' => 'relief',
                'status_icon' => 'trending-down',
                'display_order' => 3,
                'is_active' => true,
            ],
            [
                'product_category' => 'investment',
                'product_type' => 'offshore_bond',
                'tax_aspect' => 'iht',
                'title' => 'Inheritance Tax',
                'summary' => 'Forms part of estate for IHT. Often used with trusts to exclude from estate.',
                'status' => 'taxable',
                'status_icon' => 'alert',
                'display_order' => 4,
                'is_active' => true,
            ],

            // VCT (Venture Capital Trust)
            [
                'product_category' => 'investment',
                'product_type' => 'vct',
                'tax_aspect' => 'income_tax',
                'title' => 'Income Tax Relief',
                'summary' => '30% income tax relief on new subscriptions up to £200,000 per year. Must hold for 5 years or relief is clawed back.',
                'status' => 'relief',
                'status_icon' => 'trending-down',
                'display_order' => 1,
                'is_active' => true,
            ],
            [
                'product_category' => 'investment',
                'product_type' => 'vct',
                'tax_aspect' => 'dividends',
                'title' => 'Tax-Free Dividends',
                'summary' => 'All dividends from VCT shares are completely tax-free with no limit.',
                'status' => 'exempt',
                'status_icon' => 'check',
                'display_order' => 2,
                'is_active' => true,
            ],
            [
                'product_category' => 'investment',
                'product_type' => 'vct',
                'tax_aspect' => 'cgt',
                'title' => 'Capital Gains Tax',
                'summary' => 'No CGT on gains from VCT shares. Losses are not allowable for offset.',
                'status' => 'exempt',
                'status_icon' => 'check',
                'display_order' => 3,
                'is_active' => true,
            ],
            [
                'product_category' => 'investment',
                'product_type' => 'vct',
                'tax_aspect' => 'iht',
                'title' => 'Inheritance Tax',
                'summary' => 'VCT shares are NOT exempt from IHT (no Business Property Relief). Forms part of estate.',
                'status' => 'taxable',
                'status_icon' => 'alert',
                'display_order' => 4,
                'is_active' => true,
            ],

            // EIS (Enterprise Investment Scheme)
            [
                'product_category' => 'investment',
                'product_type' => 'eis',
                'tax_aspect' => 'income_tax',
                'title' => 'Income Tax Relief',
                'summary' => '30% income tax relief on investments up to £1m (£2m for knowledge-intensive companies). Must hold 3 years.',
                'status' => 'relief',
                'status_icon' => 'trending-down',
                'display_order' => 1,
                'is_active' => true,
            ],
            [
                'product_category' => 'investment',
                'product_type' => 'eis',
                'tax_aspect' => 'cgt',
                'title' => 'Capital Gains Tax',
                'summary' => 'No CGT on gains after 3 years. Can also defer existing gains by reinvesting in EIS.',
                'status' => 'exempt',
                'status_icon' => 'check',
                'display_order' => 2,
                'is_active' => true,
            ],
            [
                'product_category' => 'investment',
                'product_type' => 'eis',
                'tax_aspect' => 'iht',
                'title' => 'Inheritance Tax',
                'summary' => '100% Business Property Relief after 2 years - shares can pass IHT-free. Deferred gains wiped out on death.',
                'status' => 'exempt',
                'status_icon' => 'check',
                'display_order' => 3,
                'is_active' => true,
            ],
            [
                'product_category' => 'investment',
                'product_type' => 'eis',
                'tax_aspect' => 'special_features',
                'title' => 'Loss Relief',
                'summary' => 'If investment fails, losses (net of income tax relief) can be offset against income or capital gains.',
                'status' => 'relief',
                'status_icon' => 'shield',
                'display_order' => 4,
                'is_active' => true,
            ],

            // NSI (Investment - Premium Bonds, etc.)
            [
                'product_category' => 'investment',
                'product_type' => 'nsi',
                'tax_aspect' => 'income_tax',
                'title' => 'Interest/Prize Income',
                'summary' => 'Premium Bond prizes are completely tax-free. Other NS&I products vary - check specific product terms.',
                'status' => 'exempt',
                'status_icon' => 'check',
                'display_order' => 1,
                'is_active' => true,
            ],
            [
                'product_category' => 'investment',
                'product_type' => 'nsi',
                'tax_aspect' => 'cgt',
                'title' => 'Capital Gains Tax',
                'summary' => 'No CGT applies to NS&I products (cash-based, no capital appreciation).',
                'status' => 'exempt',
                'status_icon' => 'check',
                'display_order' => 2,
                'is_active' => true,
            ],
            [
                'product_category' => 'investment',
                'product_type' => 'nsi',
                'tax_aspect' => 'iht',
                'title' => 'Inheritance Tax',
                'summary' => 'Forms part of estate for IHT. 100% backed by HM Treasury for security.',
                'status' => 'taxable',
                'status_icon' => 'alert',
                'display_order' => 3,
                'is_active' => true,
            ],
            [
                'product_category' => 'investment',
                'product_type' => 'nsi',
                'tax_aspect' => 'allowances',
                'title' => 'Investment Limits',
                'summary' => 'Premium Bonds: £50,000 maximum holding. Other NS&I products have varying limits.',
                'status' => 'limit',
                'status_icon' => 'info',
                'display_order' => 4,
                'is_active' => true,
            ],

            // Other/SEIS
            [
                'product_category' => 'investment',
                'product_type' => 'other',
                'tax_aspect' => 'general',
                'title' => 'Tax Treatment',
                'summary' => 'Tax treatment varies by investment type. Consult the specific product documentation or a tax adviser.',
                'status' => 'taxable',
                'status_icon' => 'info',
                'display_order' => 1,
                'is_active' => true,
            ],
        ];
    }

    /**
     * Savings product tax reference data.
     */
    private function getSavingsTaxData(): array
    {
        return [
            // Cash ISA
            [
                'product_category' => 'savings',
                'product_type' => 'cash_isa',
                'tax_aspect' => 'income_tax',
                'title' => 'Interest Income',
                'summary' => 'All interest is completely tax-free. Does not use your Personal Savings Allowance.',
                'status' => 'exempt',
                'status_icon' => 'check',
                'display_order' => 1,
                'is_active' => true,
            ],
            [
                'product_category' => 'savings',
                'product_type' => 'cash_isa',
                'tax_aspect' => 'iht',
                'title' => 'Inheritance Tax',
                'summary' => 'Forms part of your estate for IHT. Spouse can inherit additional ISA allowance.',
                'status' => 'taxable',
                'status_icon' => 'alert',
                'display_order' => 2,
                'is_active' => true,
            ],
            [
                'product_category' => 'savings',
                'product_type' => 'cash_isa',
                'tax_aspect' => 'allowances',
                'title' => 'Annual Allowance',
                'summary' => 'Counts towards your £20,000 annual ISA allowance (shared across all ISA types).',
                'status' => 'limit',
                'status_icon' => 'info',
                'display_order' => 3,
                'is_active' => true,
            ],

            // Junior ISA
            [
                'product_category' => 'savings',
                'product_type' => 'junior_isa',
                'tax_aspect' => 'income_tax',
                'title' => 'Interest Income',
                'summary' => 'All interest is completely tax-free for the child. No access until age 18.',
                'status' => 'exempt',
                'status_icon' => 'check',
                'display_order' => 1,
                'is_active' => true,
            ],
            [
                'product_category' => 'savings',
                'product_type' => 'junior_isa',
                'tax_aspect' => 'allowances',
                'title' => 'Annual Allowance',
                'summary' => 'Separate £9,000 annual allowance for children. Converts to adult ISA at 18.',
                'status' => 'limit',
                'status_icon' => 'info',
                'display_order' => 2,
                'is_active' => true,
            ],

            // Easy Access / Standard Savings
            [
                'product_category' => 'savings',
                'product_type' => 'easy_access',
                'tax_aspect' => 'income_tax',
                'title' => 'Interest Income',
                'summary' => 'Interest taxable above Personal Savings Allowance (£1,000 basic rate, £500 higher rate, £0 additional rate).',
                'status' => 'taxable',
                'status_icon' => 'alert',
                'display_order' => 1,
                'is_active' => true,
            ],
            [
                'product_category' => 'savings',
                'product_type' => 'easy_access',
                'tax_aspect' => 'special_features',
                'title' => 'Starting Rate Band',
                'summary' => 'If total income under £17,570, may qualify for £5,000 starting rate band at 0% on savings.',
                'status' => 'relief',
                'status_icon' => 'info',
                'display_order' => 2,
                'is_active' => true,
            ],
            [
                'product_category' => 'savings',
                'product_type' => 'easy_access',
                'tax_aspect' => 'iht',
                'title' => 'Inheritance Tax',
                'summary' => 'Forms part of your estate for IHT purposes.',
                'status' => 'taxable',
                'status_icon' => 'alert',
                'display_order' => 3,
                'is_active' => true,
            ],

            // Notice Account
            [
                'product_category' => 'savings',
                'product_type' => 'notice',
                'tax_aspect' => 'income_tax',
                'title' => 'Interest Income',
                'summary' => 'Interest taxable above Personal Savings Allowance. Paid gross - you may need to declare on tax return.',
                'status' => 'taxable',
                'status_icon' => 'alert',
                'display_order' => 1,
                'is_active' => true,
            ],
            [
                'product_category' => 'savings',
                'product_type' => 'notice',
                'tax_aspect' => 'iht',
                'title' => 'Inheritance Tax',
                'summary' => 'Forms part of your estate for IHT purposes.',
                'status' => 'taxable',
                'status_icon' => 'alert',
                'display_order' => 2,
                'is_active' => true,
            ],

            // Fixed Rate
            [
                'product_category' => 'savings',
                'product_type' => 'fixed_rate',
                'tax_aspect' => 'income_tax',
                'title' => 'Interest Income',
                'summary' => 'Interest taxable above Personal Savings Allowance. Interest may be paid annually or at maturity.',
                'status' => 'taxable',
                'status_icon' => 'alert',
                'display_order' => 1,
                'is_active' => true,
            ],
            [
                'product_category' => 'savings',
                'product_type' => 'fixed_rate',
                'tax_aspect' => 'iht',
                'title' => 'Inheritance Tax',
                'summary' => 'Forms part of your estate for IHT purposes.',
                'status' => 'taxable',
                'status_icon' => 'alert',
                'display_order' => 2,
                'is_active' => true,
            ],

            // Premium Bonds
            [
                'product_category' => 'savings',
                'product_type' => 'premium_bonds',
                'tax_aspect' => 'income_tax',
                'title' => 'Prize Income',
                'summary' => 'All prizes are completely tax-free - from £25 to the £1 million jackpot.',
                'status' => 'exempt',
                'status_icon' => 'check',
                'display_order' => 1,
                'is_active' => true,
            ],
            [
                'product_category' => 'savings',
                'product_type' => 'premium_bonds',
                'tax_aspect' => 'cgt',
                'title' => 'Capital Gains Tax',
                'summary' => 'No CGT - bonds always redeemed at face value. No capital appreciation.',
                'status' => 'exempt',
                'status_icon' => 'check',
                'display_order' => 2,
                'is_active' => true,
            ],
            [
                'product_category' => 'savings',
                'product_type' => 'premium_bonds',
                'tax_aspect' => 'iht',
                'title' => 'Inheritance Tax',
                'summary' => 'Forms part of estate for IHT. Can continue in prize draws for 12 months after death.',
                'status' => 'taxable',
                'status_icon' => 'alert',
                'display_order' => 3,
                'is_active' => true,
            ],
            [
                'product_category' => 'savings',
                'product_type' => 'premium_bonds',
                'tax_aspect' => 'allowances',
                'title' => 'Investment Limits',
                'summary' => 'Maximum holding of £50,000 per person. 100% backed by HM Treasury.',
                'status' => 'limit',
                'status_icon' => 'info',
                'display_order' => 4,
                'is_active' => true,
            ],

            // NS&I Savings (taxable products)
            [
                'product_category' => 'savings',
                'product_type' => 'nsi',
                'tax_aspect' => 'income_tax',
                'title' => 'Interest Income',
                'summary' => 'Interest is taxable. Paid gross - PSA applies. Not all NS&I products are tax-free.',
                'status' => 'taxable',
                'status_icon' => 'alert',
                'display_order' => 1,
                'is_active' => true,
            ],
            [
                'product_category' => 'savings',
                'product_type' => 'nsi',
                'tax_aspect' => 'iht',
                'title' => 'Inheritance Tax',
                'summary' => 'Forms part of estate for IHT. 100% backed by HM Treasury (no £85k FSCS limit).',
                'status' => 'taxable',
                'status_icon' => 'alert',
                'display_order' => 2,
                'is_active' => true,
            ],

            // Lifetime ISA
            [
                'product_category' => 'savings',
                'product_type' => 'lifetime_isa',
                'tax_aspect' => 'income_tax',
                'title' => 'Interest Income',
                'summary' => 'All interest and growth is completely tax-free. Government adds 25% bonus on contributions.',
                'status' => 'exempt',
                'status_icon' => 'check',
                'display_order' => 1,
                'is_active' => true,
            ],
            [
                'product_category' => 'savings',
                'product_type' => 'lifetime_isa',
                'tax_aspect' => 'special_features',
                'title' => 'Government Bonus',
                'summary' => '25% bonus (up to £1,000/year) on contributions up to £4,000. Penalty for non-qualifying withdrawals.',
                'status' => 'relief',
                'status_icon' => 'gift',
                'display_order' => 2,
                'is_active' => true,
            ],
            [
                'product_category' => 'savings',
                'product_type' => 'lifetime_isa',
                'tax_aspect' => 'allowances',
                'title' => 'Annual Allowance',
                'summary' => 'Maximum £4,000 per year (counts towards £20,000 ISA limit). Must be 18-39 to open.',
                'status' => 'limit',
                'status_icon' => 'info',
                'display_order' => 3,
                'is_active' => true,
            ],
        ];
    }
}
