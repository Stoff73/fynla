{{--
  Hero Header with gradient + Fyn character (Rule 1: gradient is fixed, do not change).
  Section bg: linear-gradient(135deg, #1F2A44, #e74c6f) over fallback #1F2A44.

  Variables:
    $heading      string        Main heading. May include a <span style="color:#f9a8c0;"> for accent.
    $subtitle     string|null   Optional subtitle under the heading.
    $showCharacter bool         Default true. Set false to hide the Fyn character image.
--}}
@php
    $heading       = $heading       ?? 'Welcome to <span style="color:#f9a8c0;">Fynla</span>';
    $subtitle      = $subtitle      ?? null;
    $showCharacter = $showCharacter ?? true;
@endphp
<tr>
    <td bgcolor="#1F2A44" style="background-color: #1F2A44; background-image: linear-gradient(135deg, #1F2A44 0%, #e74c6f 100%); padding: 28px 36px 0; min-height: 180px;">
        <table role="presentation" cellpadding="0" cellspacing="0" width="100%">
            <tr>
                <td style="padding-bottom: 28px; vertical-align: bottom;">
                    <h1 style="font-size: 36px; font-weight: 900; color: #ffffff; line-height: 1.2; margin: 0 0 6px 0; font-family: 'Segoe UI', Inter, Arial, sans-serif;">{!! $heading !!}</h1>
                    @if($subtitle)
                        <p style="font-size: 14px; color: rgba(255,255,255,0.7); margin: 0; font-family: 'Segoe UI', Inter, Arial, sans-serif;">{{ $subtitle }}</p>
                    @endif
                </td>
                @if($showCharacter)
                    <td style="vertical-align: bottom; width: 130px; text-align: right;">
                        <img src="https://fynla.org/images/Fyn/Design%20Character%20001a.png" alt="Fyn" width="130" height="171" style="height: 171px; width: 130px; display: block; margin-bottom: -15px; border: 0;" />
                    </td>
                @endif
            </tr>
        </table>
    </td>
</tr>
