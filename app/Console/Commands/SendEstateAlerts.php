<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Estate\Gift;
use App\Models\Estate\Trust;
use App\Models\NotificationPreference;
use App\Models\User;
use App\Notifications\GiftExemptionNotification;
use App\Notifications\TrustAnniversaryNotification;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Daily scheduled command that checks for estate planning alert conditions
 * and sends notifications to users.
 *
 * Checks:
 * 1. Gifts approaching the 7-year Potentially Exempt Transfer exemption
 *    (at 6 years, 6 years 6 months, and 6 years 11 months)
 * 2. Trusts approaching their 10-year periodic charge anniversary (90 days before)
 * 3. Annual Inheritance Tax recalculation prompt (once per tax year)
 */
class SendEstateAlerts extends Command
{
    protected $signature = 'estate:send-alerts';

    protected $description = 'Send daily estate planning alerts (gift exemptions, trust anniversaries, Inheritance Tax recalculation)';

    public function handle(): int
    {
        $giftAlertCount = 0;
        $trustAlertCount = 0;
        $recalcAlertCount = 0;

        // Process users in chunks of 100 for scale
        User::where('is_preview_user', false)
            ->chunk(100, function ($users) use (&$giftAlertCount, &$trustAlertCount, &$recalcAlertCount) {
                foreach ($users as $user) {
                    // Respect notification preferences
                    if (! $this->shouldSendEstateAlerts($user->id)) {
                        continue;
                    }

                    $giftAlertCount += $this->checkGiftExemptions($user);
                    $trustAlertCount += $this->checkTrustAnniversaries($user);
                    $recalcAlertCount += $this->checkAnnualRecalculation($user);
                }
            });

        $this->info("Estate alerts sent: {$giftAlertCount} gift exemptions, {$trustAlertCount} trust anniversaries, {$recalcAlertCount} recalculation prompts.");

        return Command::SUCCESS;
    }

    /**
     * Check for gifts approaching the 7-year exemption.
     *
     * Alert milestones:
     * - 6 years after gift (1 year until exemption)
     * - 6 years 6 months after gift (6 months until exemption)
     * - 6 years 11 months after gift (1 month until exemption)
     */
    private function checkGiftExemptions(User $user): int
    {
        $count = 0;
        $today = today();

        $gifts = Gift::where('user_id', $user->id)
            ->whereIn('gift_type', ['pet', 'clt'])
            ->whereNotNull('gift_date')
            ->where('gift_date', '>', $today->copy()->subYears(7))
            ->where('gift_date', '<=', $today->copy()->subYears(6))
            ->get();

        foreach ($gifts as $gift) {
            $giftDate = Carbon::parse($gift->gift_date);
            $exemptionDate = $giftDate->copy()->addYears(7);
            $monthsUntilExemption = $today->diffInMonths($exemptionDate, false);

            $milestone = null;

            // 6 months before exemption (gift is ~6.5 years old)
            if ($monthsUntilExemption >= 5 && $monthsUntilExemption <= 7) {
                $milestone = 'six_months';
            }
            // 1 month before exemption (gift is ~6 years 11 months old)
            elseif ($monthsUntilExemption >= 0 && $monthsUntilExemption <= 2) {
                $milestone = 'one_month';
            }

            if ($milestone === null) {
                continue;
            }

            // Check if already notified for this milestone
            $alreadyNotified = $user->notifications()
                ->where('type', GiftExemptionNotification::class)
                ->whereJsonContains('data->data->gift_date', $giftDate->format('j F Y'))
                ->whereJsonContains('data->data->milestone', $milestone)
                ->exists();

            if ($alreadyNotified) {
                continue;
            }

            try {
                $user->notify(new GiftExemptionNotification(
                    recipientName: $gift->recipient ?? 'recipient',
                    giftAmount: (float) $gift->gift_value,
                    giftDate: $giftDate->format('j F Y'),
                    exemptionDate: $exemptionDate->format('j F Y'),
                    milestone: $milestone,
                ));
                $count++;
            } catch (\Exception $e) {
                Log::warning('Failed to send gift exemption notification', [
                    'user_id' => $user->id,
                    'gift_id' => $gift->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Also check for gifts that have just become exempt (within last 7 days)
        $newlyExemptGifts = Gift::where('user_id', $user->id)
            ->whereIn('gift_type', ['pet', 'clt'])
            ->whereNotNull('gift_date')
            ->where('gift_date', '<=', $today->copy()->subYears(7))
            ->where('gift_date', '>', $today->copy()->subYears(7)->subDays(7))
            ->get();

        foreach ($newlyExemptGifts as $gift) {
            $giftDate = Carbon::parse($gift->gift_date);

            $alreadyNotified = $user->notifications()
                ->where('type', GiftExemptionNotification::class)
                ->whereJsonContains('data->data->gift_date', $giftDate->format('j F Y'))
                ->whereJsonContains('data->data->milestone', 'exempt')
                ->exists();

            if ($alreadyNotified) {
                continue;
            }

            try {
                $user->notify(new GiftExemptionNotification(
                    recipientName: $gift->recipient ?? 'recipient',
                    giftAmount: (float) $gift->gift_value,
                    giftDate: $giftDate->format('j F Y'),
                    exemptionDate: $giftDate->copy()->addYears(7)->format('j F Y'),
                    milestone: 'exempt',
                ));
                $count++;
            } catch (\Exception $e) {
                Log::warning('Failed to send gift exempt notification', [
                    'user_id' => $user->id,
                    'gift_id' => $gift->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $count;
    }

    /**
     * Check for trusts approaching their 10-year anniversary.
     *
     * Alerts are sent 90 days before a relevant property trust's 10-year anniversary.
     */
    private function checkTrustAnniversaries(User $user): int
    {
        $count = 0;
        $today = today();
        $ninetyDaysFromNow = $today->copy()->addDays(90);

        // Find trusts with a 10-year anniversary within the next 90 days
        $trusts = Trust::where('user_id', $user->id)
            ->where('is_active', true)
            ->where('is_relevant_property_trust', true)
            ->whereNotNull('trust_creation_date')
            ->get();

        foreach ($trusts as $trust) {
            $creationDate = Carbon::parse($trust->trust_creation_date);

            // Calculate the next 10-year anniversary
            $yearsSinceCreation = $today->diffInYears($creationDate);
            $nextAnniversaryMultiple = (int) (ceil($yearsSinceCreation / 10) * 10);

            if ($nextAnniversaryMultiple < 10) {
                $nextAnniversaryMultiple = 10;
            }

            $nextAnniversary = $creationDate->copy()->addYears($nextAnniversaryMultiple);

            // Check if anniversary is within the next 90 days
            if ($nextAnniversary->isBefore($today) || $nextAnniversary->isAfter($ninetyDaysFromNow)) {
                continue;
            }

            $daysUntil = (int) $today->diffInDays($nextAnniversary);

            // Check if already notified for this anniversary
            $alreadyNotified = $user->notifications()
                ->where('type', TrustAnniversaryNotification::class)
                ->whereJsonContains('data->data->trust_name', $trust->trust_name)
                ->whereJsonContains('data->data->anniversary_date', $nextAnniversary->format('j F Y'))
                ->exists();

            if ($alreadyNotified) {
                continue;
            }

            try {
                $user->notify(new TrustAnniversaryNotification(
                    trustName: $trust->trust_name ?? 'Trust',
                    anniversaryDate: $nextAnniversary->format('j F Y'),
                    daysUntil: $daysUntil,
                ));
                $count++;
            } catch (\Exception $e) {
                Log::warning('Failed to send trust anniversary notification', [
                    'user_id' => $user->id,
                    'trust_id' => $trust->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $count;
    }

    /**
     * Check if the user should receive an annual Inheritance Tax recalculation prompt.
     *
     * Sends once per tax year (April 6 - April 5) if the user has an estate
     * with a non-zero Inheritance Tax liability.
     */
    private function checkAnnualRecalculation(User $user): int
    {
        $today = today();

        // UK tax year starts April 6
        $taxYearStart = $today->month > 4 || ($today->month === 4 && $today->day >= 6)
            ? Carbon::create($today->year, 4, 6)
            : Carbon::create($today->year - 1, 4, 6);

        // Only prompt in the first 30 days of the tax year
        if ($today->diffInDays($taxYearStart) > 30) {
            return 0;
        }

        // Check if user has any estate assets
        $hasAssets = $user->assets()->exists() || $user->properties()->exists();
        if (! $hasAssets) {
            return 0;
        }

        // Check if already notified this tax year
        $taxYearLabel = $taxYearStart->format('Y').'/'.($taxYearStart->year + 1);
        $alreadyNotified = $user->notifications()
            ->where('type', 'App\\Notifications\\GiftExemptionNotification')
            ->whereJsonContains('data->type', 'annual_iht_recalc')
            ->where('created_at', '>=', $taxYearStart)
            ->exists();

        // Also check database notifications table directly for the recalc type
        $alreadyNotifiedRecalc = $user->notifications()
            ->whereJsonContains('data->type', 'annual_iht_recalc')
            ->where('created_at', '>=', $taxYearStart)
            ->exists();

        if ($alreadyNotifiedRecalc) {
            return 0;
        }

        try {
            $user->notify(new class($taxYearLabel) extends \Illuminate\Notifications\Notification
            {
                public function __construct(private readonly string $taxYear) {}

                public function via(object $notifiable): array
                {
                    return ['database'];
                }

                public function toArray(object $notifiable): array
                {
                    return [
                        'title' => 'Annual Inheritance Tax Review',
                        'body' => "A new tax year ({$this->taxYear}) has started. We recommend reviewing your Inheritance Tax position to account for any changes in asset values, allowances, or legislation.",
                        'type' => 'annual_iht_recalc',
                        'data' => [
                            'tax_year' => $this->taxYear,
                        ],
                    ];
                }
            });

            return 1;
        } catch (\Exception $e) {
            Log::warning('Failed to send annual IHT recalculation notification', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return 0;
        }
    }

    /**
     * Check if estate alerts should be sent to a user based on their notification preferences.
     */
    private function shouldSendEstateAlerts(int $userId): bool
    {
        $prefs = NotificationPreference::where('user_id', $userId)->first();

        // If no preferences set, default to sending estate alerts
        if (! $prefs) {
            return true;
        }

        // Use estate_alerts preference if it exists, otherwise default to true
        return (bool) ($prefs->estate_alerts ?? true);
    }
}
