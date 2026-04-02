<?php

declare(strict_types=1);

namespace App\Http\Requests\Documents;

use App\Models\Document;
use Illuminate\Foundation\Http\FormRequest;

class UploadDocumentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'document' => [
                'sometimes',
                'file',
                'mimes:pdf,jpeg,jpg,png,webp,xlsx,xls,csv',
                'max:20480', // 20MB
            ],
            'document_type' => [
                'nullable',
                'in:'.implode(',', [
                    Document::TYPE_PENSION_STATEMENT,
                    Document::TYPE_INSURANCE_POLICY,
                    Document::TYPE_INVESTMENT_STATEMENT,
                    Document::TYPE_MORTGAGE_STATEMENT,
                    Document::TYPE_SAVINGS_STATEMENT,
                    Document::TYPE_PROPERTY_DOCUMENT,
                ]),
            ],
        ];
    }

    /**
     * Sanitise the uploaded filename after validation passes.
     */
    protected function passedValidation(): void
    {
        if ($this->hasFile('document')) {
            $file = $this->file('document');
            $originalName = $file->getClientOriginalName();
            // Strip path traversal characters and sanitise
            $sanitised = preg_replace('/[^\w\s\-\.]/', '', pathinfo($originalName, PATHINFO_FILENAME));
            $sanitised = trim($sanitised);
            if (empty($sanitised)) {
                $sanitised = 'document_'.time();
            }
        }
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'document.required' => 'Please select a document to upload.',
            'document.file' => 'The uploaded item must be a file.',
            'document.mimes' => 'Document must be a PDF, image (JPEG, PNG, WebP), or spreadsheet (Excel, CSV).',
            'document.max' => 'Document must be less than 20MB. For large PDFs, try compressing the file or using a PDF with selectable text.',
            'document_type.in' => 'Invalid document type specified.',
        ];
    }
}
