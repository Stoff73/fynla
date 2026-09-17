<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One row per job. The onboarding flow has always had a multi-job loop
 * (OnboardingStateMachine::STATE_BASE_EMPLOYMENT_MORE — "Phase 10 — multi-job
 * loop"), but `users` carries a single annual_employment_income column, so the
 * second job Fyn invited overwrote the first and the earlier salary was lost.
 *
 * users.annual_employment_income and users.annual_self_employment_income STAY,
 * maintained as the totals of these rows: 204 call sites read them, and every
 * one of those wants the household total rather than a per-job figure. This
 * table is where the detail lives; those columns are the derived sum.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('employer')->nullable();
            $table->string('occupation')->nullable();
            $table->decimal('annual_income', 12, 2)->default(0);
            // Which of the two user totals this row feeds. Self-employment is
            // taxed differently, so a row cannot simply be "income".
            $table->enum('income_type', ['employment', 'self_employment'])->default('employment');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'income_type']);
        });

        // Backfill: everyone who already has a salary keeps it, as their first
        // job, with the employer and role they gave. Without this the income
        // pages would read zero jobs for every existing user on deploy.
        foreach (DB::table('users')
            ->select('id', 'employer', 'occupation', 'annual_employment_income', 'annual_self_employment_income')
            ->where(fn ($query) => $query
                ->where('annual_employment_income', '>', 0)
                ->orWhere('annual_self_employment_income', '>', 0))
            ->cursor() as $user) {
            $rows = [];
            foreach (['employment' => 'annual_employment_income', 'self_employment' => 'annual_self_employment_income'] as $type => $column) {
                if ((float) ($user->{$column} ?? 0) <= 0) {
                    continue;
                }
                $rows[] = [
                    'user_id' => $user->id,
                    'employer' => $user->employer,
                    'occupation' => $user->occupation,
                    'annual_income' => $user->{$column},
                    'income_type' => $type,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            if ($rows !== []) {
                DB::table('employments')->insert($rows);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('employments');
    }
};
