@component('mail::message')
# Hi {{ $user->first_name ?? '' }},

Your scheduled account deletion has been cancelled. Your account remains active and you can keep using Fynla as normal.

Thanks,
Fynla
@endcomponent
