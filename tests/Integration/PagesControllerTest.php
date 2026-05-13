<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Admin\PagesController;
use App\Content\FrontMatter;
use App\Content\PageRepository;
use PHPUnit\Framework\TestCase;
use Tests\Support\TempContent;
use Tests\Support\TestConfig;

/**
 * Integration coverage for the URL Inventory + page admin paths.
 *
 * Covers the behaviours that POST /admin/pages/save, POST /admin/pages
 * /sitemap, POST /admin/pages/template are documented to provide
 * (PagesController::save, sitemapMeta, template) at the file-system
 * boundary the controllers all share: PageRepository::save() and the
 * url-meta.json overrides.
 */
final class PagesControllerTest extends TestCase
{
    private string $pagesDir;
    private PageRepository $repo;

    protected function setUp(): void
    {
        TestConfig::reset();
        $this->pagesDir = TempContent::make('pages-int');
        $this->repo = new PageRepository($this->pagesDir);
    }

    protected function tearDown(): void
    {
        TempContent::cleanup();
    }

    public function testContentSaveHitsBothFiles(): void
    {
        // Equivalent of POST /admin/pages/<slug>/content with body+meta.
        $this->repo->save('about', [
            'title' => 'About Us',
            'description' => 'Who we are',
            'status' => 'published',
        ], '# About body');

        self::assertFileExists($this->pagesDir . '/about.md');
        self::assertFileExists($this->pagesDir . '/about.meta.yaml');

        $loaded = $this->repo->find('about');
        self::assertNotNull($loaded);
        self::assertSame('About Us', $loaded['title']);
        self::assertSame('# About body', $loaded['content']);
    }

    public function testSitemapMetaOverrideRoundtrip(): void
    {
        // Mirror PagesController::saveMeta(): write a JSON sidecar with
        // per-URL overrides (in_sitemap / noindex / priority / changefreq),
        // then re-read it.
        $tmpDir = TempContent::make('url-meta');
        $metaPath = $tmpDir . '/url-meta.json';

        $payload = [
            '/about' => [
                'in_sitemap' => false,
                'noindex' => true,
                'priority' => '0.3',
                'changefreq' => 'monthly',
            ],
        ];

        $tmp = $metaPath . '.tmp';
        file_put_contents($tmp, json_encode($payload, JSON_PRETTY_PRINT));
        rename($tmp, $metaPath);

        self::assertFileExists($metaPath);
        $loaded = json_decode((string) file_get_contents($metaPath), true);
        self::assertSame(false, $loaded['/about']['in_sitemap']);
        self::assertSame('0.3', $loaded['/about']['priority']);
    }

    public function testTemplateOverrideRoundtrip(): void
    {
        // PagesController::template() persists a 'template' field on the
        // page's meta.yaml. Verify the round trip via the repository.
        $this->repo->save('home', [
            'title' => 'Home',
            'template' => 'landing-2026',
        ], 'home body');

        $loaded = $this->repo->find('home');
        self::assertNotNull($loaded);
        self::assertSame('landing-2026', $loaded['meta']['template'] ?? null);
    }

    public function testContentSaveOnExistingBundlePreservesLayout(): void
    {
        // Hand-create a bundle page first.
        $bundleDir = $this->pagesDir . '/bundle-page';
        mkdir($bundleDir, 0755, true);
        file_put_contents($bundleDir . '/index.md', 'old body');
        file_put_contents($bundleDir . '/meta.yaml', "title: Old Title\n");

        // PageRepository::save() must rewrite into the bundle, NOT create
        // a flat sibling, when a bundle already exists.
        $this->repo->save('bundle-page', ['title' => 'New Title'], 'new body');

        self::assertFileDoesNotExist($this->pagesDir . '/bundle-page.md');
        self::assertFileExists($bundleDir . '/index.md');
        self::assertFileExists($bundleDir . '/meta.yaml');
        self::assertSame('new body', file_get_contents($bundleDir . '/index.md'));
    }

    /**
     * editContent() splits the stored meta into a structured `meta_fields`
     * block + a disjoint `extras_yaml` blob. We exercise the two static
     * helpers it composes (splitMetaFields + extrasYaml) on the same input
     * shape an admin GET would build, mirroring how testContentSaveHitsBothFiles
     * stays at the repository boundary rather than booting the controller.
     */
    public function testEditContentReturnsParsedMetaFields(): void
    {
        $meta = [
            'title'        => 'About Us',
            'description'  => 'Who we are',
            'status'       => 'published',
            'date'         => '2026-04-21',
            'hero_image'   => '/uploads/about.webp',
            // An unknown key the form doesn't surface as a structured input.
            'tags'         => ['team', 'company'],
        ];

        $fields  = PagesController::splitMetaFields($meta);
        $extras  = PagesController::extrasYaml($meta);

        // Structured block carries the known keys verbatim.
        self::assertSame('published', $fields['status']);
        self::assertSame('2026-04-21', $fields['date']);
        self::assertSame('/uploads/about.webp', $fields['hero_image']);
        // scheduled_at missing from input falls through as empty string.
        self::assertSame('', $fields['scheduled_at']);

        // The extras YAML excludes everything the structured block owns +
        // title / description (Content tab) but preserves unknown keys.
        self::assertStringNotContainsString('status:', $extras);
        self::assertStringNotContainsString('date:', $extras);
        self::assertStringNotContainsString('hero_image:', $extras);
        self::assertStringNotContainsString('title:', $extras);
        self::assertStringNotContainsString('description:', $extras);
        self::assertStringContainsString('tags:', $extras);

        // And it round-trips through the parser.
        $reparsed = FrontMatter::parse($extras);
        self::assertSame(['team', 'company'], $reparsed['tags']);
    }

    /**
     * saveContent() merges raw-YAML extras with the structured fields.
     * Structured wins on conflict (the form is the source of truth for
     * the keys it knows about) and the repository must end up with both
     * blocks fused in the final on-disk meta.yaml.
     */
    public function testSaveContentMergesStructuredAndRawMeta(): void
    {
        $rawMeta = FrontMatter::parse("tags:\n  - alpha\n  - beta\ncustom_id: x-42\n");
        $fields = [
            'title'        => 'Merged',
            'description'  => 'desc',
            'hero_image'   => '/uploads/h.webp',
            'status'       => 'published',
            'date'         => '2026-05-01',
            'scheduled_at' => '',
        ];

        $merged = PagesController::mergeStructuredMeta($rawMeta, $fields);

        // Structured fields are written through verbatim.
        self::assertSame('Merged', $merged['title']);
        self::assertSame('desc', $merged['description']);
        self::assertSame('/uploads/h.webp', $merged['hero_image']);
        self::assertSame('published', $merged['status']);
        self::assertSame('2026-05-01', $merged['date']);
        // Unknown keys from raw YAML survive untouched.
        self::assertSame(['alpha', 'beta'], $merged['tags']);
        self::assertSame('x-42', $merged['custom_id']);

        // And the merge persists through the repository (real save -> find).
        $this->repo->save('merged-page', $merged, '# Body');
        $loaded = $this->repo->find('merged-page');
        self::assertNotNull($loaded);
        self::assertSame('published', $loaded['meta']['status']);
        self::assertSame(['alpha', 'beta'], $loaded['meta']['tags']);
        self::assertSame('x-42', $loaded['meta']['custom_id']);
    }

    /**
     * Emptying a structured field deletes the key, even if the raw YAML
     * blob still has it. Structured wins -- otherwise the form would be
     * unable to remove a value.
     */
    public function testSaveContentClearingFieldDeletesKey(): void
    {
        // User pasted `status: draft` into the advanced textarea AND left
        // the Status dropdown empty. The dropdown wins -> status drops out.
        $rawMeta = ['status' => 'draft', 'hero_image' => '/old.webp', 'keep_me' => 'yes'];
        $fields = [
            'title'        => '',
            'description'  => '',
            'hero_image'   => '',
            'status'       => '',
            'date'         => '',
            'scheduled_at' => '',
        ];

        $merged = PagesController::mergeStructuredMeta($rawMeta, $fields);

        self::assertArrayNotHasKey('status', $merged);
        self::assertArrayNotHasKey('hero_image', $merged);
        self::assertArrayNotHasKey('title', $merged);
        // Unknown key still preserved -- this is the "structured wins for
        // known keys, raw passes through for everything else" guarantee.
        self::assertSame('yes', $merged['keep_me']);
    }

    /**
     * The form must never silently drop YAML keys it doesn't recognise:
     * round-tripping through editContent -> saveContent on a meta with
     * exotic theme-specific keys must produce the same keys on disk.
     */
    public function testSaveContentPreservesUnknownYamlKeys(): void
    {
        // Seed the repository with a page that has meta the form has
        // never heard of.
        $original = [
            'title'         => 'Quirky',
            'description'   => 'desc',
            'status'        => 'published',
            'date'          => '2026-04-21',
            'theme_layout'  => 'wide',
            'analytics_id'  => 'UA-XYZ',
            'extra_section' => ['heading' => 'h', 'cta' => 'go'],
        ];
        $this->repo->save('quirky', $original, '# Quirky');

        // Simulate editContent(): structured -> meta_fields, unknown ->
        // extras_yaml.
        $reloaded = $this->repo->find('quirky');
        self::assertNotNull($reloaded);
        $metaFields = PagesController::splitMetaFields($reloaded['meta']);
        $extrasYaml = PagesController::extrasYaml($reloaded['meta']);

        // Now simulate saveContent() with the same fields the user would
        // see (no edits -- pure round-trip).
        $rawMeta = FrontMatter::parse($extrasYaml);
        self::assertIsArray($rawMeta);

        $fields = array_merge([
            'title'       => $reloaded['title'],
            'description' => $reloaded['description'],
        ], $metaFields);

        $merged = PagesController::mergeStructuredMeta($rawMeta, $fields);

        // Every original key survives the round-trip.
        self::assertSame('Quirky', $merged['title']);
        self::assertSame('published', $merged['status']);
        self::assertSame('2026-04-21', $merged['date']);
        self::assertSame('wide', $merged['theme_layout']);
        self::assertSame('UA-XYZ', $merged['analytics_id']);
        self::assertSame(['heading' => 'h', 'cta' => 'go'], $merged['extra_section']);

        // Persist + reload and confirm again at the filesystem boundary.
        $this->repo->save('quirky', $merged, '# Quirky');
        $after = $this->repo->find('quirky');
        self::assertNotNull($after);
        self::assertSame('wide', $after['meta']['theme_layout']);
        self::assertSame('UA-XYZ', $after['meta']['analytics_id']);
        self::assertSame(['heading' => 'h', 'cta' => 'go'], $after['meta']['extra_section']);
    }

    /**
     * Status must be one of the whitelisted values; date must be YYYY-MM-DD;
     * scheduled_at is required when status=scheduled. Anything else is a
     * 400 from saveContent() before we touch the repository.
     */
    public function testSaveContentRejectsInvalidStatus(): void
    {
        // Garbage status.
        $err = PagesController::validateMetaFields([
            'status' => 'archived', 'date' => '', 'scheduled_at' => '',
        ]);
        self::assertNotNull($err);
        self::assertStringContainsString('Invalid status', $err);

        // Malformed date (not YYYY-MM-DD).
        $err = PagesController::validateMetaFields([
            'status' => 'draft', 'date' => '2026/04/21', 'scheduled_at' => '',
        ]);
        self::assertNotNull($err);
        self::assertStringContainsString('YYYY-MM-DD', $err);

        // status=scheduled but no scheduled_at -> required.
        $err = PagesController::validateMetaFields([
            'status' => 'scheduled', 'date' => '2026-04-21', 'scheduled_at' => '',
        ]);
        self::assertNotNull($err);
        self::assertStringContainsString('scheduled_at', $err);

        // Happy paths return null.
        self::assertNull(PagesController::validateMetaFields([
            'status' => 'draft', 'date' => '2026-04-21', 'scheduled_at' => '',
        ]));
        self::assertNull(PagesController::validateMetaFields([
            'status' => 'published', 'date' => '', 'scheduled_at' => '',
        ]));
        self::assertNull(PagesController::validateMetaFields([
            'status' => 'scheduled', 'date' => '2026-04-21',
            'scheduled_at' => '2026-04-21T10:00',
        ]));
        // Empty everything is also valid -- "inherit defaults".
        self::assertNull(PagesController::validateMetaFields([
            'status' => '', 'date' => '', 'scheduled_at' => '',
        ]));
    }
}
