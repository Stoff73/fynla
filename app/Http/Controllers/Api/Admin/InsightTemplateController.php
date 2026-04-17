<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Insights\StoreInsightTemplateRequest;
use App\Http\Resources\Insights\InsightTemplateResource;
use App\Models\Insights\InsightArticle;
use App\Models\Insights\InsightTemplate;
use App\Services\Insights\InsightTemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class InsightTemplateController extends Controller
{
    public function __construct(private readonly InsightTemplateService $templates)
    {
        $this->middleware('permission:admin.access');
    }

    public function index(): AnonymousResourceCollection
    {
        return InsightTemplateResource::collection(
            InsightTemplate::orderBy('name')->get()
        );
    }

    public function store(StoreInsightTemplateRequest $request): JsonResponse
    {
        $article = InsightArticle::findOrFail($request->integer('article_id'));

        $template = $this->templates->saveFromArticle(
            $article,
            (string) $request->string('name'),
            $request->input('description'),
            $request->user(),
        );

        return (new InsightTemplateResource($template))
            ->response()
            ->setStatusCode(201);
    }

    public function update(Request $request, InsightTemplate $template): InsightTemplateResource
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:insight_templates,name,'.$template->id],
        ]);

        $updated = $this->templates->rename($template, (string) $request->string('name'));

        return new InsightTemplateResource($updated);
    }

    public function destroy(InsightTemplate $template): JsonResponse
    {
        $this->templates->delete($template);

        return response()->json(['message' => 'Deleted'], 200);
    }
}
