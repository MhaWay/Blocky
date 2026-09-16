<?php

declare(strict_types=1);

namespace Blocky\Builder;

defined( 'ABSPATH' ) || exit; // Protect against direct file access.

/**
 * Registers the Blocky builder admin page and loads the Preact SPA.
 */
final class BuilderScreen
{
    private static ?self $instance = null;

    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    private function __construct() {}

    public function boot(): void
    {
        \add_action('admin_menu',               [$this, 'registerAdminPage']);
        \add_action('admin_enqueue_scripts',    [$this, 'enqueueBuilderAssets']);
        \add_action('admin_print_styles',       [$this, 'dequeueAdminStyles'], 1000);
        \add_filter('admin_body_class',         [$this, 'addBodyClass']);
    }

    public function registerAdminPage(): void
    {
        \add_menu_page(
            \__('GG-Ally Page Builder', 'blocky'),
            \__('GG-Builder', 'blocky'),
            'edit_posts',
            'blocky-builder',
            [$this, 'renderBuilderPage'],
            'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M3 3h8v8H3zm10 0h8v8h-8zM3 13h8v8H3zm10 0h8v8h-8z"/></svg>'),
            58
        );
    }

    public function renderBuilderPage(): void
    {
        $config = \wp_json_encode([
            'restUrl'            => \esc_url_raw(\rest_url('blocky/v1/')),
            'nonce'              => \wp_create_nonce('wp_rest'),
            'assetUrl'           => '',
            'postTypes'          => $this->getSupportedPostTypes(),
            'compilerCssFragments' => $this->getCompilerCssFragments(),
            // Intl tag: JS Intl throws RangeError on PHP-style tags.
                    'locale'             => str_replace('_', '-', \function_exists('get_user_locale') ? \get_user_locale() : \get_locale()),
            'i18n'               => $this->getI18nStrings(),
        ]);
        // Config via the classic-script shim: inline output through the enqueue
        // API, no hand-printed <script> tag. Classic footer scripts are
        // guaranteed to execute before deferred ES modules.
        \wp_register_script('blocky-builder-config', false, [], \BLOCKY_BUILDER_VERSION, true);
        \wp_add_inline_script(
            'blocky-builder-config',
            'window.BlockyBuilderConfig = ' . $config . ';',
            'before'
        );
        \wp_enqueue_script('blocky-builder-config');
        echo '<div id="blocky-builder-root" class="blocky-builder-fullscreen"></div>';
    }

    public function enqueueBuilderAssets(string $hookSuffix): void
    {
        if (!str_contains($hookSuffix, 'blocky-builder')) {
            return;
        }

        // Enqueue the WP media library so wp.media() is available in the builder.
        \wp_enqueue_media();

        $buildDir = BLOCKY_BUILDER_DIR . 'dist/';
        $buildUrl = \rtrim(BLOCKY_BUILDER_URL, '/') . '/dist';
        $isDev    = $this->shouldUseDevAssets($buildDir);
        $assetUrl = $isDev ? $this->builderDevAssetUrl() : $buildUrl;

        if ($isDev) {
            // Vite dev server
            \wp_enqueue_script_module(
                'blocky-builder-vite',
                "{$assetUrl}/@vite/client",
                [],
                null,
            );
            \wp_enqueue_script_module(
                'blocky-builder',
                "{$assetUrl}/src/main.tsx",
                ['blocky-builder-vite'],
                null,
            );
        } else {
            $manifest = $this->readManifest($buildDir . '.vite/manifest.json', $assetUrl);
            $entry    = $manifest['src/main.tsx'] ?? null;

            if ($entry !== null) {
                foreach ($entry['css'] ?? [] as $i => $cssFile) {
                    \wp_enqueue_style("blocky-builder-css-{$i}", $assetUrl . '/' . $cssFile, [], \BLOCKY_CORE_VERSION);
                }
                \wp_enqueue_script_module('blocky-builder', $assetUrl . '/' . $entry['file'], [], null);
            }
        }
    }

    public function addBodyClass(string $classes): string
    {
        if ($this->isBuilderScreen()) {
            $classes .= ' blocky-builder-fullscreen';
        }
        return $classes;
    }

    public function dequeueAdminStyles(): void
    {
        if (!$this->isBuilderScreen()) {
            return;
        }

        $styles = \wp_styles();
        if (!$styles instanceof \WP_Styles) {
            return;
        }

        foreach (array_keys($styles->registered) as $handle) {
            if ($this->shouldKeepStyleHandle($handle)) {
                continue;
            }

            \wp_dequeue_style($handle);
            \wp_deregister_style($handle);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function readManifest(string $path, string $baseUrl): array
    {
        if (!file_exists($path)) {
            return [];
        }
        $raw = file_get_contents($path);
        return is_string($raw) ? (json_decode($raw, true) ?? []) : [];
    }

    /**
     * @return string[]
     */
    private function getSupportedPostTypes(): array
    {
        /** @var string[] */
        $types = \get_post_types(['public' => true], 'names');
        return array_values(array_filter($types, fn($t) => $t !== 'attachment'));
    }

    /**
     * @return string[]
     */
    private function getCompilerCssFragments(): array
    {
        $fragments = \apply_filters('blocky/builder/compiler_css_fragments', []);

        if (!is_array($fragments)) {
            return [];
        }

        return array_values(array_filter($fragments, static fn($fragment): bool => is_string($fragment) && $fragment !== ''));
    }

    /**
     * @return array<string, string>
     */
    private function getI18nStrings(): array
    {
        return [
            'toolbar.backToWordPress' => __('Back to WordPress', 'blocky'),
            'toolbar.allPages' => __('All Pages', 'blocky'),
            'toolbar.selectPageOrCreateLayout' => __('Select a page or create a new layout.', 'blocky'),
            'toolbar.unsavedChanges' => __('Unsaved changes', 'blocky'),
            'toolbar.view' => __('View', 'blocky'),
            'toolbar.classicEditor' => __('Classic Editor', 'blocky'),
            'toolbar.publish' => __('Publish', 'blocky'),
            'toolbar.save' => __('Save', 'blocky'),
            'toolbar.pageTitleAria' => __('Page title', 'blocky'),
            'toolbar.switchTheme' => __('Switch theme', 'blocky'),
            'toolbar.mode' => __('Mode', 'blocky'),
            'toolbar.modeLight' => __('Light', 'blocky'),
            'toolbar.modeDark' => __('Dark', 'blocky'),
            'toolbar.brand' => __('Brand', 'blocky'),
            'library.create' => __('Create', 'blocky'),
            'library.newLayout' => __('New Layout', 'blocky'),
            'library.newLayoutDescription' => __('Choose the type of page to generate. Each card creates a new draft page with a different starter, ready to refine in GG.', 'blocky'),
            'library.pageStarterLabel' => __('Page', 'blocky'),
            'library.pageStarterDescription' => __('A standard page to build freely.', 'blocky'),
            'library.pageStarterTitle' => __('New GG Page', 'blocky'),
            'library.landingStarterLabel' => __('Landing', 'blocky'),
            'library.landingStarterTitle' => __('Landing Page', 'blocky'),
            'library.landingStarterDescription' => __('Hero, central content, and a call to action.', 'blocky'),
            'library.baseTemplateLabel' => __('Base Template', 'blocky'),
            'library.baseTemplateTitle' => __('Base Template', 'blocky'),
            'library.baseTemplateDescription' => __('Primary template with header area, dynamic page content, and footer.', 'blocky'),
            'library.headerStarterLabel' => __('Header', 'blocky'),
            'library.headerStarterTitle' => __('Header Layout', 'blocky'),
            'library.headerStarterDescription' => __('Starter for a top bar with branding and navigation.', 'blocky'),
            'library.footerStarterLabel' => __('Footer', 'blocky'),
            'library.footerStarterTitle' => __('Footer Layout', 'blocky'),
            'library.footerStarterDescription' => __('Starter for a footer with links and notes.', 'blocky'),
            'library.menuStarterLabel' => __('Menu', 'blocky'),
            'library.menuStarterTitle' => __('Navigation Menu', 'blocky'),
            'library.menuStarterDescription' => __('Quick navigation with pre-aligned items.', 'blocky'),
            'library.sidebarStarterLabel' => __('Sidebar', 'blocky'),
            'library.sidebarStarterTitle' => __('Sidebar Layout', 'blocky'),
            'library.sidebarStarterDescription' => __('Layout with main content and a side column.', 'blocky'),
            'library.templates' => __('Templates', 'blocky'),
            'library.newTemplates' => __('New Templates', 'blocky'),
            'library.newTemplatesDescription' => __('Create reusable parts or a full base template to connect to pages through GG WordPress blocks.', 'blocky'),
            'library.allPages' => __('All Pages', 'blocky'),
            'library.searchPages' => __('Search pages', 'blocky'),
            'library.searchPagesPlaceholder' => __('Search by name or status…', 'blocky'),
            'library.loadingPages' => __('Loading pages…', 'blocky'),
            'library.noPagesFound' => __('No pages found', 'blocky'),
            'library.noPagesFoundDescription' => __('Create a new page from one of the starters above.', 'blocky'),
            'library.containsGGDocument' => __('Contains a GG document', 'blocky'),
            'library.noGGDocument' => __('WordPress page without a saved GG document', 'blocky'),
            'library.open' => __('Open', 'blocky'),
            'library.openInGG' => __('Open in GG', 'blocky'),
            'library.viewPage' => __('View Page', 'blocky'),
            'library.openClassicEditor' => __('Open Classic Editor', 'blocky'),
            'library.moveToTrash' => __('Move to Trash', 'blocky'),
            'library.contextMenuHint' => __('Right click: page actions', 'blocky'),
            /* translators: %s and %d placeholders are filled in at runtime. */
            'library.confirmTrash' => __('Move "%s" to the trash?', 'blocky'),
            'library.recentlyModified' => __('Recently updated', 'blocky'),
            /* translators: %s and %d placeholders are filled in at runtime. */
            'library.updatedOn' => __('Updated %s', 'blocky'),
            'templates.kindComponent' => __('Component', 'blocky'),
            'library.componentLabel' => __('Component', 'blocky'),
            'library.componentDescription' => __('Structure for one item of a grid, list or loop (card, product tile...).', 'blocky'),
            'library.componentTitle' => __('New Component', 'blocky'),
            'sidebar.templates' => __('Templates', 'blocky'),
            'templates.description' => __('Reusable parts and layouts. Click one to edit it.', 'blocky'),
            'templates.kindBase' => __('Base', 'blocky'),
            'templates.kindHeader' => __('Header', 'blocky'),
            'templates.kindFooter' => __('Footer', 'blocky'),
            'templates.kindMenu' => __('Menu', 'blocky'),
            'templates.kindSidebar' => __('Sidebar', 'blocky'),
            'templates.kindArticle' => __('Article', 'blocky'),
            'templates.searchPlaceholder' => __('Search templates…', 'blocky'),
            'templates.loading' => __('Loading templates…', 'blocky'),
            'templates.empty' => __('No templates yet', 'blocky'),
            'templates.emptyDescription' => __('Create a header, footer or article template above.', 'blocky'),
            'templates.openHint' => __('Open this template in the editor', 'blocky'),
            'templates.view' => __('Preview', 'blocky'),
            'templates.trash' => __('Trash', 'blocky'),
            /* translators: %s and %d placeholders are filled in at runtime. */
            'templates.trashConfirm' => __('Move template "%s" to the trash?', 'blocky'),
            'templates.recentlyModified' => __('Recently updated', 'blocky'),
            /* translators: %s and %d placeholders are filled in at runtime. */
            'templates.updatedOn' => __('Updated %s', 'blocky'),
            'library.singlePostLabel' => __('Single Post', 'blocky'),
            'library.singlePostDescription' => __('Article template: header slot, title, content, author, and post navigation.', 'blocky'),
            'library.singlePostTitle' => __('Article Template', 'blocky'),
            'sidebar.pages' => __('Pages', 'blocky'),
            'pages.add' => __('Add Page', 'blocky'),
            'pages.searchPlaceholder' => __('Search pages…', 'blocky'),
            'pages.loading' => __('Loading pages…', 'blocky'),
            'pages.empty' => __('No pages found', 'blocky'),
            'pages.emptyDescription' => __('Use Add Page to create one.', 'blocky'),
            'pages.openHint' => __('Open this page in the editor', 'blocky'),
            'pages.builtWith' => __('Built with GG', 'blocky'),
            'pages.view' => __('View', 'blocky'),
            'pages.trash' => __('Trash', 'blocky'),
            /* translators: %s and %d placeholders are filled in at runtime. */
            'pages.trashConfirm' => __('Move "%s" to the trash?', 'blocky'),
            'pages.recentlyModified' => __('Recently updated', 'blocky'),
            /* translators: %s and %d placeholders are filled in at runtime. */
            'pages.updatedOn' => __('Updated %s', 'blocky'),
            'sidebar.blocks' => __('Blocks', 'blocky'),
            'sidebar.theme' => __('Theme', 'blocky'),
            'sidebar.searchBlocks' => __('Search blocks', 'blocky'),
            'sidebar.searchBlocksPlaceholder' => __('Search blocks…', 'blocky'),
            'sidebar.layout' => __('Layout', 'blocky'),
            'sidebar.content' => __('Content', 'blocky'),
            'sidebar.wordpress' => __('WordPress', 'blocky'),
            'sidebar.categoryBasic' => __('Basic', 'blocky'),
            'sidebar.categoryContent' => __('Content', 'blocky'),
            'sidebar.categoryMedia' => __('Media', 'blocky'),
            'sidebar.categoryAdvanced' => __('Advanced', 'blocky'),
            'sidebar.noBlocksFound' => __('No blocks found.', 'blocky'),
            'sidebar.noLayoutBlocks' => __('No layout blocks.', 'blocky'),
            'sidebar.noContentBlocks' => __('No content blocks.', 'blocky'),
            'sidebar.noWordPressBlocks' => __('No WordPress blocks available.', 'blocky'),
            'theme.light' => __('Light', 'blocky'),
            'theme.dark' => __('Dark', 'blocky'),
            'theme.searchTokens' => __('Search tokens', 'blocky'),
            'theme.searchTokensPlaceholder' => __('Search tokens…', 'blocky'),
            'theme.loadingTheme' => __('Loading theme…', 'blocky'),
            'theme.dataUnavailable' => __('Theme data unavailable.', 'blocky'),
            'theme.reset' => __('Reset', 'blocky'),
            'theme.saving' => __('Saving…', 'blocky'),
            'theme.saveTheme' => __('Save Theme', 'blocky'),
            'theme.default' => __('Default', 'blocky'),
            'canvas.noDocumentLoaded' => __('No document loaded', 'blocky'),
            'canvas.noDocumentDescription' => __('Create a new page or open an existing post.', 'blocky'),
            'canvas.newPage' => __('New Page', 'blocky'),
            'canvas.dropBlocksHere' => __('Drop blocks here', 'blocky'),
            'canvas.clickToChangeImage' => __('Click to change', 'blocky'),
            'canvas.clickToChangeVideo' => __('Click to change', 'blocky'),
            'canvas.moveLeft' => __('Move left', 'blocky'),
            'canvas.moveUp' => __('Move up', 'blocky'),
            'canvas.moveDown' => __('Move down', 'blocky'),
            'canvas.moveRight' => __('Move right', 'blocky'),
            'canvas.duplicate' => __('Duplicate', 'blocky'),
            'canvas.delete' => __('Delete', 'blocky'),
            'canvas.addHeadingInside' => __('Add heading inside', 'blocky'),
            'canvas.addTextInside' => __('Add text inside', 'blocky'),
            'canvas.addButtonInside' => __('Add button inside', 'blocky'),
            'inspector.filterPages' => __('Filter pages', 'blocky'),
            'inspector.filterPagesPlaceholder' => __('Filter by page title or status…', 'blocky'),
            'inspector.loadingPages' => __('Loading GG pages…', 'blocky'),
            'inspector.noPagesFound' => __('No pages found', 'blocky'),
            'inspector.noPagesFoundDescription' => __('Try a different filter or create a new page.', 'blocky'),
            'inspector.title' => __('Inspector', 'blocky'),
            /* translators: %s and %d placeholders are filled in at runtime. */
            'inspector.settingsTitle' => __('%s Settings', 'blocky'),
            'inspector.remove' => __('Remove', 'blocky'),
            'inspector.rootSection' => __('Root section', 'blocky'),
            'inspector.selectBlock' => __('Select a block on the canvas to edit its properties.', 'blocky'),
            'inspector.blockyShort' => __('GG-Builder', 'blocky'),
            'inspector.wordpress' => __('WordPress', 'blocky'),
            'inspector.containsReusableGGDocument' => __('Contains a reusable GG document.', 'blocky'),
            'inspector.standardWordPressPageWithoutGG' => __('Standard WordPress page without a saved GG document.', 'blocky'),
            'inspector.noPageSelected' => __('No page selected.', 'blocky'),
            'inspector.changePage' => __('Change page', 'blocky'),
            'inspector.choosePage' => __('Choose page', 'blocky'),
            'inspector.clearPage' => __('Clear', 'blocky'),
            'inspector.loadingGGPages' => __('Loading GG pages...', 'blocky'),
            'inspector.pagesListedHint' => __('All pages are listed. GG pages are marked with a badge.', 'blocky'),
            'inspector.noPagesAvailableYet' => __('No pages available yet.', 'blocky'),
            'inspector.templatePart' => __('Template Part', 'blocky'),
            'inspector.selectPage' => __('Select a page', 'blocky'),
            'inspector.closePicker' => __('Close picker', 'blocky'),
            'inspector.current' => __('Current', 'blocky'),
            'inspector.wpShort' => __('WP', 'blocky'),
            'inspector.currentPageUnavailable' => __('Current page cannot be inserted into itself', 'blocky'),
            'inspector.containsGGDocument' => __('Contains a GG document', 'blocky'),
            'inspector.standardWordPressPage' => __('Standard WordPress page', 'blocky'),
            'inspector.unavailable' => __('Unavailable', 'blocky'),
            'inspector.selected' => __('Selected', 'blocky'),
            'inspector.useThis' => __('Use this', 'blocky'),
            'inspector.recentlyUpdated' => __('Recently updated', 'blocky'),
            /* translators: %s and %d placeholders are filled in at runtime. */
            'inspector.updatedOn' => __('Updated %s', 'blocky'),
            'inspector.mediaLibraryUnavailable' => __('WordPress media library not available.', 'blocky'),
            'inspector.selectMedia' => __('Select Media', 'blocky'),
            'inspector.select' => __('Select', 'blocky'),
            'inspector.noMediaSelected' => __('No media selected', 'blocky'),
            'inspector.change' => __('Change', 'blocky'),
            'inspector.altText' => __('Alt Text', 'blocky'),
            'inspector.altSuggestion' => __('Describe the image', 'blocky'),
            'inspector.imageSize' => __('Image Size', 'blocky'),
            'inspector.objectFit' => __('Object Fit', 'blocky'),
            'inspector.loading' => __('Loading', 'blocky'),
            'inspector.decoding' => __('Decoding', 'blocky'),
            'inspector.focalPoint' => __('Focal Point', 'blocky'),
            'inspector.breakpointScope' => __('Breakpoint scope', 'blocky'),
            'inspector.variantPrefix' => __('Variant prefix', 'blocky'),
            'inspector.contentTab' => __('Content', 'blocky'),
            'inspector.layoutTab' => __('Layout', 'blocky'),
            'inspector.styleTab' => __('Style', 'blocky'),
            'inspector.advancedTab' => __('Advanced', 'blocky'),
            'inspector.customChain' => __('Custom chain', 'blocky'),
            'inspector.customVariantChain' => __('Custom variant chain', 'blocky'),
            'inspector.customVariantPlaceholder' => __('md:hover', 'blocky'),
            'inspector.forceOverride' => __('Force override', 'blocky'),
            'inspector.animationsTab' => __('Animations', 'blocky'),
            'inspector.classesTab' => __('Classes', 'blocky'),
            'inspector.backgroundTransparent' => __('Transparent', 'blocky'),
            'inspector.backgroundCustom' => __('Custom', 'blocky'),
            'inspector.backgroundGradient' => __('Gradient', 'blocky'),
            'inspector.backgroundImage' => __('Image', 'blocky'),
            'inspector.motionSet' => __('Motion Set', 'blocky'),
            'inspector.entranceTitle' => __('Entrance Animation', 'blocky'),
            'inspector.entranceDescription' => __('Closed-set entrance animations. Honors prefers-reduced-motion and no-JS.', 'blocky'),
            'inspector.entrancePreset' => __('Preset', 'blocky'),
            'inspector.entranceNone' => __('None', 'blocky'),
            'inspector.entranceTrigger' => __('Trigger', 'blocky'),
            'inspector.entranceTriggerScroll' => __('On Scroll', 'blocky'),
            'inspector.entranceTriggerLoad' => __('On Load', 'blocky'),
            'inspector.entranceSpeed' => __('Speed', 'blocky'),
            'inspector.entranceSpeedFast' => __('Fast', 'blocky'),
            'inspector.entranceSpeedNormal' => __('Normal', 'blocky'),
            'inspector.entranceSpeedSlow' => __('Slow', 'blocky'),
            'inspector.entranceDelay' => __('Delay', 'blocky'),
            'inspector.entranceDelayNone' => __('No delay', 'blocky'),
            'inspector.entranceRepeat' => __('Replay on re-entry', 'blocky'),
            'inspector.clear' => __('Clear', 'blocky'),
            'inspector.transition' => __('Transition', 'blocky'),
            'inspector.duration' => __('Duration', 'blocky'),
            'inspector.delay' => __('Delay', 'blocky'),
            'inspector.easing' => __('Easing', 'blocky'),
            'inspector.guard' => __('Guard', 'blocky'),
            'inspector.hoverScale' => __('Hover Scale', 'blocky'),
            'inspector.hoverOpacity' => __('Hover Opacity', 'blocky'),
            'inspector.applyMotionSet' => __('Apply Motion Set', 'blocky'),
            'inspector.interactionsTitle' => __('Interactions', 'blocky'),
            'inspector.interactionsDescription' => __('Trigger, action, target, and basic modifiers for runtime behaviors.', 'blocky'),
            /* translators: %s and %d placeholders are filled in at runtime. */
            'inspector.interactionRule' => __('Rule %s', 'blocky'),
            'inspector.interactionEvent' => __('Event', 'blocky'),
            'inspector.interactionAction' => __('Action', 'blocky'),
            'inspector.interactionTarget' => __('Target', 'blocky'),
            'inspector.interactionTargetEventPlaceholder' => __('blocky:event-name', 'blocky'),
            'inspector.interactionTargetSelectorPlaceholder' => __('#overlay-id or .selector', 'blocky'),
            'inspector.interactionClassName' => __('Class Name', 'blocky'),
            'inspector.interactionClassPlaceholder' => __('is-open', 'blocky'),
            'inspector.interactionDelay' => __('Delay (ms)', 'blocky'),
            'inspector.interactionOnce' => __('Run Once', 'blocky'),
            'inspector.addInteraction' => __('Add Interaction', 'blocky'),
            'inspector.interactionEventClick' => __('Click', 'blocky'),
            'inspector.interactionEventHover' => __('Hover', 'blocky'),
            'inspector.interactionEventFocus' => __('Focus', 'blocky'),
            'inspector.interactionEventLoad' => __('Load', 'blocky'),
            'inspector.interactionConditions' => __('Conditions', 'blocky'),
            'inspector.interactionModifiers' => __('Modifiers', 'blocky'),
            'inspector.interactionDevice' => __('Device', 'blocky'),
            'inspector.interactionDevice.any' => __('Any Device', 'blocky'),
            'inspector.interactionDevice.desktop' => __('Desktop', 'blocky'),
            'inspector.interactionDevice.tablet' => __('Tablet', 'blocky'),
            'inspector.interactionDevice.mobile' => __('Mobile', 'blocky'),
            'inspector.interactionLoginState' => __('Login State', 'blocky'),
            'inspector.interactionLoginState.any' => __('Any State', 'blocky'),
            'inspector.interactionLoginState.logged-in' => __('Logged In', 'blocky'),
            'inspector.interactionLoginState.logged-out' => __('Logged Out', 'blocky'),
            'inspector.interactionQueryKey' => __('Query Param', 'blocky'),
            'inspector.interactionQueryKeyPlaceholder' => __('utm_campaign', 'blocky'),
            'inspector.interactionQueryValue' => __('Query Value', 'blocky'),
            'inspector.interactionQueryValuePlaceholder' => __('spring-sale', 'blocky'),
            'inspector.interactionCookieKey' => __('Cookie', 'blocky'),
            'inspector.interactionCookieKeyPlaceholder' => __('promo_seen', 'blocky'),
            'inspector.interactionCookieValue' => __('Cookie Value', 'blocky'),
            'inspector.interactionCookieValuePlaceholder' => __('1', 'blocky'),
            'inspector.interactionActionOverlayOpen' => __('Overlay Open', 'blocky'),
            'inspector.interactionActionOverlayClose' => __('Overlay Close', 'blocky'),
            'inspector.interactionActionOverlayToggle' => __('Overlay Toggle', 'blocky'),
            'inspector.interactionActionClassAdd' => __('Class Add', 'blocky'),
            'inspector.interactionActionClassRemove' => __('Class Remove', 'blocky'),
            'inspector.interactionActionClassToggle' => __('Class Toggle', 'blocky'),
            'inspector.interactionActionCustomEmit' => __('Custom Event', 'blocky'),
            'inspector.interactionDebounce' => __('Debounce (ms)', 'blocky'),
            'inspector.interactionThrottle' => __('Throttle (ms)', 'blocky'),
            'inspector.interactionPreventDefault' => __('Prevent Default', 'blocky'),
            'inspector.interactionStopPropagation' => __('Stop Propagation', 'blocky'),
            'inspector.hexColorPicker' => __('Hex color picker', 'blocky'),
            'inspector.hexColorPlaceholder' => __('#0ea5e9', 'blocky'),
            'inspector.add' => __('Add', 'blocky'),
            'inspector.searchUtilities' => __('Search utilities', 'blocky'),
            'inspector.searchUtilitiesPlaceholder' => __('padding, grid, bg, hover...', 'blocky'),
            'inspector.activeClasses' => __('Active classes', 'blocky'),
            'inspector.noTailwindClasses' => __('No Tailwind utility classes on this block.', 'blocky'),
            'inspector.removeClass' => __('Remove class', 'blocky'),
            'inspector.manualClass' => __('Manual class', 'blocky'),
            'inspector.manualClassPlaceholder' => __('w-[42rem] md:hover:bg-accent-subtle', 'blocky'),
            'inspector.addClass' => __('Add class', 'blocky'),
            'outline.close' => __('Close outline', 'blocky'),
            'outline.title' => __('Page Outline', 'blocky'),
            'outline.noDocument' => __('No document loaded.', 'blocky'),
            'outline.documentUnavailable' => __('Document tree unavailable.', 'blocky'),
            'outline.expandChildren' => __('Expand children', 'blocky'),
            'outline.collapseChildren' => __('Collapse children', 'blocky'),
            'footer.toggleBlocksPanel' => __('Toggle Blocks panel', 'blocky'),
            'footer.toggleInspectorPanel' => __('Toggle Inspector panel', 'blocky'),
            /* translators: %s and %d placeholders are filled in at runtime. */
            'footer.blocksCount' => __('%s blocks', 'blocky'),
            /* translators: %s and %d placeholders are filled in at runtime. */
            'footer.blockCount' => __('%s block', 'blocky'),
            'footer.desktop' => __('Desktop', 'blocky'),
            'footer.tablet' => __('Tablet', 'blocky'),
            'footer.mobile' => __('Mobile', 'blocky'),
            'footer.toggleOutline' => __('Toggle outline', 'blocky'),
            'footer.outline' => __('Outline', 'blocky'),
            'footer.togglePreviewTheme' => __('Toggle preview theme', 'blocky'),
            'footer.previewDark' => __('Preview Dark', 'blocky'),
            'footer.previewLight' => __('Preview Light', 'blocky'),
            'footer.saved' => __('Saved', 'blocky'),
            'footer.noDocument' => __('No document', 'blocky'),
            'starter.menuHome' => __('Home', 'blocky'),
            'starter.menuProducts' => __('Products', 'blocky'),
            'starter.menuBlog' => __('Blog', 'blocky'),
            'starter.menuSupport' => __('Support', 'blocky'),
            'starter.mainContent' => __('Main content', 'blocky'),
            'starter.mainContentDescription' => __('Use this column for the main content of the page.', 'blocky'),
            'starter.keyPoints' => __('Key point one\nKey point two\nKey point three', 'blocky'),
            'starter.sidebar' => __('Sidebar', 'blocky'),
            'starter.sidebarDescription' => __('Add CTAs, quick links, or secondary information here.', 'blocky'),
            'starter.callToAction' => __('Call to action', 'blocky'),
            'starter.newPage' => __('New page', 'blocky'),
            'starter.newPageDescription' => __('Start here: add sections, content, and layout to build the page.', 'blocky'),
            'document.embeddedVideo' => __('Embedded video', 'blocky'),
            'document.toggleTheme' => __('Toggle Theme', 'blocky'),
        ];
    }

    private function builderDevAssetUrl(): string
    {
        $devHost = getenv('BLOCKY_DEV_HOST') ?: '192.168.191.242';

        if (defined('BLOCKY_BUILDER_ASSET_URL')) {
            return \rtrim(BLOCKY_BUILDER_ASSET_URL, '/');
        }

        if (defined('BLOCKY_ASSET_URL')) {
            return \rtrim(BLOCKY_ASSET_URL, '/');
        }

        $envUrl = getenv('BLOCKY_BUILDER_DEV_URL');
        if (is_string($envUrl) && $envUrl !== '') {
            return \rtrim($envUrl, '/');
        }

        return 'http://' . $devHost . ':5174';
    }

    private function shouldUseDevAssets(string $buildDir): bool
    {
        if (!(defined('BLOCKY_DEV') && BLOCKY_DEV)) {
            return false;
        }

        return !$this->hasBuiltAssets($buildDir);
    }

    private function hasBuiltAssets(string $buildDir): bool
    {
        return file_exists($buildDir . '.vite/manifest.json');
    }

    private function isBuilderScreen(): bool
    {
        $screen = \get_current_screen();
        return $screen instanceof \WP_Screen && str_contains($screen->id, 'blocky-builder');
    }

    private function shouldKeepStyleHandle(string $handle): bool
    {
        if (str_starts_with($handle, 'blocky-builder')) {
            return true;
        }

        return in_array($handle, [
            'dashicons',
            'imgareaselect',
            'media-views',
            'buttons',
            'wp-auth-check',
        ], true);
    }
}
