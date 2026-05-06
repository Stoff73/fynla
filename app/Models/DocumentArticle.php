<?php

declare(strict_types=1);

namespace App\Models;

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

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published')->whereNotNull('published_at');
    }

    public function isPublished(): bool
    {
        return $this->status === 'published' && $this->published_at !== null;
    }

    public function previewUrl(): string
    {
        // Drafts surface through the existing /insights SPA pipeline. Admin
        // auth is the access gate (Api\Public\InsightController::show checks
        // for is_admin + preview=true), so no signed URL is needed.
        return rtrim(config('app.url'), '/')."/insights/{$this->slug}?preview=true";
    }
}
