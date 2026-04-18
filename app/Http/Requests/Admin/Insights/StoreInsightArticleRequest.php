<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Insights;

use App\Models\Insights\InsightArticle;
use App\Services\Insights\BlockValidator;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInsightArticleRequest extends FormRequest
{
    private const ALLOWED_INLINE_TAGS = '<strong><em><u><a><br><span>';

    private const ALLOWED_BLOCK_TAGS = '<p><strong><em><u><a><br><ul><ol><li><span>';

    private const ALLOWED_SPAN_CLASSES = [
        'text-sm',
        'text-lg',
        'text-raspberry-500',
        'text-horizon-500',
        'text-spring-500',
        'text-violet-500',
        'text-neutral-500',
    ];

    public function authorize(): bool
    {
        return (bool) $this->user()?->is_admin;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'summary' => ['required', 'string', 'max:2000'],
            'slug' => ['nullable', 'string', 'alpha_dash', 'max:255'],
            'category' => ['required', 'in:tax,pensions,savings-isa,estate-planning,financial-planning,ai,fintech,developer,international,platform-updates'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:50'],
            'authors' => ['nullable', 'array', 'max:3'],
            'authors.*' => ['string', Rule::in(InsightArticle::ALLOWED_AUTHORS)],
            'hero_image_path' => ['nullable', 'string'],
            'hero_image_card_path' => ['nullable', 'string'],
            'hero_image_thumb_path' => ['nullable', 'string'],
            'body_blocks' => ['nullable', 'array'],
            'template_id' => ['nullable', 'exists:insight_templates,id'],
            'status' => ['nullable', 'in:draft,published,archived'],
            'is_featured' => ['nullable', 'boolean'],
            'scheduled_at' => ['nullable', 'date', 'after:now'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'canonical_url' => ['nullable', 'url'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $blocks = $this->input('body_blocks');
        if (! is_array($blocks)) {
            return;
        }

        $cleaned = array_map(function ($block) {
            if (! is_array($block) || ! isset($block['type'])) {
                return $block;
            }

            $sanitiseString = fn ($v, $tags) => is_string($v) ? $this->sanitiseHtml($v, $tags) : $v;
            $sanitiseArray = fn ($arr, $tags) => is_array($arr)
                ? array_map(fn ($i) => $sanitiseString($i, $tags), $arr)
                : $arr;

            if ($block['type'] === 'paragraph' && isset($block['html'])) {
                $block['html'] = $sanitiseString($block['html'], self::ALLOWED_INLINE_TAGS);
            }
            if ($block['type'] === 'callout' && isset($block['html'])) {
                $block['html'] = $sanitiseString($block['html'], self::ALLOWED_BLOCK_TAGS);
            }
            if ($block['type'] === 'heading' && isset($block['text'])) {
                $block['text'] = $sanitiseString($block['text'], self::ALLOWED_INLINE_TAGS);
            }
            if ($block['type'] === 'pull_quote' && isset($block['text'])) {
                $block['text'] = $sanitiseString($block['text'], self::ALLOWED_INLINE_TAGS);
            }
            if ($block['type'] === 'list' && isset($block['items'])) {
                $block['items'] = $sanitiseArray($block['items'], self::ALLOWED_INLINE_TAGS);
            }
            if ($block['type'] === 'key_takeaways' && isset($block['bullets'])) {
                $block['bullets'] = $sanitiseArray($block['bullets'], self::ALLOWED_INLINE_TAGS);
            }

            return $block;
        }, $blocks);

        $this->merge(['body_blocks' => $cleaned]);
    }

    private function sanitiseHtml(string $html, string $allowedTags): string
    {
        $stripped = strip_tags($html, $allowedTags);

        $stripped = preg_replace('/\s+on\w+\s*=\s*"[^"]*"/i', '', $stripped);
        $stripped = preg_replace('/\s+on\w+\s*=\s*\'[^\']*\'/i', '', $stripped);
        $stripped = preg_replace('/href\s*=\s*"javascript:[^"]*"/i', 'href="#"', $stripped);
        $stripped = preg_replace('/href\s*=\s*\'javascript:[^\']*\'/i', "href='#'", $stripped);

        // Lock <span> attributes to a class on the palette/size allow-list.
        // Anything else (style, id, data-*, onclick, …) is stripped.
        $stripped = preg_replace_callback(
            '/<span\b([^>]*)>/i',
            function ($m) {
                if (preg_match('/class\s*=\s*"([^"]*)"/i', $m[1], $cm)) {
                    $kept = array_values(array_filter(
                        preg_split('/\s+/', trim($cm[1])),
                        fn ($c) => in_array($c, self::ALLOWED_SPAN_CLASSES, true),
                    ));
                    if ($kept !== []) {
                        return '<span class="'.implode(' ', $kept).'">';
                    }
                }

                return '<span>';
            },
            (string) $stripped
        );

        return (string) $stripped;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $blocks = $this->input('body_blocks', []);
            if (! is_array($blocks) || $blocks === []) {
                return;
            }
            foreach ((new BlockValidator)->validate($blocks) as $error) {
                $v->errors()->add('body_blocks', $error);
            }
        });
    }
}
