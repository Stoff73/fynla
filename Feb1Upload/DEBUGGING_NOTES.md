# Document Upload Debugging Notes

## Date: Feb 1, 2026

## Issue
User reports "nothing happens" when trying to upload documents - no errors, no calls to Anthropic API, no failures, no data.

## Investigation Summary

### Phase 1: Root Cause Investigation

#### 1. API Key Check
- **Finding:** `.env` file does NOT contain `ANTHROPIC_API_KEY`
- The `.env` file has 70 lines and ends at VITE_PUSHER_APP_CLUSTER
- User claimed API key is in .env, but it's not present

#### 2. Database Check
- **Finding:** Database tables exist (documents, document_extractions, document_extraction_logs)
- Migrations ran successfully (tables exist in database)

#### 3. Route Check
- **Finding:** All routes are correctly registered:
  - POST `/api/documents/upload` → `DocumentController@upload`
  - Routes have proper middleware (auth:sanctum, throttle)

#### 4. Controller/Service Check
- **Finding:** All services instantiate correctly
- DocumentProcessor can be created without errors

#### 5. Frontend Check
- **Finding:** Components are correctly wired:
  - RetirementReadiness.vue has `showUploadModal` data property
  - DocumentUploadModal is imported and rendered with `v-if="showUploadModal"`
  - Upload button triggers `@click="showUploadModal = true"`

### Potential Issues Identified

1. **Missing API Key** - The `ANTHROPIC_API_KEY` is not in `.env`
   - Required: `ANTHROPIC_API_KEY=sk-ant-api03-xxxxx`

2. **Preview Mode Blocking** - The `v-preview-disabled="'upload'"` directive on the Upload button will block clicks if user is in preview mode

3. **Silent Failures** - If any step fails silently, the modal may appear stuck

## Debugging Code Added

Added console.log statements to trace the flow:

### Frontend (DocumentUploadModal.vue)
- `[DocumentUpload] File selected: {name}, {type}, {size}`
- `[DocumentUpload] startUpload called`
- `[DocumentUpload] Starting upload to API...`
- `[DocumentUpload] Upload progress: {percent}`
- `[DocumentUpload] API response: {result}`
- `[DocumentUpload] Upload error: {error}`

### Frontend (documentService.js)
- `[documentService] upload called: {fileName, documentType}`
- `[documentService] upload progress: {percent}`
- `[documentService] making POST to /documents/upload`
- `[documentService] upload response: {data}`

### Backend (DocumentController.php)
- `[DocumentController] upload called: {has_file, document_type, user_id}`
- `[DocumentController] upload failed: {error, trace}`

### Backend (DocumentProcessor.php)
- `[DocumentProcessor] process called: {file details}`
- `[DocumentProcessor] Step 1: Uploading document`
- `[DocumentProcessor] Document uploaded: {document_id}`
- `[DocumentProcessor] Step 2: Extracting data via AI`
- `[DocumentProcessor] Extraction complete: {extraction_id}`

### Backend (AIExtractionService.php)
- `[AIExtractionService] extract called: {document_id}`
- `[AIExtractionService] Processing document: {media_type}`
- `[AIExtractionService] Calling Claude API: {base64_length}`
- `[AIExtractionService] Claude API response received`

## Testing Instructions

1. **Add API Key to .env:**
   ```
   ANTHROPIC_API_KEY=sk-ant-api03-your-key-here
   ```

2. **Open Browser Console** (F12 → Console tab)

3. **Navigate to Retirement Readiness page**

4. **Click "Upload Statement" button**
   - Check: Does modal open?
   - Console should show any JavaScript errors

5. **Select a PDF file**
   - Console should show: `[DocumentUpload] File selected: filename.pdf, application/pdf, 12345`

6. **Click "Upload & Analyse"**
   - Console should show:
     - `[DocumentUpload] startUpload called`
     - `[documentService] upload called`
     - `[documentService] making POST to /documents/upload`
   - Check Network tab for the actual request

7. **Check Laravel logs:**
   ```bash
   tail -f storage/logs/laravel.log
   ```
   Should see:
   - `[DocumentController] upload called`
   - `[DocumentProcessor] process called`
   - `[AIExtractionService] extract called`
   - `[AIExtractionService] Calling Claude API`

## Next Steps

1. User needs to test and report which console messages appear
2. Check if API key is present
3. Check if user is in preview mode (non-preview users only)
4. Check Network tab for failed requests
5. Check Laravel logs for backend errors

## Files Modified for Debugging

- `resources/js/components/Shared/DocumentUploadModal.vue`
- `resources/js/services/documentService.js`
- `app/Http/Controllers/Api/DocumentController.php`
- `app/Services/Documents/DocumentProcessor.php`
- `app/Services/Documents/AIExtractionService.php`
