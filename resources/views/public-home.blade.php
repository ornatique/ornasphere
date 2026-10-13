<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>OrnaSphere</title>
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: Arial, Helvetica, sans-serif;
            background: #1f2142;
            color: #ffffff;
        }

        .page {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 28px;
        }

        .panel {
            width: 100%;
            max-width: 760px;
            text-align: center;
            background: #282a4a;
            border: 1px solid #373a66;
            border-radius: 10px;
            padding: 38px 30px;
            box-shadow: 0 12px 34px rgba(0, 0, 0, 0.32);
        }

        .logo {
            width: 360px;
            max-width: 100%;
            height: auto;
            display: block;
            margin: 0 auto 22px;
        }

        h1 {
            margin: 0 0 10px;
            font-size: 30px;
            line-height: 1.2;
        }

        p {
            max-width: 560px;
            margin: 0 auto 26px;
            color: #c9cbe4;
            line-height: 1.6;
            font-size: 15px;
        }

        .notice {
            max-width: 560px;
            margin: 0 auto 18px;
            padding: 12px 14px;
            border-radius: 6px;
            background: rgba(255, 171, 64, 0.12);
            border: 1px solid rgba(255, 171, 64, 0.34);
            color: #ffd29a;
            font-size: 14px;
            line-height: 1.5;
        }

    </style>
</head>
<body>
    <main class="page">
        <section class="panel">
            <img class="logo" src="{{ url('/celestial/assets/images/ornasphere-auth-logo.png') }}" alt="OrnaSphere">
            <h1>OrnaSphere Jewellery ERP</h1>
            @if (session('company_unavailable'))
                <div class="notice">{{ session('company_unavailable') }}</div>
            @endif
            <p>
                This company workspace is not available or has been removed. Please contact your administrator if you need access to a company account.
            </p>
        </section>
    </main>
</body>
</html>
