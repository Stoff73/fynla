<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Mail\DataDeletionConfirmation;
use App\Mail\DataRetentionWarning;
use App\Mail\DeletionVerificationCode;
use App\Mail\PaymentConfirmation;
use App\Mail\SpouseAccountCreated;
use App\Mail\SpouseAccountLinked;
use App\Mail\SubscriptionCancellation;
use App\Mail\SubscriptionRenewalReminder;
use App\Mail\TrialExpirationReminder;
use App\Mail\VerificationCode;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendTestEmails extends Command
{
    protected $signature = 'email:test {recipient} {--template= : Send only a specific template}';
    protected $description = 'Send test versions of all email templates to a specified address';

    public function handle(): int
    {
        $recipient = $this->argument('recipient');
        $template = $this->option('template');

        $user = User::where('email', 'chris@fynla.org')->first()
            ?? User::first();

        if (!$user) {
            $this->error('No user found for test data.');
            return 1;
        }

        $spouse = User::where('email', 'jane@example.com')->first() ?? $user;

        $templates = [
            'verification' => fn() => $this->sendVerification($recipient, $user),
            'payment' => fn() => $this->sendPayment($recipient, $user),
            'trial' => fn() => $this->sendTrial($recipient, $user),
            'renewal' => fn() => $this->sendRenewal($recipient, $user),
            'cancellation' => fn() => $this->sendCancellation($recipient, $user),
            'spouse-created' => fn() => $this->sendSpouseCreated($recipient, $user, $spouse),
            'spouse-linked' => fn() => $this->sendSpouseLinked($recipient, $user, $spouse),
            'retention' => fn() => $this->sendRetention($recipient, $user),
            'deletion-verify' => fn() => $this->sendDeletionVerify($recipient, $user),
            'deletion-confirm' => fn() => $this->sendDeletionConfirm($recipient, $user),
        ];

        if ($template && isset($templates[$template])) {
            $templates[$template]();
            $this->info("Sent '{$template}' to {$recipient}");
            return 0;
        }

        if ($template) {
            $this->error("Unknown template: {$template}. Available: " . implode(', ', array_keys($templates)));
            return 1;
        }

        foreach ($templates as $name => $sender) {
            try {
                $sender();
                $this->info("Sent: {$name}");
                sleep(1);
            } catch (\Exception $e) {
                $this->error("Failed: {$name} — {$e->getMessage()}");
            }
        }

        $this->info("All test emails sent to {$recipient}");
        return 0;
    }

    private function sendVerification(string $to, User $user): void
    {
        $userObj = (object) ['first_name' => $user->first_name, 'email' => $user->email];
        Mail::to($to)->send(new VerificationCode($userObj, '847293', 'login'));
    }

    private function sendPayment(string $to, User $user): void
    {
        $payment = \App\Models\Payment::latest()->first();

        if (!$payment) {
            $this->warn('No payment records found — skipping payment confirmation email.');
            return;
        }

        $paymentUser = $payment->user ?? $user;
        Mail::to($to)->send(new PaymentConfirmation($paymentUser, $payment));
    }

    private function sendTrial(string $to, User $user): void
    {
        Mail::to($to)->send(new TrialExpirationReminder($user, 2));
    }

    private function sendRenewal(string $to, User $user): void
    {
        $subscription = $user->subscription;
        if (!$subscription) {
            $subscription = \App\Models\Subscription::latest()->first();
        }
        if (!$subscription) {
            $this->warn('No subscription found — skipping renewal reminder email.');
            return;
        }
        $subUser = $subscription->user ?? $user;
        Mail::to($to)->send(new SubscriptionRenewalReminder($subUser, $subscription));
    }

    private function sendCancellation(string $to, User $user): void
    {
        $subscription = $user->subscription;
        if (!$subscription) {
            $subscription = \App\Models\Subscription::latest()->first();
        }
        if (!$subscription) {
            $this->warn('No subscription found — skipping cancellation email.');
            return;
        }
        $subUser = $subscription->user ?? $user;
        Mail::to($to)->send(new SubscriptionCancellation($subUser, $subscription));
    }

    private function sendSpouseCreated(string $to, User $user, User $spouse): void
    {
        Mail::to($to)->send(new SpouseAccountCreated($spouse, $user, 'TempPass123!'));
    }

    private function sendSpouseLinked(string $to, User $user, User $spouse): void
    {
        Mail::to($to)->send(new SpouseAccountLinked($spouse, $user));
    }

    private function sendRetention(string $to, User $user): void
    {
        Mail::to($to)->send(new DataRetentionWarning($user, 30));
    }

    private function sendDeletionVerify(string $to, User $user): void
    {
        Mail::to($to)->send(new DeletionVerificationCode($user, '593721'));
    }

    private function sendDeletionConfirm(string $to, User $user): void
    {
        Mail::to($to)->send(new DataDeletionConfirmation($user->first_name, $user->email));
    }
}
