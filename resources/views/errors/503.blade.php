<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="refresh" content="60">
    <title>Under Maintenance - SAIMS</title>
    <link rel="icon" type="image/x-icon" href="{{ asset('assets/img/favicon/favicon.ico') }}">
    <style>
        :root { --bg: #f5f5f9; --card: #ffffff; --text: #566a7f; --heading: #32475c; --primary: #696cff; --soft: #e7e7ff; }
        @media (prefers-color-scheme: dark) {
            :root { --bg: #232333; --card: #2b2c40; --text: #b6bee3; --heading: #eaeaff; --soft: #35365f; }
        }
        * { box-sizing: border-box; }
        body {
            margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center;
            padding: 16px; background: var(--bg); color: var(--text);
            font-family: "Public Sans", system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
        }
        .card {
            width: 100%; max-width: 480px; text-align: center; background: var(--card);
            border-radius: 12px; padding: 40px 32px; box-shadow: 0 2px 12px rgba(50, 71, 92, .12);
        }
        .icon {
            width: 84px; height: 84px; margin: 0 auto 20px; border-radius: 50%;
            background: var(--soft); display: flex; align-items: center; justify-content: center;
        }
        .icon svg { width: 44px; height: 44px; stroke: var(--primary); }
        h1 { margin: 0 0 8px; font-size: 1.6rem; color: var(--heading); }
        p { margin: 0 0 12px; line-height: 1.55; }
        .note { font-size: .875rem; opacity: .85; }
        .brand { margin-top: 24px; font-size: .8rem; opacity: .7; }
    </style>
</head>
<body>
    <main class="card">
        <div class="icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <path d="M14.7 6.3a4 4 0 0 0-5.4 5.4L3 18v3h3l6.3-6.3a4 4 0 0 0 5.4-5.4l-2.6 2.6-2.4-.6-.6-2.4 2.6-2.6z"/>
            </svg>
        </div>
        <h1>We'll be right back</h1>
        <p>SAIMS is undergoing scheduled maintenance and system updates.</p>
        <p class="note">Please try again in a few minutes. This page refreshes automatically.</p>
        <div class="brand">SAIMS &middot; CFB Marketing</div>
    </main>
</body>
</html>
