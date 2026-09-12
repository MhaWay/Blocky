<?php
declare(strict_types=1);

namespace Blocky\Core\Tests\Unit;

use Blocky\Core\Support\CssSanitizer;
use PHPUnit\Framework\TestCase;

final class CssSanitizerTest extends TestCase
{
    public function testAcceptsPlainUtilityCss(): void
    {
        $css = '.foo{padding:calc(var(--bky-space-4)*1)}.bar{--bky-tw-bg:red}';
        self::assertSame($css, CssSanitizer::sanitize($css));
    }

    public function testStripsComments(): void
    {
        self::assertSame('.a{color:red}', CssSanitizer::sanitize('/* hi */.a{color:red}/* bye */'));
    }

    /**
     * @return array<string,array{string}>
     */
    public static function forbiddenProvider(): array
    {
        return [
            'import'      => ['@import url("x.css");.a{color:red}'],
            'charset'     => ['@charset "utf-8";'],
            'expression'  => ['.a{width:expression(alert(1))}'],
            'behavior'    => ['.a{behavior:url(#x)}'],
            'mozbinding'  => ['.a{-moz-binding:url(x)}'],
            'javascript'  => ['.a{background:url(javascript:alert(1))}'],
            'vbscript'    => ['.a{background:vbscript:msgbox(1)}'],
            'externalurl' => ['.a{background:url(https://evil.test/x.png)}'],
            'protocolrel' => ['.a{background:url(//evil.test/x.png)}'],
            'stylebreak'  => ['.a{color:red}</style><script>1</script>'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('forbiddenProvider')]
    public function testRejectsDangerousConstructs(string $css): void
    {
        self::assertSame('', CssSanitizer::sanitize($css));
    }

    public function testRejectsOversizedInput(): void
    {
        self::assertSame('', CssSanitizer::sanitize(str_repeat('.a{color:red}', CssSanitizer::MAX_BYTES)));
    }

    public function testEmptyInEmptyOut(): void
    {
        self::assertSame('', CssSanitizer::sanitize('   '));
    }
}
