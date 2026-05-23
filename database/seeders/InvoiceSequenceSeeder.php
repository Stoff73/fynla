<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class InvoiceSequenceSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('invoice_sequences')->updateOrInsert(
            ['id' => 1],
            ['next_value' => 1]
        );
    }
}
