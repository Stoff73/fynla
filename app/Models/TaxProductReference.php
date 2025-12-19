<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Tax product reference data model.
 *
 * Stores static UK tax treatment information for investment and savings products.
 * Used to display tax status information and for future tax calculations.
 *
 * @property int $id
 * @property string $product_category 'investment' or 'savings'
 * @property string $product_type Account type (isa, gia, cash_isa, etc.)
 * @property string $tax_aspect Tax aspect (income_tax, cgt, iht, etc.)
 * @property string $title Display title
 * @property string $summary Bullet point summary
 * @property string $status Tax status (exempt, taxable, deferred, relief, limit)
 * @property string|null $status_icon Icon identifier
 * @property int $display_order Order for display
 * @property bool $is_active Whether this record is active
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class TaxProductReference extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'tax_product_reference';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'product_category',
        'product_type',
        'tax_aspect',
        'title',
        'summary',
        'status',
        'status_icon',
        'display_order',
        'is_active',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'display_order' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Tax status constants.
     */
    public const STATUS_EXEMPT = 'exempt';

    public const STATUS_TAXABLE = 'taxable';

    public const STATUS_DEFERRED = 'deferred';

    public const STATUS_RELIEF = 'relief';

    public const STATUS_LIMIT = 'limit';

    /**
     * Product category constants.
     */
    public const CATEGORY_INVESTMENT = 'investment';

    public const CATEGORY_SAVINGS = 'savings';

    /**
     * Get tax info for a specific product type within a category.
     *
     * @param  string  $category  Product category ('investment' or 'savings')
     * @param  string  $productType  Product type (e.g., 'isa', 'gia', 'cash_isa')
     * @return Collection<int, self>
     */
    public static function getForProductType(string $category, string $productType): Collection
    {
        return static::where('product_category', $category)
            ->where('product_type', $productType)
            ->where('is_active', true)
            ->orderBy('display_order')
            ->get();
    }

    /**
     * Get all tax info for investments.
     *
     * @return Collection<int, self>
     */
    public static function getAllInvestmentTaxInfo(): Collection
    {
        return static::where('product_category', self::CATEGORY_INVESTMENT)
            ->where('is_active', true)
            ->orderBy('product_type')
            ->orderBy('display_order')
            ->get();
    }

    /**
     * Get all tax info for savings.
     *
     * @return Collection<int, self>
     */
    public static function getAllSavingsTaxInfo(): Collection
    {
        return static::where('product_category', self::CATEGORY_SAVINGS)
            ->where('is_active', true)
            ->orderBy('product_type')
            ->orderBy('display_order')
            ->get();
    }

    /**
     * Get unique product types for a category.
     *
     * @param  string  $category  Product category
     * @return array<int, string>
     */
    public static function getProductTypes(string $category): array
    {
        return static::where('product_category', $category)
            ->where('is_active', true)
            ->distinct()
            ->pluck('product_type')
            ->toArray();
    }

    /**
     * Check if a product type has any tax-exempt aspects.
     *
     * @param  string  $category  Product category
     * @param  string  $productType  Product type
     */
    public static function hasTaxExemptAspects(string $category, string $productType): bool
    {
        return static::where('product_category', $category)
            ->where('product_type', $productType)
            ->where('status', self::STATUS_EXEMPT)
            ->where('is_active', true)
            ->exists();
    }

    /**
     * Get a summary of tax status for a product type.
     * Useful for dashboard displays showing overall tax efficiency.
     *
     * @param  string  $category  Product category
     * @param  string  $productType  Product type
     * @return array{exempt: int, taxable: int, deferred: int, relief: int}
     */
    public static function getTaxStatusSummary(string $category, string $productType): array
    {
        $items = static::getForProductType($category, $productType);

        return [
            'exempt' => $items->where('status', self::STATUS_EXEMPT)->count(),
            'taxable' => $items->where('status', self::STATUS_TAXABLE)->count(),
            'deferred' => $items->where('status', self::STATUS_DEFERRED)->count(),
            'relief' => $items->where('status', self::STATUS_RELIEF)->count(),
        ];
    }
}
