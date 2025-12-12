<?php

declare(strict_types=1);

namespace App\Services\Documents;

use App\Models\Document;

class DocumentTypeDetector
{
    /**
     * Map document types to their possible subtypes and target models.
     */
    private const TYPE_MODEL_MAP = [
        Document::TYPE_PENSION_STATEMENT => [
            'dc_pension' => \App\Models\DCPension::class,
            'db_pension' => \App\Models\DBPension::class,
            'state_pension' => \App\Models\StatePension::class,
        ],
        Document::TYPE_INSURANCE_POLICY => [
            'life_insurance' => \App\Models\LifeInsurancePolicy::class,
            'critical_illness' => \App\Models\CriticalIllnessPolicy::class,
            'income_protection' => \App\Models\IncomeProtectionPolicy::class,
            'disability' => \App\Models\DisabilityPolicy::class,
            'sickness_illness' => \App\Models\SicknessIllnessPolicy::class,
        ],
        Document::TYPE_INVESTMENT_STATEMENT => [
            'investment_account' => \App\Models\Investment\InvestmentAccount::class,
        ],
        Document::TYPE_MORTGAGE_STATEMENT => [
            'mortgage' => \App\Models\Mortgage::class,
        ],
        Document::TYPE_SAVINGS_STATEMENT => [
            'savings_account' => \App\Models\SavingsAccount::class,
            'cash_account' => \App\Models\CashAccount::class,
        ],
        Document::TYPE_PROPERTY_DOCUMENT => [
            'property' => \App\Models\Property::class,
        ],
    ];

    /**
     * Known UK financial providers for better detection.
     */
    private const KNOWN_PROVIDERS = [
        // Pension Providers
        'pension' => [
            'Scottish Widows', 'Aviva', 'Standard Life', 'Legal & General',
            'Royal London', 'Aegon', 'Nest', 'NOW: Pensions', 'The People\'s Pension',
            'Fidelity', 'AJ Bell', 'Hargreaves Lansdown', 'Interactive Investor',
        ],
        // Insurance Providers
        'insurance' => [
            'Aviva', 'Legal & General', 'Royal London', 'Vitality', 'LV=',
            'Zurich', 'Scottish Widows', 'Liverpool Victoria', 'Guardian',
            'British Friendly', 'Cirencester Friendly', 'Holloway Friendly',
        ],
        // Investment Platforms
        'investment' => [
            'Hargreaves Lansdown', 'Vanguard', 'AJ Bell', 'Fidelity',
            'Interactive Investor', 'Charles Stanley', 'Bestinvest',
            'Nutmeg', 'Wealthify', 'Moneyfarm',
        ],
        // Mortgage Lenders
        'mortgage' => [
            'Nationwide', 'Halifax', 'Santander', 'Barclays', 'HSBC',
            'NatWest', 'Lloyds', 'TSB', 'Virgin Money', 'Yorkshire Building Society',
        ],
        // Savings/Banking
        'savings' => [
            'NS&I', 'Marcus', 'Aldermore', 'Atom Bank', 'Paragon',
            'Shawbrook', 'OakNorth', 'Sainsbury\'s Bank', 'Tesco Bank',
        ],
    ];

    /**
     * Detect document type from extracted data.
     */
    public function detect(array $extractedData): array
    {
        $type = $extractedData['document_type'] ?? Document::TYPE_UNKNOWN;
        $subtype = $extractedData['document_subtype'] ?? null;

        // Normalize the type
        $type = $this->normalizeType($type);

        // Calculate confidence based on extraction quality
        $confidence = $this->calculateConfidence($extractedData);

        return [
            'type' => $type,
            'subtype' => $subtype,
            'confidence' => $confidence,
        ];
    }

    /**
     * Get the target model class for a document.
     */
    public function getTargetModel(Document $document): ?string
    {
        $type = $document->document_type;
        $subtype = $document->detected_document_subtype;

        if (! isset(self::TYPE_MODEL_MAP[$type])) {
            return null;
        }

        // If subtype is specified and valid, use it
        if ($subtype && isset(self::TYPE_MODEL_MAP[$type][$subtype])) {
            return self::TYPE_MODEL_MAP[$type][$subtype];
        }

        // Return first model for type if subtype not matched
        $models = self::TYPE_MODEL_MAP[$type];

        return ! empty($models) ? array_values($models)[0] : null;
    }

    /**
     * Get all available mappers for a document type.
     */
    public function getAvailableSubtypes(string $documentType): array
    {
        return array_keys(self::TYPE_MODEL_MAP[$documentType] ?? []);
    }

    /**
     * Get the target model for a specific subtype.
     */
    public function getModelForSubtype(string $documentType, string $subtype): ?string
    {
        return self::TYPE_MODEL_MAP[$documentType][$subtype] ?? null;
    }

    /**
     * Check if a document type is valid.
     */
    public function isValidType(string $type): bool
    {
        return isset(self::TYPE_MODEL_MAP[$type]) || $type === Document::TYPE_UNKNOWN;
    }

    /**
     * Check if a subtype is valid for a given type.
     */
    public function isValidSubtype(string $type, string $subtype): bool
    {
        return isset(self::TYPE_MODEL_MAP[$type][$subtype]);
    }

    /**
     * Get known providers for a category.
     */
    public function getKnownProviders(string $category): array
    {
        return self::KNOWN_PROVIDERS[$category] ?? [];
    }

    /**
     * Identify provider category from provider name.
     */
    public function identifyProviderCategory(string $providerName): ?string
    {
        $providerName = strtolower($providerName);

        foreach (self::KNOWN_PROVIDERS as $category => $providers) {
            foreach ($providers as $provider) {
                if (stripos($providerName, strtolower($provider)) !== false) {
                    return $category;
                }
            }
        }

        return null;
    }

    /**
     * Normalize document type from AI extraction.
     */
    private function normalizeType(string $type): string
    {
        $type = strtolower(trim($type));

        // Map AI response types to our canonical types
        $mapping = [
            'dc_pension' => Document::TYPE_PENSION_STATEMENT,
            'db_pension' => Document::TYPE_PENSION_STATEMENT,
            'state_pension' => Document::TYPE_PENSION_STATEMENT,
            'pension' => Document::TYPE_PENSION_STATEMENT,
            'pension_statement' => Document::TYPE_PENSION_STATEMENT,

            'life_insurance' => Document::TYPE_INSURANCE_POLICY,
            'critical_illness' => Document::TYPE_INSURANCE_POLICY,
            'income_protection' => Document::TYPE_INSURANCE_POLICY,
            'insurance' => Document::TYPE_INSURANCE_POLICY,
            'insurance_policy' => Document::TYPE_INSURANCE_POLICY,

            'investment' => Document::TYPE_INVESTMENT_STATEMENT,
            'investment_statement' => Document::TYPE_INVESTMENT_STATEMENT,
            'isa' => Document::TYPE_INVESTMENT_STATEMENT,
            'gia' => Document::TYPE_INVESTMENT_STATEMENT,

            'mortgage' => Document::TYPE_MORTGAGE_STATEMENT,
            'mortgage_statement' => Document::TYPE_MORTGAGE_STATEMENT,

            'savings' => Document::TYPE_SAVINGS_STATEMENT,
            'savings_statement' => Document::TYPE_SAVINGS_STATEMENT,
            'bank_statement' => Document::TYPE_SAVINGS_STATEMENT,

            'property' => Document::TYPE_PROPERTY_DOCUMENT,
            'property_document' => Document::TYPE_PROPERTY_DOCUMENT,
        ];

        return $mapping[$type] ?? Document::TYPE_UNKNOWN;
    }

    /**
     * Calculate confidence based on extraction quality.
     */
    private function calculateConfidence(array $extractedData): float
    {
        $fields = $extractedData['fields'] ?? [];
        $confidences = $extractedData['confidence'] ?? [];

        if (empty($confidences)) {
            return 0.5; // Default if no confidence data
        }

        // Weight important fields more heavily
        $importantFields = [
            'scheme_name', 'provider', 'current_value', 'sum_assured',
            'current_balance', 'current_fund_value', 'policy_number',
            'account_number', 'member_number',
        ];

        $totalWeight = 0;
        $weightedSum = 0;

        foreach ($confidences as $field => $confidence) {
            $weight = in_array($field, $importantFields, true) ? 2 : 1;
            $weightedSum += $confidence * $weight;
            $totalWeight += $weight;
        }

        return $totalWeight > 0 ? $weightedSum / $totalWeight : 0.5;
    }

    /**
     * Get all supported document types.
     */
    public function getSupportedTypes(): array
    {
        return array_keys(self::TYPE_MODEL_MAP);
    }

    /**
     * Get type label for display.
     */
    public function getTypeLabel(string $type): string
    {
        return match ($type) {
            Document::TYPE_PENSION_STATEMENT => 'Pension Statement',
            Document::TYPE_INSURANCE_POLICY => 'Insurance Policy',
            Document::TYPE_INVESTMENT_STATEMENT => 'Investment Statement',
            Document::TYPE_MORTGAGE_STATEMENT => 'Mortgage Statement',
            Document::TYPE_SAVINGS_STATEMENT => 'Savings Statement',
            Document::TYPE_PROPERTY_DOCUMENT => 'Property Document',
            default => 'Unknown Document',
        };
    }

    /**
     * Get subtype label for display.
     */
    public function getSubtypeLabel(string $subtype): string
    {
        return match ($subtype) {
            'dc_pension' => 'DC Pension',
            'db_pension' => 'DB Pension',
            'state_pension' => 'State Pension',
            'life_insurance' => 'Life Insurance',
            'critical_illness' => 'Critical Illness',
            'income_protection' => 'Income Protection',
            'disability' => 'Disability Insurance',
            'sickness_illness' => 'Sickness & Illness',
            'investment_account' => 'Investment Account',
            'mortgage' => 'Mortgage',
            'savings_account' => 'Savings Account',
            'cash_account' => 'Cash Account',
            'property' => 'Property',
            default => ucwords(str_replace('_', ' ', $subtype)),
        };
    }
}
