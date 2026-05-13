<?php
declare(strict_types=1);

// Demo taxonomy installed by the wizard's "seed demo content" option.
// Replace these with categories that match your site, or delete the ones
// you don't need. Top-level keys are URL slugs: /<category>/<article-slug>.

return [
    'getting-started' => [
        'title'         => 'Getting Started',
        'description'   => 'Onboarding guides and first steps for new Bird CMS sites.',
        'icon'          => 'rocket',
        'subcategories' => [],
    ],
    'tutorials' => [
        'title'         => 'Tutorials',
        'description'   => 'Walkthroughs for customizing themes, writing posts, and shipping content.',
        'icon'          => 'book-open',
        'subcategories' => [],
    ],
    'news' => [
        'title'         => 'News',
        'description'   => 'Release notes, roadmap updates, and announcements.',
        'icon'          => 'megaphone',
        'subcategories' => [],
    ],
];
