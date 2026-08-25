<?php

declare(strict_types=1);

namespace App\Http\Resources\Insights;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InsightArticleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'summary' => $this->summary,
            'category' => $this->category,
            'tags' => $this->tags ?? [],
            'authors' => $this->authors ?? [],
            'hero_image' => $this->heroImageUrls(),
            'cta' => $this->buildCta(),
            'body_blocks' => $this->body_blocks ?? [],
            'body_html' => null,
            'template_id' => $this->template_id,
            'status' => $this->status,
            'is_featured' => $this->is_featured,
            'is_bespoke' => $this->is_bespoke,
            'bespoke_component' => $this->bespoke_component,
            'published_at' => optional($this->published_at)->toIso8601String(),
            'scheduled_at' => optional($this->scheduled_at)->toIso8601String(),
            'author' => $this->whenLoaded('author', fn () => [
                'id' => $this->author->id,
                'name' => $this->author->name,
            ]),
            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,
            'canonical_url' => $this->canonical_url,
            'pipeline_campaign' => $this->whenLoaded('pipelineCampaign', fn () => $this->pipelineCampaign ? [
                'id' => $this->pipelineCampaign->id,
                'slug' => $this->pipelineCampaign->slug,
                'name' => $this->pipelineCampaign->name,
                'landing_url' => $this->pipelineCampaign->landing_url,
                'cta_heading' => $this->pipelineCampaign->cta_heading,
                'cta_subheading' => $this->pipelineCampaign->cta_subheading,
                'cta_button_text' => $this->pipelineCampaign->cta_button_text,
            ] : null),
        ];
    }

    private function heroImageUrls(): array
    {
        $base = rtrim(config('app.url'), '/').'/storage/';

        return [
            'full' => $this->hero_image_path ? $base.$this->hero_image_path : null,
            'card' => $this->hero_image_card_path ? $base.$this->hero_image_card_path : null,
            'thumb' => $this->hero_image_thumb_path ? $base.$this->hero_image_thumb_path : null,
        ];
    }

    /**
     * Bottom-of-article CTA. A linked campaign overrides the default
     * "register free" prompt with its own heading/button + landing page.
     */
    private function buildCta(): array
    {
        $campaign = $this->relationLoaded('pipelineCampaign') ? $this->pipelineCampaign : null;

        if ($campaign) {
            return [
                'heading' => $campaign->cta_heading ?: 'Ready to plan smarter?',
                'subheading' => $campaign->cta_subheading ?: 'See how much more control you can have over your money.',
                'button_text' => $campaign->cta_button_text ?: 'Get started',
                'url' => $campaign->landing_url ?: '/register',
                'is_campaign' => true,
            ];
        }

        return [
            'heading' => 'Take control of your financial future',
            'subheading' => 'Fynla brings your whole financial picture together with clear, tailored next steps. Get started free.',
            'button_text' => 'Get started for free',
            'url' => '/register',
            'is_campaign' => false,
        ];
    }
}
