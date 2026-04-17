<?php

declare(strict_types=1);

namespace App\Models\Insights;

use App\Models\User;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InsightTemplate extends Model
{
    use Auditable;
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'body_blocks',
        'created_by',
    ];

    protected $casts = [
        'body_blocks' => 'array',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function articles(): HasMany
    {
        return $this->hasMany(InsightArticle::class, 'template_id');
    }
}
