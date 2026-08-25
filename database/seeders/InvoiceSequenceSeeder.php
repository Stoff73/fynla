<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class InvoiceSequenceSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $highestIssued = DB::table('invoices')
                ->where('invoice_number', 'like', 'FYN-INV-%')
                ->pluck('invoice_number')
                ->map(static function (string $invoiceNumber): int {
                    $suffix = substr($invoiceNumber, strlen('FYN-INV-'));

                    return ctype_digit($suffix) ? (int) $suffix : 0;
                })
                ->max() ?? 0;

            DB::table('invoice_sequences')->insertOrIgnore([
                'id' => 1,
                'next_value' => 1,
            ]);

            $sequence = DB::table('invoice_sequences')->where('id', 1)->lockForUpdate()->first();
            $safeNextValue = max((int) $sequence->next_value, $highestIssued + 1);

            DB::table('invoice_sequences')->where('id', 1)->update([
                'next_value' => $safeNextValue,
            ]);
        });
    }
}
