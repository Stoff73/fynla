<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The one home for the relationship between a holding's units, price and value.
 *
 * **The decision this class encodes (W-0039): units are the fact, value is derived.**
 *
 * A holding is N units of a security at price P. Where both are known, its value
 * is N x P and nothing else — so `current_value` and `quantity` cannot disagree,
 * because only one of them is ever authoritative at a time.
 *
 * This inverts the previous direction rather than adding a second writable field.
 * `InvestmentController::storeHolding` and `::updateHolding` each carried their
 * own copy of `quantity = current_value / current_price`, so the user's actual
 * fact (351 units of Fundsmith) sat at the END of a derivation chain
 * — allocation % -> value -> quantity — with no way to type it. Ten of the
 * peak_earners persona's holdings were unenterable for that reason.
 *
 * The legacy direction is kept as a FALLBACK, so every holding that works today
 * keeps working: when no quantity is supplied but a value and a price are, the
 * quantity is still back-calculated. Nothing regresses; units simply win when
 * the user gives them.
 *
 * **Supplied beats inherited (W-0121).** "Units win" is a rule about what THIS
 * payload says, never about what the stored row happens to remember. An update
 * that types a value and no units is the caller restating the value — the typed
 * figure stands exactly as entered and the units are back-calculated from it.
 * Resolving the quantity against the stored row before choosing a branch made
 * the inherited unit count silently overwrite a figure the user had just typed,
 * validated and been 200'd on: 19.955704 stored units x a typed £450 turned a
 * typed £45,000 into £8,980.07. In a financial application a silently discarded
 * figure is worse than the derivation bug it replaced.
 *
 * **When one payload supplies both, units still win.** That is deliberate and is
 * why `HoldingForm` computes the value from units rather than letting the two be
 * typed independently: the form only ever sends a value it derived itself, so
 * the two cannot disagree from the UI. An API caller that sends a disagreeing
 * pair is choosing which field is authoritative, and units are the fact.
 *
 * With no usable price the two cannot be related at all, so both are stored as
 * given — 50,000 units of an unpriced fund and a £12,500 valuation are each a
 * fact worth keeping.
 *
 * `/m` has no holding-entry surface — it reads holdings only — so this
 * server-side derivation is the single mechanism web and `/m` share (Rule 20).
 * **Fyn is not yet a reader of it:** `CoordinatingAgent::handleCreateHolding`
 * still derives `current_value` from an allocation percentage inline and never
 * writes a unit count. That is a second mechanism for the same relationship and
 * is raised as W-0122 — it is not fixed here, so do not read this class as
 * covering the Fyn path until that item lands.
 */
final class HoldingValuation
{
    /**
     * Resolve `quantity`, `current_value` and `cost_basis` from whatever is known.
     *
     * Only keys this method actually derives are written back, so a partial
     * update stays partial and never resurrects a field the caller left alone.
     *
     * @param  array<string, mixed>  $data  the validated payload
     * @param  object|null  $existing  the stored holding, on an update
     * @return array<string, mixed>
     */
    public static function reconcile(array $data, ?object $existing = null): array
    {
        $quantity = self::resolve($data, $existing, 'quantity');
        $currentPrice = self::resolve($data, $existing, 'current_price');
        $purchasePrice = self::resolve($data, $existing, 'purchase_price');
        $currentValue = self::resolve($data, $existing, 'current_value');

        $priceIsUsable = $currentPrice !== null && $currentPrice > 0.0;
        $unitsSupplied = self::supplied($data, 'quantity');
        $valueSupplied = self::supplied($data, 'current_value');

        if ($valueSupplied && ! $unitsSupplied) {
            // The caller typed a value and no units, so the typed figure is the
            // fact and is never overwritten by a unit count it never mentioned.
            // The units follow from it (W-0121).
            if ($priceIsUsable) {
                $quantity = round($currentValue / $currentPrice, 6);
                $data['quantity'] = $quantity;
            }
        } elseif ($quantity !== null && $priceIsUsable) {
            // Units win: the value follows from them. This is the branch a
            // price-only edit takes too — the stored units revalue at the new
            // price, which is the whole point of units being the fact.
            $data['current_value'] = round($quantity * $currentPrice, 2);
        } elseif ($quantity === null && $currentValue !== null && $priceIsUsable) {
            // Legacy fallback — no units anywhere, so back-calculate them from
            // the value on record and the price the caller did supply.
            $quantity = round($currentValue / $currentPrice, 6);
            $data['quantity'] = $quantity;
        }

        if ($quantity !== null && $purchasePrice !== null) {
            $data['cost_basis'] = round($quantity * $purchasePrice, 2);
        }

        return $data;
    }

    /**
     * Did THIS payload state a usable figure for the field?
     *
     * The distinction the derivation turns on: a key the caller sent is a fact
     * they asserted, while a value read off the stored record is only what the
     * row remembered. An explicit null clears the field and asserts nothing.
     *
     * @param  array<string, mixed>  $data
     */
    private static function supplied(array $data, string $key): bool
    {
        return array_key_exists($key, $data)
            && $data[$key] !== null
            && $data[$key] !== '';
    }

    /**
     * The value in play for a field: the payload's when the key is present,
     * otherwise the stored record's.
     *
     * @param  array<string, mixed>  $data
     */
    private static function resolve(array $data, ?object $existing, string $key): ?float
    {
        if (array_key_exists($key, $data)) {
            return ($data[$key] === null || $data[$key] === '') ? null : (float) $data[$key];
        }

        $stored = $existing?->{$key} ?? null;

        return $stored === null ? null : (float) $stored;
    }
}
