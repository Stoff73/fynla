<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Insights\InsightArticle;
use App\Services\Insights\InsightSeoService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class InsightsSeoMetaInjector
{
    public function __construct(private readonly InsightSeoService $seo) {}

    public function handle(Request $request, Closure $next): Response
    {
        $slug = $request->route('slug') ?? $this->extractSlug($request->path());

        if (! $slug) {
            return $next($request);
        }

        $article = InsightArticle::where('slug', $slug)->published()->first();
        if (! $article || $article->is_bespoke) {
            return $next($request);
        }

        $meta = $this->seo->metaTags($article);
        $jsonLd = $this->seo->jsonLd($article);

        $rendered = $this->renderMeta($meta, $jsonLd);

        View::composer('app', function ($view) use ($rendered): void {
            // startPush with a non-empty content argument immediately extends the
            // named stack without opening an output buffer — no paired stopPush
            // needed, and no chance of an "end without start" error.
            $view->getFactory()->startPush('head', $rendered);
        });

        return $next($request);
    }

    private function renderMeta(array $meta, array $jsonLd): string
    {
        $html = '';
        $html .= '<title>'.e($meta['title']).'</title>'.PHP_EOL;
        $html .= '<meta name="description" content="'.e($meta['description']).'">'.PHP_EOL;
        $html .= '<link rel="canonical" href="'.e($meta['canonical']).'">'.PHP_EOL;
        foreach ($meta['og'] as $key => $value) {
            if ($value) {
                $html .= '<meta property="og:'.$key.'" content="'.e($value).'">'.PHP_EOL;
            }
        }
        foreach ($meta['twitter'] as $key => $value) {
            if ($value) {
                $html .= '<meta name="twitter:'.$key.'" content="'.e($value).'">'.PHP_EOL;
            }
        }
        $html .= '<script type="application/ld+json">'.json_encode($jsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE).'</script>'.PHP_EOL;

        return $html;
    }

    private function extractSlug(string $path): ?string
    {
        if (preg_match('#^insights/([a-z0-9-]+)$#', trim($path, '/'), $m)) {
            return $m[1];
        }

        return null;
    }
}
