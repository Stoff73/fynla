<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Mail\Newsletter\NewsletterWelcomeMail;
use App\Models\News\NewsSubscriber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Mail;

class NewsletterActionController extends Controller
{
    public function confirm(string $token): RedirectResponse
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

        return redirect('/news?subscribed='.($alreadyConfirmed ? 'already' : '1'));
    }

    public function unsubscribe(string $token): RedirectResponse
    {
        $subscriber = NewsSubscriber::where('confirmation_token', $token)->firstOrFail();

        if ($subscriber->unsubscribed_at === null) {
            $subscriber->update(['unsubscribed_at' => now()]);
        }

        return redirect('/news?unsubscribed=1');
    }
}
