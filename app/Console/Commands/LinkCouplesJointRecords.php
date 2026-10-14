<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Onboarding\SpouseJointRecords;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * T27 (Q23, RC8): joint records added through Fyn after onboarding were never
 * linked to the partner (production property 533). This links the ones a
 * linked couple already holds, by SpouseJointRecords::linkExisting.
 */
class LinkCouplesJointRecords extends Command
{
    protected $signature = 'couples:link-joint-records
                            {--dry-run : List the records that would be linked, without changing them}';

    protected $description = 'Link joint records with no co-owner to the linked spouse (T27)';

    public function handle(SpouseJointRecords $records): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $all = [];
        User::query()->whereNotNull('spouse_id')->where('is_preview_user', false)->each(function (User $user) use ($records, $dryRun, &$all): void {
            foreach ($records->linkExisting($user, $dryRun) as $record) {
                $all[] = $record['type'].' '.$record['id'].' (user '.$user->id.')';
            }
        });
        $list = implode(', ', $all);
        $count = count($all);

        if ($dryRun) {
            $this->info("Would link {$count} joint record(s): {$list}");

            return Command::SUCCESS;
        }

        $this->info("Linked {$count} joint record(s): {$list}");
        Log::info('[LinkCouplesJointRecords] Linked joint records to the spouse', ['records' => $all]);

        return Command::SUCCESS;
    }
}
