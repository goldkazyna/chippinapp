<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Chippin — Telegram Login</title>
    <style>
        body {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            background: #f5f5f5;
            font-family: -apple-system, BlinkMacSystemFont, sans-serif;
        }
        .container {
            text-align: center;
            padding: 32px;
        }
        .title {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 24px;
            color: #1a1a1a;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="title">Log in with Telegram</div>
        <script async src="https://telegram.org/js/telegram-widget.js?22"
            data-telegram-login="{{ config('services.telegram.bot_username') }}"
            data-size="large"
            data-radius="12"
            data-onauth="onTelegramAuth(user)"
            data-request-access="write">
        </script>
        <script>
            function onTelegramAuth(user) {
                // Send data back to the Flutter app via URL scheme
                const params = new URLSearchParams(user).toString();
                window.location.href = 'chippin://telegram-auth?' + params;
            }
        </script>
    </div>
</body>
</html>
