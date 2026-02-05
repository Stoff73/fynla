<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds RNRB transferred from spouse field for widows/widowers.
     * UK IHT rules allow unused RNRB to be transferred to surviving spouse.
     */
    public function up(): void
    {
        Schema::table('iht_profiles', function (Blueprint $table) {
            $table->decimal('rnrb_transferred_from_spouse', 15, 2)
                ->default(0)
                ->after('nrb_transferred_from_spouse')
                ->comment('Residence Nil Rate Band transferred from deceased spouse');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('iht_profiles', function (Blueprint $table) {
            $table->dropColumn('rnrb_transferred_from_spouse');
        });
    }
};
