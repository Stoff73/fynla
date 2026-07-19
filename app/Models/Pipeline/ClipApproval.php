<?php

declare(strict_types=1);

namespace App\Models\Pipeline;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClipApproval extends Model
{
    protected $table = 'pipeline_clip_approvals';

    protected $guarded = ['id'];

    protected $casts = [
        'clip_index' => 'integer',
        'token_expires_at' => 'datetime',
        'scheduled_at' => 'datetime',
        'approved_at' => 'datetime',
        'approved_via_email' => 'boolean',
    ];

    public function pipelineArticle(): BelongsTo
    {
        return $this->belongsTo(PipelineArticle::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function scopePending(Builder $q): Builder
    {
        return $q->where('status', 'pending');
    }

    public function scopeApprovedOrAuto(Builder $q): Builder
    {
        return $q->whereIn('status', ['approved', 'auto_approved']);
    }

    /** Auto-approve fires when now() >= scheduled_at - N minutes AND still pending. */
    public function isPastAutoApproveDeadline(int $minutesBefore): bool
    {
        return $this->status === 'pending'
            && $this->scheduled_at->subMinutes($minutesBefore)->isPast();
    }
}
