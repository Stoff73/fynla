@component('mail::message')
# Hi {{ $user->first_name ?? '' }},

Your Fynla account is scheduled for deletion on {{ $executesAt->format('j F Y') }}.

You can cancel this at any time before that date by going to **Settings → Privacy** in your dashboard.

Thanks,
Fynla
@endcomponent
