<?php

declare(strict_types=1);

namespace App\Support;

final class Markdown
{
    private static ?string $currentBundlePath = null;

    public static function toHtml(string $markdown, ?string $bundlePath = null): string
    {
        self::$currentBundlePath = $bundlePath;
        $markdown = str_replace(["
", ""], "\n", $markdown);
        $lines = explode("\n", $markdown);
        $html = [];
        $buffer = [];
        $inCodeBlock = false;
        $codeBuffer = [];
        $codeLang   = '';
        $inTable = false;
        $tableBuffer = [];
        $inList = false;
        $listBuffer = [];
        $listType = null;

        foreach ($lines as $line) {
            // Code blocks. Language hint after the opening fence
            // (``` bash, ```yaml, ```php, ...) becomes a "language-X"
            // class on <code> so client-side highlighters (highlight.js)
            // can color the block. No hint -> plain <pre><code>.
            if (preg_match('/^```([\w+-]*)\s*$/', $line, $fence)) {
                if ($inCodeBlock) {
                    $classAttr = $codeLang !== ''
                        ? ' class="language-' . htmlspecialchars($codeLang, ENT_QUOTES) . '"'
                        : '';
                    $html[] = '<pre><code' . $classAttr . '>'
                        . htmlspecialchars(implode("\n", $codeBuffer))
                        . '</code></pre>';
                    $codeBuffer  = [];
                    $codeLang    = '';
                    $inCodeBlock = false;
                } else {
                    if (!empty($buffer)) {
                        $html[] = '<p>' . self::inline(implode(' ', $buffer)) . '</p>';
                        $buffer = [];
                    }
                    $codeLang    = $fence[1] ?? '';
                    $inCodeBlock = true;
                }
                continue;
            }

            if ($inCodeBlock) {
                $codeBuffer[] = $line;
                continue;
            }

            // Tables
            if (preg_match('/^\|(.+)\|$/', $line)) {
                // Skip separator line (|---|---|) or (| --- | --- |)
                if (preg_match('/^\|\s*[\-:]+(\s*\|\s*[\-:]+)+\s*\|$/', $line)) {
                    continue;
                }

                if (!$inTable) {
                    if (!empty($buffer)) {
                        $html[] = '<p>' . self::inline(implode(' ', $buffer)) . '</p>';
                        $buffer = [];
                    }
                    $inTable = true;
                }

                $tableBuffer[] = $line;
                continue;
            } else {
                if ($inTable) {
                    $html[] = self::parseTable($tableBuffer);
                    $tableBuffer = [];
                    $inTable = false;
                }
            }

            // Lists (bullets and numbered)
            if (preg_match('/^(\*|\-|\d+\.)\s+(.*)$/', $line, $matches)) {
                $currentType = is_numeric($matches[1][0]) ? 'ol' : 'ul';

                if (!$inList) {
                    if (!empty($buffer)) {
                        $html[] = '<p>' . self::inline(implode(' ', $buffer)) . '</p>';
                        $buffer = [];
                    }
                    $inList = true;
                    $listType = $currentType;
                }

                // Handle task list items (checkboxes)
                $itemContent = $matches[2];
                if (preg_match('/^\[([ xX])\]\s*(.*)$/', $itemContent, $checkMatch)) {
                    $checked = strtolower($checkMatch[1]) === 'x' ? ' checked' : '';
                    $listBuffer[] = '<li class="task-item"><input type="checkbox" disabled' . $checked . '><span>' . self::inline($checkMatch[2]) . '</span></li>';
                } else {
                    $listBuffer[] = '<li>' . self::inline($itemContent) . '</li>';
                }
                continue;
            } else {
                if ($inList) {
                    // Add task-list class if contains task items
                    $hasTaskItems = str_contains(implode('', $listBuffer), 'task-item');
                    $listClass = $hasTaskItems ? ' class="task-list"' : '';
                    $html[] = "<$listType$listClass>" . implode("\n", $listBuffer) . "</$listType>";
                    $listBuffer = [];
                    $inList = false;
                    $listType = null;
                }
            }

            // Blockquotes
            if (preg_match('/^>\s+(.*)$/', $line, $matches)) {
                if (!empty($buffer)) {
                    $html[] = '<p>' . self::inline(implode(' ', $buffer)) . '</p>';
                    $buffer = [];
                }
                $html[] = '<blockquote>' . self::inline($matches[1]) . '</blockquote>';
                continue;
            }

            // Horizontal rules
            if (preg_match('/^(\-\-\-|___|\*\*\*)$/', $line)) {
                if (!empty($buffer)) {
                    $html[] = '<p>' . self::inline(implode(' ', $buffer)) . '</p>';
                    $buffer = [];
                }
                $html[] = '<hr>';
                continue;
            }

            // Empty lines
            if (trim($line) === '') {
                if (!empty($buffer)) {
                    $html[] = '<p>' . self::inline(implode(' ', $buffer)) . '</p>';
                    $buffer = [];
                }
                continue;
            }

            // Headers
            if (preg_match('/^(#{1,6})\s+(.*)$/', $line, $matches)) {
                if (!empty($buffer)) {
                    $html[] = '<p>' . self::inline(implode(' ', $buffer)) . '</p>';
                    $buffer = [];
                }
                $level = strlen($matches[1]);
                $html[] = sprintf('<h%d>%s</h%d>', $level, self::inline($matches[2]), $level);
                continue;
            }

            // Regular text
            $buffer[] = trim($line);
        }

        // Flush remaining buffers
        if ($inCodeBlock && !empty($codeBuffer)) {
            $classAttr = $codeLang !== ''
                ? ' class="language-' . htmlspecialchars($codeLang, ENT_QUOTES) . '"'
                : '';
            $html[] = '<pre><code' . $classAttr . '>'
                . htmlspecialchars(implode("\n", $codeBuffer))
                . '</code></pre>';
        }
        if ($inTable && !empty($tableBuffer)) {
            $html[] = self::parseTable($tableBuffer);
        }
        if ($inList && !empty($listBuffer)) {
            $hasTaskItems = str_contains(implode('', $listBuffer), 'task-item');
            $listClass = $hasTaskItems ? ' class="task-list"' : '';
            $html[] = "<$listType$listClass>" . implode("\n", $listBuffer) . "</$listType>";
        }
        if (!empty($buffer)) {
            $html[] = '<p>' . self::inline(implode(' ', $buffer)) . '</p>';
        }

        $output = implode("\n", $html);

        // Post-processing: Make Sources section collapsible
        $output = self::makeSourcesCollapsible($output);

        return $output;
    }

    /**
     * Make the Sources section collapsible (collapsed by default)
     */
    private static function makeSourcesCollapsible(string $html): string
    {
        // Find the Sources h2 heading and wrap everything after it until next h2 or end
        $pattern = '/(<h2>Sources<\/h2>)(.*?)(?=<h2>|$)/is';

        $replacement = '<details class="sources-section">
<summary class="sources-toggle">
<h2>Sources</h2>
<svg class="sources-icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
<path d="M6 9l6 6 6-6"/>
</svg>
</summary>
<div class="sources-content">$2</div>
</details>';

        return preg_replace($pattern, $replacement, $html);
    }

    private static function parseTable(array $lines): string
    {
        if (empty($lines)) {
            return '';
        }

        $rows = [];
        $isFirstRow = true;

        foreach ($lines as $line) {
            $cells = array_map('trim', explode('|', trim($line, '|')));
            $tag = $isFirstRow ? 'th' : 'td';
            $row = '<tr>';
            foreach ($cells as $cell) {
                $row .= "<$tag>" . self::inline($cell) . "</$tag>";
            }
            $row .= '</tr>';

            if ($isFirstRow) {
                $rows[] = '<thead>' . $row . '</thead><tbody>';
                $isFirstRow = false;
            } else {
                $rows[] = $row;
            }
        }

        $rows[] = '</tbody>';

        return '<table class="markdown-table">' . implode("\n", $rows) . '</table>';
    }

    private static function inline(string $text): string
    {
        // Process images first with path resolution
        $text = preg_replace_callback(
            '/!\[([^\]]*)\]\(([^\)]+)\)/',
            function ($matches) {
                $alt = htmlspecialchars($matches[1], ENT_QUOTES);
                $src = self::resolveImagePath($matches[2]);
                return '<img src="' . $src . '" alt="' . $alt . '" />';
            },
            $text
        );

        // Inline code MUST escape its contents -- otherwise `<title>`,
        // `<script>`, `<style>` etc. in docs end up as live HTML and break
        // the page (browser swallows everything until the matching close
        // tag). Bold/italic/link patterns intentionally come AFTER so they
        // don't run inside already-escaped code spans.
        $text = preg_replace_callback(
            '/`([^`]+)`/',
            static fn(array $m): string => '<code>' . htmlspecialchars($m[1], ENT_QUOTES) . '</code>',
            $text
        ) ?? $text;

        $patterns = [
            '/\*\*(.+?)\*\*/s' => '<strong>$1</strong>',
            '/\*(.+?)\*/s' => '<em>$1</em>',
            '/\[([^\]]+)\]\(([^\)]+)\)/' => '<a href="$2">$1</a>',
        ];

        foreach ($patterns as $pattern => $replacement) {
            $text = preg_replace($pattern, $replacement, $text);
        }

        return nl2br($text, false);
    }

    /**
     * Resolve image path using ImageResolver
     */
    private static function resolveImagePath(string $src): string
    {
        $src = trim($src);

        // Only process relative paths when we have bundle context
        if (self::$currentBundlePath !== null && str_starts_with($src, './')) {
            $resolver = new ImageResolver();
            $resolver->setBundlePath(self::$currentBundlePath);
            return $resolver->resolve($src);
        }

        return $src;
    }
}
