<?php
declare(strict_types=1);

namespace Blocky\Core\Rest;

defined( 'ABSPATH' ) || exit; // Protect against direct file access.

use Blocky\Core\Blocks\Registry;
use Blocky\Core\Compiler\PageCompiler;
use Blocky\Core\Tokens\ThemeEngine;

/**
 * Registers all REST controllers.
 */
final class RestRegistrar
{
    public function __construct(
        private readonly Registry    $registry,
        private readonly ThemeEngine $themeEngine,
        private readonly PageCompiler $pageCompiler,
    ) {}

    public function register(): void
    {
        (new ThemesController($this->themeEngine))->register_routes();
        (new BlocksController($this->registry))->register_routes();
        (new DocumentController($this->registry, $this->themeEngine, $this->pageCompiler))->register_routes();
        (new McpController(new DocumentController($this->registry, $this->themeEngine, $this->pageCompiler), $this->registry))->register_routes();
    }
}
