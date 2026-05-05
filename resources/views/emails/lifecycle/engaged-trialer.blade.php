@extends('emails.lifecycle._layout')
@section('title', 'Your Fynla picture so far')
@section('content')
    <p>Hi {{ $firstName }},</p>

    <p>Your free Fynla trial has wrapped up — but the picture you started building is still there, and it's looking strong:</p>

    {{-- HTML table-based progress bar --}}
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin: 20px 0;">
        <tr>
            <td>
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color: #FDFAF7; border-radius: 12px; overflow: hidden; padding: 20px;">
                    <tr>
                        <td style="text-align: center; font-size: 14px; color: #717171; padding-bottom: 8px;">YOU'RE</td>
                    </tr>
                    <tr>
                        <td style="text-align: center; font-size: 36px; font-weight: 900; color: #1F2A44; padding-bottom: 4px;">{{ $completionPct }}%</td>
                    </tr>
                    <tr>
                        <td style="text-align: center; font-size: 14px; color: #717171; padding-bottom: 12px;">THERE</td>
                    </tr>
                    <tr>
                        <td>
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color: #EEEEEE; border-radius: 6px; height: 8px;">
                                <tr>
                                    <td style="background-color: #20B486; width: {{ $completionPct }}%; border-radius: 6px;">&nbsp;</td>
                                    <td>&nbsp;</td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <p>You've started tracking:</p>
    <ul>
        @foreach ($modulesWithData as $module)
            <li>{{ $module['count'] }} {{ $module['label'] }}</li>
        @endforeach
    </ul>

    @if (count($modulesRemaining) > 0)
        <p>{{ count($modulesRemaining) }} more {{ count($modulesRemaining) === 1 ? 'area' : 'areas' }} to set up — {{ implode(', ', $modulesRemaining) }} — and your full Fynla plan is complete.</p>
    @endif

    <p>To help you finish, we're offering a one-time welcome discount on any Fynla plan. Pick what works for you:</p>

    {{-- Discount table --}}
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin: 20px 0; border-collapse: collapse;">
        <tr style="background-color: #F7F6F4;">
            <th style="padding: 10px; border: 1px solid #EEEEEE; text-align: left; font-size: 14px;">Plan</th>
            <th style="padding: 10px; border: 1px solid #EEEEEE; text-align: right; font-size: 14px;">Monthly</th>
            <th style="padding: 10px; border: 1px solid #EEEEEE; text-align: right; font-size: 14px;">Yearly</th>
        </tr>
        <tr>
            <td style="padding: 10px; border: 1px solid #EEEEEE; font-size: 14px;">Student</td>
            <td style="padding: 10px; border: 1px solid #EEEEEE; text-align: right; font-size: 14px;"><strong>£2.99</strong></td>
            <td style="padding: 10px; border: 1px solid #EEEEEE; text-align: right; font-size: 14px;"><strong>£21.99</strong></td>
        </tr>
        <tr>
            <td style="padding: 10px; border: 1px solid #EEEEEE; font-size: 14px;">Standard</td>
            <td style="padding: 10px; border: 1px solid #EEEEEE; text-align: right; font-size: 14px;"><strong>£5.99</strong></td>
            <td style="padding: 10px; border: 1px solid #EEEEEE; text-align: right; font-size: 14px;"><strong>£55.00</strong></td>
        </tr>
        <tr>
            <td style="padding: 10px; border: 1px solid #EEEEEE; font-size: 14px;">Family</td>
            <td style="padding: 10px; border: 1px solid #EEEEEE; text-align: right; font-size: 14px;"><strong>£10.99</strong></td>
            <td style="padding: 10px; border: 1px solid #EEEEEE; text-align: right; font-size: 14px;"><strong>£100.00</strong></td>
        </tr>
        <tr>
            <td style="padding: 10px; border: 1px solid #EEEEEE; font-size: 14px;">Pro</td>
            <td style="padding: 10px; border: 1px solid #EEEEEE; text-align: right; font-size: 14px;">£19.99</td>
            <td style="padding: 10px; border: 1px solid #EEEEEE; text-align: right; font-size: 14px;">£200.00</td>
        </tr>
    </table>

    @include('emails.lifecycle._button', ['url' => $magicUrl, 'label' => 'CLAIM YOUR DISCOUNT'])

    <p style="text-align: center; color: #717171; font-size: 13px;">If the button doesn't work, your discount code is:</p>
    <p style="text-align: center; font-family: monospace; font-size: 18px; font-weight: 700; color: #1F2A44; letter-spacing: 1px;">{{ $discountCode }}</p>
    <p style="text-align: center; color: #717171; font-size: 13px;">This offer expires in 7 days. Pro is at standard pricing.</p>

    <p>— The Fynla team</p>
@endsection
