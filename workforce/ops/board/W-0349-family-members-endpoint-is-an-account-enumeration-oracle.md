

- 2026-08-24 — **`compliance-lead` on the `SpouseInvitation` email: the copy itself
  cleared every one of the seven rules within competence**, and was called "the
  best-judged consent text in this batch". Two things were not the copy:
  - **(G) It offered a means of refusal that cannot function.** The default dark footer
    links to `https://fynla.org/unsubscribe`, **a route that does not exist** — the only
    unsubscribe route is `/unsubscribe/news/{token}` — and even if it resolved there is
    no stored record to suppress (W-0472). **An inoperative refusal mechanism is worse
    than none: it looks like a control and is not.** The footer module gains
    `$showUnsubscribe`, defaulting TRUE so every other email is unchanged, and this one
    email passes false. The suggested line "we will not email this address again" was
    deliberately NOT added — the inviter can re-send within the 5/hour throttle, so it
    would not be true.
  - **(H) No perimeter line on an acquisition email.** Added: *"Fynla provides guidance
    to help you understand your own finances. It is not a regulated financial adviser and
    does not give financial advice."*
  - **Raised for `security-reviewer`, not a compliance block:** the inviter's display
    name is user-controlled and delivered to an address of the inviter's choosing.
    Escaping stops markup injection; it does not stop someone setting their name to a
    sentence and using Fynla to deliver it. Mitigated only by the throttle.
  - **Still open: acceptance 4.** W-0347 itself is FLAGGED on five findings and its
    acceptances 3 and 4 are both unmet.
