{{--
  Footer — dark (with logo + links) or light (simple line).

  The dark variant logo links to homepage (Rule 3).
  Section bg: #1F2A44 (dark) or #F7F6F4 (light).

  Variables:
    $variant   string  'dark' | 'light'. Default 'dark'.
    $links     array<array{label:string,url:string}>  Shown in dark variant.
    $year      string|int  Default current year.
    $addressLine string|null  Dark variant only. Default "Fynla Ltd, London, England".
    $helpUrl   string  Light variant only. Default 'https://fynla.org/help'.
--}}
@php
    $variant     = $variant ?? 'dark';
    $links       = $links   ?? [
        ['label' => 'Privacy Policy',   'url' => 'https://fynla.org/privacy'],
        ['label' => 'Terms of Service', 'url' => 'https://fynla.org/terms'],
        ['label' => 'Unsubscribe',      'url' => 'https://fynla.org/unsubscribe'],
    ];
    $year        = $year        ?? date('Y');
    $addressLine = $addressLine ?? 'Registered in England &amp; Wales.';
    $helpUrl     = $helpUrl     ?? 'https://fynla.org/help';
@endphp
@if($variant === 'light')
    <tr>
        <td style="background: #F7F6F4; border-top: 1px solid #e8e2db; padding: 20px 30px; text-align: center; font-family: 'Segoe UI', Inter, Arial, sans-serif;">
            <p style="margin: 0; font-size: 12px; color: #888; font-family: 'Segoe UI', Inter, Arial, sans-serif;">This email was sent by Fynla. If you have questions, reply to this email or visit <a href="{{ $helpUrl }}" style="color: #e74c6f; text-decoration: none;">fynla.org/help</a>.</p>
            <p style="margin: 8px 0 0 0; font-size: 11px; color: #bbb; font-family: 'Segoe UI', Inter, Arial, sans-serif;">&copy; Fynla {{ $year }} &bull; <a href="{{ $links[count($links) - 1]['url'] ?? '#' }}" style="color: #bbb; text-decoration: none;">Unsubscribe</a></p>
        </td>
    </tr>
@else
    <tr>
        <td bgcolor="#1F2A44" style="background: #1F2A44; padding: 24px 36px; font-family: 'Segoe UI', Inter, Arial, sans-serif;">
            <table role="presentation" cellpadding="0" cellspacing="0" width="100%">
                <tr>
                    <td style="vertical-align: top; width: 30%;">
                        <a href="https://fynla.org" style="display: inline-block; text-decoration: none;">
                            <img src="https://fynla.org/images/logos/LogoHiResFynlaLight.png" alt="Fynla" style="height: 28px; width: auto; display: block; border: 0;" />
                        </a>
                    </td>
                    <td style="vertical-align: top; text-align: right;">
                        <p style="margin: 0 0 8px 0;">
                            @foreach($links as $i => $link)
                                <a href="{{ $link['url'] }}" style="font-size: 12px; color: #b3b9c5; text-decoration: none;{{ $i < count($links) - 1 ? ' margin-right: 16px;' : '' }} font-family: 'Segoe UI', Inter, Arial, sans-serif;">{{ $link['label'] }}</a>
                            @endforeach
                        </p>
                        <p style="margin: 0; font-size: 11px; color: #7a8194; line-height: 1.5; font-family: 'Segoe UI', Inter, Arial, sans-serif;">&copy; Fynla {{ $year }}. All rights reserved.<br/>{!! $addressLine !!}</p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
@endif
