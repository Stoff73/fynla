<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE savings_accounts MODIFY ownership_type ENUM('individual','joint','tenants_in_common','trust') NOT NULL DEFAULT 'individual'");
        DB::statement("ALTER TABLE liabilities MODIFY ownership_type ENUM('individual','joint','tenants_in_common','trust') NOT NULL DEFAULT 'individual'");

        if (! Schema::hasColumn('savings_accounts', 'trust_id')) {
            Schema::table('savings_accounts', function (Blueprint $table): void {
                $table->foreignId('trust_id')->nullable()->after('joint_owner_id')->constrained('trusts')->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('liabilities', 'ownership_percentage')) {
            Schema::table('liabilities', function (Blueprint $table): void {
                $table->decimal('ownership_percentage', 5, 2)->default(100.00)->after('ownership_type');
            });
            DB::table('liabilities')
                ->where('ownership_type', 'joint')
                ->update(['ownership_percentage' => 50.00]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('savings_accounts', 'trust_id')) {
            Schema::table('savings_accounts', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('trust_id');
            });
        }

        DB::table('savings_accounts')->where('ownership_type', 'tenants_in_common')->update(['ownership_type' => 'joint']);
        DB::table('liabilities')->where('ownership_type', 'tenants_in_common')->update(['ownership_type' => 'joint']);

        DB::statement("ALTER TABLE savings_accounts MODIFY ownership_type ENUM('individual','joint','trust') NOT NULL DEFAULT 'individual'");
        DB::statement("ALTER TABLE liabilities MODIFY ownership_type ENUM('individual','joint','trust') NOT NULL DEFAULT 'individual'");

        if (Schema::hasColumn('liabilities', 'ownership_percentage')) {
            Schema::table('liabilities', function (Blueprint $table): void {
                $table->dropColumn('ownership_percentage');
            });
        }
    }
};
