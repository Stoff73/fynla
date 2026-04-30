{{--
  Sign-off — "Kindest regards, The Fynla Team". Text-only: no logo (the logo
  lives in the logo-bar at the top and, when applicable, in the dark footer).

  Variables:
    $closing   string  First line. Default "Kindest regards,".
    $team      string  Second line. Default "The Fynla Team".
    $outerBg   string  Outer <td> bg. Default #ffffff.
    $padding   string  Outer <td> padding. Default "28px 36px".
--}}
@php
    $closing = $closing ?? 'Kindest regards,';
    $team    = $team    ?? 'The Fynla Team';
    $outerBg = $outerBg ?? '#f5f0eb';
    $padding = $padding ?? '28px 36px';
@endphp
<tr>
    <td style="background: {{ $outerBg }}; padding: {{ $padding }}; font-family: 'Segoe UI', Inter, Arial, sans-serif;">
        <p style="margin: 0 0 2px 0; font-size: 14px; color: #6b7280; line-height: 1.4; font-family: 'Segoe UI', Inter, Arial, sans-serif;">{{ $closing }}</p>
        <p style="margin: 0; font-size: 22px; font-weight: 900; color: #1F2A44; line-height: 1.2; letter-spacing: -0.3px; font-family: 'Segoe UI', Inter, Arial, sans-serif;">{{ $team }}</p>
    </td>
</tr>
