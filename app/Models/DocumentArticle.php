<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Pipeline\PipelineCampaign;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentArticle extends Model
{
    use HasFactory;

    protected $fillable = [
        'slug',
        'title',
        'subtitle',
        'description',
        'keywords',
        'author_name',
        'author_byline',
        'cover_image_path',
        'html_body',
        'status',
        'pipeline_campaign_id',
        'published_at',
        'imported_by',
        'original_filename',
        'original_doc_hash',
    ];

    protected $casts = [
        'published_at' => 'datetime',
    ];

    public function importer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by');
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(PipelineCampaign::class, 'pipeline_campaign_id');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published')->whereNotNull('published_at');
    }

    public function isPublished(): bool
    {
        return $this->status === 'published' && $this->published_at !== null;
    }

    /**
     * Approved for publication AND the publish time has arrived. An approver can
     * schedule an article by giving it a future published_at; it stays off the
     * public site until then, with no cron needed.
     */
    public function scopeLive(Builder $query): Builder
    {
        return $query->published()->where('published_at', '<=', now());
    }

    /** Approved but with a publish time still in the future. */
    public function isScheduled(): bool
    {
        return $this->isPublished() && $this->published_at->isFuture();
    }

    public function previewUrl(): string
    {
        // Drafts surface through the existing /insights SPA pipeline. Admin
        // auth is the access gate (Api\Public\InsightController::show checks
        // for is_admin + preview=true), so no signed URL is needed.
        return rtrim(config('app.url'), '/')."/insights/{$this->slug}?preview=true";
    }
}
