# Feature Specification: Cross-Cutting Features - Document Upload with AI Extraction

## Status: Live

## Executive Summary

The Document Upload with AI Extraction feature enables users to upload financial documents (pension statements, insurance policies, bank statements, investment reports) and have data automatically extracted using Claude AI. Extracted data is presented for user review and confirmation before being saved to the appropriate records.

### Elevator Pitch

Upload your financial documents and let AI extract the key details for you, turning tedious data entry into a simple review and confirm process.

### Problem Statement

Manual data entry is time-consuming, error-prone, and often causes users to abandon comprehensive financial tracking. Financial documents contain all the necessary information but users must manually transcribe it, leading to errors and incomplete records.

### Target Audience

- Primary: Users with PDF or image copies of financial statements
- Secondary: Users with pension or insurance documents to record
- Tertiary: Users preferring automated data entry over manual

### Unique Selling Proposition

AI-powered extraction using Claude that understands UK financial document formats, presents extracted data with confidence scoring, and allows user correction before saving.

### Success Metrics

| Metric | Target | Measurement Method |
|--------|--------|-------------------|
| Upload feature usage | 25% of records added via upload | Feature tracking |
| Extraction accuracy | 85% of fields extracted correctly | User correction tracking |
| User acceptance rate | 90% of extractions accepted | Confirmation tracking |
| Time savings | 70% faster than manual entry | User surveys |

---

## User Personas

### Persona 1: Sarah - Document Organised User

**Demographics**: 45-year-old with well-organised document files

**Goals**:
- Upload pension statements
- Avoid manual data entry
- Quickly populate records

**Pain Points**:
- Manual entry tedious
- Easy to make errors
- Has many documents to process

**Success Criteria**: Uploads documents, reviews extraction, confirms accurate.

### Persona 2: James - Photo Capture User

**Demographics**: 35-year-old who photographs documents on phone

**Goals**:
- Upload photos of documents
- Extract key details
- Build records quickly

**Pain Points**:
- Document photos not always perfect
- Does not want to retype everything
- Wants mobile-friendly process

**Success Criteria**: Photo uploads work, extraction handles imperfect images.

### Persona 3: Emma - Excel Statement User

**Demographics**: 40-year-old with investment statements in Excel

**Goals**:
- Upload Excel spreadsheets
- Extract holding details
- Avoid row-by-row entry

**Pain Points**:
- Many rows of data
- Manual entry impractical
- Wants bulk import

**Success Criteria**: Excel uploads extract multiple holdings efficiently.

---

## User Stories

### US-01: Upload PDF Document

**As a** user with PDF financial document,
**I want to** upload it for extraction,
**So that** I do not enter data manually.

**Acceptance Criteria**:
- Given I am adding a record
- When I click "Upload Document" and select PDF
- Then document is processed for extraction

### US-02: Upload Image Document

**As a** user with photographed document,
**I want to** upload the image,
**So that** data is extracted.

**Acceptance Criteria**:
- Given I have document photo
- When I upload PNG or JPG
- Then image is processed for extraction

### US-03: Upload Excel Spreadsheet

**As a** user with Excel statement,
**I want to** upload the spreadsheet,
**So that** data is extracted.

**Acceptance Criteria**:
- Given I have Excel file
- When I upload XLSX, XLS, or CSV
- Then spreadsheet is parsed for data

### US-04: Review Extracted Data

**As a** user after upload,
**I want to** review extracted data,
**So that I** can correct any errors.

**Acceptance Criteria**:
- Given document has been processed
- When extraction completes
- Then I see extracted fields for review

**Review Display**:
- Field name
- Extracted value
- Confidence score (if available)
- Edit capability

### US-05: Correct Extraction Errors

**As a** user reviewing extraction,
**I want to** correct any errors,
**So that** data is accurate.

**Acceptance Criteria**:
- Given I see extracted data
- When I edit a field
- Then the corrected value is used

### US-06: Confirm and Save

**As a** user after review,
**I want to** confirm and save,
**So that** record is created.

**Acceptance Criteria**:
- Given I have reviewed extraction
- When I click "Save" or "Confirm"
- Then record is created with data

### US-07: Cancel and Enter Manually

**As a** user unhappy with extraction,
**I want to** cancel and enter manually,
**So that I** have control.

**Acceptance Criteria**:
- Given extraction is unsatisfactory
- When I click "Cancel" or "Enter Manually"
- Then I get standard form

### US-08: Extract Pension Statement Data

**As a** user uploading pension statement,
**I want** pension-specific extraction,
**So that** pension fields are populated.

**Extracted Fields**:
- Provider name
- Scheme name
- Current fund value
- Contribution amounts
- Retirement age
- Pension type

### US-09: Extract Insurance Policy Data

**As a** user uploading insurance document,
**I want** policy details extracted,
**So that** policy fields are populated.

**Extracted Fields**:
- Provider name
- Policy type
- Sum assured
- Premium amount
- Policy dates
- Reference number

### US-10: Extract Bank Statement Data

**As a** user uploading bank statement,
**I want** account details extracted,
**So that** savings account is created.

**Extracted Fields**:
- Bank name
- Account type
- Current balance
- Interest rate

### US-11: Extract Investment Statement Data

**As a** user uploading investment statement,
**I want** holdings extracted,
**So that** investments are recorded.

**Extracted Fields**:
- Provider name
- Account type
- Holdings (multiple)
- Values
- Performance data

---

## Feature Details

### Supported File Formats

| Format | Extension | Max Size | Processing |
|--------|-----------|----------|------------|
| PDF | .pdf | 10MB | AI extraction |
| PNG Image | .png | 10MB | AI extraction (with compression) |
| JPEG Image | .jpg, .jpeg | 10MB | AI extraction (with compression) |
| WebP Image | .webp | 10MB | AI extraction (with compression) |
| Excel | .xlsx | 5MB | PhpSpreadsheet parsing |
| Excel (legacy) | .xls | 5MB | PhpSpreadsheet parsing |
| CSV | .csv | 5MB | PHP parsing |

### Image Processing

**Compression**:
- Large images automatically compressed
- Maintains readability while reducing size
- Improves processing speed
- Handled by ImageResizeService

### AI Extraction Process

**Technology**: Claude Sonnet 4.5 via API

**Process**:
1. Document uploaded to server
2. File validated (type, size)
3. Image compressed if needed
4. Content sent to Claude API
5. AI extracts relevant fields
6. Results returned with confidence
7. User reviews and confirms

### Field Mappers

**Purpose**: Translate AI extraction to database fields

**Available Mappers**:
- DCPensionMapper
- DBPensionMapper
- LifeInsuranceMapper
- InvestmentAccountMapper

**Mapper Function**:
```
AI Output: { "provider": "Aviva", "fund_value": "123456.78" }
    |
    v
Mapper translates to database fields
    |
    v
Database: { provider_name: "Aviva", current_fund_value: 123456.78 }
```

### Excel Parsing

**ExcelParserService**:
- Uses PhpSpreadsheet library
- Handles XLSX, XLS, CSV formats
- Extracts tabular data
- Particularly useful for holdings lists

### Confidence Scoring

**Purpose**: Indicate extraction certainty

**Score Levels**:
| Score | Meaning | Display |
|-------|---------|---------|
| High | Very confident | Green indicator |
| Medium | Fairly confident | Amber indicator |
| Low | Uncertain | Red indicator, review suggested |

### Extraction Locations

**Protection Module**:
- Life insurance policies
- Critical illness policies
- Income protection policies

**Savings Module**:
- Bank statements
- Savings account details

**Investment Module**:
- Investment statements
- Holdings lists

**Retirement Module**:
- DC pension statements
- DB pension statements

---

## User Flows

### Flow 1: Upload Pension Statement

```
Retirement Dashboard
    |
    v
Click "Add Pension" or "Upload Document"
    |
    v
Select pension statement PDF
    |
    v
Upload progress shown
    |
    v
AI processing indicator
    |
    v
Review Extracted Data:
    |
    +--> Provider: Scottish Widows
    +--> Fund Value: GBP 125,000
    +--> Retirement Age: 65
    +--> Contribution: 5%
    |
    v
Correct any errors
    |
    v
Click "Save"
    |
    v
Pension record created
```

### Flow 2: Upload Investment Excel

```
Investment Dashboard
    |
    v
Click "Upload Document"
    |
    v
Select holdings spreadsheet (.xlsx)
    |
    v
Excel parsed
    |
    v
Review Holdings List:
    |
    +--> Fund A: GBP 15,000
    +--> Fund B: GBP 12,000
    +--> Fund C: GBP 8,000
    |
    v
Confirm each holding
    |
    v
Click "Save All"
    |
    v
Holdings created
```

### Flow 3: Upload with Errors

```
Upload document
    |
    v
AI processes
    |
    v
Some fields extracted, some missing
    |
    v
Review shows:
    |
    +--> Provider: "AVIVA" (High confidence)
    +--> Value: "[Not found]" (Manual entry needed)
    |
    v
User enters missing value
    |
    v
Click "Save"
    |
    v
Record created with combined data
```

---

## Edge Cases

### EC-01: Unsupported File Type

**Scenario**: User uploads .doc or other unsupported format.
**Expected Behaviour**: Clear error message listing supported formats.

### EC-02: File Too Large

**Scenario**: PDF exceeds 10MB limit.
**Expected Behaviour**: Error message with size limit. Suggest compression.

### EC-03: Unreadable Document

**Scenario**: PDF is scanned poorly, text unreadable.
**Expected Behaviour**: Extraction may fail or be incomplete. Allow manual entry fallback.

### EC-04: Password-Protected PDF

**Scenario**: PDF has password protection.
**Expected Behaviour**: Error explaining PDF is protected. Cannot process.

### EC-05: Multi-Page Document

**Scenario**: Statement is 10 pages long.
**Expected Behaviour**: Process all pages. Extract relevant data throughout.

### EC-06: Wrong Document Type

**Scenario**: User uploads pension statement to life insurance upload.
**Expected Behaviour**: AI may still extract. User reviews and determines if useful.

### EC-07: Foreign Language Document

**Scenario**: Document is not in English.
**Expected Behaviour**: Extraction may fail or be poor. Manual entry recommended.

### EC-08: No Relevant Data Found

**Scenario**: Document does not contain expected financial data.
**Expected Behaviour**: Message indicating no data extracted. Redirect to manual entry.

---

## Acceptance Criteria Summary

| ID | Criterion | Testable |
|----|-----------|----------|
| AC-01 | PDF upload and extraction works | Yes |
| AC-02 | PNG/JPG upload and extraction works | Yes |
| AC-03 | Excel upload and parsing works | Yes |
| AC-04 | Extracted data displays for review | Yes |
| AC-05 | Users can edit extracted values | Yes |
| AC-06 | Confirm saves to correct record type | Yes |
| AC-07 | Cancel allows manual entry | Yes |
| AC-08 | Pension data extracts correctly | Yes |
| AC-09 | Insurance data extracts correctly | Yes |
| AC-10 | File validation prevents bad uploads | Yes |

---

## Dependencies

### Upstream Dependencies

- Claude API access (Anthropic)
- PhpSpreadsheet library
- File storage capability
- Image processing library

### Downstream Dependencies

- All record creation (pensions, policies, accounts)
- Form pre-population

---

## Technical Constraints

1. **API Costs**: Claude API calls have cost implications
2. **Processing Time**: Large documents may take several seconds
3. **Image Size**: Large images need compression for API limits
4. **Accuracy**: AI extraction is not 100% accurate
5. **Privacy**: Documents processed via external API

---

## Non-Functional Requirements

### Performance

- Upload: Immediate feedback
- Processing: Under 15 seconds typical
- Large files: Under 30 seconds

### Security

- Documents not stored long-term (processed and deleted)
- API calls over HTTPS
- No document data in logs

### Reliability

- Graceful fallback to manual entry
- Clear error messages
- Retry capability

### Privacy

- User informed of external processing
- Minimal data retention
- GDPR compliance

---

## UX Considerations

1. **Upload Progress**: Clear progress indication
2. **Processing Feedback**: Show AI is working
3. **Confidence Display**: Visual confidence indicators
4. **Easy Correction**: Inline editing of extracted values
5. **Fallback Path**: Clear path to manual entry
6. **Success Confirmation**: Clear when extraction successful
7. **Format Guidance**: Show supported formats
8. **Size Guidance**: Indicate file size limits
