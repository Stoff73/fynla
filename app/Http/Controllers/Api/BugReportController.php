<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\SanitizedErrorResponse;
use App\Mail\BugReportMail;
use App\Models\AiConversation;
use App\Services\Integrations\GithubIssueService;
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
     * Authenticated users only (route is gated by `auth:sanctum`).
     * Rate limited to 5 reports per hour per user.
     *
     * All user-supplied text is run through strip_tags() as defence-in-depth
     * (the global SanitizeInput middleware also strips tags, but the
     * controller defending its own surface is the secure pattern). The
     * downstream Blade view (`resources/views/emails/bug-report.blade.php`)
     * escapes via `{{ }}` so mail clients cannot interpret any residual
     * HTML.
     *
     * Console logs are capped at 2048 chars to limit the size of
     * attacker-controlled content that can be staged into an inbound
     * email — see REVIEW Top-10 #8 / W1-M.
     *
     * Mail dispatch is queued so abuse can be detected before delivery
     * (on `sync` queue connection the queued job still runs synchronously
     * inside the request, but production uses a real connection).
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'description' => 'required|string|max:5000',
            'expected_behaviour' => 'nullable|string|max:2000',
            'console_logs' => 'nullable|string|max:2048',
            'page_url' => 'nullable|string|max:500',
            'user_agent' => 'nullable|string|max:500',
            'screen_size' => 'nullable|string|max:50',
            'viewport_size' => 'nullable|string|max:50',
            'client_timestamp' => 'nullable|string|max:50',
            'category' => 'nullable|string|max:50',
            'severity' => 'nullable|string|max:20',
            'app_version' => 'nullable|string|max:50',
            'app_build' => 'nullable|string|max:50',
            'environment' => 'nullable|string|max:50',
            'request_correlation_id' => 'nullable|string|max:100',
            'native_session_uuid' => 'nullable|uuid',
            'device_model' => 'nullable|string|max:100',
            'os_version' => 'nullable|string|max:100',
            'platform' => 'nullable|string|max:20',
            'route' => 'nullable|string|max:200',
            'conversation_id' => 'nullable|integer',
        ]);

        $user = $request->user();

        $bugReportData = [
            'user_id' => $user->id,
            'user_name' => trim(($user->first_name ?? '').' '.($user->surname ?? '')),
            'user_email' => $user->email,
            'is_preview_user' => $user->is_preview_user ?? false,
            'description' => strip_tags($validated['description']),
            'expected_behaviour' => isset($validated['expected_behaviour'])
                ? strip_tags($validated['expected_behaviour'])
                : null,
            'console_logs' => isset($validated['console_logs'])
                ? strip_tags($validated['console_logs'])
                : null,
            'page_url' => isset($validated['page_url'])
                ? strip_tags($validated['page_url'])
                : null,
            'user_agent' => isset($validated['user_agent'])
                ? strip_tags($validated['user_agent'])
                : null,
            'screen_size' => $validated['screen_size'] ?? null,
            'viewport_size' => $validated['viewport_size'] ?? null,
            'ip_address' => $request->ip(),
            'client_timestamp' => $validated['client_timestamp'] ?? null,
            'server_timestamp' => now()->toIso8601String(),
            'category' => isset($validated['category']) ? strip_tags($validated['category']) : null,
            'severity' => isset($validated['severity']) ? strip_tags($validated['severity']) : null,
            'app_version' => isset($validated['app_version']) ? strip_tags($validated['app_version']) : null,
            'app_build' => isset($validated['app_build']) ? strip_tags($validated['app_build']) : null,
            'environment' => isset($validated['environment']) ? strip_tags($validated['environment']) : null,
            'request_correlation_id' => isset($validated['request_correlation_id'])
                ? strip_tags($validated['request_correlation_id'])
                : null,
            'native_session_uuid' => $validated['native_session_uuid'] ?? null,
            'device_model' => isset($validated['device_model']) ? strip_tags($validated['device_model']) : null,
            'os_version' => isset($validated['os_version']) ? strip_tags($validated['os_version']) : null,
            'platform' => isset($validated['platform']) ? strip_tags($validated['platform']) : null,
            'route' => isset($validated['route']) ? strip_tags($validated['route']) : null,
            'conversation_id' => $validated['conversation_id'] ?? null,
            // Fyn conversation transcript, when the report was filed from the
            // chat. Ownership-scoped (forUser) so a user can only attach their
            // own conversation — never another user's by guessing an id.
            'fyn_transcript' => ($validated['platform'] ?? null) === 'ios'
                ? null
                : $this->captureTranscript($user->id, $validated['conversation_id'] ?? null),
        ];

        try {
            Mail::to('chris@fynla.org')->queue(new BugReportMail($bugReportData));

            // Best-effort: also raise a GitHub issue so the autonomous Claude
            // workflow can pick it up. Never lets a GitHub failure break the
            // request — the email above is the source of truth.
            $issue = GithubIssueService::fromConfig()->createBugIssue($bugReportData);

            Log::info('Bug report submitted', [
                'user_id' => $user->id,
                'ip' => $request->ip(),
                'github_issue' => $issue['number'] ?? null,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Bug report submitted successfully. Thank you for your feedback!',
                'issue_url' => $issue['html_url'] ?? null,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send bug report email', [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to submit bug report. Please try again later.',
            ], 500);
        }
    }

    /**
     * Build a plain-text transcript of a Fyn conversation to attach to the bug
     * report, so a bug filed from the chat carries what Fyn actually did
     * (e.g. a duplicated startup message).
     *
     * Ownership-scoped via `forUser` — a user can only ever attach their own
     * conversation; a guessed/foreign id resolves to null, never another user's
     * data. Capped to the most recent turns to bound the payload.
     */
    private function captureTranscript(int $userId, ?int $conversationId): ?string
    {
        if ($conversationId === null) {
            return null;
        }

        $conversation = AiConversation::forUser($userId)->find($conversationId);
        if ($conversation === null) {
            return null;
        }

        $messages = $conversation->messages()
            ->whereIn('role', ['user', 'assistant'])
            ->orderByDesc('created_at')
            ->limit(40)
            ->get(['role', 'content'])
            ->reverse();

        if ($messages->isEmpty()) {
            return null;
        }

        $lines = $messages->map(function ($m) {
            $who = $m->role === 'user' ? 'User' : 'Fyn';

            return $who.': '.trim((string) $m->content);
        })->implode("\n");

        // Bound the size — the rest is truncated by GithubIssueService too.
        return mb_substr($lines, 0, 8000);
    }
}
