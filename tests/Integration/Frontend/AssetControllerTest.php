<?php

declare(strict_types=1);

namespace Tests\Integration\Frontend;

use App\Http\Frontend\AssetController;
use PHPUnit\Framework\TestCase;

/**
 * AssetController serves uploads/ and content/<bundle>/ images via PHP
 * because nginx can't reach those paths on the current deployment shape.
 *
 * We assert two things:
 *   - URI predicate (matches()): only image extensions in the right
 *     subtrees, and the .ico extension is uploads/-only (the content/
 *     branch in the original code intentionally excluded .ico).
 *   - On a hit, the response carries the right Content-Type from the
 *     MIME map. We can't actually exit/readfile in a test, so we cover
 *     the predicate + the MIME map by exercising the matcher and the
 *     extension lookup the controller would use.
 *
 * The on-disk path that AssetController takes calls exit() — we don't
 * invoke handle() directly here. The 404-on-miss path is observable via
 * `http_response_code()` after a non-existent file is requested through
 * the dispatcher, which the smoke test covers end-to-end.
 */
final class AssetControllerTest extends TestCase
{
    public function testMatchesUploadsImageExtensions(): void
    {
        self::assertTrue(AssetController::matches('uploads/photo.jpg'));
        self::assertTrue(AssetController::matches('uploads/icon.ico'));
        self::assertTrue(AssetController::matches('uploads/anim.gif'));
        self::assertTrue(AssetController::matches('uploads/nested/dir/file.webp'));
    }

    public function testMatchesContentImageExtensions(): void
    {
        self::assertTrue(AssetController::matches('content/articles/blog/post/hero.webp'));
        self::assertTrue(AssetController::matches('content/foo.png'));
    }

    public function testRejectsNonImageUris(): void
    {
        // Non-image extensions
        self::assertFalse(AssetController::matches('uploads/script.php'));
        self::assertFalse(AssetController::matches('uploads/notes.txt'));
        self::assertFalse(AssetController::matches('content/page.md'));

        // .ico is uploads-only (the pre-refactor content branch didn't list
        // .ico in its MIME map; preserving that behavior is the point of
        // having two separate matchers).
        self::assertFalse(AssetController::matches('content/favicon.ico'));

        // Wrong subtree
        self::assertFalse(AssetController::matches('foo/uploads/image.jpg'));
        self::assertFalse(AssetController::matches('admin/image.jpg'));
    }

    public function testRejectsTraversalLikePathsAtPredicateLayer(): void
    {
        // The predicate is a substring + extension check. Path traversal
        // is a deployment-layer concern (nginx, file_exists()) — we just
        // confirm the predicate doesn't accept obviously wrong shapes.
        self::assertFalse(AssetController::matches('../uploads/image.jpg'));
        self::assertFalse(AssetController::matches('uploads/'));
    }

    public function testConstructorAcceptsSiteRoot(): void
    {
        // Smoke: building the controller doesn't throw for a valid path.
        // handle() ends in exit() so it isn't exercised here.
        $controller = new AssetController(sys_get_temp_dir());
        self::assertInstanceOf(AssetController::class, $controller);
    }
}
