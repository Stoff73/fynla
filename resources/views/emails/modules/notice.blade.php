{{--
  Notification / notice pill — coloured rounded box with a short message.

  Variables:
    $message  string   HTML allowed (bold, code). e.g. "Your trial expires in 3 days."
    $variant  string   'info' | 'solid-blue' | 'pink' | 'violet' | 'neutral'. Default 'info'.
    $icon     string|null  Optional HTML entity/unicode prefix. e.g. "&#9432;"
    $outerBg  string   Outer <td> bg. Default #ffffff.

  NOTE — no green ('success') or raspberry ('raspberry') variants. Solid green and
  solid raspberry pill backgrounds read as clickable CTAs and dilute the real
  raspberry CTA buttons. If you need a positive/affirmation notice use 'info' or
  'neutral'; if you need an alert, use 'pink' or 'violet'.

  Rule 2 adjacency: successive notice tiles with different variants are fine;
  two tiles with the same variant should not sit directly next to each other.
--}}
@php
    $message = $message ?? '';
    $variant = $variant ?? 'info';
    $icon    = $icon    ?? null;
    $outerBg = $outerBg ?? '#f5f0eb';

    $styles = [
        'info'       => ['bg' => '#dbeafe', 'color' => '#1F2A44', 'weight' => '400'],
        'solid-blue' => ['bg' => '#3b82f6', 'color' => '#ffffff', 'weight' => '400'],
        'pink'       => ['bg' => '#fce4ec', 'color' => '#1F2A44', 'weight' => '400'],
        'violet'     => ['bg' => '#ede9fe', 'color' => '#5b21b6', 'weight' => '400'],
        'neutral'    => ['bg' => '#F7F6F4', 'color' => '#1F2A44', 'weight' => '400'],
    ];
    $s = $styles[$variant] ?? $styles['info'];
@endphp
<tr>
    <td style="background: {{ $outerBg }}; padding: 8px 36px;">
        <div style="background: {{ $s['bg'] }}; border-radius: 10px; padding: 16px 20px;">
            <p style="margin: 0; font-size: 13px; color: {{ $s['color'] }}; font-weight: {{ $s['weight'] }}; font-family: 'Segoe UI', Inter, Arial, sans-serif; line-height: 1.5;">@if($icon){!! $icon !!} @endif{!! $message !!}</p>
        </div>
    </td>
</tr>
