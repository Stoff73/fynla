<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Split single 'name' column into first_name, middle_name, surname.
     * Parses existing names intelligently:
     * - "John" -> first_name: "John", surname: ""
     * - "John Smith" -> first_name: "John", surname: "Smith"
     * - "John David Smith" -> first_name: "John", middle_name: "David", surname: "Smith"
     * - "John David William Smith" -> first_name: "John", middle_name: "David William", surname: "Smith"
     */
    public function up(): void
    {
        // Step 1: Add new columns
        Schema::table('users', function (Blueprint $table) {
            $table->string('first_name')->nullable()->after('id');
            $table->string('middle_name')->nullable()->after('first_name');
            $table->string('surname')->nullable()->after('middle_name');
        });

        // Step 2: Migrate existing data from 'name' to new columns
        $users = DB::table('users')->select('id', 'name')->get();

        foreach ($users as $user) {
            $nameParts = $this->parseName($user->name ?? '');

            DB::table('users')
                ->where('id', $user->id)
                ->update([
                    'first_name' => $nameParts['first_name'],
                    'middle_name' => $nameParts['middle_name'],
                    'surname' => $nameParts['surname'],
                ]);
        }

        // Step 3: Make first_name required (has data now), surname remains nullable for backwards compat
        Schema::table('users', function (Blueprint $table) {
            $table->string('first_name')->nullable(false)->change();
        });

        // Step 4: Drop the old 'name' column
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Step 1: Add back the 'name' column
        Schema::table('users', function (Blueprint $table) {
            $table->string('name')->nullable()->after('id');
        });

        // Step 2: Migrate data back to single 'name' column
        $users = DB::table('users')->select('id', 'first_name', 'middle_name', 'surname')->get();

        foreach ($users as $user) {
            $fullName = trim(implode(' ', array_filter([
                $user->first_name,
                $user->middle_name,
                $user->surname,
            ])));

            DB::table('users')
                ->where('id', $user->id)
                ->update(['name' => $fullName ?: 'User']);
        }

        // Step 3: Make name required
        Schema::table('users', function (Blueprint $table) {
            $table->string('name')->nullable(false)->change();
        });

        // Step 4: Drop the new columns
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['first_name', 'middle_name', 'surname']);
        });
    }

    /**
     * Parse a full name into components.
     *
     * @return array{first_name: string, middle_name: string|null, surname: string}
     */
    private function parseName(?string $fullName): array
    {
        if (empty($fullName)) {
            return [
                'first_name' => 'User',
                'middle_name' => null,
                'surname' => '',
            ];
        }

        $parts = preg_split('/\s+/', trim($fullName));
        $count = count($parts);

        if ($count === 1) {
            // Single name: treat as first name
            return [
                'first_name' => $parts[0],
                'middle_name' => null,
                'surname' => '',
            ];
        }

        if ($count === 2) {
            // Two parts: first and last
            return [
                'first_name' => $parts[0],
                'middle_name' => null,
                'surname' => $parts[1],
            ];
        }

        // Three or more parts: first, middle(s), last
        $firstName = array_shift($parts);
        $surname = array_pop($parts);
        $middleName = implode(' ', $parts);

        return [
            'first_name' => $firstName,
            'middle_name' => $middleName ?: null,
            'surname' => $surname,
        ];
    }
};
