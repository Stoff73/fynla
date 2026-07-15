<?php

declare(strict_types=1);

namespace App\Console\Commands\Pipeline;

use App\Mail\Pipeline\VideoAuditReminderMail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use SplFileInfo;
use Symfony\Component\Finder\Finder;

/**
 * Runs quarterly (Kernel schedule): finds source videos + clips on the
 * server file system that are older than the retention threshold and
 * emails marketing@fynla.org so they can decide what to delete.
 *
 * The default retention is 1 year (config('pipeline.video.audit_older_than_days')).
 * The command never deletes anything — that's an intentional human decision.
 */
class AuditSocialVideos extends Command
{
    protected $signature = 'pipeline:audit-social-videos
                            {--dry-run : Do not send email; print the audit list only}';

    protected $description = 'Quarterly retention audit — email marketing@ with videos older than 1 year';

    public function handle(): int
    {
        $olderThanDays = (int) config('pipeline.video.audit_older_than_days', 365);
        $cutoff = now()->subDays($olderThanDays);

        $roots = [
            storage_path('app/social/source'),
            storage_path('app/social/video'),
        ];

        $rows = [];
        foreach ($roots as $root) {
            if (! is_dir($root)) {
                continue;
            }
            $finder = (new Finder())
                ->files()
                ->in($root)
                ->name('*.mp4')
                ->date('until '.$cutoff->toDateString());

            foreach ($finder as $file) {
                /** @var SplFileInfo $file */
                $rows[] = [
                    'path' => $file->getRealPath(),
                    'relative' => str_replace(storage_path('app/social/').DIRECTORY_SEPARATOR, '', $file->getRealPath()),
                    'age_days' => (int) $file->getMTime() > 0
                        ? (int) floor((time() - $file->getMTime()) / 86400)
                        : null,
                    'size_mb' => (float) round($file->getSize() / (1024 * 1024), 1),
                ];
            }
        }

        usort($rows, fn ($a, $b) => ($b['age_days'] ?? 0) <=> ($a['age_days'] ?? 0));

        if ($rows === []) {
            $this->info('No videos older than '.$olderThanDays.' days found.');

            return self::SUCCESS;
        }

        $this->info(sprintf('Found %d file(s) older than %d days.', count($rows), $olderThanDays));

        if ($this->option('dry-run')) {
            foreach ($rows as $row) {
                $this->line(sprintf('  · [%d days · %.1f MB] %s', $row['age_days'] ?? 0, $row['size_mb'], $row['relative']));
            }

            return self::SUCCESS;
        }

        Mail::to((string) config('pipeline.notifications.script_ready_to'))
            ->queue(new VideoAuditReminderMail($rows, $olderThanDays));

        $this->info('Audit reminder emailed to '.config('pipeline.notifications.script_ready_to').'.');

        return self::SUCCESS;
    }
}
