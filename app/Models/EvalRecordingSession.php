<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One row per `php artisan eval:record` invocation. Captures the scenario,
 * the seeded eval user, and the start state Fyn was working from. The
 * paired EvalProviderRun rows hold the per-(provider, model) results.
 */
final class EvalRecordingSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'scenario_id',
        'scenario_path',
        'scenario_yaml',
        'eval_user_id',
        'start_state_snapshot',
        'fynla_branch',
        'fynla_sha',
        'status',
        'started_at',
        'completed_at',
        'remedial_report',
        'remedial_report_updated_at',
    ];

    protected $casts = [
        'start_state_snapshot' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'remedial_report_updated_at' => 'datetime',
    ];

    public function evalUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'eval_user_id');
    }

    public function providerRuns(): HasMany
    {
        return $this->hasMany(EvalProviderRun::class);
    }
}
