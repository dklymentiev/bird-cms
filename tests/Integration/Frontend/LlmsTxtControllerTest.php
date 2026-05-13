<?php

declare(strict_types=1);

namespace Tests\Integration\Frontend;

use App\Content\ArticleRepository;
use App\Http\Frontend\LlmsTxtController;
use PHPUnit\Framework\TestCase;
use Tests\Support\TempContent;
use Tests\Support\TestConfig;

/**
 * LlmsTxtController produces a text/plain feed of articles grouped by
 * their declared `type`. The view layer is a stack of `echo`s inside
 * handle(); we capture stdout and assert on the body shape.
 *
 * Coverage:
 *   - site name + description make it into the heading
 *   - articles are grouped under type sections (## H2)
 *   - each item is rendered as a markdown link with optional ": <desc>"
 *   - the Contact section anchors the document
 */
final class LlmsTxtControllerTest extends TestCase
{
    private string $articlesDir;
    private ArticleRepository $repo;

    protected function setUp(): void
    {
        TestConfig::reset();
        TestConfig::set('site_url', 'http://example.test');
        TestConfig::set('site_name', 'Example Site');
        TestConfig::set('site_description', 'Example tagline');
        $this->articlesDir = TempContent::make('llms');
        $this->repo = new ArticleRepository($this->articlesDir);
    }

    protected function tearDown(): void
    {
        TempContent::cleanup();
    }

    public function testRendersHeaderDescriptionAndContactBlocks(): void
    {
        $this->repo->save('blog', 'first', $this->meta(['title' => 'First', 'type' => 'insight']), 'body');
        $body = $this->capture(new LlmsTxtController($this->repo, ''));

        // Title + description
        self::assertStringContainsString("# Example Site\n", $body);
        self::assertStringContainsString('> Example tagline', $body);
        // Contact block
        self::assertStringContainsString("## Contact\n", $body);
        self::assertStringContainsString('Website: http://example.test', $body);
    }

    public function testGroupsArticlesByType(): void
    {
        $this->repo->save('blog', 'a', $this->meta(['title' => 'A', 'type' => 'insight']), 'body');
        $this->repo->save('blog', 'b', $this->meta(['title' => 'B', 'type' => 'how-to']), 'body');
        $this->repo->save('blog', 'c', $this->meta(['title' => 'C', 'type' => 'insight']), 'body');

        $body = $this->capture(new LlmsTxtController($this->repo, ''));

        // Two distinct type sections — case-preserved-via-ucfirst.
        self::assertStringContainsString('## Insight', $body);
        self::assertStringContainsString('## How-to', $body);

        // Items are markdown links.
        self::assertStringContainsString('[A](http://example.test/blog/a', $body);
        self::assertStringContainsString('[C](http://example.test/blog/c', $body);
        self::assertStringContainsString('[B](http://example.test/blog/b', $body);
    }

    public function testEmptyDescriptionOmitsBlockquote(): void
    {
        TestConfig::set('site_description', '');
        $body = $this->capture(new LlmsTxtController($this->repo, ''));
        self::assertStringNotContainsString('> ', $body);
    }

    public function testArticleUrlPrefixIsHonored(): void
    {
        $this->repo->save('blog', 'with-prefix', $this->meta(['title' => 'P']), 'body');
        $body = $this->capture(new LlmsTxtController($this->repo, '/articles'));
        self::assertStringContainsString('http://example.test/articles/blog/with-prefix', $body);
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function meta(array $overrides = []): array
    {
        return array_replace([
            'title' => 'Default',
            'description' => 'd',
            'date' => '2025-01-01',
            'type' => 'insight',
            'status' => 'published',
            'tags' => [],
            'primary' => 'k',
        ], $overrides);
    }

    private function capture(LlmsTxtController $controller): string
    {
        ob_start();
        try {
            $controller->handle();
            return (string) ob_get_clean();
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
    }
}
