{{--
  Call-to-Action button row. One <tr> may contain multiple buttons (stacked).

  Variables:
    $buttons  array<array{label:string,url:string,variant?:string}>
              Allowed variants:
                'raspberry' (default primary)
                'green'     (Fyn-action)
                'dark'      (manage)
                'outline'   (secondary)
                'subtle'    (tertiary)
    $outerBg  string  Outer <td> bg. Default #f5f0eb.

  Outlook note: buttons are built as a table with the background and padding on
  the <td>, not as a styled <a>. Outlook on Windows renders with Word, which
  ignores display:inline-block and padding on an anchor — a CSS-styled button
  there collapses into a run of coloured text with no shape. Word does honour
  bgcolor and padding on a table cell, so the cell carries both. border-radius
  is still ignored by Word, so Outlook shows a square-cornered button; every
  other client rounds it. Corners degrade, the button never does.
--}}
@php
    $buttons = $buttons ?? [];
    $outerBg = $outerBg ?? '#f5f0eb';
    $font    = "'Segoe UI', Inter, Arial, sans-serif";

    $variants = [
        'raspberry' => ['bg' => '#e74c6f', 'color' => '#ffffff', 'border' => 'none',              'padding' => '14px 40px', 'radius' => '12px', 'size' => '16px', 'shadow' => 'box-shadow: 0 4px 14px #d9a0b0;'],
        'green'     => ['bg' => '#20B486', 'color' => '#ffffff', 'border' => 'none',              'padding' => '14px 40px', 'radius' => '12px', 'size' => '16px', 'shadow' => ''],
        'dark'      => ['bg' => '#1F2A44', 'color' => '#ffffff', 'border' => 'none',              'padding' => '14px 40px', 'radius' => '12px', 'size' => '16px', 'shadow' => ''],
        'outline'   => ['bg' => '#ffffff', 'color' => '#e74c6f', 'border' => '2px solid #e8e2db', 'padding' => '14px 40px', 'radius' => '12px', 'size' => '16px', 'shadow' => ''],
        'subtle'    => ['bg' => '#F7F6F4', 'color' => '#1F2A44', 'border' => 'none',              'padding' => '12px 32px', 'radius' => '6px',  'size' => '14px', 'shadow' => ''],
    ];
@endphp
<tr>
    <td style="background: {{ $outerBg }}; padding: 16px 36px; text-align: center; font-family: {{ $font }};">
        <table role="presentation" cellpadding="0" cellspacing="0" border="0" align="center" style="margin: 0 auto; border-collapse: separate;">
            @foreach($buttons as $i => $button)
                @php
                    $variant = $button['variant'] ?? 'raspberry';
                    $v       = $variants[$variant] ?? $variants['raspberry'];
                @endphp
                @if($i > 0)
                    {{-- Spacer row: Word drops margins between stacked buttons, so the gap is a cell. --}}
                    <tr><td style="height: 12px; line-height: 12px; font-size: 12px;">&nbsp;</td></tr>
                @endif
                <tr>
                    <td align="center" bgcolor="{{ $v['bg'] }}" style="background: {{ $v['bg'] }}; padding: {{ $v['padding'] }}; border-radius: {{ $v['radius'] }}; border: {{ $v['border'] }}; {{ $v['shadow'] }}">
                        <a href="{{ $button['url'] ?? '#' }}" style="color: {{ $v['color'] }}; font-size: {{ $v['size'] }}; font-weight: 700; text-decoration: none; font-family: {{ $font }};">{{ $button['label'] ?? 'Click here' }}</a>
                    </td>
                </tr>
            @endforeach
        </table>
    </td>
</tr>
