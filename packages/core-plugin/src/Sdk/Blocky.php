<?php
declare(strict_types=1);

namespace Blocky\Core\Sdk;

use Blocky\Core\Plugin;
use Blocky\Core\Blocks\BlockDefinition;
use Blocky\Core\Blocks\Node;
use Blocky\Core\Tokens\ThemeVariant;
use Blocky\Core\Support\HtmlString;

/**
 * Public PHP API facade for Blocky.
 *
 * Usage:
 *   Blocky::registerBlock($definition);
 *   $html = Blocky::renderDocument($json);
 *   Blocky::registerTheme($variant);
 */
final class Blocky
{
    /**
     * Register a custom block definition.
     */
    public static function registerBlock(BlockDefinition $definition): void
    {
        Plugin::getInstance()->getRegistry()->register($definition);
    }

    /**
     * Render a Blocky document JSON string to HTML.
     */
    public static function renderDocument(string $json): HtmlString
    {
        // Lazy-create the pipeline using the bootstrapped plugin instance
        $plugin   = Plugin::getInstance();
        $registry = $plugin->getRegistry();
        $engine   = $plugin->getThemeEngine();

        $pipeline = new \Blocky\Core\Blocks\Renderer\Pipeline($registry, $engine);
        return $pipeline->renderDocument($json);
    }

    /**
     * Render a single node.
     *
     * @param array<string, mixed>    $props
     * @param array<string, string[]> $slots
     * @param array<string, string>   $variants
     */
    public static function renderBlock(
        string $type,
        array $props    = [],
        array $slots    = [],
        array $variants = [],
    ): HtmlString {
        $node = new Node(
            id:       'sdk-' . uniqid(),
            type:     $type,
            props:    $props,
            slots:    $slots,
            variants: $variants,
        );

        $plugin   = Plugin::getInstance();
        $registry = $plugin->getRegistry();
        $engine   = $plugin->getThemeEngine();
        $pipeline = new \Blocky\Core\Blocks\Renderer\Pipeline($registry, $engine);

        $ctx = \Blocky\Core\Support\RenderContext::make([], $engine, $pipeline);
        return $pipeline->renderNode($node, $ctx);
    }

    /**
     * Register a theme variant.
     */
    public static function registerTheme(ThemeVariant $variant): void
    {
        Plugin::getInstance()->getThemeEngine()->registerVariant($variant);
    }

    /**
     * Get the active brand slug.
     */
    public static function getActiveBrand(): string
    {
        return Plugin::getInstance()->getThemeEngine()->getActiveBrand();
    }

    /**
     * Get the active mode ("light" | "dark").
     */
    public static function getActiveMode(): string
    {
        return Plugin::getInstance()->getThemeEngine()->getActiveMode();
    }
}
