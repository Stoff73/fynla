@extends('emails.layouts.master', [
    'title'     => "Great job {$firstName}, you're closer to financial freedom",
    'preheader' => "You're closer to financial freedom — here's what you've unlocked.",
])

@section('logoBar')
    @include('emails.modules.logo-bar')
@endsection

@section('header')
    @include('emails.modules.hero-header', [
        'heading'  => 'Great job<br/><span style="color:#f9a8c0;">' . e($firstName) . '</span>',
        'subtitle' => "You're closer to financial freedom",
    ])
@endsection

@section('body')
    <tr>
        <td bgcolor="#f5f0eb" style="background:#f5f0eb;padding:28px 36px;">
            <table role="presentation" cellpadding="0" cellspacing="0" width="100%">
                <tr>
                    <td style="vertical-align:middle;padding-right:20px;">
                        <h3 style="margin:0 0 8px;font-size:20px;font-weight:700;color:#1F2A44;">Get even more value</h3>
                        <p style="margin:0;font-size:14px;color:#555;line-height:1.6;">There's more financial gains for you to unlock. The more you unlock, the more discount and benefit you get!</p>
                    </td>
                    <td style="vertical-align:middle;width:150px;text-align:center;">
                        <p style="margin:0;font-size:64px;font-weight:900;color:#e74c6f;line-height:0.9;letter-spacing:-1px;">{{ $discountEarned }}</p>
                        <p style="margin:6px 0 0;font-size:12px;font-weight:700;color:#1F2A44;line-height:1.3;">Current discount earned</p>
                    </td>
                </tr>
            </table>
            <div style="text-align:center;margin-top:20px;">
                <a href="{{ $finishPlanUrl1 }}" style="display:inline-block;padding:14px 40px;background:#e74c6f;color:#ffffff;font-size:16px;font-weight:700;border-radius:12px;text-decoration:none;box-shadow:0 4px 14px #d9a0b0;">Finish my plan</a>
            </div>
        </td>
    </tr>

    <tr>
        <td bgcolor="#fce4ec" style="background:#fce4ec;padding:28px 36px;">
            <h3 style="margin:0 0 14px;font-size:20px;font-weight:700;color:#1F2A44;">Top tips</h3>
            <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom:28px;border-collapse:separate;border-spacing:8px 0;">
                <tr>
                    <td width="33.33%" height="150" style="vertical-align:top;height:150px;">
                        <div style="background:#ffffff;border-radius:12px;padding:16px;min-height:118px;box-sizing:border-box;">
                            <p style="margin:0 0 6px;font-size:14px;font-weight:700;color:#1F2A44;line-height:1.3;">Review quarterly</p>
                            <p style="margin:0;font-size:12px;color:#555;line-height:1.5;">Tax allowances change every year &mdash; a 10-minute check keeps you ahead.</p>
                        </div>
                    </td>
                    <td width="33.33%" height="150" style="vertical-align:top;height:150px;">
                        <div style="background:#ffffff;border-radius:12px;padding:16px;min-height:118px;box-sizing:border-box;">
                            <p style="margin:0 0 6px;font-size:14px;font-weight:700;color:#1F2A44;line-height:1.3;">Invite your partner</p>
                            <p style="margin:0;font-size:12px;color:#555;line-height:1.5;">Joint modelling unlocks household-level tax savings most single-user plans miss.</p>
                        </div>
                    </td>
                    <td width="33.33%" height="150" style="vertical-align:top;height:150px;">
                        <div style="background:#ffffff;border-radius:12px;padding:16px;min-height:118px;box-sizing:border-box;">
                            <p style="margin:0 0 6px;font-size:14px;font-weight:700;color:#1F2A44;line-height:1.3;">Keep details current</p>
                            <p style="margin:0;font-size:12px;color:#555;line-height:1.5;">Salary, pension contributions and property values feed every projection you see.</p>
                        </div>
                    </td>
                </tr>
            </table>

            <h3 style="margin:0 0 14px;font-size:20px;font-weight:700;color:#1F2A44;">Next steps</h3>
            @foreach([
                ['title' => 'Complete your Estate module', 'description' => 'Model your Inheritance Tax position and protect what you pass on from a 40% liability.'],
                ['title' => 'Set up your Goals',           'description' => 'Connect your plan to the life events you want to fund &mdash; retirement, a property, a wedding, school fees.'],
                ['title' => 'Unlock the full 20% discount','description' => 'Finishing the last two modules takes you from ' . $discountEarned . ' to the maximum 20% off your first year.'],
            ] as $i => $step)
                <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom:{{ $i === 2 ? '28px' : '18px' }};">
                    <tr>
                        <td style="vertical-align:top;width:70px;padding-right:14px;">
                            <p style="margin:0;font-size:54px;font-weight:900;color:#e74c6f;line-height:0.9;text-align:center;font-family:'Segoe UI',Inter,Arial,sans-serif;">{{ $i + 1 }}</p>
                        </td>
                        <td style="vertical-align:top;">
                            <p style="margin:0 0 4px;font-size:15px;font-weight:700;color:#1F2A44;line-height:1.2;">{{ $step['title'] }}</p>
                            <p style="margin:0;font-size:13px;color:#1F2A44;line-height:1.6;">{!! $step['description'] !!}</p>
                        </td>
                    </tr>
                </table>
            @endforeach

            <div style="text-align:center;">
                <a href="{{ $finishPlanUrl2 }}" style="display:inline-block;padding:14px 40px;background:#e74c6f;color:#ffffff;font-size:16px;font-weight:700;border-radius:12px;text-decoration:none;box-shadow:0 4px 14px #d9a0b0;">Finish my plan</a>
            </div>
        </td>
    </tr>

    <tr>
        <td style="padding:0;">
            <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="background:#0F172A;font-family:'Segoe UI',Inter,Arial,sans-serif;">
                <tr>
                    <td colspan="2" style="padding:24px 36px 4px;">
                        <h3 style="margin:0;font-size:20px;font-weight:700;color:#ffffff;">Your journey</h3>
                    </td>
                </tr>
            </table>
        </td>
    </tr>

    @include('emails.modules.discount-panels', [
        'progressPercent' => $progressPercent,
        'nextTierText'    => 'Next tier: 100% &rarr; 20% discount',
        'modules' => [
            ['title' => 'Protection', 'subtitle' => 'Life, income, critical illness', 'url' => $protectionUrl, 'status' => 'full'],
            ['title' => 'Savings',    'subtitle' => 'Cash, ISAs, emergency fund',     'url' => $savingsUrl,    'status' => 'full'],
            ['title' => 'Investment', 'subtitle' => 'Portfolios & projections',       'url' => $investmentUrl, 'status' => 'full'],
            ['title' => 'Retirement', 'subtitle' => 'Pensions & decumulation',        'url' => $retirementUrl, 'status' => 'three-quarter'],
            ['title' => 'Estate',     'subtitle' => 'IHT, wills, LPAs',               'url' => $estateUrl,     'status' => 'empty'],
            ['title' => 'Goals',      'subtitle' => 'Life events & targets',          'url' => $goalsUrl,      'status' => 'empty'],
        ],
    ])

    <tr>
        <td bgcolor="#fce4ec" style="background:#fce4ec;padding:28px 36px;">
            <h3 style="margin:0 0 8px;font-size:20px;font-weight:700;color:#1F2A44;">Why this matters</h3>
            <p style="margin:0 0 10px;font-size:16px;font-weight:700;color:#1F2A44;line-height:1.35;">You're building something real.</p>
            <p style="margin:0 0 20px;font-size:14px;color:#1F2A44;line-height:1.7;">
                Most UK households don't know where they stand. By filling in the majority of your modules you already have more visibility over your finances than the vast majority of people your age &mdash; and households that complete an estate plan typically save their heirs tens of thousands in Inheritance Tax. Finishing the last modules is where Fynla pays for itself.
            </p>
            <div style="text-align:center;">
                <a href="{{ $finishPlanUrl3 }}" style="display:inline-block;padding:14px 40px;background:#e74c6f;color:#ffffff;font-size:16px;font-weight:700;border-radius:12px;text-decoration:none;box-shadow:0 4px 14px #d9a0b0;">Finish my plan</a>
            </div>
        </td>
    </tr>
@endsection

@section('signoff')
    @include('emails.modules.signoff')
@endsection

@section('footer')
    @include('emails.modules.footer', ['variant' => 'dark'])
@endsection
