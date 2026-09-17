<?php
declare(strict_types=1);

namespace Blocky\Core\Tests\Unit;

use Blocky\Core\Support\IconLibrary;
use PHPUnit\Framework\TestCase;

final class IconLibrarySanitizeTest extends TestCase
{
    public function testBenignSvgKeepsArtButDropsHandlersAndStyles(): void
    {
        $raw = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" onload="alert(1)" style="background:url(http://evil)">'
            . '<circle cx="12" cy="12" r="9" fill="#e11" stroke-width="2"/></svg>';
        $out = IconLibrary::sanitize_svg($raw);
        self::assertNotNull($out);
        self::assertStringContainsString('<circle', $out);
        self::assertStringContainsString('fill="#e11"', $out);
        self::assertStringNotContainsString('onload', $out);
        self::assertStringNotContainsString('style=', $out);
    }

    public function testRejectsScriptsDoctypesAndEntities(): void
    {
        self::assertNull(IconLibrary::sanitize_svg('<svg><script>alert(1)</script></svg>'));
        self::assertNull(IconLibrary::sanitize_svg('<!DOCTYPE svg [<!ENTITY x "y">]><svg/>'));
        self::assertNull(IconLibrary::sanitize_svg('<!ENTITY x SYSTEM "file:///etc/passwd"><svg/>'));
        self::assertNull(IconLibrary::sanitize_svg('<div>not svg</div>'));
        self::assertNull(IconLibrary::sanitize_svg(str_repeat('<svg xmlns="http://www.w3.org/2000/svg"></svg>', 5000)));
    }

    public function testStripsEventAttributesAndDangerousUris(): void
    {
        $raw = '<svg xmlns="http://www.w3.org/2000/svg"><g onclick="x()" transform="translate(1,1)">'
            . '<a href="javascript:alert(1)"><circle r="5" stroke="blue"/></a></g></svg>';
        $out = IconLibrary::sanitize_svg($raw);
        self::assertNotNull($out);
        self::assertStringNotContainsString('onclick', $out);
        self::assertStringNotContainsString('javascript:', $out);
        // Non-allowlisted elements are dropped with their whole subtree.
        self::assertStringNotContainsString('<a', $out);
        self::assertStringNotContainsString('stroke="blue"', $out);
        self::assertStringContainsString('transform="translate(1,1)"', $out);
    }

    public function testRejectsoversizedinput(): void
    {
        self::assertNull(IconLibrary::sanitize_svg(str_repeat('a', 70000)));
    }
}
