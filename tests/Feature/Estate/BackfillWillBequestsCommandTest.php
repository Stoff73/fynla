<?php

declare(strict_types=1);

use App\Models\Estate\Bequest;
use App\Models\Estate\Will;
use App\Models\Estate\WillDocument;
use App\Models\User;
use App\Services\Estate\WillDocumentService;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * W-0046 — wills completed before W-0023 hold their gifts on the will document
 * and have zero Bequest rows, so the Estate module cannot see them and a
 * charitable legacy is worth £0 to the Inheritance Tax calculation.
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
});

function completedWillWithGifts(array $gifts, ?User $user = null): WillDocument
{
    $user ??= User::factory()->create(['marital_status' => 'single']);

    $will = Will::firstOrCreate(['user_id' => $user->id], ['has_will' => true]);

    return WillDocument::factory()->create([
        'user_id' => $user->id,
        'will_id' => $will->id,
        'status' => 'complete',
        'testator_full_name' => $user->name,
        'executors' => [['name' => 'Someone Else', 'address' => '1 High St']],
        'residuary_estate' => [['beneficiary_name' => 'Someone Else', 'percentage' => 100]],
        'specific_gifts' => $gifts,
    ]);
}

describe('the dry run', function () {
    it('writes nothing without --force', function () {
        $doc = completedWillWithGifts([
            ['beneficiary_name' => 'Cancer Research UK', 'type' => 'cash', 'amount' => 10000],
        ]);

        $this->artisan('estate:backfill-bequests')
            ->expectsOutputToContain('DRY RUN')
            ->assertSuccessful();

        expect(Bequest::where('user_id', $doc->user_id)->count())->toBe(0);
    });

    it('reports the before and after charitable totals it would produce', function () {
        completedWillWithGifts([
            ['beneficiary_name' => 'Cancer Research UK', 'type' => 'cash', 'amount' => 10000],
        ]);

        $this->artisan('estate:backfill-bequests')
            ->expectsOutputToContain('Would backfill 1 will(s) → 1 bequest row(s).')
            ->expectsOutputToContain('£0.00 → £10,000.00')
            ->assertSuccessful();
    });
});

describe('the backfill', function () {
    it('creates the missing bequest rows with --force', function () {
        $doc = completedWillWithGifts([
            ['beneficiary_name' => 'Cancer Research UK', 'type' => 'cash', 'amount' => 10000],
            ['beneficiary_name' => 'William Jones', 'type' => 'item', 'description' => 'My grandfather clock'],
        ]);

        $this->artisan('estate:backfill-bequests', ['--force' => true])->assertSuccessful();

        $bequests = Bequest::where('user_id', $doc->user_id)->orderBy('priority_order')->get();

        expect($bequests)->toHaveCount(2);
        expect($bequests[0]->beneficiary_name)->toBe('Cancer Research UK');
        expect($bequests[0]->bequest_type)->toBe('specific_amount');
        expect((float) $bequests[0]->specific_amount)->toBe(10000.0);
        expect($bequests[0]->will_document_id)->toBe($doc->id);
        expect($bequests[1]->bequest_type)->toBe('specific_asset');
    });

    it('is idempotent — re-running changes nothing', function () {
        $doc = completedWillWithGifts([
            ['beneficiary_name' => 'Cancer Research UK', 'type' => 'cash', 'amount' => 10000],
        ]);

        $this->artisan('estate:backfill-bequests', ['--force' => true])->assertSuccessful();
        $first = Bequest::where('user_id', $doc->user_id)->get();

        $this->artisan('estate:backfill-bequests', ['--force' => true])->assertSuccessful();
        $second = Bequest::where('user_id', $doc->user_id)->get();

        expect($second)->toHaveCount(1);
        expect($second->count())->toBe($first->count());
    });

    it('never touches a bequest the user created by hand', function () {
        $doc = completedWillWithGifts([
            ['beneficiary_name' => 'Cancer Research UK', 'type' => 'cash', 'amount' => 10000],
        ]);
        $will = Will::where('user_id', $doc->user_id)->firstOrFail();

        $manual = Bequest::create([
            'will_id' => $will->id,
            'user_id' => $doc->user_id,
            'beneficiary_name' => 'Macmillan Cancer Support',
            'bequest_type' => 'percentage',
            'percentage_of_estate' => 5,
            'priority_order' => 9,
        ]);

        $this->artisan('estate:backfill-bequests', ['--force' => true])->assertSuccessful();

        expect(Bequest::find($manual->id))->not->toBeNull();
        expect(Bequest::find($manual->id)->will_document_id)->toBeNull();
        expect(Bequest::where('user_id', $doc->user_id)->count())->toBe(2);
    });

    it('leaves a will whose gifts are already synced untouched', function () {
        $doc = completedWillWithGifts([
            ['beneficiary_name' => 'Cancer Research UK', 'type' => 'cash', 'amount' => 10000],
        ]);

        $this->artisan('estate:backfill-bequests', ['--force' => true])->assertSuccessful();
        $before = Bequest::where('user_id', $doc->user_id)->pluck('id')->all();

        $this->artisan('estate:backfill-bequests', ['--force' => true])->assertSuccessful();

        expect(Bequest::where('user_id', $doc->user_id)->count())->toBe(count($before));
    });

    it('skips a completed will that holds no gifts', function () {
        completedWillWithGifts([]);

        $this->artisan('estate:backfill-bequests', ['--force' => true])
            ->expectsOutputToContain('Nothing to backfill')
            ->assertSuccessful();
    });

    it('restricts to one user with --user', function () {
        $mine = completedWillWithGifts([
            ['beneficiary_name' => 'Cancer Research UK', 'type' => 'cash', 'amount' => 10000],
        ]);
        $theirs = completedWillWithGifts([
            ['beneficiary_name' => 'British Heart Foundation', 'type' => 'cash', 'amount' => 5000],
        ]);

        $this->artisan('estate:backfill-bequests', ['--force' => true, '--user' => $mine->user_id])
            ->assertSuccessful();

        expect(Bequest::where('user_id', $mine->user_id)->count())->toBe(1);
        expect(Bequest::where('user_id', $theirs->user_id)->count())->toBe(0);
    });
});

describe('a superseded document', function () {
    it('syncs only the latest completed will, not an earlier one', function () {
        $user = User::factory()->create(['marital_status' => 'single']);

        completedWillWithGifts([
            ['beneficiary_name' => 'Old Charity', 'type' => 'cash', 'amount' => 1000],
        ], $user);

        $latest = completedWillWithGifts([
            ['beneficiary_name' => 'Cancer Research UK', 'type' => 'cash', 'amount' => 10000],
        ], $user);

        $this->artisan('estate:backfill-bequests', ['--force' => true])->assertSuccessful();

        $bequests = Bequest::where('user_id', $user->id)->get();

        expect($bequests)->toHaveCount(1);
        expect($bequests[0]->beneficiary_name)->toBe('Cancer Research UK');
        expect($bequests[0]->will_document_id)->toBe($latest->id);
    });
});

describe('the backfill and a later re-completion cannot both write the same gift', function () {
    it('holds one row after backfilling and then re-completing', function () {
        $doc = completedWillWithGifts([
            ['beneficiary_name' => 'Cancer Research UK', 'type' => 'cash', 'amount' => 10000],
        ]);
        $doc->update([
            'testator_date_of_birth' => now()->subYears(48),
            'domicile_confirmed' => 'england_wales',
        ]);

        $this->artisan('estate:backfill-bequests', ['--force' => true])->assertSuccessful();
        expect(Bequest::where('user_id', $doc->user_id)->count())->toBe(1);

        app(WillDocumentService::class)->markComplete($doc->fresh());

        expect(Bequest::where('user_id', $doc->user_id)->count())->toBe(1);
    });
});
