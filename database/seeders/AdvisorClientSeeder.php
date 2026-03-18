<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\AdvisorClient;
use App\Models\ClientActivity;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AdvisorClientSeeder extends Seeder
{
    /**
     * Seed advisor-client relationships and sample activities for preview personas.
     *
     * Sets chris@fynla.org as an advisor and assigns all 6 primary preview
     * persona users as clients with realistic review schedules and activity history.
     */
    public function run(): void
    {
        // 1. Set chris@fynla.org as an advisor (bypass guarded attributes)
        $advisor = User::where('email', 'chris@fynla.org')->first();

        if (! $advisor) {
            Log::warning('AdvisorClientSeeder: chris@fynla.org not found — skipping.');

            return;
        }

        DB::table('users')
            ->where('id', $advisor->id)
            ->update(['is_advisor' => true]);

        // 2. Per-persona configuration
        $clientConfigs = [
            'young_family' => [
                'avatar_colour' => 'violet-500',
                'assigned_date' => '2025-06-01',
                'last_review_date' => '2026-01-12',
                'next_review_due' => '2026-07-12',
                'review_frequency_months' => 6,
                'activities' => [
                    ['type' => 'suitability_report', 'summary' => 'Protection review completed', 'date' => '2026-01-12', 'report_type' => 'protection_review', 'report_sent_date' => '2026-01-12'],
                    ['type' => 'email', 'summary' => 'Follow-up on mortgage protection options', 'date' => '2026-03-03'],
                    ['type' => 'phone', 'summary' => 'Discussed workplace pension contribution increase', 'date' => '2026-02-15'],
                    ['type' => 'suitability_report', 'summary' => 'Protection review sent', 'date' => '2026-03-12', 'report_type' => 'protection_review', 'report_sent_date' => '2026-03-12'],
                ],
            ],
            'peak_earners' => [
                'avatar_colour' => 'raspberry-500',
                'assigned_date' => '2025-03-01',
                'last_review_date' => '2025-12-15',
                'next_review_due' => '2025-12-15',
                'review_frequency_months' => 12,
                'activities' => [
                    ['type' => 'suitability_report', 'summary' => 'Annual review completed', 'date' => '2025-12-15', 'report_type' => 'annual_review', 'report_sent_date' => '2025-12-15'],
                    ['type' => 'phone', 'summary' => 'Discussed SIPP drawdown strategy', 'date' => '2026-02-28'],
                    ['type' => 'email', 'summary' => 'Annual review reminder sent', 'date' => '2026-03-03'],
                    ['type' => 'meeting', 'summary' => 'NHS pension transfer analysis discussion', 'date' => '2026-01-20'],
                ],
            ],
            'widow' => [
                'avatar_colour' => 'spring-500',
                'assigned_date' => '2025-04-15',
                'last_review_date' => '2026-02-05',
                'next_review_due' => '2026-08-05',
                'review_frequency_months' => 6,
                'activities' => [
                    ['type' => 'suitability_report', 'summary' => 'Estate suitability review', 'date' => '2026-02-05', 'report_type' => 'estate_review', 'report_sent_date' => '2026-02-05'],
                    ['type' => 'meeting', 'summary' => 'Full estate planning review meeting', 'date' => '2026-02-05'],
                    ['type' => 'email', 'summary' => 'Trust structure documentation sent', 'date' => '2026-01-15'],
                ],
            ],
            'entrepreneur' => [
                'avatar_colour' => 'savannah-500',
                'assigned_date' => '2025-02-01',
                'last_review_date' => '2025-11-20',
                'next_review_due' => '2025-11-20',
                'review_frequency_months' => 12,
                'activities' => [
                    ['type' => 'suitability_report', 'summary' => 'SIPP review completed', 'date' => '2025-11-20', 'report_type' => 'pension_review', 'report_sent_date' => '2025-11-20'],
                    ['type' => 'email', 'summary' => 'Business succession planning resources', 'date' => '2026-03-10'],
                    ['type' => 'phone', 'summary' => 'Discussed SIPP contribution deadline', 'date' => '2026-03-10'],
                    ['type' => 'meeting', 'summary' => 'Trust structure review', 'date' => '2025-12-05'],
                ],
            ],
            'young_saver' => [
                'avatar_colour' => 'light-blue-500',
                'assigned_date' => '2025-09-01',
                'last_review_date' => '2026-03-01',
                'next_review_due' => '2026-09-01',
                'review_frequency_months' => 6,
                'activities' => [
                    ['type' => 'suitability_report', 'summary' => 'Savings review completed', 'date' => '2026-03-01', 'report_type' => 'savings_review', 'report_sent_date' => '2026-03-01'],
                    ['type' => 'email', 'summary' => 'Savings account review follow-up', 'date' => '2026-03-15'],
                    ['type' => 'phone', 'summary' => 'Emergency fund strategy call', 'date' => '2026-02-10'],
                ],
            ],
            'retired_couple' => [
                'avatar_colour' => 'horizon-500',
                'assigned_date' => '2025-01-15',
                'last_review_date' => '2026-02-20',
                'next_review_due' => '2027-02-20',
                'review_frequency_months' => 12,
                'activities' => [
                    ['type' => 'suitability_report', 'summary' => 'Full annual review completed', 'date' => '2026-02-20', 'report_type' => 'annual_review', 'report_sent_date' => '2026-02-20'],
                    ['type' => 'meeting', 'summary' => 'Full annual review completed', 'date' => '2026-02-20'],
                    ['type' => 'email', 'summary' => 'Decumulation strategy update', 'date' => '2026-01-30'],
                    ['type' => 'phone', 'summary' => 'Estate planning IHT review discussion', 'date' => '2026-01-10'],
                ],
            ],
        ];

        // 3. Create advisor_clients records and seed activities
        foreach ($clientConfigs as $personaKey => $config) {
            $client = User::where('preview_persona_id', $personaKey)
                ->where('is_primary_account', true)
                ->first();

            if (! $client) {
                Log::warning("AdvisorClientSeeder: Preview persona '{$personaKey}' not found — skipping.");

                continue;
            }

            $advisorClient = AdvisorClient::updateOrCreate(
                [
                    'advisor_id' => $advisor->id,
                    'client_id' => $client->id,
                ],
                [
                    'status' => 'active',
                    'assigned_date' => $config['assigned_date'],
                    'last_review_date' => $config['last_review_date'],
                    'next_review_due' => $config['next_review_due'],
                    'review_frequency_months' => $config['review_frequency_months'],
                    'notes' => json_encode(['avatar_colour' => $config['avatar_colour']]),
                ]
            );

            foreach ($config['activities'] as $activity) {
                $activityData = [
                    'advisor_id' => $advisor->id,
                    'client_id' => $client->id,
                    'summary' => $activity['summary'],
                ];

                if (isset($activity['report_type'])) {
                    $activityData['report_type'] = $activity['report_type'];
                }

                if (isset($activity['report_sent_date'])) {
                    $activityData['report_sent_date'] = $activity['report_sent_date'];
                }

                ClientActivity::updateOrCreate(
                    [
                        'advisor_client_id' => $advisorClient->id,
                        'activity_date' => $activity['date'],
                        'activity_type' => $activity['type'],
                    ],
                    $activityData
                );
            }
        }

        $clientCount = AdvisorClient::where('advisor_id', $advisor->id)->count();
        $activityCount = ClientActivity::where('advisor_id', $advisor->id)->count();

        $this->command->info("AdvisorClientSeeder: {$clientCount} clients, {$activityCount} activities seeded.");
    }
}
