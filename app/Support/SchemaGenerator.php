<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Generates Schema.org structured data based on article type and metadata
 */
final class SchemaGenerator
{
    /**
     * Generate all applicable schemas for an article
     *
     * @param array $article Article data
     * @param array $meta Meta.yaml data (includes schema config)
     * @return array Array of schema objects
     */
    public static function generate(array $article, array $meta = []): array
    {
        $schemas = [];
        $schemaType = $meta['schema'] ?? self::inferSchemaType($article['type'] ?? 'insight');

        // Add type-specific schema
        switch ($schemaType) {
            case 'review':
                $reviewSchema = self::buildReviewSchema($article, $meta);
                if ($reviewSchema) {
                    $schemas[] = $reviewSchema;
                }
                break;

            case 'comparison':
                $comparisonSchema = self::buildComparisonSchema($article, $meta);
                if ($comparisonSchema) {
                    $schemas[] = $comparisonSchema;
                }
                break;

            case 'howto':
                $howtoSchema = self::buildHowToSchema($article, $meta);
                if ($howtoSchema) {
                    $schemas[] = $howtoSchema;
                }
                break;

            case 'article':
            case 'blog':
                $articleSchema = self::buildArticleSchema($article, $meta);
                if ($articleSchema) {
                    $schemas[] = $articleSchema;
                }
                break;
        }

        // Add FAQ schema if present
        if (!empty($meta['faq'])) {
            $faqSchema = self::buildFAQSchema($meta['faq']);
            if ($faqSchema) {
                $schemas[] = $faqSchema;
            }
        }

        // Add Breadcrumb schema if breadcrumbs were attached to the meta.
        // Themes pass an array of {label|name|title, url} items.
        if (!empty($meta['breadcrumb'])) {
            $breadcrumbSchema = self::buildBreadcrumbSchema($meta['breadcrumb']);
            if ($breadcrumbSchema) {
                array_unshift($schemas, $breadcrumbSchema);
            }
        }

        return $schemas;
    }

    /**
     * Generate schemas for a page (non-article content like services, areas)
     *
     * @param array $pageData Page-specific data
     * @param string $pageType Type of page (service, area, contact, etc.)
     * @return array Array of schema objects
     */
    public static function generateForPage(array $pageData, string $pageType = 'page'): array
    {
        $schemas = [];

        switch ($pageType) {
            case 'service':
                $serviceSchema = self::buildServiceSchema($pageData);
                if ($serviceSchema) {
                    $schemas[] = $serviceSchema;
                }
                break;

            case 'area':
            case 'location':
                // LocalBusiness with areaServed
                $localSchema = self::buildLocalBusinessSchema($pageData);
                if ($localSchema) {
                    $schemas[] = $localSchema;
                }
                break;
        }

        // Add FAQ schema if present in page data
        if (!empty($pageData['faqs'])) {
            $faqSchema = self::buildFAQSchemaFromArray($pageData['faqs']);
            if ($faqSchema) {
                $schemas[] = $faqSchema;
            }
        }

        // Add breadcrumbs if present
        if (!empty($pageData['breadcrumbs'])) {
            $breadcrumbSchema = self::buildBreadcrumbSchema($pageData['breadcrumbs']);
            if ($breadcrumbSchema) {
                $schemas[] = $breadcrumbSchema;
            }
        }

        return $schemas;
    }

    /**
     * Generate LocalBusiness schema from site config
     *
     * @param array|null $override Override default business data
     * @return array|null
     */
    public static function generateLocalBusiness(?array $override = null): ?array
    {
        $business = $override ?? config('business', []);

        if (empty($business['name'])) {
            return null;
        }

        $schema = [
            '@context' => 'https://schema.org',
            '@type' => $business['type'] ?? 'LocalBusiness',
            'name' => $business['name'],
            'url' => site_url('/'),
        ];

        // Add description
        if (!empty($business['description'])) {
            $schema['description'] = $business['description'];
        }

        // Add telephone
        if (!empty($business['phone'])) {
            $schema['telephone'] = $business['phone'];
        }

        // Add email
        if (!empty($business['email'])) {
            $schema['email'] = $business['email'];
        }

        // Add address
        if (!empty($business['address'])) {
            $addr = $business['address'];
            $schema['address'] = [
                '@type' => 'PostalAddress',
                'addressLocality' => $addr['city'] ?? '',
                'addressRegion' => $addr['region'] ?? $addr['state'] ?? '',
                'addressCountry' => $addr['country'] ?? 'CA',
            ];
            if (!empty($addr['street'])) {
                $schema['address']['streetAddress'] = $addr['street'];
            }
            if (!empty($addr['postal_code'])) {
                $schema['address']['postalCode'] = $addr['postal_code'];
            }
        }

        // Add price range
        if (!empty($business['price_range'])) {
            $schema['priceRange'] = $business['price_range'];
        }

        // Add areas served
        if (!empty($business['areas_served'])) {
            $schema['areaServed'] = array_map(fn($area) => [
                '@type' => 'City',
                'name' => $area,
            ], $business['areas_served']);
        }

        // Add opening hours
        if (!empty($business['hours'])) {
            $schema['openingHoursSpecification'] = [];
            foreach ($business['hours'] as $spec) {
                $hoursSpec = [
                    '@type' => 'OpeningHoursSpecification',
                    'dayOfWeek' => $spec['days'] ?? [],
                ];
                if (!empty($spec['opens'])) {
                    $hoursSpec['opens'] = $spec['opens'];
                }
                if (!empty($spec['closes'])) {
                    $hoursSpec['closes'] = $spec['closes'];
                }
                $schema['openingHoursSpecification'][] = $hoursSpec;
            }
        }

        // Add aggregate rating
        if (!empty($business['rating'])) {
            $schema['aggregateRating'] = [
                '@type' => 'AggregateRating',
                'ratingValue' => $business['rating']['value'] ?? $business['rating'],
                'bestRating' => $business['rating']['best'] ?? 5,
                'ratingCount' => $business['rating']['count'] ?? 100,
            ];
        }

        // Add logo
        if (!empty($business['logo'])) {
            $schema['logo'] = $business['logo'];
        }

        // Add image
        if (!empty($business['image'])) {
            $schema['image'] = $business['image'];
        }

        return $schema;
    }

    /**
     * Infer schema type from article type
     */
    private static function inferSchemaType(string $articleType): string
    {
        return match (strtolower($articleType)) {
            'review' => 'review',
            'comparison', 'face-off' => 'comparison',
            'guide', 'playbook', 'how-to', 'howto' => 'howto',
            default => 'article',
        };
    }

    /**
     * Build Review schema with Product
     */
    private static function buildReviewSchema(array $article, array $meta): ?array
    {
        $review = $meta['review'] ?? [];

        // Need at least product name (skip if empty or TODO placeholder)
        $productName = $review['product'] ?? '';
        if (empty($productName) || str_starts_with(trim($productName), '#') || str_contains($productName, 'TODO')) {
            return null;
        }

        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Review',
            'name' => $article['title'] ?? '',
            'description' => $article['description'] ?? '',
            'datePublished' => self::formatDate($article['date'] ?? null),
            'author' => [
                '@type' => 'Organization',
                'name' => publisher_name(),
            ],
            'publisher' => [
                '@type' => 'Organization',
                'name' => publisher_name(),
                'url' => site_url('/'),
            ],
            'itemReviewed' => [
                '@type' => 'SoftwareApplication',
                'name' => $productName,
                'applicationCategory' => 'BusinessApplication',
            ],
        ];

        // Add product URL
        if (!empty($review['product_url'])) {
            $schema['itemReviewed']['url'] = $review['product_url'];
        }

        // Add price if available
        if (!empty($review['price'])) {
            $schema['itemReviewed']['offers'] = [
                '@type' => 'Offer',
                'price' => self::extractPrice($review['price']),
                'priceCurrency' => 'USD',
                'description' => $review['price'],
            ];
        }

        // Add rating
        if (!empty($review['rating'])) {
            $schema['reviewRating'] = [
                '@type' => 'Rating',
                'ratingValue' => (float) $review['rating'],
                'bestRating' => 10,
                'worstRating' => 1,
            ];
        }

        // Add pros/cons as review body
        $reviewBody = [];
        if (!empty($review['pros'])) {
            $reviewBody[] = 'Pros: ' . implode(', ', $review['pros']);
        }
        if (!empty($review['cons'])) {
            $reviewBody[] = 'Cons: ' . implode(', ', $review['cons']);
        }
        if (!empty($reviewBody)) {
            $schema['reviewBody'] = implode('. ', $reviewBody);
        }

        // Add positive/negative notes (new schema format)
        if (!empty($review['pros'])) {
            $schema['positiveNotes'] = [
                '@type' => 'ItemList',
                'itemListElement' => array_map(fn($pro, $i) => [
                    '@type' => 'ListItem',
                    'position' => $i + 1,
                    'name' => $pro,
                ], $review['pros'], array_keys($review['pros'])),
            ];
        }

        if (!empty($review['cons'])) {
            $schema['negativeNotes'] = [
                '@type' => 'ItemList',
                'itemListElement' => array_map(fn($con, $i) => [
                    '@type' => 'ListItem',
                    'position' => $i + 1,
                    'name' => $con,
                ], $review['cons'], array_keys($review['cons'])),
            ];
        }

        return $schema;
    }

    /**
     * Build Comparison schema (ItemList with multiple products)
     */
    private static function buildComparisonSchema(array $article, array $meta): ?array
    {
        $comparison = $meta['comparison'] ?? [];
        $products = $comparison['products'] ?? [];

        if (empty($products)) {
            return null;
        }

        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'ItemList',
            'name' => $article['title'] ?? 'Product Comparison',
            'description' => $article['description'] ?? '',
            'numberOfItems' => count($products),
            'itemListElement' => [],
        ];

        foreach ($products as $i => $product) {
            $item = [
                '@type' => 'ListItem',
                'position' => $i + 1,
                'item' => [
                    '@type' => 'SoftwareApplication',
                    'name' => $product['name'] ?? '',
                    'applicationCategory' => 'BusinessApplication',
                ],
            ];

            if (!empty($product['url'])) {
                $item['item']['url'] = $product['url'];
            }

            if (!empty($product['rating'])) {
                $item['item']['aggregateRating'] = [
                    '@type' => 'AggregateRating',
                    'ratingValue' => (float) $product['rating'],
                    'bestRating' => 10,
                    'worstRating' => 1,
                    'ratingCount' => 1,
                ];
            }

            $schema['itemListElement'][] = $item;
        }

        return $schema;
    }

    /**
     * Build HowTo schema
     */
    private static function buildHowToSchema(array $article, array $meta): ?array
    {
        $howto = $meta['howto'] ?? [];
        $steps = $howto['steps'] ?? [];

        if (empty($steps)) {
            return null;
        }

        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'HowTo',
            'name' => $article['title'] ?? '',
            'description' => $article['description'] ?? '',
            'step' => [],
        ];

        foreach ($steps as $i => $step) {
            $schema['step'][] = [
                '@type' => 'HowToStep',
                'position' => $i + 1,
                'name' => $step['title'] ?? "Step " . ($i + 1),
                'text' => $step['text'] ?? '',
            ];
        }

        // Add total time if specified
        if (!empty($howto['total_time'])) {
            $schema['totalTime'] = $howto['total_time'];
        }

        return $schema;
    }

    /**
     * Build FAQ schema
     */
    public static function buildFAQSchema(array $faq): ?array
    {
        if (empty($faq)) {
            return null;
        }

        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => [],
        ];

        foreach ($faq as $item) {
            if (empty($item['q']) || empty($item['a'])) {
                continue;
            }

            $schema['mainEntity'][] = [
                '@type' => 'Question',
                'name' => $item['q'],
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => $item['a'],
                ],
            ];
        }

        return empty($schema['mainEntity']) ? null : $schema;
    }

    /**
     * Format date for schema
     */
    private static function formatDate(?string $date): string
    {
        if (empty($date)) {
            return date('c');
        }

        try {
            return (new \DateTimeImmutable($date))->format('c');
        } catch (\Exception $e) {
            return date('c');
        }
    }

    /**
     * Extract numeric price from string like "$14/month"
     */
    private static function extractPrice(string $priceStr): string
    {
        if (preg_match('/[\d.]+/', $priceStr, $m)) {
            return $m[0];
        }
        return '0';
    }

    /**
     * Build Article/BlogPosting schema
     */
    private static function buildArticleSchema(array $article, array $meta): ?array
    {
        if (empty($article['title'])) {
            return null;
        }

        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Article',
            'headline' => $article['title'],
            'datePublished' => self::formatDate($article['date'] ?? null),
            'author' => [
                '@type' => 'Organization',
                'name' => $article['author'] ?? publisher_name(),
            ],
            'publisher' => [
                '@type' => 'Organization',
                'name' => publisher_name(),
                'url' => site_url('/'),
            ],
        ];

        if (!empty($article['description'])) {
            $schema['description'] = $article['description'];
        }

        if (!empty($article['url'])) {
            $schema['mainEntityOfPage'] = $article['url'];
        }

        if (!empty($article['image']) || !empty($article['hero_image'])) {
            $schema['image'] = $article['image'] ?? $article['hero_image'];
        }

        if (!empty($article['modified'])) {
            $schema['dateModified'] = self::formatDate($article['modified']);
        }

        return $schema;
    }

    /**
     * Build Service schema for service pages
     */
    private static function buildServiceSchema(array $data): ?array
    {
        if (empty($data['title'])) {
            return null;
        }

        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Service',
            'name' => $data['title'],
            'provider' => [
                '@type' => config('business.type', 'LocalBusiness'),
                'name' => config('business.name', site_name()),
            ],
        ];

        if (!empty($data['description'])) {
            $schema['description'] = $data['description'];
        }

        // Add service area
        if (!empty($data['area'])) {
            $schema['areaServed'] = [
                '@type' => 'City',
                'name' => $data['area'],
            ];
        } elseif ($areasServed = config('business.areas_served')) {
            $schema['areaServed'] = array_map(fn($area) => [
                '@type' => 'City',
                'name' => $area,
            ], $areasServed);
        }

        // Add pricing if available
        if (!empty($data['pricing'])) {
            // Get first price as starting price
            $firstPrice = $data['pricing'][0] ?? null;
            if ($firstPrice && !empty($firstPrice['price'])) {
                $priceValue = self::extractPrice($firstPrice['price']);
                if ($priceValue !== '0') {
                    $schema['offers'] = [
                        '@type' => 'Offer',
                        'price' => $priceValue,
                        'priceCurrency' => config('business.currency', 'CAD'),
                        'availability' => 'https://schema.org/InStock',
                    ];
                }
            }
        }

        // Add service URL
        if (!empty($data['url'])) {
            $schema['url'] = $data['url'];
        }

        return $schema;
    }

    /**
     * Build LocalBusiness schema for area/location pages
     */
    private static function buildLocalBusinessSchema(array $data): ?array
    {
        $business = config('business', []);

        if (empty($business['name'])) {
            return null;
        }

        $schema = self::generateLocalBusiness($business);

        // Override/add area-specific data
        if (!empty($data['name'])) {
            $schema['areaServed'] = [
                '@type' => 'City',
                'name' => $data['name'],
            ];
        }

        return $schema;
    }

    /**
     * Build FAQ schema from array format [['question' => ..., 'answer' => ...], ...]
     */
    private static function buildFAQSchemaFromArray(array $faqs): ?array
    {
        if (empty($faqs)) {
            return null;
        }

        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => [],
        ];

        foreach ($faqs as $item) {
            $question = $item['question'] ?? $item['q'] ?? null;
            $answer = $item['answer'] ?? $item['a'] ?? null;

            if (empty($question) || empty($answer)) {
                continue;
            }

            $schema['mainEntity'][] = [
                '@type' => 'Question',
                'name' => $question,
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => $answer,
                ],
            ];
        }

        return empty($schema['mainEntity']) ? null : $schema;
    }

    /**
     * Build Breadcrumb schema (BreadcrumbList).
     *
     * Accepts crumbs with `name` / `title` / `label` (aliases) and `url`.
     * Relative URLs are resolved against `config('site_url')`. Empty `url`
     * means "current page" — resolved to `$_SERVER['REQUEST_URI']`.
     */
    public static function buildBreadcrumbSchema(array $breadcrumbs): ?array
    {
        if (empty($breadcrumbs)) {
            return null;
        }

        $siteUrl = function_exists('config') ? rtrim((string) config('site_url'), '/') : '';
        $requestUri = $_SERVER['REQUEST_URI'] ?? '/';

        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => [],
        ];

        foreach ($breadcrumbs as $i => $crumb) {
            $item = [
                '@type' => 'ListItem',
                'position' => $i + 1,
                'name' => $crumb['name'] ?? $crumb['title'] ?? $crumb['label'] ?? '',
            ];

            $url = $crumb['url'] ?? null;
            if ($url === null || $url === '') {
                // Current page
                $url = $siteUrl . (str_starts_with($requestUri, '/') ? $requestUri : '/' . $requestUri);
            } elseif (!preg_match('#^https?://#i', $url)) {
                $url = $siteUrl . '/' . ltrim($url, '/');
            }
            $item['item'] = $url;

            $schema['itemListElement'][] = $item;
        }

        return $schema;
    }

    /**
     * Render schemas as JSON-LD script tags
     */
    public static function render(array $schemas): string
    {
        if (empty($schemas)) {
            return '';
        }

        $output = '';
        foreach ($schemas as $schema) {
            if ($schema) {
                $json = json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                $output .= '<script type="application/ld+json">' . $json . '</script>' . "\n";
            }
        }

        return $output;
    }

    /**
     * Convenience method: generate and render in one call
     */
    public static function generateAndRender(array $article, array $meta = []): string
    {
        return self::render(self::generate($article, $meta));
    }

    /**
     * Convenience method: generate for page and render
     */
    public static function generateForPageAndRender(array $pageData, string $pageType = 'page'): string
    {
        return self::render(self::generateForPage($pageData, $pageType));
    }
}
