<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Content\ServiceRepository;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\TempContent;
use Tests\Support\TestConfig;

final class ServiceRepositoryTest extends TestCase
{
    private string $servicesDir;
    private ServiceRepository $repo;

    protected function setUp(): void
    {
        TestConfig::reset();
        $this->servicesDir = TempContent::make('services');
        $this->repo = new ServiceRepository($this->servicesDir);
    }

    protected function tearDown(): void
    {
        TempContent::cleanup();
    }

    public function testSaveWritesAtomically(): void
    {
        $this->repo->save('residential', 'house-cleaning', [
            'title' => 'House Cleaning',
            'description' => 'desc',
        ], 'body');

        $body = $this->servicesDir . '/residential/house-cleaning.md';
        $meta = $this->servicesDir . '/residential/house-cleaning.meta.yaml';
        self::assertFileExists($body);
        self::assertFileExists($meta);
        self::assertSame('body', file_get_contents($body));
    }

    public function testFindRoundtrip(): void
    {
        $this->repo->save('residential', 'window-cleaning', [
            'title' => 'Window Cleaning',
            'description' => 'sparkly',
            'priority' => 5,
        ], 'window body');

        $found = $this->repo->find('residential', 'window-cleaning');
        self::assertNotNull($found);
        self::assertSame('window-cleaning', $found['slug']);
        self::assertSame('Window Cleaning', $found['title']);
        self::assertSame('residential', $found['type']);
        self::assertSame('window body', $found['content']);
    }

    public function testInvalidTypeIsRejected(): void
    {
        $this->expectException(RuntimeException::class);
        $this->repo->save('Bad Type', 'ok-slug', ['title' => 't'], '');
    }

    public function testInvalidSlugIsRejected(): void
    {
        $this->expectException(RuntimeException::class);
        $this->repo->save('residential', 'Bad Slug', ['title' => 't'], '');
    }

    public function testByTypeReturnsOnlyMatching(): void
    {
        $this->repo->save('residential', 'a', ['title' => 'A'], '');
        $this->repo->save('residential', 'b', ['title' => 'B'], '');
        $this->repo->save('commercial', 'c', ['title' => 'C'], '');

        $repo = new ServiceRepository($this->servicesDir);
        $r = $repo->byType('residential');
        $c = $repo->byType('commercial');
        self::assertCount(2, $r);
        self::assertCount(1, $c);
    }

    public function testFlatAndBundleFormatBothLoad(): void
    {
        // Flat
        $this->repo->save('residential', 'flat-svc', ['title' => 'Flat Svc'], '');

        // Bundle
        $bundleDir = $this->servicesDir . '/residential/bundle-svc';
        mkdir($bundleDir, 0755, true);
        file_put_contents($bundleDir . '/index.md', 'bundle');
        file_put_contents(
            $bundleDir . '/meta.yaml',
            "title: Bundle Svc\nslug: bundle-svc\n"
        );

        $repo = new ServiceRepository($this->servicesDir);
        $svcs = $repo->byType('residential');
        $titles = array_map(static fn(array $s) => $s['title'], $svcs);
        sort($titles);
        self::assertSame(['Bundle Svc', 'Flat Svc'], $titles);
    }
}
