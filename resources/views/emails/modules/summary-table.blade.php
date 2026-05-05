{{--
  Detail / Summary Table — key/value pairs in a rounded coloured box.

  Rule 4: when $surroundBg is a solid colour (light-pink, light-blue, raspberry,
  violet, success, solid-blue), the table-wrapper bg is FORCED to eggshell (#F7F6F4)
  for legibility. When $surroundBg is white or eggshell, the default pink wrapper
  (#FAD6E0) is used (matches the mockup).

  Variables:
    $rows        array<array{label:string,value:string,valueColor?:string}>
    $surroundBg  string  Outer <td> bg. Default '#ffffff'.
--}}
@php
    $rows       = $rows       ?? [];
    $surroundBg = $surroundBg ?? '#f5f0eb';

    // Rule 4: eggshell table on any solid-colour background, pink on white/eggshell.
    $colourBgs = ['#fce4ec', '#FAD6E0', '#f0f9ff', '#dbeafe', '#3b82f6', '#e74c6f', '#ede9fe', '#d1fae5', '#1F2A44'];
    $tableBg = in_array(strtolower($surroundBg), array_map('strtolower', $colourBgs)) ? '#F7F6F4' : '#FAD6E0';
    $total   = count($rows);
@endphp
<tr>
    <td style="background: {{ $surroundBg }}; padding: 16px 36px;">
        <div style="background: {{ $tableBg }}; border-radius: 10px; padding: 16px 20px;">
            <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="font-family: 'Segoe UI', Inter, Arial, sans-serif;">
                @foreach($rows as $i => $row)
                    @php $isLast = ($i === $total - 1); @endphp
                    <tr>
                        <td style="padding: 8px 0; font-size: 14px; font-weight: 700; color: #1F2A44;{{ $isLast ? '' : ' border-bottom: 1px solid #e8e2db;' }}">{{ $row['label'] ?? '' }}</td>
                        <td style="padding: 8px 0; font-size: 14px; color: {{ $row['valueColor'] ?? '#1F2A44' }}; text-align: right;{{ $isLast ? '' : ' border-bottom: 1px solid #e8e2db;' }}">{!! $row['value'] ?? '' !!}</td>
                    </tr>
                @endforeach
            </table>
        </div>
    </td>
</tr>
