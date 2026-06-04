<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>G-SHT | High Roller VIP Club</title>
    <link rel="icon" type="image/png" href="/logo.png">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:300,400,500,600,700,800,900" rel="stylesheet">
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const el = document.getElementById('app');
                if (el) el.innerHTML = '<div style="padding:2rem;max-width:560px;margin:6rem auto;font-family:sans-serif;color:#fafafa;background:#111114;border:1px solid #D4AF37;border-radius:16px"><h1 style="color:#fcd34d;margin:0 0 .5rem;font-size:1.25rem">Frontend assets not built</h1><p style="color:#a1a1aa;font-size:.875rem;line-height:1.5">Run <code style="background:#0c0c0f;padding:.125rem .375rem;border-radius:4px;color:#fcd34d">npm install &amp;&amp; npm run build</code> from the project root to build the SPA, or <code style="background:#0c0c0f;padding:.125rem .375rem;border-radius:4px;color:#fcd34d">npm run dev</code> for hot-reload.</p></div>';
            });
        </script>
    @endif
</head>
<body>
    <div id="app"></div>
</body>
</html>
