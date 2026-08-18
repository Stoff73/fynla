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
}
