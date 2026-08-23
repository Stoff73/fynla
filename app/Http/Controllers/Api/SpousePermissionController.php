<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Exceptions\SpouseCollisionException;
use App\Http\Controllers\Controller;
use App\Http\Traits\SanitizedErrorResponse;
use App\Models\FamilyMember;
use App\Models\SpousePermission;
use App\Models\User;
use App\Notifications\SpousePermissionRequest;
use App\Services\Onboarding\SpouseLinkingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SpousePermissionController extends Controller
{
    use SanitizedErrorResponse;

    public function __construct(
        private readonly SpouseLinkingService $spouseLinking,
    ) {}

    /**
     * Get the current permission status with spouse
     *
     * GET /api/spouse-permission/status
     */
    /**
     * The minimum a client needs to name the other party.
     *
     * Never the model. A raw `User` here shipped date of birth, full address,
     * phone, occupation, employer, every `annual_*_income` column, monthly and
     * annual expenditure with all 21 category columns, health and smoking
     * status and domicile — `$hidden` strips only credentials and the national
     * insurance number, so every column added to `users` afterwards would have
     * joined them automatically (W-0348). The UI renders a name and nothing
     * else.
     *
     * @return array{id: int, first_name: ?string, surname: ?string, name: ?string}
     */
    private function counterparty(User $other): array
    {
        return [
            'id' => $other->id,
            'first_name' => $other->first_name,
            'surname' => $other->surname,
            'name' => $other->name,
            // Safe in both directions this is used: an invitee being asked to
            // link needs to know who is asking, and the requester chose to
            // disclose themselves by asking. It is NOT used for the reverse —
            // see the outgoing-invitation branch in status().
            'email' => $other->email,
        ];
    }

    public function status(Request $request): JsonResponse
    {
        $user = $request->user();

        // An invitation addressed to this user, still unanswered. Checked FIRST
        // and independently of `spouse_id`, because under the invitation flow
        // (W-0347) the invitee has no link — that is the whole point, and a
        // status endpoint that reported "no spouse" here would leave the
        // request permanently invisible to the only person who can answer it.
        $incoming = SpousePermission::where('spouse_id', $user->id)
            ->where('status', 'pending')
            ->first();

        if ($incoming) {
            $requester = User::find($incoming->user_id);

            return response()->json([
                'success' => true,
                'data' => [
                    'has_spouse' => true,
                    'spouse' => $requester ? $this->counterparty($requester) : null,
                    'permission' => $incoming,
                    'can_view_spouse_data' => false,
                    'awaiting_your_response' => true,
                ],
            ]);
        }

        // An invitation this user sent that has not been answered yet.
        $outgoing = SpousePermission::where('user_id', $user->id)
            ->where('status', 'pending')
            ->first();

        if ($outgoing && ! $user->spouse_id) {
            // Deliberately the CALLER'S OWN family-member card, never the
            // invitee's account. Returning the account holder's real name here
            // would answer "who owns this address?" for any address the caller
            // typed — the enumeration this flow exists to close (W-0349). Until
            // they accept, the caller sees back only what they themselves
            // entered.
            $ownCard = FamilyMember::where('user_id', $user->id)
                ->where('relationship', 'spouse')
                ->first();

            return response()->json([
                'success' => true,
                'data' => [
                    'has_spouse' => true,
                    'spouse' => [
                        'id' => null,
                        'name' => $ownCard?->name,
                        'email' => null,
                    ],
                    'permission' => $outgoing,
                    'can_view_spouse_data' => false,
                    'awaiting_their_response' => true,
                ],
            ]);
        }

        // A spouse in family_members with no account link (may never have had one).
        $spouseFamilyMember = FamilyMember::where('user_id', $user->id)
            ->where('relationship', 'spouse')
            ->first();

        if (! $user->spouse_id && ! $spouseFamilyMember) {
            return response()->json([
                'success' => true,
                'data' => [
                    'has_spouse' => false,
                    'spouse' => null,
                    'permission' => null,
                ],
            ]);
        }

        if ($user->spouse_id) {
            // Only a LIVE spouse counts: `spouse_id` deliberately survives the
            // partner deleting their account, and sharing must not.
            $spouse = $user->liveSpouse();

            if ($spouse === null) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'has_spouse' => false,
                        'spouse' => null,
                        'permission' => null,
                    ],
                ]);
            }

            $permission = SpousePermission::where(function ($query) use ($user, $spouse) {
                $query->where('user_id', $user->id)
                    ->where('spouse_id', $spouse->id);
            })->orWhere(function ($query) use ($user, $spouse) {
                $query->where('user_id', $spouse->id)
                    ->where('spouse_id', $user->id);
            })->first();

            return response()->json([
                'success' => true,
                'data' => [
                    'has_spouse' => true,
                    'spouse' => $this->counterparty($spouse),
                    'permission' => $permission,
                    'can_view_spouse_data' => $permission && $permission->status === 'accepted',
                ],
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'has_spouse' => true,
                'spouse' => [
                    'id' => null,
                    'name' => $spouseFamilyMember->name,
                    'email' => null,
                ],
                'permission' => null,
                'can_view_spouse_data' => false,
                'requires_account_link' => true,
                'message' => 'Your spouse needs an account to enable data sharing. Add their email in the Family Members section.',
            ],
        ]);
    }

    /**
     * Request permission to view spouse's data
     *
     * POST /api/spouse-permission/request
     */
    public function request(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->spouse_id) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have a linked spouse',
            ], 422);
        }

        // Check if permission already exists
        $existingPermission = SpousePermission::where(function ($query) use ($user) {
            $query->where('user_id', $user->id)
                ->where('spouse_id', $user->spouse_id);
        })->orWhere(function ($query) use ($user) {
            $query->where('user_id', $user->spouse_id)
                ->where('spouse_id', $user->id);
        })->first();

        if ($existingPermission) {
            return response()->json([
                'success' => false,
                'message' => 'A permission request already exists',
                'data' => ['permission' => $existingPermission],
            ], 422);
        }

        // Create new permission request
        $permission = SpousePermission::create([
            'user_id' => $user->id,
            'spouse_id' => $user->spouse_id,
            'status' => 'pending',
            'requested_at' => now(),
        ]);

        // Send notification/email to spouse
        $spouse = User::find($user->spouse_id);
        if ($spouse) {
            $spouse->notify(new SpousePermissionRequest($user->name));
        }

        return response()->json([
            'success' => true,
            'message' => 'Permission request sent to your spouse',
            'data' => ['permission' => $permission],
        ], 201);
    }

    /**
     * Accept a permission request
     *
     * POST /api/spouse-permission/accept
     */
    public function accept(Request $request): JsonResponse
    {
        $user = $request->user();

        // Deliberately NOT gated on `$user->spouse_id`. Under the invitation
        // flow (W-0347) the invitee has no link yet — accepting is what creates
        // it. Requiring a link here would have made every invitation
        // unacceptable, which is how the old code ended up forging the
        // acceptance in `SpouseLinkingService` instead.
        $permission = SpousePermission::where('spouse_id', $user->id)
            ->where('status', 'pending')
            ->when($user->spouse_id, fn ($q) => $q->where('user_id', $user->spouse_id))
            ->first();

        if (! $permission) {
            return response()->json([
                'success' => false,
                'message' => 'No pending permission request found',
            ], 404);
        }

        $requester = User::find($permission->user_id);

        if (! $requester) {
            return response()->json([
                'success' => false,
                'message' => 'No pending permission request found',
            ], 404);
        }

        // The invitee may have linked to somebody else while this sat pending.
        if ($user->spouse_id && (int) $user->spouse_id !== $requester->id) {
            return response()->json([
                'success' => false,
                'message' => 'You are already linked to a different spouse',
            ], 422);
        }

        try {
            $this->spouseLinking->establishAcceptedLink($requester, $user);
        } catch (SpouseCollisionException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        $permission->update([
            'status' => 'accepted',
            'responded_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Permission granted successfully',
            'data' => ['permission' => $permission->fresh()],
        ]);
    }

    /**
     * Reject a permission request
     *
     * POST /api/spouse-permission/reject
     */
    public function reject(Request $request): JsonResponse
    {
        $user = $request->user();

        // Same relaxation as accept(): a pending invitation exists precisely
        // because there is no link yet.
        $permission = SpousePermission::where('spouse_id', $user->id)
            ->where('status', 'pending')
            ->when($user->spouse_id, fn ($q) => $q->where('user_id', $user->spouse_id))
            ->first();

        if (! $permission) {
            return response()->json([
                'success' => false,
                'message' => 'No pending permission request found',
            ], 404);
        }

        $permission->update([
            'status' => 'rejected',
            'responded_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Permission request rejected',
            'data' => ['permission' => $permission->fresh()],
        ]);
    }

    /**
     * Revoke permission (can be done by either spouse)
     *
     * DELETE /api/spouse-permission/revoke
     */
    public function revoke(Request $request): JsonResponse
    {
        $user = $request->user();

        // Not gated on `$user->spouse_id`. This endpoint is also what the
        // requester's "Cancel Request" button calls, and under the invitation
        // flow (W-0347) a requester with an unanswered invitation has no link
        // yet — the old guard would have refused to let them withdraw it.
        //
        // Either party, either direction, linked or merely invited.
        $query = SpousePermission::query();

        if ($user->spouse_id) {
            // Linked: only the row for THAT link, either direction. Never a
            // stale row naming somebody else.
            $query->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)->where('spouse_id', $user->spouse_id);
            })->orWhere(function ($q) use ($user) {
                $q->where('user_id', $user->spouse_id)->where('spouse_id', $user->id);
            });
        } else {
            // Not linked: an unanswered invitation this user sent or received.
            $query->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)->orWhere('spouse_id', $user->id);
            });
        }

        $permission = $query->first();

        if (! $permission) {
            return response()->json([
                'success' => false,
                'message' => 'No permission found to revoke',
            ], 404);
        }

        // Marked, not deleted. A deleted row left no record that anyone had
        // decided anything, and `hasAcceptedSpousePermission()` reads the
        // absence of a row as "this link predates the consent flow" and honours
        // it — so deleting the row switched sharing back ON. It also loses the
        // audit trail of a withdrawal, which an FCA-regulated product should
        // not do.
        $permission->update([
            'status' => 'rejected',
            'responded_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Permission revoked successfully',
        ]);
    }
}
