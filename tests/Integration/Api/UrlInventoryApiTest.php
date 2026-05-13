<?php

declare(strict_types=1);

namespace Tests\Integration\Api;

use App\Http\Api\Request;
use App\Http\Api\Response;
use App\Http\Api\ResponseSentException;
use App\Http\Api\UrlMetaController;
use PHPUnit\Framework\TestCase;
use Tests\Support\TempContent;
use Tests\Support\TestConfig;

/**
 * Coverage for /api/v1/url-meta GET/PUT.
 *
 * /api/v1/url-inventory itself depends on ContentRouter + config load,
 * which is exercised by the admin PagesControllerTest; this class
 * focuses on the per-URL meta endpoint shape + path-traversal
 * defences that are unique to the API surface.
 */
final class UrlInventoryApiTest extends TestCase
{
    protected function setUp(): void
    {
        TestConfig::reset();
        Response::$testMode = true;
        Response::$lastCaptured = null;

        // The controllers use SITE_ROOT to compute uploads + storage
        // paths. SITE_ROOT is a runtime constant -- once defined it
        // sticks for the entire PHPUnit process. Pin it to a stable
        // sys_get_temp_dir() child that survives TempContent cleanup
        // between test cases.
        TestSiteRoot::ensure();
        @mkdir(SITE_ROOT . '/storage', 0755, true);

        // Reset cache between tests since the controller writes to
        // a single static-cached file.
        \App\Support\UrlMeta::reset();
    }

    protected function tearDown(): void
    {
        Response::$testMode = false;
        Request::setBody(null);

        // Clean up any state left behind in the shared SITE_ROOT.
        @unlink(SITE_ROOT . '/storage/url-meta.json');

        TempContent::cleanup();
    }

    public function testShowReturnsDefaultsForUnknownPath(): void
    {
        $c = new UrlMetaController();
        try {
            $c->show('/blog/unknown');
            self::fail('show() should have thrown.');
        } catch (ResponseSentException $e) {
            self::assertSame(200, $e->status);
            $decoded = $e->decoded();
            self::assertSame('/blog/unknown', $decoded['path']);
            self::assertTrue($decoded['in_sitemap'], 'Default in_sitemap is true.');
            self::assertFalse($decoded['noindex']);
        }
    }

    public function testUpdateAndRoundTripPersists(): void
    {
        $c = new UrlMetaController();

        $this->withJsonBody([
            'in_sitemap' => false,
            'noindex'    => true,
            'priority'   => '0.4',
            'changefreq' => 'monthly',
        ]);
        try {
            $c->update('/blog/post-a');
        } catch (ResponseSentException $e) {
            self::assertSame(200, $e->status);
        }

        \App\Support\UrlMeta::reset();
        try {
            (new UrlMetaController())->show('/blog/post-a');
        } catch (ResponseSentException $e) {
            $decoded = $e->decoded();
            self::assertFalse($decoded['in_sitemap']);
            self::assertTrue($decoded['noindex']);
            self::assertSame('0.4', $decoded['priority']);
            self::assertSame('monthly', $decoded['changefreq']);
        }
    }

    public function testUpdateRejectsPathTraversal(): void
    {
        $c = new UrlMetaController();
        $this->expectException(ResponseSentException::class);
        try {
            $c->update('/blog/../etc/passwd');
        } catch (ResponseSentException $e) {
            self::assertSame(400, $e->status);
            self::assertSame('invalid_path', $e->decoded()['error']['code']);
            throw $e;
        }
    }

    public function testUpdateRejectsInvalidPriority(): void
    {
        $c = new UrlMetaController();
        $this->withJsonBody(['priority' => '1.5']);
        $this->expectException(ResponseSentException::class);
        try {
            $c->update('/blog/x');
        } catch (ResponseSentException $e) {
            self::assertSame(400, $e->status);
            self::assertSame('invalid_priority', $e->decoded()['error']['code']);
            throw $e;
        }
    }

    public function testUpdateRejectsInvalidChangefreq(): void
    {
        $c = new UrlMetaController();
        $this->withJsonBody(['changefreq' => 'every-fortnight']);
        $this->expectException(ResponseSentException::class);
        try {
            $c->update('/blog/x');
        } catch (ResponseSentException $e) {
            self::assertSame(400, $e->status);
            self::assertSame('invalid_changefreq', $e->decoded()['error']['code']);
            throw $e;
        }
    }

    /**
     * UrlMetaController reads its body through Request::body(). Tests
     * install the JSON they want the controller to see; tearDown
     * reverts the override.
     *
     * @param array<string, mixed> $body
     */
    private function withJsonBody(array $body): void
    {
        Request::setBody(json_encode($body) ?: '');
    }
}
