<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\URL;

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
        return URL::temporarySignedRoute(
            'document-articles.show',
            now()->addMinutes(30),
            ['slug' => $this->slug, 'preview' => 1]
        );
    }
}
