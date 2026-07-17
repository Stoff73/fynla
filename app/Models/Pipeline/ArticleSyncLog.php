<?php

declare(strict_types=1);

namespace App\Models\Pipeline;

use App\Models\Insights\InsightArticle;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArticleSyncLog extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'metadata' => 'array',
        'response_status' => 'integer',
    ];

    public function insightArticle(): BelongsTo
    {
        return $this->belongsTo(InsightArticle::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
