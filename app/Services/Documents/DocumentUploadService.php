<?php

declare(strict_types=1);

namespace App\Services\Documents;

use App\Models\Document;
use App\Models\DocumentExtractionLog;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

class DocumentUploadService
{
    /**
     * Allowed MIME types for document uploads.
     */
    private const ALLOWED_MIME_TYPES = [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'image/webp',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-excel',
        'text/csv',
        'application/csv',
    ];

    /**
     * Maximum file size in bytes (20MB).
     */
    private const MAX_FILE_SIZE = 20 * 1024 * 1024;

    /**
     * Upload a document and create a database record.
     */
    public function upload(
        UploadedFile $file,
        User $user,
        ?string $expectedType = null
    ): Document {
        // Validate file
        $this->validateFile($file);

        // Generate secure filename using UUID
        $extension = $this->getExtensionFromMime($file->getMimeType()) ?: $file->getClientOriginalExtension();
        $storedFilename = Str::uuid()->toString().'.'.$extension;

        // Store in user-specific directory
        $path = "documents/{$user->id}/{$storedFilename}";

        // Use local disk for document storage
        $disk = 'local';

        // Store the file
        Storage::disk($disk)->put($path, file_get_contents($file->getRealPath()));

        // Create document record
        $document = Document::create([
            'user_id' => $user->id,
            'original_filename' => $file->getClientOriginalName(),
            'stored_filename' => $storedFilename,
            'disk' => $disk,
            'path' => $path,
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'document_type' => $expectedType ?? Document::TYPE_UNKNOWN,
            'status' => Document::STATUS_UPLOADED,
        ]);

        // Log the upload
        DocumentExtractionLog::log(
            $document,
            $user,
            DocumentExtractionLog::ACTION_UPLOADED,
            [
                'original_filename' => $file->getClientOriginalName(),
                'file_size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
            ]
        );

        return $document;
    }

    /**
     * Get file contents for a document.
     */
    public function getFileContents(Document $document): string
    {
        return Storage::disk($document->disk)->get($document->path);
    }

    /**
     * Get file contents as base64.
     */
    public function getBase64(Document $document): string
    {
        $contents = $this->getFileContents($document);

        return base64_encode($contents);
    }

    /**
     * Delete a document and its file.
     */
    public function delete(Document $document, User $user): bool
    {
        // Delete the actual file
        Storage::disk($document->disk)->delete($document->path);

        // Log the deletion
        DocumentExtractionLog::log(
            $document,
            $user,
            DocumentExtractionLog::ACTION_DELETED
        );

        // Soft delete the record
        return $document->delete();
    }

    /**
     * Check if a file exists for a document.
     */
    public function fileExists(Document $document): bool
    {
        return Storage::disk($document->disk)->exists($document->path);
    }

    /**
     * Get the temporary URL for a document (for S3).
     */
    public function getTemporaryUrl(Document $document, int $minutes = 60): ?string
    {
        if ($document->disk !== 's3') {
            return null;
        }

        return Storage::disk($document->disk)
            ->temporaryUrl($document->path, now()->addMinutes($minutes));
    }

    /**
     * Validate the uploaded file.
     *
     * @throws InvalidArgumentException
     */
    private function validateFile(UploadedFile $file): void
    {
        // Check MIME type
        if (! in_array($file->getMimeType(), self::ALLOWED_MIME_TYPES, true)) {
            throw new InvalidArgumentException(
                'Invalid file type. Allowed types: PDF, JPEG, PNG, WebP, Excel, CSV'
            );
        }

        // Check file size
        if ($file->getSize() > self::MAX_FILE_SIZE) {
            throw new InvalidArgumentException(
                'File too large. Maximum size is 20MB'
            );
        }

        // Check if file is readable
        if (! $file->isReadable()) {
            throw new InvalidArgumentException(
                'Unable to read the uploaded file'
            );
        }
    }

    /**
     * Get file extension from MIME type.
     */
    private function getExtensionFromMime(string $mimeType): string
    {
        return match ($mimeType) {
            'application/pdf' => 'pdf',
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
            'application/vnd.ms-excel' => 'xls',
            'text/csv', 'application/csv' => 'csv',
            default => 'bin',
        };
    }

    /**
     * Get allowed MIME types.
     */
    public function getAllowedMimeTypes(): array
    {
        return self::ALLOWED_MIME_TYPES;
    }

    /**
     * Get max file size in bytes.
     */
    public function getMaxFileSize(): int
    {
        return self::MAX_FILE_SIZE;
    }

    /**
     * Get max file size in MB.
     */
    public function getMaxFileSizeMB(): int
    {
        return self::MAX_FILE_SIZE / (1024 * 1024);
    }
}
