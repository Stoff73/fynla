<?php

declare(strict_types=1);

namespace Tests\Unit\Resources;

use App\Http\Resources\MortgageResource;
use App\Models\Mortgage;
use Illuminate\Http\Request;
use Tests\TestCase;

class MortgageResourceTest extends TestCase
{
    public function test_it_returns_joint_borrower_details_for_an_existing_joint_mortgage(): void
    {
        $mortgage = new Mortgage([
            'ownership_type' => 'joint',
            'ownership_percentage' => 50.00,
            'joint_owner_id' => 202,
            'joint_owner_name' => 'Alex Smith',
        ]);

        $data = (new MortgageResource($mortgage))->toArray(Request::create('/'));

        expect($data['joint_owner_id'])->toBe(202)
            ->and($data['joint_owner_name'])->toBe('Alex Smith');
    }

    /**
     * W-0351. The table holds TWO pairs for a mixed rate — the RATES
     * (`fixed_interest_rate`, `variable_interest_rate`) and the SPLIT
     * (`fixed_rate_percentage`, `variable_rate_percentage`) — and this resource served
     * only the rates.
     *
     * `PropertyDetailInline.vue:319,323` gates each row on the SPLIT and prints it as
     * the label, so gating on a key the payload never carried made `undefined` the
     * gate: both rows were STRUCTURALLY unreachable, and a user who had just saved a
     * 60/40 split could not see it anywhere.
     */
    public function test_it_serialises_the_rate_split_a_mixed_mortgage_detail_view_gates_on(): void
    {
        $mortgage = new Mortgage([
            'rate_type' => 'mixed',
            'fixed_rate_percentage' => 60.00,
            'variable_rate_percentage' => 40.00,
            'fixed_interest_rate' => 12.0000,
            'variable_interest_rate' => 14.7500,
        ]);

        // `resolve()`, not `toArray()`: `when()` leaves a `MissingValue` under the key
        // and only `resolve()` strips it, which is what the response path actually
        // calls. A `toArray()` assertion would pass on a withheld field.
        $data = (new MortgageResource($mortgage))->resolve(Request::create('/'));

        // Both halves, because the view needs the split for the label AND the rate for
        // the value — serving one without the other is the defect.
        expect($data)->toHaveKey('fixed_rate_percentage')
            ->and($data)->toHaveKey('variable_rate_percentage')
            ->and((float) $data['fixed_rate_percentage'])->toBe(60.0)
            ->and((float) $data['variable_rate_percentage'])->toBe(40.0)
            ->and((float) $data['fixed_interest_rate'])->toBe(12.0)
            ->and((float) $data['variable_interest_rate'])->toBe(14.75);
    }

    /**
     * The split is gated on `rate_type`, not `mortgage_type` — the pair beside it in
     * the resource splits repayment against interest-only, which is a different
     * question about the same mortgage answered by a different column. A fixed-rate
     * mortgage has no split to serve.
     */
    public function test_it_withholds_the_rate_split_from_a_mortgage_that_is_not_mixed_rate(): void
    {
        $mortgage = new Mortgage([
            'rate_type' => 'fixed',
            'mortgage_type' => 'mixed',
            'fixed_rate_percentage' => 60.00,
            'variable_rate_percentage' => 40.00,
        ]);

        $data = (new MortgageResource($mortgage))->resolve(Request::create('/'));

        expect($data)->not->toHaveKey('fixed_rate_percentage')
            ->and($data)->not->toHaveKey('variable_rate_percentage');
    }
}
