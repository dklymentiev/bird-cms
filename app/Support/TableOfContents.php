<?php

declare(strict_types=1);

namespace App\Support;

final class TableOfContents
{
    /**
     * Generate TOC and add IDs to headings
     *
     * @return array{html: string, toc: array<int, array{level: int, text: string, id: string}>}
     */
    public static function generate(string $html): array
    {
        $toc = [];
        $counter = 0;

        // Process headings and add IDs
        $html = preg_replace_callback(
            '/<h([2-3])>(.*?)<\/h\1>/i',
            static function ($matches) use (&$toc, &$counter) {
                $level = (int) $matches[1];
                $text = strip_tags($matches[2]);
                $id = self::slugify($text) . '-' . $counter++;

                $toc[] = [
                    'level' => $level,
                    'text' => $text,
                    'id' => $id,
                ];

                return sprintf('<h%d id="%s">%s</h%d>', $level, $id, $matches[2], $level);
            },
            $html
        );

        return [
            'html' => $html,
            'toc' => $toc,
        ];
    }

    /**
     * Check if content has enough headings for TOC
     */
    public static function shouldShow(array $toc, int $minHeadings = 3): bool
    {
        return count($toc) >= $minHeadings;
    }

    private static function slugify(string $text): string
    {
        $text = strtolower($text);
        $text = preg_replace('/[^a-z0-9\s-]/', '', $text);
        $text = preg_replace('/[\s-]+/', '-', $text);
        return trim($text, '-');
    }
}
