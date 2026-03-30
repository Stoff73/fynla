<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdvisorClient extends Model
{
    use HasFactory;

    protected $fillable = [
        'advisor_id', 'client_id', 'assigned_date',
        'last_review_date', 'next_review_due', 'review_frequency_months', 'notes',
    ];

    protected $casts = [
        'assigned_date' => 'date',
        'last_review_date' => 'date',
        'next_review_due' => 'date',
        'review_frequency_months' => 'integer',
    ];

    public function advisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'advisor_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(ClientActivity::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeForAdvisor($query, int $advisorId)
    {
        return $query->where('advisor_id', $advisorId);
    }
}
