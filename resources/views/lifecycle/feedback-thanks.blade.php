<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thanks for your feedback — Fynla</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #F7F6F4; color: #1F2A44; margin: 0; padding: 40px 20px; }
        .card { max-width: 600px; margin: 0 auto; background: #FFFFFF; border-radius: 12px; padding: 40px; box-shadow: 0 4px 16px rgba(0,0,0,0.05); }
        h1 { color: #1F2A44; font-weight: 900; }
        textarea { width: 100%; min-height: 120px; padding: 12px; border: 1px solid #EEEEEE; border-radius: 6px; font-family: inherit; font-size: 14px; box-sizing: border-box; }
        .btn { display: inline-block; padding: 12px 24px; background: #E83E6D; color: #FFFFFF; text-decoration: none; font-weight: 600; border-radius: 8px; border: none; font-size: 16px; cursor: pointer; }
        .btn-secondary { background: transparent; color: #1F2A44; border: 1px solid #1F2A44; margin-left: 8px; }
        .reason { background: #FDFAF7; padding: 12px; border-radius: 6px; margin: 20px 0; }
    </style>
</head>
<body>
    <div class="card">
        <h1>Thanks — that helps us understand</h1>

        <div class="reason">
            <strong>You said:</strong> {{ ucwords(str_replace('_', ' ', $reason)) }}
        </div>

        <p>If there's anything else you'd like to share, we'd love to hear it. Optional — but every word helps us improve Fynla.</p>

        <form method="POST" action="{{ $feedback_text_url }}">
            @csrf
            <input type="hidden" name="user_id" value="{{ $user_id }}">
            <input type="hidden" name="campaign" value="{{ $campaign }}">
            <textarea name="free_text" maxlength="2000" placeholder="Tell us more (optional)..."></textarea>
            <p style="margin: 20px 0;">
                <button type="submit" class="btn">Send feedback</button>
                <a href="{{ config('app.url') }}" class="btn btn-secondary">No thanks, I'm done</a>
            </p>
        </form>
    </div>
</body>
</html>
