<?php

declare(strict_types=1);

use App\Models\Property;
use App\Models\User;
use App\Services\Property\PropertyService;
use Illuminate\Database\Migrations\Migration;

/**
 * Backfill counterpart to PropertyRentalIncomeObserver (W-0173).
 *
 * `users.annual_rental_income` was written only for the user who recorded the
 * property, and with a gross figure rather than the profit every other surface
 * uses. So a joint buy-to-let left the co-owner's column at 0.00 and the
 * recorder's overstated. Removing the bad write paths fixes every FUTURE write;
 * the rows already stored keep the wrong figure until their property is touched
 * again, which for a household that finished data entry is never.
 *
 * Deliberately narrow: only users who actually hold a buy-to-let record are
 * recomputed. The column is fully derived for them — both write paths computed
 * it from property records — so recomputing cannot lose anything they typed. A
 * user with no buy-to-let is left exactly as they are, because a figure on such
 * an account did not come from a property and this migration has no standing to
 * decide where it did come from.
 */
return new class extends Migration
{
    public function up(): void
    {
        $propertyService = app(PropertyService::class);

        $ownerIds = Property::query()
            ->where('property_type', 'buy_to_let')
            ->get(['user_id', 'joint_owner_id'])
            ->flatMap(fn (Property $property): array => [$property->user_id, $property->joint_owner_id])
            ->filter()
            ->unique()
            ->values();

        foreach ($ownerIds as $ownerId) {
            $user = User::find($ownerId);

            if (! $user) {
                continue;
            }

            $user->update([
                'annual_rental_income' => round($propertyService->annualRentalTaxPosition($user)['total'], 2),
            ]);
        }
    }

    public function down(): void
    {
        // Irreversible by design: the figures this replaced were wrong on both
        // sides of every shared record and are not worth restoring.
    }
};
