<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Content\AreaRepository;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\TempContent;
use Tests\Support\TestConfig;

/**
 * AreaRepository is the odd one out: pure-YAML records (no markdown body),
 * with parent/child relationships for sub-areas. Tests cover the YAML-only
 * round trip plus the subarea attach behaviour.
 */
final class AreaRepositoryTest extends TestCase
{
    private string $areasDir;
    private AreaRepository $repo;

    protected function setUp(): void
    {
        TestConfig::reset();
        $this->areasDir = TempContent::make('areas');
        $this->repo = new AreaRepository($this->areasDir);
    }

    protected function tearDown(): void
    {
        TempContent::cleanup();
    }

    public function testSaveWritesYamlFile(): void
    {
        $this->repo->save('toronto', ['name' => 'Toronto']);
        $path = $this->areasDir . '/toronto.yaml';
        self::assertFileExists($path);
        $raw = (string) file_get_contents($path);
        self::assertStringContainsString('Toronto', $raw);
        self::assertStringContainsString('toronto', $raw);
    }

    public function testFindRoundtrip(): void
    {
        $this->repo->save('toronto', ['name' => 'Toronto', 'population' => 3000000]);
        $repo = new AreaRepository($this->areasDir);

        $area = $repo->find('toronto');
        self::assertNotNull($area);
        self::assertSame('toronto', $area['slug']);
        self::assertSame('Toronto', $area['name']);
    }

    public function testInvalidSlugIsRejected(): void
    {
        $this->expectException(RuntimeException::class);
        $this->repo->save('Bad Slug', ['name' => 'X']);
    }

    public function testSubareasAreAttachedToParent(): void
    {
        $this->repo->save('toronto', ['name' => 'Toronto']);
        $this->repo->save('north-york', ['name' => 'North York', 'parent' => 'toronto']);

        $repo = new AreaRepository($this->areasDir);
        $sub = $repo->findSubarea('toronto', 'north-york');
        self::assertNotNull($sub);
        self::assertSame('North York', $sub['name']);
        self::assertSame('toronto', $sub['parent']);
    }

    public function testAllFlattensTopLevelAndSubareas(): void
    {
        $this->repo->save('toronto', ['name' => 'Toronto']);
        $this->repo->save('north-york', ['name' => 'North York', 'parent' => 'toronto']);

        $repo = new AreaRepository($this->areasDir);
        $all = $repo->all();
        $slugs = array_map(static fn(array $a) => $a['slug'], $all);
        self::assertContains('toronto', $slugs);
        self::assertContains('north-york', $slugs);
    }
}
