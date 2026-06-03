<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Mail\Lifecycle\CountdownMail;
use App\Mail\Lifecycle\DontMissOutMail;
use App\Mail\Lifecycle\GetStartedMail;
use App\Mail\Lifecycle\GreatJobMail;
use App\Mail\Lifecycle\InsightsMail;
use App\Mail\Lifecycle\SubscribeInProgressMail;
use App\Mail\Lifecycle\SubscribeMaxDiscountMail;
use App\Mail\Lifecycle\WeHaventSeenYouMail;
use App\Mail\Lifecycle\WelcomeMail;
use App\Mail\Lifecycle\WellDoneMail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendLifecycleTestCommand extends Command
{
    protected $signature = 'mail:send-lifecycle-test {email : Recipient address} {--only= : Comma-separated slugs (e.g. welcome,well-done) to send a subset; default is all 11}';

    protected $description = 'Fire the 11 lifecycle emails with demo data to the given address. Used for visual QA before scheduling.';

    public function handle(): int
    {
        $email = $this->argument('email');

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error("Invalid email address: {$email}");

            return self::FAILURE;
        }

        // Demo data — James Carter / Save Tax journey / 3 days remaining / 26 April trial end
        $demo = [
            'firstName' => 'James',
            'email' => 'james.carter@example.com',
            'username' => 'james',
            'planLabel' => 'Free trial (7 days)',
            'startDate' => '22 April 2026',
            'journeySlug' => 'save-tax',
            'journeyHeadline' => 'You can save tax',
            'journeyPhrase' => 'saving tax?',
            'trialEndDate' => '26 April 2026',
            'daysRemaining' => 3,
        ];

        $sequence = [
            'welcome' => fn () => new WelcomeMail(
                firstName: $demo['firstName'],
                email: $demo['email'],
                username: $demo['username'],
                planLabel: $demo['planLabel'],
                startDate: $demo['startDate'],
                journeySlug: $demo['journeySlug'],
                journeyHeadline: $demo['journeyHeadline'],
            ),
            'get-started' => fn () => new GetStartedMail(firstName: $demo['firstName'], progressPercent: 50),
            'dont-miss-out' => fn () => new DontMissOutMail(firstName: $demo['firstName']),
            'insights' => fn () => new InsightsMail(firstName: $demo['firstName'], journeyPhrase: $demo['journeyPhrase'], progressPercent: 50),
            'great-job' => fn () => new GreatJobMail(firstName: $demo['firstName'], progressPercent: 75, discountEarned: '15%'),
            'well-done' => fn () => new WellDoneMail(firstName: $demo['firstName']),
            'subscribe-in-progress' => fn () => new SubscribeInProgressMail(
                firstName: $demo['firstName'],
                daysRemaining: $demo['daysRemaining'],
                trialEndDate: $demo['trialEndDate'],
                currentDiscount: '10%',
                progressPercent: 50,
            ),
            'subscribe-max-discount' => fn () => new SubscribeMaxDiscountMail(
                firstName: $demo['firstName'],
                daysRemaining: $demo['daysRemaining'],
                trialEndDate: $demo['trialEndDate'],
            ),
            'countdown' => fn () => new CountdownMail(
                firstName: $demo['firstName'],
                daysRemaining: $demo['daysRemaining'],
                trialEndDate: $demo['trialEndDate'],
                discountCode: 'FYNLA15',
                discountPercent: '15%',
            ),
            'we-havent-seen-you' => fn () => new WeHaventSeenYouMail(firstName: $demo['firstName']),
        ];

        $only = $this->option('only');
        if ($only !== null && $only !== '') {
            $knownSlugs = array_keys($sequence);
            $selected = array_map('trim', explode(',', $only));
            $sequence = array_intersect_key($sequence, array_flip($selected));
            if (empty($sequence)) {
                $this->error('No matching slugs. Known slugs: '.implode(', ', $knownSlugs));

                return self::FAILURE;
            }
        }

        $total = count($sequence);
        $this->info("Sending {$total} lifecycle test email(s) to {$email}…");
        $this->newLine();

        $position = 0;
        $sent = 0;
        $failed = [];

        foreach ($sequence as $slug => $factory) {
            $position++;
            $label = "[{$position}/{$total}] {$slug}";
            try {
                $mailable = $factory();
                Mail::to($email)->send($mailable);
                $this->line("<fg=green>[OK]</> {$label} - sent");
                $sent++;
            } catch (Throwable $e) {
                $this->line("<fg=red>[FAIL]</> {$label} - failed: ".$e->getMessage());
                $failed[] = $slug;
            }
        }

        $this->newLine();
        $this->info("Done: {$sent}/{$total} sent.");

        if (! empty($failed)) {
            $this->warn('Failed: '.implode(', ', $failed));

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
