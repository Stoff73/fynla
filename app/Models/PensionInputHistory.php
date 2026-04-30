<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One record per (user, tax_year) capturing the gross pension input amount
 * for that year. Drives the Pension Annual Allowance Carry-Forward strategy
 * (#3) — we look back three tax years and surface unused AA the user could
 * still pension-up at their marginal rate.
 *
 * @property int $id
 * @property int $user_id
 * @property string $tax_year
 * @property float $pension_input_amount
 */
final class PensionInputHistory extends Model
{
    use HasFactory;

    protected $table = 'pension_input_history';

    protected $fillable = [
        'user_id',
        'tax_year',
        'pension_input_amount',
    ];

    protected $casts = [
        'pension_input_amount' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
