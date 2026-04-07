<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;

class ContactFormController extends Controller
{
    private const REASON_EMAILS = [
        'general' => 'hello@fynla.org',
        'support' => 'support@fynla.org',
        'press' => 'press@fynla.org',
    ];

    public function submit(Request $request): JsonResponse
    {
        $key = 'contact-form:'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, 3)) {
            return response()->json([
                'message' => 'Too many submissions. Please try again later.',
            ], 429);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'reason' => 'required|in:general,support,press',
            'message' => 'required|string|max:5000',
        ]);

        $toEmail = self::REASON_EMAILS[$validated['reason']];

        $reasonLabels = [
            'general' => 'General enquiry',
            'support' => 'Technical support',
            'press' => 'Press and media',
        ];

        Mail::raw(
            "New contact form submission\n\n".
            "Name: {$validated['name']}\n".
            "Email: {$validated['email']}\n".
            "Reason: {$reasonLabels[$validated['reason']]}\n\n".
            "Message:\n{$validated['message']}",
            function ($mail) use ($toEmail, $validated, $reasonLabels) {
                $mail->to($toEmail)
                    ->replyTo($validated['email'], $validated['name'])
                    ->subject("Fynla contact: {$reasonLabels[$validated['reason']]} from {$validated['name']}");
            }
        );

        RateLimiter::hit($key, 300);

        return response()->json([
            'message' => 'Your message has been sent. We\'ll get back to you soon.',
        ]);
    }
}
