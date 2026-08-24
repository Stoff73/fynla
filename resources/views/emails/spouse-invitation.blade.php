{{--
    W-0349. The email sent when someone adds a partner by an address that has NO
    Fynla account.

    It replaces `spouse-account-created.blade.php` on that path, and the
    difference is the whole point of the item: that email opened "X has created a
    spouse account for you" and carried a temporary password, because the service
    really had created an account for an address the caller merely typed. Nothing
    here says an account exists, because none does — the address is invited to
    make one, and no `users` row is written until they do.

    Deliberately says nothing about the sender's finances and shows no figures.
    The recipient has not agreed to anything yet, and until they register and
    accept the link there is nothing shared in either direction.
--}}
@extends('emails.layouts.master', [
    'title'     => $inviterName . ' has invited you to Fynla',
    'preheader' => $inviterName . ' would like to plan your household finances with you on Fynla.',
])

@section('logoBar')
    @include('emails.modules.logo-bar')
@endsection

@section('header')
    @include('emails.modules.hero-header', [
        'heading'  => 'You have been<br/><span style="color:#f9a8c0;">invited to Fynla</span>',
        'subtitle' => 'Plan your household finances together',
    ])
@endsection

@section('body')
    @include('emails.modules.body', [
        'greeting'   => 'Hello,',
        'paragraphs' => [
            '<strong>' . e($inviterName) . '</strong> uses Fynla to plan their finances, and has invited you to join them as their partner.',
            'Fynla is a United Kingdom financial planning app. Planning together lets a household see one shared picture &mdash; savings, pensions, protection and estate &mdash; instead of two halves that never quite add up.',
            // compliance-lead finding H, 2026-08-24: a public claim about what Fynla
            // does, sent unsolicited to someone who is not a user, with no statement
            // of regulatory posture. `05-perimeter.md` §3 binds it.
            'Fynla provides guidance to help you understand your own finances. It is not a regulated financial adviser and does not give financial advice.',
        ],
    ])

    <tr>
        <td bgcolor="#fce4ec" style="background:#fce4ec;padding:28px 36px;">
            <h3 style="margin:0 0 10px;font-size:20px;font-weight:700;color:#1F2A44;">What happens next</h3>
            <p style="margin:0 0 16px;font-size:14px;color:#1F2A44;line-height:1.7;">
                Creating an account is your choice, and so is linking it. Nothing is shared
                between you and {{ $inviterName }} until you have made an account and
                accepted the link &mdash; and you can withdraw it at any time afterwards.
            </p>
            <p style="margin:0 0 16px;font-size:14px;color:#1F2A44;line-height:1.7;">
                If you would rather not, you do not need to do anything at all. No account
                has been created for you, and ignoring this email leaves nothing behind.
            </p>
            <p style="margin:0;font-size:14px;color:#1F2A44;line-height:1.7;">
                Register with <strong>{{ $invitedEmail }}</strong> &mdash; that is the address
                {{ $inviterName }} invited, and the one the link will recognise.
            </p>
        </td>
    </tr>

    @include('emails.modules.cta', [
        'outerBg' => '#f5f0eb',
        'buttons' => [
            [
                'label'   => 'Create your account',
                'url'     => $registerUrl,
                'variant' => 'raspberry',
            ],
        ],
    ])
@endsection

@section('signoff')
    @include('emails.modules.signoff')
@endsection

@section('footer')
    {{--
        W-0349, compliance-lead finding G. No unsubscribe control on this one email,
        and the reasoning matters: the default footer links to
        `https://fynla.org/unsubscribe`, **a route that does not exist** (the only
        unsubscribe route is `/unsubscribe/news/{token}`), and even if it resolved it
        could not be honoured — the invited address is used once and never stored
        (W-0472), so there is no record to suppress and nowhere to note a refusal.

        An inoperative refusal mechanism is worse than none: it looks like a control
        and is not. The body offers the honest alternative instead — do nothing, and
        nothing happens.
    --}}
    @include('emails.modules.footer', ['variant' => 'dark', 'showUnsubscribe' => false])
@endsection
