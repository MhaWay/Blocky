<?php
/**
 * @package Blocky\Core\Blocks\Renderer
 */

declare(strict_types=1);

namespace Blocky\Core\Blocks\Renderer;

defined( 'ABSPATH' ) || exit; // Protect against direct file access.

use Blocky\Core\Blocks\BlockDefinition;
use Blocky\Core\Blocks\Node;
use Blocky\Core\Blocks\Registry;
use Blocky\Core\Support\RenderContext;
use Blocky\Core\Support\HtmlString;
use Blocky\Core\Tokens\ThemeEngine;

/**
 * Traverses a node tree and renders it to HTML.
 */
final class Pipeline
{
    public function __construct(
        private readonly Registry    $registry,
        private readonly ThemeEngine $themeEngine,
    ) {}

    /**
     * Render a root node and its entire subtree.
     *
     * @param array<string, Node> $nodeMap  Full document node map for slot resolution
     */
    public function render(Node $rootNode, array $nodeMap, bool $editorMode = false, int $currentPostId = 0): HtmlString
    {
        $ctx = RenderContext::make(
            nodeMap:     $nodeMap,
            themeEngine: $this->themeEngine,
            pipeline:    $this,
            editorMode:  $editorMode,
            currentPostId: $currentPostId,
        );

        return $this->renderNode($rootNode, $ctx);
    }

    /**
     * Render a single node.
     */
    public function renderNode(Node $node, RenderContext $ctx): HtmlString
    {
        $definition = $this->registry->get($node->type);

        if ($definition === null) {
            // Unknown block — render a comment placeholder in debug, nothing in prod
            if (defined('WP_DEBUG') && WP_DEBUG) {
                return HtmlString::of(
                    '<!-- [Blocky] Unknown block type: ' . \esc_html($node->type) . ' -->'
                );
            }
            return HtmlString::of('');
        }

        return $definition->render($node, $ctx);
    }

    /**
     * Render all nodes in a slot.
     *
     * @param string[] $nodeIds
     */
    public function renderSlot(array $nodeIds, RenderContext $ctx): HtmlString
    {
        $parts = [];
        foreach ($nodeIds as $id) {
            $node = $ctx->getNode($id);
            if ($node !== null) {
                $parts[] = $this->renderNode($node, $ctx)->toString();
            }
        }
        return HtmlString::of(implode('', $parts));
    }

    /**
     * Render a Blocky document (JSON string) to HTML.
     */
    public function renderDocument(string $json, bool $editorMode = false, int $currentPostId = 0): HtmlString
    {
        /** @var array{root: string, nodes: array<string, array<string, mixed>>}|null $doc */
        $doc = json_decode($json, true);

        if (!is_array($doc) || !isset($doc['root'], $doc['nodes'])) {
            return HtmlString::of('<!-- [Blocky] Invalid document JSON -->');
        }

        $nodeMap = [];
        foreach ($doc['nodes'] as $id => $data) {
            $nodeMap[$id] = Node::fromArray($data);
        }

        $root = $nodeMap[$doc['root']] ?? null;
        if ($root === null) {
            return HtmlString::of('<!-- [Blocky] Root node not found -->');
        }

        return $this->render($root, $nodeMap, $editorMode, $currentPostId);
    }

    /**
     * Compatibility render from Gutenberg block attributes.
     *
     * @param array<string, mixed> $attrs
     */
    public static function renderFromGutenberg(
        BlockDefinition $definition,
        array $attrs,
        string $content
    ): string {
        // Create a transient node from Gutenberg attrs
        $node = new Node(
            id:       'gutenberg-' . uniqid(),
            type:     $definition->type,
            props:    $attrs,
            slots:    ['default' => []],
            variants: [],
        );

        // Minimal context
        $ctx = RenderContext::makeMinimal();

        return $definition->render($node, $ctx)->toString();
    }
}
