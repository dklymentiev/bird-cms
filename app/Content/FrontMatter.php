<?php

declare(strict_types=1);

namespace App\Content;

final class FrontMatter
{
    /**
     * Parse markdown file with YAML frontmatter
     *
     * @param string $content Full file content with --- delimited frontmatter
     * @return array ['meta' => array, 'body' => string]
     */
    public static function parseWithBody(string $content): array
    {
        $content = ltrim($content);

        // Check for frontmatter delimiter
        if (!str_starts_with($content, '---')) {
            return ['meta' => [], 'body' => $content];
        }

        // Find closing delimiter
        $endPos = strpos($content, "\n---", 3);
        if ($endPos === false) {
            return ['meta' => [], 'body' => $content];
        }

        $yaml = substr($content, 4, $endPos - 4);
        $body = ltrim(substr($content, $endPos + 4));

        return [
            'meta' => self::parse($yaml),
            'body' => $body,
        ];
    }

    public static function parse(string $yaml): array
    {
        if (function_exists('yaml_parse')) {
            $parsed = @yaml_parse($yaml);
            if (is_array($parsed)) {
                return $parsed;
            }
        }

        $lines = preg_split('/\r?\n/', $yaml) ?: [];
        $result = [];

        self::parseLines($lines, 0, count($lines), -1, $result);

        return $result;
    }

    /**
     * Recursively parse YAML lines into an array
     */
    private static function parseLines(array $lines, int $start, int $end, int $baseIndent, array &$result): void
    {
        $i = $start;

        while ($i < $end) {
            $line = $lines[$i];
            $trimmed = trim($line);

            // Skip empty lines and comments
            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                $i++;
                continue;
            }

            $indent = strspn($line, ' ');

            // If indent is less than or equal to base, we're done with this level
            if ($baseIndent >= 0 && $indent <= $baseIndent) {
                break;
            }

            // List item
            if (str_starts_with($trimmed, '- ')) {
                $value = trim(substr($trimmed, 2));

                // Check if this list item has nested content
                $childStart = $i + 1;
                $childEnd = self::findBlockEnd($lines, $childStart, $end, $indent);

                if ($value === '' && $childStart < $childEnd) {
                    // List item with nested object on next lines
                    $childResult = [];
                    self::parseLines($lines, $childStart, $childEnd, $indent, $childResult);
                    $result[] = $childResult;
                    $i = $childEnd;
                } elseif (str_contains($value, ':') && !str_contains($value, 'http')) {
                    // Check if there's more content below at higher indent
                    if ($childStart < $childEnd && isset($lines[$childStart])) {
                        $nextIndent = strspn($lines[$childStart], ' ');
                        $nextTrimmed = trim($lines[$childStart]);
                        if ($nextIndent > $indent && $nextTrimmed !== '' && str_contains($nextTrimmed, ':')) {
                            // Nested object starting with inline key-value
                            $childResult = [];
                            // Parse the inline key:value from current line
                            [$key, $val] = array_map('trim', explode(':', $value, 2));
                            $childResult[$key] = self::castValue($val);
                            // Parse nested content
                            self::parseLines($lines, $childStart, $childEnd, $indent, $childResult);
                            $result[] = $childResult;
                            $i = $childEnd;
                            continue;
                        }
                    }

                    // Inline key:value only (e.g., "- title: Something")
                    [$key, $val] = array_map('trim', explode(':', $value, 2));

                    // Check for multi-line object
                    if ($childStart < $childEnd && isset($lines[$childStart])) {
                        $nextIndent = strspn($lines[$childStart], ' ');
                        $nextTrimmed = trim($lines[$childStart]);
                        if ($nextIndent > $indent && $nextTrimmed !== '' && str_contains($nextTrimmed, ':')) {
                            // This is an object with more keys
                            $childResult = [$key => self::castValue($val)];
                            self::parseLines($lines, $childStart, $childEnd, $indent, $childResult);
                            $result[] = $childResult;
                            $i = $childEnd;
                            continue;
                        }
                    }

                    // Simple inline map
                    if (str_starts_with($val, '{') && str_ends_with($val, '}')) {
                        $result[] = self::parseInlineMap($val);
                    } else {
                        $result[] = [$key => self::castValue($val)];
                    }
                    $i++;
                } else {
                    // Simple scalar list item
                    $result[] = self::castValue($value);
                    $i++;
                }
                continue;
            }

            // Key: value pair
            if (str_contains($trimmed, ':')) {
                [$key, $value] = array_map('trim', explode(':', $trimmed, 2));

                // Find the block of content belonging to this key
                $childStart = $i + 1;
                $childEnd = self::findBlockEnd($lines, $childStart, $end, $indent);

                if ($value === '') {
                    // Nested structure (array or object)
                    $childResult = [];
                    self::parseLines($lines, $childStart, $childEnd, $indent, $childResult);
                    $result[$key] = $childResult;
                    $i = $childEnd;
                } else {
                    // Inline value
                    if (str_starts_with($value, '{') && str_ends_with($value, '}')) {
                        $result[$key] = self::parseInlineMap($value);
                    } else {
                        $result[$key] = self::castValue($value);
                    }
                    $i++;
                }
                continue;
            }

            $i++;
        }
    }

    /**
     * Find where a block ends (next line with same or less indentation)
     */
    private static function findBlockEnd(array $lines, int $start, int $maxEnd, int $baseIndent): int
    {
        for ($i = $start; $i < $maxEnd; $i++) {
            $line = $lines[$i];
            $trimmed = trim($line);

            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                continue;
            }

            $indent = strspn($line, ' ');
            if ($indent <= $baseIndent) {
                return $i;
            }
        }

        return $maxEnd;
    }

    public static function encode(array $data): string
    {
        return rtrim(self::encodeLevel($data), "\n");
    }

    private static function encodeLevel(array $data, int $depth = 0): string
    {
        $indent = str_repeat('  ', $depth);
        $yaml = '';

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                if (self::isSequential($value)) {
                    $yaml .= sprintf("%s%s:\n", $indent, $key);
                    foreach ($value as $item) {
                        if (is_array($item)) {
                            // Multi-line object in list
                            $yaml .= sprintf("%s  -", $indent);
                            $first = true;
                            foreach ($item as $itemKey => $itemVal) {
                                if (is_array($itemVal)) {
                                    // Nested array/map inside a list-of-objects entry.
                                    if ($first) {
                                        $yaml .= sprintf(" %s:\n", $itemKey);
                                        $first = false;
                                    } else {
                                        $yaml .= sprintf("%s    %s:\n", $indent, $itemKey);
                                    }
                                    if (self::isSequential($itemVal)) {
                                        foreach ($itemVal as $sub) {
                                            if (is_array($sub)) {
                                                $yaml .= sprintf("%s      -", $indent);
                                                $subFirst = true;
                                                foreach ($sub as $sk => $sv) {
                                                    if ($subFirst) {
                                                        $yaml .= sprintf(" %s: %s\n", $sk, self::escapeScalar($sv));
                                                        $subFirst = false;
                                                    } else {
                                                        $yaml .= sprintf("%s          %s: %s\n", $indent, $sk, self::escapeScalar($sv));
                                                    }
                                                }
                                            } else {
                                                $yaml .= sprintf("%s      - %s\n", $indent, self::escapeScalar($sub));
                                            }
                                        }
                                    } else {
                                        foreach ($itemVal as $sk => $sv) {
                                            $yaml .= sprintf("%s      %s: %s\n", $indent, $sk, self::escapeScalar($sv));
                                        }
                                    }
                                } else if ($first) {
                                    $yaml .= sprintf(" %s: %s\n", $itemKey, self::escapeScalar($itemVal));
                                    $first = false;
                                } else {
                                    $yaml .= sprintf("%s    %s: %s\n", $indent, $itemKey, self::escapeScalar($itemVal));
                                }
                            }
                        } else {
                            $yaml .= sprintf("%s  - %s\n", $indent, self::escapeScalar($item));
                        }
                    }
                } else {
                    $yaml .= sprintf("%s%s:\n", $indent, $key);
                    $yaml .= self::encodeLevel($value, $depth + 1);
                }
            } else {
                $yaml .= sprintf("%s%s: %s\n", $indent, $key, self::escapeScalar($value));
            }
        }

        return $yaml;
    }

    private static function escapeScalar(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if ($value === null) {
            return 'null';
        }
        // Cast only genuine int/float PHP scalars. String values that *look*
        // numeric (e.g. "01", "007", "1e10") must stay strings; emitting them
        // unquoted lets parse() coerce them on next read.
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }
        $string = (string) $value;
        if ($string === '') {
            return '""';
        }
        $needsQuote = preg_match('/[:#\n\t]/', $string)
            || preg_match('/^[\s\-\'\"!&*?{}\[\],%@`>|]/', $string)
            || preg_match('/\s$/', $string)
            || is_numeric($string)
            || preg_match('/^(?:true|false|null|yes|no|on|off|~)$/i', $string);
        if ($needsQuote) {
            // YAML double-quoted form: only \ and " need escaping. Single
            // quotes pass through unchanged — PHP addslashes was wrong and
            // accumulated backslashes on every save.
            $escaped = strtr($string, [
                '\\' => '\\\\',
                '"'  => '\\"',
                "\n" => '\\n',
                "\t" => '\\t',
                "\r" => '\\r',
            ]);
            return '"' . $escaped . '"';
        }
        return $string;
    }

    private static function parseInlineMap(string $value): array
    {
        $value = trim($value, '{} ');
        $pairs = array_map('trim', explode(',', $value));
        $result = [];

        foreach ($pairs as $pair) {
            if (!str_contains($pair, ':')) {
                continue;
            }
            [$key, $scalar] = array_map('trim', explode(':', $pair, 2));
            $result[$key] = self::castValue($scalar);
        }

        return $result;
    }

    private static function castValue(string $value): mixed
    {
        $value = trim($value);
        // YAML-quoted scalars: keep as string, never coerce. Strip the quote
        // pair, unescape; this preserves "01", "007", "true" (the string), etc.
        if (strlen($value) >= 2) {
            $first = $value[0];
            $last  = $value[strlen($value) - 1];
            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                $inner = substr($value, 1, -1);
                if ($first === '"') {
                    $inner = strtr($inner, [
                        '\\\\' => '\\',
                        '\\"'  => '"',
                        '\\n'  => "\n",
                        '\\t'  => "\t",
                        '\\r'  => "\r",
                    ]);
                }
                return $inner;
            }
        }

        if (strtolower($value) === 'true') {
            return true;
        }
        if (strtolower($value) === 'false') {
            return false;
        }
        // YAML null forms: null, Null, NULL, ~, or empty string. Without
        // this, `url: null` in front-matter parsed as the literal STRING
        // "null", which is truthy -- views guarding `if (!empty($x))`
        // would render an `href="null"` link instead of skipping.
        if (in_array(strtolower($value), ['null', '~', ''], true)) {
            return null;
        }
        if (is_numeric($value)) {
            return str_contains($value, '.') ? (float) $value : (int) $value;
        }

        return $value;
    }

    private static function isSequential(array $value): bool
    {
        if (function_exists('array_is_list')) {
            return array_is_list($value);
        }

        $i = 0;
        foreach ($value as $key => $_) {
            if ($key !== $i++) {
                return false;
            }
        }
        return true;
    }
}
