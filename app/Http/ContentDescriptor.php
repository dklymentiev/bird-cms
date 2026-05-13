<?php

declare(strict_types=1);

namespace App\Http;

/**
 * ContentDescriptor — reverse-lookup result of {@see ContentRouter::resolve()}.
 *
 * Tells the URL Inventory editor *how* a given URL is rendered:
 *   - which content-type bucket it belongs to (`source`),
 *   - which slug + optional category lookup hits the underlying file,
 *   - which repository class is responsible for read/write,
 *   - whether the URL has an editable body / template at all.
 *
 * `source = 'static'` is reserved for URLs the engine renders programmatically
 * (homepage `/`, article category indexes `/<category>`). Those don't map to a
 * single markdown file today; the inventory editor offers a "create override"
 * affordance via the page-fall-through introduced in Step 5.
 */
final class ContentDescriptor
{
    public function __construct(
        public readonly string $source,
        public readonly string $slug,
        public readonly ?string $category,
        public readonly string $repositoryClass,
        public readonly bool $editableBody = true,
        public readonly bool $editableTemplate = true,
    ) {
    }

    /**
     * @return array{
     *   source: string,
     *   slug: string,
     *   category: ?string,
     *   repository_class: string,
     *   editable_body: bool,
     *   editable_template: bool
     * }
     */
    public function toArray(): array
    {
        return [
            'source'            => $this->source,
            'slug'              => $this->slug,
            'category'          => $this->category,
            'repository_class'  => $this->repositoryClass,
            'editable_body'     => $this->editableBody,
            'editable_template' => $this->editableTemplate,
        ];
    }
}
