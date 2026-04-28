<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Mail\Newsletter\NewsletterWelcomeMail;
use App\Models\News\NewsSubscriber;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Mail;

class NewsletterActionController extends Controller
{
    public function confirm(string $token): View
    {
        $subscriber = NewsSubscriber::where('confirmation_token', $token)->firstOrFail();

        $alreadyConfirmed = $subscriber->isConfirmed();

        if (! $alreadyConfirmed) {
            $subscriber->update([
                'confirmed_at' => now(),
                'unsubscribed_at' => null,
            ]);

            Mail::to($subscriber->email)->queue(new NewsletterWelcomeMail($subscriber));
        }

        return view('newsletter.confirmed', [
            'email' => $subscriber->email,
            'alreadyConfirmed' => $alreadyConfirmed,
        ]);
    }

    public function unsubscribe(string $token): View
    {
        $subscriber = NewsSubscriber::where('confirmation_token', $token)->firstOrFail();

        if ($subscriber->unsubscribed_at === null) {
            $subscriber->update(['unsubscribed_at' => now()]);
        }

        return view('newsletter.unsubscribed', [
            'email' => $subscriber->email,
        ]);
    }
}
