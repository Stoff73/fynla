<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientActivity extends Model
{
    use HasFactory;

    protected $fillable = [
        'advisor_client_id', 'advisor_id', 'client_id', 'activity_type',
        'summary', 'details', 'activity_date', 'follow_up_date',
        'follow_up_completed', 'report_type', 'report_sent_date', 'report_acknowledged_date',
    ];

    protected $casts = [
        'activity_date' => 'datetime',
        'follow_up_date' => 'date',
        'follow_up_completed' => 'boolean',
        'report_sent_date' => 'date',
        'report_acknowledged_date' => 'date',
    ];

    public function advisorClient(): BelongsTo
    {
        return $this->belongsTo(AdvisorClient::class);
    }

    public function advisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'advisor_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('activity_type', $type);
    }

    public function scopeReports($query)
    {
        return $query->where('activity_type', 'suitability_report');
    }
}
