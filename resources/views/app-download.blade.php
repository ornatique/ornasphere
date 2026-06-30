<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Download Ornatique</title>
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: Arial, Helvetica, sans-serif;
            color: #1f2933;
            background: #f5f7fa;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }

        main {
            width: 100%;
            max-width: 420px;
            background: #ffffff;
            border: 1px solid #d9e2ec;
            border-radius: 8px;
            padding: 28px;
            text-align: center;
            box-shadow: 0 12px 30px rgba(15, 23, 42, 0.08);
        }

        h1 {
            margin: 0 0 10px;
            font-size: 28px;
            line-height: 1.2;
        }

        p {
            margin: 0 0 24px;
            color: #52606d;
            line-height: 1.5;
        }

        .actions {
            display: grid;
            gap: 12px;
        }

        a {
            display: block;
            padding: 14px 16px;
            border-radius: 6px;
            color: #ffffff;
            background: #0f172a;
            font-weight: 700;
            text-decoration: none;
        }

        a.secondary {
            color: #0f172a;
            background: #e4e7eb;
        }
    </style>
</head>
<body>
    <main>
        <h1>Download Ornatique</h1>
        <p>Select your device store to install the app.</p>

        <div class="actions">
            <a href="{{ $androidUrl }}">Get it on Google Play</a>
            <a class="secondary" href="{{ $iosUrl }}">Download on the App Store</a>
        </div>
    </main>
</body>
</html>
