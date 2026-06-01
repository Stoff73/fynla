<?php

declare(strict_types=1);

use App\Models\TaxProductReference;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->corpus = sys_get_temp_dir().'/sem-'.uniqid();
    config(['fyn.memory.semantic_path' => $this->corpus]);
});

afterEach(fn () => File::deleteDirectory($this->corpus));

it('writes one product .md per active reference row', function (): void {
    TaxProductReference::create([
        'product_category' => 'investment', 'product_type' => 'isa', 'tax_aspect' => 'cgt',
        'title' => 'ISA capital gains', 'summary' => 'Gains inside an ISA are tax-free.',
        'status' => 'advantage', 'display_order' => 1, 'is_active' => true,
    ]);

    $this->artisan('fyn:semantic:generate-products')->assertExitCode(0);

    $files = glob("$this->corpus/product/*.md");
    expect($files)->toHaveCount(1);
    $body = file_get_contents($files[0]);
    expect($body)->toContain('category: product')
        ->and($body)->toContain('fact_id: product-investment-isa-cgt')
        ->and($body)->toContain('Gains inside an ISA are tax-free.');
});

it('regenerates idempotently (clears prior product files first)', function (): void {
    TaxProductReference::create(['product_category' => 'savings', 'product_type' => 'easy_access', 'tax_aspect' => 'income_tax', 'title' => 'PSA', 'summary' => 'Personal savings allowance applies.', 'status' => 'neutral', 'display_order' => 1, 'is_active' => true]);
    @mkdir("$this->corpus/product", 0777, true);
    file_put_contents("$this->corpus/product/stale.md", "---\nfact_id: stale\n---\n");

    $this->artisan('fyn:semantic:generate-products')->assertExitCode(0);

    expect(file_exists("$this->corpus/product/stale.md"))->toBeFalse()
        ->and(glob("$this->corpus/product/*.md"))->toHaveCount(1);
});
