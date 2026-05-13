<?php

declare(strict_types=1);

/**
 * Content Types Configuration
 *
 * Defines content types, their sources, URL patterns, and sitemap settings.
 * This is the single source of truth for routing and sitemap generation.
 *
 * URL Pattern Variables:
 *   {slug}     - Content item slug
 *   {category} - Category/folder name (for articles)
 *   {type}     - Type subfolder (for services: residential/commercial)
 *   {parent}   - Parent slug (for sub-areas)
 */

return [
    'types' => [
        // Blog articles: /category/article-slug
        'articles' => [
            'source' => 'content/articles',
            'format' => 'markdown',
            'url' => '/{category}/{slug}',
            'repository' => \App\Content\ArticleRepository::class,
            'view' => 'article',
            'sitemap' => [
                'priority' => '0.7',
                'changefreq' => 'weekly',
            ],
        ],

        // Portfolio projects: /projects/project-slug
        'projects' => [
            'source' => 'content/projects',
            'format' => 'markdown',
            'url' => '/projects/{slug}',
            'index_url' => '/projects',
            'repository' => \App\Content\ProjectRepository::class,
            'view' => 'project',
            'index_view' => 'projects',
            'sitemap' => [
                'priority' => '0.8',
                'changefreq' => 'monthly',
            ],
        ],

        // Services (local business): /residential/service-slug, /commercial/service-slug
        'services' => [
            'source' => 'content/services',
            'format' => 'yaml',
            'url' => '/{type}/{slug}',
            'index_url' => '/{type}',
            'repository' => \App\Content\ServiceRepository::class,
            'view' => 'service',
            'sitemap' => [
                'priority' => '0.8',
                'changefreq' => 'weekly',
            ],
        ],

        // Service areas (local business): /areas/city-slug
        'areas' => [
            'source' => 'content/areas',
            'format' => 'yaml',
            'url' => '/areas/{slug}',
            'subarea_url' => '/areas/{parent}/{slug}',
            'index_url' => '/areas',
            'repository' => \App\Content\AreaRepository::class,
            'view' => 'area',
            'index_view' => 'areas',
            'sitemap' => [
                'priority' => '0.8',
                'changefreq' => 'monthly',
            ],
        ],

        // Static pages: /about, /contact, /privacy
        'pages' => [
            'source' => 'content/pages',
            'format' => 'markdown',
            'url' => '/{slug}',
            'repository' => \App\Content\PageRepository::class,
            'view' => 'page',
            'sitemap' => [
                'priority' => '0.6',
                'changefreq' => 'monthly',
            ],
        ],
    ],

    // Route matching priority (first match wins)
    'priority' => [
        'services',  // Check /residential/*, /commercial/* first
        'areas',     // Then /areas/*
        'projects',  // Then /projects/*
        'articles',  // Then /{category}/{slug}
        'pages',     // Finally /{slug} (catch-all for static pages)
    ],
];
