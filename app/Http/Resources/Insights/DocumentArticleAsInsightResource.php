<?php

declare(strict_types=1);

namespace App\Http\Resources\Insights;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Maps a DocumentArticle (CMS-imported .docx) into the same response shape as
 * InsightArticleResource so it can be rendered by InsightArticlePage.vue
 * without the SPA needing to know which storage table the article came from.
 *
 * Doc articles carry a single sanitised `html_body` instead of structured
 * `body_blocks`; the front end renders `body_html` via v-html when present
 * and falls back to ArticleBlockRenderer otherwise.
 */
class DocumentArticleAsInsightResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $base = rtrim(config('app.url'), '/').'/storage/';
        $coverUrl = $this->cover_image_path ? $base.$this->cover_image_path : null;

        $authors = [];
        if ($this->author_byline) {
            $authors[] = $this->author_byline;
        } elseif ($this->author_name) {
            $authors[] = $this->author_name;
        }

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'summary' => $this->description,
            'category' => null,
            'tags' => [],
            'authors' => $authors,
            'hero_image' => [
                'full' => $coverUrl,
                'card' => $coverUrl,
                'thumb' => $coverUrl,
            ],
            'body_blocks' => [],
            'body_html' => $this->html_body,
            'template_id' => null,
            'status' => $this->status,
            'is_featured' => false,
            'is_bespoke' => false,
            'bespoke_component' => null,
            'published_at' => optional($this->published_at)->toIso8601String(),
            'scheduled_at' => null,
            'meta_title' => $this->title,
            'meta_description' => $this->description,
            'canonical_url' => null,
            'source' => 'document',
            'cta' => $this->buildCta(),
        ];
    }

    /**
     * Bottom call-to-action. A linked campaign overrides the default
     * "register free" prompt with the campaign's own heading/button and
     * points the button at the campaign landing page.
     *
     * @return array{heading:string,subheading:string,button_text:string,url:string,is_campaign:bool}
     */
    private function buildCta(): array
    {
        $campaign = $this->campaign; // BelongsTo; lazy-loaded when not eager-loaded.

        if ($campaign !== null) {
            return [
                'heading' => $campaign->cta_heading ?: 'Ready to plan smarter?',
                'subheading' => $campaign->cta_subheading ?: 'See how much more control you can have over your money.',
                'button_text' => $campaign->cta_button_text ?: 'Get started',
                'url' => $campaign->landing_url,
                'is_campaign' => true,
            ];
        }

        return [
            'heading' => 'Ready to plan smarter?',
            'subheading' => 'Register free and see how much more control you can have over your money.',
            'button_text' => 'Register free',
            'url' => '/register',
            'is_campaign' => false,
        ];
    }
}
