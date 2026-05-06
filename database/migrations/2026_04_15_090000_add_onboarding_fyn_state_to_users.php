<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the four state columns that back the Fyn-driven onboarding
 * state machine (OnboardingChatDirector). These fields persist per-user
 * progress across reloads so the director can resume a partially
 * completed flow without relying on frontend state.
 *
 *  - onboarding_fyn_step      current state id in OnboardingStateMachine
 *  - onboarding_fyn_path      'journey' | 'focus' — the top-level choice
 *  - onboarding_fyn_selection specific journey/focus name, e.g. 'savings'
 *  - onboarding_fyn_context   JSON scratch pad for per-state detail
 *
 * Plan: April/April15Updates/fynOnboardFix.md §3.1
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $anchor = Schema::hasColumn('users', 'onboarding_asset_flags')
                ? 'onboarding_asset_flags'
                : null;

            if (! Schema::hasColumn('users', 'onboarding_fyn_step')) {
                $column = $table->string('onboarding_fyn_step', 50)->nullable();
                if ($anchor !== null) {
                    $column->after($anchor);
                }
            }

            if (! Schema::hasColumn('users', 'onboarding_fyn_path')) {
                $column = $table->string('onboarding_fyn_path', 20)->nullable();
                if (Schema::hasColumn('users', 'onboarding_fyn_step')) {
                    $column->after('onboarding_fyn_step');
                }
            }

            if (! Schema::hasColumn('users', 'onboarding_fyn_selection')) {
                $column = $table->string('onboarding_fyn_selection', 50)->nullable();
                if (Schema::hasColumn('users', 'onboarding_fyn_path')) {
                    $column->after('onboarding_fyn_path');
                }
            }

            if (! Schema::hasColumn('users', 'onboarding_fyn_context')) {
                $column = $table->json('onboarding_fyn_context')->nullable();
                if (Schema::hasColumn('users', 'onboarding_fyn_selection')) {
                    $column->after('onboarding_fyn_selection');
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            foreach ([
                'onboarding_fyn_context',
                'onboarding_fyn_selection',
                'onboarding_fyn_path',
                'onboarding_fyn_step',
            ] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
