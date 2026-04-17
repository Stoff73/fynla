<?php

declare(strict_types=1);

namespace App\Models\Insights;

use App\Models\User;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class InsightArticle extends Model
{
    use Auditable;
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'slug',
        'title',
        'subtitle',
        'summary',
        'category',
        'tags',
        'hero_image_path',
        'hero_image_card_path',
        'hero_image_thumb_path',
        'body_blocks',
        'template_id',
        'status',
        'is_featured',
        'is_bespoke',
        'bespoke_component',
        'published_at',
        'scheduled_at',
        'author_id',
        'meta_title',
        'meta_description',
        'canonical_url',
    ];

    protected $casts = [
        'tags' => 'array',
        'body_blocks' => 'array',
        'is_featured' => 'boolean',
        'is_bespoke' => 'boolean',
        'published_at' => 'datetime',
        'scheduled_at' => 'datetime',
    ];

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(InsightTemplate::class, 'template_id');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(InsightArticleRevision::class, 'article_id')->orderByDesc('saved_at');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published');
    }

    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true);
    }

    public function scopeBespoke(Builder $query, bool $bespoke = true): Builder
    {
        return $query->where('is_bespoke', $bespoke);
    }

    public function scopeScheduledDue(Builder $query): Builder
    {
        return $query->where('status', 'draft')
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', now());
    }
}
