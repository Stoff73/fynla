<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Financial adviser export pack</title>
    <style>
        @page { margin: 28mm 18mm 24mm; }
        body { color: #1f2937; font-family: DejaVu Sans, sans-serif; font-size: 10px; line-height: 1.45; }
        h1 { color: #0f3f5c; font-size: 24px; margin: 0 0 5px; }
        h2 { border-bottom: 1px solid #d8e1e7; color: #0f3f5c; font-size: 15px; margin: 22px 0 8px; padding-bottom: 4px; }
        h3 { color: #334155; font-size: 11px; margin: 12px 0 5px; }
        p { margin: 4px 0; }
        table { border-collapse: collapse; margin: 5px 0 12px; width: 100%; }
        th { background: #eef4f7; color: #0f3f5c; font-weight: bold; text-align: left; }
        th, td { border-bottom: 1px solid #d8e1e7; padding: 6px; vertical-align: top; }
        .amount { text-align: right; white-space: nowrap; }
        .meta { color: #64748b; }
        .summary { background: #f7f2e9; border-left: 3px solid #7c3aed; margin: 8px 0; padding: 8px 10px; }
        .assumptions-section { page-break-before: always; }
        .assumption-block { page-break-inside: avoid; }
        .disclaimer { border-top: 1px solid #d8e1e7; color: #64748b; font-size: 8px; margin-top: 24px; padding-top: 8px; }
    </style>
</head>
<body>
@php
    $money = static fn ($value) => '£'.number_format((float) $value, 2);
    $title = static fn ($value) => str((string) $value)->replace('_', ' ')->title();
    $scalarRows = static function (array $values) use ($title): array {
        return collect($values)
            ->reject(fn ($value, $key) => str_contains(strtolower((string) $key), 'score'))
            ->reject(fn ($value, $key) => str_ends_with((string) $key, '_default'))
            ->reject(fn ($value, $key) => in_array($key, ['fees', 'has_overrides', 'total_value'], true))
            ->filter(fn ($value) => is_scalar($value) && $value !== '' && $value !== null)
            ->map(fn ($value, $key) => ['key' => $key, 'label' => $title($key), 'value' => $value])
            ->values()
            ->all();
    };
    $displayValue = static function (array $row) use ($title): string {
        if (is_bool($row['value'])) {
            return $row['value'] ? 'Yes' : 'No';
        }
        if (is_numeric($row['value']) && str_contains((string) $row['key'], 'rate')) {
            return number_format((float) $row['value'], 2).'%';
        }
        if (is_string($row['value']) && str_contains($row['value'], '_')) {
            return $title($row['value'])->toString();
        }

        return (string) $row['value'];
    };
@endphp

<h1>Financial adviser export pack</h1>
<p><strong>{{ $pack['profile']['name'] }}</strong></p>
<p class="meta">Generated {{ \Carbon\Carbon::parse($pack['generated_at'])->format('j F Y, H:i') }}. Data as at {{ \Carbon\Carbon::parse($pack['data_as_at'])->format('j F Y') }}.</p>

<h2>Profile</h2>
<table>
    <tbody>
    @if($pack['profile']['date_of_birth'])<tr><th>Date of birth</th><td>{{ \Carbon\Carbon::parse($pack['profile']['date_of_birth'])->format('j F Y') }}</td></tr>@endif
    @if($pack['profile']['marital_status'])<tr><th>Marital status</th><td>{{ $title($pack['profile']['marital_status']) }}</td></tr>@endif
    @if($pack['profile']['email'])<tr><th>Email</th><td>{{ $pack['profile']['email'] }}</td></tr>@endif
    @if($pack['profile']['phone'])<tr><th>Phone</th><td>{{ $pack['profile']['phone'] }}</td></tr>@endif
    </tbody>
</table>

@if(count($pack['assets']))
<h2>Assets</h2>
<table>
    <thead><tr><th>Asset</th><th>Type</th><th>Ownership</th><th class="amount">Value</th></tr></thead>
    <tbody>
    @foreach($pack['assets'] as $asset)
        <tr>
            <td>{{ $asset['name'] }}</td>
            <td>{{ $title($asset['type']) }}</td>
            <td>{{ $title($asset['ownership_type']) }} ({{ number_format($asset['ownership_percentage'], 0) }}%)</td>
            <td class="amount">{{ $money($asset['value']) }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
@endif

@if(count($pack['liabilities']))
<h2>Liabilities</h2>
<table>
    <thead><tr><th>Liability</th><th>Type</th><th>Interest rate</th><th class="amount">Balance</th></tr></thead>
    <tbody>
    @foreach($pack['liabilities'] as $liability)
        <tr>
            <td>{{ $liability['name'] }}</td>
            <td>{{ $title($liability['type']) }}</td>
            <td>{{ $liability['interest_rate'] === null ? 'Not recorded' : number_format($liability['interest_rate'], 2).'%' }}</td>
            <td class="amount">{{ $money($liability['balance']) }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
@endif

@php
    $income = $pack['income_expenditure']['income'];
    $expenditure = $pack['income_expenditure']['expenditure'];
    $annualIncome = (float) ($income['total_annual_income'] ?? 0);
    $annualExpenditure = (float) ($expenditure['annual_expenditure'] ?? 0);
@endphp
@if($annualIncome > 0 || $annualExpenditure > 0)
<h2>Income and expenditure</h2>
<table>
    <tbody>
    @if($annualIncome > 0)<tr><th>Total annual income</th><td class="amount">{{ $money($annualIncome) }}</td></tr>@endif
    @if($annualExpenditure > 0)<tr><th>Total annual expenditure</th><td class="amount">{{ $money($annualExpenditure) }}</td></tr>@endif
    </tbody>
</table>
@endif

@if($pack['risk_profile'])
<h2>Risk profile</h2>
<p><strong>{{ $pack['risk_profile']['config']['display_name'] }}</strong></p>
<p>{{ $pack['risk_profile']['config']['short_description'] }}</p>
@endif

@if(count($pack['goals']))
<h2>Goals</h2>
<table>
    <thead><tr><th>Goal</th><th>Target date</th><th class="amount">Current</th><th class="amount">Target</th></tr></thead>
    <tbody>
    @foreach($pack['goals'] as $goal)
        <tr>
            <td>{{ $goal['name'] }}</td>
            <td>{{ $goal['target_date'] ? \Carbon\Carbon::parse($goal['target_date'])->format('j F Y') : 'Not recorded' }}</td>
            <td class="amount">{{ $money($goal['current_amount']) }}</td>
            <td class="amount">{{ $money($goal['target_amount']) }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
@endif

@if(count($pack['life_events']))
<h2>Life events</h2>
<table>
    <thead><tr><th>Event</th><th>Expected date</th><th>Impact</th><th class="amount">Amount</th></tr></thead>
    <tbody>
    @foreach($pack['life_events'] as $event)
        <tr>
            <td>{{ $event['name'] }}</td>
            <td>{{ $event['expected_date'] ? \Carbon\Carbon::parse($event['expected_date'])->format('j F Y') : 'Not recorded' }}</td>
            <td>{{ $title($event['impact_type']) }}</td>
            <td class="amount">{{ $money($event['amount']) }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
@endif

@if(count($pack['plan_summaries']))
<h2>Module plan summaries</h2>
@foreach($pack['plan_summaries'] as $module => $summary)
    @php($rows = $scalarRows($summary))
    @if(count($rows))
        <div class="summary">
            <h3>{{ $module }}</h3>
            @foreach($rows as $row)<p><strong>{{ $row['label'] }}:</strong> {{ is_bool($row['value']) ? ($row['value'] ? 'Yes' : 'No') : $row['value'] }}</p>@endforeach
        </div>
    @endif
@endforeach
@endif

@if(count($pack['assumptions']))
<div class="assumptions-section">
<h2>Planning assumptions</h2>
@foreach($pack['assumptions'] as $module => $assumptions)
    @php($rows = $scalarRows($assumptions))
    @if(count($rows))
        <div class="assumption-block">
            <h3>{{ $title($module) }}</h3>
            <table><tbody>
            @foreach($rows as $row)<tr><th>{{ $row['label'] }}</th><td>{{ $displayValue($row) }}</td></tr>@endforeach
            </tbody></table>
        </div>
    @endif
@endforeach
</div>
@endif

<p class="disclaimer">This document was generated by Fynla Financial Planning Software from the information recorded at the data-as-at date. It is intended to support a conversation with a qualified financial adviser. This document is not financial advice.</p>
</body>
</html>
