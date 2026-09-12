<?php
/**
 * @package Blocky\Core\Blocks\Renderer
 */

declare(strict_types=1);

namespace Blocky\Core\Blocks\Renderer;

use Blocky\Core\Blocks\Node;
use Blocky\Core\Support\RenderContext;
use Blocky\Core\Support\HtmlString;

interface BlockRendererInterface
{
    public function render(Node $node, RenderContext $ctx): HtmlString;
}
