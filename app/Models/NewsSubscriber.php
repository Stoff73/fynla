<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class NewsSubscriber extends Model
{
    use HasFactory;

    protected $fillable = [
        'email',
        'confirmation_token',
        'confirmed_at',
        'unsubscribed_at',
        'ip_address',
        'source',
    ];

    protected $casts = [
        'confirmed_at' => 'datetime',
        'unsubscribed_at' => 'datetime',
    ];

    public static function generateToken(): string
    {
        return Str::random(48);
    }

    public function scopeConfirmed($query)
    {
        return $query->whereNotNull('confirmed_at')->whereNull('unsubscribed_at');
    }

    public function scopePending($query)
    {
        return $query->whereNull('confirmed_at')->whereNull('unsubscribed_at');
    }

    public function scopeUnsubscribed($query)
    {
        return $query->whereNotNull('unsubscribed_at');
    }

    public function isConfirmed(): bool
    {
        return $this->confirmed_at !== null && $this->unsubscribed_at === null;
    }

    public function isPending(): bool
    {
        return $this->confirmed_at === null && $this->unsubscribed_at === null;
    }
}
