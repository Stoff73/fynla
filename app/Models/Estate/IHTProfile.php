<?php

declare(strict_types=1);

namespace App\Models\Estate;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class IHTProfile extends Model
{
    use HasFactory, SoftDeletes;

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
        'home_value' => 'decimal:2',
        'nrb_transferred_from_spouse' => 'decimal:2',
        'rnrb_transferred_from_spouse' => 'decimal:2',
        'charitable_giving_percent' => 'decimal:4',
    ];

    /**
     * User relationship
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
