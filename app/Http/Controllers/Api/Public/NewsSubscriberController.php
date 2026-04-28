<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Mail\Newsletter\NewsletterConfirmationMail;
use App\Models\News\NewsSubscriber;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;

class NewsSubscriberController extends Controller
{
    private const RATE_LIMIT_KEY_PREFIX = 'news-subscribe:';
    private const RATE_LIMIT_MAX_ATTEMPTS = 3;
    private const RATE_LIMIT_DECAY_SECONDS = 300;

    public function subscribe(Request $request): JsonResponse
    {
        $key = self::RATE_LIMIT_KEY_PREFIX.$request->ip();

        if (RateLimiter::tooManyAttempts($key, self::RATE_LIMIT_MAX_ATTEMPTS)) {
            return response()->json([
                'success' => false,
                'status' => 'rate_limited',
                'message' => 'Too many attempts. Please try again in a few minutes.',
            ], 429);
        }

        $validated = $request->validate([
            'email' => 'required|email|max:255',
        ]);

        RateLimiter::hit($key, self::RATE_LIMIT_DECAY_SECONDS);

        $email = strtolower(trim($validated['email']));

        if (User::where('email', $email)->exists()) {
            return response()->json([
                'success' => true,
                'status' => 'already_registered',
                'message' => "You're already registered with Fynla — sign in to manage your news preferences.",
            ]);
        }

        $subscriber = NewsSubscriber::firstOrNew(['email' => $email]);

        if ($subscriber->exists && $subscriber->isConfirmed()) {
            return response()->json([
                'success' => true,
                'status' => 'already_confirmed',
                'message' => "You're already subscribed — thanks!",
            ]);
        }

        $wasRecentlyCreated = ! $subscriber->exists;

        $subscriber->fill([
            'confirmation_token' => NewsSubscriber::generateToken(),
            'confirmed_at' => null,
            'unsubscribed_at' => null,
            'ip_address' => $request->ip(),
            'source' => 'news_hub',
        ])->save();

        Mail::to($subscriber->email)->send(new NewsletterConfirmationMail($subscriber));

        $message = $wasRecentlyCreated
            ? 'Check your inbox to confirm your subscription.'
            : 'Confirmation email re-sent — check your inbox.';

        return response()->json([
            'success' => true,
            'status' => 'pending_confirmation',
            'message' => $message,
        ]);
    }
}
