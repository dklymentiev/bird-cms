<?php

/**
 * Author Helper Functions
 */

/**
 * Get author configuration by slug
 *
 * @param string|null $authorSlug Author identifier (e.g., 'john-smith')
 * @return array Author configuration or default editorial team
 */
function get_author(?string $authorSlug): array
{
    $authors = \App\Support\Config::load('authors');

    // If no author specified, return editorial team
    if ($authorSlug === null) {
        return $authors['editorial-team'];
    }

    // Return author if exists, otherwise return editorial team
    return $authors[$authorSlug] ?? $authors['editorial-team'];
}

/**
 * Get all authors
 *
 * @return array All authors configuration
 */
function get_all_authors(): array
{
    $authors = \App\Support\Config::load('authors');

    return $authors;
}

/**
 * Get author name by slug
 *
 * @param string|null $authorSlug Author identifier
 * @return string Author full name
 */
function get_author_name(?string $authorSlug): string
{
    if (empty($authorSlug)) {
        return default_author();
    }

    $author = get_author($authorSlug);
    return $author['name'] ?? default_author();
}

/**
 * Get author avatar URL
 *
 * @param string|null $authorSlug Author identifier
 * @return string Avatar URL
 */
function get_author_avatar(?string $authorSlug): string
{
    if (empty($authorSlug)) {
        $author = get_author('editorial-team');
    } else {
        $author = get_author($authorSlug);
    }

    return $author['avatar'] ?? '/assets/avatars/default-avatar.jpg';
}

/**
 * Render author byline HTML
 *
 * @param string|null $authorSlug Author identifier
 * @param bool $includeAvatar Include avatar image
 * @return string HTML output
 */
function render_author_byline(?string $authorSlug, bool $includeAvatar = false): string
{
    $author = empty($authorSlug) ? get_author('editorial-team') : get_author($authorSlug);

    $html = '';

    if ($includeAvatar && isset($author['avatar'])) {
        $html .= sprintf(
            '<img src="%s" alt="%s" class="h-10 w-10 rounded-full" loading="lazy" /> ',
            htmlspecialchars($author['avatar']),
            htmlspecialchars($author['name'])
        );
    }

    $html .= sprintf(
        '<span class="font-medium">%s</span>',
        htmlspecialchars($author['name'])
    );

    if (isset($author['role'])) {
        $html .= sprintf(
            ' <span class="text-slate-500 dark:text-slate-400">— %s</span>',
            htmlspecialchars($author['role'])
        );
    }

    return $html;
}
