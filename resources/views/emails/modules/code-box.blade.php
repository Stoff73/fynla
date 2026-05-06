{{--
  Verification / Password-reset code box — dark treatment used for both
  verification codes and password-reset codes.

  Variables:
    $code     string        e.g. "847293"
    $label    string        e.g. "Your verification code" / "Password Reset Code"
    $expiry   string|null   e.g. "Expires in 10 minutes"
    $outerBg  string        Outer <td> bg. Default #ffffff.
--}}
@php
    $code    = $code    ?? '------';
    $label   = $label   ?? 'Your verification code';
    $expiry  = $expiry  ?? null;
    $outerBg = $outerBg ?? '#f5f0eb';
@endphp
<tr>
    <td style="background: {{ $outerBg }}; padding: 16px 36px;">
        <div style="background: #1F2A44; border-radius: 12px; padding: 24px; text-align: center;">
            <p style="margin: 0 0 8px 0; font-size: 12px; color: #8f97a8; text-transform: uppercase; letter-spacing: 1px; font-family: 'Segoe UI', Inter, Arial, sans-serif;">{{ $label }}</p>
            <p style="margin: 0; font-size: 36px; font-weight: 900; color: #ffffff; letter-spacing: 8px; font-family: 'Courier New', monospace;">{{ $code }}</p>
            @if($expiry)
                <p style="margin: 8px 0 0 0; font-size: 12px; color: #8f97a8; font-family: 'Segoe UI', Inter, Arial, sans-serif;">{{ $expiry }}</p>
            @endif
        </div>
    </td>
</tr>
