<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\DocumentArticle;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PublicDocumentArticleController extends Controller
{
    public function show(Request $request, string $slug): View
    {
        $article = DocumentArticle::where('slug', $slug)->firstOrFail();

        if (! $article->isPublished()) {
            if (! $request->hasValidSignature()) {
                abort(Response::HTTP_NOT_FOUND);
            }
        }

        return view('articles.show', ['article' => $article]);
    }
}
