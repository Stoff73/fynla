@component('mail::message')
# Hi {{ $user->first_name ?? '' }},

Your Fynla account has been deleted. Your records are retained for {{ config('retention.account_years', 7) }} years for regulatory compliance, after which they will be permanently deleted.

You can restore your account at any time within that period by signing in with your existing credentials.

Thanks,
Fynla
@endcomponent
