<?php

declare(strict_types=1);

namespace App\Services\Documents;

use App\Models\Document;
use App\Models\DocumentExtraction;
use App\Models\DocumentExtractionLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Smalot\PdfParser\Parser as PdfParser;

class AIExtractionService
{
    private const ANTHROPIC_API_URL = 'https://api.anthropic.com/v1/messages';

    private const ANTHROPIC_MODEL = 'claude-3-5-haiku-20241022';

    private const XAI_API_URL = 'https://api.x.ai/v1/chat/completions';

    private const MAX_TOKENS = 4096;

    private const TIMEOUT_SECONDS = 120;

    /**
     * Maximum file size for scanned PDFs (no extractable text) - 15MB.
     * Text-based PDFs can be larger since we extract text.
     */
    private const MAX_SCANNED_PDF_SIZE = 15 * 1024 * 1024;

    public function __construct(
        private readonly DocumentUploadService $uploadService,
        private readonly DocumentTypeDetector $typeDetector,
        private readonly ImageResizeService $imageResizeService,
    ) {}

    /**
     * Extract data from a single Excel sheet's text content.
     *
     * @return array{category: string, category_confidence: float, account: array, holdings: array, confidence: array, warnings: array}
     */
    public function extractSheet(string $sheetName, string $sheetContent): array
    {
        $prompt = $this->getBasePrompt()."\n\n".$this->getExcelSheetPrompt();
        $userContent = "Sheet name: \"{$sheetName}\"\n\n{$sheetContent}";

        $response = $this->callClaudeAPIWithText($userContent, $prompt);
        $parsed = $this->parseResponse($response);

        return array_merge([
            'sheet_name' => $sheetName,
            'category' => 'ignore',
            'category_confidence' => 0.0,
            'account' => [],
            'holdings' => [],
            'properties' => [],
            'policies' => [],
            'confidence' => [],
            'warnings' => [],
        ], $parsed);
    }

    /**
     * Extract data from a document using AI Vision API (Anthropic or xAI).
     */
    public function extract(Document $document): DocumentExtraction
    {
        \Log::info('[AIExtractionService] extract called', ['document_id' => $document->id]);
        $user = $document->user;

        // Update status to processing
        $document->update(['status' => Document::STATUS_PROCESSING]);

        // Log extraction start
        DocumentExtractionLog::log(
            $document,
            $user,
            DocumentExtractionLog::ACTION_EXTRACTION_STARTED
        );

        try {
            $mediaType = $document->mime_type;
            Log::info('[AIExtractionService] Processing document', ['media_type' => $mediaType]);

            // Build the extraction prompt
            $prompt = $this->buildExtractionPrompt($document);

            // Handle PDFs - try text extraction first
            if ($mediaType === 'application/pdf') {
                $response = $this->processPdfDocument($document, $prompt);
            } else {
                // Images - use vision API
                Log::info('[AIExtractionService] Processing as image');
                $base64 = $this->uploadService->getBase64($document);
                Log::info('[AIExtractionService] Calling Claude API', ['base64_length' => strlen($base64)]);
                $response = $this->callClaudeAPI($base64, $mediaType, $prompt);
                Log::info('[AIExtractionService] Claude API response received');
            }

            // Parse the response
            $extractedData = $this->parseResponse($response);

            // Detect document type if unknown
            if ($document->document_type === Document::TYPE_UNKNOWN) {
                $detection = $this->typeDetector->detect($extractedData);
                $document->update([
                    'document_type' => $detection['type'],
                    'detected_document_subtype' => $detection['subtype'],
                    'detection_confidence' => $detection['confidence'],
                ]);
            } elseif (! $document->detected_document_subtype && isset($extractedData['document_subtype'])) {
                $document->update([
                    'detected_document_subtype' => $extractedData['document_subtype'],
                ]);
            }

            // Get next version number
            $version = ($document->extractions()->max('extraction_version') ?? 0) + 1;

            // Create extraction record
            $extraction = DocumentExtraction::create([
                'document_id' => $document->id,
                'extraction_version' => $version,
                'model_used' => $response['model'] ?? (\Illuminate\Support\Facades\Cache::get('ai_provider', config('services.ai_provider', 'anthropic')) === 'xai'
                    ? config('services.xai.vision_model', 'grok-4-1-fast-non-reasoning')
                    : self::ANTHROPIC_MODEL),
                'input_tokens' => $response['usage']['input_tokens'] ?? null,
                'output_tokens' => $response['usage']['output_tokens'] ?? null,
                'raw_response' => json_encode($response),
                'extracted_fields' => $extractedData['fields'] ?? [],
                'field_confidence' => $extractedData['confidence'] ?? [],
                'warnings' => $extractedData['warnings'] ?? null,
                'target_model' => $this->typeDetector->getTargetModel($document),
            ]);

            // Update document status
            $document->update([
                'status' => Document::STATUS_EXTRACTED,
                'processed_at' => now(),
            ]);

            // Log success
            DocumentExtractionLog::log(
                $document,
                $user,
                DocumentExtractionLog::ACTION_EXTRACTION_COMPLETED,
                [
                    'extraction_id' => $extraction->id,
                    'tokens_used' => ($extraction->input_tokens ?? 0) + ($extraction->output_tokens ?? 0),
                    'fields_extracted' => count($extractedData['fields'] ?? []),
                ]
            );

            return $extraction;

        } catch (\Exception $e) {
            Log::error('Document extraction failed', [
                'document_id' => $document->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Update document status
            $document->update([
                'status' => Document::STATUS_FAILED,
                'error_message' => $e->getMessage(),
            ]);

            // Log failure
            DocumentExtractionLog::log(
                $document,
                $user,
                DocumentExtractionLog::ACTION_EXTRACTION_FAILED,
                ['error' => $e->getMessage()]
            );

            throw $e;
        }
    }

    /**
     * Call the vision API (supports both Anthropic and xAI providers).
     */
    private function callClaudeAPI(string $base64, string $mediaType, string $prompt): array
    {
        $isXai = \Illuminate\Support\Facades\Cache::get('ai_provider', config('services.ai_provider', 'anthropic')) === 'xai';

        // For images, resize if exceeds API limits
        $processedData = $base64;
        $processedMediaType = $mediaType;

        if ($mediaType !== 'application/pdf') {
            $result = $this->imageResizeService->processForClaudeAPI($base64, $mediaType);
            $processedData = $result['data'];
            $processedMediaType = $result['media_type'];

            if ($result['was_resized']) {
                Log::info('Image was resized for API', [
                    'original_media_type' => $mediaType,
                    'new_media_type' => $processedMediaType,
                ]);
            }
        }

        if ($isXai) {
            return $this->callXaiVisionAPI($processedData, $processedMediaType, $prompt);
        }

        return $this->callAnthropicVisionAPI($processedData, $processedMediaType, $prompt);
    }

    /**
     * Call xAI vision API (OpenAI-compatible format).
     */
    private function callXaiVisionAPI(string $base64, string $mediaType, string $prompt): array
    {
        $apiKey = config('services.xai.api_key');
        if (! $apiKey) {
            throw new RuntimeException('XAI_API_KEY is not configured');
        }

        $model = config('services.xai.vision_model', 'grok-4-1-fast-non-reasoning');

        // Build image content block in OpenAI format
        $imageUrl = "data:{$mediaType};base64,{$base64}";
        $content = [
            ['type' => 'image_url', 'image_url' => ['url' => $imageUrl]],
            ['type' => 'text', 'text' => $prompt],
        ];

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$apiKey}",
            'Content-Type' => 'application/json',
        ])->timeout(self::TIMEOUT_SECONDS)->post(self::XAI_API_URL, [
            'model' => $model,
            'max_tokens' => self::MAX_TOKENS,
            'messages' => [
                ['role' => 'user', 'content' => $content],
            ],
        ]);

        if (! $response->successful()) {
            $errorBody = $response->json();
            $errorMessage = $errorBody['error']['message'] ?? $response->body();
            throw new RuntimeException('xAI API error: '.$errorMessage);
        }

        $json = $response->json();

        // Normalise response to common format for parseResponse()
        return [
            'content' => [['text' => $json['choices'][0]['message']['content'] ?? '']],
            'usage' => [
                'input_tokens' => $json['usage']['prompt_tokens'] ?? 0,
                'output_tokens' => $json['usage']['completion_tokens'] ?? 0,
            ],
            'model' => $json['model'] ?? $model,
        ];
    }

    /**
     * Call Anthropic vision API (legacy).
     */
    private function callAnthropicVisionAPI(string $base64, string $mediaType, string $prompt): array
    {
        $apiKey = config('services.anthropic.api_key');
        if (! $apiKey) {
            throw new RuntimeException('Anthropic API key not configured');
        }

        $contentBlock = $this->buildContentBlock($base64, $mediaType);

        $headers = [
            'x-api-key' => $apiKey,
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ];

        $response = Http::withHeaders($headers)->timeout(self::TIMEOUT_SECONDS)->post(self::ANTHROPIC_API_URL, [
            'model' => self::ANTHROPIC_MODEL,
            'max_tokens' => self::MAX_TOKENS,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        $contentBlock,
                        ['type' => 'text', 'text' => $prompt],
                    ],
                ],
            ],
        ]);

        if (! $response->successful()) {
            $errorBody = $response->json();
            $errorMessage = $errorBody['error']['message'] ?? $response->body();
            throw new RuntimeException('Claude API error: '.$errorMessage);
        }

        return $response->json();
    }

    /**
     * Call AI API with text content (for spreadsheets and text-based PDFs).
     */
    private function callClaudeAPIWithText(string $textContent, string $prompt): array
    {
        $isXai = \Illuminate\Support\Facades\Cache::get('ai_provider', config('services.ai_provider', 'anthropic')) === 'xai';
        $fullPrompt = "Here is the spreadsheet data:\n\n{$textContent}\n\n{$prompt}";

        if ($isXai) {
            $apiKey = config('services.xai.api_key');
            if (! $apiKey) {
                throw new RuntimeException('XAI_API_KEY is not configured');
            }

            $model = config('services.xai.vision_model', 'grok-4-1-fast-non-reasoning');

            $response = Http::withHeaders([
                'Authorization' => "Bearer {$apiKey}",
                'Content-Type' => 'application/json',
            ])->timeout(self::TIMEOUT_SECONDS)->post(self::XAI_API_URL, [
                'model' => $model,
                'max_tokens' => self::MAX_TOKENS,
                'messages' => [
                    ['role' => 'user', 'content' => $fullPrompt],
                ],
            ]);

            if (! $response->successful()) {
                $errorBody = $response->json();
                $errorMessage = $errorBody['error']['message'] ?? $response->body();
                throw new RuntimeException('xAI API error: '.$errorMessage);
            }

            $json = $response->json();

            return [
                'content' => [['text' => $json['choices'][0]['message']['content'] ?? '']],
                'usage' => [
                    'input_tokens' => $json['usage']['prompt_tokens'] ?? 0,
                    'output_tokens' => $json['usage']['completion_tokens'] ?? 0,
                ],
                'model' => $json['model'] ?? $model,
            ];
        }

        // Anthropic path
        $apiKey = config('services.anthropic.api_key');
        if (! $apiKey) {
            throw new RuntimeException('Anthropic API key not configured');
        }

        $response = Http::withHeaders([
            'x-api-key' => $apiKey,
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ])->timeout(self::TIMEOUT_SECONDS)->post(self::ANTHROPIC_API_URL, [
            'model' => self::ANTHROPIC_MODEL,
            'max_tokens' => self::MAX_TOKENS,
            'messages' => [
                ['role' => 'user', 'content' => $fullPrompt],
            ],
        ]);

        if (! $response->successful()) {
            $errorBody = $response->json();
            $errorMessage = $errorBody['error']['message'] ?? $response->body();
            throw new RuntimeException('Claude API error: '.$errorMessage);
        }

        return $response->json();
    }

    /**
     * Build the appropriate content block based on media type.
     * PDFs use 'document' type, images use 'image' type.
     */
    private function buildContentBlock(string $base64, string $mediaType): array
    {
        if ($mediaType === 'application/pdf') {
            return [
                'type' => 'document',
                'source' => [
                    'type' => 'base64',
                    'media_type' => 'application/pdf',
                    'data' => $base64,
                ],
            ];
        }

        // For images (jpeg, png, gif, webp)
        return [
            'type' => 'image',
            'source' => [
                'type' => 'base64',
                'media_type' => $mediaType,
                'data' => $base64,
            ],
        ];
    }

    /**
     * Build the extraction prompt based on document type.
     */
    private function buildExtractionPrompt(Document $document): string
    {
        $basePrompt = $this->getBasePrompt();

        // Add type-specific extraction guidance
        $typeGuidance = match ($document->document_type) {
            Document::TYPE_PENSION_STATEMENT => $this->getPensionPrompt(),
            Document::TYPE_INSURANCE_POLICY => $this->getInsurancePrompt(),
            Document::TYPE_INVESTMENT_STATEMENT => $this->getInvestmentPrompt(),
            Document::TYPE_MORTGAGE_STATEMENT => $this->getMortgagePrompt(),
            Document::TYPE_SAVINGS_STATEMENT => $this->getSavingsPrompt(),
            default => $this->getUnknownTypePrompt(),
        };

        return $basePrompt."\n\n".$typeGuidance;
    }

    /**
     * Base prompt for all document types.
     */
    private function getBasePrompt(): string
    {
        return <<<'PROMPT'
You are a financial document extraction specialist for a UK financial planning application.

IMPORTANT RULES:
1. Extract all visible data from this document
2. Return data as valid JSON with the exact field names specified
3. For each field, provide a confidence score (0.0 to 1.0)
4. If a field is not found, use null
5. Use ISO 8601 format for dates (YYYY-MM-DD)
6. Use numeric values without currency symbols or commas
7. Percentages as decimals (5% = 0.05, not 5)
8. Preserve exact provider/scheme names as written
9. Note any warnings or ambiguities

UK CONTEXT:
- Tax year runs April 6 to April 5 (e.g., 2024/25 = 6 April 2024 to 5 April 2025)
- Currency is GBP (£)
- Date formats on documents are typically DD/MM/YYYY

Response format:
{
  "document_type": "detected type",
  "document_subtype": "specific subtype",
  "fields": { ... extracted field values ... },
  "confidence": { ... confidence per field (0.0-1.0) ... },
  "warnings": [ ... any extraction warnings ... ]
}
PROMPT;
    }

    /**
     * Pension-specific extraction prompt.
     */
    private function getPensionPrompt(): string
    {
        return <<<'PROMPT'
PENSION DOCUMENT - Identify if DC Pension, DB Pension, or State Pension and extract:

For DC Pensions (workplace, SIPP, personal, stakeholder):
- provider: Company administering the pension
- scheme_name: Name of the pension scheme
- member_number: Member/policy reference number
- pension_type: One of [occupational, sipp, personal, stakeholder]
- current_fund_value: Current pot value (number only)
- annual_salary: Pensionable salary if shown
- employee_contribution_percent: Employee % as decimal (5% = 0.05)
- employer_contribution_percent: Employer % as decimal
- monthly_contribution_amount: Fixed monthly amount (for personal pensions)
- platform_fee_percent: Annual fee as decimal (0.45% = 0.0045)
- retirement_age: Target retirement age
- projected_value_at_retirement: Projected value if shown
- investment_strategy: Fund/strategy description

For DB Pensions (final salary, career average, public sector):
- scheme_name: Name of the DB scheme
- scheme_type: One of [final_salary, career_average, public_sector]
- accrued_annual_pension: Annual pension entitlement
- pensionable_service_years: Years of service (can be decimal)
- pensionable_salary: Pensionable salary
- normal_retirement_age: Scheme NRA
- spouse_pension_percent: Spouse pension as decimal (50% = 0.50)
- lump_sum_entitlement: Tax-free lump sum
- inflation_protection: One of [cpi, rpi, fixed, none]
- revaluation_method: How benefits revalue

For State Pension (DWP forecast):
- ni_years_completed: Qualifying years on record
- ni_years_required: Years needed for full pension (typically 35)
- state_pension_forecast_annual: Annual forecast amount
- state_pension_age: Age when eligible

Set document_subtype to: dc_pension, db_pension, or state_pension
PROMPT;
    }

    /**
     * Insurance-specific extraction prompt.
     */
    private function getInsurancePrompt(): string
    {
        return <<<'PROMPT'
INSURANCE POLICY - Identify policy type and extract:

For Life Insurance:
- provider: Insurance company name
- policy_number: Policy reference
- policy_type: One of [term, decreasing_term, level_term, whole_of_life, family_income_benefit]
- sum_assured: Death benefit amount
- premium_amount: Premium payment
- premium_frequency: One of [monthly, quarterly, annually]
- policy_start_date: Inception date (YYYY-MM-DD)
- policy_end_date: Expiry date (YYYY-MM-DD) - null for whole of life
- policy_term_years: Term in years
- indexation_rate: Annual increase as decimal
- in_trust: true/false if written in trust
- beneficiaries: Named beneficiaries if shown

For Critical Illness:
- provider: Insurance company
- policy_number: Policy reference
- policy_type: One of [standalone, accelerated, additional]
- sum_assured: CI benefit amount
- premium_amount: Premium payment
- premium_frequency: One of [monthly, quarterly, annually]
- policy_start_date: Start date (YYYY-MM-DD)
- policy_term_years: Term in years

For Income Protection:
- provider: Insurance company
- policy_number: Policy reference
- benefit_amount: Monthly/weekly benefit
- benefit_frequency: One of [monthly, weekly]
- deferred_period_weeks: Waiting period in weeks
- benefit_period_months: Benefit duration (null if to retirement)
- premium_amount: Premium payment
- policy_start_date: Start date (YYYY-MM-DD)

Set document_subtype to: life_insurance, critical_illness, or income_protection
PROMPT;
    }

    /**
     * Investment-specific extraction prompt.
     */
    private function getInvestmentPrompt(): string
    {
        return <<<'PROMPT'
INVESTMENT STATEMENT - Extract account and holdings:

Account Details:
- provider: Platform/provider name (e.g., Hargreaves Lansdown, Vanguard)
- account_number: Account reference
- account_type: One of [isa, gia, nsi, onshore_bond, offshore_bond, vct, eis, other]
- platform: Platform name if different from provider
- current_value: Total account value
- contributions_ytd: Contributions this tax year
- isa_subscription_current_year: ISA contributions this year (for ISAs, max £20,000)
- platform_fee_percent: Annual platform fee as decimal
- tax_year: Tax year of statement (YYYY/YY format)

Holdings (array of investments):
Each holding should have:
- security_name: Name of the investment
- ticker: Stock ticker if shown
- isin: ISIN code if shown
- asset_type: One of [uk_equity, us_equity, international_equity, fund, etf, bond, cash, alternative, property]
- quantity: Number of units/shares
- current_price: Price per unit
- current_value: Total value

Set document_subtype to: investment_account
PROMPT;
    }

    /**
     * Mortgage-specific extraction prompt.
     */
    private function getMortgagePrompt(): string
    {
        return <<<'PROMPT'
MORTGAGE STATEMENT - Extract:

- lender_name: Mortgage lender
- mortgage_account_number: Account reference
- mortgage_type: One of [repayment, interest_only, mixed]
- original_loan_amount: Original loan amount
- outstanding_balance: Current balance
- interest_rate: Current rate as decimal (3.5% = 0.035)
- rate_type: One of [fixed, variable, tracker, discount, mixed]
- rate_fix_end_date: Fixed rate end date (YYYY-MM-DD) if applicable
- monthly_payment: Monthly payment amount
- start_date: Mortgage start date (YYYY-MM-DD)
- maturity_date: Mortgage end date (YYYY-MM-DD)
- remaining_term_months: Months remaining

Set document_subtype to: mortgage
PROMPT;
    }

    /**
     * Savings-specific extraction prompt.
     */
    private function getSavingsPrompt(): string
    {
        return <<<'PROMPT'
SAVINGS/BANK STATEMENT - Extract:

- institution: Bank/building society name
- account_number: Account number (last 4 digits for security)
- account_type: Type of savings account
- current_balance: Current balance
- interest_rate: Interest rate as decimal (AER)
- access_type: One of [immediate, notice, fixed]
- notice_period_days: Notice period if applicable
- maturity_date: Maturity date if fixed term (YYYY-MM-DD)
- is_isa: true/false if this is a Cash ISA
- isa_subscription_year: Tax year of subscription (YYYY/YY)
- isa_subscription_amount: Amount subscribed this year

Set document_subtype to: savings_account or cash_account
PROMPT;
    }

    /**
     * Excel sheet classification and extraction prompt.
     */
    private function getExcelSheetPrompt(): string
    {
        return <<<'PROMPT'
EXCEL SHEET EXTRACTION — Classify this sheet and extract financial data.

Determine what category this sheet belongs to based on the sheet name AND content:

CATEGORIES:
- investment_holdings: ISA, GIA, stocks, equities, portfolio, investments (columns: ticker, units, price, ISIN)
- pension_holdings: SIPP, pension fund, retirement pot, DC pension (columns: fund names, units, value)
- cash_savings: Cash, current account, savings, easy access, deposits (columns: balance, interest rate, sort code)
- property: Property, properties, real estate, house, flat, buy-to-let (columns: address, value, rental income)
- protection: Insurance, policies, life cover, protection, critical illness (columns: sum assured, premium, policy number)
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
  address, current_value, property_type (main_residence|secondary_residence|buy_to_let),
  ownership_type (individual|joint), rental_income_monthly, mortgage_outstanding

For protection: "holdings" should be empty. Return one object per policy row in "policies" array:
  provider, policy_type (term|whole_of_life|critical_illness|income_protection),
  sum_assured, premium_amount, premium_frequency (monthly|annually), start_date, term_years

For ignore: Return minimal response with just category and category_confidence.

IMPORTANT: Use the sheet name as a strong signal for category, but let the actual content override if they conflict.
PROMPT;
    }

    /**
     * Prompt for unknown document types.
     */
    private function getUnknownTypePrompt(): string
    {
        return <<<'PROMPT'
UNKNOWN DOCUMENT TYPE - Please analyze and:

1. Identify the document type from:
   - pension_statement (DC pension, DB pension, or State Pension)
   - insurance_policy (life, critical illness, income protection)
   - investment_statement (ISA, GIA, bonds, etc.)
   - mortgage_statement
   - savings_statement

2. Set document_subtype to the specific type:
   - dc_pension, db_pension, state_pension
   - life_insurance, critical_illness, income_protection
   - investment_account
   - mortgage
   - savings_account, cash_account

3. Extract all relevant fields for that document type following the patterns above.

If you cannot determine the document type with confidence, set document_type to "unknown".
PROMPT;
    }

    /**
     * Parse the Claude API response.
     */
    private function parseResponse(array $response): array
    {
        $content = $response['content'][0]['text'] ?? '';

        // Extract JSON from response (handle markdown code blocks)
        if (preg_match('/```(?:json)?\s*(.*?)\s*```/s', $content, $matches)) {
            $content = $matches[1];
        }

        // Try to parse as JSON
        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::warning('Failed to parse extraction response as JSON', [
                'error' => json_last_error_msg(),
                'content' => substr($content, 0, 500),
            ]);

            throw new RuntimeException(
                'Failed to parse extraction response: '.json_last_error_msg()
            );
        }

        return $data;
    }

    /**
     * Process a PDF document - try text extraction first, fall back to vision API.
     */
    private function processPdfDocument(Document $document, string $prompt): array
    {
        $fileContents = $this->uploadService->getFileContents($document);

        // Try to extract text from PDF
        $extractedText = $this->extractPdfText($fileContents);

        if ($extractedText !== null && strlen($extractedText) > 100) {
            // Text-based PDF - filter noise and send text to Claude
            Log::info('[AIExtractionService] PDF has extractable text', [
                'raw_length' => strlen($extractedText),
            ]);

            $filteredText = $this->filterPdfNoise($extractedText);

            Log::info('[AIExtractionService] Filtered PDF text', [
                'filtered_length' => strlen($filteredText),
            ]);

            return $this->callClaudeAPIWithText($filteredText, $prompt);
        }

        // Scanned PDF - check file size limit
        if ($document->file_size > self::MAX_SCANNED_PDF_SIZE) {
            throw new RuntimeException(
                'This PDF appears to be scanned (no extractable text) and is too large for image processing. '.
                'Maximum size for scanned PDFs is 15MB. Please try: '.
                '(1) Compress the PDF, (2) Re-scan at 150 DPI, or (3) Use a PDF with selectable text.'
            );
        }

        // Fall back to vision API for scanned PDFs
        Log::info('[AIExtractionService] PDF appears to be scanned, using vision API');
        $base64 = base64_encode($fileContents);

        return $this->callClaudeAPI($base64, 'application/pdf', $prompt);
    }

    /**
     * Extract text from a PDF file using smalot/pdfparser.
     *
     * @return string|null Extracted text, or null if extraction failed
     */
    private function extractPdfText(string $fileContents): ?string
    {
        try {
            // Increase memory limit temporarily for large PDFs
            $oldMemoryLimit = ini_get('memory_limit');
            ini_set('memory_limit', '256M');

            try {
                $parser = new PdfParser;
                $pdf = $parser->parseContent($fileContents);
                $text = $pdf->getText();

                return $text;
            } finally {
                ini_set('memory_limit', $oldMemoryLimit);
            }
        } catch (\Exception $e) {
            Log::warning('[AIExtractionService] PDF text extraction failed', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Filter noise from PDF text (T&Cs, disclaimers, headers, footers, marketing).
     */
    private function filterPdfNoise(string $text): string
    {
        $lines = explode("\n", $text);
        $filteredLines = [];
        $skipPatterns = [
            // Legal disclaimers
            '/terms\s+(and|&)\s+conditions/i',
            '/privacy\s+policy/i',
            '/data\s+protection/i',
            '/financial\s+conduct\s+authority/i',
            '/fca\s+register/i',
            '/regulated\s+by/i',
            '/authorised\s+(and|&)\s+regulated/i',
            '/compensation\s+scheme/i',
            '/fscs/i',
            '/ombudsman/i',
            '/complaints?\s+procedure/i',

            // Marketing content
            '/visit\s+(our\s+)?website/i',
            '/download\s+(our\s+)?app/i',
            '/contact\s+us\s+(today|now)/i',
            '/call\s+us\s+(on|at)/i',
            '/follow\s+us\s+on/i',
            '/social\s+media/i',
            '/newsletter/i',
            '/sign\s+up/i',
            '/special\s+offer/i',

            // Page furniture
            '/^page\s+\d+\s*(of\s+\d+)?$/i',
            '/^\d+\s*$/i',  // Just page numbers
            '/^[A-Z][a-z]+\s+\d{4}$/i',  // Month Year
            '/confidential/i',
            '/for\s+your\s+records/i',
            '/please\s+keep\s+this/i',

            // Introductory fluff
            '/thank\s+you\s+for\s+(your|choosing)/i',
            '/we\s+are\s+pleased\s+to/i',
            '/enclosed\s+(please\s+find|you\s+will\s+find)/i',
            '/this\s+statement\s+shows/i',
            '/important\s+information\s+about/i',

            // Website/contact info (typically footer)
            '/www\.[a-z0-9-]+\.[a-z]+/i',
            '/[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}/i',  // Emails
            '/registered\s+(in|office)/i',
            '/company\s+(number|no\.?|registration)/i',
            '/vat\s+(number|no\.?|registration)/i',
        ];

        // Keywords that indicate important financial content - keep these lines
        $keepPatterns = [
            '/fund\s+value/i',
            '/current\s+value/i',
            '/balance/i',
            '/contribution/i',
            '/pension/i',
            '/retirement/i',
            '/investment/i',
            '/premium/i',
            '/sum\s+assured/i',
            '/benefit/i',
            '/salary/i',
            '/employer/i',
            '/employee/i',
            '/annual/i',
            '/monthly/i',
            '/percentage/i',
            '/£\d/i',  // Currency amounts
            '/\d+\.\d{2}/i',  // Decimal numbers (likely money)
            '/\d{1,2}[\/\-]\d{1,2}[\/\-]\d{2,4}/i',  // Dates
        ];

        $inSkipSection = false;
        $skipSectionKeywords = [
            'terms and conditions',
            'important information',
            'regulatory information',
            'how to complain',
            'data protection',
            'privacy notice',
        ];

        foreach ($lines as $line) {
            $line = trim($line);

            // Skip empty lines
            if (empty($line)) {
                continue;
            }

            // Check if entering a skip section
            $lineLower = strtolower($line);
            foreach ($skipSectionKeywords as $keyword) {
                if (str_contains($lineLower, $keyword)) {
                    $inSkipSection = true;

                    continue 2;
                }
            }

            // Check if line contains financial data - always keep
            foreach ($keepPatterns as $pattern) {
                if (preg_match($pattern, $line)) {
                    $filteredLines[] = $line;
                    $inSkipSection = false;  // Reset skip section

                    continue 2;
                }
            }

            // Skip if in a skip section
            if ($inSkipSection) {
                continue;
            }

            // Check skip patterns
            $shouldSkip = false;
            foreach ($skipPatterns as $pattern) {
                if (preg_match($pattern, $line)) {
                    $shouldSkip = true;
                    break;
                }
            }

            if (! $shouldSkip) {
                $filteredLines[] = $line;
            }
        }

        return implode("\n", $filteredLines);
    }
}
