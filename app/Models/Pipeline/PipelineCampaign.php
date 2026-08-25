<?php

declare(strict_types=1);

namespace App\Models\Pipeline;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PipelineCampaign extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected $casts = [
        'active_from' => 'date',
        'active_to' => 'date',
    ];

    public function posts(): HasMany
    {
        return $this->hasMany(PipelinePost::class);
    }

    public function isActive(): bool
    {
        $today = today();
        if ($this->active_from && $today->lt($this->active_from)) {
            return false;
        }
        if ($this->active_to && $today->gt($this->active_to)) {
            return false;
        }

        return true;
    }
}
