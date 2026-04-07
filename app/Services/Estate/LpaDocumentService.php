<?php

declare(strict_types=1);

namespace App\Services\Estate;

use App\Models\Document;
use App\Models\Estate\LastingPowerOfAttorney;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class LpaDocumentService
{
    /**
     * Upload an LPA document and create an associated LPA record.
     */
    public function uploadLpa(User $user, UploadedFile $file, string $lpaType): LastingPowerOfAttorney
    {
        // Store the document
        $document = $this->storeDocument($user, $file);

        // Create the LPA record linked to the document
        $lpa = LastingPowerOfAttorney::create([
            'user_id' => $user->id,
            'lpa_type' => $lpaType,
            'status' => 'uploaded',
            'source' => 'uploaded',
            'document_id' => $document->id,
        ]);

        return $lpa->load('document');
    }

    /**
     * Link an existing document to an LPA.
     */
    public function linkDocument(LastingPowerOfAttorney $lpa, int $documentId): LastingPowerOfAttorney
    {
        $lpa->update(['document_id' => $documentId]);

        return $lpa->fresh('document');
    }

    /**
     * Store the uploaded file as a Document record.
     */
    private function storeDocument(User $user, UploadedFile $file): Document
    {
        $filename = Str::uuid().'.'.($file->guessExtension() ?? $file->getClientOriginalExtension());
        $path = "documents/{$user->id}/lpa/{$filename}";

        Storage::disk('local')->put($path, $file->getContent());

        return Document::create([
            'user_id' => $user->id,
            'original_filename' => $file->getClientOriginalName(),
            'stored_filename' => $filename,
            'disk' => 'local',
            'path' => $path,
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'document_type' => Document::TYPE_LPA,
            'status' => Document::STATUS_UPLOADED,
        ]);
    }
}
