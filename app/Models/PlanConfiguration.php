<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlanConfiguration extends Model
{
    protected $fillable = [
        'version',
        'config_data',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'config_data' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * Get the active plan configuration.
     */
    public static function getActive(): ?self
    {
        return static::where('is_active', true)->first();
    }

    /**
     * Activate this configuration and deactivate others.
     */
    public function activate(): void
    {
        static::where('is_active', true)->update(['is_active' => false]);
        $this->update(['is_active' => true]);
    }
}
