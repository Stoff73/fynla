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
    $outerBg  string  Outer <td> bg. Default #ffffff.
--}}
@php
    $buttons = $buttons ?? [];
    $outerBg = $outerBg ?? '#f5f0eb';

    $variants = [
        'raspberry' => ['bg' => '#e74c6f', 'color' => '#ffffff', 'border' => 'none',              'padding' => '14px 40px', 'radius' => '12px', 'size' => '16px', 'shadow' => 'box-shadow: 0 4px 14px #d9a0b0;'],
        'green'     => ['bg' => '#20B486', 'color' => '#ffffff', 'border' => 'none',              'padding' => '14px 40px', 'radius' => '12px', 'size' => '16px', 'shadow' => ''],
        'dark'      => ['bg' => '#1F2A44', 'color' => '#ffffff', 'border' => 'none',              'padding' => '14px 40px', 'radius' => '12px', 'size' => '16px', 'shadow' => ''],
        'outline'   => ['bg' => '#ffffff', 'color' => '#e74c6f', 'border' => '2px solid #e8e2db', 'padding' => '14px 40px', 'radius' => '12px', 'size' => '16px', 'shadow' => ''],
        'subtle'    => ['bg' => '#F7F6F4', 'color' => '#1F2A44', 'border' => 'none',              'padding' => '12px 32px', 'radius' => '6px',  'size' => '14px', 'shadow' => ''],
    ];
@endphp
<tr>
    <td style="background: {{ $outerBg }}; padding: 16px 36px; text-align: center; font-family: 'Segoe UI', Inter, Arial, sans-serif;">
        @foreach($buttons as $i => $button)
            @php
                $variant = $button['variant'] ?? 'raspberry';
                $v       = $variants[$variant] ?? $variants['raspberry'];
            @endphp
            <div style="{{ $i === 0 ? 'margin-top:0;' : 'margin-top:12px;' }}">
                <a href="{{ $button['url'] ?? '#' }}" style="display: inline-block; padding: {{ $v['padding'] }}; background: {{ $v['bg'] }}; color: {{ $v['color'] }}; font-size: {{ $v['size'] }}; font-weight: 700; border-radius: {{ $v['radius'] }}; text-decoration: none; border: {{ $v['border'] }}; {{ $v['shadow'] }} font-family: 'Segoe UI', Inter, Arial, sans-serif;">{{ $button['label'] ?? 'Click here' }}</a>
            </div>
        @endforeach
    </td>
</tr>
