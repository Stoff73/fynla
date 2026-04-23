<?php

declare(strict_types=1);

namespace App\Services\Lifecycle;

use App\Models\DiscountCode;
use App\Models\User;
use Illuminate\Support\Str;
use RuntimeException;

class LifecycleDiscountCodeGenerator
{
    private const MAX_COLLISION_RETRIES = 5;

    /**
     * Generate a user-locked welcome discount code for Campaign 2 (engaged trialer).
     *
     * The code is a one-shot WELCOME_XXXXXXXX voucher that applies only to the
     * specified user, expires after the configured TTL, and carries the
     * per-plan-per-cycle discount amounts in its metadata. Pro plan is
     * deliberately excluded — lifecycle welcome is for student/standard/family.
     */
    public function generate(User $user): DiscountCode
    {
        $code = $this->generateUniqueCode();

        return DiscountCode::create([
            'code' => $code,
            'type' => 'lifecycle_welcome',
            'value' => 0,
            'user_id' => $user->id,
            'max_uses' => 1,
            'max_uses_per_user' => 1,
            'applicable_plans' => ['student', 'standard', 'family'],
            'applicable_cycles' => ['monthly', 'yearly'],
            'starts_at' => now(),
            'expires_at' => now()->addDays((int) config('lifecycle.discount_code_ttl_days', 7)),
            'is_active' => true,
            'metadata' => [
                'plan_amounts' => config('lifecycle.campaign2_discounts', []),
                'campaign' => 'engaged_trialer',
                'issued_via' => 'lifecycle_email',
            ],
        ]);
    }

    private function generateUniqueCode(): string
    {
        for ($i = 0; $i < self::MAX_COLLISION_RETRIES; $i++) {
            $code = 'WELCOME_' . strtoupper(Str::random(8));
            if (! DiscountCode::where('code', $code)->exists()) {
                return $code;
            }
        }

        throw new RuntimeException(
            'Failed to generate a unique lifecycle discount code after '
            . self::MAX_COLLISION_RETRIES . ' attempts.'
        );
    }
}
