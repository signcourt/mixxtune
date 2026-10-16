<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >
    <title>Scheduled Maintenance - Mixx Tune</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            background: #f1f5f9;
            font-family:
                Inter,
                ui-sans-serif,
                system-ui,
                -apple-system,
                BlinkMacSystemFont,
                "Segoe UI",
                sans-serif;
            color: #0f172a;
        }

        .card {
            width: 100%;
            max-width: 620px;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 24px;
            padding: 42px;
            text-align: center;
            box-shadow: 0 20px 60px rgba(15, 23, 42, .10);
        }

        .icon {
            width: 72px;
            height: 72px;
            margin: 0 auto 22px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 20px;
            background: #0f172a;
            color: white;
            font-size: 30px;
        }

        h1 {
            margin: 0;
            font-size: 28px;
        }

        p {
            margin: 14px auto 0;
            max-width: 480px;
            line-height: 1.7;
            color: #64748b;
        }

        .status {
            margin-top: 26px;
            padding: 14px 18px;
            border-radius: 14px;
            background: #fffbeb;
            border: 1px solid #fde68a;
            color: #92400e;
            font-size: 14px;
        }
    </style>
</head>

<body>
    <div class="card">
        <div class="icon">⚙</div>

        <h1>Scheduled Maintenance</h1>

        <p>
            {{ $message }}
        </p>

        <div class="status">
            Mixx Tune will be available again shortly.
        </div>
    </div>
</body>
</html>
