# Excel Holdings Import — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Extend the existing document upload modal to accept Excel workbooks, extract holdings/accounts/properties/policies per sheet via AI, and let the user review and confirm everything in one screen.

**Architecture:** The `ExcelParserService` (already exists) converts each sheet to text. A new `extractFromExcel()` method in `AIExtractionService` makes one AI call per sheet to classify it (investment/pension/cash/property/protection/ignore) and extract structured data. A new `HoldingsImportService` handles account matching, holdings diffing, and bulk save. The frontend adds a "Sheet Review" step inside the existing `DocumentUploadModal`.

**Tech Stack:** Laravel 10, PhpSpreadsheet (already installed), xAI Grok API, Vue.js 3, Vuex

---

## File Map

### Backend — New Files
| File | Responsibility |
|------|---------------|
| `app/Services/Documents/ExcelExtractionService.php` | Orchestrates per-sheet AI extraction for Excel files |
| `app/Services/Documents/HoldingsImportService.php` | Account matching, holdings diff, bulk save |
| `app/Services/Documents/FieldMappers/PropertyMapper.php` | Maps extracted property fields to Property model |
| `app/Services/Documents/FieldMappers/ProtectionMapper.php` | Maps extracted protection fields to policy models |
| `app/Services/Documents/FieldMappers/SavingsAccountMapper.php` | Maps extracted savings fields to SavingsAccount model |
| `app/Services/Documents/FieldMappers/MortgageMapper.php` | Maps extracted mortgage fields to Mortgage model |

### Backend — Modified Files
| File | Change |
|------|--------|
| `app/Services/Documents/ExcelParserService.php` | New `parseToSheets()` returning per-sheet structured data |
| `app/Services/Documents/AIExtractionService.php` | New `extractSheet()` method with Excel-specific prompt |
| `app/Services/Documents/DocumentProcessor.php` | New `processExcel()` + `confirmExcel()` methods, register new mappers |
| `app/Http/Controllers/Api/DocumentController.php` | Excel-aware upload + confirm endpoints |
| `app/Http/Requests/Documents/UploadDocumentRequest.php` | Add xlsx/xls/csv MIME types |

### Frontend — New Files
| File | Responsibility |
|------|---------------|
| `resources/js/components/Shared/SheetReviewStep.vue` | Sheet mapping + holdings review (inside DocumentUploadModal) |
| `resources/js/components/Shared/HoldingsReviewTable.vue` | Holdings diff table with add/update/unchanged/not-in-import |

### Frontend — Modified Files
| File | Change |
|------|--------|
| `resources/js/components/Shared/DocumentUploadModal.vue` | Accept Excel, add sheet review step |
| `resources/js/components/Shared/UploadDropZone.vue` | Add Excel MIME types to accepted list |
| `resources/js/services/documentService.js` | New `confirmExcel()` API call |

---

## Task 1: ExcelParserService — Add parseToSheets()

**Files:**
- Modify: `app/Services/Documents/ExcelParserService.php`
- Test: `tests/Unit/Services/Documents/ExcelParserServiceTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Services\Documents\ExcelParserService;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

it('parses workbook into per-sheet structured data', function () {
    // Create a test workbook with 2 sheets
    $spreadsheet = new Spreadsheet();

    $sheet1 = $spreadsheet->getActiveSheet();
    $sheet1->setTitle('ISA');
    $sheet1->setCellValue('A1', 'Security Name');
    $sheet1->setCellValue('B1', 'Ticker');
    $sheet1->setCellValue('C1', 'Value');
    $sheet1->setCellValue('A2', 'Vanguard FTSE 100');
    $sheet1->setCellValue('B2', 'VUKE');
    $sheet1->setCellValue('C2', '15000');

    $sheet2 = $spreadsheet->createSheet();
    $sheet2->setTitle('SIPP');
    $sheet2->setCellValue('A1', 'Fund Name');
    $sheet2->setCellValue('B1', 'Units');
    $sheet2->setCellValue('A2', 'L&G Global Equity');
    $sheet2->setCellValue('B2', '500');

    $tempFile = tempnam(sys_get_temp_dir(), 'test_') . '.xlsx';
    (new Xlsx($spreadsheet))->save($tempFile);

    $service = new ExcelParserService();
    $sheets = $service->parseToSheets($tempFile);

    expect($sheets)->toHaveCount(2);
    expect($sheets[0]['name'])->toBe('ISA');
    expect($sheets[0]['row_count'])->toBe(2);
    expect($sheets[0]['content'])->toContain('Vanguard FTSE 100');
    expect($sheets[1]['name'])->toBe('SIPP');
    expect($sheets[1]['content'])->toContain('L&G Global Equity');

    unlink($tempFile);
});

it('skips empty sheets', function () {
    $spreadsheet = new Spreadsheet();
    $spreadsheet->getActiveSheet()->setTitle('Data');
    $spreadsheet->getActiveSheet()->setCellValue('A1', 'Name');
    $spreadsheet->getActiveSheet()->setCellValue('A2', 'Test');
    $spreadsheet->createSheet()->setTitle('Empty');

    $tempFile = tempnam(sys_get_temp_dir(), 'test_') . '.xlsx';
    (new Xlsx($spreadsheet))->save($tempFile);

    $service = new ExcelParserService();
    $sheets = $service->parseToSheets($tempFile);

    expect($sheets)->toHaveCount(1);
    expect($sheets[0]['name'])->toBe('Data');

    unlink($tempFile);
});

it('caps at 10 sheets', function () {
    $spreadsheet = new Spreadsheet();
    for ($i = 0; $i < 12; $i++) {
        $sheet = $i === 0 ? $spreadsheet->getActiveSheet() : $spreadsheet->createSheet();
        $sheet->setTitle("Sheet{$i}");
        $sheet->setCellValue('A1', 'Data');
        $sheet->setCellValue('A2', "Row{$i}");
    }

    $tempFile = tempnam(sys_get_temp_dir(), 'test_') . '.xlsx';
    (new Xlsx($spreadsheet))->save($tempFile);

    $service = new ExcelParserService();
    $sheets = $service->parseToSheets($tempFile);

    expect($sheets)->toHaveCount(10);

    unlink($tempFile);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Unit/Services/Documents/ExcelParserServiceTest.php -v`
Expected: FAIL — `parseToSheets` method not found

- [ ] **Step 3: Implement parseToSheets()**

Add to `app/Services/Documents/ExcelParserService.php`:

```php
private const MAX_SHEETS = 10;

/**
 * Parse an Excel file into per-sheet structured data.
 *
 * @return array<int, array{name: string, content: string, row_count: int, headers: array}>
 */
public function parseToSheets(string $filePath): array
{
    try {
        $spreadsheet = IOFactory::load($filePath);
    } catch (\Exception $e) {
        throw new RuntimeException('Failed to parse Excel file: ' . $e->getMessage());
    }

    $sheets = [];
    $sheetCount = min($spreadsheet->getSheetCount(), self::MAX_SHEETS);

    for ($i = 0; $i < $sheetCount; $i++) {
        $sheet = $spreadsheet->getSheet($i);
        $sheetName = $sheet->getTitle();

        $highestRow = min($sheet->getHighestRow(), self::MAX_ROWS);
        $highestColumn = $sheet->getHighestColumn();
        $highestColumnIndex = min(
            \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn),
            self::MAX_COLS
        );

        // Skip sheets with no data rows (only header or empty)
        if ($highestRow < 2) {
            // Check if even the first row has data
            $hasAnyData = false;
            for ($col = 1; $col <= $highestColumnIndex; $col++) {
                if ($sheet->getCellByColumnAndRow($col, 1)->getFormattedValue() !== '') {
                    $hasAnyData = true;
                    break;
                }
            }
            if (!$hasAnyData) {
                continue;
            }
        }

        // Get headers
        $headers = [];
        for ($col = 1; $col <= $highestColumnIndex; $col++) {
            $cellValue = trim((string) $sheet->getCellByColumnAndRow($col, 1)->getFormattedValue());
            if ($cellValue !== '') {
                $headers[$col] = $cellValue;
            }
        }

        // Build text content (same format as existing convertToText but per-sheet)
        $lines = ["=== Sheet: {$sheetName} ==="];
        if (!empty($headers)) {
            $lines[] = 'Headers: ' . implode(' | ', $headers);
        }

        $dataRowCount = 0;
        for ($row = 2; $row <= $highestRow; $row++) {
            $rowData = [];
            $hasData = false;

            for ($col = 1; $col <= $highestColumnIndex; $col++) {
                $value = $sheet->getCellByColumnAndRow($col, $row)->getFormattedValue();
                if ($value !== null && $value !== '') {
                    $hasData = true;
                    if (!empty($headers[$col])) {
                        $rowData[] = "{$headers[$col]}: {$value}";
                    } else {
                        $rowData[] = (string) $value;
                    }
                }
            }

            if ($hasData) {
                $dataRowCount++;
                $lines[] = "Row {$row}: " . implode(', ', $rowData);
            }
        }

        // Skip sheets with no data rows
        if ($dataRowCount === 0 && empty($headers)) {
            continue;
        }

        $sheets[] = [
            'name' => $sheetName,
            'content' => implode("\n", $lines),
            'row_count' => $dataRowCount + 1, // include header
            'headers' => array_values($headers),
        ];
    }

    return $sheets;
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `./vendor/bin/pest tests/Unit/Services/Documents/ExcelParserServiceTest.php -v`
Expected: All 3 tests PASS

- [ ] **Step 5: Commit**

```bash
git add app/Services/Documents/ExcelParserService.php tests/Unit/Services/Documents/ExcelParserServiceTest.php
git commit -m "feat: add parseToSheets() to ExcelParserService for per-sheet extraction"
```

---

## Task 2: AI Extraction — Excel Sheet Prompt + extractSheet()

**Files:**
- Modify: `app/Services/Documents/AIExtractionService.php`
- Test: `tests/Unit/Services/Documents/AIExtractionServiceTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Services\Documents\AIExtractionService;

it('builds correct prompt for excel sheet extraction', function () {
    $service = app(AIExtractionService::class);

    // Use reflection to test the private method
    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('getExcelSheetPrompt');
    $method->setAccessible(true);

    $prompt = $method->invoke($service);

    expect($prompt)->toContain('investment_holdings');
    expect($prompt)->toContain('pension_holdings');
    expect($prompt)->toContain('cash_savings');
    expect($prompt)->toContain('property');
    expect($prompt)->toContain('protection');
    expect($prompt)->toContain('ignore');
    expect($prompt)->toContain('holdings');
    expect($prompt)->toContain('category');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Unit/Services/Documents/AIExtractionServiceTest.php -v`
Expected: FAIL — `getExcelSheetPrompt` method not found

- [ ] **Step 3: Add getExcelSheetPrompt() and extractSheet() to AIExtractionService**

Add these methods to `app/Services/Documents/AIExtractionService.php`:

```php
/**
 * Extract data from a single Excel sheet's text content.
 *
 * @return array{category: string, category_confidence: float, account: array, holdings: array, confidence: array, warnings: array}
 */
public function extractSheet(string $sheetName, string $sheetContent, Document $document): array
{
    $prompt = $this->getBasePrompt() . "\n\n" . $this->getExcelSheetPrompt();

    $userContent = "Sheet name: \"{$sheetName}\"\n\n{$sheetContent}";

    $provider = \Illuminate\Support\Facades\Cache::get('ai_provider', config('services.ai_provider', 'anthropic'));

    if ($provider === 'xai') {
        $response = $this->callXaiTextAPI($userContent, $prompt);
    } else {
        $response = $this->callClaudeTextAPI($userContent, $prompt);
    }

    $parsed = $this->parseResponse($response);

    // Ensure required keys exist
    return array_merge([
        'sheet_name' => $sheetName,
        'category' => 'ignore',
        'category_confidence' => 0.0,
        'account' => [],
        'holdings' => [],
        'confidence' => [],
        'warnings' => [],
    ], $parsed['fields'] ?? $parsed);
}

/**
 * Excel sheet classification and extraction prompt.
 */
private function getExcelSheetPrompt(): string
{
    return <<<'PROMPT'
EXCEL SHEET EXTRACTION — Classify this sheet and extract financial data.

First, determine what category this sheet belongs to based on the sheet name and content:

CATEGORIES:
- investment_holdings: ISA, GIA, stocks, equities, portfolio, investments (has: ticker, units, price, ISIN columns)
- pension_holdings: SIPP, pension fund, retirement pot, DC pension (has: fund names, units, value)
- cash_savings: Cash, current account, savings, easy access, deposits (has: balance, interest rate, sort code)
- property: Property, properties, real estate, house, flat, buy-to-let (has: address, value, rental income)
- protection: Insurance, policies, life cover, protection, critical illness (has: sum assured, premium, policy number)
- ignore: Summary, notes, cover page, disclaimer, T&Cs, fees schedule (no actionable financial data)

Return JSON in this exact format:
{
  "category": "investment_holdings|pension_holdings|cash_savings|property|protection|ignore",
  "category_confidence": 0.95,
  "account": {
    "provider": "Provider/platform name",
    "account_type": "isa|gia|sipp|savings|current|etc",
    "account_number": "Reference number if shown",
    "total_value": 95000.00
  },
  "holdings": [
    {
      "security_name": "Fund or stock name",
      "ticker": "TICKER",
      "isin": "ISIN code",
      "asset_type": "uk_equity|us_equity|international_equity|fund|etf|bond|cash|alternative|property",
      "quantity": 150.5,
      "current_price": 85.20,
      "current_value": 12822.60,
      "purchase_price": null,
      "cost_basis": null
    }
  ],
  "confidence": {
    "provider": 0.95,
    "account_type": 0.90,
    "holdings": 0.85
  },
  "warnings": []
}

CATEGORY-SPECIFIC RULES:

For cash_savings: "holdings" should be empty. "account" should include:
  balance, interest_rate (as decimal), access_type (immediate|notice|fixed), is_isa (true/false)

For property: "holdings" should be empty. Return one object per property row in "properties" array:
  address, current_value, property_type (main_residence|secondary_residence|buy_to_let), ownership_type (individual|joint), rental_income_monthly, mortgage_outstanding

For protection: "holdings" should be empty. Return one object per policy row in "policies" array:
  provider, policy_type (term|whole_of_life|critical_illness|income_protection), sum_assured, premium_amount, premium_frequency (monthly|annually), start_date, term_years

For ignore: Return minimal response with just category and category_confidence.

IMPORTANT: Use the sheet name as a strong signal for category, but let the actual content override if they conflict.
PROMPT;
}
```

Also add these helper methods if not already present (for text-only API calls without vision):

```php
/**
 * Call xAI API with text content (no vision).
 */
private function callXaiTextAPI(string $textContent, string $systemPrompt): string
{
    $apiKey = config('services.xai.api_key');
    $model = config('services.xai.vision_model', 'grok-4-1-fast-non-reasoning');

    $response = Http::withHeaders([
        'Authorization' => "Bearer {$apiKey}",
        'Content-Type' => 'application/json',
    ])->timeout(self::TIMEOUT_SECONDS)->post(self::XAI_API_URL, [
        'model' => $model,
        'max_tokens' => self::MAX_TOKENS,
        'messages' => [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $textContent],
        ],
    ]);

    if (!$response->successful()) {
        throw new RuntimeException('xAI API request failed: ' . $response->status());
    }

    return $response->json('choices.0.message.content', '');
}

/**
 * Call Claude API with text content (no vision).
 */
private function callClaudeTextAPI(string $textContent, string $systemPrompt): string
{
    $apiKey = config('services.anthropic.api_key');

    $response = Http::withHeaders([
        'x-api-key' => $apiKey,
        'anthropic-version' => '2023-06-01',
        'content-type' => 'application/json',
    ])->timeout(self::TIMEOUT_SECONDS)->post(self::ANTHROPIC_API_URL, [
        'model' => self::ANTHROPIC_MODEL,
        'max_tokens' => self::MAX_TOKENS,
        'system' => $systemPrompt,
        'messages' => [
            ['role' => 'user', 'content' => $textContent],
        ],
    ]);

    if (!$response->successful()) {
        throw new RuntimeException('Anthropic API request failed: ' . $response->status());
    }

    return $response->json('content.0.text', '');
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Unit/Services/Documents/AIExtractionServiceTest.php -v`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Services/Documents/AIExtractionService.php tests/Unit/Services/Documents/AIExtractionServiceTest.php
git commit -m "feat: add extractSheet() and Excel prompt to AIExtractionService"
```

---

## Task 3: New Field Mappers — Property, Protection, Savings, Mortgage

**Files:**
- Create: `app/Services/Documents/FieldMappers/PropertyMapper.php`
- Create: `app/Services/Documents/FieldMappers/ProtectionMapper.php`
- Create: `app/Services/Documents/FieldMappers/SavingsAccountMapper.php`
- Create: `app/Services/Documents/FieldMappers/MortgageMapper.php`
- Modify: `app/Services/Documents/DocumentProcessor.php` (register mappers)
- Test: `tests/Unit/Services/Documents/FieldMappers/PropertyMapperTest.php`
- Test: `tests/Unit/Services/Documents/FieldMappers/ProtectionMapperTest.php`

- [ ] **Step 1: Write failing tests**

`tests/Unit/Services/Documents/FieldMappers/PropertyMapperTest.php`:
```php
<?php

declare(strict_types=1);

use App\Services\Documents\FieldMappers\PropertyMapper;

it('maps extracted property fields to Property model fields', function () {
    $mapper = new PropertyMapper();

    $extracted = [
        'address' => '14 Oakwood Drive, Manchester',
        'current_value' => '£850,000',
        'property_type' => 'Main Residence',
        'ownership_type' => 'Joint',
        'rental_income_monthly' => null,
        'mortgage_outstanding' => '280000',
    ];

    $mapped = $mapper->map($extracted);

    expect($mapped['address'])->toBe('14 Oakwood Drive, Manchester');
    expect($mapped['current_value'])->toBe(850000.0);
    expect($mapped['property_type'])->toBe('main_residence');
    expect($mapped['ownership_type'])->toBe('joint');
});

it('validates required property fields', function () {
    $mapper = new PropertyMapper();
    $errors = $mapper->validate(['ownership_type' => 'individual']);
    expect($errors)->toHaveKey('address');
    expect($errors)->toHaveKey('current_value');
});
```

`tests/Unit/Services/Documents/FieldMappers/ProtectionMapperTest.php`:
```php
<?php

declare(strict_types=1);

use App\Services\Documents\FieldMappers\ProtectionMapper;

it('maps extracted protection fields to policy model fields', function () {
    $mapper = new ProtectionMapper();

    $extracted = [
        'provider' => 'Aviva',
        'policy_type' => 'Term Life',
        'sum_assured' => '£500,000',
        'premium_amount' => '45.50',
        'premium_frequency' => 'monthly',
        'policy_start_date' => '01/06/2020',
        'term_years' => '25',
    ];

    $mapped = $mapper->map($extracted);

    expect($mapped['provider'])->toBe('Aviva');
    expect($mapped['sum_assured'])->toBe(500000.0);
    expect($mapped['premium_amount'])->toBe(45.50);
    expect($mapped['policy_start_date'])->toBe('2020-06-01');
    expect($mapped['policy_term_years'])->toBe(25);
});

it('detects policy model class from type', function () {
    $mapper = new ProtectionMapper();

    expect($mapper->getModelClassForType('term'))->toBe(\App\Models\LifeInsurancePolicy::class);
    expect($mapper->getModelClassForType('critical_illness'))->toBe(\App\Models\CriticalIllnessPolicy::class);
    expect($mapper->getModelClassForType('income_protection'))->toBe(\App\Models\IncomeProtectionPolicy::class);
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `./vendor/bin/pest tests/Unit/Services/Documents/FieldMappers/ -v`
Expected: FAIL — classes not found

- [ ] **Step 3: Create PropertyMapper**

`app/Services/Documents/FieldMappers/PropertyMapper.php`:
```php
<?php

declare(strict_types=1);

namespace App\Services\Documents\FieldMappers;

class PropertyMapper extends AbstractFieldMapper
{
    protected array $fieldMappings = [
        'address' => 'address',
        'current_value' => 'current_value',
        'property_type' => 'property_type',
        'ownership_type' => 'ownership_type',
        'rental_income_monthly' => 'rental_income_monthly',
        'mortgage_outstanding' => 'mortgage_outstanding',
        'purchase_price' => 'purchase_price',
        'purchase_date' => 'purchase_date',
    ];

    public function __construct()
    {
        $this->transformations = [
            'address' => fn ($v) => $this->normalizeString($v),
            'current_value' => fn ($v) => $this->parseDecimal($v),
            'property_type' => fn ($v) => $this->parseEnum($v, [
                'main_residence', 'secondary_residence', 'buy_to_let',
            ], 'main_residence'),
            'ownership_type' => fn ($v) => $this->parseEnum($v, [
                'individual', 'joint', 'tenants_in_common', 'trust',
            ], 'individual'),
            'rental_income_monthly' => fn ($v) => $this->parseDecimal($v),
            'mortgage_outstanding' => fn ($v) => $this->parseDecimal($v),
            'purchase_price' => fn ($v) => $this->parseDecimal($v),
            'purchase_date' => fn ($v) => $this->parseDate($v),
        ];
    }

    public function getModelClass(): string
    {
        return \App\Models\Property::class;
    }

    public function getSubtype(): string
    {
        return 'property';
    }

    public function getRequiredFields(): array
    {
        return ['address', 'current_value'];
    }

    public function getOptionalFields(): array
    {
        return ['property_type', 'ownership_type', 'rental_income_monthly', 'mortgage_outstanding', 'purchase_price', 'purchase_date'];
    }
}
```

- [ ] **Step 4: Create ProtectionMapper**

`app/Services/Documents/FieldMappers/ProtectionMapper.php`:
```php
<?php

declare(strict_types=1);

namespace App\Services\Documents\FieldMappers;

class ProtectionMapper extends AbstractFieldMapper
{
    protected array $fieldMappings = [
        'provider' => 'provider',
        'policy_number' => 'policy_number',
        'policy_type' => 'policy_type',
        'sum_assured' => 'sum_assured',
        'premium_amount' => 'premium_amount',
        'premium_frequency' => 'premium_frequency',
        'policy_start_date' => 'policy_start_date',
        'policy_end_date' => 'policy_end_date',
        'term_years' => 'policy_term_years',
        'in_trust' => 'in_trust',
    ];

    public function __construct()
    {
        $this->transformations = [
            'provider' => fn ($v) => $this->normalizeString($v),
            'policy_number' => fn ($v) => $this->normalizeString($v),
            'policy_type' => fn ($v) => $this->normalizePolicyType($v),
            'sum_assured' => fn ($v) => $this->parseDecimal($v),
            'premium_amount' => fn ($v) => $this->parseDecimal($v),
            'premium_frequency' => fn ($v) => $this->parseEnum($v, ['monthly', 'quarterly', 'annually'], 'monthly'),
            'policy_start_date' => fn ($v) => $this->parseDate($v),
            'policy_end_date' => fn ($v) => $this->parseDate($v),
            'term_years' => fn ($v) => $this->parseInt($v),
            'in_trust' => fn ($v) => $this->parseBool($v),
        ];
    }

    public function getModelClass(): string
    {
        return \App\Models\LifeInsurancePolicy::class;
    }

    /**
     * Get the correct model class based on policy type.
     */
    public function getModelClassForType(string $type): string
    {
        return match ($type) {
            'critical_illness', 'standalone', 'accelerated', 'additional' => \App\Models\CriticalIllnessPolicy::class,
            'income_protection' => \App\Models\IncomeProtectionPolicy::class,
            default => \App\Models\LifeInsurancePolicy::class,
        };
    }

    public function getSubtype(): string
    {
        return 'protection';
    }

    public function getRequiredFields(): array
    {
        return ['provider', 'sum_assured'];
    }

    public function getOptionalFields(): array
    {
        return ['policy_number', 'policy_type', 'premium_amount', 'premium_frequency', 'policy_start_date', 'policy_end_date', 'term_years', 'in_trust'];
    }

    private function normalizePolicyType(?string $type): ?string
    {
        if (!$type) {
            return 'term';
        }

        $type = strtolower(trim($type));

        return match (true) {
            str_contains($type, 'whole') => 'whole_of_life',
            str_contains($type, 'decreasing') => 'decreasing_term',
            str_contains($type, 'level') => 'level_term',
            str_contains($type, 'family') || str_contains($type, 'income benefit') => 'family_income_benefit',
            str_contains($type, 'critical') || str_contains($type, 'ci') => 'critical_illness',
            str_contains($type, 'income protection') || str_contains($type, 'ip') => 'income_protection',
            str_contains($type, 'term') => 'term',
            default => 'term',
        };
    }
}
```

- [ ] **Step 5: Create SavingsAccountMapper**

`app/Services/Documents/FieldMappers/SavingsAccountMapper.php`:
```php
<?php

declare(strict_types=1);

namespace App\Services\Documents\FieldMappers;

class SavingsAccountMapper extends AbstractFieldMapper
{
    protected array $fieldMappings = [
        'institution' => 'institution',
        'account_number' => 'account_number',
        'account_type' => 'account_type',
        'current_balance' => 'current_balance',
        'interest_rate' => 'interest_rate',
        'access_type' => 'access_type',
        'notice_period_days' => 'notice_period_days',
        'maturity_date' => 'maturity_date',
        'is_isa' => 'is_isa',
    ];

    public function __construct()
    {
        $this->transformations = [
            'institution' => fn ($v) => $this->normalizeString($v),
            'account_number' => fn ($v) => $this->normalizeString($v),
            'account_type' => fn ($v) => $this->normalizeString($v),
            'current_balance' => fn ($v) => $this->parseDecimal($v),
            'interest_rate' => fn ($v) => $this->parsePercentage($v),
            'access_type' => fn ($v) => $this->parseEnum($v, ['immediate', 'notice', 'fixed'], 'immediate'),
            'notice_period_days' => fn ($v) => $this->parseInt($v),
            'maturity_date' => fn ($v) => $this->parseDate($v),
            'is_isa' => fn ($v) => $this->parseBool($v),
        ];
    }

    public function getModelClass(): string
    {
        return \App\Models\SavingsAccount::class;
    }

    public function getSubtype(): string
    {
        return 'savings_account';
    }

    public function getRequiredFields(): array
    {
        return ['institution', 'current_balance'];
    }

    public function getOptionalFields(): array
    {
        return ['account_number', 'account_type', 'interest_rate', 'access_type', 'notice_period_days', 'maturity_date', 'is_isa'];
    }
}
```

- [ ] **Step 6: Create MortgageMapper**

`app/Services/Documents/FieldMappers/MortgageMapper.php`:
```php
<?php

declare(strict_types=1);

namespace App\Services\Documents\FieldMappers;

class MortgageMapper extends AbstractFieldMapper
{
    protected array $fieldMappings = [
        'lender_name' => 'lender_name',
        'mortgage_account_number' => 'mortgage_account_number',
        'mortgage_type' => 'mortgage_type',
        'original_loan_amount' => 'original_loan_amount',
        'outstanding_balance' => 'outstanding_balance',
        'interest_rate' => 'interest_rate',
        'rate_type' => 'rate_type',
        'monthly_payment' => 'monthly_payment',
        'start_date' => 'start_date',
        'maturity_date' => 'end_date',
        'remaining_term_months' => 'remaining_term_months',
    ];

    public function __construct()
    {
        $this->transformations = [
            'lender_name' => fn ($v) => $this->normalizeString($v),
            'mortgage_account_number' => fn ($v) => $this->normalizeString($v),
            'mortgage_type' => fn ($v) => $this->parseEnum($v, ['repayment', 'interest_only', 'mixed'], 'repayment'),
            'original_loan_amount' => fn ($v) => $this->parseDecimal($v),
            'outstanding_balance' => fn ($v) => $this->parseDecimal($v),
            'interest_rate' => fn ($v) => $this->parsePercentage($v),
            'rate_type' => fn ($v) => $this->parseEnum($v, ['fixed', 'variable', 'tracker', 'discount', 'mixed'], 'variable'),
            'monthly_payment' => fn ($v) => $this->parseDecimal($v),
            'start_date' => fn ($v) => $this->parseDate($v),
            'maturity_date' => fn ($v) => $this->parseDate($v),
            'remaining_term_months' => fn ($v) => $this->parseInt($v),
        ];
    }

    public function getModelClass(): string
    {
        return \App\Models\Mortgage::class;
    }

    public function getSubtype(): string
    {
        return 'mortgage';
    }

    public function getRequiredFields(): array
    {
        return ['lender_name', 'outstanding_balance'];
    }

    public function getOptionalFields(): array
    {
        return ['mortgage_account_number', 'mortgage_type', 'original_loan_amount', 'interest_rate', 'rate_type', 'monthly_payment', 'start_date', 'maturity_date', 'remaining_term_months'];
    }
}
```

- [ ] **Step 7: Register new mappers in DocumentProcessor**

In `app/Services/Documents/DocumentProcessor.php`, update `registerMappers()`:

```php
private function registerMappers(): void
{
    $this->mappers = [
        \App\Models\DCPension::class => new DCPensionMapper,
        \App\Models\DBPension::class => new DBPensionMapper,
        \App\Models\LifeInsurancePolicy::class => new LifeInsuranceMapper,
        \App\Models\Investment\InvestmentAccount::class => new InvestmentAccountMapper,
        \App\Models\Property::class => new FieldMappers\PropertyMapper,
        \App\Models\SavingsAccount::class => new FieldMappers\SavingsAccountMapper,
        \App\Models\Mortgage::class => new FieldMappers\MortgageMapper,
    ];
}
```

Add use statements at the top of `DocumentProcessor.php`:
```php
use App\Services\Documents\FieldMappers\PropertyMapper;
use App\Services\Documents\FieldMappers\ProtectionMapper;
use App\Services\Documents\FieldMappers\SavingsAccountMapper;
use App\Services\Documents\FieldMappers\MortgageMapper;
```

- [ ] **Step 8: Run all mapper tests**

Run: `./vendor/bin/pest tests/Unit/Services/Documents/FieldMappers/ -v`
Expected: All PASS

- [ ] **Step 9: Commit**

```bash
git add app/Services/Documents/FieldMappers/PropertyMapper.php \
       app/Services/Documents/FieldMappers/ProtectionMapper.php \
       app/Services/Documents/FieldMappers/SavingsAccountMapper.php \
       app/Services/Documents/FieldMappers/MortgageMapper.php \
       app/Services/Documents/DocumentProcessor.php \
       tests/Unit/Services/Documents/FieldMappers/
git commit -m "feat: add Property, Protection, Savings, Mortgage field mappers"
```

---

## Task 4: HoldingsImportService — Account Matching + Holdings Diff

**Files:**
- Create: `app/Services/Documents/HoldingsImportService.php`
- Test: `tests/Unit/Services/Documents/HoldingsImportServiceTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Models\Investment\InvestmentAccount;
use App\Models\Investment\Holding;
use App\Models\User;
use App\Services\Documents\HoldingsImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\TaxConfigurationSeeder::class);
});

it('matches sheet to existing account by type and provider', function () {
    $user = User::factory()->create();
    $account = InvestmentAccount::factory()->create([
        'user_id' => $user->id,
        'account_type' => 'isa',
        'provider' => 'Hargreaves Lansdown',
    ]);

    $service = new HoldingsImportService();
    $match = $service->findMatchingAccount($user, 'investment_holdings', [
        'account_type' => 'isa',
        'provider' => 'Hargreaves Lansdown',
    ]);

    expect($match)->not->toBeNull();
    expect($match->id)->toBe($account->id);
});

it('returns null when no matching account found', function () {
    $user = User::factory()->create();

    $service = new HoldingsImportService();
    $match = $service->findMatchingAccount($user, 'investment_holdings', [
        'account_type' => 'isa',
        'provider' => 'Vanguard',
    ]);

    expect($match)->toBeNull();
});

it('diffs imported holdings against existing', function () {
    $user = User::factory()->create();
    $account = InvestmentAccount::factory()->create(['user_id' => $user->id]);

    // Existing holding
    Holding::create([
        'holdable_id' => $account->id,
        'holdable_type' => InvestmentAccount::class,
        'security_name' => 'Vanguard FTSE 100',
        'ticker' => 'VUKE',
        'isin' => 'IE00B810Q511',
        'quantity' => 100,
        'current_value' => 5000,
    ]);

    $service = new HoldingsImportService();
    $diff = $service->diffHoldings($account, [
        ['security_name' => 'Vanguard FTSE 100', 'isin' => 'IE00B810Q511', 'quantity' => 150, 'current_value' => 7500],
        ['security_name' => 'iShares Core MSCI World', 'ticker' => 'IWDA', 'quantity' => 200, 'current_value' => 12000],
    ]);

    // VUKE matched by ISIN, quantity changed → 'update'
    expect($diff[0]['status'])->toBe('update');
    expect($diff[0]['security_name'])->toBe('Vanguard FTSE 100');

    // IWDA not found → 'add'
    expect($diff[1]['status'])->toBe('add');
    expect($diff[1]['security_name'])->toBe('iShares Core MSCI World');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Unit/Services/Documents/HoldingsImportServiceTest.php -v`
Expected: FAIL — class not found

- [ ] **Step 3: Implement HoldingsImportService**

`app/Services/Documents/HoldingsImportService.php`:
```php
<?php

declare(strict_types=1);

namespace App\Services\Documents;

use App\Models\DCPension;
use App\Models\Investment\Holding;
use App\Models\Investment\InvestmentAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class HoldingsImportService
{
    /**
     * Find an existing account that matches the extracted sheet data.
     */
    public function findMatchingAccount(User $user, string $category, array $accountData): ?Model
    {
        $type = $accountData['account_type'] ?? null;
        $provider = $accountData['provider'] ?? null;

        return match ($category) {
            'investment_holdings' => $this->matchInvestmentAccount($user, $type, $provider),
            'pension_holdings' => $this->matchPension($user, $provider),
            default => null,
        };
    }

    /**
     * Diff imported holdings against existing holdings on an account.
     *
     * @return array Each item has 'status' (add|update|unchanged|not_in_import) + holding data
     */
    public function diffHoldings(Model $account, array $importedHoldings): array
    {
        $existing = $account->holdings()->get();
        $result = [];
        $matchedExistingIds = [];

        foreach ($importedHoldings as $imported) {
            $match = $this->findMatchingHolding($existing, $imported);

            if ($match) {
                $matchedExistingIds[] = $match->id;
                $hasChanges = $this->holdingHasChanges($match, $imported);

                $result[] = array_merge($imported, [
                    'status' => $hasChanges ? 'update' : 'unchanged',
                    'existing_id' => $match->id,
                    'existing_quantity' => $match->quantity,
                    'existing_value' => $match->current_value,
                ]);
            } else {
                $result[] = array_merge($imported, [
                    'status' => 'add',
                    'existing_id' => null,
                ]);
            }
        }

        // Holdings in Fynla but not in import
        foreach ($existing as $existingHolding) {
            if (!in_array($existingHolding->id, $matchedExistingIds)) {
                $result[] = [
                    'status' => 'not_in_import',
                    'existing_id' => $existingHolding->id,
                    'security_name' => $existingHolding->security_name,
                    'ticker' => $existingHolding->ticker,
                    'isin' => $existingHolding->isin,
                    'quantity' => $existingHolding->quantity,
                    'current_value' => $existingHolding->current_value,
                ];
            }
        }

        return $result;
    }

    /**
     * Apply confirmed holdings import to an account.
     */
    public function applyHoldings(Model $account, array $confirmedHoldings): array
    {
        $created = 0;
        $updated = 0;
        $removed = 0;

        DB::transaction(function () use ($account, $confirmedHoldings, &$created, &$updated, &$removed) {
            foreach ($confirmedHoldings as $holding) {
                $status = $holding['status'] ?? 'add';

                if ($status === 'add') {
                    Holding::create([
                        'holdable_id' => $account->id,
                        'holdable_type' => get_class($account),
                        'security_name' => $holding['security_name'] ?? null,
                        'ticker' => $holding['ticker'] ?? null,
                        'isin' => $holding['isin'] ?? null,
                        'asset_type' => $holding['asset_type'] ?? 'fund',
                        'quantity' => $holding['quantity'] ?? null,
                        'current_price' => $holding['current_price'] ?? null,
                        'current_value' => $holding['current_value'] ?? null,
                        'purchase_price' => $holding['purchase_price'] ?? null,
                        'cost_basis' => $holding['cost_basis'] ?? null,
                    ]);
                    $created++;
                } elseif ($status === 'update' && isset($holding['existing_id'])) {
                    Holding::where('id', $holding['existing_id'])->update([
                        'quantity' => $holding['quantity'] ?? null,
                        'current_price' => $holding['current_price'] ?? null,
                        'current_value' => $holding['current_value'] ?? null,
                    ]);
                    $updated++;
                } elseif ($status === 'remove' && isset($holding['existing_id'])) {
                    Holding::where('id', $holding['existing_id'])->delete();
                    $removed++;
                }
                // 'unchanged' and 'not_in_import' (without remove) — skip
            }
        });

        return compact('created', 'updated', 'removed');
    }

    private function matchInvestmentAccount(User $user, ?string $type, ?string $provider): ?InvestmentAccount
    {
        $query = InvestmentAccount::where('user_id', $user->id);

        if ($type) {
            $query->where('account_type', $type);
        }
        if ($provider) {
            $query->where('provider', 'LIKE', "%{$provider}%");
        }

        return $query->first();
    }

    private function matchPension(User $user, ?string $provider): ?DCPension
    {
        $query = DCPension::where('user_id', $user->id);

        if ($provider) {
            $query->where('provider', 'LIKE', "%{$provider}%");
        }

        return $query->first();
    }

    private function findMatchingHolding($existingHoldings, array $imported): ?Holding
    {
        // Match by ISIN first (most reliable)
        if (!empty($imported['isin'])) {
            $match = $existingHoldings->firstWhere('isin', $imported['isin']);
            if ($match) {
                return $match;
            }
        }

        // Match by ticker
        if (!empty($imported['ticker'])) {
            $match = $existingHoldings->first(function ($h) use ($imported) {
                return $h->ticker && strtoupper($h->ticker) === strtoupper($imported['ticker']);
            });
            if ($match) {
                return $match;
            }
        }

        // Match by security name (fuzzy)
        if (!empty($imported['security_name'])) {
            $match = $existingHoldings->first(function ($h) use ($imported) {
                return $h->security_name &&
                    str_contains(
                        strtolower($h->security_name),
                        strtolower(substr($imported['security_name'], 0, 15))
                    );
            });
            if ($match) {
                return $match;
            }
        }

        return null;
    }

    private function holdingHasChanges(Holding $existing, array $imported): bool
    {
        if (isset($imported['quantity']) && (float) $imported['quantity'] !== (float) $existing->quantity) {
            return true;
        }
        if (isset($imported['current_value']) && abs((float) $imported['current_value'] - (float) $existing->current_value) > 0.01) {
            return true;
        }

        return false;
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `./vendor/bin/pest tests/Unit/Services/Documents/HoldingsImportServiceTest.php -v`
Expected: All PASS

- [ ] **Step 5: Commit**

```bash
git add app/Services/Documents/HoldingsImportService.php tests/Unit/Services/Documents/HoldingsImportServiceTest.php
git commit -m "feat: add HoldingsImportService with account matching and holdings diff"
```

---

## Task 5: DocumentProcessor + Controller — Excel Upload Flow

**Files:**
- Modify: `app/Services/Documents/DocumentProcessor.php`
- Modify: `app/Http/Controllers/Api/DocumentController.php`
- Modify: `app/Http/Requests/Documents/UploadDocumentRequest.php`

- [ ] **Step 1: Update UploadDocumentRequest to accept Excel**

In `app/Http/Requests/Documents/UploadDocumentRequest.php`, change the mimes rule:

```php
'document' => 'required|file|mimes:pdf,jpeg,png,webp,xlsx,xls,csv|max:20480',
```

- [ ] **Step 2: Add processExcel() to DocumentProcessor**

Add to `app/Services/Documents/DocumentProcessor.php`:

```php
use App\Services\Documents\FieldMappers\ProtectionMapper;

/**
 * Process an Excel workbook: parse sheets, extract per-sheet, return for review.
 */
public function processExcel(
    \Illuminate\Http\UploadedFile $file,
    User $user,
): array {
    return DB::transaction(function () use ($file, $user) {
        // 1. Upload document
        $document = $this->uploadService->upload($file, $user, 'holdings_import');
        $document->update(['status' => Document::STATUS_PROCESSING]);

        // 2. Parse Excel into sheets
        $excelParser = app(ExcelParserService::class);
        $filePath = storage_path('app/' . $document->path);
        $sheets = $excelParser->parseToSheets($filePath);

        if (empty($sheets)) {
            $document->update(['status' => Document::STATUS_FAILED, 'error_message' => 'No data sheets found']);
            throw new RuntimeException('No financial data found in this workbook.');
        }

        // 3. Extract each sheet via AI
        $sheetResults = [];
        foreach ($sheets as $sheet) {
            try {
                $extracted = $this->extractionService->extractSheet(
                    $sheet['name'],
                    $sheet['content'],
                    $document,
                );

                // Save extraction record per sheet
                $extraction = \App\Models\DocumentExtraction::create([
                    'document_id' => $document->id,
                    'extraction_version' => 1,
                    'model_used' => config('services.xai.vision_model', 'grok-4-1-fast-non-reasoning'),
                    'extracted_fields' => $extracted,
                    'field_confidence' => $extracted['confidence'] ?? [],
                    'warnings' => $extracted['warnings'] ?? [],
                    'is_valid' => true,
                ]);

                $sheetResults[] = [
                    'extraction_id' => $extraction->id,
                    'sheet_name' => $sheet['name'],
                    'row_count' => $sheet['row_count'],
                    'category' => $extracted['category'] ?? 'ignore',
                    'category_confidence' => $extracted['category_confidence'] ?? 0.0,
                    'account' => $extracted['account'] ?? [],
                    'holdings' => $extracted['holdings'] ?? [],
                    'properties' => $extracted['properties'] ?? [],
                    'policies' => $extracted['policies'] ?? [],
                    'confidence' => $extracted['confidence'] ?? [],
                    'warnings' => $extracted['warnings'] ?? [],
                ];
            } catch (\Exception $e) {
                \Log::warning("[DocumentProcessor] Sheet extraction failed: {$sheet['name']}", [
                    'error' => $e->getMessage(),
                ]);
                $sheetResults[] = [
                    'extraction_id' => null,
                    'sheet_name' => $sheet['name'],
                    'row_count' => $sheet['row_count'],
                    'category' => 'error',
                    'error' => 'Failed to process this sheet',
                ];
            }
        }

        $document->update(['status' => Document::STATUS_REVIEW_PENDING]);

        // 4. Auto-match accounts
        $importService = app(HoldingsImportService::class);
        foreach ($sheetResults as &$result) {
            if (in_array($result['category'], ['investment_holdings', 'pension_holdings'])) {
                $match = $importService->findMatchingAccount(
                    $user,
                    $result['category'],
                    $result['account'] ?? [],
                );
                $result['matched_account'] = $match ? [
                    'id' => $match->id,
                    'name' => $match->provider ?? $match->scheme_name ?? 'Unknown',
                    'type' => class_basename($match),
                ] : null;

                // Diff holdings if matched
                if ($match && !empty($result['holdings'])) {
                    $result['holdings'] = $importService->diffHoldings($match, $result['holdings']);
                }
            } else {
                $result['matched_account'] = null;
            }
        }
        unset($result);

        return [
            'document' => $document->fresh(),
            'sheets' => $sheetResults,
        ];
    });
}

/**
 * Confirm Excel import — create/update accounts and holdings from confirmed sheet data.
 */
public function confirmExcel(
    Document $document,
    array $confirmedSheets,
    User $user,
): array {
    return DB::transaction(function () use ($document, $confirmedSheets, $user) {
        $importService = app(HoldingsImportService::class);
        $results = [];

        foreach ($confirmedSheets as $sheet) {
            $category = $sheet['category'] ?? 'ignore';
            if ($category === 'ignore' || $category === 'error') {
                continue;
            }

            $accountId = $sheet['matched_account_id'] ?? null;
            $createNew = $sheet['create_new'] ?? false;

            if ($category === 'investment_holdings' || $category === 'pension_holdings') {
                $account = null;

                if ($accountId) {
                    $modelClass = $category === 'investment_holdings'
                        ? InvestmentAccount::class
                        : DCPension::class;
                    $account = $modelClass::where('user_id', $user->id)->find($accountId);
                }

                if (!$account && $createNew) {
                    $accountData = $sheet['account'] ?? [];
                    $accountData['user_id'] = $user->id;

                    if ($category === 'investment_holdings') {
                        $account = InvestmentAccount::create($accountData);
                    } else {
                        $account = DCPension::create($accountData);
                    }
                }

                if ($account && !empty($sheet['holdings'])) {
                    $holdingResults = $importService->applyHoldings($account, $sheet['holdings']);
                    $results[] = [
                        'sheet_name' => $sheet['sheet_name'],
                        'category' => $category,
                        'account_id' => $account->id,
                        'account_type' => class_basename($account),
                        'holdings' => $holdingResults,
                    ];
                }
            } elseif ($category === 'cash_savings') {
                $mapper = new FieldMappers\SavingsAccountMapper();
                $mapped = $mapper->map($sheet['account'] ?? []);
                $mapped['user_id'] = $user->id;
                $model = \App\Models\SavingsAccount::create($mapped);
                $results[] = [
                    'sheet_name' => $sheet['sheet_name'],
                    'category' => $category,
                    'model_type' => 'SavingsAccount',
                    'model_id' => $model->id,
                ];
            } elseif ($category === 'property') {
                $mapper = new FieldMappers\PropertyMapper();
                foreach ($sheet['properties'] ?? [$sheet['account'] ?? []] as $propertyData) {
                    $mapped = $mapper->map($propertyData);
                    $mapped['user_id'] = $user->id;
                    $model = \App\Models\Property::create($mapped);
                    $results[] = [
                        'sheet_name' => $sheet['sheet_name'],
                        'category' => $category,
                        'model_type' => 'Property',
                        'model_id' => $model->id,
                    ];
                }
            } elseif ($category === 'protection') {
                $mapper = new ProtectionMapper();
                foreach ($sheet['policies'] ?? [$sheet['account'] ?? []] as $policyData) {
                    $mapped = $mapper->map($policyData);
                    $mapped['user_id'] = $user->id;
                    $policyType = $mapped['policy_type'] ?? 'term';
                    $modelClass = $mapper->getModelClassForType($policyType);
                    $model = $modelClass::create($mapped);
                    $results[] = [
                        'sheet_name' => $sheet['sheet_name'],
                        'category' => $category,
                        'model_type' => class_basename($modelClass),
                        'model_id' => $model->id,
                    ];
                }
            }
        }

        $document->update([
            'status' => Document::STATUS_CONFIRMED,
            'confirmed_at' => now(),
        ]);

        DocumentExtractionLog::log($document, $user, DocumentExtractionLog::ACTION_CONFIRMED, [
            'import_type' => 'excel',
            'sheets_processed' => count($results),
        ]);

        return [
            'document' => $document->fresh(),
            'results' => $results,
        ];
    });
}
```

Add missing use statements at the top of DocumentProcessor:
```php
use App\Models\Investment\InvestmentAccount;
use App\Models\DCPension;
```

- [ ] **Step 3: Update DocumentController for Excel flow**

Add to `app/Http/Controllers/Api/DocumentController.php`:

In the `upload()` method, add Excel detection before the existing `$this->processor->process()` call:

```php
public function upload(UploadDocumentRequest $request): JsonResponse
{
    try {
        $file = $request->file('document');
        $mimeType = $file->getMimeType();

        // Excel files use a different processing path
        $excelParser = app(\App\Services\Documents\ExcelParserService::class);
        if ($excelParser->isSpreadsheet($mimeType)) {
            $result = $this->processor->processExcel($file, $request->user());

            return response()->json([
                'success' => true,
                'message' => 'Workbook processed successfully',
                'data' => [
                    'document_id' => $result['document']->id,
                    'is_excel' => true,
                    'sheets' => $result['sheets'],
                ],
            ], 201);
        }

        // Existing PDF/image flow (unchanged)
        $result = $this->processor->process(
            $file,
            $request->user(),
            $request->input('document_type')
        );
        // ... rest of existing code ...
```

Add new `confirmExcel()` endpoint:

```php
/**
 * Confirm Excel import with sheet mappings.
 * POST /api/documents/{id}/confirm-excel
 */
public function confirmExcel(Request $request, int $id): JsonResponse
{
    $request->validate([
        'sheets' => 'required|array|min:1',
        'sheets.*.sheet_name' => 'required|string',
        'sheets.*.category' => 'required|string',
    ]);

    $document = Document::where('user_id', $request->user()->id)->findOrFail($id);

    try {
        $result = $this->processor->confirmExcel(
            $document,
            $request->input('sheets'),
            $request->user()
        );

        return response()->json([
            'success' => true,
            'message' => 'Import completed successfully',
            'data' => $result,
        ]);
    } catch (\Exception $e) {
        return $this->safeErrorResponse('Import failed', $e);
    }
}
```

- [ ] **Step 4: Add route for confirmExcel**

In `routes/api.php`, find the documents route group and add:

```php
Route::post('/documents/{id}/confirm-excel', [DocumentController::class, 'confirmExcel']);
```

- [ ] **Step 5: Verify PHP syntax**

Run: `php -l app/Services/Documents/DocumentProcessor.php && php -l app/Http/Controllers/Api/DocumentController.php && php -l app/Http/Requests/Documents/UploadDocumentRequest.php`
Expected: No syntax errors

- [ ] **Step 6: Commit**

```bash
git add app/Services/Documents/DocumentProcessor.php \
       app/Http/Controllers/Api/DocumentController.php \
       app/Http/Requests/Documents/UploadDocumentRequest.php \
       routes/api.php
git commit -m "feat: add Excel upload processing and confirm-excel endpoint"
```

---

## Task 6: Frontend — UploadDropZone + DocumentUploadModal Excel Support

**Files:**
- Modify: `resources/js/components/Shared/UploadDropZone.vue`
- Modify: `resources/js/components/Shared/DocumentUploadModal.vue`
- Modify: `resources/js/services/documentService.js`

- [ ] **Step 1: Update UploadDropZone accepted types**

In `resources/js/components/Shared/UploadDropZone.vue`, find the accepted types and add Excel:

```javascript
// In the accept prop default or the input element
accept: '.pdf,.jpg,.jpeg,.png,.webp,.xlsx,.xls,.csv'
```

Update the help text to mention spreadsheets:

```
PDF, images, or Excel spreadsheets (max 20MB)
```

- [ ] **Step 2: Add confirmExcel to documentService**

In `resources/js/services/documentService.js`, add:

```javascript
/**
 * Confirm Excel import with sheet mappings.
 */
async confirmExcel(documentId, sheets) {
    const response = await api.post(`/documents/${documentId}/confirm-excel`, { sheets });
    return response.data;
},
```

- [ ] **Step 3: Update DocumentUploadModal for Excel flow**

In `resources/js/components/Shared/DocumentUploadModal.vue`:

After the upload response is received, detect if it's an Excel file and switch to sheet review:

```javascript
// In the upload handler, after receiving response:
if (response.data.is_excel) {
    this.isExcel = true;
    this.documentId = response.data.document_id;
    this.sheets = response.data.sheets;
    this.step = 'sheet-review'; // New step
    return;
}
// ... existing PDF/image review flow ...
```

Add data properties:

```javascript
data() {
    return {
        // ... existing properties ...
        isExcel: false,
        sheets: [],
    };
},
```

Add the SheetReviewStep component in the template (between processing and review steps):

```html
<SheetReviewStep
    v-if="step === 'sheet-review'"
    :sheets="sheets"
    :document-id="documentId"
    @confirm="handleExcelConfirm"
    @close="$emit('close')"
/>
```

Add the confirm handler:

```javascript
async handleExcelConfirm(confirmedSheets) {
    this.step = 'processing';
    try {
        const response = await documentService.confirmExcel(this.documentId, confirmedSheets);
        this.$emit('saved', response.data);
        this.$emit('close');
    } catch (error) {
        this.error = 'Import failed. Please try again.';
        this.step = 'sheet-review';
    }
},
```

- [ ] **Step 4: Commit**

```bash
git add resources/js/components/Shared/UploadDropZone.vue \
       resources/js/components/Shared/DocumentUploadModal.vue \
       resources/js/services/documentService.js
git commit -m "feat: extend DocumentUploadModal and UploadDropZone for Excel files"
```

---

## Task 7: Frontend — SheetReviewStep + HoldingsReviewTable Components

**Files:**
- Create: `resources/js/components/Shared/SheetReviewStep.vue`
- Create: `resources/js/components/Shared/HoldingsReviewTable.vue`

- [ ] **Step 1: Create HoldingsReviewTable component**

`resources/js/components/Shared/HoldingsReviewTable.vue`:

A table showing holdings with diff status badges. Props: `holdings` (array with status field), `editable` (boolean). Shows columns: Security Name, Ticker/ISIN, Quantity, Price, Value, Status badge.

Status badges:
- `add` → green "New" badge
- `update` → blue "Updated" badge (show old → new for quantity/value)
- `unchanged` → muted "No Change"
- `not_in_import` → muted italic with optional remove checkbox

Emits `update:holdings` when user edits, `remove` when user ticks remove on a not_in_import row.

This component should follow the design system: `raspberry-*` for CTAs, `horizon-*` for text, `spring-*` for success/new badges, `violet-*` for update badges, `neutral-*` for muted. Use `currencyMixin` for formatting values. Use Tailwind classes from the palette — no hardcoded hex.

- [ ] **Step 2: Create SheetReviewStep component**

`resources/js/components/Shared/SheetReviewStep.vue`:

Props: `sheets` (array from backend), `documentId`.

Displays all sheets in a vertical list. Per sheet:
- Sheet name + row count
- Category badge dropdown (investment_holdings | pension_holdings | cash_savings | property | protection | skip)
- Account match dropdown (for investment/pension categories): lists existing accounts + "Create new account"
- Expandable section: HoldingsReviewTable (for investment/pension) or field list (for cash/property/protection)
- Error state for sheets that failed extraction

Footer: "Import All" button (raspberry CTA) and "Cancel" link.

On confirm, collects all sheet data (category, matched_account_id or create_new flag, holdings with statuses) and emits `confirm` with the array.

Follows the design system: card containers with `rounded-xl border border-light-gray`, `bg-white`, sheet name in `text-horizon-700 font-bold`, badges using standard badge classes.

- [ ] **Step 3: Verify Vite compiles without errors**

Run: `./dev.sh` and check terminal for compile errors. Fix any import issues.

- [ ] **Step 4: Commit**

```bash
git add resources/js/components/Shared/SheetReviewStep.vue \
       resources/js/components/Shared/HoldingsReviewTable.vue
git commit -m "feat: add SheetReviewStep and HoldingsReviewTable components"
```

---

## Task 8: Integration Test — Full Excel Upload Flow

**Files:**
- Test: `tests/Feature/Documents/ExcelUploadTest.php`

- [ ] **Step 1: Write integration test**

```php
<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Investment\InvestmentAccount;
use App\Services\Documents\ExcelParserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\TaxConfigurationSeeder::class);
});

it('accepts xlsx upload and returns sheet data', function () {
    $user = User::factory()->create();
    $this->actingAs($user, 'sanctum');

    // Create a test xlsx file
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('ISA');
    $sheet->setCellValue('A1', 'Security Name');
    $sheet->setCellValue('B1', 'Ticker');
    $sheet->setCellValue('C1', 'Value');
    $sheet->setCellValue('A2', 'Vanguard FTSE 100');
    $sheet->setCellValue('B2', 'VUKE');
    $sheet->setCellValue('C2', '15000');

    $tempPath = tempnam(sys_get_temp_dir(), 'test_') . '.xlsx';
    (new Xlsx($spreadsheet))->save($tempPath);

    $file = new UploadedFile($tempPath, 'portfolio.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

    $response = $this->postJson('/api/documents/upload', [
        'document' => $file,
    ]);

    $response->assertStatus(201);
    $response->assertJsonPath('data.is_excel', true);
    $response->assertJsonStructure([
        'data' => [
            'document_id',
            'is_excel',
            'sheets' => [
                '*' => ['sheet_name', 'category'],
            ],
        ],
    ]);

    unlink($tempPath);
});

it('rejects files over 20MB', function () {
    $user = User::factory()->create();
    $this->actingAs($user, 'sanctum');

    $file = UploadedFile::fake()->create('huge.xlsx', 21000); // 21MB

    $response = $this->postJson('/api/documents/upload', [
        'document' => $file,
    ]);

    $response->assertStatus(422);
});
```

- [ ] **Step 2: Run test**

Run: `./vendor/bin/pest tests/Feature/Documents/ExcelUploadTest.php -v`

Note: The AI extraction call will need mocking in CI, but for local testing it will call the real API. Mark the AI-dependent test with a `->skip()` or mock if needed.

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/Documents/ExcelUploadTest.php
git commit -m "test: add Excel upload integration tests"
```

---

## Task 9: Browser Testing — End-to-End Upload Flow

**Files:** No code changes — Playwright browser testing only

- [ ] **Step 1: Seed database**

Run: `php artisan db:seed`

- [ ] **Step 2: Log in and navigate to a page with document upload**

Use Playwright to:
1. Log in as `chris@fynla.org` / `Password1!` (get verification code from DB)
2. Navigate to Investments page
3. Find and click the upload/import button

- [ ] **Step 3: Upload a test Excel file**

Create a test .xlsx with 2 sheets (ISA + SIPP), upload via the modal, verify:
- Processing spinner shows
- Sheet review step appears with both sheets listed
- Categories are auto-detected
- Account matching works
- Holdings table is visible

- [ ] **Step 4: Confirm import and verify data saved**

Click "Import All", verify:
- Success state shows
- Holdings appear on the investment account
- No console errors

- [ ] **Step 5: Commit any fixes found during testing**

```bash
git add -A
git commit -m "fix: browser testing fixes for Excel upload flow"
```
