{{--
    Core Icon - FontAwesome Implementation with Pro/Free Detection

    Uses FontAwesome with automatic brand/jelly detection:
    - Brand icons (github, twitter, etc.) → fa-brands
    - Jelly icons (globe, clock, etc.) → fa-jelly (Pro) or fa-solid (Free fallback)
    - All others → fa-solid (or explicit style override)

    Pro styles: solid, regular, light, thin, duotone, brands, sharp, jelly
    Free styles: solid, regular, brands (others fall back automatically)

    Props: name, style (solid|regular|light|thin|duotone|brands|jelly),
           size (xs|sm|lg|xl|2xl), spin, pulse, flip, rotate, fw
--}}
@props([
    'name',
    'style' => null,      // Override: solid, regular, light, thin, duotone, brands, jelly
    'size' => null,       // Size class: xs, sm, lg, xl, 2xl, etc.
    'spin' => false,      // Animate spinning
    'pulse' => false,     // Animate pulsing
    'flip' => null,       // horizontal, vertical, both
    'rotate' => null,     // 90, 180, 270
    'fw' => false,        // Fixed width
])

@php
    use Core\Pro;

    // Brand icons - always use fa-brands (available in Free)
    $brandIcons = [
        // Social
        'facebook', 'facebook-f', 'facebook-messenger', 'instagram', 'twitter', 'x-twitter',
        'tiktok', 'youtube', 'linkedin', 'linkedin-in', 'pinterest', 'pinterest-p',
        'snapchat', 'snapchat-ghost', 'whatsapp', 'telegram', 'telegram-plane',
        'discord', 'twitch', 'reddit', 'reddit-alien', 'threads', 'mastodon', 'bluesky',
        // Media
        'spotify', 'soundcloud', 'apple', 'itunes', 'itunes-note', 'bandcamp',
        'deezer', 'napster', 'audible', 'vimeo', 'vimeo-v', 'dailymotion',
        // Dev/Tech
        'github', 'github-alt', 'gitlab', 'bitbucket', 'dribbble', 'behance',
        'figma', 'sketch', 'codepen', 'jsfiddle', 'stack-overflow',
        'npm', 'node', 'node-js', 'js', 'php', 'python', 'java', 'rust',
        'react', 'vuejs', 'angular', 'laravel', 'symfony', 'docker',
        'aws', 'google', 'microsoft',
        // Commerce
        'shopify', 'etsy', 'amazon', 'ebay', 'paypal', 'stripe', 'cc-stripe',
        'cc-visa', 'cc-mastercard', 'cc-amex', 'cc-paypal', 'cc-apple-pay',
        'bitcoin', 'btc', 'ethereum', 'monero',
        // Communication
        'slack', 'slack-hash', 'skype', 'viber', 'line', 'wechat', 'qq',
        // Other
        'wordpress', 'wordpress-simple', 'medium', 'blogger', 'tumblr',
        'patreon', 'kickstarter', 'product-hunt', 'airbnb', 'uber', 'lyft',
        'yelp', 'tripadvisor',
    ];

    // Jelly style icons - full list from FA Pro+ metadata
    // Generated from ~/Code/lib/fontawesome/metadata/icon-families.json
    $jellyIcons = [
        'address-card', 'alarm-clock', 'anchor', 'angle-down', 'angle-left',
        'angle-right', 'angle-up', 'arrow-down', 'arrow-down-to-line',
        'arrow-down-wide-short', 'arrow-left', 'arrow-right',
        'arrow-right-arrow-left', 'arrow-right-from-bracket',
        'arrow-right-to-bracket', 'arrow-rotate-left', 'arrow-rotate-right',
        'arrow-up', 'arrow-up-from-bracket', 'arrow-up-from-line',
        'arrow-up-right-from-square', 'arrow-up-wide-short', 'arrows-rotate',
        'at', 'backward', 'backward-step', 'bag-shopping', 'bars',
        'battery-bolt', 'battery-empty', 'battery-half', 'battery-low',
        'battery-three-quarters', 'bed', 'bell', 'block-quote', 'bold', 'bolt',
        'bomb', 'book', 'book-open', 'bookmark', 'box', 'box-archive', 'bug',
        'building', 'bus', 'cake-candles', 'calendar', 'camera', 'camera-slash',
        'car', 'cart-shopping', 'chart-bar', 'chart-pie', 'check', 'circle',
        'circle-check', 'circle-half-stroke', 'circle-info', 'circle-plus',
        'circle-question', 'circle-user', 'circle-xmark', 'city', 'clipboard',
        'clock', 'clone', 'cloud', 'code', 'command', 'comment', 'comment-dots',
        'comments', 'compact-disc', 'compass', 'compress', 'credit-card',
        'crown', 'database', 'desktop', 'door-closed', 'droplet', 'ellipsis',
        'envelope', 'equals', 'expand', 'eye', 'eye-slash', 'face-frown',
        'face-grin', 'face-meh', 'face-smile', 'file', 'files', 'film',
        'filter', 'fire', 'fish', 'flag', 'flower', 'folder', 'folders', 'font',
        'font-awesome', 'font-case', 'forward', 'forward-step', 'gamepad',
        'gauge', 'gear', 'gift', 'globe', 'grid', 'hand', 'headphones', 'heart',
        'heart-half', 'hourglass', 'house', 'image', 'images', 'inbox',
        'italic', 'key', 'landmark', 'language', 'laptop', 'layer-group',
        'leaf', 'life-ring', 'lightbulb', 'link', 'list', 'list-ol',
        'location-arrow', 'location-dot', 'lock', 'lock-open',
        'magnifying-glass', 'magnifying-glass-minus', 'magnifying-glass-plus',
        'map', 'martini-glass', 'microphone', 'microphone-slash', 'minus',
        'mobile', 'money-bill', 'moon', 'mug-hot', 'music', 'newspaper',
        'notdef', 'palette', 'paper-plane', 'paperclip', 'pause', 'paw',
        'pencil', 'percent', 'person-biking', 'phone', 'phone-slash', 'plane',
        'play', 'play-pause', 'plus', 'print', 'question', 'quote-left',
        'rectangle', 'rectangle-tall', 'rectangle-vertical', 'rectangle-wide',
        'scissors', 'share-nodes', 'shield', 'shield-halved', 'ship', 'shirt',
        'shop', 'sidebar', 'sidebar-flip', 'signal-bars', 'signal-bars-fair',
        'signal-bars-good', 'signal-bars-slash', 'signal-bars-weak', 'skull',
        'sliders', 'snowflake', 'sort', 'sparkles', 'square', 'square-code',
        'star', 'star-half', 'stop', 'stopwatch', 'strikethrough', 'suitcase',
        'sun', 'tag', 'terminal', 'thumbs-down', 'thumbs-up', 'thumbtack',
        'ticket', 'train', 'trash', 'tree', 'triangle', 'triangle-exclamation',
        'trophy', 'truck', 'tv-retro', 'umbrella', 'universal-access', 'user',
        'users', 'utensils', 'video', 'video-slash', 'volume', 'volume-low',
        'volume-off', 'volume-slash', 'volume-xmark', 'wand-magic-sparkles',
        'wheelchair-move', 'wifi', 'wifi-fair', 'wifi-slash', 'wifi-weak',
        'wrench', 'xmark',
    ];

    // Pro-only style fallbacks (when FA Pro not available)
    $proStyleFallbacks = [
        'light' => 'regular',
        'thin' => 'regular',
        'duotone' => 'solid',
        'sharp' => 'solid',
        'jelly' => 'solid',
    ];

    $hasFaPro = Pro::hasFontAwesomePro();

    // Determine raw style
    if ($style) {
        $rawStyle = match($style) {
            'brands', 'brand' => 'brands',
            default => $style,
        };
    } elseif (in_array($name, $brandIcons)) {
        $rawStyle = 'brands';
    } elseif (in_array($name, $jellyIcons)) {
        $rawStyle = 'jelly';
    } else {
        $rawStyle = 'solid';
    }

    // Apply fallback if Pro not available
    $finalStyle = $rawStyle;
    if (!$hasFaPro && isset($proStyleFallbacks[$rawStyle])) {
        $finalStyle = $proStyleFallbacks[$rawStyle];
    }

    $iconStyle = "fa-{$finalStyle}";

    // Build classes
    $classes = collect([
        $iconStyle,
        "fa-{$name}",
        $size ? "fa-{$size}" : null,
        $spin ? 'fa-spin' : null,
        $pulse ? 'fa-pulse' : null,
        $flip ? "fa-flip-{$flip}" : null,
        $rotate ? "fa-rotate-{$rotate}" : null,
        $fw ? 'fa-fw' : null,
    ])->filter()->implode(' ');
@endphp

<i {{ $attributes->class($classes) }} aria-hidden="true"></i>
