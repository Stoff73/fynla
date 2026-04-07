<?php

declare(strict_types=1);

namespace App\Models\Estate;

use App\Models\User;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class WillDocument extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'will_id',
        'mirror_document_id',
        'will_type',
        'status',
        'testator_full_name',
        'testator_address',
        'testator_date_of_birth',
        'testator_occupation',
        'executors',
        'guardians',
        'specific_gifts',
        'residuary_estate',
        'funeral_preference',
        'funeral_wishes_notes',
        'digital_executor_name',
        'digital_assets_instructions',
        'survivorship_days',
        'domicile_confirmed',
        'signed_date',
        'witnesses',
        'generated_at',
        'last_edited_at',
    ];

    protected $casts = [
        'executors' => 'array',
        'guardians' => 'array',
        'specific_gifts' => 'array',
        'residuary_estate' => 'array',
        'witnesses' => 'array',
        'testator_date_of_birth' => 'date',
        'signed_date' => 'date',
        'generated_at' => 'datetime',
        'last_edited_at' => 'datetime',
        'survivorship_days' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function will(): BelongsTo
    {
        return $this->belongsTo(Will::class);
    }

    public function mirrorDocument(): BelongsTo
    {
        return $this->belongsTo(self::class, 'mirror_document_id');
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isComplete(): bool
    {
        return $this->status === 'complete';
    }

    public function isMirror(): bool
    {
        return $this->will_type === 'mirror';
    }

    public function hasMirrorDocument(): bool
    {
        return $this->mirror_document_id !== null;
    }
}
