<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Content\PageRepository;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\TempContent;
use Tests\Support\TestConfig;

final class PageRepositoryTest extends TestCase
{
    private string $pagesDir;
    private PageRepository $repo;

    protected function setUp(): void
    {
        TestConfig::reset();
        $this->pagesDir = TempContent::make('pages');
        $this->repo = new PageRepository($this->pagesDir);
    }

    protected function tearDown(): void
    {
        TempContent::cleanup();
    }

    public function testSaveWritesAtomically(): void
    {
        $this->repo->save('about', ['title' => 'About', 'description' => 'd'], '## About body');

        $body = $this->pagesDir . '/about.md';
        $meta = $this->pagesDir . '/about.meta.yaml';
        self::assertFileExists($body);
        self::assertFileExists($meta);
        self::assertSame('## About body', file_get_contents($body));
    }

    public function testFindRoundtrip(): void
    {
        $this->repo->save('contact', [
            'title' => 'Contact Us',
            'description' => 'Reach the team',
        ], 'contact body');

        $found = $this->repo->find('contact');
        self::assertNotNull($found);
        self::assertSame('contact', $found['slug']);
        self::assertSame('Contact Us', $found['title']);
        self::assertSame('Reach the team', $found['description']);
        self::assertSame('contact body', $found['content']);
    }

    public function testFindReturnsNullForMissing(): void
    {
        self::assertNull($this->repo->find('does-not-exist'));
    }

    public function testInvalidSlugIsRejectedOnSave(): void
    {
        $this->expectException(RuntimeException::class);
        $this->repo->save('Bad Slug', ['title' => 'X'], 'body');
    }

    public function testFlatAndBundleFormatBothLoad(): void
    {
        $this->repo->save('flat-page', ['title' => 'Flat Page'], 'flat');

        $bundleDir = $this->pagesDir . '/bundle-page';
        mkdir($bundleDir, 0755, true);
        file_put_contents($bundleDir . '/index.md', 'bundle');
        file_put_contents($bundleDir . '/meta.yaml', "title: Bundle Page\n");

        $flat = $this->repo->find('flat-page');
        $bundle = $this->repo->find('bundle-page');
        self::assertNotNull($flat);
        self::assertNotNull($bundle);
        self::assertSame('Flat Page', $flat['title']);
        self::assertSame('Bundle Page', $bundle['title']);
    }

    public function testAllListsBoth(): void
    {
        $this->repo->save('one', ['title' => 'One'], 'one');
        $this->repo->save('two', ['title' => 'Two'], 'two');

        $all = $this->repo->all();
        $slugs = array_map(static fn(array $p) => $p['slug'], $all);
        sort($slugs);
        self::assertSame(['one', 'two'], $slugs);
    }
}
