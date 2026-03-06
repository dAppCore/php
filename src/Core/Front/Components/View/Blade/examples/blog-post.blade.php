{{--
Example: Blog Post using Content Layout
Route: /examples/blog-post
--}}

<x-layouts.content
    title="How to grow your audience with Host Social"
    description="Learn the strategies top creators use to build engaged communities and grow their following across platforms."
    author="Sarah Chen"
    date="2024-12-15"
    category="Marketing"
    backLink="/blog"
    backLabel="Back to Blog">

    <p class="lead">
        Building an audience takes time, but with the right tools and strategies, you can accelerate your growth
        and create a community that genuinely engages with your content.
    </p>

    <h2>Start with your niche</h2>

    <p>
        The most successful creators focus on a specific niche before expanding. This doesn't mean you're limited
        forever, but starting focused helps you build authority and attract a dedicated audience who knows exactly
        what to expect from you.
    </p>

    <p>
        Consider what makes you unique. Your perspective, experience, or approach to topics can differentiate you
        from others in your space.
    </p>

    <blockquote>
        <p>"The riches are in the niches. Find your specific audience first, then expand from a position of strength."</p>
    </blockquote>

    <h2>Consistency beats perfection</h2>

    <p>
        One of the biggest mistakes new creators make is waiting for perfect conditions. The algorithm rewards
        consistency, and your audience builds habits around your posting schedule.
    </p>

    <ul>
        <li>Post at regular intervals your audience can rely on</li>
        <li>Batch create content to maintain consistency during busy periods</li>
        <li>Use scheduling tools like Host Social to automate posting</li>
        <li>Track what works and iterate on successful formats</li>
    </ul>

    <h2>Engage authentically</h2>

    <p>
        Social media is a two-way conversation. The creators who build the strongest communities are those who
        genuinely engage with their audience, respond to comments, and create content based on feedback.
    </p>

    <h3>Respond to comments quickly</h3>

    <p>
        The first hour after posting is crucial. Being present to respond to early comments signals to the algorithm
        that your content is generating engagement, which can boost its reach.
    </p>

    <h3>Ask questions</h3>

    <p>
        End your posts with questions that invite discussion. This transforms passive viewers into active participants
        and helps you understand what your audience wants.
    </p>

    <hr>

    <h2>Tools that help</h2>

    <p>
        Host Social makes managing multiple platforms simple. Schedule your content once and let it publish across
        all your channels automatically. This frees up time for what matters most: creating great content and
        engaging with your community.
    </p>

    <x-slot:after>
        <div class="text-center">
            <h3 class="text-xl font-bold text-white mb-4">Ready to grow your audience?</h3>
            <p class="text-slate-400 mb-6">Start scheduling your content with Host Social today.</p>
            <a href="/hub" class="btn text-slate-900 bg-gradient-to-r from-white/80 via-white to-white/80 hover:bg-white">
                Get Started Free
            </a>
        </div>
    </x-slot:after>

</x-layouts.content>
