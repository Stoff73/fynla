<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\SanitizedErrorResponse;
use App\Mail\BugReportMail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class BugReportController extends Controller
{
    use SanitizedErrorResponse;

    /**
     * Submit a bug report.
     *
     * Accepts bug reports from both authenticated and guest users.
     * Rate limited to 5 reports per hour per user/IP.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'description' => 'required|string|max:5000',
            'expected_behaviour' => 'nullable|string|max:2000',
            'console_logs' => 'nullable|string|max:10000',
            'page_url' => 'nullable|string|max:500',
            'user_agent' => 'nullable|string|max:500',
            'screen_size' => 'nullable|string|max:50',
            'viewport_size' => 'nullable|string|max:50',
            'client_timestamp' => 'nullable|string|max:50',
        ]);

        // Get user info if authenticated
        $user = $request->user();

        $bugReportData = [
            'user_id' => $user?->id,
            'user_name' => $user ? trim(($user->first_name ?? '').' '.($user->last_name ?? '')) : null,
            'user_email' => $user?->email,
            'is_preview_user' => $user?->is_preview_user ?? false,
            'description' => $validated['description'],
            'expected_behaviour' => $validated['expected_behaviour'] ?? null,
            'console_logs' => $validated['console_logs'] ?? null,
            'page_url' => $validated['page_url'] ?? null,
            'user_agent' => $validated['user_agent'] ?? null,
            'screen_size' => $validated['screen_size'] ?? null,
            'viewport_size' => $validated['viewport_size'] ?? null,
            'ip_address' => $request->ip(),
            'client_timestamp' => $validated['client_timestamp'] ?? null,
            'server_timestamp' => now()->toIso8601String(),
        ];

        try {
            Mail::to('chris@fynla.org')->send(new BugReportMail($bugReportData));

            Log::info('Bug report submitted', [
                'user_id' => $user?->id,
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Bug report submitted successfully. Thank you for your feedback!',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send bug report email', [
                'error' => $e->getMessage(),
                'user_id' => $user?->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to submit bug report. Please try again later.',
            ], 500);
        }
    }
}
