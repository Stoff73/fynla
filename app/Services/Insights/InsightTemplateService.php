<?php

declare(strict_types=1);

namespace App\Services\Insights;

use App\Models\Insights\InsightArticle;
use App\Models\Insights\InsightTemplate;
use App\Models\User;

class InsightTemplateService
{
    public function saveFromArticle(
        InsightArticle $article,
        string $name,
        ?string $description,
        User $creator,
    ): InsightTemplate {
        return InsightTemplate::create([
            'name' => $name,
            'description' => $description,
            'body_blocks' => $article->body_blocks,
            'created_by' => $creator->id,
        ]);
    }

    public function rename(InsightTemplate $template, string $name): InsightTemplate
    {
        $template->update(['name' => $name]);

        return $template->fresh();
    }

    public function delete(InsightTemplate $template): void
    {
        // nullOnDelete on the FK handles article detachment.
        $template->delete();
    }
}
