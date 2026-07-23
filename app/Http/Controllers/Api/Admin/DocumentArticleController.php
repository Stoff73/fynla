<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DocumentArticleImportRequest;
use App\Http\Requests\Admin\DocumentArticleUpdateRequest;
use App\Models\DocumentArticle;
use App\Services\Documents\DocumentArticleImporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class DocumentArticleController extends Controller
{
    public function index(): JsonResponse
    {
        $articles = DocumentArticle::query()
            ->with('importer:id,first_name,surname,email')
            ->latest('id')
            ->get();

        return response()->json(['data' => $articles]);
    }

    public function show(DocumentArticle $document): JsonResponse
    {
        $document->load('importer:id,first_name,surname,email', 'campaign');

        return response()->json(['data' => $document]);
    }

    public function store(DocumentArticleImportRequest $request, DocumentArticleImporter $importer): JsonResponse
    {
        $article = $importer->import(
            docxFile: $request->file('docx'),
            html: $request->input('html'),
            imageBlobs: $request->file('images', []),
            clientMetadata: [
                'title' => $request->input('metadata.title'),
                'subtitle' => $request->input('metadata.subtitle'),
                'description' => $request->input('metadata.description'),
                'keywords' => $request->input('metadata.keywords'),
                'author_name' => $request->input('metadata.author_name'),
            ],
            importedBy: $request->user(),
        );

        return response()->json(['data' => $article], 201);
    }

    public function update(DocumentArticleUpdateRequest $request, DocumentArticle $document): JsonResponse
    {
        $document->update($request->validated());

        return response()->json(['data' => $document->fresh()]);
    }

    public function destroy(DocumentArticle $document): Response
    {
        Storage::disk('public')->deleteDirectory("document-articles/{$document->id}");
        $document->delete();

        return response()->noContent();
    }

    public function publish(DocumentArticle $document): JsonResponse
    {
        $errors = [];
        if (trim((string) $document->title) === '') {
            $errors['title'] = ['Title is required to publish.'];
        }
        if (trim(strip_tags((string) $document->html_body)) === '') {
            $errors['html_body'] = ['Body cannot be empty when publishing.'];
        }
        if (! empty($errors)) {
            throw ValidationException::withMessages($errors);
        }

        $document->update([
            'status' => 'published',
            'published_at' => now(),
        ]);

        return response()->json(['data' => $document->fresh()]);
    }

    public function unpublish(DocumentArticle $document): JsonResponse
    {
        $document->update([
            'status' => 'draft',
            'published_at' => null,
        ]);

        return response()->json(['data' => $document->fresh()]);
    }

    public function previewUrl(DocumentArticle $document): JsonResponse
    {
        return response()->json(['url' => $document->previewUrl()]);
    }

    /**
     * Upload a cover image for an article that has no suitable embedded image.
     * Stored alongside the article's other assets (cleaned up on destroy).
     * Returns the storage-relative path; the editor sets it as cover_image_path
     * and it persists on the next save.
     */
    public function uploadCoverImage(Request $request, DocumentArticle $document): JsonResponse
    {
        $request->validate([
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $path = $request->file('image')->store("document-articles/{$document->id}", 'public');

        return response()->json(['path' => $path]);
    }
}
