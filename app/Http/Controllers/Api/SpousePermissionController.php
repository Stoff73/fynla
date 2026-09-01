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
            // Once the accounts ARE linked the counterparty is already known to this
            // user — they linked to them — so withholding the name here would tell
            // them less than the screen they can already see. The withholding applies
            // to the case W-0349 closed: an invitation to an address that may or may
            // not hold an account, where the name would answer "who owns this?".
            $linkedSpouse = $user->spouse_id ? User::find($user->spouse_id) : null;

            if ($linkedSpouse) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'has_spouse' => true,
                        'spouse' => $this->counterparty($linkedSpouse),
                        'permission' => $outgoing,
                        'can_view_spouse_data' => false,
                        'awaiting_their_response' => true,
                    ],
                ]);
            }

            // W-0476 — an unanswered invitation from an UNLINKED caller returns the
            // one shape below whether or not the invitee holds an account. It used to
            // return this branch's payload when they did and the family-member branch
            // at the foot of this method when they did not: five keys against six, and
            // `permission` carrying the invitee's user id, which exists only because
            // the address is registered. Pressing Withdraw then finished the job — see
            // `revoke()`.
            return $this->unansweredInvitationStatus($user);
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

        // A spouse recorded in `family_members` with no account link and no pending
        // row. Same shape as an unanswered invitation to a registered address — see
        // `unansweredInvitationStatus()` for why they must be indistinguishable.
        return $this->unansweredInvitationStatus($user);
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

        // W-0476 — a missing row is not an error, and saying so was an account
        // enumeration oracle. `SpouseLinkingService` can only create a permission row
        // when the invited address holds an account, so "no row" meant "that address
        // is not registered" — and the caller learned it by pressing Withdraw. The
        // 404 distinguished the two addresses even after `status()` was made to
        // return the same shape for both, which is the mistake this item records:
        // the disclosure re-formed one button further on.
        //
        // Revocation is idempotent by nature: the caller asked for sharing to be off
        // and sharing is off. Reporting success asserts the end state, not the
        // existence of a row, and W-0472's decision not to retain an invited address
        // means there will never be a row to find for an unregistered invitee.
        if (! $permission) {
            return response()->json([
                'success' => true,
                'message' => 'Permission revoked successfully',
            ]);
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

    /**
     * The one shape for "this user has a partner recorded who has not accepted".
     *
     * **Both invitation states return this, and that is the whole point (W-0476).**
     * Only a REGISTERED invitee has a user id to key a `SpousePermission` row on, so
     * every branch that varied with the row's existence answered "is that address
     * registered?" for any address the caller typed. W-0349 closed that on the POST
     * and it re-formed here, on the status endpoint the screen polls straight after.
     *
     * Three things are withheld, each because it exists only in the registered case:
     * the permission row (its `spouse_id` IS the invitee's account id), the invitee's
     * account details, and the key set itself — a payload with five keys for one
     * branch and six for the other is an oracle whatever the values are.
     *
     * The name returned is the CALLER'S OWN family-member card. Until the invitee
     * accepts, the caller sees back only what they themselves entered.
     *
     * The sentence has to be true in both states, so it names acceptance rather than
     * account creation: an invitee who already holds an account is not waiting to make
     * one. `requires_account_link` keeps its name and its value — `/m` reads it to
     * draw "Not shared yet" — and both states genuinely are "not linked yet".
     */
    private function unansweredInvitationStatus(User $user): JsonResponse
    {
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
                'permission' => null,
                'can_view_spouse_data' => false,
                'awaiting_their_response' => true,
                'requires_account_link' => true,
                'message' => 'Nothing can be shared until your partner accepts the link from their own Fynla account.',
            ],
        ]);
    }
}
