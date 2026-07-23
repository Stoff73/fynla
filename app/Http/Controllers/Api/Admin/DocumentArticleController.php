<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DocumentArticleImportRequest;
use App\Http\Requests\Admin\DocumentArticleUpdateRequest;
use App\Models\DocumentArticle;
use App\Models\Pipeline\PipelineArticle;
use App\Services\Documents\DocumentArticleImporter;
use App\Services\Documents\StockImageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
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

    /**
     * The social video clips generated for this article by the marketing
     * pipeline (if any), with signed preview URLs and the pipeline status.
     */
    public function socialClips(DocumentArticle $document): JsonResponse
    {
        $pipeline = PipelineArticle::where('document_article_id', $document->id)->first();

        if ($pipeline === null) {
            return response()->json(['data' => ['status' => null, 'clips' => []]]);
        }

        $ttlDays = (int) config('pipeline.video.signed_url_ttl_days', 30);
        $clips = collect((array) $pipeline->clip_paths)->values()->map(function ($path, $i) use ($document, $ttlDays) {
            return [
                'index' => $i + 1,
                'filename' => basename((string) $path),
                'url' => URL::temporarySignedRoute(
                    'pipeline.clip.download',
                    now()->addDays($ttlDays),
                    ['slug' => $document->slug, 'filename' => basename((string) $path)],
                ),
            ];
        })->all();

        return response()->json(['data' => [
            'pipeline_article_id' => $pipeline->id,
            'status' => $pipeline->status,
            'clips' => $clips,
            'clips_generated_at' => optional($pipeline->clips_generated_at)->toIso8601String(),
        ]]);
    }

    /**
     * Search Pexels for a relevant stock photo and set it as the article cover.
     * Defaults the search to the article title; returns the stored path.
     */
    public function stockCover(Request $request, DocumentArticle $document, StockImageService $stock): JsonResponse
    {
        $query = trim((string) $request->input('query', '')) ?: (string) $document->title;
        $result = $stock->searchAndStore($query, $document);

        if ($result === null) {
            $message = $stock->isConfigured()
                ? 'No stock photo found for that search — try different keywords.'
                : 'Stock photo search is not configured (missing Pexels API key).';

            return response()->json(['message' => $message], 422);
        }

        return response()->json(['path' => $result['path'], 'photographer' => $result['photographer']]);
    }
}
