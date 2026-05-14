<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Content\FrontMatter;
use PHPUnit\Framework\TestCase;

/**
 * Coverage for FrontMatter encoder + parser round-trip safety.
 *
 * Each bug class gets a single decode -> encode -> decode round-trip
 * test plus an encode-only assertion against a YAML library reference
 * shape. The four classes mirror the audit captured in v3.1.7 CHANGELOG.
 */
final class FrontMatterTest extends TestCase
{
    /** Bug 1: addslashes corrupts the apostrophe on each encode pass. */
    public function testApostropheSurvivesMultipleRoundTrips(): void
    {
        $original = ["title" => "Dmytro\x27s plan"];

        $yaml1 = FrontMatter::encode($original);
        $back1 = FrontMatter::parse($yaml1);
        self::assertSame("Dmytro\x27s plan", $back1["title"], "first round-trip must preserve the apostrophe");

        $yaml2 = FrontMatter::encode($back1);
        $back2 = FrontMatter::parse($yaml2);
        self::assertSame("Dmytro\x27s plan", $back2["title"], "second round-trip must NOT add a backslash");

        $yaml3 = FrontMatter::encode($back2);
        $back3 = FrontMatter::parse($yaml3);
        self::assertSame("Dmytro\x27s plan", $back3["title"], "third round-trip must NOT have a runaway backslash count");
    }

    /** Bug 2: unquoted string containing inner single quotes drops the trailing portion. */
    public function testStringWithInnerSingleQuotesIsPreserved(): void
    {
        $original = ["caption" => "It\x27s a \x27good\x27 idea"];

        $yaml = FrontMatter::encode($original);
        $back = FrontMatter::parse($yaml);

        self::assertSame("It\x27s a \x27good\x27 idea", $back["caption"]);
    }

    /** Bug 3: numeric-looking strings (leading zeros) must keep their string form. */
    public function testNumericLookingStringKeepsLeadingZero(): void
    {
        $original = ["step" => "01", "code" => "007"];

        $yaml = FrontMatter::encode($original);
        $back = FrontMatter::parse($yaml);

        self::assertSame("01", $back["step"], "must NOT cast '01' to int 1");
        self::assertSame("007", $back["code"], "must NOT cast '007' to int 7");
    }

    /** Bug 4: list of objects where an object value is itself a sequential array. */
    public function testNestedArrayInsideListItemIsEncodedAndParsedBack(): void
    {
        $original = [
            "stack" => [
                [
                    "name"  => "backend",
                    "items" => ["PHP", "MySQL", "Redis"],
                ],
                [
                    "name"  => "frontend",
                    "items" => ["TypeScript", "Vue"],
                ],
            ],
        ];

        $yaml = FrontMatter::encode($original);

        self::assertStringNotContainsString(
            "Array",
            $yaml,
            "encode must NOT collapse a nested array into the literal string 'Array'"
        );

        $back = FrontMatter::parse($yaml);

        self::assertSame($original, $back, "list-of-objects with nested seq array must survive round-trip");
    }

    /** Smoke: simple flat round-trip still works (regression guard). */
    public function testFlatRoundTrip(): void
    {
        $original = [
            "title" => "Hello world",
            "slug"  => "hello-world",
            "date"  => "2026-05-13",
            "draft" => false,
            "count" => 42,
        ];

        $yaml = FrontMatter::encode($original);
        $back = FrontMatter::parse($yaml);

        self::assertSame($original, $back);
    }

    /** Bug 5 (v3.1.8): quoted list-item containing `: ` must NOT be re-parsed as a map. */
    public function testQuotedListItemWithColonStaysString(): void
    {
        $original = ["paragraphs" => [
            "Everyone has seen the demo: ask an AI agent to book a flight.",
            "Another: with colon inside.",
            "Plain string no colon",
            "URL inline: https://example.com/path",
        ]];

        $yaml = FrontMatter::encode($original);
        $back = FrontMatter::parse($yaml);

        self::assertSame($original, $back);
    }
}
