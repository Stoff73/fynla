<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * OccupationCode Model
 *
 * Represents ONS Standard Occupational Classification (SOC) 2020 codes.
 * Used for occupation autocomplete and standardisation.
 *
 * @property int $id
 * @property string $soc_code SOC 2020 4-digit unit group code
 * @property string $title Job title or occupation name
 * @property string|null $unit_group SOC 2020 unit group description
 * @property string|null $minor_group SOC 2020 minor group (3-digit)
 * @property string|null $sub_major_group SOC 2020 sub-major group (2-digit)
 * @property string|null $major_group SOC 2020 major group (1-digit)
 * @property bool $is_primary Is this the primary title for the SOC code
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class OccupationCode extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'soc_code',
        'title',
        'unit_group',
        'minor_group',
        'sub_major_group',
        'major_group',
        'is_primary',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_primary' => 'boolean',
    ];

    /**
     * Search for occupations matching the query.
     *
     * @param  string  $query  Search term (minimum 3 characters)
     * @param  int  $limit  Maximum results to return
     */
    public static function search(string $query, int $limit = 10): \Illuminate\Database\Eloquent\Collection
    {
        $query = trim($query);

        if (strlen($query) < 3) {
            return collect();
        }

        // Search using LIKE for partial matches at word boundaries
        // Prioritise exact matches and starts-with matches
        return self::where('title', 'LIKE', $query.'%')
            ->orWhere('title', 'LIKE', '% '.$query.'%')
            ->orderByRaw('CASE WHEN title LIKE ? THEN 0 WHEN title LIKE ? THEN 1 ELSE 2 END', [$query.'%', '% '.$query.'%'])
            ->orderBy('title')
            ->limit($limit)
            ->get(['id', 'title', 'soc_code', 'unit_group']);
    }
}
