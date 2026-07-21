<?php

declare(strict_types=1);

use App\Models\Document;
use App\Models\SavingsAccount;
use App\Models\User;
use App\Services\Documents\DocumentProcessor;
use Database\Seeders\TaxConfigurationSeeder;
use Database\Seeders\TierConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(TierConfigurationSeeder::class);
});

it('DocumentProcessor::confirmExcel persists a savings extraction via SavingsStore with IngestSource::UPLOAD', function () {
    $user = User::factory()->create();

    // Create a minimal Document row — no file on disk is needed for confirmExcel.
    $document = Document::create([
        'user_id' => $user->id,
        'original_filename' => 'statement.xlsx',
        'stored_filename' => 'statement.xlsx',
        'disk' => 'local',
        'path' => 'documents/statement.xlsx',
        'mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'file_size' => 1024,
        'document_type' => Document::TYPE_SAVINGS_STATEMENT,
        'status' => Document::STATUS_REVIEW_PENDING,
    ]);

    $confirmedSheets = [
        [
            'sheet_name' => 'Sheet1',
            'category' => 'cash_savings',
            'account' => [
                'account_name' => 'Barclays Cash ISA',
                'institution' => 'Barclays',
                'account_type' => 'cash_isa',
                'current_balance' => 12000,
                'is_isa' => true,
            ],
        ],
    ];

    $processor = app(DocumentProcessor::class);
    $results = $processor->confirmExcel($document, $confirmedSheets, $user);

    // One SavingsAccount row must exist.
    expect(SavingsAccount::count())->toBe(1);

    $account = SavingsAccount::first();
    expect($account->user_id)->toBe($user->id);
    // SavingsAccountMapper does not map account_name — fromUpload falls back to institution.
    expect($account->account_name)->toBe('Barclays');
    expect((float) $account->current_balance)->toBe(12000.0);
    expect($account->ownership_type)->toBe('individual');
    expect($account->ownership_percentage)->toBe('100.00');
    expect($account->country)->toBe('United Kingdom');

    // confirmExcel returns ['document' => ..., 'results' => [...]].
    expect($results['results'])->toHaveCount(1);
    expect($results['results'][0]['model_type'])->toBe('SavingsAccount');
    expect($results['results'][0]['model_id'])->toBe($account->id);
});
