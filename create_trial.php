<?php

// Run on server: php create_trial.php
// Then delete: rm create_trial.php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$user = \App\Models\User::where('email', 'chris@fynla.org')->first();

if (! $user) {
    echo "User not found\n";
    exit;
}

if ($user->subscription) {
    echo "Subscription already exists: {$user->subscription->status}\n";
    exit;
}

$user->subscription()->create([
    'plan' => 'standard',
    'billing_cycle' => 'yearly',
    'status' => 'trialing',
    'amount' => 0,
    'trial_started_at' => now(),
    'trial_ends_at' => now()->addDays(14),
    'current_period_start' => now(),
    'current_period_end' => now()->addDays(14),
]);

echo "Trial subscription created for {$user->email}\n";
