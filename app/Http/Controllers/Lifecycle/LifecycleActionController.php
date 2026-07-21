<?php

declare(strict_types=1);

namespace App\Http\Controllers\Lifecycle;

use App\Http\Controllers\Controller;
use App\Models\FeedbackResponse;
use App\Models\LifecycleEmailLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;

/**
 * Handles magic-link actions from lifecycle emails.
 *
 * All routes are behind Laravel's `signed` middleware. Each method updates the
 * corresponding LifecycleEmailLog row with click metadata, performs its action
 * (or stores it in the session for post-login execution), and redirects to the
 * relevant SPA page — Fynla's web layer is SPA-routed, so targets here are
 * hard-coded string paths, not Laravel named routes.
 */
class LifecycleActionController extends Controller
{
    public function applyDiscount(Request $request): RedirectResponse
    {
        $userId = (int) $request->query('user_id');
        $campaign = (string) $request->query('campaign');
        $code = (string) $request->query('code');

        $this->markClicked($userId, $campaign, 'applied_discount');

        // Route through /dashboard with a lifecycle_discount query param so
        // AppLayout's trial-expired modal picks up the code and threads it
        // through PlanSelectionModal → handlePlanSelect → /checkout?...&discount=CODE.
        // CheckoutPage's existing prefilledDiscountCode logic then auto-applies.
        $target = '/dashboard?lifecycle_discount='.rawurlencode($code);

        if (auth()->check() && auth()->id() === $userId) {
            return redirect($target);
        }

        return redirect('/login?redirect='.rawurlencode($target));
    }

    public function feedback(Request $request): View
    {
        $userId = (int) $request->query('user_id');
        $campaign = (string) $request->query('campaign');
        $reason = (string) $request->query('reason');

        $allowedReasons = config("lifecycle.feedback_reasons.{$campaign}", []);
        abort_unless(in_array($reason, $allowedReasons, true), 400);

        FeedbackResponse::updateOrCreate(
            ['user_id' => $userId, 'campaign' => $campaign],
            ['reason_code' => $reason, 'clicked_at' => now()],
        );

        $this->markClicked($userId, $campaign, "feedback:{$reason}");

        // Re-sign for the POST target — signed URL signatures are path-specific,
        // so the original /lifecycle/feedback signature can't be reused on the
        // /lifecycle/feedback-text path. Generate a fresh, short-lived signed
        // URL scoped to this user+campaign so the free-text form POST can pass
        // the signed middleware.
        $feedbackTextUrl = URL::temporarySignedRoute(
            'lifecycle.feedback-text',
            now()->addHour(),
            ['user_id' => $userId, 'campaign' => $campaign]
        );

        return view('lifecycle.feedback-thanks', [
            'campaign' => $campaign,
            'reason' => $reason,
            'user_id' => $userId,
            'feedback_text_url' => $feedbackTextUrl,
        ]);
    }

    public function submitFeedbackText(Request $request): View
    {
        $request->validate(['free_text' => 'required|string|max:2000']);

        FeedbackResponse::where('user_id', (int) $request->input('user_id'))
            ->where('campaign', (string) $request->input('campaign'))
            ->update([
                'free_text' => $request->input('free_text'),
                'text_submitted_at' => now(),
            ]);

        return view('lifecycle.feedback-text-thanks');
    }

    public function updatePayment(Request $request): RedirectResponse
    {
        $userId = (int) $request->query('user_id');

        $this->markClicked($userId, 'lapsed_subscriber', 'clicked_update_payment');

        // Subscription management lives in the UserProfile SPA view under a
        // tab, so /profile is the closest equivalent to the plan's
        // route('account.billing'). The user needs to click the Subscription
        // tab once landed — deep-linking the tab is a separate UX task.
        $profilePath = '/profile';

        if (auth()->check() && auth()->id() === $userId) {
            return redirect($profilePath);
        }

        return redirect('/login?redirect='.rawurlencode($profilePath));
    }

    private function markClicked(int $userId, string $campaign, string $action): void
    {
        LifecycleEmailLog::where('user_id', $userId)
            ->where('campaign', $campaign)
            ->whereNull('clicked_at')
            ->update([
                'clicked_at' => now(),
                'action_taken' => $action,
            ]);
    }
}
