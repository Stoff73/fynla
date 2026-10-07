<?php

declare(strict_types=1);

namespace App\Services\Stores;

use App\Models\AuditLog;
use App\Models\Estate\Gift;
use App\Models\User;
use App\Services\Cache\CacheInvalidationService;
use App\Services\Stores\Exceptions\GiftOwnedByTrustException;
use App\Services\Stores\Exceptions\StoreValidationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * The one write path for a gift: the web gift form (EstateController) and
 * Fyn (create_estate_gift, update_record, delete_record) both come here, so
 * the rules and the trust refusal are written once.
 *
 * A gift written by `TrustObserver` as a trust's settlement is NOT written
 * here: that record follows the trust.
 */
class GiftStore
{
    private const TYPES = 'pet,clt,exempt,small_gift,annual_exemption';

    public function __construct(
        private readonly CacheInvalidationService $cacheInvalidation,
    ) {}

    public function create(array $canonical, User $user, IngestSource $source): Gift
    {
        $canonical['user_id'] = $user->id;
        $this->validate($canonical, partial: false);

        $gift = AuditLog::withContext(
            ['ingest_source' => $source->value],
            fn () => DB::transaction(fn () => Gift::query()->create($canonical)),
        );
        $this->cacheInvalidation->invalidateForUser($user->id);

        return $gift;
    }

    /**
     * The create rules on their own, for a caller that must check a gift
     * before something else happens (Fyn's duplicate check runs before the
     * write, and must not inspect a gift with no recipient).
     *
     * @throws StoreValidationException
     */
    public function validateNew(array $canonical): void
    {
        $this->validate($canonical, partial: false);
    }

    /**
     * @throws GiftOwnedByTrustException when the gift is a trust's settlement
     */
    public function update(int $id, array $canonical, User $user, IngestSource $source): Gift
    {
        $gift = $this->ownGift($id, $user, 'Edit');
        unset($canonical['user_id'], $canonical['trust_id']);
        $this->validate($canonical, partial: true);

        AuditLog::withContext(
            ['ingest_source' => $source->value],
            fn () => DB::transaction(fn () => $gift->update($canonical)),
        );
        $this->cacheInvalidation->invalidateForUser($user->id);

        return $gift->fresh();
    }

    /**
     * @throws GiftOwnedByTrustException when the gift is a trust's settlement
     */
    public function delete(int $id, User $user, IngestSource $source): Gift
    {
        $gift = $this->ownGift($id, $user, 'Delete');

        AuditLog::withContext(
            ['ingest_source' => $source->value],
            fn () => DB::transaction(fn () => $gift->delete()),
        );
        $this->cacheInvalidation->invalidateForUser($user->id);

        return $gift;
    }

    /**
     * W-0528 — a settlement into a trust is the trust's record, not a
     * free-standing gift. The chargeable lifetime transfer written by
     * `TrustObserver` is what withholds the settlor's nil rate band for seven
     * years. Editing or deleting it released or moved that band while the
     * trust still stood, and the next edit to the trust put it straight back.
     * One record, one owner: the trust. Fyn's edit and delete skipped this
     * refusal while it lived in the controller.
     */
    private function ownGift(int $id, User $user, string $verb): Gift
    {
        $gift = Gift::query()->where('id', $id)->where('user_id', $user->id)->firstOrFail();
        if ($gift->trust_id !== null) {
            throw new GiftOwnedByTrustException(
                $verb.' the trust "'.$gift->recipient.'" instead — this record is its settlement, and it follows whatever you change there.'
            );
        }

        return $gift;
    }

    private function validate(array $canonical, bool $partial): void
    {
        $required = $partial ? 'sometimes|' : 'required|';
        $validator = Validator::make($canonical, [
            // Not in the future: a gift is recorded once it has been made.
            'gift_date' => $required.'date|before_or_equal:today',
            'recipient' => $required.'string|max:255',
            // Required on a new gift: the column defaults to "exempt", so a
            // missing type would read as a choice the user never made.
            'gift_type' => $required.'in:'.self::TYPES,
            'gift_value' => $required.'numeric|min:0|max:999999999.99',
            'status' => 'sometimes|in:within_7_years,survived_7_years',
            'taper_relief_applicable' => 'sometimes|boolean',
            'notes' => 'sometimes|nullable|string',
        ]);

        if ($validator->fails()) {
            throw new StoreValidationException($validator->errors()->toArray());
        }
    }
}
