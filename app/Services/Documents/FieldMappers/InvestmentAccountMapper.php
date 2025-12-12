<?php

declare(strict_types=1);

namespace App\Services\Documents\FieldMappers;

class InvestmentAccountMapper extends AbstractFieldMapper
{
    protected array $fieldMappings = [
        'provider' => 'provider',
        'account_number' => 'account_number',
        'account_type' => 'account_type',
        'platform' => 'platform',
        'current_value' => 'current_value',
        'contributions_ytd' => 'contributions_ytd',
        'isa_subscription_current_year' => 'isa_subscription_current_year',
        'platform_fee_percent' => 'platform_fee_percent',
    ];

    public function __construct()
    {
        $this->transformations = [
            'provider' => fn ($v) => $this->normalizeString($v),
            'account_number' => fn ($v) => $this->normalizeString($v),
            'account_type' => fn ($v) => $this->normalizeAccountType($v),
            'platform' => fn ($v) => $this->normalizeString($v),
            'current_value' => fn ($v) => $this->parseDecimal($v),
            'contributions_ytd' => fn ($v) => $this->parseDecimal($v),
            'isa_subscription_current_year' => fn ($v) => $this->parseDecimal($v),
            'platform_fee_percent' => fn ($v) => $this->parsePercentage($v),
        ];
    }

    public function getModelClass(): string
    {
        return \App\Models\Investment\InvestmentAccount::class;
    }

    public function getSubtype(): string
    {
        return 'investment_account';
    }

    public function getRequiredFields(): array
    {
        return [
            'provider',
            'account_type',
            'current_value',
        ];
    }

    public function getOptionalFields(): array
    {
        return [
            'account_number',
            'platform',
            'contributions_ytd',
            'isa_subscription_current_year',
            'platform_fee_percent',
        ];
    }

    /**
     * Also extract and map holdings separately.
     */
    public function mapWithHoldings(array $extractedFields): array
    {
        $accountData = $this->map($extractedFields);

        // Extract holdings if present
        $holdings = [];
        if (isset($extractedFields['holdings']) && is_array($extractedFields['holdings'])) {
            foreach ($extractedFields['holdings'] as $holding) {
                $holdings[] = $this->mapHolding($holding);
            }
        }

        return [
            'account' => $accountData,
            'holdings' => $holdings,
        ];
    }

    /**
     * Map a single holding.
     */
    private function mapHolding(array $holding): array
    {
        return [
            'security_name' => $this->normalizeString($holding['security_name'] ?? null),
            'ticker' => $this->normalizeString($holding['ticker'] ?? null),
            'isin' => $this->normalizeString($holding['isin'] ?? null),
            'asset_type' => $this->normalizeAssetType($holding['asset_type'] ?? null),
            'quantity' => $this->parseDecimal($holding['quantity'] ?? null),
            'current_price' => $this->parseDecimal($holding['current_price'] ?? null),
            'current_value' => $this->parseDecimal($holding['current_value'] ?? null),
        ];
    }

    /**
     * Normalize account type to canonical values.
     */
    private function normalizeAccountType(?string $type): ?string
    {
        if (! $type) {
            return 'gia';
        }

        $type = strtolower(trim($type));

        return match (true) {
            str_contains($type, 'isa') && ! str_contains($type, 'cash') => 'isa',
            str_contains($type, 'gia') || str_contains($type, 'general') => 'gia',
            str_contains($type, 'nsi') || str_contains($type, 'ns&i') || str_contains($type, 'national savings') => 'nsi',
            str_contains($type, 'onshore') => 'onshore_bond',
            str_contains($type, 'offshore') => 'offshore_bond',
            str_contains($type, 'vct') || str_contains($type, 'venture') => 'vct',
            str_contains($type, 'eis') || str_contains($type, 'enterprise') => 'eis',
            default => 'other',
        };
    }

    /**
     * Normalize asset type for holdings.
     */
    private function normalizeAssetType(?string $type): ?string
    {
        if (! $type) {
            return 'fund';
        }

        $type = strtolower(trim($type));

        return match (true) {
            str_contains($type, 'uk') && str_contains($type, 'equit') => 'uk_equity',
            str_contains($type, 'us') && str_contains($type, 'equit') => 'us_equity',
            str_contains($type, 'international') || str_contains($type, 'global') || str_contains($type, 'world') => 'international_equity',
            str_contains($type, 'etf') => 'etf',
            str_contains($type, 'bond') || str_contains($type, 'gilt') || str_contains($type, 'fixed') => 'bond',
            str_contains($type, 'cash') || str_contains($type, 'money market') => 'cash',
            str_contains($type, 'property') || str_contains($type, 'reit') => 'property',
            str_contains($type, 'alternative') || str_contains($type, 'commodity') || str_contains($type, 'gold') => 'alternative',
            str_contains($type, 'fund') || str_contains($type, 'trust') => 'fund',
            default => 'fund',
        };
    }
}
