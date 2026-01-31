<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bug Report</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            line-height: 1.6;
            color: #333333;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 700px;
            margin: 20px auto;
            background-color: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        .header {
            background-color: #dc2626;
            color: #ffffff;
            padding: 25px 30px;
        }
        .header h1 {
            margin: 0;
            font-size: 22px;
            font-weight: 600;
        }
        .header .subtitle {
            margin: 5px 0 0 0;
            font-size: 14px;
            opacity: 0.9;
        }
        .preview-badge {
            display: inline-block;
            background-color: #fbbf24;
            color: #92400e;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            margin-left: 10px;
            vertical-align: middle;
        }
        .section {
            padding: 20px 30px;
            border-bottom: 1px solid #e5e7eb;
        }
        .section:last-child {
            border-bottom: none;
        }
        .section-title {
            font-size: 14px;
            font-weight: 600;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 12px;
        }
        .info-grid {
            display: grid;
            grid-template-columns: 140px 1fr;
            gap: 8px 16px;
        }
        .info-label {
            font-weight: 500;
            color: #6b7280;
        }
        .info-value {
            color: #111827;
        }
        .description-box {
            background-color: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            padding: 15px;
            white-space: pre-wrap;
            word-break: break-word;
        }
        .console-box {
            background-color: #1f2937;
            color: #f9fafb;
            border-radius: 6px;
            padding: 15px;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            white-space: pre-wrap;
            word-break: break-word;
            max-height: 400px;
            overflow-y: auto;
        }
        .footer {
            background-color: #f9fafb;
            padding: 15px 30px;
            text-align: center;
            font-size: 12px;
            color: #9ca3af;
        }
        .no-data {
            color: #9ca3af;
            font-style: italic;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>
                Bug Report
                @if($bugReport['is_preview_user'] ?? false)
                    <span class="preview-badge">PREVIEW USER</span>
                @endif
            </h1>
            <p class="subtitle">Submitted {{ $bugReport['server_timestamp'] ?? now()->toIso8601String() }}</p>
        </div>

        {{-- User Information --}}
        <div class="section">
            <div class="section-title">User Information</div>
            <div class="info-grid">
                <span class="info-label">User ID:</span>
                <span class="info-value">{{ $bugReport['user_id'] ?? 'Guest (not logged in)' }}</span>

                <span class="info-label">Name:</span>
                <span class="info-value">{{ $bugReport['user_name'] ?: 'N/A' }}</span>

                <span class="info-label">Email:</span>
                <span class="info-value">{{ $bugReport['user_email'] ?? 'N/A' }}</span>

                <span class="info-label">Preview User:</span>
                <span class="info-value">{{ ($bugReport['is_preview_user'] ?? false) ? 'Yes' : 'No' }}</span>
            </div>
        </div>

        {{-- Bug Description --}}
        <div class="section">
            <div class="section-title">Bug Description</div>
            <div class="description-box">{{ $bugReport['description'] }}</div>
        </div>

        {{-- Expected Behaviour (if provided) --}}
        @if(!empty($bugReport['expected_behaviour']))
        <div class="section">
            <div class="section-title">Expected Behaviour</div>
            <div class="description-box">{{ $bugReport['expected_behaviour'] }}</div>
        </div>
        @endif

        {{-- Technical Context --}}
        <div class="section">
            <div class="section-title">Technical Context</div>
            <div class="info-grid">
                <span class="info-label">Page URL:</span>
                <span class="info-value">{{ $bugReport['page_url'] ?? 'N/A' }}</span>

                <span class="info-label">Browser:</span>
                <span class="info-value" style="font-size: 12px;">{{ $bugReport['user_agent'] ?? 'N/A' }}</span>

                <span class="info-label">Screen Size:</span>
                <span class="info-value">{{ $bugReport['screen_size'] ?? 'N/A' }}</span>

                <span class="info-label">Viewport:</span>
                <span class="info-value">{{ $bugReport['viewport_size'] ?? 'N/A' }}</span>

                <span class="info-label">IP Address:</span>
                <span class="info-value">{{ $bugReport['ip_address'] ?? 'N/A' }}</span>

                <span class="info-label">Client Time:</span>
                <span class="info-value">{{ $bugReport['client_timestamp'] ?? 'N/A' }}</span>
            </div>
        </div>

        {{-- Console Logs --}}
        <div class="section">
            <div class="section-title">Console Logs (Last 100 Entries)</div>
            @if(!empty($bugReport['console_logs']))
                <div class="console-box">{{ $bugReport['console_logs'] }}</div>
            @else
                <p class="no-data">No console logs captured</p>
            @endif
        </div>

        <div class="footer">
            <p>This bug report was automatically generated by Fynla.</p>
        </div>
    </div>
</body>
</html>
