<?php

declare(strict_types=1);

namespace App\Support;

/**
 * YAML Helper
 *
 * Thin wrapper around {@see YamlMini} for backwards-compatible callers.
 * The actual parser lives in YamlMini so the MCP server (bootstrap-free)
 * and the engine share one implementation. Validation helpers
 * (validateRequired / isValidSlug / isValidCategory) stay here because
 * they're meta.yaml field-level checks, not YAML grammar.
 */
final class YamlHelper
{
    /**
     * Parse YAML string into array.
     */
    public static function parse(string $yaml): array
    {
        return YamlMini::parse($yaml);
    }

    /**
     * Dump array to YAML string. $fieldOrder lists keys to emit first
     * (in the order given); remaining keys follow in their natural
     * iteration order.
     *
     * @param array<string, mixed> $data
     * @param list<string>         $fieldOrder
     */
    public static function dump(array $data, array $fieldOrder = []): string
    {
        return YamlMini::dump($data, $fieldOrder);
    }

    /**
     * Validate required fields exist.
     *
     * @param array<string, mixed> $data
     * @param list<string>         $required
     * @return list<string> Missing field names (empty when valid)
     */
    public static function validateRequired(array $data, array $required): array
    {
        $missing = [];
        foreach ($required as $field) {
            if (!isset($data[$field]) || $data[$field] === '' || $data[$field] === null) {
                $missing[] = $field;
            }
        }
        return $missing;
    }

    /**
     * Validate slug format (safe for filesystem).
     */
    public static function isValidSlug(string $slug): bool
    {
        return (bool)preg_match('/^[a-z0-9][a-z0-9-]*[a-z0-9]$|^[a-z0-9]$/', $slug)
            && !str_contains($slug, '--')
            && strlen($slug) >= 3
            && strlen($slug) <= 100;
    }

    /**
     * Validate category (safe for filesystem).
     */
    public static function isValidCategory(string $category): bool
    {
        return (bool)preg_match('/^[a-z][a-z0-9-]*$/', $category)
            && strlen($category) <= 50;
    }
}
