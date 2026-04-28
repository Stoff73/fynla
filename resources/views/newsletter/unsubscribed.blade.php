<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/png" href="{{ asset('images/logos/favicon.png') }}">
    <title>Unsubscribed | Fynla</title>
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
        <h1>You've unsubscribed</h1>
        <p>We've removed <strong>{{ $email }}</strong> from the Fynla news list. You won't receive any more announcements from us.</p>
        <p>Changed your mind? Just sign up again from the news page.</p>
        <a href="/news">Back to Fynla</a>
    </div>
</body>
</html>
