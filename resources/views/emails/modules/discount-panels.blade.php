{{--
  Unlock Discount Panel — full-bleed dark #0F172A strip. TWO STATES:

  ZERO STATE ($progressPercent === 0 OR $modules is empty):
    Render progress bar + progress details only, followed by a centred green
    "Get started" CTA. No module tiles, no discount-max footer line.

  NON-ZERO STATE ($progressPercent > 0 AND modules are supplied):
    Render progress bar + 2-column grid of clickable module tiles (Harvey-ball
    status) + bottom max-discount footer line. No green CTA (tiles themselves
    are the deep-link CTAs).

  Progress mapping (the canonical journey tiers):
    25% complete = 5% discount
    50%          = 10%
    75%          = 15%
    100%         = 20%

  Variables:
    $progressPercent    int 0..100     Fill for the bar. Default 50.
    $discountEarned     string         e.g. "10%". Default computed from progressPercent.
    $nextTierText       string|null    e.g. "Next tier: 75% → 15% discount". Pass null to hide.
    $modules            array<array{title:string,subtitle:string,url:string,status:string}>
                                       status = 'full' | 'three-quarter' | 'half' | 'quarter' | 'empty'
                                       Maps to Harvey ball: ● ◕ ◑ ◔ ○
    $maxDiscountFooter  string         Default "Complete all the modules in your journey and unlock the full <strong style=\"color:#22c55e;\">20% discount</strong>."
    $ctaLabel           string         Zero-state only. Default "Get started".
    $ctaUrl             string         Zero-state only. Default "https://fynla.org/dashboard".
--}}
@php
    $progressPercent = max(0, min(100, (int) ($progressPercent ?? 50)));

    // Tier mapping
    $tierMap = [0 => '0%', 25 => '5%', 50 => '10%', 75 => '15%', 100 => '20%'];
    $nearestTier = 0;
    foreach (array_keys($tierMap) as $tier) {
        if ($progressPercent >= $tier) {
            $nearestTier = $tier;
        }
    }
    $discountEarned    = $discountEarned    ?? $tierMap[$nearestTier];
    $nextTierText      = $nextTierText      ?? null;
    $modules           = $modules           ?? [];
    $maxDiscountFooter = $maxDiscountFooter ?? 'Complete all the modules in your journey and unlock the full <strong style="color:#22c55e;">20% discount</strong>.';

    $harvey = [
        'full'           => '&#9679;',  // ●
        'three-quarter'  => '&#9685;',  // ◕
        'half'           => '&#9681;',  // ◑
        'quarter'        => '&#9684;',  // ◔
        'empty'          => '&#9675;',  // ○
    ];

    $fillWidth  = $progressPercent;
    $restWidth  = 100 - $progressPercent;
    // Pair modules for 2-column rendering.
    $rows = array_chunk($modules, 2);
    $total = count($rows);

    // Zero-state rendering flag: either progress is 0 or no tiles supplied.
    $isZeroState = ($progressPercent === 0) || empty($modules);
    $ctaLabel    = $ctaLabel ?? 'Get started';
    $ctaUrl      = $ctaUrl   ?? 'https://fynla.org/dashboard';
@endphp
<tr>
    <td style="padding: 0;">
        <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="background: #0F172A; font-family: 'Segoe UI', Inter, Arial, sans-serif;">
            <tr>
                <td colspan="2" style="padding: 20px 36px 8px;">
                    <table role="presentation" cellpadding="0" cellspacing="0" width="100%">
                        <tr>
                            <td style="vertical-align: baseline;">
                                <p style="margin: 0; font-size: 14px; font-weight: 700; color: #ffffff; font-family: 'Segoe UI', Inter, Arial, sans-serif;">Your progress</p>
                            </td>
                            <td style="vertical-align: baseline; text-align: right;">
                                <p style="margin: 0; font-size: 14px; font-weight: 700; color: #22c55e; font-family: 'Segoe UI', Inter, Arial, sans-serif;">{{ $discountEarned }} discount earned</p>
                            </td>
                        </tr>
                    </table>
                    <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="margin-top: 10px; border-collapse: separate; border-radius: 6px; overflow: hidden; background: #1F2A44;">
                        <tr>
                            @if($fillWidth > 0)
                                <td height="10" bgcolor="#22c55e" style="background: #22c55e; width: {{ $fillWidth }}%; font-size: 0; line-height: 0;">&nbsp;</td>
                            @endif
                            @if($restWidth > 0)
                                <td height="10" style="width: {{ $restWidth }}%; font-size: 0; line-height: 0;">&nbsp;</td>
                            @endif
                        </tr>
                    </table>
                    <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="margin-top: 8px;">
                        <tr>
                            <td style="vertical-align: baseline;">
                                @if($nextTierText)
                                    <p style="margin: 0; font-size: 12px; color: #94a3b8; font-family: 'Segoe UI', Inter, Arial, sans-serif;">{!! $nextTierText !!}</p>
                                @endif
                            </td>
                            <td style="vertical-align: baseline; text-align: right;">
                                <p style="margin: 0; font-size: 12px; font-weight: 700; color: #ffffff; font-family: 'Segoe UI', Inter, Arial, sans-serif;">{{ $progressPercent }}% complete</p>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
            @if($isZeroState)
                <tr>
                    <td colspan="2" style="padding: 20px 36px 28px; text-align: center;">
                        <a href="{{ $ctaUrl }}" style="display: inline-block; padding: 14px 40px; background: #20B486; color: #ffffff; font-size: 16px; font-weight: 700; border-radius: 12px; text-decoration: none; font-family: 'Segoe UI', Inter, Arial, sans-serif;">{{ $ctaLabel }}</a>
                    </td>
                </tr>
            @else
                @foreach($rows as $rowIndex => $pair)
                    @php
                        $isFirstRow = ($rowIndex === 0);
                        $isLastRow  = ($rowIndex === $total - 1);
                        $topPadding = $isFirstRow ? '16px' : '8px';
                        $btmPadding = $isLastRow  ? '20px' : '8px';
                    @endphp
                    <tr>
                        @foreach($pair as $colIndex => $module)
                            @php
                                $status    = $module['status']    ?? 'empty';
                                $ball      = $harvey[$status] ?? $harvey['empty'];
                                $ballColor = $status === 'empty' ? '#64748b' : '#22c55e';
                                $leftCol   = ($colIndex === 0);
                                $cellPad   = $leftCol
                                    ? "{$topPadding} 12px {$btmPadding} 36px"
                                    : "{$topPadding} 36px {$btmPadding} 12px";
                            @endphp
                            <td style="padding: {{ $cellPad }}; width: 50%; vertical-align: top;">
                                <a href="{{ $module['url'] ?? '#' }}" style="display: block; background: #1F2A44; border-radius: 12px; padding: 16px; text-decoration: none; color: #ffffff;">
                                    <table role="presentation" cellpadding="0" cellspacing="0" width="100%">
                                        <tr>
                                            <td style="vertical-align: middle;">
                                                <p style="margin: 0; font-size: 17px; font-weight: 700; color: #ffffff; line-height: 1.2; font-family: 'Segoe UI', Inter, Arial, sans-serif;">{{ $module['title'] ?? '' }}</p>
                                            </td>
                                            <td style="vertical-align: middle; text-align: right; width: 28px;">
                                                <span style="font-size: 22px; color: {{ $ballColor }}; line-height: 1;">{!! $ball !!}</span>
                                            </td>
                                        </tr>
                                    </table>
                                    <p style="margin: 4px 0 0 0; font-size: 11px; color: #94a3b8; font-family: 'Segoe UI', Inter, Arial, sans-serif;">{{ $module['subtitle'] ?? '' }}</p>
                                </a>
                            </td>
                        @endforeach
                        {{-- Fill an empty cell if the row has only one module --}}
                        @if(count($pair) === 1)
                            <td style="padding: 0; width: 50%;">&nbsp;</td>
                        @endif
                    </tr>
                @endforeach
                <tr>
                    <td colspan="2" style="padding: 0 36px 24px; text-align: center;">
                        <p style="margin: 0; font-size: 13px; color: #94a3b8; font-family: 'Segoe UI', Inter, Arial, sans-serif;">{!! $maxDiscountFooter !!}</p>
                    </td>
                </tr>
            @endif
        </table>
    </td>
</tr>
