<?php

declare(strict_types=1);

use App\Models\Chattel;
use App\Models\Investment\InvestmentAccount;
use App\Models\Property;
use App\Models\SavingsAccount;
use App\Models\User;
use Database\Seeders\TierConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/**
 * W-0040, the mechanism half: a share the caller STATED is honoured or refused,
 * never quietly rewritten; a share nobody stated is defaulted.
 *
 * The rule is one rule across every asset type on purpose. Before this, savings
 * refused a stated 0 while property, chattels and investments silently rewrote a
 * stated 100 to 50 and returned 201 — so "I own all of it" was stored as "I own
 * half of it". CSJ's ruling: a 100/0 split IS individual ownership.
 */
beforeEach(function () {
    $this->seed(TierConfigurationSeeder::class);

    $this->user = User::factory()->create(['is_preview_user' => false]);
    $this->spouse = User::factory()->create(['is_preview_user' => false]);
    $this->user->update(['spouse_id' => $this->spouse->id]);
    $this->spouse->update(['spouse_id' => $this->user->id]);

    Sanctum::actingAs($this->user);
});

dataset('shares that are not a split', [
    'the whole asset' => [100],
    'none of it' => [0],
]);

describe('a stated share that is not a shared split is refused', function () {
    it('refuses one on a savings account', function (int $share) {
        $this->postJson('/api/savings/accounts', [
            'institution' => 'Nationwide',
            'account_type' => 'current_account',
            'current_balance' => 4500,
            'ownership_type' => 'joint',
            'joint_owner_id' => $this->spouse->id,
            'ownership_percentage' => $share,
        ])->assertStatus(422)->assertJsonValidationErrors('ownership_percentage');

        expect(SavingsAccount::count())->toBe(0);
    })->with('shares that are not a split');

    it('refuses one on a chattel', function (int $share) {
        $this->postJson('/api/chattels', [
            'chattel_type' => 'vehicle',
            'name' => 'BMW X5',
            'current_value' => 42000,
            'ownership_type' => 'joint',
            'joint_owner_id' => $this->spouse->id,
            'ownership_percentage' => $share,
        ])->assertStatus(422)->assertJsonValidationErrors('ownership_percentage');

        expect(Chattel::count())->toBe(0);
    })->with('shares that are not a split');

    it('refuses one on a property', function (int $share) {
        $this->postJson('/api/properties', [
            'property_type' => 'main_residence',
            'address_line_1' => '1 Test Street',
            'city' => 'Manchester',
            'postcode' => 'M1 1AA',
            'current_value' => 400000,
            'ownership_type' => 'tenants_in_common',
            'joint_owner_id' => $this->spouse->id,
            'ownership_percentage' => $share,
        ])->assertStatus(422)->assertJsonValidationErrors('ownership_percentage');

        expect(Property::count())->toBe(0);
    })->with('shares that are not a split');

    it('refuses one on an investment account', function (int $share) {
        $this->postJson('/api/investment/accounts', [
            'account_name' => 'Joint General Investment Account',
            'account_type' => 'gia',
            'current_value' => 95000,
            'ownership_type' => 'joint',
            'joint_owner_id' => $this->spouse->id,
            'ownership_percentage' => $share,
        ])->assertStatus(422)->assertJsonValidationErrors('ownership_percentage');

        expect(InvestmentAccount::count())->toBe(0);
    })->with('shares that are not a split');
});

describe('a share nobody stated is defaulted, not refused', function () {
    it('defaults a savings account the modal sent no share for', function () {
        $this->postJson('/api/savings/accounts', [
            'institution' => 'Nationwide',
            'account_type' => 'current_account',
            'current_balance' => 4500,
            'ownership_type' => 'joint',
            'joint_owner_id' => $this->spouse->id,
        ])->assertCreated();

        expect((float) SavingsAccount::sole()->ownership_percentage)->toBe(50.0);
    });

    it('defaults a chattel the modal sent no share for', function () {
        $this->postJson('/api/chattels', [
            'chattel_type' => 'vehicle',
            'name' => 'BMW X5',
            'current_value' => 42000,
            'ownership_type' => 'joint',
            'joint_owner_id' => $this->spouse->id,
        ])->assertCreated();

        expect((float) Chattel::sole()->ownership_percentage)->toBe(50.0);
    });
});

describe('an uneven share the caller did state survives exactly as stated', function () {
    it('keeps it on a savings account', function () {
        $this->postJson('/api/savings/accounts', [
            'institution' => 'Nationwide',
            'account_type' => 'current_account',
            'current_balance' => 4500,
            'ownership_type' => 'joint',
            'joint_owner_id' => $this->spouse->id,
            'ownership_percentage' => 70,
        ])->assertCreated();

        expect((float) SavingsAccount::sole()->ownership_percentage)->toBe(70.0);
    });

    it('keeps it on a chattel', function () {
        $this->postJson('/api/chattels', [
            'chattel_type' => 'collectible',
            'name' => 'First Edition Books',
            'current_value' => 12000,
            'ownership_type' => 'joint',
            'joint_owner_id' => $this->spouse->id,
            'ownership_percentage' => 70,
        ])->assertCreated();

        expect((float) Chattel::sole()->ownership_percentage)->toBe(70.0);
    });
});

describe('an update that states no share leaves the stored one alone', function () {
    it('does not re-default a savings account stored at 70', function () {
        $account = SavingsAccount::factory()->create([
            'user_id' => $this->user->id,
            'joint_owner_id' => $this->spouse->id,
            'ownership_type' => 'joint',
            'ownership_percentage' => 70,
        ]);

        // The modal has no share input, so every update it sends states none.
        // Re-defaulting here would rewrite a stored 70 to 50 — the same silent
        // overwrite one layer along.
        $this->putJson("/api/savings/accounts/{$account->id}", [
            'ownership_type' => 'joint',
            'joint_owner_id' => $this->spouse->id,
            'current_balance' => 5000,
        ])->assertOk();

        expect((float) $account->fresh()->ownership_percentage)->toBe(70.0);
    });

    it('does not re-default a chattel stored at 70', function () {
        $chattel = Chattel::factory()->create([
            'user_id' => $this->user->id,
            'joint_owner_id' => $this->spouse->id,
            'ownership_type' => 'joint',
            'ownership_percentage' => 70,
        ]);

        $this->putJson("/api/chattels/{$chattel->id}", [
            'ownership_type' => 'joint',
            'joint_owner_id' => $this->spouse->id,
            'current_value' => 45000,
        ])->assertOk();

        expect((float) $chattel->fresh()->ownership_percentage)->toBe(70.0);
    });

    it('still defaults an account being converted from individual to joint', function () {
        // An individual account carries 100 by definition. That is not a shared
        // split to inherit, so conversion re-defaults rather than storing 100/0.
        $account = SavingsAccount::factory()->create([
            'user_id' => $this->user->id,
            'ownership_type' => 'individual',
            'ownership_percentage' => 100,
            'joint_owner_id' => null,
        ]);

        $this->putJson("/api/savings/accounts/{$account->id}", [
            'ownership_type' => 'joint',
            'joint_owner_id' => $this->spouse->id,
        ])->assertOk();

        expect((float) $account->fresh()->ownership_percentage)->toBe(50.0);
    });
});
