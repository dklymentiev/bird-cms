<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Content\ProjectRepository;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\TempContent;
use Tests\Support\TestConfig;

/**
 * ProjectRepository writes inline-frontmatter single-file format
 * (---\nfm\n---\nbody) rather than the body+sidecar split the article and
 * page repositories use. Tests pin that round-trips, format detection,
 * and slug validation match.
 */
final class ProjectRepositoryTest extends TestCase
{
    private string $projectsDir;
    private ProjectRepository $repo;

    protected function setUp(): void
    {
        TestConfig::reset();
        $this->projectsDir = TempContent::make('projects');
        $this->repo = new ProjectRepository($this->projectsDir);
    }

    protected function tearDown(): void
    {
        TempContent::cleanup();
    }

    public function testSaveWritesAtomically(): void
    {
        $this->repo->save('flint', ['title' => 'Flint', 'description' => 'd'], 'project body');
        $path = $this->projectsDir . '/flint.md';
        self::assertFileExists($path);
        $contents = (string) file_get_contents($path);
        self::assertStringContainsString('---', $contents);
        self::assertStringContainsString('Flint', $contents);
        self::assertStringContainsString('project body', $contents);
    }

    public function testFindRoundtrip(): void
    {
        $this->repo->save('lukas', [
            'title' => 'Lukas',
            'description' => 'For Nina',
            'tech' => ['python', 'fastapi'],
        ], 'lukas body');

        $found = $this->repo->find('lukas');
        self::assertNotNull($found);
        self::assertSame('lukas', $found['slug']);
        self::assertSame('Lukas', $found['title']);
        self::assertSame('For Nina', $found['description']);
        self::assertSame('lukas body', $found['content']);
    }

    public function testFindReturnsNullForMissing(): void
    {
        self::assertNull($this->repo->find('does-not-exist'));
    }

    public function testInvalidSlugIsRejected(): void
    {
        $this->expectException(RuntimeException::class);
        $this->repo->save('Bad!', ['title' => 'X'], 'b');
    }

    public function testBundleFormatLoads(): void
    {
        // Hand-craft bundle: <slug>/index.md with frontmatter.
        $bundleDir = $this->projectsDir . '/screenbox';
        mkdir($bundleDir, 0755, true);
        file_put_contents(
            $bundleDir . '/index.md',
            "---\ntitle: Screenbox\n---\nbundle body\n"
        );

        $found = $this->repo->find('screenbox');
        self::assertNotNull($found);
        self::assertSame('Screenbox', $found['title']);
        self::assertStringContainsString('bundle body', $found['content']);
    }
}
