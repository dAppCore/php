@php
    $appName = config('core.app.name', __('core::core.brand.name'));
    $appIcon = config('core.app.icon', '/images/icon.svg');
@endphp
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('core::core.errors.404.title') }} - {{ $appName }}</title>
    <link rel="icon" href="{{ $appIcon }}" type="image/svg+xml">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(to bottom right, #0f172a, #1e1b4b, #0f172a);
            color: #e2e8f0;
        }
        .container {
            text-align: center;
            padding: 2rem;
            max-width: 600px;
        }
        .vi-image {
            width: 280px;
            height: auto;
            margin: 0 auto 2rem;
            filter: drop-shadow(0 10px 30px rgba(139, 92, 246, 0.3));
        }
        h1 {
            font-size: 1.5rem;
            font-weight: 600;
            color: #f8fafc;
            margin-bottom: 0.75rem;
        }
        .code {
            font-size: 4rem;
            font-weight: 700;
            background: linear-gradient(to right, #a78bfa, #818cf8);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 0.5rem;
        }
        p {
            font-size: 1rem;
            color: #94a3b8;
            margin-bottom: 2rem;
            line-height: 1.6;
        }
        .actions {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
        }
        a {
            display: inline-block;
            padding: 0.75rem 1.5rem;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.15s ease;
        }
        .primary {
            background: linear-gradient(to right, #a78bfa, #818cf8);
            color: #0f172a;
        }
        .primary:hover {
            filter: brightness(1.1);
        }
        .secondary {
            background: rgba(255, 255, 255, 0.1);
            color: #e2e8f0;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        .secondary:hover {
            background: rgba(255, 255, 255, 0.15);
        }
    </style>
</head>
<body>
    <div class="container">
        <img src="/images/vi/vi_404.webp" alt="404 illustration" class="vi-image">
        <div class="code">404</div>
        <h1>{{ __('core::core.errors.404.heading') }}</h1>
        <p>{{ __('core::core.errors.404.message') }}</p>
        <div class="actions">
            <a href="/" class="primary">{{ __('core::core.errors.404.back_home') }}</a>
            <a href="/help" class="secondary">{{ __('core::core.errors.404.help') }}</a>
        </div>
    </div>
</body>
</html>
