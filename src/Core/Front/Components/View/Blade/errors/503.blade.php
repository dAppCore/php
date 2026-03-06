@php
    $appName = config('core.app.name', __('core::core.brand.name'));
    $appIcon = config('core.app.icon', '/images/icon.svg');
    $statusUrl = config('core.urls.status');
@endphp
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('core::core.errors.503.title') }} - {{ $appName }}</title>
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
            background: linear-gradient(to right, #fbbf24, #f59e0b);
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
        .status-link {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            font-weight: 500;
            text-decoration: none;
            background: rgba(255, 255, 255, 0.1);
            color: #e2e8f0;
            border: 1px solid rgba(255, 255, 255, 0.1);
            transition: all 0.15s ease;
        }
        .status-link:hover {
            background: rgba(255, 255, 255, 0.15);
        }
        .refresh-note {
            margin-top: 2rem;
            font-size: 0.875rem;
            color: #64748b;
        }
    </style>
</head>
<body>
    <div class="container">
        <img src="/images/vi/vi_503.webp" alt="Maintenance illustration" class="vi-image">
        <div class="code">503</div>
        <h1>{{ __('core::core.errors.503.heading') }}</h1>
        <p>{{ __('core::core.errors.503.message') }}</p>
        @if($statusUrl)
        <a href="{{ $statusUrl }}" class="status-link" target="_blank" rel="noopener">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"></circle>
                <polyline points="12,6 12,12 16,14"></polyline>
            </svg>
            {{ __('core::core.errors.503.check_status') }}
        </a>
        @endif
        <p class="refresh-note">{{ __('core::core.errors.503.auto_refresh') }}</p>
    </div>
    <script>
        setTimeout(function() { location.reload(); }, 30000);
    </script>
</body>
</html>
