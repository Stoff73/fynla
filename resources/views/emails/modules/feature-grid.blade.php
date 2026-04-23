{{--
  Feature Grid (2×2) — dark navy tiles on a #0F172A background strip.
  No coloured icons — title + subtitle only. Title sized large (17px) to
  match the discount-panels tile treatment.

  Variables:
    $features  array<array{title:string,subtitle:string}>
               Up to 4 items. Provide 4 for the 2×2 layout.
    $cta       array|null  Optional bottom CTA inside the dark strip:
                           ['label' => 'Check out features', 'url' => 'https://fynla.org/dashboard']
--}}
@php
    $features = $features ?? [];
    $f   = array_pad($features, 4, ['title' => '', 'subtitle' => '']);
    $cta = $cta ?? null;
@endphp
<tr>
    <td style="padding: 0;">
        <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="background: #0F172A; font-family: 'Segoe UI', Inter, Arial, sans-serif;">
            <tr>
                <td style="padding: 24px 20px 12px 36px; width: 50%; vertical-align: top;">
                    <div style="background: #1F2A44; border-radius: 12px; padding: 16px;">
                        <p style="margin: 0; font-size: 17px; font-weight: 700; color: #ffffff; line-height: 1.2; font-family: 'Segoe UI', Inter, Arial, sans-serif;">{{ $f[0]['title'] }}</p>
                        <p style="margin: 4px 0 0 0; font-size: 11px; color: #94a3b8; font-family: 'Segoe UI', Inter, Arial, sans-serif;">{{ $f[0]['subtitle'] }}</p>
                    </div>
                </td>
                <td style="padding: 24px 36px 12px 20px; width: 50%; vertical-align: top;">
                    <div style="background: #1F2A44; border-radius: 12px; padding: 16px;">
                        <p style="margin: 0; font-size: 17px; font-weight: 700; color: #ffffff; line-height: 1.2; font-family: 'Segoe UI', Inter, Arial, sans-serif;">{{ $f[1]['title'] }}</p>
                        <p style="margin: 4px 0 0 0; font-size: 11px; color: #94a3b8; font-family: 'Segoe UI', Inter, Arial, sans-serif;">{{ $f[1]['subtitle'] }}</p>
                    </div>
                </td>
            </tr>
            <tr>
                <td style="padding: 12px 20px {{ $cta ? '12px' : '24px' }} 36px; width: 50%; vertical-align: top;">
                    <div style="background: #1F2A44; border-radius: 12px; padding: 16px;">
                        <p style="margin: 0; font-size: 17px; font-weight: 700; color: #ffffff; line-height: 1.2; font-family: 'Segoe UI', Inter, Arial, sans-serif;">{{ $f[2]['title'] }}</p>
                        <p style="margin: 4px 0 0 0; font-size: 11px; color: #94a3b8; font-family: 'Segoe UI', Inter, Arial, sans-serif;">{{ $f[2]['subtitle'] }}</p>
                    </div>
                </td>
                <td style="padding: 12px 36px {{ $cta ? '12px' : '24px' }} 20px; width: 50%; vertical-align: top;">
                    <div style="background: #1F2A44; border-radius: 12px; padding: 16px;">
                        <p style="margin: 0; font-size: 17px; font-weight: 700; color: #ffffff; line-height: 1.2; font-family: 'Segoe UI', Inter, Arial, sans-serif;">{{ $f[3]['title'] }}</p>
                        <p style="margin: 4px 0 0 0; font-size: 11px; color: #94a3b8; font-family: 'Segoe UI', Inter, Arial, sans-serif;">{{ $f[3]['subtitle'] }}</p>
                    </div>
                </td>
            </tr>
            @if($cta)
                <tr>
                    <td colspan="2" style="padding: 8px 36px 24px; text-align: center;">
                        <a href="{{ $cta['url'] ?? '#' }}" style="display: inline-block; padding: 14px 40px; background: #e74c6f; color: #ffffff; font-size: 16px; font-weight: 700; border-radius: 12px; text-decoration: none; box-shadow: 0 4px 14px #d9a0b0; font-family: 'Segoe UI', Inter, Arial, sans-serif;">{{ $cta['label'] ?? 'Check out features' }}</a>
                    </td>
                </tr>
            @endif
        </table>
    </td>
</tr>
