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
    private const API_URL = 'https://api.anthropic.com/v1/messages';

    private const MODEL = 'claude-haiku-4-5-20241022';

    private const MAX_TOKENS = 4096;

    private const TIMEOUT_SECONDS = 120;

    public function __construct(
        private DocumentUploadService $uploadService,
        private DocumentTypeDetector $typeDetector,
        private ImageResizeService $imageResizeService,
        private ExcelParserService $excelParserService,
    ) {}

    /**
     * Extract data from a document using Claude Vision API.
     */
    public function extract(Document $document): DocumentExtraction
    {
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

            // Build the extraction prompt
            $prompt = $this->buildExtractionPrompt($document);

            // Handle different file types appropriately
            if ($this->excelParserService->isSpreadsheet($mediaType)) {
                // Spreadsheets: extract text and send to Claude
                $fileContents = $this->uploadService->getFileContents($document);
                $spreadsheetText = $this->excelParserService->parseFromContent($fileContents, $mediaType);
                $response = $this->callClaudeAPIWithText($spreadsheetText, $prompt);
            } elseif ($mediaType === 'application/pdf') {
                // PDFs: extract text to avoid large base64 payloads
                $fileContents = $this->uploadService->getFileContents($document);
                $pdfText = $this->extractPdfText($fileContents);

                if (! empty(trim($pdfText))) {
                    // Use text-based extraction (much smaller payload)
                    $response = $this->callClaudeAPIWithText($pdfText, $prompt);
                } else {
                    // Fall back to image-based extraction if no text found (scanned PDF)
                    $base64 = $this->uploadService->getBase64($document);
                    $response = $this->callClaudeAPI($base64, $mediaType, $prompt);
                }
            } else {
                // Images: send as base64 (ImageResizeService handles size limits)
                $base64 = $this->uploadService->getBase64($document);
                $response = $this->callClaudeAPI($base64, $mediaType, $prompt);
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
                'model_used' => self::MODEL,
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
     * Call the Claude Vision API.
     */
    private function callClaudeAPI(string $base64, string $mediaType, string $prompt): array
    {
        $apiKey = config('services.anthropic.api_key');

        if (! $apiKey) {
            throw new RuntimeException('Anthropic API key not configured');
        }

        // Check size before sending to API (base64 adds ~33% overhead)
        // Claude API has a practical limit around 20-25MB for requests
        $base64SizeBytes = strlen($base64);
        $maxBase64Size = 20 * 1024 * 1024; // 20MB limit for base64 data

        if ($base64SizeBytes > $maxBase64Size) {
            $fileSizeMB = round($base64SizeBytes / (1024 * 1024), 1);

            if ($mediaType === 'application/pdf') {
                throw new RuntimeException(
                    "This appears to be a scanned PDF ({$fileSizeMB}MB) which is too large for image-based processing. ".
                    'Please try: (1) A smaller/compressed PDF, (2) Re-scan at lower resolution (150 DPI is sufficient), '.
                    'or (3) Use a PDF with selectable text instead of scanned images.'
                );
            }

            throw new RuntimeException(
                "File is too large for processing ({$fileSizeMB}MB). Please use a smaller file (under 15MB)."
            );
        }

        // For images, resize if exceeds Claude API 5MB limit
        $processedData = $base64;
        $processedMediaType = $mediaType;

        if ($mediaType !== 'application/pdf') {
            $result = $this->imageResizeService->processForClaudeAPI($base64, $mediaType);
            $processedData = $result['data'];
            $processedMediaType = $result['media_type'];

            if ($result['was_resized']) {
                Log::info('Image was resized for Claude API', [
                    'original_media_type' => $mediaType,
                    'new_media_type' => $processedMediaType,
                ]);
            }
        }

        // Build content based on media type
        $contentBlock = $this->buildContentBlock($processedData, $processedMediaType);

        // Build headers - PDF support is now GA, no beta header needed
        $headers = [
            'x-api-key' => $apiKey,
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ];

        $response = Http::withHeaders($headers)->timeout(self::TIMEOUT_SECONDS)->post(self::API_URL, [
            'model' => self::MODEL,
            'max_tokens' => self::MAX_TOKENS,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        $contentBlock,
                        [
                            'type' => 'text',
                            'text' => $prompt,
                        ],
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
     * Call Claude API with text content (for spreadsheets).
     */
    private function callClaudeAPIWithText(string $textContent, string $prompt): array
    {
        $apiKey = config('services.anthropic.api_key');

        if (! $apiKey) {
            throw new RuntimeException('Anthropic API key not configured');
        }

        $headers = [
            'x-api-key' => $apiKey,
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ];

        // Combine spreadsheet content with the prompt
        $fullPrompt = "Here is the spreadsheet data:\n\n{$textContent}\n\n{$prompt}";

        $response = Http::withHeaders($headers)->timeout(self::TIMEOUT_SECONDS)->post(self::API_URL, [
            'model' => self::MODEL,
            'max_tokens' => self::MAX_TOKENS,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $fullPrompt,
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
     * Extract text from PDF and filter out noise (T&Cs, legal, marketing).
     */
    private function extractPdfText(string $fileContents): string
    {
        try {
            $parser = new PdfParser;
            $pdf = $parser->parseContent($fileContents);
            $text = $pdf->getText();

            // Filter out common noise patterns
            $text = $this->filterPdfNoise($text);

            return $text;
        } catch (\Exception $e) {
            Log::warning('PDF text extraction failed, will try image-based extraction', [
                'error' => $e->getMessage(),
            ]);

            return '';
        }
    }

    /**
     * Filter out T&Cs, legal disclaimers, and marketing noise from PDF text.
     */
    private function filterPdfNoise(string $text): string
    {
        // Split into lines for processing
        $lines = explode("\n", $text);
        $filteredLines = [];
        $skipSection = false;
        $skipPatterns = [];

        // Patterns that indicate start of noise sections (case-insensitive)
        $noiseSectionStarts = [
            '/^terms\s+(and|&)\s+conditions/i',
            '/^important\s+information$/i',
            '/^legal\s+information/i',
            '/^regulatory\s+information/i',
            '/^disclaimer/i',
            '/^privacy\s+(notice|policy|statement)/i',
            '/^data\s+protection/i',
            '/^complaints?\s+procedure/i',
            '/^how\s+to\s+complain/i',
            '/^financial\s+services\s+compensation/i',
            '/^fscs\s+protection/i',
            '/^about\s+this\s+statement$/i',
            '/^understanding\s+your\s+statement/i',
            '/^glossary/i',
            '/^definitions$/i',
            '/^notes$/i',
            '/^appendix/i',
        ];

        // Patterns that indicate end of noise sections (return to useful content)
        $noiseSectionEnds = [
            '/^(your\s+)?(fund|pension|investment|portfolio)\s+(value|summary|details)/i',
            '/^(current|total)\s+(value|balance)/i',
            '/^contributions?/i',
            '/^(fund|investment)\s+(performance|allocation|breakdown)/i',
            '/^charges?\s+(and\s+fees|summary)/i',
            '/^transaction\s+(history|summary)/i',
        ];

        // Lines to always skip (individual line patterns)
        $skipLinePatterns = [
            '/^page\s+\d+\s+(of\s+\d+)?$/i',
            '/^\d+\s*\/\s*\d+$/',  // Page numbers like "1/5"
            '/^(©|copyright)\s+/i',
            '/^all\s+rights\s+reserved/i',
            '/^registered\s+(in|office)/i',
            '/^authorised\s+and\s+regulated/i',
            '/^fca\s+register/i',
            '/^company\s+(registration|number)/i',
            '/^vat\s+(registration|number)/i',
            '/^\s*$/',  // Empty lines (we'll add back reasonable spacing)
            '/^www\./i',
            '/^https?:\/\//i',
            '/^tel(ephone)?:?\s*[\d\s\-\+]+$/i',
            '/^fax:?\s*[\d\s\-\+]+$/i',
            '/^email:?\s*\S+@\S+$/i',
        ];

        $consecutiveEmptyLines = 0;

        foreach ($lines as $line) {
            $trimmedLine = trim($line);

            // Check if we're entering a noise section
            foreach ($noiseSectionStarts as $pattern) {
                if (preg_match($pattern, $trimmedLine)) {
                    $skipSection = true;
                    break;
                }
            }

            // Check if we're exiting a noise section
            if ($skipSection) {
                foreach ($noiseSectionEnds as $pattern) {
                    if (preg_match($pattern, $trimmedLine)) {
                        $skipSection = false;
                        break;
                    }
                }
            }

            // Skip if in a noise section
            if ($skipSection) {
                continue;
            }

            // Check individual line skip patterns
            $shouldSkip = false;
            foreach ($skipLinePatterns as $pattern) {
                if (preg_match($pattern, $trimmedLine)) {
                    $shouldSkip = true;
                    break;
                }
            }

            if ($shouldSkip) {
                // Track empty lines to avoid too many consecutive blank lines
                if (empty($trimmedLine)) {
                    $consecutiveEmptyLines++;
                }

                continue;
            }

            // Add line to output (with max 1 blank line between sections)
            if (empty($trimmedLine)) {
                if ($consecutiveEmptyLines < 1) {
                    $filteredLines[] = '';
                    $consecutiveEmptyLines++;
                }
            } else {
                $filteredLines[] = $trimmedLine;
                $consecutiveEmptyLines = 0;
            }
        }

        $filteredText = implode("\n", $filteredLines);

        // Additional cleanup: remove repeated whitespace
        $filteredText = preg_replace('/[ \t]+/', ' ', $filteredText);
        $filteredText = preg_replace('/\n{3,}/', "\n\n", $filteredText);

        return trim($filteredText);
    }
}
