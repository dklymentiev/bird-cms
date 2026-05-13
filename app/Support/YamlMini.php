<?php

declare(strict_types=1);

namespace App\Support;

/**
 * YamlMini -- the one YAML parser/serializer used everywhere in Bird CMS.
 *
 * The codebase historically had two near-identical "minimal subset" YAML
 * parsers: one in {@see YamlHelper} (used by the engine) and one inlined
 * in mcp/server.php (used by the bootstrap-free MCP server). They drifted.
 *
 * This class is the union of features both call sites actually exercised
 * against real meta.yaml files:
 *   - top-level scalar key: value
 *   - quoted strings ("..." with \n / \t / \" escapes, '...')
 *   - sequence values (- item, - "item")
 *   - inline arrays ([a, b, "c"])
 *   - booleans (true/false/yes/no/on/off), null (null/~), numbers, strings
 *   - block scalars (| and >)
 *
 * Deliberately NOT supported (nobody writes this in a meta.yaml):
 *   - anchors / aliases / merge keys
 *   - tags (!!str etc)
 *   - multi-document streams (---)
 *   - deeply nested mappings (FAQ-style nested arrays are special-cased
 *     by ArticleRepository::parseMetaYaml, not here)
 *
 * No autoload, no Composer dependency. The MCP server boots without
 * bootstrap.php, so YamlMini is included via require_once and used as
 * \App\Support\YamlMini::parse() / ::dump().
 */
final class YamlMini
{
    /**
     * Parse a YAML document into an associative array.
     *
     * Falls through to the ext-yaml C parser if the extension is
     * available -- it's faster and handles edge cases the hand-rolled
     * parser doesn't. The hand-rolled path is the fallback for stock
     * PHP installs (most shared hosts don't ship ext-yaml).
     */
    public static function parse(string $yaml): array
    {
        if (function_exists('yaml_parse')) {
            $parsed = @yaml_parse($yaml);
            if (is_array($parsed)) {
                return $parsed;
            }
        }

        $result = [];
        $lines = explode("\n", $yaml);
        $currentKey = null;
        $currentArray = [];
        $inArray = false;
        $multilineKey = null;
        $multilineValue = '';
        $multilineIndent = 0;

        foreach ($lines as $line) {
            // Block scalar continuation: every line indented past the
            // anchor's own indent belongs to the value (blank lines too).
            if ($multilineKey !== null) {
                $lineIndent = strlen($line) - strlen(ltrim($line));
                if ($lineIndent >= $multilineIndent || trim($line) === '') {
                    $multilineValue .= ($multilineValue ? "\n" : '') . substr($line, $multilineIndent);
                    continue;
                }
                $result[$multilineKey] = rtrim($multilineValue);
                $multilineKey = null;
                $multilineValue = '';
            }

            if (trim($line) === '' || str_starts_with(trim($line), '#')) {
                continue;
            }

            // Sequence entry "- value" only counts when we're already
            // collecting a list -- otherwise a stray dash at column 0 is
            // a parse error we silently ignore (same as the old behavior).
            if (preg_match('/^(\s*)-\s*(.*)$/', $line, $m)) {
                if ($inArray && $currentKey !== null) {
                    $currentArray[] = self::parseValue(trim($m[2]));
                }
                continue;
            }

            if (preg_match('/^([a-zA-Z_][a-zA-Z0-9_]*):\s*(.*)$/', $line, $m)) {
                // Flush any list we were accumulating before starting
                // the new key. Multi-key documents would otherwise lose
                // every list except the last.
                if ($inArray && $currentKey !== null) {
                    $result[$currentKey] = $currentArray;
                    $inArray = false;
                    $currentArray = [];
                }

                $key = $m[1];
                $value = trim($m[2]);

                if ($value === '|' || $value === '>') {
                    $multilineKey = $key;
                    // Block scalar children must be indented deeper than
                    // the key itself; +2 matches the conventional layout.
                    $multilineIndent = (strlen($line) - strlen(ltrim($line))) + 2;
                    continue;
                }

                if ($value === '') {
                    $currentKey = $key;
                    $currentArray = [];
                    $inArray = true;
                    continue;
                }

                $result[$key] = self::parseValue($value);
                $currentKey = null;
                $inArray = false;
            }
        }

        if ($inArray && $currentKey !== null) {
            $result[$currentKey] = $currentArray;
        }
        if ($multilineKey !== null) {
            $result[$multilineKey] = rtrim($multilineValue);
        }

        return $result;
    }

    /**
     * Serialize an associative array to YAML. The output round-trips
     * through self::parse() and is byte-stable for re-saves -- callers
     * use that to detect "did anything actually change".
     *
     * @param array<string, mixed> $data
     * @param list<string>         $fieldOrder Keys to emit first, in order
     */
    public static function dump(array $data, array $fieldOrder = []): string
    {
        $output = '';

        foreach ($fieldOrder as $key) {
            if (array_key_exists($key, $data)) {
                $output .= self::dumpField($key, $data[$key]);
                unset($data[$key]);
            }
        }

        foreach ($data as $key => $value) {
            $output .= self::dumpField((string) $key, $value);
        }

        return $output;
    }

    private static function parseValue(string $value): mixed
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        // Inline array first -- "[true, 1]" must stay an array, not be
        // mistaken for a quoted scalar by the unquote path below.
        if (str_starts_with($value, '[') && str_ends_with($value, ']')) {
            return self::parseInlineArray($value);
        }

        $lower = strtolower($value);
        if (in_array($lower, ['true', 'yes', 'on'], true)) {
            return true;
        }
        if (in_array($lower, ['false', 'no', 'off'], true)) {
            return false;
        }
        if (in_array($lower, ['null', '~'], true)) {
            return null;
        }

        if (is_numeric($value)) {
            return str_contains($value, '.') ? (float) $value : (int) $value;
        }

        return self::unquote($value);
    }

    /**
     * Parse "[a, b, \"c, d\"]" into a list, respecting quote-protected
     * commas. The naive explode(',', ...) version split "Smith, Jane"
     * into two entries, which broke author lists.
     *
     * @return array<int, mixed>
     */
    private static function parseInlineArray(string $value): array
    {
        $inner = trim(substr($value, 1, -1));
        if ($inner === '') {
            return [];
        }

        $result = [];
        $current = '';
        $inQuote = false;
        $quoteChar = '';
        $len = strlen($inner);

        for ($i = 0; $i < $len; $i++) {
            $char = $inner[$i];

            if (!$inQuote && ($char === '"' || $char === "'")) {
                $inQuote = true;
                $quoteChar = $char;
                $current .= $char;
            } elseif ($inQuote && $char === $quoteChar) {
                $inQuote = false;
                $quoteChar = '';
                $current .= $char;
            } elseif (!$inQuote && $char === ',') {
                $result[] = self::parseValue(trim($current));
                $current = '';
            } else {
                $current .= $char;
            }
        }

        if (trim($current) !== '') {
            $result[] = self::parseValue(trim($current));
        }

        return $result;
    }

    private static function unquote(string $value): string
    {
        $value = trim($value);

        if (strlen($value) >= 2 && str_starts_with($value, '"') && str_ends_with($value, '"')) {
            $value = substr($value, 1, -1);
            return str_replace(['\\"', '\\n', '\\t', '\\\\'], ['"', "\n", "\t", '\\'], $value);
        }

        if (strlen($value) >= 2 && str_starts_with($value, "'") && str_ends_with($value, "'")) {
            return substr($value, 1, -1);
        }

        return $value;
    }

    private static function dumpField(string $key, mixed $value): string
    {
        if (is_array($value)) {
            $output = "$key:\n";
            foreach ($value as $item) {
                $output .= '  - ' . self::escapeString(self::scalarToString($item)) . "\n";
            }
            return $output;
        }

        if (is_bool($value)) {
            return "$key: " . ($value ? 'true' : 'false') . "\n";
        }

        if ($value === null) {
            return "$key: null\n";
        }

        if (is_int($value) || is_float($value)) {
            return "$key: " . $value . "\n";
        }

        return "$key: " . self::escapeString((string) $value) . "\n";
    }

    private static function scalarToString(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if ($value === null) {
            return 'null';
        }
        return (string) $value;
    }

    /**
     * Decide whether $value needs quoting and escape it if so. The rule
     * is "quote whenever a bare emit could be re-parsed as something
     * other than a string": YAML indicators, leading-special chars,
     * embedded newlines, numeric-looking strings, boolean-looking
     * strings.
     */
    private static function escapeString(string $value): string
    {
        if ($value === '') {
            return '""';
        }

        $needsQuotes = false;

        if (preg_match('/[:#\[\]{}|>&*!?,]/', $value)) {
            $needsQuotes = true;
        }
        if (preg_match('/^[@`\'"\-\s]/', $value)) {
            $needsQuotes = true;
        }
        if (str_contains($value, "\n") || str_contains($value, "\t")) {
            $needsQuotes = true;
        }
        if (is_numeric($value)) {
            $needsQuotes = true;
        }
        if (in_array(strtolower($value), ['true', 'false', 'yes', 'no', 'null', '~', 'on', 'off'], true)) {
            $needsQuotes = true;
        }

        if ($needsQuotes) {
            $escaped = str_replace(['\\', '"', "\n", "\t"], ['\\\\', '\\"', '\\n', '\\t'], $value);
            return '"' . $escaped . '"';
        }

        return $value;
    }
}
