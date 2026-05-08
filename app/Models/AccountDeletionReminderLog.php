<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountDeletionReminderLog extends Model
{
    public $timestamps = false;

    protected $table = 'account_deletion_reminder_log';

    protected $fillable = ['user_id', 'days_remaining', 'sent_at'];

    protected $casts = ['sent_at' => 'datetime'];
}
