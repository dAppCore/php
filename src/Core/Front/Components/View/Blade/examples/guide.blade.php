{{--
Example: Guide with TOC using Sidebar Right Layout
Route: /examples/guide
--}}

<x-layouts.sidebar-right
    title="Getting Started with Host Social"
    description="A complete guide to setting up and using Host Social for social media scheduling."
    backLink="/guides"
    backLabel="All Guides">

    <x-slot:toc>
        <x-toc-link href="#introduction">Introduction</x-toc-link>
        <x-toc-link href="#connecting-accounts">Connecting accounts</x-toc-link>
        <x-toc-link href="#creating-posts">Creating posts</x-toc-link>
        <x-toc-link href="#scheduling">Scheduling</x-toc-link>
        <x-toc-link href="#analytics">Analytics</x-toc-link>
        <x-toc-link href="#best-practices">Best practices</x-toc-link>
        <x-toc-link href="#troubleshooting">Troubleshooting</x-toc-link>
    </x-slot:toc>

    <h2 id="introduction">Introduction</h2>

    <p>
        Host Social is your all-in-one social media management platform. Schedule posts, track performance,
        and manage all your social accounts from a single dashboard.
    </p>

    <p>
        This guide will walk you through everything you need to know to get started and make the most of
        Host Social's features.
    </p>

    <h2 id="connecting-accounts">Connecting your social accounts</h2>

    <p>
        Before you can start scheduling posts, you'll need to connect your social media accounts.
        Host Social supports the following platforms:
    </p>

    <ul>
        <li><strong>Instagram</strong> - Business and Creator accounts</li>
        <li><strong>Twitter/X</strong> - Personal and Business accounts</li>
        <li><strong>Facebook</strong> - Pages and Groups</li>
        <li><strong>LinkedIn</strong> - Personal and Company pages</li>
        <li><strong>TikTok</strong> - Creator and Business accounts</li>
        <li><strong>YouTube</strong> - Channels with upload permission</li>
    </ul>

    <p>To connect an account:</p>

    <ol>
        <li>Navigate to <strong>Settings → Connected Accounts</strong></li>
        <li>Click the platform you want to connect</li>
        <li>Authorise Host Social to access your account</li>
        <li>Select which profile or page to use</li>
    </ol>

    <blockquote>
        <p><strong>Tip:</strong> Connect all your accounts at once to enable cross-posting from day one.</p>
    </blockquote>

    <h2 id="creating-posts">Creating posts</h2>

    <p>
        Host Social makes it easy to create content that works across multiple platforms. The composer
        automatically adapts your content to each platform's requirements.
    </p>

    <h3>Using the post composer</h3>

    <p>
        Click <strong>Create Post</strong> to open the composer. You can write your content once and
        customise it for each platform if needed.
    </p>

    <pre><code>Example post structure:
- Hook (first line that grabs attention)
- Value (the main content)
- Call to action (what you want readers to do)</code></pre>

    <h3>Adding media</h3>

    <p>
        Drag and drop images or videos into the composer, or click the media button to upload.
        Host Social will automatically resize and optimise your media for each platform.
    </p>

    <h2 id="scheduling">Scheduling your posts</h2>

    <p>
        Once you've created your post, you can either publish immediately or schedule it for later.
        Use the calendar view to plan your content strategy across the week or month.
    </p>

    <h3>Best times to post</h3>

    <p>
        Host Social analyses your audience engagement and suggests optimal posting times.
        Look for the <span class="text-green-400">green indicators</span> on the calendar for
        high-engagement windows.
    </p>

    <h2 id="analytics">Understanding your analytics</h2>

    <p>
        Track your performance across all platforms from a single dashboard. Key metrics include:
    </p>

    <ul>
        <li><strong>Reach</strong> - How many people saw your content</li>
        <li><strong>Engagement</strong> - Likes, comments, shares, and saves</li>
        <li><strong>Click-through rate</strong> - For posts with links</li>
        <li><strong>Follower growth</strong> - Net new followers over time</li>
    </ul>

    <h2 id="best-practices">Best practices</h2>

    <p>
        To get the most out of Host Social, follow these recommendations:
    </p>

    <ol>
        <li><strong>Batch your content</strong> - Create a week's worth of posts in one session</li>
        <li><strong>Use the preview</strong> - Always check how your post looks on each platform</li>
        <li><strong>Mix content types</strong> - Alternate between images, videos, and text posts</li>
        <li><strong>Engage after posting</strong> - Set reminders to respond to comments</li>
        <li><strong>Review analytics weekly</strong> - Adjust your strategy based on performance</li>
    </ol>

    <h2 id="troubleshooting">Troubleshooting</h2>

    <h3>Post failed to publish</h3>

    <p>
        If a scheduled post fails, check the following:
    </p>

    <ul>
        <li>Your account connection is still active (re-authorise if needed)</li>
        <li>The content meets platform guidelines</li>
        <li>Media files are under the size limit</li>
    </ul>

    <h3>Account disconnected</h3>

    <p>
        Social platforms occasionally require re-authorisation. If your account shows as disconnected,
        simply click <strong>Reconnect</strong> and authorise again.
    </p>

    <hr>

    <p>
        Need more help? Visit the <a href="/help">Help Centre</a> or
        <a href="/contact">contact support</a>.
    </p>

</x-layouts.sidebar-right>
