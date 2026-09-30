<?php
declare(strict_types=1);

namespace Blocky\Core\Tests\Unit;

use Blocky\Core\Blocks\Node;
use Blocky\Core\Blocks\Renderers\ButtonRenderer;
use Blocky\Core\Support\FrontendHtml;
use Blocky\Core\Support\HtmlString;
use Blocky\Core\Support\RenderContext;
use PHPUnit\Framework\TestCase;

final class OutputEscapingTest extends TestCase
{
    public function testBuildAttrsEscapesQuotesAndAngleBrackets(): void
    {
        $out = HtmlString::attrs([
            'class' => '"><script>alert(1)</script>',
            'data-x' => "'><b>i</b>",
        ]);
        self::assertStringNotContainsString('<script', $out);
        self::assertStringNotContainsString('><b>', $out);
        self::assertStringContainsString('&lt;script&gt;', $out);
    }

    public function testButtonRendererNeutralisesMaliciousProps(): void
    {
        $node = new Node(
            id: 'n1',
            type: 'button',
            props: [
                'label' => '"><img src=x onerror=alert(1)>',
                'href' => 'javascript:alert(1)',
            ],
            slots: ['default' => []],
            variants: [],
        );
        $html = (new ButtonRenderer())->render($node, RenderContext::makeMinimal())->toString();
        self::assertStringNotContainsString('<img', $html);
        self::assertStringNotContainsString('javascript:', $html);
        self::assertStringContainsString('&lt;img', $html);
    }

    public function testFrontendHtmlAllowListIncludesIslandAttributes(): void
    {
        $allowed = FrontendHtml::allowed_html();
        foreach (['div', 'span', 'button', 'a', 'img', 'input', 'form', 'iframe'] as $tag) {
            self::assertArrayHasKey($tag, $allowed);
            self::assertArrayHasKey('data-*', $allowed[$tag]);
        }
        self::assertArrayHasKey('path', $allowed);
    }
}
