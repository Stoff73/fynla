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
            ->orderBy('id')
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
            ->orderBy('id')
            ->first();

        // W-0347 G1 (compliance-lead, 2026-08-24) — this used to require
        // `! $user->spouse_id`, on the reasoning that an unanswered invitation means
        // there is no link yet. The re-ask migration breaks that assumption: it leaves
        // households **reciprocally linked AND holding a pending row**, which is the
        // MODAL state after release, not a corner case. The requester fell through to
        // the linked branch with no `awaiting_*` flag at all, and `/m` — which reads
        // only those flags — rendered "Sharing is off" with an "Ask to share again"
        // button that answers 422. They were not told a request was outstanding and
        // could not cancel it.
        //
        // Fixed HERE rather than in the two components: one condition serves web,
        // `/m` and native from the one endpoint (Rule 20). The `spouse` payload still
        // withholds the invitee's account details in the unlinked case, which is what
        // W-0349 closed — that distinction is made below, not by this branch test.
        if ($outgoing) {
            // Deliberately the CALLER'S OWN family-member card, never the
            // invitee's account. Returning the account holder's real name here
            // would answer "who owns this address?" for any address the caller
            // typed — the enumeration this flow exists to close (W-0349). Until
            // they accept, the caller sees back only what they themselves
            // entered.
            $ownCard = FamilyMember::where('user_id', $user->id)
                ->where('relationship', 'spouse')
                ->first();

            // Once the accounts ARE linked the counterparty is already known to this
            // user — they linked to them — so withholding the name here would tell
            // them less than the screen they can already see. The withholding applies
            // to the case W-0349 closed: an invitation to an address that may or may
            // not hold an account, where the name would answer "who owns this?".
            $linkedSpouse = $user->spouse_id ? User::find($user->spouse_id) : null;

            return response()->json([
                'success' => true,
                'data' => [
                    'has_spouse' => true,
                    'spouse' => $linkedSpouse
                        ? $this->counterparty($linkedSpouse)
                        : [
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
            })
                // W-0347 F5 — the one read that was left unordered, and the one that
                // matters most: it DRAWS THE SCREEN and sets `can_view_spouse_data`.
                // Unordered, it and `hasAcceptedSpousePermission()` could pick
                // different rows for the same couple, so the user is told sharing is
                // off while the gate says on, or the reverse — F5's harm expressed on
                // the surface people look at for the truth.
                ->orderBy('id')
                ->first();

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

        // W-0349. This sentence used to read "Add their email in the Family
        // Members section", which after CSJ's 2026-08-23 decision told a user
        // who had just done exactly that to go and do it. Supplying the email
        // no longer creates and links an account — it sends an invitation.
        //
        // **One sentence, and no conditional.** A first version added "If you have
        // given us their email address, we have already invited them", which
        // `compliance-lead` showed cannot be true where it prints: a LIVE
        // invitation is caught by the outgoing-pending branch above (`:88-115`),
        // so the only way to reach here having supplied an address is that the
        // invitation was **declined, rejected or withdrawn**. In that one case the
        // sentence would conceal a refusal behind a reassurance, on a consent
        // surface — worse than saying less.
        //
        // The honest three-state version — never asked / invited and waiting /
        // invited and declined — is not writable, because `family_members` has no
        // email column and the invitation is not retained (W-0472). That is the
        // second consequence of the retention gap, and it belongs on that item
        // rather than being worked around in copy.
        //
        // Note also `sendInvitationNotification()` swallows a send failure and
        // returns false, so any future copy asserting "we have invited them" must
        // read that boolean rather than assume it.
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
                'message' => 'Nothing can be shared until your partner has their own Fynla account and accepts the link.',
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
        })->orderBy('id')->first();

        // W-0347 F4 — withdrawal used to be a one-way door. This refused while
        // ANY row existed, and `revoke()` leaves a `rejected` row behind rather
        // than deleting it, so once sharing was off neither party could turn it
        // back on through any interface. A withdrawal a user cannot reverse is
        // one they will hesitate to make, which makes the consent worth less,
        // not more. A settled `rejected` row can be asked again; a `pending` or
        // `accepted` one still stands and is still refused.
        if ($existingPermission && $existingPermission->status !== 'rejected') {
            return response()->json([
                'success' => false,
                'message' => 'A permission request already exists',
                'data' => ['permission' => $existingPermission],
            ], 422);
        }

        if ($existingPermission) {
            // Asked again on the same row rather than a second one — the unique
            // key would refuse the insert in one direction and permit a
            // contradictory mirror in the other (F5).
            $existingPermission->update([
                'user_id' => $user->id,
                'spouse_id' => $user->spouse_id,
                'status' => 'pending',
                'requested_at' => now(),
                'responded_at' => null,
            ]);

            $permission = $existingPermission->fresh();
        } else {
            $permission = SpousePermission::create([
                'user_id' => $user->id,
                'spouse_id' => $user->spouse_id,
                'status' => 'pending',
                'requested_at' => now(),
            ]);
        }

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
            ->orderBy('id')
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
            ->orderBy('id')
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

        // W-0347 F5 — ordered, so this and `hasAcceptedSpousePermission()` cannot
        // pick different rows for the same couple and leave a withdrawal undone.
        $permission = $query->orderBy('id')->first();

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
