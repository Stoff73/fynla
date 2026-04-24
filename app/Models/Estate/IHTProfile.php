<?php

declare(strict_types=1);

namespace App\Models\Estate;

use App\Models\User;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class IHTProfile extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    protected $table = 'iht_profiles';

    protected $fillable = [
        'user_id',
        'marital_status',
        'has_spouse',
        'own_home',
        'home_value',
        'nrb_transferred_from_spouse',
        'rnrb_transferred_from_spouse',
        'charitable_giving_percent',
    ];

    protected $casts = [
        'has_spouse' => 'boolean',
        'own_home' => 'boolean',
        'home_value' => 'float',
        'nrb_transferred_from_spouse' => 'float',
        'rnrb_transferred_from_spouse' => 'float',
        'charitable_giving_percent' => 'float',
    ];

    /**
     * User relationship
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
