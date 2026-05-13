<?php

declare(strict_types=1);

namespace Tests\Integration\Api;

use App\Http\Api\AssetsController;
use App\Http\Api\Response;
use App\Http\Api\ResponseSentException;
use PHPUnit\Framework\TestCase;

/**
 * Coverage for /api/v1/assets/*.
 *
 * The two invariants worth pinning down:
 *   1. Path traversal is rejected up-front by normalisePath().
 *   2. Upload writes land under uploads/ and never outside.
 *
 * The controller's upload path uses move_uploaded_file when running
 * inside a SAPI context. In CLI/PHPUnit we fall back to rename(),
 * which the controller already supports for testing.
 */
final class AssetsApiTest extends TestCase
{
    protected function setUp(): void
    {
        Response::$testMode = true;
        Response::$lastCaptured = null;

        TestSiteRoot::ensure();
        $uploadsDir = SITE_ROOT . '/uploads';
        if (is_dir($uploadsDir)) {
            $this->recursiveCleanup($uploadsDir);
        }
        @mkdir($uploadsDir, 0755, true);
    }

    protected function tearDown(): void
    {
        Response::$testMode = false;
        $uploadsDir = SITE_ROOT . '/uploads';
        if (is_dir($uploadsDir)) {
            $this->recursiveCleanup($uploadsDir);
        }
        unset($_FILES['file'], $_POST['path']);
    }

    public function testUploadAndShowRoundTrip(): void
    {
        // Stage a fake PNG (1x1 transparent pixel). 67 bytes is enough
        // to satisfy size > 0 and finfo's PNG detector.
        $pngBytes = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII='
        );
        $tmp = tempnam(sys_get_temp_dir(), 'pngup_');
        file_put_contents($tmp, $pngBytes);

        $_FILES['file'] = [
            'name'     => 'pixel.png',
            'type'     => 'image/png',
            'tmp_name' => $tmp,
            'error'    => UPLOAD_ERR_OK,
            'size'     => filesize($tmp),
        ];
        $_POST['path'] = 'images/pixel.png';

        $c = new AssetsController();
        try {
            $c->upload();
        } catch (ResponseSentException $e) {
            self::assertSame(201, $e->status);
            $decoded = $e->decoded();
            self::assertSame('images/pixel.png', $decoded['path']);
            self::assertSame('image/png', $decoded['mime']);
        }

        $stored = SITE_ROOT . '/uploads/images/pixel.png';
        self::assertFileExists($stored);

        // show()
        try {
            (new AssetsController())->show('images/pixel.png');
        } catch (ResponseSentException $e) {
            self::assertSame(200, $e->status);
            $decoded = $e->decoded();
            self::assertSame('image/png', $decoded['mime']);
            self::assertGreaterThan(0, (int) $decoded['size']);
        }
    }

    public function testUploadRejectsPathTraversal(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'evil_');
        file_put_contents($tmp, 'GIF87a' . str_repeat("\0", 32));

        $_FILES['file'] = [
            'name'     => 'evil.png',
            'type'     => 'image/png',
            'tmp_name' => $tmp,
            'error'    => UPLOAD_ERR_OK,
            'size'     => filesize($tmp),
        ];
        $_POST['path'] = '../../../etc/evil.png';

        $this->expectException(ResponseSentException::class);
        try {
            (new AssetsController())->upload();
        } catch (ResponseSentException $e) {
            self::assertSame(400, $e->status);
            self::assertSame('invalid_path', $e->decoded()['error']['code']);
            // Make sure nothing was written outside uploads/.
            self::assertFalse(file_exists(SITE_ROOT . '/../../etc/evil.png'));
            throw $e;
        }
    }

    public function testUploadRejectsAbsolutePath(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'abs_');
        file_put_contents($tmp, 'GIF87a' . str_repeat("\0", 32));

        $_FILES['file'] = [
            'name'     => 'x.gif',
            'type'     => 'image/gif',
            'tmp_name' => $tmp,
            'error'    => UPLOAD_ERR_OK,
            'size'     => filesize($tmp),
        ];
        $_POST['path'] = '/etc/x.gif';

        $this->expectException(ResponseSentException::class);
        try {
            (new AssetsController())->upload();
        } catch (ResponseSentException $e) {
            self::assertSame(400, $e->status);
            throw $e;
        }
    }

    public function testDeleteIsIdempotent(): void
    {
        // Stage and upload first.
        $pngBytes = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII='
        );
        $tmp = tempnam(sys_get_temp_dir(), 'pngdel_');
        file_put_contents($tmp, $pngBytes);

        $_FILES['file'] = [
            'name'     => 'a.png',
            'type'     => 'image/png',
            'tmp_name' => $tmp,
            'error'    => UPLOAD_ERR_OK,
            'size'     => filesize($tmp),
        ];
        $_POST['path'] = 'one-shot.png';

        try {
            (new AssetsController())->upload();
        } catch (ResponseSentException) {
            // ignore
        }
        self::assertFileExists(SITE_ROOT . '/uploads/one-shot.png');

        try {
            (new AssetsController())->destroy('one-shot.png');
        } catch (ResponseSentException $e) {
            self::assertSame(200, $e->status);
            self::assertTrue($e->decoded()['deleted']);
        }
        self::assertFileDoesNotExist(SITE_ROOT . '/uploads/one-shot.png');

        // Second delete: still 200, deleted=false.
        try {
            (new AssetsController())->destroy('one-shot.png');
        } catch (ResponseSentException $e) {
            self::assertSame(200, $e->status);
            self::assertFalse($e->decoded()['deleted']);
        }
    }

    private function recursiveCleanup(string $dir): void
    {
        if (!is_dir($dir)) return;
        foreach (scandir($dir) ?: [] as $i) {
            if ($i === '.' || $i === '..') continue;
            $path = $dir . '/' . $i;
            if (is_dir($path)) {
                $this->recursiveCleanup($path);
                @rmdir($path);
            } else {
                @unlink($path);
            }
        }
    }
}
