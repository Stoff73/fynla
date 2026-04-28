<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/png" href="{{ asset('images/logos/favicon.png') }}">
    <title>You're subscribed | Fynla</title>
    <style>
        body { margin: 0; font-family: 'Segoe UI', Inter, sans-serif; background: #F7F6F4; color: #1F2A44; }
        .wrap { max-width: 540px; margin: 64px auto; padding: 48px 32px; background: #ffffff; border-radius: 16px; text-align: center; }
        h1 { font-size: 28px; font-weight: 900; margin: 0 0 16px; color: #1F2A44; }
        p { font-size: 15px; line-height: 1.6; color: #555; margin: 0 0 16px; }
        a { display: inline-block; margin-top: 24px; padding: 12px 24px; background: #E83E6D; color: #ffffff; text-decoration: none; border-radius: 8px; font-weight: 700; }
    </style>
</head>
<body>
    <div class="wrap">
        <h1>You're subscribed</h1>
        @if($alreadyConfirmed)
            <p>Your subscription was already confirmed. We'll keep <strong>{{ $email }}</strong> in the loop.</p>
        @else
            <p>Thanks for confirming. We've added <strong>{{ $email }}</strong> to the Fynla news list.</p>
        @endif
        <a href="/news">Read latest news</a>
    </div>
</body>
</html>
