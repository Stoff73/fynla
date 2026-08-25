<?php

declare(strict_types=1);

namespace App\Notifications;

use Carbon\Carbon;
use Illuminate\Notifications\Notification;

/**
 * Notifies a user that a Companies House filing deadline for a company they
 * own is approaching, due today, or overdue.
 *
 * Dates come from the Companies House register (CompaniesHouseService), never
 * from an estimate — this notification is only ever raised against a synced
 * date, so the deadline it quotes is the statutory one.
 *
 * The two filings carry different consequences and the copy says so: late
 * accounts attract an automatic penalty, a late confirmation statement does
 * not but can lead to the company being struck off.
 */
class CompanyFilingDueNotification extends Notification
{
    public const TYPE_ACCOUNTS = 'accounts';

    public const TYPE_CONFIRMATION = 'confirmation';

    public function __construct(
        private readonly string $businessName,
        private readonly string $filingType,
        private readonly string $dueDate,
        private readonly int $daysUntil,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title(),
            'body' => $this->body(),
            'type' => 'company_filing_due',
            'data' => [
                'business_name' => $this->businessName,
                'filing_type' => $this->filingType,
                'due_date' => $this->dueDate,
                'days_until' => $this->daysUntil,
                'overdue' => $this->daysUntil < 0,
            ],
        ];
    }

    private function isAccounts(): bool
    {
        return $this->filingType === self::TYPE_ACCOUNTS;
    }

    private function label(): string
    {
        return $this->isAccounts() ? 'annual accounts' : 'confirmation statement';
    }

    private function title(): string
    {
        $filing = $this->isAccounts() ? 'Annual accounts' : 'Confirmation statement';

        if ($this->daysUntil < 0) {
            return $filing.' overdue';
        }

        return $this->daysUntil === 0
            ? $filing.' due today'
            : $filing.' due soon';
    }

    private function body(): string
    {
        $date = Carbon::parse($this->dueDate)->format('j F Y');
        $verb = $this->isAccounts() ? 'are' : 'is';

        if ($this->daysUntil < 0) {
            $days = abs($this->daysUntil);
            $ago = $days === 1 ? '1 day ago' : "{$days} days ago";

            return "The {$this->label()} for {$this->businessName} {$verb} overdue. "
                ."The deadline was {$date}, {$ago}. "
                .$this->overdueConsequence();
        }

        if ($this->daysUntil === 0) {
            return "The {$this->label()} for {$this->businessName} {$verb} due today, {$date}. "
                .'Filing after the deadline '.$this->lateConsequence();
        }

        $days = $this->daysUntil === 1 ? '1 day' : "{$this->daysUntil} days";

        return "The {$this->label()} for {$this->businessName} {$verb} due on {$date}, in {$days}.";
    }

    private function overdueConsequence(): string
    {
        return $this->isAccounts()
            ? 'Companies House charges an automatic late filing penalty, which rises the longer the accounts are outstanding and doubles if you also filed late in the previous year. File as soon as you can to stop it increasing.'
            : 'There is no financial penalty for a late confirmation statement, but Companies House can strike the company off the register and prosecute its directors.';
    }

    private function lateConsequence(): string
    {
        return $this->isAccounts()
            ? 'triggers an automatic late filing penalty from Companies House.'
            : 'can lead to the company being struck off the register.';
    }
}
