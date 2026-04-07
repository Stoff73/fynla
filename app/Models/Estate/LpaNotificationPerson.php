<?php

declare(strict_types=1);

namespace App\Models\Estate;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LpaNotificationPerson extends Model
{
    use Auditable, HasFactory;

    protected $table = 'lpa_notification_persons';

    protected $fillable = [
        'lasting_power_of_attorney_id',
        'full_name',
        'address_line_1',
        'address_line_2',
        'address_city',
        'address_county',
        'address_postcode',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function lastingPowerOfAttorney(): BelongsTo
    {
        return $this->belongsTo(LastingPowerOfAttorney::class, 'lasting_power_of_attorney_id');
    }
}
