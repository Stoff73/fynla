<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LifecycleEmailLog extends Model
{
    use HasFactory;

    protected $table = 'lifecycle_email_log';

    protected $fillable = [
        'user_id',
        'campaign',
        'sent_at',
        'clicked_at',
        'action_taken',
        'context',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'clicked_at' => 'datetime',
        'context' => 'array',
    ];

    public $timestamps = false;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
