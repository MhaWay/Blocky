<?php
/**
 * @package Blocky\Core\Blocks
 */

declare(strict_types=1);

namespace Blocky\Core\Blocks;

defined( 'ABSPATH' ) || exit; // Protect against direct file access.

use Blocky\Core\Blocks\Renderer\BlockRendererInterface;
use Blocky\Core\Blocks\Renderers\HeadingRenderer;
use Blocky\Core\Blocks\Renderers\SectionRenderer;
use Blocky\Core\Blocks\Renderers\TextRenderer;
use Blocky\Core\Blocks\Renderers\ButtonRenderer;
use Blocky\Core\Blocks\Renderers\ImageRenderer;
use Blocky\Core\Blocks\Renderers\GridRenderer;
use Blocky\Core\Blocks\Renderers\RowsRenderer;
use Blocky\Core\Blocks\Renderers\ContainerRenderer;
use Blocky\Core\Blocks\Renderers\ColumnsRenderer;
use Blocky\Core\Blocks\Renderers\CardRenderer;
use Blocky\Core\Blocks\Renderers\DataFieldRenderer;
use Blocky\Core\Support\DataFields;
use Blocky\Core\Blocks\Renderers\DividerRenderer;
use Blocky\Core\Blocks\Renderers\SpacerRenderer;
use Blocky\Core\Blocks\Renderers\ListRenderer;
use Blocky\Core\Blocks\Renderers\IconRenderer;
use Blocky\Core\Blocks\Renderers\IconBoxRenderer;
use Blocky\Core\Blocks\Renderers\IconListRenderer;
use Blocky\Core\Blocks\Renderers\ImageBoxRenderer;
use Blocky\Core\Blocks\Renderers\AlertRenderer;
use Blocky\Core\Blocks\Renderers\ProgressBarRenderer;
use Blocky\Core\Blocks\Renderers\CounterRenderer;
use Blocky\Core\Blocks\Renderers\StarRatingRenderer;
use Blocky\Core\Blocks\Renderers\AnchorRenderer;
use Blocky\Core\Blocks\Renderers\SocialIconsRenderer;
use Blocky\Core\Blocks\Renderers\ShareButtonsRenderer;
use Blocky\Core\Blocks\Renderers\SearchFormRenderer;
use Blocky\Core\Blocks\Renderers\NavMenuRenderer;
use Blocky\Core\Blocks\Renderers\BreadcrumbsRenderer;
use Blocky\Core\Blocks\Renderers\PostsListRenderer;
use Blocky\Core\Blocks\Renderers\PostsGridRenderer;
use Blocky\Core\Blocks\Renderers\FeaturedPostsRenderer;
use Blocky\Core\Blocks\Renderers\TaxonomyListRenderer;
use Blocky\Core\Blocks\Renderers\ArchivePostsRenderer;
use Blocky\Core\Blocks\Renderers\PaginationRenderer;
use Blocky\Core\Blocks\Renderers\ImageGalleryRenderer;
use Blocky\Core\Blocks\Renderers\BasicGalleryRenderer;
use Blocky\Core\Blocks\Renderers\ImageCarouselRenderer;
use Blocky\Core\Blocks\Renderers\ContentCarouselRenderer;
use Blocky\Core\Blocks\Renderers\CallToActionRenderer;
use Blocky\Core\Blocks\Renderers\TestimonialRenderer;
use Blocky\Core\Blocks\Renderers\PricingRenderer;
use Blocky\Core\Blocks\Renderers\FlipBoxRenderer;
use Blocky\Core\Blocks\Renderers\HotspotRenderer;
use Blocky\Core\Blocks\Renderers\BeforeAfterRenderer;
use Blocky\Core\Blocks\Renderers\CountdownRenderer;
use Blocky\Core\Blocks\Renderers\AnimatedHeadlineRenderer;
use Blocky\Core\Blocks\Renderers\MarqueeRenderer;
use Blocky\Core\Blocks\Renderers\LottieRenderer;
use Blocky\Core\Blocks\Renderers\EmbedGoogleMapsRenderer;
use Blocky\Core\Blocks\Renderers\EmbedIframeRenderer;
use Blocky\Core\Blocks\Renderers\CodeHighlightRenderer;
use Blocky\Core\Blocks\Renderers\MegaMenuRenderer;
use Blocky\Core\Blocks\Renderers\LoginFormRenderer;
use Blocky\Core\Blocks\Renderers\RegisterFormRenderer;
use Blocky\Core\Blocks\Renderers\ContactFormRenderer;
use Blocky\Core\Blocks\Renderers\AuthorBoxRenderer;
use Blocky\Core\Blocks\Renderers\CommentsRenderer;
use Blocky\Core\Blocks\Renderers\CommentFormRenderer;
use Blocky\Core\Blocks\Renderers\PostNavigationRenderer;
use Blocky\Core\Blocks\Renderers\LightboxRenderer;
use Blocky\Core\Blocks\Renderers\ScrollProgressRenderer;
use Blocky\Core\Blocks\Renderers\SitemapRenderer;
use Blocky\Core\Blocks\Renderers\StickyBarRenderer;
use Blocky\Core\Blocks\Renderers\TableOfContentsRenderer;
use Blocky\Core\Blocks\Renderers\BackToTopRenderer;
use Blocky\Core\Blocks\Renderers\TabsRenderer;
use Blocky\Core\Blocks\Renderers\AccordionRenderer;
use Blocky\Core\Blocks\Renderers\ToggleRenderer;
use Blocky\Core\Blocks\Renderers\FormRenderer;
use Blocky\Core\Blocks\Renderers\FormFieldRenderer;
use Blocky\Core\Blocks\Renderers\FormSubmitRenderer;
use Blocky\Core\Blocks\Renderers\OverlayRenderer;
use Blocky\Core\Blocks\Renderers\ModalTriggerRenderer;
use Blocky\Core\Blocks\Renderers\QuoteRenderer;
use Blocky\Core\Blocks\Renderers\VideoRenderer;
use Blocky\Core\Blocks\Renderers\HtmlRenderer;
use Blocky\Core\Blocks\Renderers\WpPostTitleRenderer;
use Blocky\Core\Blocks\Renderers\WpPostContentRenderer;
use Blocky\Core\Blocks\Renderers\WpFeaturedImageRenderer;
use Blocky\Core\Blocks\Renderers\WpShortcodeRenderer;
use Blocky\Core\Blocks\Renderers\WpHookRenderer;
use Blocky\Core\Blocks\Renderers\WpTemplatePartRenderer;
use Blocky\Core\Blocks\Renderers\ThemeToggleRenderer;

/**
 * Block type registry.
 *
 * All custom block types (bky/*) are registered here.
 * Third-party blocks register via the blocky/register_blocks action.
 */
final class Registry
{
    /** @var array<string, BlockDefinition> */
    private array $definitions = [];

    /**
     * Register a block definition.
     */
    public function register(BlockDefinition $definition): void
    {
        $this->definitions[$definition->type] = self::withTailwindChannel($definition);
    }

    /**
     * Every block gets the visual Tailwind channel (Classes tab) in its
     * schema so the parser never strips twClasses / twColorVars / twStyleVars.
     */
    private static function withTailwindChannel(BlockDefinition $definition): BlockDefinition
    {
        $schema = $definition->schema;
        if (isset($schema['properties']['twClasses'])) {
            return $definition;
        }
        $schema['properties'] = array_merge((array) ($schema['properties'] ?? []), [
            'twClasses'    => ['type' => 'array', 'default' => []],
            'twColorVars'  => ['type' => 'array', 'default' => []],
            'twStyleVars'  => ['type' => 'array', 'default' => []],
        ]);
        return new BlockDefinition(
            $definition->type,
            $schema,
            $definition->variants,
            $definition->renderer,
            $definition->editorConfig,
            $definition->interactive,
            $definition->cssHandles,
            $definition->jsHandles,
            $definition->label,
            $definition->category,
            $definition->description,
            $definition->keywords,
            $definition->icon
        );
    }

    /**
     * Get a block definition by type.
     */
    public function get(string $type): ?BlockDefinition
    {
        return $this->definitions[$type] ?? null;
    }

    /**
     * Check if a type is registered.
     */
    public function has(string $type): bool
    {
        return isset($this->definitions[$type]);
    }

    /**
     * Get all registered definitions.
     *
     * @return array<string, BlockDefinition>
     */
    public function all(): array
    {
        return $this->definitions;
    }

    /**
     * Register core Blocky blocks.
     */
    public function registerCoreBlocks(): void
    {
        $this->register(self::makeHeading());
        $this->register(self::makeSection());
        $this->register(self::makeRows());
        $this->register(self::makeText());
        $this->register(self::makeButton());
        $this->register(self::makeImage());
        $this->register(self::makeGrid());
        $this->register(self::makeContainer());
        $this->register(self::makeColumns());
        $this->register(self::makeCard());
        $this->register(self::makeDivider());
        $this->register(self::makeSpacer());
        $this->register(self::makeList());
        $this->register(self::makeIcon());
        $this->register(self::makeIconBox());
        $this->register(self::makeIconList());
        $this->register(self::makeImageBox());
        $this->register(self::makeAlert());
        $this->register(self::makeProgressBar());
        $this->register(self::makeCounter());
        $this->register(self::makeStarRating());
        $this->register(self::makeAnchor());
        $this->register(self::makeSocialIcons());
        $this->register(self::makeShareButtons());
        $this->register(self::makeSearchForm());
        $this->register(self::makeNavMenu());
        $this->register(self::makeBreadcrumbs());
        $this->register(self::makePostsList());
        $this->register(self::makePostsGrid());
        $this->register(self::makeFeaturedPosts());
        $this->register(self::makeTaxonomyList());
        $this->register(self::makeArchivePosts());
        $this->register(self::makePagination());
        $this->register(self::makeImageGallery());
        $this->register(self::makeBasicGallery());
        $this->register(self::makeImageCarousel());
        $this->register(self::makeContentCarousel());
        $this->register(self::makeSlider());
        $this->register(self::makeCallToAction());
        $this->register(self::makeTestimonial());
        $this->register(self::makePriceTable());
        $this->register(self::makePriceList());
        $this->register(self::makeFlipBox());
        $this->register(self::makeHotspot());
        $this->register(self::makeBeforeAfterSlider());
        $this->register(self::makeCountdown());
        $this->register(self::makeAnimatedHeadline());
        $this->register(self::makeMarquee());
        $this->register(self::makeLottie());
        $this->register(self::makeEmbedGoogleMaps());
        $this->register(self::makeEmbedIframe());
        $this->register(self::makeCodeHighlight());
        $this->register(self::makeMegaMenu());
        $this->register(self::makeLoginForm());
        $this->register(self::makeRegisterForm());
        $this->register(self::makeContactForm());
        $this->register(self::makeAuthorBox());
        $this->register(self::makeComments());
        $this->register(self::makeCommentForm());
        $this->register(self::makePostNavigation());
        $this->register(self::makeSitemap());
        $this->register(self::makeTableOfContents());
        $this->register(self::makeScrollProgress());
        $this->register(self::makeStickyBar());
        $this->register(self::makeBackToTop());
        $this->register(self::makePopup());
        $this->register(self::makeNotificationToast());
        $this->register(self::makeCookieBanner());
        $this->register(self::makeLightbox());
        $this->register(self::makeCommandPalette());
        $this->register(self::makeTabs());
        $this->register(self::makeAccordion());
        $this->register(self::makeToggle());
        $this->register(self::makeForm());
        $this->register(self::makeFormFieldText());
        $this->register(self::makeFormFieldTextarea());
        $this->register(self::makeFormFieldSelect());
        $this->register(self::makeFormFieldRadio());
        $this->register(self::makeFormFieldCheckbox());
        $this->register(self::makeFormFieldDate());
        $this->register(self::makeFormFieldFile());
        $this->register(self::makeFormFieldHidden());
        $this->register(self::makeFormFieldHoneypot());
        $this->register(self::makeFormSubmit());
        $this->register(self::makeModal());
        $this->register(self::makeOffcanvas());
        $this->register(self::makeDrawer());
        $this->register(self::makeModalTrigger());
        $this->register(self::makePopover());
        $this->register(self::makeTooltip());
        $this->register(self::makeDialogConfirm());
        $this->register(self::makeQuote());
        $this->register(self::makeVideo());
        $this->register(self::makeHtml());
        $this->register(self::makeWpPostTitle());
        $this->register(self::makeWpPostContent());
        $this->register(self::makeWpFeaturedImage());
        $this->register(self::makeWpTemplatePart());
        $this->register(self::makeWpShortcode());
        $this->register(self::makeDataField());
        $this->register(self::makeWpHook());
        $this->register(self::makeThemeToggle());
    }

    /**
     * Register blocks with WordPress block API (for Gutenberg compatibility).
     */
    public function registerBlockTypes(): void
    {
        $registryClass = '\\WP_Block_Type_Registry';

        foreach ($this->definitions as $type => $definition) {
            if (\function_exists('register_block_type') && \class_exists($registryClass) && !$registryClass::get_instance()->is_registered($type)) {
                \register_block_type($type, [
                    'render_callback' => static function (array $attrs, string $content) use ($definition): string {
                        // Minimal Gutenberg render — forward to our pipeline
                        return \Blocky\Core\Blocks\Renderer\Pipeline::renderFromGutenberg(
                            $definition,
                            $attrs,
                            $content
                        );
                    },
                ]);
            }
        }
    }

    /**
     * @param array<string, mixed> $editorConfig
     * @return array<string, mixed>
     */
    private static function localizeEditorConfig(array $editorConfig): array
    {
        /** @var array<string, mixed> $localized */
        $localized = self::localizeEditorValue($editorConfig);

        return $localized;
    }

    private static function tr(string $text): string
    {
        return \__($text, 'blocky'); // phpcs:ignore WordPress.WP.I18n -- callers pass literal descriptor labels only; extracted via xgettext.
    }

    private static function localizeEditorValue(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        if (self::isEditorOptionPair($value)) {
            if (is_string($value[1])) {
                $value[1] = self::tr($value[1]);
            }

            return $value;
        }

        foreach ($value as $key => $item) {
            if (in_array($key, ['label', 'description', 'title', 'placeholder'], true) && is_string($item)) {
                $value[$key] = self::tr($item);
                continue;
            }

            $value[$key] = self::localizeEditorValue($item);
        }

        return $value;
    }

    /**
     * @param array<mixed> $value
     */
    private static function isEditorOptionPair(array $value): bool
    {
        return array_key_exists(0, $value) && array_key_exists(1, $value) && count($value) === 2;
    }

    // ── Block factory methods ──────────────────────────────────────────────────

    private static function makeHeading(): BlockDefinition
    {
        return new BlockDefinition(
            type: 'bky/heading',
            label: self::tr('Heading'),
            category: 'basic',
            description: self::tr('Title text with level, tone, and alignment controls.'),
            keywords: ['title', 'headline', 'h1', 'h2'],
            icon: 'H',
            schema: [
                'type'       => 'object',
                'required'   => ['text'],
                'properties' => [
                    'level' => ['type' => 'integer', 'enum' => [1,2,3,4,5,6], 'default' => 2],
                    'text'  => ['type' => 'string'],
                    'anchorId' => ['type' => 'string', 'default' => ''],
                    'tone'  => ['type' => 'string', 'enum' => ['default', 'muted', 'accent'], 'default' => 'default'],
                    'align' => ['type' => 'string', 'enum' => ['start', 'center', 'end'],      'default' => 'start'],
                ],
            ],
            variants: [
                'tone' => [
                    'default' => 'text-text-base',
                    'muted'   => 'text-text-muted',
                    'accent'  => 'text-accent-text',
                ],
                'align' => [
                    'start'  => 'text-start',
                    'center' => 'text-center',
                    'end'    => 'text-end',
                ],
            ],
            renderer: new HeadingRenderer(),
            editorConfig: self::localizeEditorConfig([
                'tabs' => [
                    ['id' => 'content', 'label' => 'Content', 'controls' => [
                        ['id' => 'level', 'type' => 'select', 'label' => 'Level', 'options' => [[1,'H1'],[2,'H2'],[3,'H3'],[4,'H4'],[5,'H5'],[6,'H6']]],
                        ['id' => 'text',  'type' => 'richtext', 'label' => 'Text'],
                        ['id' => 'anchorId', 'type' => 'text', 'label' => 'Anchor ID'],
                    ]],
                    ['id' => 'style', 'label' => 'Style', 'controls' => [
                        ['id' => 'tone',  'type' => 'variant', 'label' => 'Tone',  'variantKey' => 'tone'],
                        ['id' => 'align', 'type' => 'variant', 'label' => 'Align', 'variantKey' => 'align'],
                    ]],
                ],
            ]),
        );
    }

    private static function makeSection(): BlockDefinition
    {
        return new BlockDefinition(
            type: 'bky/section',
            label: self::tr('Section'),
            category: 'layout',
            description: self::tr('Full-width page section with spacing and background controls.'),
            keywords: ['layout', 'wrapper', 'band', 'area'],
            icon: 'S',
            schema: [
                'type'       => 'object',
                'properties' => [
                    'paddingY'    => ['type' => 'string', 'enum' => ['none', 'sm', 'base', 'lg', 'xl', '2xl'], 'default' => 'lg'],
                    'paddingX'    => ['type' => 'string', 'enum' => ['none', 'sm', 'base', 'lg', 'xl'], 'default' => 'base'],
                    'background'  => ['type' => 'string', 'enum' => ['transparent', 'surface', 'elevated', 'sunken', 'accent', 'dark'], 'default' => 'transparent'],
                    'textColor'   => ['type' => 'string', 'enum' => ['base', 'muted', 'inverse', 'accent'], 'default' => 'base'],
                    'fullWidth'   => ['type' => 'boolean', 'default' => false],
                    'contentWidth' => ['type' => 'string', 'enum' => ['narrow', 'container', 'wide', 'full'], 'default' => 'container'],
                    'minHeight'   => ['type' => 'string', 'enum' => ['auto', 'screen25', 'screen50', 'screen75', 'screen'], 'default' => 'auto'],
                    'verticalAlign' => ['type' => 'string', 'enum' => ['start', 'center', 'end'], 'default' => 'start'],
                    'horizontalAlign' => ['type' => 'string', 'enum' => ['start', 'center', 'end', 'stretch'], 'default' => 'start'],
                    'gap'         => ['type' => 'string', 'enum' => ['none', 'sm', 'base', 'lg', 'xl'], 'default' => 'base'],
                    'border'      => ['type' => 'string', 'enum' => ['none', 'subtle', 'base', 'strong', 'accent'], 'default' => 'none'],
                    'radius'      => ['type' => 'string', 'enum' => ['none', 'sm', 'base', 'lg', 'xl'], 'default' => 'none'],
                    'shadow'      => ['type' => 'string', 'enum' => ['none', 'sm', 'base', 'md', 'lg'], 'default' => 'none'],
                    'overflow'    => ['type' => 'string', 'enum' => ['visible', 'hidden'], 'default' => 'visible'],
                    'customClass' => ['type' => 'string', 'default' => ''],
                ],
            ],
            variants: [
                'paddingY' => [
                    'none' => '',
                    'sm'   => 'py-8',
                    'base' => 'py-12',
                    'lg'   => 'py-20',
                    'xl'   => 'py-24',
                    '2xl'  => 'py-32',
                ],
                'paddingX' => [
                    'none' => 'px-0',
                    'sm'   => 'px-4',
                    'base' => 'px-6',
                    'lg'   => 'px-8',
                    'xl'   => 'px-12',
                ],
                'background' => [
                    'transparent' => '',
                    'surface'     => 'bg-surface-base',
                    'elevated'    => 'bg-surface-elevated',
                    'sunken'      => 'bg-surface-sunken',
                    'accent'      => 'bg-accent-subtle',
                    'dark'        => 'bg-text-base',
                ],
                'textColor' => [
                    'base'    => 'text-text-base',
                    'muted'   => 'text-text-muted',
                    'inverse' => 'text-text-inverse',
                    'accent'  => 'text-accent-text',
                ],
                'contentWidth' => [
                    'narrow'    => 'max-w-3xl mx-auto',
                    'container' => 'container mx-auto',
                    'wide'      => 'max-w-7xl mx-auto',
                    'full'      => 'w-full',
                ],
                'minHeight' => [
                    'auto'     => '',
                    'screen25' => 'min-h-[25vh]',
                    'screen50' => 'min-h-[50vh]',
                    'screen75' => 'min-h-[75vh]',
                    'screen'   => 'min-h-screen',
                ],
                'verticalAlign' => [
                    'start'  => 'justify-start',
                    'center' => 'justify-center',
                    'end'    => 'justify-end',
                ],
                'horizontalAlign' => [
                    'start'   => 'items-start text-left',
                    'center'  => 'items-center text-center',
                    'end'     => 'items-end text-right',
                    'stretch' => 'items-stretch',
                ],
                'gap' => [
                    'none' => 'gap-0',
                    'sm'   => 'gap-4',
                    'base' => 'gap-6',
                    'lg'   => 'gap-8',
                    'xl'   => 'gap-12',
                ],
                'border' => [
                    'none'   => '',
                    'subtle' => 'border border-border-subtle',
                    'base'   => 'border border-border-base',
                    'strong' => 'border border-border-strong',
                    'accent' => 'border border-accent-base',
                ],
                'radius' => [
                    'none' => 'rounded-none',
                    'sm'   => 'rounded-sm',
                    'base' => 'rounded-base',
                    'lg'   => 'rounded-lg',
                    'xl'   => 'rounded-xl',
                ],
                'shadow' => [
                    'none' => 'shadow-none',
                    'sm'   => 'shadow-sm',
                    'base' => 'shadow',
                    'md'   => 'shadow-md',
                    'lg'   => 'shadow-lg',
                ],
                'overflow' => [
                    'visible' => 'overflow-visible',
                    'hidden'  => 'overflow-hidden',
                ],
            ],
            renderer: new SectionRenderer(),
            editorConfig: self::localizeEditorConfig([
                'hasSlots'    => ['default'],
                'isContainer' => true,
                'tabs' => [
                    ['id' => 'layout', 'label' => 'Layout', 'controls' => [
                        ['id' => 'contentWidth', 'type' => 'variant', 'label' => 'Content Width', 'variantKey' => 'contentWidth'],
                        ['id' => 'fullWidth', 'type' => 'toggle', 'label' => 'Stretch Section'],
                        ['id' => 'minHeight', 'type' => 'variant', 'label' => 'Minimum Height', 'variantKey' => 'minHeight'],
                        ['id' => 'horizontalAlign', 'type' => 'variant', 'label' => 'Horizontal Align', 'variantKey' => 'horizontalAlign'],
                        ['id' => 'verticalAlign', 'type' => 'variant', 'label' => 'Vertical Align', 'variantKey' => 'verticalAlign'],
                        ['id' => 'gap', 'type' => 'variant', 'label' => 'Block Gap', 'variantKey' => 'gap'],
                    ]],
                    ['id' => 'style', 'label' => 'Style', 'controls' => [
                        ['id' => 'background', 'type' => 'variant', 'label' => 'Background', 'variantKey' => 'background'],
                        ['id' => 'textColor', 'type' => 'variant', 'label' => 'Text Color', 'variantKey' => 'textColor'],
                        ['id' => 'border', 'type' => 'variant', 'label' => 'Border', 'variantKey' => 'border'],
                        ['id' => 'radius', 'type' => 'variant', 'label' => 'Radius', 'variantKey' => 'radius'],
                        ['id' => 'shadow', 'type' => 'variant', 'label' => 'Shadow', 'variantKey' => 'shadow'],
                        ['id' => 'overflow', 'type' => 'variant', 'label' => 'Overflow', 'variantKey' => 'overflow'],
                    ]],
                    ['id' => 'advanced', 'label' => 'Advanced', 'controls' => [
                        ['id' => 'paddingY', 'type' => 'variant', 'label' => 'Vertical Padding', 'variantKey' => 'paddingY'],
                        ['id' => 'paddingX', 'type' => 'variant', 'label' => 'Horizontal Padding', 'variantKey' => 'paddingX'],
                        ['id' => 'customClass', 'type' => 'text', 'label' => 'Custom CSS Classes'],
                    ]],
                ],
            ]),
        );
    }

    private static function makeText(): BlockDefinition
    {
        return new BlockDefinition(
            type: 'bky/text',
            label: self::tr('Text'),
            category: 'basic',
            description: self::tr('Rich paragraph content with size and color controls.'),
            keywords: ['paragraph', 'copy', 'body'],
            icon: 'T',
            schema: [
                'type'       => 'object',
                'required'   => ['content'],
                'properties' => [
                    'content' => ['type' => 'string'],
                    'size'    => ['type' => 'string', 'enum' => ['sm', 'base', 'lg', 'xl'], 'default' => 'base'],
                    'color'   => ['type' => 'string', 'enum' => ['base', 'muted', 'faint'], 'default' => 'base'],
                    'align'   => ['type' => 'string', 'enum' => ['start', 'center', 'end'], 'default' => 'start'],
                ],
            ],
            variants: [
                'size'  => ['sm' => 'text-sm', 'base' => 'text-base', 'lg' => 'text-lg', 'xl' => 'text-xl'],
                'color' => ['base' => 'text-text-base', 'muted' => 'text-text-muted', 'faint' => 'text-text-faint'],
                'align' => ['start' => 'text-start', 'center' => 'text-center', 'end' => 'text-end'],
            ],
            renderer: new TextRenderer(),
            editorConfig: self::localizeEditorConfig([
                'tabs' => [
                    ['id' => 'content', 'label' => 'Content', 'controls' => [
                        ['id' => 'content', 'type' => 'richtext', 'label' => 'Content'],
                    ]],
                    ['id' => 'style', 'label' => 'Style', 'controls' => [
                        ['id' => 'size', 'type' => 'variant', 'label' => 'Size', 'variantKey' => 'size'],
                        ['id' => 'color', 'type' => 'variant', 'label' => 'Color', 'variantKey' => 'color'],
                        ['id' => 'align', 'type' => 'variant', 'label' => 'Alignment', 'variantKey' => 'align'],
                    ]],
                ],
            ]),
        );
    }

    private static function makeButton(): BlockDefinition
    {
        return new BlockDefinition(
            type: 'bky/button',
            label: self::tr('Button'),
            category: 'basic',
            description: self::tr('Clickable call to action with slotted content, surface styling, and link target.'),
            keywords: ['cta', 'link', 'action'],
            icon: 'B',
            schema: [
                'type'       => 'object',
                'properties' => [
                    'label'   => ['type' => 'string', 'default' => ''],
                    'href'    => ['type' => 'string', 'default' => '#'],
                    'variant' => ['type' => 'string', 'enum' => ['primary', 'secondary', 'ghost', 'outline'], 'default' => 'primary'],
                    'size'    => ['type' => 'string', 'enum' => ['sm', 'base', 'lg'], 'default' => 'base'],
                    'target'  => ['type' => 'string', 'enum' => ['_self', '_blank'], 'default' => '_self'],
                    'align'   => ['type' => 'string', 'enum' => ['start', 'center', 'end', 'stretch'], 'default' => 'start'],
                    'fullWidth' => ['type' => 'boolean', 'default' => false],
                    'background' => ['type' => 'string', 'enum' => ['transparent', 'surface', 'elevated', 'accent', 'dark'], 'default' => 'transparent'],
                    'textColor' => ['type' => 'string', 'enum' => ['inherit', 'base', 'muted', 'inverse', 'accent'], 'default' => 'inherit'],
                    'border' => ['type' => 'string', 'enum' => ['none', 'subtle', 'base', 'strong', 'accent'], 'default' => 'none'],
                    'radius' => ['type' => 'string', 'enum' => ['none', 'sm', 'base', 'lg', 'xl', 'full', 'button'], 'default' => 'button'],
                    'shadow' => ['type' => 'string', 'enum' => ['none', 'sm', 'base', 'md', 'lg'], 'default' => 'none'],
                    'overflow' => ['type' => 'string', 'enum' => ['visible', 'hidden'], 'default' => 'visible'],
                ],
            ],
            variants: [
                'variant' => [
                    'primary'   => 'bg-accent-base text-text-on-accent hover:bg-accent-hover',
                    'secondary' => 'bg-surface-elevated text-text-base border border-border-base hover:bg-surface-overlay',
                    'ghost'     => 'bg-transparent text-accent-text hover:bg-accent-subtle',
                    'outline'   => 'bg-transparent text-accent-base border border-accent-base hover:bg-accent-subtle',
                ],
                'size' => [
                    'sm'   => 'px-3 py-1.5 text-sm rounded-button',
                    'base' => 'px-4 py-2 text-base rounded-button',
                    'lg'   => 'px-6 py-3 text-lg rounded-button',
                ],
                'align' => [
                    'start'   => 'justify-start',
                    'center'  => 'justify-center',
                    'end'     => 'justify-end',
                    'stretch' => 'justify-stretch',
                ],
                'background' => [
                    'transparent' => '',
                    'surface'     => 'bg-surface-base',
                    'elevated'    => 'bg-surface-elevated',
                    'accent'      => 'bg-accent-subtle',
                    'dark'        => 'bg-text-base',
                ],
                'textColor' => [
                    'inherit' => '',
                    'base'    => 'text-text-base',
                    'muted'   => 'text-text-muted',
                    'inverse' => 'text-text-inverse',
                    'accent'  => 'text-accent-text',
                ],
                'border' => [
                    'none'   => '',
                    'subtle' => 'border border-border-subtle',
                    'base'   => 'border border-border-base',
                    'strong' => 'border border-border-strong',
                    'accent' => 'border border-accent-base',
                ],
                'radius' => [
                    'none' => 'rounded-none',
                    'sm'   => 'rounded-sm',
                    'base' => 'rounded-base',
                    'lg'   => 'rounded-lg',
                    'xl'   => 'rounded-xl',
                    'full' => 'rounded-full',
                    'button' => 'rounded-button',
                ],
                'shadow' => [
                    'none' => 'shadow-none',
                    'sm'   => 'shadow-sm',
                    'base' => 'shadow',
                    'md'   => 'shadow-md',
                    'lg'   => 'shadow-lg',
                ],
                'overflow' => [
                    'visible' => 'overflow-visible',
                    'hidden'  => 'overflow-hidden',
                ],
            ],
            renderer: new ButtonRenderer(),
            editorConfig: self::localizeEditorConfig([
                'hasSlots' => ['default'],
                'isContainer' => true,
                'tabs' => [
                    ['id' => 'content', 'label' => 'Content', 'controls' => [
                        ['id' => 'href', 'type' => 'text', 'label' => 'Link'],
                        ['id' => 'target', 'type' => 'select', 'label' => 'Open in new tab', 'options' => [['_self', 'Same Tab'], ['_blank', 'New Tab']]],
                    ]],
                    ['id' => 'layout', 'label' => 'Layout', 'controls' => [
                        ['id' => 'align', 'type' => 'variant', 'label' => 'Alignment', 'variantKey' => 'align'],
                        ['id' => 'fullWidth', 'type' => 'toggle', 'label' => 'Full Width'],
                    ]],
                    ['id' => 'style', 'label' => 'Style', 'controls' => [
                        ['id' => 'variant', 'type' => 'variant', 'label' => 'Variant', 'variantKey' => 'variant'],
                        ['id' => 'size', 'type' => 'variant', 'label' => 'Size', 'variantKey' => 'size'],
                        ['id' => 'background', 'type' => 'variant', 'label' => 'Background', 'variantKey' => 'background'],
                        ['id' => 'textColor', 'type' => 'variant', 'label' => 'Text Color', 'variantKey' => 'textColor'],
                        ['id' => 'border', 'type' => 'variant', 'label' => 'Border', 'variantKey' => 'border'],
                        ['id' => 'radius', 'type' => 'variant', 'label' => 'Radius', 'variantKey' => 'radius'],
                        ['id' => 'shadow', 'type' => 'variant', 'label' => 'Shadow', 'variantKey' => 'shadow'],
                        ['id' => 'overflow', 'type' => 'variant', 'label' => 'Overflow', 'variantKey' => 'overflow'],
                    ]],
                ],
            ]),
        );
    }

    private static function makeImage(): BlockDefinition
    {
        return new BlockDefinition(
            type: 'bky/image',
            label: self::tr('Image'),
            category: 'media',
            description: self::tr('Image block with alt text, rounded corners, and aspect ratio.'),
            keywords: ['media', 'photo', 'picture'],
            icon: 'I',
            schema: [
                'type'       => 'object',
                'required'   => ['attachmentId'],
                'properties' => [
                    'attachmentId' => ['type' => 'integer'],
                    'alt'          => ['type' => 'string', 'default' => ''],
                    'size'         => ['type' => 'string', 'default' => 'large'],
                    'loading'      => ['type' => 'string', 'enum' => ['lazy', 'eager'], 'default' => 'lazy'],
                    'decoding'     => ['type' => 'string', 'enum' => ['async', 'sync', 'auto'], 'default' => 'async'],
                    'fit'          => ['type' => 'string', 'enum' => ['cover', 'contain', 'fill', 'none', 'scale-down'], 'default' => 'cover'],
                    'focalX'       => ['type' => 'integer', 'default' => 50],
                    'focalY'       => ['type' => 'integer', 'default' => 50],
                    'rounded'      => ['type' => 'string', 'enum' => ['none', 'base', 'lg', 'full'], 'default' => 'base'],
                    'aspectRatio'  => ['type' => 'string', 'enum' => ['auto', 'square', 'video', 'wide'], 'default' => 'auto'],
                    'align'        => ['type' => 'string', 'enum' => ['start', 'center', 'end', 'stretch'], 'default' => 'center'],
                    'width'        => ['type' => 'string', 'enum' => ['auto', 'sm', 'base', 'lg', 'full'], 'default' => 'full'],
                ],
            ],
            variants: [
                'rounded' => [
                    'none' => '',
                    'base' => 'rounded-image',
                    'lg'   => 'rounded-2xl',
                    'full' => 'rounded-full',
                ],
                'aspectRatio' => [
                    'auto'   => '',
                    'square' => 'aspect-square',
                    'video'  => 'aspect-video',
                    'wide'   => 'aspect-[21/9]',
                ],
                'align' => [
                    'start'   => 'justify-start',
                    'center'  => 'justify-center',
                    'end'     => 'justify-end',
                    'stretch' => 'justify-stretch',
                ],
                'width' => [
                    'auto' => 'w-auto',
                    'sm'   => 'w-64 max-w-full',
                    'base' => 'w-96 max-w-full',
                    'lg'   => 'w-[40rem] max-w-full',
                    'full' => 'w-full',
                ],
            ],
            renderer: new ImageRenderer(),
            editorConfig: self::localizeEditorConfig([
                'tabs' => [
                    ['id' => 'content', 'label' => 'Content', 'controls' => [
                        ['id' => 'attachmentId', 'type' => 'media', 'label' => 'Image', 'mediaType' => 'image'],
                    ]],
                    ['id' => 'layout', 'label' => 'Layout', 'controls' => [
                        ['id' => 'align', 'type' => 'variant', 'label' => 'Alignment', 'variantKey' => 'align'],
                        ['id' => 'width', 'type' => 'variant', 'label' => 'Width', 'variantKey' => 'width'],
                    ]],
                    ['id' => 'style', 'label' => 'Style', 'controls' => [
                        ['id' => 'rounded', 'type' => 'variant', 'label' => 'Rounded Corners', 'variantKey' => 'rounded'],
                        ['id' => 'aspectRatio', 'type' => 'variant', 'label' => 'Aspect Ratio', 'variantKey' => 'aspectRatio'],
                    ]],
                ],
            ]),
        );
    }

    private static function makeGrid(): BlockDefinition
    {
        return new BlockDefinition(
            type: 'bky/grid',
            label: self::tr('Grid'),
            category: 'layout',
            description: self::tr('Responsive grid container for laying out child blocks.'),
            keywords: ['layout', 'columns', 'cards'],
            icon: 'G',
            schema: [
                'type'       => 'object',
                'properties' => [
                    'columns' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 12, 'default' => 3],
                    'rows'    => ['type' => 'integer', 'minimum' => 1, 'maximum' => 6, 'default' => 1],
                    'gap'     => ['type' => 'string', 'enum' => ['none', 'sm', 'base', 'lg', 'xl'], 'default' => 'base'],
                    'rowGap'  => ['type' => 'string', 'enum' => ['none', 'sm', 'base', 'lg', 'xl'], 'default' => 'base'],
                    'alignItems' => ['type' => 'string', 'enum' => ['start', 'center', 'end', 'stretch'], 'default' => 'stretch'],
                    'justifyItems' => ['type' => 'string', 'enum' => ['start', 'center', 'end', 'stretch'], 'default' => 'stretch'],
                    'background' => ['type' => 'string', 'enum' => ['transparent', 'surface', 'elevated', 'accent', 'dark'], 'default' => 'transparent'],
                    'textColor' => ['type' => 'string', 'enum' => ['inherit', 'base', 'muted', 'inverse', 'accent'], 'default' => 'inherit'],
                    'border' => ['type' => 'string', 'enum' => ['none', 'subtle', 'base', 'strong', 'accent'], 'default' => 'none'],
                    'radius' => ['type' => 'string', 'enum' => ['none', 'sm', 'base', 'lg', 'xl'], 'default' => 'none'],
                    'shadow' => ['type' => 'string', 'enum' => ['none', 'sm', 'base', 'md', 'lg'], 'default' => 'none'],
                    'overflow' => ['type' => 'string', 'enum' => ['visible', 'hidden'], 'default' => 'visible'],
                ],
            ],
            variants: [
                'columns' => [
                    '1' => 'grid-cols-1',
                    '2' => 'grid-cols-1 sm:grid-cols-2',
                    '3' => 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-3',
                    '4' => 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-4',
                    '5' => 'grid-cols-2 sm:grid-cols-3 lg:grid-cols-5',
                    '6' => 'grid-cols-2 sm:grid-cols-3 lg:grid-cols-6',
                    '7' => 'grid-cols-2 sm:grid-cols-4 lg:grid-cols-7',
                    '8' => 'grid-cols-2 sm:grid-cols-4 lg:grid-cols-8',
                    '9' => 'grid-cols-3 lg:grid-cols-9',
                    '10' => 'grid-cols-2 sm:grid-cols-5 lg:grid-cols-10',
                    '11' => 'grid-cols-3 sm:grid-cols-6 lg:grid-cols-11',
                    '12' => 'grid-cols-3 sm:grid-cols-6 lg:grid-cols-12',
                ],
                'rows' => [
                    '1' => 'grid-rows-1',
                    '2' => 'grid-rows-2',
                    '3' => 'grid-rows-3',
                    '4' => 'grid-rows-4',
                    '5' => 'grid-rows-5',
                    '6' => 'grid-rows-6',
                ],
                'gap' => [
                    'none' => 'gap-0',
                    'sm'   => 'gap-4',
                    'base' => 'gap-6',
                    'lg'   => 'gap-8',
                    'xl'   => 'gap-10',
                ],
                'rowGap' => [
                    'none' => 'gap-y-0',
                    'sm'   => 'gap-y-4',
                    'base' => 'gap-y-6',
                    'lg'   => 'gap-y-8',
                    'xl'   => 'gap-y-10',
                ],
                'alignItems' => [
                    'start'   => 'items-start',
                    'center'  => 'items-center',
                    'end'     => 'items-end',
                    'stretch' => 'items-stretch',
                ],
                'justifyItems' => [
                    'start'   => 'justify-items-start',
                    'center'  => 'justify-items-center',
                    'end'     => 'justify-items-end',
                    'stretch' => 'justify-items-stretch',
                ],
                'background' => [
                    'transparent' => '',
                    'surface'     => 'bg-surface-base',
                    'elevated'    => 'bg-surface-elevated',
                    'accent'      => 'bg-accent-subtle',
                    'dark'        => 'bg-text-base',
                ],
                'textColor' => [
                    'inherit' => '',
                    'base'    => 'text-text-base',
                    'muted'   => 'text-text-muted',
                    'inverse' => 'text-text-inverse',
                    'accent'  => 'text-accent-text',
                ],
                'border' => [
                    'none'   => '',
                    'subtle' => 'border border-border-subtle',
                    'base'   => 'border border-border-base',
                    'strong' => 'border border-border-strong',
                    'accent' => 'border border-accent-base',
                ],
                'radius' => [
                    'none' => 'rounded-none',
                    'sm'   => 'rounded-sm',
                    'base' => 'rounded-base',
                    'lg'   => 'rounded-lg',
                    'xl'   => 'rounded-xl',
                ],
                'shadow' => [
                    'none' => 'shadow-none',
                    'sm'   => 'shadow-sm',
                    'base' => 'shadow',
                    'md'   => 'shadow-md',
                    'lg'   => 'shadow-lg',
                ],
                'overflow' => [
                    'visible' => 'overflow-visible',
                    'hidden'  => 'overflow-hidden',
                ],
            ],
            renderer: new GridRenderer(),
            editorConfig: self::localizeEditorConfig([
                'hasSlots' => ['default'],
                'isContainer' => true,
                'tabs' => [
                    ['id' => 'layout', 'label' => 'Layout', 'controls' => [
                        ['id' => 'columns', 'type' => 'number', 'label' => 'Columns', 'min' => 1, 'max' => 12, 'step' => 1],
                        ['id' => 'rows', 'type' => 'number', 'label' => 'Rows', 'min' => 1, 'max' => 6, 'step' => 1],
                        ['id' => 'gap', 'type' => 'variant', 'label' => 'Column Gap', 'variantKey' => 'gap'],
                        ['id' => 'rowGap', 'type' => 'variant', 'label' => 'Row Gap', 'variantKey' => 'rowGap'],
                        ['id' => 'alignItems', 'type' => 'variant', 'label' => 'Vertical Align', 'variantKey' => 'alignItems'],
                        ['id' => 'justifyItems', 'type' => 'variant', 'label' => 'Horizontal Align', 'variantKey' => 'justifyItems'],
                    ]],
                    ['id' => 'style', 'label' => 'Style', 'controls' => [
                        ['id' => 'background', 'type' => 'variant', 'label' => 'Background', 'variantKey' => 'background'],
                        ['id' => 'textColor', 'type' => 'variant', 'label' => 'Text Color', 'variantKey' => 'textColor'],
                        ['id' => 'border', 'type' => 'variant', 'label' => 'Border', 'variantKey' => 'border'],
                        ['id' => 'radius', 'type' => 'variant', 'label' => 'Radius', 'variantKey' => 'radius'],
                        ['id' => 'shadow', 'type' => 'variant', 'label' => 'Shadow', 'variantKey' => 'shadow'],
                        ['id' => 'overflow', 'type' => 'variant', 'label' => 'Overflow', 'variantKey' => 'overflow'],
                    ]],
                ],
            ]),
        );
    }

    private static function makeRows(): BlockDefinition
    {
        return new BlockDefinition(
            type: 'bky/rows',
            label: self::tr('Rows'),
            category: 'layout',
            description: self::tr('Vertical rows layout with independently editable row slots.'),
            keywords: ['layout', 'stack', 'row', 'vertical'],
            icon: 'R',
            schema: [
                'type'       => 'object',
                'properties' => [
                    'count' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 6, 'default' => 3],
                    'gap' => ['type' => 'string', 'enum' => ['none', 'sm', 'base', 'lg', 'xl'], 'default' => 'base'],
                    'alignItems' => ['type' => 'string', 'enum' => ['start', 'center', 'end', 'stretch'], 'default' => 'stretch'],
                    'background' => ['type' => 'string', 'enum' => ['transparent', 'surface', 'elevated', 'accent', 'dark'], 'default' => 'transparent'],
                    'textColor' => ['type' => 'string', 'enum' => ['inherit', 'base', 'muted', 'inverse', 'accent'], 'default' => 'inherit'],
                    'border' => ['type' => 'string', 'enum' => ['none', 'subtle', 'base', 'strong', 'accent'], 'default' => 'none'],
                    'radius' => ['type' => 'string', 'enum' => ['none', 'sm', 'base', 'lg', 'xl'], 'default' => 'none'],
                    'shadow' => ['type' => 'string', 'enum' => ['none', 'sm', 'base', 'md', 'lg'], 'default' => 'none'],
                    'overflow' => ['type' => 'string', 'enum' => ['visible', 'hidden'], 'default' => 'visible'],
                ],
            ],
            variants: [
                'gap' => [
                    'none' => 'gap-0',
                    'sm'   => 'gap-4',
                    'base' => 'gap-6',
                    'lg'   => 'gap-8',
                    'xl'   => 'gap-10',
                ],
                'alignItems' => [
                    'start'   => 'items-start',
                    'center'  => 'items-center',
                    'end'     => 'items-end',
                    'stretch' => 'items-stretch',
                ],
                'background' => [
                    'transparent' => '',
                    'surface'     => 'bg-surface-base',
                    'elevated'    => 'bg-surface-elevated',
                    'accent'      => 'bg-accent-subtle',
                    'dark'        => 'bg-text-base',
                ],
                'textColor' => [
                    'inherit' => '',
                    'base'    => 'text-text-base',
                    'muted'   => 'text-text-muted',
                    'inverse' => 'text-text-inverse',
                    'accent'  => 'text-accent-text',
                ],
                'border' => [
                    'none'   => '',
                    'subtle' => 'border border-border-subtle',
                    'base'   => 'border border-border-base',
                    'strong' => 'border border-border-strong',
                    'accent' => 'border border-accent-base',
                ],
                'radius' => [
                    'none' => 'rounded-none',
                    'sm'   => 'rounded-sm',
                    'base' => 'rounded-base',
                    'lg'   => 'rounded-lg',
                    'xl'   => 'rounded-xl',
                ],
                'shadow' => [
                    'none' => 'shadow-none',
                    'sm'   => 'shadow-sm',
                    'base' => 'shadow',
                    'md'   => 'shadow-md',
                    'lg'   => 'shadow-lg',
                ],
                'overflow' => [
                    'visible' => 'overflow-visible',
                    'hidden'  => 'overflow-hidden',
                ],
            ],
            renderer: new RowsRenderer(),
            editorConfig: self::localizeEditorConfig([
                'hasSlots' => ['row-1', 'row-2', 'row-3', 'row-4', 'row-5', 'row-6'],
                'isContainer' => true,
                'tabs' => [
                    ['id' => 'layout', 'label' => 'Layout', 'controls' => [
                        ['id' => 'count', 'type' => 'number', 'label' => 'Rows', 'min' => 1, 'max' => 6, 'step' => 1],
                        ['id' => 'gap', 'type' => 'variant', 'label' => 'Gap', 'variantKey' => 'gap'],
                        ['id' => 'alignItems', 'type' => 'variant', 'label' => 'Horizontal Align', 'variantKey' => 'alignItems'],
                    ]],
                    ['id' => 'style', 'label' => 'Style', 'controls' => [
                        ['id' => 'background', 'type' => 'variant', 'label' => 'Background', 'variantKey' => 'background'],
                        ['id' => 'textColor', 'type' => 'variant', 'label' => 'Text Color', 'variantKey' => 'textColor'],
                        ['id' => 'border', 'type' => 'variant', 'label' => 'Border', 'variantKey' => 'border'],
                        ['id' => 'radius', 'type' => 'variant', 'label' => 'Radius', 'variantKey' => 'radius'],
                        ['id' => 'shadow', 'type' => 'variant', 'label' => 'Shadow', 'variantKey' => 'shadow'],
                        ['id' => 'overflow', 'type' => 'variant', 'label' => 'Overflow', 'variantKey' => 'overflow'],
                    ]],
                ],
            ]),
        );
    }

    private static function makeContainer(): BlockDefinition
    {
        return new BlockDefinition(
            type: 'bky/container',
            label: self::tr('Container'),
            category: 'layout',
            description: self::tr('Constrained content wrapper with alignment controls.'),
            keywords: ['layout', 'wrapper', 'width'],
            icon: 'C',
            schema: [
                'type'       => 'object',
                'properties' => [
                    'maxWidth' => ['type' => 'string', 'enum' => ['sm', 'base', 'lg', 'xl', 'full'], 'default' => 'base'],
                    'align'    => ['type' => 'string', 'enum' => ['start', 'center', 'end'], 'default' => 'center'],
                    'padding'  => ['type' => 'string', 'enum' => ['none', 'sm', 'base', 'lg'], 'default' => 'none'],
                    'background' => ['type' => 'string', 'enum' => ['transparent', 'surface', 'elevated', 'accent'], 'default' => 'transparent'],
                    'radius'   => ['type' => 'string', 'enum' => ['none', 'base', 'lg', 'xl'], 'default' => 'none'],
                ],
            ],
            variants: [
                'maxWidth' => [
                    'sm'   => 'max-w-2xl',
                    'base' => 'max-w-4xl',
                    'lg'   => 'max-w-6xl',
                    'xl'   => 'max-w-7xl',
                    'full' => 'max-w-none',
                ],
                'align' => [
                    'start'  => 'mr-auto',
                    'center' => 'mx-auto',
                    'end'    => 'ml-auto',
                ],
                'padding' => [
                    'none' => 'p-0',
                    'sm'   => 'p-4',
                    'base' => 'p-6',
                    'lg'   => 'p-8',
                ],
                'background' => [
                    'transparent' => '',
                    'surface' => 'bg-surface-base',
                    'elevated' => 'bg-surface-elevated',
                    'accent' => 'bg-accent-subtle',
                ],
                'radius' => [
                    'none' => 'rounded-none',
                    'base' => 'rounded-base',
                    'lg' => 'rounded-lg',
                    'xl' => 'rounded-xl',
                ],
            ],
            renderer: new ContainerRenderer(),
            editorConfig: self::localizeEditorConfig([
                'hasSlots' => ['default'],
                'isContainer' => true,
                'tabs' => [
                    ['id' => 'layout', 'label' => 'Layout', 'controls' => [
                        ['id' => 'maxWidth', 'type' => 'variant', 'label' => 'Max Width', 'variantKey' => 'maxWidth'],
                        ['id' => 'align', 'type' => 'variant', 'label' => 'Alignment', 'variantKey' => 'align'],
                        ['id' => 'padding', 'type' => 'variant', 'label' => 'Padding', 'variantKey' => 'padding'],
                    ]],
                    ['id' => 'style', 'label' => 'Style', 'controls' => [
                        ['id' => 'background', 'type' => 'variant', 'label' => 'Background', 'variantKey' => 'background'],
                        ['id' => 'radius', 'type' => 'variant', 'label' => 'Radius', 'variantKey' => 'radius'],
                    ]],
                ],
            ]),
        );
    }

    private static function makeColumns(): BlockDefinition
    {
        return new BlockDefinition(
            type: 'bky/columns',
            label: self::tr('Columns'),
            category: 'layout',
            description: self::tr('Multi-column layout with independent editable slots.'),
            keywords: ['layout', 'split', 'column'],
            icon: 'Cols',
            schema: [
                'type'       => 'object',
                'properties' => [
                    'count'    => ['type' => 'integer', 'minimum' => 2, 'maximum' => 6, 'default' => 2],
                    'gap'      => ['type' => 'string', 'enum' => ['none', 'sm', 'base', 'lg', 'xl'], 'default' => 'base'],
                    'stackAt'  => ['type' => 'string', 'enum' => ['never', 'sm', 'md', 'lg'], 'default' => 'md'],
                    'verticalAlign' => ['type' => 'string', 'enum' => ['start', 'center', 'end', 'stretch'], 'default' => 'start'],
                    'background' => ['type' => 'string', 'enum' => ['transparent', 'surface', 'elevated', 'accent', 'dark'], 'default' => 'transparent'],
                    'textColor' => ['type' => 'string', 'enum' => ['inherit', 'base', 'muted', 'inverse', 'accent'], 'default' => 'inherit'],
                    'border' => ['type' => 'string', 'enum' => ['none', 'subtle', 'base', 'strong', 'accent'], 'default' => 'none'],
                    'radius' => ['type' => 'string', 'enum' => ['none', 'sm', 'base', 'lg', 'xl'], 'default' => 'none'],
                    'shadow' => ['type' => 'string', 'enum' => ['none', 'sm', 'base', 'md', 'lg'], 'default' => 'none'],
                    'overflow' => ['type' => 'string', 'enum' => ['visible', 'hidden'], 'default' => 'visible'],
                ],
            ],
            variants: [
                'count' => [
                    '2' => 'grid-cols-1 md:grid-cols-2',
                    '3' => 'grid-cols-1 md:grid-cols-3',
                    '4' => 'grid-cols-1 md:grid-cols-4',
                    '5' => 'grid-cols-1 md:grid-cols-5',
                    '6' => 'grid-cols-1 md:grid-cols-6',
                ],
                'gap' => [
                    'none' => 'gap-0',
                    'sm'   => 'gap-4',
                    'base' => 'gap-8',
                    'lg'   => 'gap-12',
                    'xl'   => 'gap-16',
                ],
                'verticalAlign' => [
                    'start'   => 'items-start',
                    'center'  => 'items-center',
                    'end'     => 'items-end',
                    'stretch' => 'items-stretch',
                ],
                'background' => [
                    'transparent' => '',
                    'surface'     => 'bg-surface-base',
                    'elevated'    => 'bg-surface-elevated',
                    'accent'      => 'bg-accent-subtle',
                    'dark'        => 'bg-text-base',
                ],
                'textColor' => [
                    'inherit' => '',
                    'base'    => 'text-text-base',
                    'muted'   => 'text-text-muted',
                    'inverse' => 'text-text-inverse',
                    'accent'  => 'text-accent-text',
                ],
                'border' => [
                    'none'   => '',
                    'subtle' => 'border border-border-subtle',
                    'base'   => 'border border-border-base',
                    'strong' => 'border border-border-strong',
                    'accent' => 'border border-accent-base',
                ],
                'radius' => [
                    'none' => 'rounded-none',
                    'sm'   => 'rounded-sm',
                    'base' => 'rounded-base',
                    'lg'   => 'rounded-lg',
                    'xl'   => 'rounded-xl',
                ],
                'shadow' => [
                    'none' => 'shadow-none',
                    'sm'   => 'shadow-sm',
                    'base' => 'shadow',
                    'md'   => 'shadow-md',
                    'lg'   => 'shadow-lg',
                ],
                'overflow' => [
                    'visible' => 'overflow-visible',
                    'hidden'  => 'overflow-hidden',
                ],
            ],
            renderer: new ColumnsRenderer(),
            editorConfig: self::localizeEditorConfig([
                'hasSlots' => ['column-1', 'column-2', 'column-3', 'column-4', 'column-5', 'column-6'],
                'isContainer' => true,
                'tabs' => [
                    ['id' => 'layout', 'label' => 'Layout', 'controls' => [
                        ['id' => 'count', 'type' => 'number', 'label' => 'Columns', 'min' => 2, 'max' => 6, 'step' => 1],
                        ['id' => 'gap', 'type' => 'variant', 'label' => 'Gap', 'variantKey' => 'gap'],
                        ['id' => 'stackAt', 'type' => 'select', 'label' => 'Stack At', 'options' => [['never', 'Never'], ['sm', 'Small'], ['md', 'Medium'], ['lg', 'Large']]],
                        ['id' => 'verticalAlign', 'type' => 'variant', 'label' => 'Vertical Align', 'variantKey' => 'verticalAlign'],
                    ]],
                    ['id' => 'style', 'label' => 'Style', 'controls' => [
                        ['id' => 'background', 'type' => 'variant', 'label' => 'Background', 'variantKey' => 'background'],
                        ['id' => 'textColor', 'type' => 'variant', 'label' => 'Text Color', 'variantKey' => 'textColor'],
                        ['id' => 'border', 'type' => 'variant', 'label' => 'Border', 'variantKey' => 'border'],
                        ['id' => 'radius', 'type' => 'variant', 'label' => 'Radius', 'variantKey' => 'radius'],
                        ['id' => 'shadow', 'type' => 'variant', 'label' => 'Shadow', 'variantKey' => 'shadow'],
                        ['id' => 'overflow', 'type' => 'variant', 'label' => 'Overflow', 'variantKey' => 'overflow'],
                    ]],
                ],
            ]),
        );
    }

    private static function makeCard(): BlockDefinition
    {
        return new BlockDefinition(
            type: 'bky/card',
            schema: [
                'type'       => 'object',
                'properties' => [
                    'padding'    => ['type' => 'string', 'enum' => ['none', 'sm', 'base', 'lg'], 'default' => 'base'],
                    'background' => ['type' => 'string', 'enum' => ['base', 'elevated', 'accent'], 'default' => 'elevated'],
                    'border'     => ['type' => 'boolean', 'default' => true],
                    'shadow'     => ['type' => 'string', 'enum' => ['none', 'sm', 'base', 'md', 'lg'], 'default' => 'sm'],
                    'overflow'   => ['type' => 'string', 'enum' => ['hidden', 'visible'], 'default' => 'hidden'],
                ],
            ],
            variants: [
                'padding' => [
                    'none' => 'p-0',
                    'sm'   => 'p-4',
                    'base' => 'p-6',
                    'lg'   => 'p-8',
                ],
                'background' => [
                    'base'     => 'bg-surface-base',
                    'elevated' => 'bg-surface-elevated',
                    'accent'   => 'bg-accent-subtle',
                ],
                'shadow' => [
                    'none' => 'shadow-none',
                    'sm'   => 'shadow-sm',
                    'base' => 'shadow',
                    'md'   => 'shadow-md',
                    'lg'   => 'shadow-lg',
                ],
                'overflow' => [
                    'hidden'  => 'overflow-hidden',
                    'visible' => 'overflow-visible',
                ],
            ],
            renderer: new CardRenderer(),
            editorConfig: self::localizeEditorConfig([
                'hasSlots' => ['media', 'header', 'default', 'footer'],
                'isContainer' => true,
                'tabs' => [
                    ['id' => 'layout', 'label' => 'Layout', 'controls' => [
                        ['id' => 'padding', 'type' => 'variant', 'label' => 'Inner Padding', 'variantKey' => 'padding'],
                    ]],
                    ['id' => 'style', 'label' => 'Style', 'controls' => [
                        ['id' => 'background', 'type' => 'variant', 'label' => 'Background', 'variantKey' => 'background'],
                        ['id' => 'border', 'type' => 'toggle', 'label' => 'Border'],
                        ['id' => 'shadow', 'type' => 'variant', 'label' => 'Shadow', 'variantKey' => 'shadow'],
                        ['id' => 'overflow', 'type' => 'variant', 'label' => 'Overflow', 'variantKey' => 'overflow'],
                    ]],
                ],
            ]),
            label: self::tr('Card'),
            category: 'layout',
            description: self::tr('A structured card surface with media, header, body, and footer regions.'),
            keywords: ['box', 'panel', 'container', 'surface', 'media', 'header', 'footer'],
            icon: 'C',
        );
    }

    private static function makeDivider(): BlockDefinition
    {
        return new BlockDefinition(
            type: 'bky/divider',
            schema: [
                'type'       => 'object',
                'properties' => [
                    'style' => ['type' => 'string', 'enum' => ['solid', 'dashed', 'dotted', 'gradient', 'ornament'], 'default' => 'solid'],
                    'ornament' => ['type' => 'string', 'default' => '◆'],
                    'tone'  => ['type' => 'string', 'enum' => ['subtle', 'base', 'strong', 'accent'], 'default' => 'subtle'],
                    'width' => ['type' => 'string', 'enum' => ['sm', 'base', 'full'], 'default' => 'full'],
                ],
            ],
            variants: [
                'tone' => [
                    'subtle' => 'border-border-subtle',
                    'base'   => 'border-border-base',
                    'strong' => 'border-border-strong',
                    'accent' => 'border-accent-base',
                ],
                'width' => [
                    'sm'   => 'max-w-xs',
                    'base' => 'max-w-2xl',
                    'full' => 'w-full',
                ],
            ],
            renderer: new DividerRenderer(),
            editorConfig: self::localizeEditorConfig([
                'tabs' => [
                    ['id' => 'content', 'label' => 'Content', 'controls' => [
                        ['id' => 'style', 'type' => 'select', 'label' => 'Style', 'options' => [['solid', 'Solid'], ['dashed', 'Dashed'], ['dotted', 'Dotted'], ['gradient', 'Gradient'], ['ornament', 'Ornament']]],
                        ['id' => 'ornament', 'type' => 'text', 'label' => 'Ornament'],
                        ['id' => 'tone', 'type' => 'select', 'label' => 'Tone', 'options' => [['subtle', 'Subtle'], ['base', 'Base'], ['strong', 'Strong'], ['accent', 'Accent']]],
                        ['id' => 'width', 'type' => 'select', 'label' => 'Width', 'options' => [['sm', 'Small'], ['base', 'Base'], ['full', 'Full']]],
                    ]],
                ],
            ]),
            label: self::tr('Divider'),
            category: 'content',
            description: self::tr('A horizontal separator with decorative divider styles.'),
            keywords: ['line', 'separator', 'rule'],
            icon: '-',
        );
    }

    private static function makeSpacer(): BlockDefinition
    {
        return new BlockDefinition(
            type: 'bky/spacer',
            schema: [
                'type'       => 'object',
                'properties' => [
                    'size' => ['type' => 'string', 'enum' => ['xs', 'sm', 'base', 'lg', 'xl'], 'default' => 'base'],
                    'mobileSize' => ['type' => 'string', 'enum' => ['xs', 'sm', 'base', 'lg', 'xl'], 'default' => 'sm'],
                    'tabletSize' => ['type' => 'string', 'enum' => ['xs', 'sm', 'base', 'lg', 'xl'], 'default' => 'base'],
                    'desktopSize' => ['type' => 'string', 'enum' => ['xs', 'sm', 'base', 'lg', 'xl'], 'default' => 'lg'],
                ],
            ],
            variants: [
                'size' => [
                    'xs'   => 'h-4',
                    'sm'   => 'h-8',
                    'base' => 'h-12',
                    'lg'   => 'h-20',
                    'xl'   => 'h-32',
                ],
            ],
            renderer: new SpacerRenderer(),
            editorConfig: self::localizeEditorConfig([
                'tabs' => [
                    ['id' => 'content', 'label' => 'Content', 'controls' => [
                        ['id' => 'mobileSize', 'type' => 'select', 'label' => 'Mobile Size', 'options' => [['xs', 'Extra Small'], ['sm', 'Small'], ['base', 'Base'], ['lg', 'Large'], ['xl', 'Extra Large']]],
                        ['id' => 'tabletSize', 'type' => 'select', 'label' => 'Tablet Size', 'options' => [['xs', 'Extra Small'], ['sm', 'Small'], ['base', 'Base'], ['lg', 'Large'], ['xl', 'Extra Large']]],
                        ['id' => 'desktopSize', 'type' => 'select', 'label' => 'Desktop Size', 'options' => [['xs', 'Extra Small'], ['sm', 'Small'], ['base', 'Base'], ['lg', 'Large'], ['xl', 'Extra Large']]],
                    ]],
                ],
            ]),
            label: self::tr('Spacer'),
            category: 'content',
            description: self::tr('Responsive vertical space between blocks.'),
            keywords: ['gap', 'margin', 'spacing'],
            icon: '+',
        );
    }

    private static function makeList(): BlockDefinition
    {
        return new BlockDefinition(
            type: 'bky/list',
            schema: [
                'type'       => 'object',
                'properties' => [
                    'ordered' => ['type' => 'boolean', 'default' => false],
                    'items'   => ['type' => 'string', 'default' => "First item\nSecond item\nThird item"],
                ],
            ],
            variants: [],
            renderer: new ListRenderer(),
            label: self::tr('List'),
            category: 'content',
            description: self::tr('Bulleted or numbered list.'),
            keywords: ['ul', 'ol', 'bullets', 'numbered'],
            icon: 'L',
        );
    }

    private static function makeIcon(): BlockDefinition
    {
        return new BlockDefinition(
            type: 'bky/icon',
            label: self::tr('Icon'),
            category: 'basic',
            description: self::tr('Single icon with sizing, tone, surface, and optional link controls.'),
            keywords: ['symbol', 'emoji', 'icon'],
            icon: '☆',
            schema: [
                'type' => 'object',
                'properties' => [
                    'icon' => ['type' => 'string', 'default' => '★'],
                    'href' => ['type' => 'string', 'default' => ''],
                    'target' => ['type' => 'string', 'enum' => ['_self', '_blank'], 'default' => '_self'],
                    'size' => ['type' => 'string', 'enum' => ['sm', 'base', 'lg', 'xl'], 'default' => 'base'],
                    'tone' => ['type' => 'string', 'enum' => ['base', 'muted', 'accent', 'inverse'], 'default' => 'accent'],
                    'background' => ['type' => 'string', 'enum' => ['transparent', 'surface', 'elevated', 'accent', 'dark'], 'default' => 'transparent'],
                    'border' => ['type' => 'string', 'enum' => ['none', 'subtle', 'base', 'strong'], 'default' => 'none'],
                    'radius' => ['type' => 'string', 'enum' => ['none', 'sm', 'base', 'lg', 'full'], 'default' => 'full'],
                    'shadow' => ['type' => 'string', 'enum' => ['none', 'sm', 'base', 'md'], 'default' => 'none'],
                    'align' => ['type' => 'string', 'enum' => ['start', 'center', 'end'], 'default' => 'start'],
                ],
            ],
            variants: [
                'size' => ['sm' => 'text-base p-2', 'base' => 'text-2xl p-3', 'lg' => 'text-3xl p-4', 'xl' => 'text-4xl p-5'],
                'tone' => ['base' => 'text-text-base', 'muted' => 'text-text-muted', 'accent' => 'text-accent-base', 'inverse' => 'text-text-inverse'],
                'background' => ['transparent' => '', 'surface' => 'bg-surface-base', 'elevated' => 'bg-surface-elevated', 'accent' => 'bg-accent-subtle', 'dark' => 'bg-text-base'],
                'border' => ['none' => '', 'subtle' => 'border border-border-subtle', 'base' => 'border border-border-base', 'strong' => 'border border-border-strong'],
                'radius' => ['none' => 'rounded-none', 'sm' => 'rounded-sm', 'base' => 'rounded-base', 'lg' => 'rounded-lg', 'full' => 'rounded-full'],
                'shadow' => ['none' => 'shadow-none', 'sm' => 'shadow-sm', 'base' => 'shadow', 'md' => 'shadow-md'],
                'align' => ['start' => 'justify-start', 'center' => 'justify-center', 'end' => 'justify-end'],
            ],
            renderer: new IconRenderer(),
            editorConfig: self::localizeEditorConfig([
                'tabs' => [
                    ['id' => 'content', 'label' => 'Content', 'controls' => [
                        ['id' => 'icon', 'type' => 'text', 'label' => 'Icon'],
                        ['id' => 'href', 'type' => 'text', 'label' => 'Link'],
                        ['id' => 'target', 'type' => 'select', 'label' => 'Open in new tab', 'options' => [['_self', 'Same Tab'], ['_blank', 'New Tab']]],
                    ]],
                    ['id' => 'layout', 'label' => 'Layout', 'controls' => [
                        ['id' => 'align', 'type' => 'variant', 'label' => 'Alignment', 'variantKey' => 'align'],
                    ]],
                    ['id' => 'style', 'label' => 'Style', 'controls' => [
                        ['id' => 'size', 'type' => 'variant', 'label' => 'Size', 'variantKey' => 'size'],
                        ['id' => 'tone', 'type' => 'variant', 'label' => 'Tone', 'variantKey' => 'tone'],
                        ['id' => 'background', 'type' => 'variant', 'label' => 'Background', 'variantKey' => 'background'],
                        ['id' => 'border', 'type' => 'variant', 'label' => 'Border', 'variantKey' => 'border'],
                        ['id' => 'radius', 'type' => 'variant', 'label' => 'Radius', 'variantKey' => 'radius'],
                        ['id' => 'shadow', 'type' => 'variant', 'label' => 'Shadow', 'variantKey' => 'shadow'],
                    ]],
                ],
            ]),
        );
    }

    private static function makeIconBox(): BlockDefinition
    {
        return new BlockDefinition(
            type: 'bky/icon-box',
            label: self::tr('Icon Box'),
            category: 'basic',
            description: self::tr('Feature box with icon, title, copy, and optional link.'),
            keywords: ['feature', 'icon', 'card'],
            icon: '✦',
            schema: [
                'type' => 'object',
                'properties' => [
                    'icon' => ['type' => 'string', 'default' => '✨'],
                    'title' => ['type' => 'string', 'default' => 'Icon box'],
                    'text' => ['type' => 'string', 'default' => 'Describe this feature or benefit.'],
                    'href' => ['type' => 'string', 'default' => ''],
                    'target' => ['type' => 'string', 'enum' => ['_self', '_blank'], 'default' => '_self'],
                    'layout' => ['type' => 'string', 'enum' => ['vertical', 'horizontal'], 'default' => 'vertical'],
                    'align' => ['type' => 'string', 'enum' => ['start', 'center', 'end'], 'default' => 'start'],
                    'gap' => ['type' => 'string', 'enum' => ['sm', 'base', 'lg'], 'default' => 'base'],
                    'iconSize' => ['type' => 'string', 'enum' => ['sm', 'base', 'lg'], 'default' => 'base'],
                    'iconTone' => ['type' => 'string', 'enum' => ['base', 'muted', 'accent', 'inverse'], 'default' => 'accent'],
                    'background' => ['type' => 'string', 'enum' => ['transparent', 'surface', 'elevated', 'accent'], 'default' => 'transparent'],
                    'textColor' => ['type' => 'string', 'enum' => ['inherit', 'base', 'muted', 'inverse', 'accent'], 'default' => 'inherit'],
                    'border' => ['type' => 'string', 'enum' => ['none', 'subtle', 'base', 'strong'], 'default' => 'none'],
                    'radius' => ['type' => 'string', 'enum' => ['none', 'sm', 'base', 'lg', 'xl'], 'default' => 'lg'],
                    'shadow' => ['type' => 'string', 'enum' => ['none', 'sm', 'base', 'md'], 'default' => 'none'],
                ],
            ],
            variants: [
                'layout' => ['vertical' => 'flex-col', 'horizontal' => 'flex-row items-start'],
                'align' => ['start' => 'items-start text-start', 'center' => 'items-center text-center', 'end' => 'items-end text-end'],
                'gap' => ['sm' => 'gap-3 p-4', 'base' => 'gap-4 p-5', 'lg' => 'gap-6 p-6'],
                'iconSize' => ['sm' => 'text-xl', 'base' => 'text-3xl', 'lg' => 'text-4xl'],
                'iconTone' => ['base' => 'text-text-base', 'muted' => 'text-text-muted', 'accent' => 'text-accent-base', 'inverse' => 'text-text-inverse'],
                'background' => ['transparent' => '', 'surface' => 'bg-surface-base', 'elevated' => 'bg-surface-elevated', 'accent' => 'bg-accent-subtle'],
                'textColor' => ['inherit' => '', 'base' => 'text-text-base', 'muted' => 'text-text-muted', 'inverse' => 'text-text-inverse', 'accent' => 'text-accent-text'],
                'border' => ['none' => '', 'subtle' => 'border border-border-subtle', 'base' => 'border border-border-base', 'strong' => 'border border-border-strong'],
                'radius' => ['none' => 'rounded-none', 'sm' => 'rounded-sm', 'base' => 'rounded-base', 'lg' => 'rounded-lg', 'xl' => 'rounded-xl'],
                'shadow' => ['none' => 'shadow-none', 'sm' => 'shadow-sm', 'base' => 'shadow', 'md' => 'shadow-md'],
            ],
            renderer: new IconBoxRenderer(),
            editorConfig: self::localizeEditorConfig([
                'tabs' => [
                    ['id' => 'content', 'label' => 'Content', 'controls' => [
                        ['id' => 'icon', 'type' => 'text', 'label' => 'Icon'],
                        ['id' => 'title', 'type' => 'text', 'label' => 'Title'],
                        ['id' => 'text', 'type' => 'richtext', 'label' => 'Text'],
                        ['id' => 'href', 'type' => 'text', 'label' => 'Link'],
                        ['id' => 'target', 'type' => 'select', 'label' => 'Open in new tab', 'options' => [['_self', 'Same Tab'], ['_blank', 'New Tab']]],
                    ]],
                    ['id' => 'layout', 'label' => 'Layout', 'controls' => [
                        ['id' => 'layout', 'type' => 'variant', 'label' => 'Layout Direction', 'variantKey' => 'layout'],
                        ['id' => 'align', 'type' => 'variant', 'label' => 'Alignment', 'variantKey' => 'align'],
                        ['id' => 'gap', 'type' => 'variant', 'label' => 'Spacing', 'variantKey' => 'gap'],
                    ]],
                    ['id' => 'style', 'label' => 'Style', 'controls' => [
                        ['id' => 'iconSize', 'type' => 'variant', 'label' => 'Icon Size', 'variantKey' => 'iconSize'],
                        ['id' => 'iconTone', 'type' => 'variant', 'label' => 'Icon Tone', 'variantKey' => 'iconTone'],
                        ['id' => 'background', 'type' => 'variant', 'label' => 'Background', 'variantKey' => 'background'],
                        ['id' => 'textColor', 'type' => 'variant', 'label' => 'Text Color', 'variantKey' => 'textColor'],
                        ['id' => 'border', 'type' => 'variant', 'label' => 'Border', 'variantKey' => 'border'],
                        ['id' => 'radius', 'type' => 'variant', 'label' => 'Radius', 'variantKey' => 'radius'],
                        ['id' => 'shadow', 'type' => 'variant', 'label' => 'Shadow', 'variantKey' => 'shadow'],
                    ]],
                ],
            ]),
        );
    }

    private static function makeIconList(): BlockDefinition
    {
        return new BlockDefinition(
            type: 'bky/icon-list',
            label: self::tr('Icon List'),
            category: 'basic',
            description: self::tr('Vertical list of items with a repeated icon marker.'),
            keywords: ['list', 'benefits', 'checklist'],
            icon: '✓',
            schema: [
                'type' => 'object',
                'properties' => [
                    'icon' => ['type' => 'string', 'default' => '✓'],
                    'items' => ['type' => 'string', 'default' => "First item\nSecond item\nThird item", 'listFields' => [ [ 'key' => 'item', 'label' => 'Item' ] ]],
                    'gap' => ['type' => 'string', 'enum' => ['sm', 'base', 'lg'], 'default' => 'base'],
                    'tone' => ['type' => 'string', 'enum' => ['base', 'muted', 'accent'], 'default' => 'base'],
                ],
            ],
            variants: [
                'gap' => ['sm' => 'space-y-2', 'base' => 'space-y-3', 'lg' => 'space-y-4'],
                'tone' => ['base' => 'text-text-base', 'muted' => 'text-text-muted', 'accent' => 'text-accent-text'],
            ],
            renderer: new IconListRenderer(),
            editorConfig: self::localizeEditorConfig([
                'tabs' => [
                    ['id' => 'content', 'label' => 'Content', 'controls' => [
                        ['id' => 'icon', 'type' => 'text', 'label' => 'Icon'],
                        ['id' => 'items', 'type' => 'richtext', 'label' => 'Items'],
                    ]],
                    ['id' => 'layout', 'label' => 'Layout', 'controls' => [
                        ['id' => 'gap', 'type' => 'variant', 'label' => 'Spacing', 'variantKey' => 'gap'],
                    ]],
                    ['id' => 'style', 'label' => 'Style', 'controls' => [
                        ['id' => 'tone', 'type' => 'variant', 'label' => 'Tone', 'variantKey' => 'tone'],
                    ]],
                ],
            ]),
        );
    }

    private static function makeImageBox(): BlockDefinition
    {
        return new BlockDefinition(
            type: 'bky/image-box',
            label: self::tr('Image Box'),
            category: 'basic',
            description: self::tr('Media card with image, title, copy, and optional link.'),
            keywords: ['image', 'card', 'feature'],
            icon: '▣',
            schema: [
                'type' => 'object',
                'properties' => [
                    'attachmentId' => ['type' => 'integer', 'default' => 0],
                    'title' => ['type' => 'string', 'default' => 'Image box'],
                    'text' => ['type' => 'string', 'default' => 'Describe this image or linked content.'],
                    'href' => ['type' => 'string', 'default' => ''],
                    'target' => ['type' => 'string', 'enum' => ['_self', '_blank'], 'default' => '_self'],
                    'layout' => ['type' => 'string', 'enum' => ['vertical', 'horizontal'], 'default' => 'vertical'],
                    'align' => ['type' => 'string', 'enum' => ['start', 'center', 'end'], 'default' => 'start'],
                    'gap' => ['type' => 'string', 'enum' => ['sm', 'base', 'lg'], 'default' => 'base'],
                    'imageWidth' => ['type' => 'string', 'enum' => ['sm', 'base', 'lg', 'full'], 'default' => 'full'],
                    'rounded' => ['type' => 'string', 'enum' => ['none', 'base', 'lg', 'full'], 'default' => 'base'],
                    'aspectRatio' => ['type' => 'string', 'enum' => ['auto', 'square', 'video', 'wide'], 'default' => 'wide'],
                    'background' => ['type' => 'string', 'enum' => ['transparent', 'surface', 'elevated', 'accent'], 'default' => 'transparent'],
                    'textColor' => ['type' => 'string', 'enum' => ['inherit', 'base', 'muted', 'inverse', 'accent'], 'default' => 'inherit'],
                    'border' => ['type' => 'string', 'enum' => ['none', 'subtle', 'base', 'strong'], 'default' => 'none'],
                    'radius' => ['type' => 'string', 'enum' => ['none', 'sm', 'base', 'lg', 'xl'], 'default' => 'lg'],
                    'shadow' => ['type' => 'string', 'enum' => ['none', 'sm', 'base', 'md'], 'default' => 'none'],
                ],
            ],
            variants: [
                'layout' => ['vertical' => 'flex-col', 'horizontal' => 'flex-row items-start'],
                'align' => ['start' => 'items-start text-start', 'center' => 'items-center text-center', 'end' => 'items-end text-end'],
                'gap' => ['sm' => 'gap-3 p-4', 'base' => 'gap-4 p-5', 'lg' => 'gap-6 p-6'],
                'imageWidth' => ['sm' => 'w-24', 'base' => 'w-36', 'lg' => 'w-48', 'full' => 'w-full'],
                'rounded' => ['none' => '', 'base' => 'rounded-image', 'lg' => 'rounded-2xl', 'full' => 'rounded-full'],
                'aspectRatio' => ['auto' => '', 'square' => 'aspect-square object-cover', 'video' => 'aspect-video object-cover', 'wide' => 'aspect-[21/9] object-cover'],
                'background' => ['transparent' => '', 'surface' => 'bg-surface-base', 'elevated' => 'bg-surface-elevated', 'accent' => 'bg-accent-subtle'],
                'textColor' => ['inherit' => '', 'base' => 'text-text-base', 'muted' => 'text-text-muted', 'inverse' => 'text-text-inverse', 'accent' => 'text-accent-text'],
                'border' => ['none' => '', 'subtle' => 'border border-border-subtle', 'base' => 'border border-border-base', 'strong' => 'border border-border-strong'],
                'radius' => ['none' => 'rounded-none', 'sm' => 'rounded-sm', 'base' => 'rounded-base', 'lg' => 'rounded-lg', 'xl' => 'rounded-xl'],
                'shadow' => ['none' => 'shadow-none', 'sm' => 'shadow-sm', 'base' => 'shadow', 'md' => 'shadow-md'],
            ],
            renderer: new ImageBoxRenderer(),
            editorConfig: self::localizeEditorConfig([
                'tabs' => [
                    ['id' => 'content', 'label' => 'Content', 'controls' => [
                        ['id' => 'attachmentId', 'type' => 'media', 'label' => 'Image', 'mediaType' => 'image'],
                        ['id' => 'title', 'type' => 'text', 'label' => 'Title'],
                        ['id' => 'text', 'type' => 'richtext', 'label' => 'Text'],
                        ['id' => 'href', 'type' => 'text', 'label' => 'Link'],
                        ['id' => 'target', 'type' => 'select', 'label' => 'Open in new tab', 'options' => [['_self', 'Same Tab'], ['_blank', 'New Tab']]],
                    ]],
                    ['id' => 'layout', 'label' => 'Layout', 'controls' => [
                        ['id' => 'layout', 'type' => 'variant', 'label' => 'Layout Direction', 'variantKey' => 'layout'],
                        ['id' => 'align', 'type' => 'variant', 'label' => 'Alignment', 'variantKey' => 'align'],
                        ['id' => 'gap', 'type' => 'variant', 'label' => 'Spacing', 'variantKey' => 'gap'],
                        ['id' => 'imageWidth', 'type' => 'variant', 'label' => 'Image Width', 'variantKey' => 'imageWidth'],
                    ]],
                    ['id' => 'style', 'label' => 'Style', 'controls' => [
                        ['id' => 'rounded', 'type' => 'variant', 'label' => 'Rounded Corners', 'variantKey' => 'rounded'],
                        ['id' => 'aspectRatio', 'type' => 'variant', 'label' => 'Aspect Ratio', 'variantKey' => 'aspectRatio'],
                        ['id' => 'background', 'type' => 'variant', 'label' => 'Background', 'variantKey' => 'background'],
                        ['id' => 'textColor', 'type' => 'variant', 'label' => 'Text Color', 'variantKey' => 'textColor'],
                        ['id' => 'border', 'type' => 'variant', 'label' => 'Border', 'variantKey' => 'border'],
                        ['id' => 'radius', 'type' => 'variant', 'label' => 'Radius', 'variantKey' => 'radius'],
                        ['id' => 'shadow', 'type' => 'variant', 'label' => 'Shadow', 'variantKey' => 'shadow'],
                    ]],
                ],
            ]),
        );
    }

    private static function makeAlert(): BlockDefinition
    {
        return new BlockDefinition(
            type: 'bky/alert',
            label: self::tr('Alert'),
            category: 'basic',
            description: self::tr('Inline alert box for status, warning, or error messaging.'),
            keywords: ['notice', 'warning', 'message'],
            icon: '!',
            schema: [
                'type' => 'object',
                'properties' => [
                    'title' => ['type' => 'string', 'default' => 'Heads up'],
                    'message' => ['type' => 'string', 'default' => 'Share an important update or status message.'],
                    'icon' => ['type' => 'string', 'default' => 'ℹ'],
                    'tone' => ['type' => 'string', 'enum' => ['info', 'success', 'warning', 'danger'], 'default' => 'info'],
                    'radius' => ['type' => 'string', 'enum' => ['none', 'base', 'lg', 'xl'], 'default' => 'lg'],
                ],
            ],
            variants: [
                'tone' => ['info' => 'border-sky-300 bg-sky-50 text-sky-900', 'success' => 'border-emerald-300 bg-emerald-50 text-emerald-900', 'warning' => 'border-amber-300 bg-amber-50 text-amber-900', 'danger' => 'border-rose-300 bg-rose-50 text-rose-900'],
                'radius' => ['none' => 'rounded-none', 'base' => 'rounded-base', 'lg' => 'rounded-lg', 'xl' => 'rounded-xl'],
            ],
            renderer: new AlertRenderer(),
            editorConfig: self::localizeEditorConfig([
                'tabs' => [
                    ['id' => 'content', 'label' => 'Content', 'controls' => [
                        ['id' => 'title', 'type' => 'text', 'label' => 'Title'],
                        ['id' => 'message', 'type' => 'richtext', 'label' => 'Message'],
                        ['id' => 'icon', 'type' => 'text', 'label' => 'Icon'],
                    ]],
                    ['id' => 'style', 'label' => 'Style', 'controls' => [
                        ['id' => 'tone', 'type' => 'variant', 'label' => 'Tone', 'variantKey' => 'tone'],
                        ['id' => 'radius', 'type' => 'variant', 'label' => 'Radius', 'variantKey' => 'radius'],
                    ]],
                ],
            ]),
        );
    }

    private static function makeProgressBar(): BlockDefinition
    {
        return new BlockDefinition(
            type: 'bky/progress-bar',
            label: self::tr('Progress Bar'),
            category: 'basic',
            description: self::tr('Progress indicator with label, percentage, tone, and size controls.'),
            keywords: ['progress', 'meter', 'loading'],
            icon: '▤',
            schema: [
                'type' => 'object',
                'properties' => [
                    'label' => ['type' => 'string', 'default' => 'Progress'],
                    'value' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 100, 'default' => 65],
                    'showLabel' => ['type' => 'boolean', 'default' => true],
                    'striped' => ['type' => 'boolean', 'default' => false],
                    'size' => ['type' => 'string', 'enum' => ['sm', 'base', 'lg'], 'default' => 'base'],
                    'tone' => ['type' => 'string', 'enum' => ['accent', 'success', 'warning', 'danger'], 'default' => 'accent'],
                    'radius' => ['type' => 'string', 'enum' => ['none', 'base', 'lg'], 'default' => 'base'],
                ],
            ],
            variants: [
                'size' => ['sm' => 'h-2', 'base' => 'h-3', 'lg' => 'h-4'],
                'tone' => ['accent' => 'bg-accent-base', 'success' => 'bg-feedback-success', 'warning' => 'bg-feedback-warning', 'danger' => 'bg-feedback-danger'],
                'radius' => ['none' => 'rounded-none', 'base' => 'rounded-full', 'lg' => 'rounded-full'],
            ],
            renderer: new ProgressBarRenderer(),
            editorConfig: self::localizeEditorConfig([
                'tabs' => [
                    ['id' => 'content', 'label' => 'Content', 'controls' => [
                        ['id' => 'label', 'type' => 'text', 'label' => 'Label'],
                        ['id' => 'value', 'type' => 'range', 'label' => 'Value', 'min' => 0, 'max' => 100, 'step' => 1],
                        ['id' => 'showLabel', 'type' => 'toggle', 'label' => 'Show Label'],
                        ['id' => 'striped', 'type' => 'toggle', 'label' => 'Striped'],
                    ]],
                    ['id' => 'style', 'label' => 'Style', 'controls' => [
                        ['id' => 'size', 'type' => 'variant', 'label' => 'Size', 'variantKey' => 'size'],
                        ['id' => 'tone', 'type' => 'variant', 'label' => 'Tone', 'variantKey' => 'tone'],
                        ['id' => 'radius', 'type' => 'variant', 'label' => 'Radius', 'variantKey' => 'radius'],
                    ]],
                ],
            ]),
        );
    }

    private static function makeCounter(): BlockDefinition
    {
        return new BlockDefinition(
            type: 'bky/counter',
            label: self::tr('Counter'),
            category: 'basic',
            description: self::tr('Large numeric highlight with optional prefix, suffix, and caption.'),
            keywords: ['metric', 'stat', 'number'],
            icon: '#',
            schema: [
                'type' => 'object',
                'properties' => [
                    'prefix' => ['type' => 'string', 'default' => ''],
                    'value' => ['type' => 'integer', 'default' => 128],
                    'suffix' => ['type' => 'string', 'default' => '+'],
                    'label' => ['type' => 'string', 'default' => 'Satisfied customers'],
                    'align' => ['type' => 'string', 'enum' => ['start', 'center', 'end'], 'default' => 'start'],
                    'size' => ['type' => 'string', 'enum' => ['sm', 'base', 'lg'], 'default' => 'base'],
                    'tone' => ['type' => 'string', 'enum' => ['base', 'muted', 'accent'], 'default' => 'accent'],
                ],
            ],
            variants: [
                'align' => ['start' => 'items-start text-start', 'center' => 'items-center text-center', 'end' => 'items-end text-end'],
                'size' => ['sm' => 'text-3xl', 'base' => 'text-5xl', 'lg' => 'text-6xl'],
                'tone' => ['base' => 'text-text-base', 'muted' => 'text-text-muted', 'accent' => 'text-accent-text'],
            ],
            renderer: new CounterRenderer(),
            editorConfig: self::localizeEditorConfig([
                'tabs' => [
                    ['id' => 'content', 'label' => 'Content', 'controls' => [
                        ['id' => 'prefix', 'type' => 'text', 'label' => 'Prefix'],
                        ['id' => 'value', 'type' => 'number', 'label' => 'Value', 'min' => 0, 'step' => 1],
                        ['id' => 'suffix', 'type' => 'text', 'label' => 'Suffix'],
                        ['id' => 'label', 'type' => 'text', 'label' => 'Caption'],
                    ]],
                    ['id' => 'layout', 'label' => 'Layout', 'controls' => [
                        ['id' => 'align', 'type' => 'variant', 'label' => 'Alignment', 'variantKey' => 'align'],
                    ]],
                    ['id' => 'style', 'label' => 'Style', 'controls' => [
                        ['id' => 'size', 'type' => 'variant', 'label' => 'Size', 'variantKey' => 'size'],
                        ['id' => 'tone', 'type' => 'variant', 'label' => 'Tone', 'variantKey' => 'tone'],
                    ]],
                ],
            ]),
        );
    }

    private static function makeStarRating(): BlockDefinition
    {
        return new BlockDefinition(
            type: 'bky/star-rating',
            label: self::tr('Star Rating'),
            category: 'basic',
            description: self::tr('Visual star rating with numeric score and optional label.'),
            keywords: ['rating', 'review', 'stars'],
            icon: '★',
            schema: [
                'type' => 'object',
                'properties' => [
                    'value' => ['type' => 'number', 'minimum' => 0, 'maximum' => 5, 'default' => 4.5],
                    'max' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 10, 'default' => 5],
                    'label' => ['type' => 'string', 'default' => '4.5 average rating'],
                    'size' => ['type' => 'string', 'enum' => ['sm', 'base', 'lg'], 'default' => 'base'],
                    'tone' => ['type' => 'string', 'enum' => ['gold', 'accent', 'muted'], 'default' => 'gold'],
                    'align' => ['type' => 'string', 'enum' => ['start', 'center', 'end'], 'default' => 'start'],
                ],
            ],
            variants: [
                'size' => ['sm' => 'text-lg', 'base' => 'text-2xl', 'lg' => 'text-3xl'],
                'tone' => ['gold' => 'text-amber-400', 'accent' => 'text-accent-base', 'muted' => 'text-text-muted'],
                'align' => ['start' => 'justify-start', 'center' => 'justify-center', 'end' => 'justify-end'],
            ],
            renderer: new StarRatingRenderer(),
            editorConfig: self::localizeEditorConfig([
                'tabs' => [
                    ['id' => 'content', 'label' => 'Content', 'controls' => [
                        ['id' => 'value', 'type' => 'range', 'label' => 'Value', 'min' => 0, 'max' => 5, 'step' => 0.5],
                        ['id' => 'max', 'type' => 'number', 'label' => 'Max Stars', 'min' => 1, 'max' => 10, 'step' => 1],
                        ['id' => 'label', 'type' => 'text', 'label' => 'Label'],
                    ]],
                    ['id' => 'layout', 'label' => 'Layout', 'controls' => [
                        ['id' => 'align', 'type' => 'variant', 'label' => 'Alignment', 'variantKey' => 'align'],
                    ]],
                    ['id' => 'style', 'label' => 'Style', 'controls' => [
                        ['id' => 'size', 'type' => 'variant', 'label' => 'Size', 'variantKey' => 'size'],
                        ['id' => 'tone', 'type' => 'variant', 'label' => 'Tone', 'variantKey' => 'tone'],
                    ]],
                ],
            ]),
        );
    }

    private static function makeAnchor(): BlockDefinition
    {
        return new BlockDefinition(
            type: 'bky/anchor',
            label: self::tr('Anchor'),
            category: 'basic',
            description: self::tr('Invisible scroll target with an editable anchor id.'),
            keywords: ['anchor', 'jump link', 'scroll'],
            icon: '#',
            schema: [
                'type' => 'object',
                'properties' => [
                    'anchorId' => ['type' => 'string', 'default' => 'section-anchor'],
                    'label' => ['type' => 'string', 'default' => 'Section anchor'],
                ],
            ],
            variants: [],
            renderer: new AnchorRenderer(),
            editorConfig: self::localizeEditorConfig([
                'tabs' => [
                    ['id' => 'content', 'label' => 'Content', 'controls' => [
                        ['id' => 'anchorId', 'type' => 'text', 'label' => 'Anchor ID'],
                        ['id' => 'label', 'type' => 'text', 'label' => 'Editor Label'],
                    ]],
                ],
            ]),
        );
    }

    private static function makeSocialIcons(): BlockDefinition
    {
        return new BlockDefinition(
            type: 'bky/social-icons',
            label: self::tr('Social Icons'),
            category: 'basic',
            description: self::tr('Social profile links rendered as icon buttons or chips.'),
            keywords: ['social', 'profiles', 'icons'],
            icon: '@',
            schema: [
                'type' => 'object',
                'properties' => [
                    'items' => ['type' => 'string', 'default' => "facebook|https://facebook.com\ninstagram|https://instagram.com\nlinkedin|https://linkedin.com", 'listFields' => [ [ 'key' => 'network', 'label' => 'Network' ], [ 'key' => 'url', 'label' => 'URL', 'kind' => 'url' ] ]],
                    'layout' => ['type' => 'string', 'enum' => ['row', 'column'], 'default' => 'row'],
                    'gap' => ['type' => 'string', 'enum' => ['sm', 'base', 'lg'], 'default' => 'base'],
                    'align' => ['type' => 'string', 'enum' => ['start', 'center', 'end'], 'default' => 'start'],
                    'size' => ['type' => 'string', 'enum' => ['sm', 'base', 'lg'], 'default' => 'base'],
                    'tone' => ['type' => 'string', 'enum' => ['brand', 'neutral', 'ghost'], 'default' => 'brand'],
                    'radius' => ['type' => 'string', 'enum' => ['none', 'base', 'button', 'full'], 'default' => 'full'],
                ],
            ],
            variants: [
                'layout' => ['row' => 'flex-row flex-wrap', 'column' => 'flex-col'],
                'gap' => ['sm' => 'gap-2', 'base' => 'gap-3', 'lg' => 'gap-4'],
                'align' => ['start' => 'items-start justify-start', 'center' => 'items-center justify-center', 'end' => 'items-end justify-end'],
                'size' => ['sm' => 'h-9 min-w-9 px-3 text-sm', 'base' => 'h-11 min-w-11 px-4 text-base', 'lg' => 'h-12 min-w-12 px-5 text-lg'],
                'tone' => ['brand' => 'bg-accent-base text-text-on-accent', 'neutral' => 'bg-surface-elevated text-text-base border border-border-base', 'ghost' => 'bg-transparent text-text-base'],
                'radius' => ['none' => 'rounded-none', 'base' => 'rounded-base', 'button' => 'rounded-button', 'full' => 'rounded-full'],
            ],
            renderer: new SocialIconsRenderer(),
            editorConfig: self::localizeEditorConfig([
                'tabs' => [
                    ['id' => 'content', 'label' => 'Content', 'controls' => [
                        ['id' => 'items', 'type' => 'richtext', 'label' => 'Items'],
                    ]],
                    ['id' => 'layout', 'label' => 'Layout', 'controls' => [
                        ['id' => 'layout', 'type' => 'variant', 'label' => 'Layout Direction', 'variantKey' => 'layout'],
                        ['id' => 'gap', 'type' => 'variant', 'label' => 'Spacing', 'variantKey' => 'gap'],
                        ['id' => 'align', 'type' => 'variant', 'label' => 'Alignment', 'variantKey' => 'align'],
                    ]],
                    ['id' => 'style', 'label' => 'Style', 'controls' => [
                        ['id' => 'size', 'type' => 'variant', 'label' => 'Size', 'variantKey' => 'size'],
                        ['id' => 'tone', 'type' => 'variant', 'label' => 'Tone', 'variantKey' => 'tone'],
                        ['id' => 'radius', 'type' => 'variant', 'label' => 'Radius', 'variantKey' => 'radius'],
                    ]],
                ],
            ]),
        );
    }

    private static function makeShareButtons(): BlockDefinition
    {
        return new BlockDefinition(
            type: 'bky/share-buttons',
            label: self::tr('Share Buttons'),
            category: 'basic',
            description: self::tr('Prebuilt share actions for the current page URL.'),
            keywords: ['share', 'social', 'buttons'],
            icon: '⇪',
            schema: [
                'type' => 'object',
                'properties' => [
                    'networks' => ['type' => 'string', 'default' => 'facebook,x,linkedin'],
                    'title' => ['type' => 'string', 'default' => 'Share this page'],
                    'gap' => ['type' => 'string', 'enum' => ['sm', 'base', 'lg'], 'default' => 'base'],
                    'align' => ['type' => 'string', 'enum' => ['start', 'center', 'end'], 'default' => 'start'],
                    'size' => ['type' => 'string', 'enum' => ['sm', 'base', 'lg'], 'default' => 'base'],
                    'tone' => ['type' => 'string', 'enum' => ['brand', 'neutral', 'ghost'], 'default' => 'neutral'],
                    'radius' => ['type' => 'string', 'enum' => ['none', 'button', 'full'], 'default' => 'button'],
                ],
            ],
            variants: [
                'gap' => ['sm' => 'gap-2', 'base' => 'gap-3', 'lg' => 'gap-4'],
                'align' => ['start' => 'justify-start', 'center' => 'justify-center', 'end' => 'justify-end'],
                'size' => ['sm' => 'h-9 px-3 text-sm', 'base' => 'h-10 px-4 text-sm', 'lg' => 'h-12 px-5 text-base'],
                'tone' => ['brand' => 'bg-accent-base text-text-on-accent', 'neutral' => 'bg-surface-elevated text-text-base border border-border-base', 'ghost' => 'bg-transparent text-text-base'],
                'radius' => ['none' => 'rounded-none', 'button' => 'rounded-button', 'full' => 'rounded-full'],
            ],
            renderer: new ShareButtonsRenderer(),
            editorConfig: self::localizeEditorConfig([
                'tabs' => [
                    ['id' => 'content', 'label' => 'Content', 'controls' => [
                        ['id' => 'networks', 'type' => 'text', 'label' => 'Networks'],
                        ['id' => 'title', 'type' => 'text', 'label' => 'Share Title'],
                    ]],
                    ['id' => 'layout', 'label' => 'Layout', 'controls' => [
                        ['id' => 'gap', 'type' => 'variant', 'label' => 'Spacing', 'variantKey' => 'gap'],
                        ['id' => 'align', 'type' => 'variant', 'label' => 'Alignment', 'variantKey' => 'align'],
                    ]],
                    ['id' => 'style', 'label' => 'Style', 'controls' => [
                        ['id' => 'size', 'type' => 'variant', 'label' => 'Size', 'variantKey' => 'size'],
                        ['id' => 'tone', 'type' => 'variant', 'label' => 'Tone', 'variantKey' => 'tone'],
                        ['id' => 'radius', 'type' => 'variant', 'label' => 'Radius', 'variantKey' => 'radius'],
                    ]],
                ],
            ]),
        );
    }

    private static function makeSearchForm(): BlockDefinition
    {
        return new BlockDefinition(
            type: 'bky/search-form',
            label: self::tr('Search Form'),
            category: 'basic',
            description: self::tr('Site search form with inline or stacked layout.'),
            keywords: ['search', 'form', 'query'],
            icon: '?',
            schema: [
                'type' => 'object',
                'properties' => [
                    'placeholder' => ['type' => 'string', 'default' => 'Search…'],
                    'buttonLabel' => ['type' => 'string', 'default' => 'Search'],
                    'layout' => ['type' => 'string', 'enum' => ['inline', 'stacked'], 'default' => 'inline'],
                    'gap' => ['type' => 'string', 'enum' => ['sm', 'base', 'lg'], 'default' => 'base'],
                    'buttonTone' => ['type' => 'string', 'enum' => ['primary', 'neutral', 'ghost'], 'default' => 'primary'],
                    'radius' => ['type' => 'string', 'enum' => ['none', 'button', 'full'], 'default' => 'button'],
                ],
            ],
            variants: [
                'layout' => ['inline' => 'flex-row', 'stacked' => 'flex-col'],
                'gap' => ['sm' => 'gap-2', 'base' => 'gap-3', 'lg' => 'gap-4'],
                'buttonTone' => ['primary' => 'bg-accent-base text-text-on-accent', 'neutral' => 'bg-surface-elevated text-text-base border border-border-base', 'ghost' => 'bg-transparent text-text-base border border-border-base'],
                'radius' => ['none' => 'rounded-none', 'button' => 'rounded-button', 'full' => 'rounded-full'],
            ],
            renderer: new SearchFormRenderer(),
            editorConfig: self::localizeEditorConfig([
                'tabs' => [
                    ['id' => 'content', 'label' => 'Content', 'controls' => [
                        ['id' => 'placeholder', 'type' => 'text', 'label' => 'Placeholder'],
                        ['id' => 'buttonLabel', 'type' => 'text', 'label' => 'Button Label'],
                    ]],
                    ['id' => 'layout', 'label' => 'Layout', 'controls' => [
                        ['id' => 'layout', 'type' => 'variant', 'label' => 'Layout Direction', 'variantKey' => 'layout'],
                        ['id' => 'gap', 'type' => 'variant', 'label' => 'Spacing', 'variantKey' => 'gap'],
                    ]],
                    ['id' => 'style', 'label' => 'Style', 'controls' => [
                        ['id' => 'buttonTone', 'type' => 'variant', 'label' => 'Button Tone', 'variantKey' => 'buttonTone'],
                        ['id' => 'radius', 'type' => 'variant', 'label' => 'Radius', 'variantKey' => 'radius'],
                    ]],
                ],
            ]),
        );
    }

    private static function makeNavMenu(): BlockDefinition
    {
        return new BlockDefinition(
            type: 'bky/nav-menu',
            label: self::tr('Nav Menu'),
            category: 'basic',
            description: self::tr('Navigation menu sourced from a WP location or a manual fallback list.'),
            keywords: ['menu', 'navigation', 'header'],
            icon: '≡',
            schema: [
                'type' => 'object',
                'properties' => [
                    'menuLocation' => ['type' => 'string', 'default' => ''],
                    'items' => ['type' => 'string', 'default' => "Home|/\nAbout|/about\nContact|/contact", 'listFields' => [ [ 'key' => 'label', 'label' => 'Label' ], [ 'key' => 'url', 'label' => 'URL', 'kind' => 'url' ] ]],
                    'layout' => ['type' => 'string', 'enum' => ['row', 'column'], 'default' => 'row'],
                    'gap' => ['type' => 'string', 'enum' => ['sm', 'base', 'lg'], 'default' => 'base'],
                    'align' => ['type' => 'string', 'enum' => ['start', 'center', 'end'], 'default' => 'start'],
                ],
            ],
            variants: [
                'layout' => ['row' => 'flex-row flex-wrap', 'column' => 'flex-col'],
                'gap' => ['sm' => 'gap-3', 'base' => 'gap-4', 'lg' => 'gap-6'],
                'align' => ['start' => 'justify-start', 'center' => 'justify-center', 'end' => 'justify-end'],
            ],
            renderer: new NavMenuRenderer(),
            editorConfig: self::localizeEditorConfig([
                'tabs' => [
                    ['id' => 'content', 'label' => 'Content', 'controls' => [
                        ['id' => 'menuLocation', 'type' => 'text', 'label' => 'Menu Location'],
                        ['id' => 'items', 'type' => 'richtext', 'label' => 'Fallback Items'],
                    ]],
                    ['id' => 'layout', 'label' => 'Layout', 'controls' => [
                        ['id' => 'layout', 'type' => 'variant', 'label' => 'Layout Direction', 'variantKey' => 'layout'],
                        ['id' => 'gap', 'type' => 'variant', 'label' => 'Spacing', 'variantKey' => 'gap'],
                        ['id' => 'align', 'type' => 'variant', 'label' => 'Alignment', 'variantKey' => 'align'],
                    ]],
                ],
            ]),
        );
    }

    private static function makeBreadcrumbs(): BlockDefinition
    {
        return new BlockDefinition(
            type: 'bky/breadcrumbs',
            label: self::tr('Breadcrumbs'),
            category: 'basic',
            description: self::tr('Simple breadcrumb trail for the current page context.'),
            keywords: ['breadcrumb', 'trail', 'navigation'],
            icon: '/',
            schema: [
                'type' => 'object',
                'properties' => [
                    'separator' => ['type' => 'string', 'default' => '/'],
                    'showCurrent' => ['type' => 'boolean', 'default' => true],
                ],
            ],
            variants: [],
            renderer: new BreadcrumbsRenderer(),
            editorConfig: self::localizeEditorConfig([
                'tabs' => [
                    ['id' => 'content', 'label' => 'Content', 'controls' => [
                        ['id' => 'separator', 'type' => 'text', 'label' => 'Separator'],
                        ['id' => 'showCurrent', 'type' => 'toggle', 'label' => 'Show Current Page'],
                    ]],
                ],
            ]),
        );
    }

    private static function makePostsList(): BlockDefinition
    {
        return new BlockDefinition(
            type: 'bky/posts-list',
            label: self::tr('Posts List'),
            category: 'basic',
            description: self::tr('Vertical list of recent posts from a selected post type.'),
            keywords: ['posts', 'query', 'list'],
            icon: '≣',
            schema: [
                'type' => 'object',
                'properties' => [
                    'postType' => ['type' => 'string', 'default' => 'post'],
                    'perPage' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 12, 'default' => 3],
                    'showExcerpt' => ['type' => 'boolean', 'default' => true],
                    'structureId' => ['type' => 'integer', 'minimum' => 0, 'default' => 0],
                ],
            ],
            variants: [],
            renderer: new PostsListRenderer(),
            editorConfig: self::localizeEditorConfig([
                'tabs' => [
                    ['id' => 'content', 'label' => 'Content', 'controls' => [
                        ['id' => 'postType', 'type' => 'text', 'label' => 'Post Type'],
                        ['id' => 'perPage', 'type' => 'number', 'label' => 'Posts Per Page', 'min' => 1, 'max' => 12, 'step' => 1],
                        ['id' => 'showExcerpt', 'type' => 'toggle', 'label' => 'Show Excerpt'],
                        ['id' => 'structureId', 'type' => 'select', 'label' => 'Structure'],
                    ]],
                ],
            ]),
        );
    }

    private static function makePostsGrid(): BlockDefinition
    {
        return new BlockDefinition(
            type: 'bky/posts-grid',
            label: self::tr('Posts Grid'),
            category: 'basic',
            description: self::tr('Grid of recent posts with configurable columns and excerpt display.'),
            keywords: ['posts', 'grid', 'query'],
            icon: '▦',
            schema: [
                'type' => 'object',
                'properties' => [
                    'postType' => ['type' => 'string', 'default' => 'post'],
                    'perPage' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 12, 'default' => 6],
                    'columns' => ['type' => 'string', 'enum' => ['2', '3', '4'], 'default' => '3'],
                    'showExcerpt' => ['type' => 'boolean', 'default' => true],
                    'structureId' => ['type' => 'integer', 'minimum' => 0, 'default' => 0],
                ],
            ],
            variants: [
                'columns' => ['2' => 'grid-cols-1 md:grid-cols-2', '3' => 'grid-cols-1 md:grid-cols-2 lg:grid-cols-3', '4' => 'grid-cols-1 md:grid-cols-2 xl:grid-cols-4'],
            ],
            renderer: new PostsGridRenderer(),
            editorConfig: self::localizeEditorConfig([
                'tabs' => [
                    ['id' => 'content', 'label' => 'Content', 'controls' => [
                        ['id' => 'postType', 'type' => 'text', 'label' => 'Post Type'],
                        ['id' => 'perPage', 'type' => 'number', 'label' => 'Posts Per Page', 'min' => 1, 'max' => 12, 'step' => 1],
                        ['id' => 'showExcerpt', 'type' => 'toggle', 'label' => 'Show Excerpt'],
                        ['id' => 'structureId', 'type' => 'select', 'label' => 'Structure'],
                    ]],
                    ['id' => 'layout', 'label' => 'Layout', 'controls' => [
                        ['id' => 'columns', 'type' => 'variant', 'label' => 'Columns', 'variantKey' => 'columns'],
                    ]],
                ],
            ]),
        );
    }

    private static function makeFeaturedPosts(): BlockDefinition
    {
        return new BlockDefinition(
            type: 'bky/featured-posts',
            label: self::tr('Featured Posts'),
            category: 'query',
            description: self::tr('Featured or sticky posts presented in a list or grid layout.'),
            keywords: ['featured', 'sticky', 'posts', 'query'],
            icon: '★',
            schema: [
                'type' => 'object',
                'properties' => [
                    'title' => ['type' => 'string', 'default' => 'Featured posts'],
                    'perPage' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 12, 'default' => 3],
                    'layout' => ['type' => 'string', 'enum' => ['list', 'grid'], 'default' => 'grid'],
                    'showExcerpt' => ['type' => 'boolean', 'default' => true],
                    'structureId' => ['type' => 'integer', 'minimum' => 0, 'default' => 0],
                    'stickyOnly' => ['type' => 'boolean', 'default' => true],
                ],
            ],
            variants: [],
            renderer: new FeaturedPostsRenderer(),
            editorConfig: self::localizeEditorConfig([
                'tabs' => [
                    ['id' => 'content', 'label' => 'Content', 'controls' => [
                        ['id' => 'title', 'type' => 'text', 'label' => 'Title'],
                        ['id' => 'perPage', 'type' => 'number', 'label' => 'Posts Per Page', 'min' => 1, 'max' => 12, 'step' => 1],
                        ['id' => 'layout', 'type' => 'select', 'label' => 'Layout', 'options' => [['list', 'List'], ['grid', 'Grid']]],
                        ['id' => 'showExcerpt', 'type' => 'toggle', 'label' => 'Show Excerpt'],
                        ['id' => 'structureId', 'type' => 'select', 'label' => 'Structure'],
                        ['id' => 'stickyOnly', 'type' => 'toggle', 'label' => 'Sticky Posts Only'],
                    ]],
                ],
            ]),
        );
    }

    private static function makeTaxonomyList(): BlockDefinition
    {
        return new BlockDefinition(
            type: 'bky/taxonomy-list',
            label: self::tr('Taxonomy List'),
            category: 'query',
            description: self::tr('List public taxonomy terms like categories or tags.'),
            keywords: ['taxonomy', 'terms', 'categories', 'tags'],
            icon: '#',
            schema: [
                'type' => 'object',
                'properties' => [
                    'title' => ['type' => 'string', 'default' => 'Browse topics'],
                    'taxonomy' => ['type' => 'string', 'default' => 'category'],
                    'layout' => ['type' => 'string', 'enum' => ['list', 'pills'], 'default' => 'pills'],
                    'showCount' => ['type' => 'boolean', 'default' => true],
                    'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50, 'default' => 8],
                ],
            ],
            variants: [],
            renderer: new TaxonomyListRenderer(),
            editorConfig: self::localizeEditorConfig([
                'tabs' => [
                    ['id' => 'content', 'label' => 'Content', 'controls' => [
                        ['id' => 'title', 'type' => 'text', 'label' => 'Title'],
                        ['id' => 'taxonomy', 'type' => 'text', 'label' => 'Taxonomy'],
                        ['id' => 'layout', 'type' => 'select', 'label' => 'Layout', 'options' => [['list', 'List'], ['pills', 'Pills']]],
                        ['id' => 'showCount', 'type' => 'toggle', 'label' => 'Show Count'],
                        ['id' => 'limit', 'type' => 'number', 'label' => 'Limit', 'min' => 1, 'max' => 50, 'step' => 1],
                    ]],
                ],
            ]),
        );
    }

    private static function makeArchivePosts(): BlockDefinition
    {
        return new BlockDefinition(
            type: 'bky/archive-posts',
            label: self::tr('Archive Posts'),
            category: 'basic',
            description: self::tr('Loop block that renders the current archive query with an editor fallback.'),
            keywords: ['archive', 'posts', 'loop'],
            icon: 'A',
            schema: [
                'type' => 'object',
                'properties' => [
                    'perPage' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 12, 'default' => 6],
                    'showExcerpt' => ['type' => 'boolean', 'default' => true],
                    'structureId' => ['type' => 'integer', 'minimum' => 0, 'default' => 0],
                ],
            ],
            variants: [],
            renderer: new ArchivePostsRenderer(),
            editorConfig: self::localizeEditorConfig([
                'tabs' => [
                    ['id' => 'content', 'label' => 'Content', 'controls' => [
                        ['id' => 'perPage', 'type' => 'number', 'label' => 'Fallback Posts Per Page', 'min' => 1, 'max' => 12, 'step' => 1],
                        ['id' => 'showExcerpt', 'type' => 'toggle', 'label' => 'Show Excerpt'],
                        ['id' => 'structureId', 'type' => 'select', 'label' => 'Structure'],
                    ]],
                ],
            ]),
        );
    }

    private static function makePagination(): BlockDefinition
    {
        return new BlockDefinition(
            type: 'bky/pagination',
            label: self::tr('Pagination'),
            category: 'basic',
            description: self::tr('Pagination links for archive and query-driven templates.'),
            keywords: ['pagination', 'pages', 'navigation'],
            icon: '→',
            schema: [
                'type' => 'object',
                'properties' => [
                    'prevLabel' => ['type' => 'string', 'default' => 'Previous'],
                    'nextLabel' => ['type' => 'string', 'default' => 'Next'],
                    'align' => ['type' => 'string', 'enum' => ['start', 'center', 'end'], 'default' => 'start'],
                ],
            ],
            variants: [
                'align' => ['start' => 'justify-start', 'center' => 'justify-center', 'end' => 'justify-end'],
            ],
            renderer: new PaginationRenderer(),
            editorConfig: self::localizeEditorConfig([
                'tabs' => [
                    ['id' => 'content', 'label' => 'Content', 'controls' => [
                        ['id' => 'prevLabel', 'type' => 'text', 'label' => 'Previous Label'],
                        ['id' => 'nextLabel', 'type' => 'text', 'label' => 'Next Label'],
                    ]],
                    ['id' => 'layout', 'label' => 'Layout', 'controls' => [
                        ['id' => 'align', 'type' => 'variant', 'label' => 'Alignment', 'variantKey' => 'align'],
                    ]],
                ],
            ]),
        );
    }

    private static function makeImageGallery(): BlockDefinition
    {
        return new BlockDefinition(
            type: 'bky/image-gallery',
            label: self::tr('Image Gallery'),
            category: 'basic',
            description: self::tr('Grid gallery driven by image IDs or URLs.'),
            keywords: ['gallery', 'images', 'grid'],
            icon: '▥',
            schema: [
                'type' => 'object',
                'properties' => [
                    'items' => ['type' => 'string', 'default' => '', 'listFields' => [ [ 'key' => 'source', 'label' => 'Image', 'kind' => 'image' ], [ 'key' => 'caption', 'label' => 'Caption' ] ]],
                    'columns' => ['type' => 'string', 'enum' => ['2', '3', '4'], 'default' => '3'],
                    'gap' => ['type' => 'string', 'enum' => ['sm', 'base', 'lg'], 'default' => 'base'],
                    'aspectRatio' => ['type' => 'string', 'enum' => ['square', 'video', 'wide'], 'default' => 'square'],
                ],
            ],
            variants: [
                'columns' => ['2' => 'grid-cols-1 md:grid-cols-2', '3' => 'grid-cols-1 md:grid-cols-2 lg:grid-cols-3', '4' => 'grid-cols-1 md:grid-cols-2 xl:grid-cols-4'],
                'gap' => ['sm' => 'gap-3', 'base' => 'gap-4', 'lg' => 'gap-6'],
                'aspectRatio' => ['square' => 'aspect-square', 'video' => 'aspect-video', 'wide' => 'aspect-[21/9]'],
            ],
            renderer: new ImageGalleryRenderer(),
            editorConfig: self::localizeEditorConfig([
                'tabs' => [
                    ['id' => 'content', 'label' => 'Content', 'controls' => [
                        ['id' => 'items', 'type' => 'richtext', 'label' => 'Items'],
                    ]],
                    ['id' => 'layout', 'label' => 'Layout', 'controls' => [
                        ['id' => 'columns', 'type' => 'variant', 'label' => 'Columns', 'variantKey' => 'columns'],
                        ['id' => 'gap', 'type' => 'variant', 'label' => 'Spacing', 'variantKey' => 'gap'],
                    ]],
                    ['id' => 'style', 'label' => 'Style', 'controls' => [
                        ['id' => 'aspectRatio', 'type' => 'variant', 'label' => 'Aspect Ratio', 'variantKey' => 'aspectRatio'],
                    ]],
                ],
            ]),
        );
    }

    private static function makeBasicGallery(): BlockDefinition
    {
        return new BlockDefinition(
            type: 'bky/basic-gallery',
            label: self::tr('Basic Gallery'),
            category: 'basic',
            description: self::tr('Simple classic gallery layout driven by image IDs or URLs.'),
            keywords: ['gallery', 'images'],
            icon: '▤',
            schema: [
                'type' => 'object',
                'properties' => [
                    'items' => ['type' => 'string', 'default' => '', 'listFields' => [ [ 'key' => 'source', 'label' => 'Image', 'kind' => 'image' ] ]],
                    'columns' => ['type' => 'string', 'enum' => ['2', '3', '4'], 'default' => '3'],
                    'gap' => ['type' => 'string', 'enum' => ['sm', 'base', 'lg'], 'default' => 'base'],
                ],
            ],
            variants: [
                'columns' => ['2' => 'grid-cols-2', '3' => 'grid-cols-3', '4' => 'grid-cols-4'],
                'gap' => ['sm' => 'gap-2', 'base' => 'gap-3', 'lg' => 'gap-4'],
            ],
            renderer: new BasicGalleryRenderer(),
            editorConfig: self::localizeEditorConfig([
                'tabs' => [
                    ['id' => 'content', 'label' => 'Content', 'controls' => [
                        ['id' => 'items', 'type' => 'richtext', 'label' => 'Items'],
                    ]],
                    ['id' => 'layout', 'label' => 'Layout', 'controls' => [
                        ['id' => 'columns', 'type' => 'variant', 'label' => 'Columns', 'variantKey' => 'columns'],
                        ['id' => 'gap', 'type' => 'variant', 'label' => 'Spacing', 'variantKey' => 'gap'],
                    ]],
                ],
            ]),
        );
    }

    private static function makeImageCarousel(): BlockDefinition
    {
        return new BlockDefinition(
            type: 'bky/image-carousel',
            label: self::tr('Image Carousel'),
            category: 'basic',
            description: self::tr('Horizontal image carousel driven by image IDs or URLs.'),
            keywords: ['carousel', 'slider', 'images'],
            icon: '⇆',
            schema: [
                'type' => 'object',
                'properties' => [
                    'items' => ['type' => 'string', 'default' => '', 'listFields' => [ [ 'key' => 'source', 'label' => 'Image', 'kind' => 'image' ], [ 'key' => 'caption', 'label' => 'Caption' ] ]],
                    'slidesVisible' => ['type' => 'string', 'enum' => ['1', '2', '3'], 'default' => '1'],
                    'gap' => ['type' => 'string', 'enum' => ['sm', 'base', 'lg'], 'default' => 'base'],
                    'aspectRatio' => ['type' => 'string', 'enum' => ['square', 'video', 'wide'], 'default' => 'video'],
                    'showCaptions' => ['type' => 'boolean', 'default' => true],
                    'autoPlay' => ['type' => 'boolean', 'default' => false],
                    'interval' => ['type' => 'integer', 'minimum' => 2, 'maximum' => 15, 'default' => 5],
                ],
            ],
            variants: [
                'slidesVisible' => ['1' => 'basis-full', '2' => 'basis-full md:basis-1/2', '3' => 'basis-full md:basis-1/2 lg:basis-1/3'],
                'gap' => ['sm' => 'gap-3', 'base' => 'gap-4', 'lg' => 'gap-6'],
                'aspectRatio' => ['square' => 'aspect-square', 'video' => 'aspect-video', 'wide' => 'aspect-[21/9]'],
            ],
            renderer: new ImageCarouselRenderer(),
            editorConfig: self::localizeEditorConfig([
                'tabs' => [
                    ['id' => 'content', 'label' => 'Content', 'controls' => [
                        ['id' => 'items', 'type' => 'richtext', 'label' => 'Items'],
                        ['id' => 'showCaptions', 'type' => 'toggle', 'label' => 'Show Captions'],
                        ['id' => 'autoPlay', 'type' => 'toggle', 'label' => 'Auto Play'],
                        ['id' => 'interval', 'type' => 'number', 'label' => 'Interval (seconds)', 'min' => 2, 'max' => 15, 'step' => 1],
                    ]],
                    ['id' => 'layout', 'label' => 'Layout', 'controls' => [
                        ['id' => 'slidesVisible', 'type' => 'variant', 'label' => 'Slides Visible', 'variantKey' => 'slidesVisible'],
                        ['id' => 'gap', 'type' => 'variant', 'label' => 'Spacing', 'variantKey' => 'gap'],
                    ]],
                    ['id' => 'style', 'label' => 'Style', 'controls' => [
                        ['id' => 'aspectRatio', 'type' => 'variant', 'label' => 'Aspect Ratio', 'variantKey' => 'aspectRatio'],
                    ]],
                ],
            ]),
        );
    }

    private static function makeContentCarousel(): BlockDefinition
    {
        return self::makeContentCarouselDefinition(
            type: 'bky/content-carousel',
            label: 'Content Carousel',
            description: 'Content slider driven by title, copy, and optional CTA pairs.'
        );
    }

    private static function makeSlider(): BlockDefinition
    {
        return self::makeContentCarouselDefinition(
            type: 'bky/slider',
            label: 'Slider',
            description: 'Generic content slider with optional CTA actions.'
        );
    }

    private static function makeContentCarouselDefinition(string $type, string $label, string $description): BlockDefinition
    {
        return new BlockDefinition(
            type: $type,
            label: self::tr($label),
            category: 'basic',
            description: self::tr($description),
            keywords: ['carousel', 'slider', 'content'],
            icon: '↔',
            schema: [
                'type' => 'object',
                'properties' => [
                    'items' => ['type' => 'string', 'default' => "Slide 1|Highlight your offer|Learn more|#\nSlide 2|Guide visitors to the next step|Contact us|#", 'listFields' => [ [ 'key' => 'title', 'label' => 'Title' ], [ 'key' => 'content', 'label' => 'Content' ], [ 'key' => 'button', 'label' => 'Button label' ], [ 'key' => 'url', 'label' => 'Link URL', 'kind' => 'url' ] ]],
                    'slidesVisible' => ['type' => 'string', 'enum' => ['1', '2', '3'], 'default' => '1'],
                    'gap' => ['type' => 'string', 'enum' => ['sm', 'base', 'lg'], 'default' => 'base'],
                    'align' => ['type' => 'string', 'enum' => ['start', 'center'], 'default' => 'start'],
                    'tone' => ['type' => 'string', 'enum' => ['surface', 'accent', 'contrast'], 'default' => 'surface'],
                    'autoPlay' => ['type' => 'boolean', 'default' => false],
                    'interval' => ['type' => 'integer', 'minimum' => 2, 'maximum' => 15, 'default' => 5],
                ],
            ],
            variants: [
                'slidesVisible' => ['1' => 'basis-full', '2' => 'basis-full md:basis-1/2', '3' => 'basis-full md:basis-1/2 lg:basis-1/3'],
                'gap' => ['sm' => 'gap-3', 'base' => 'gap-4', 'lg' => 'gap-6'],
                'align' => ['start' => 'items-start text-left', 'center' => 'items-center text-center'],
                'tone' => ['surface' => 'border border-border-subtle bg-surface-base text-text-base', 'accent' => 'bg-accent-subtle text-text-base', 'contrast' => 'bg-text-base text-text-on-accent'],
            ],
            renderer: new ContentCarouselRenderer(),
            editorConfig: self::localizeEditorConfig([
                'tabs' => [
                    ['id' => 'content', 'label' => 'Content', 'controls' => [
                        ['id' => 'items', 'type' => 'richtext', 'label' => 'Items'],
                        ['id' => 'autoPlay', 'type' => 'toggle', 'label' => 'Auto Play'],
                        ['id' => 'interval', 'type' => 'number', 'label' => 'Interval (seconds)', 'min' => 2, 'max' => 15, 'step' => 1],
                    ]],
                    ['id' => 'layout', 'label' => 'Layout', 'controls' => [
                        ['id' => 'slidesVisible', 'type' => 'variant', 'label' => 'Slides Visible', 'variantKey' => 'slidesVisible'],
                        ['id' => 'gap', 'type' => 'variant', 'label' => 'Spacing', 'variantKey' => 'gap'],
                        ['id' => 'align', 'type' => 'variant', 'label' => 'Alignment', 'variantKey' => 'align'],
                    ]],
                    ['id' => 'style', 'label' => 'Style', 'controls' => [
                        ['id' => 'tone', 'type' => 'variant', 'label' => 'Tone', 'variantKey' => 'tone'],
                    ]],
                ],
            ]),
        );
    }

    private static function makeCallToAction(): BlockDefinition
    {
        return new BlockDefinition(
            type: 'bky/call-to-action',
            label: self::tr('Call To Action'),
            category: 'basic',
            description: self::tr('Marketing panel with copy, media, and primary actions.'),
            keywords: ['cta', 'hero', 'promotion'],
            icon: '⇢',
            schema: [
                'type' => 'object',
                'properties' => [
                    'attachmentId' => ['type' => 'integer', 'default' => 0],
                    'eyebrow' => ['type' => 'string', 'default' => 'Limited release'],
                    'title' => ['type' => 'string', 'default' => 'Launch a focused campaign with Blocky'],
                    'text' => ['type' => 'string', 'default' => 'Combine persuasive copy, supporting media, and clear next steps inside a reusable CTA section.'],
                    'primaryLabel' => ['type' => 'string', 'default' => 'Start now'],
                    'primaryUrl' => ['type' => 'string', 'default' => '#'],
                    'secondaryLabel' => ['type' => 'string', 'default' => 'Book a demo'],
                    'secondaryUrl' => ['type' => 'string', 'default' => '#'],
                    'layout' => ['type' => 'string', 'enum' => ['stacked', 'split'], 'default' => 'split'],
                    'align' => ['type' => 'string', 'enum' => ['start', 'center'], 'default' => 'start'],
                    'mediaPosition' => ['type' => 'string', 'enum' => ['start', 'end'], 'default' => 'end'],
                    'tone' => ['type' => 'string', 'enum' => ['surface', 'accent', 'contrast'], 'default' => 'surface'],
                    'padding' => ['type' => 'string', 'enum' => ['base', 'lg'], 'default' => 'lg'],
                ],
            ],
            variants: [
                'layout' => ['stacked' => 'flex-col', 'split' => 'flex-col lg:flex-row'],
                'align' => ['start' => 'items-start text-left', 'center' => 'items-center text-center'],
                'mediaPosition' => ['start' => 'lg:flex-row-reverse', 'end' => ''],
                'tone' => ['surface' => 'border border-border-subtle bg-surface-base text-text-base', 'accent' => 'bg-accent-subtle text-text-base', 'contrast' => 'bg-text-base text-text-on-accent'],
                'padding' => ['base' => 'p-6', 'lg' => 'p-8'],
            ],
            renderer: new CallToActionRenderer(),
            editorConfig: self::localizeEditorConfig([
                'tabs' => [
                    ['id' => 'content', 'label' => 'Content', 'controls' => [
                        ['id' => 'attachmentId', 'type' => 'media', 'label' => 'Image', 'mediaType' => 'image'],
                        ['id' => 'eyebrow', 'type' => 'text', 'label' => 'Eyebrow'],
                        ['id' => 'title', 'type' => 'text', 'label' => 'Title'],
                        ['id' => 'text', 'type' => 'richtext', 'label' => 'Text'],
                        ['id' => 'primaryLabel', 'type' => 'text', 'label' => 'Primary Label'],
                        ['id' => 'primaryUrl', 'type' => 'text', 'label' => 'Primary URL'],
                        ['id' => 'secondaryLabel', 'type' => 'text', 'label' => 'Secondary Label'],
                        ['id' => 'secondaryUrl', 'type' => 'text', 'label' => 'Secondary URL'],
                    ]],
                    ['id' => 'layout', 'label' => 'Layout', 'controls' => [
                        ['id' => 'layout', 'type' => 'variant', 'label' => 'Layout', 'variantKey' => 'layout'],
                        ['id' => 'align', 'type' => 'variant', 'label' => 'Alignment', 'variantKey' => 'align'],
                        ['id' => 'mediaPosition', 'type' => 'variant', 'label' => 'Media Position', 'variantKey' => 'mediaPosition'],
                    ]],
                    ['id' => 'style', 'label' => 'Style', 'controls' => [
                        ['id' => 'tone', 'type' => 'variant', 'label' => 'Tone', 'variantKey' => 'tone'],
                        ['id' => 'padding', 'type' => 'variant', 'label' => 'Padding', 'variantKey' => 'padding'],
                    ]],
                ],
            ]),
        );
    }

    private static function makeTestimonial(): BlockDefinition
    {
        return new BlockDefinition(
            type: 'bky/testimonial',
            label: self::tr('Testimonial'),
            category: 'basic',
            description: self::tr('Customer quote with optional avatar and role.'),
            keywords: ['quote', 'review', 'customer'],
            icon: '❞',
            schema: [
                'type' => 'object',
                'properties' => [
                    'attachmentId' => ['type' => 'integer', 'default' => 0],
                    'quote' => ['type' => 'string', 'default' => 'Blocky helped us ship faster without losing layout control.'],
                    'author' => ['type' => 'string', 'default' => 'Alex Morgan'],
                    'role' => ['type' => 'string', 'default' => 'Product Marketing Lead'],
                    'layout' => ['type' => 'string', 'enum' => ['card', 'centered'], 'default' => 'card'],
                    'tone' => ['type' => 'string', 'enum' => ['surface', 'accent', 'contrast'], 'default' => 'surface'],
                ],
            ],
            variants: [
                'layout' => ['card' => 'items-start text-left', 'centered' => 'items-center text-center'],
                'tone' => ['surface' => 'border border-border-subtle bg-surface-base text-text-base', 'accent' => 'bg-accent-subtle text-text-base', 'contrast' => 'bg-text-base text-text-on-accent'],
            ],
            renderer: new TestimonialRenderer(),
            editorConfig: self::localizeEditorConfig([
                'tabs' => [
                    ['id' => 'content', 'label' => 'Content', 'controls' => [
                        ['id' => 'attachmentId', 'type' => 'media', 'label' => 'Image', 'mediaType' => 'image'],
                        ['id' => 'quote', 'type' => 'richtext', 'label' => 'Quote'],
                        ['id' => 'author', 'type' => 'text', 'label' => 'Author'],
                        ['id' => 'role', 'type' => 'text', 'label' => 'Role'],
                    ]],
                    ['id' => 'layout', 'label' => 'Layout', 'controls' => [
                        ['id' => 'layout', 'type' => 'variant', 'label' => 'Layout', 'variantKey' => 'layout'],
                    ]],
                    ['id' => 'style', 'label' => 'Style', 'controls' => [
                        ['id' => 'tone', 'type' => 'variant', 'label' => 'Tone', 'variantKey' => 'tone'],
                    ]],
                ],
            ]),
        );
    }

    private static function makePriceTable(): BlockDefinition
    {
        return new BlockDefinition(
            type: 'bky/price-table',
            label: self::tr('Price Table'),
            category: 'basic',
            description: self::tr('Single pricing card with features and CTA.'),
            keywords: ['pricing', 'plans', 'subscription'],
            icon: '$',
            schema: [
                'type' => 'object',
                'properties' => [
                    'title' => ['type' => 'string', 'default' => 'Growth'],
                    'subtitle' => ['type' => 'string', 'default' => 'For teams ready to scale.'],
                    'price' => ['type' => 'string', 'default' => '49'],
                    'currency' => ['type' => 'string', 'default' => '€'],
                    'cadence' => ['type' => 'string', 'default' => '/mo'],
                    'features' => ['type' => 'string', 'default' => "Unlimited sections\nShared design tokens\nPriority support"],
                    'buttonLabel' => ['type' => 'string', 'default' => 'Choose plan'],
                    'buttonUrl' => ['type' => 'string', 'default' => '#'],
                    'featured' => ['type' => 'boolean', 'default' => false],
                    'align' => ['type' => 'string', 'enum' => ['start', 'center'], 'default' => 'start'],
                    'tone' => ['type' => 'string', 'enum' => ['surface', 'accent', 'contrast'], 'default' => 'surface'],
                ],
            ],
            variants: [
                'align' => ['start' => 'items-start text-left', 'center' => 'items-center text-center'],
                'tone' => ['surface' => 'border border-border-subtle bg-surface-base text-text-base', 'accent' => 'bg-accent-subtle text-text-base', 'contrast' => 'bg-text-base text-text-on-accent'],
            ],
            renderer: new PricingRenderer('table'),
            editorConfig: self::localizeEditorConfig([
                'tabs' => [
                    ['id' => 'content', 'label' => 'Content', 'controls' => [
                        ['id' => 'title', 'type' => 'text', 'label' => 'Title'],
                        ['id' => 'subtitle', 'type' => 'richtext', 'label' => 'Subtitle'],
                        ['id' => 'price', 'type' => 'text', 'label' => 'Price'],
                        ['id' => 'currency', 'type' => 'text', 'label' => 'Currency'],
                        ['id' => 'cadence', 'type' => 'text', 'label' => 'Cadence'],
                        ['id' => 'features', 'type' => 'richtext', 'label' => 'Features'],
                        ['id' => 'buttonLabel', 'type' => 'text', 'label' => 'Button Label'],
                        ['id' => 'buttonUrl', 'type' => 'text', 'label' => 'Button URL'],
                        ['id' => 'featured', 'type' => 'toggle', 'label' => 'Featured Plan'],
                    ]],
                    ['id' => 'layout', 'label' => 'Layout', 'controls' => [
                        ['id' => 'align', 'type' => 'variant', 'label' => 'Alignment', 'variantKey' => 'align'],
                    ]],
                    ['id' => 'style', 'label' => 'Style', 'controls' => [
                        ['id' => 'tone', 'type' => 'variant', 'label' => 'Tone', 'variantKey' => 'tone'],
                    ]],
                ],
            ]),
        );
    }

    private static function makePriceList(): BlockDefinition
    {
        return new BlockDefinition(
            type: 'bky/price-list',
            label: self::tr('Price List'),
            category: 'basic',
            description: self::tr('Service or menu list with optional descriptions and prices.'),
            keywords: ['menu', 'pricing', 'list'],
            icon: '≣',
            schema: [
                'type' => 'object',
                'properties' => [
                    'items' => ['type' => 'string', 'default' => "Strategy Session|60 minute workshop|€120\nImplementation Sprint|Landing page setup|€900", 'listFields' => [ [ 'key' => 'name', 'label' => 'Plan' ], [ 'key' => 'description', 'label' => 'Description' ], [ 'key' => 'price', 'label' => 'Price' ] ]],
                    'showDividers' => ['type' => 'boolean', 'default' => true],
                    'tone' => ['type' => 'string', 'enum' => ['surface', 'accent'], 'default' => 'surface'],
                ],
            ],
            variants: [
                'tone' => ['surface' => 'border border-border-subtle bg-surface-base text-text-base', 'accent' => 'bg-accent-subtle text-text-base'],
            ],
            renderer: new PricingRenderer('list'),
            editorConfig: self::localizeEditorConfig([
                'tabs' => [
                    ['id' => 'content', 'label' => 'Content', 'controls' => [
                        ['id' => 'items', 'type' => 'richtext', 'label' => 'Items'],
                        ['id' => 'showDividers', 'type' => 'toggle', 'label' => 'Show Dividers'],
                    ]],
                    ['id' => 'style', 'label' => 'Style', 'controls' => [
                        ['id' => 'tone', 'type' => 'variant', 'label' => 'Tone', 'variantKey' => 'tone'],
                    ]],
                ],
            ]),
        );
    }

    private static function makeFlipBox(): BlockDefinition
    {
        return new BlockDefinition(
            type: 'bky/flip-box',
            label: self::tr('Flip Box'),
            category: 'basic',
            description: self::tr('Front and back content card with hover flip effect.'),
            keywords: ['flip', 'card', 'interactive'],
            icon: '⟲',
            schema: [
                'type' => 'object',
                'properties' => [
                    'frontTitle' => ['type' => 'string', 'default' => 'Feature teaser'],
                    'frontText' => ['type' => 'string', 'default' => 'Keep the front side concise and visual.'],
                    'backTitle' => ['type' => 'string', 'default' => 'Reveal the full pitch'],
                    'backText' => ['type' => 'string', 'default' => 'Use the back side for detail, proof, or the next action.'],
                    'buttonLabel' => ['type' => 'string', 'default' => 'Learn more'],
                    'buttonUrl' => ['type' => 'string', 'default' => '#'],
                    'height' => ['type' => 'string', 'enum' => ['sm', 'base', 'lg'], 'default' => 'base'],
                    'verticalAlign' => ['type' => 'string', 'enum' => ['start', 'center', 'end'], 'default' => 'end'],
                    'tone' => ['type' => 'string', 'enum' => ['surface', 'accent', 'contrast'], 'default' => 'surface'],
                ],
            ],
            variants: [
                'height' => ['sm' => 'min-h-64', 'base' => 'min-h-80', 'lg' => 'min-h-[26rem]'],
                'verticalAlign' => ['start' => 'justify-start', 'center' => 'justify-center', 'end' => 'justify-end'],
                'tone' => ['surface' => 'border border-border-subtle bg-surface-base text-text-base', 'accent' => 'bg-accent-subtle text-text-base', 'contrast' => 'bg-text-base text-text-on-accent'],
            ],
            renderer: new FlipBoxRenderer(),
            editorConfig: self::localizeEditorConfig([
                'tabs' => [
                    ['id' => 'content', 'label' => 'Content', 'controls' => [
                        ['id' => 'frontTitle', 'type' => 'text', 'label' => 'Front Title'],
                        ['id' => 'frontText', 'type' => 'richtext', 'label' => 'Front Text'],
                        ['id' => 'backTitle', 'type' => 'text', 'label' => 'Back Title'],
                        ['id' => 'backText', 'type' => 'richtext', 'label' => 'Back Text'],
                        ['id' => 'buttonLabel', 'type' => 'text', 'label' => 'Button Label'],
                        ['id' => 'buttonUrl', 'type' => 'text', 'label' => 'Button URL'],
                    ]],
                    ['id' => 'layout', 'label' => 'Layout', 'controls' => [
                        ['id' => 'height', 'type' => 'variant', 'label' => 'Height', 'variantKey' => 'height'],
                    ]],
                    ['id' => 'style', 'label' => 'Style', 'controls' => [
                        ['id' => 'verticalAlign', 'type' => 'variant', 'label' => 'Vertical Align', 'variantKey' => 'verticalAlign'],
                        ['id' => 'tone', 'type' => 'variant', 'label' => 'Tone', 'variantKey' => 'tone'],
                    ]],
                ],
            ]),
        );
    }

    private static function makeHotspot(): BlockDefinition
    {
        return new BlockDefinition(
            type: 'bky/hotspot',
            label: self::tr('Hotspot'),
            category: 'basic',
            description: self::tr('Image hotspots with point labels and supporting copy.'),
            keywords: ['hotspot', 'image', 'annotation'],
            icon: '⊕',
            schema: [
                'type' => 'object',
                'properties' => [
                    'attachmentId' => ['type' => 'integer', 'default' => 0],
                    'points' => ['type' => 'string', 'default' => "Hero area|28|35|Point out the main value proposition.\nCall to action|70|62|Use a second marker for the next step.", 'listFields' => [ [ 'key' => 'label', 'label' => 'Label' ], [ 'key' => 'x', 'label' => 'X %', 'kind' => 'number' ], [ 'key' => 'y', 'label' => 'Y %', 'kind' => 'number' ], [ 'key' => 'content', 'label' => 'Content' ] ]],
                    'tone' => ['type' => 'string', 'enum' => ['surface', 'accent'], 'default' => 'surface'],
                ],
            ],
            variants: [
                'tone' => ['surface' => 'border border-border-subtle bg-surface-base text-text-base', 'accent' => 'bg-accent-subtle text-text-base'],
            ],
            renderer: new HotspotRenderer(),
            editorConfig: self::localizeEditorConfig([
                'tabs' => [
                    ['id' => 'content', 'label' => 'Content', 'controls' => [
                        ['id' => 'attachmentId', 'type' => 'media', 'label' => 'Image', 'mediaType' => 'image'],
                        ['id' => 'points', 'type' => 'richtext', 'label' => 'Points'],
                    ]],
                    ['id' => 'style', 'label' => 'Style', 'controls' => [
                        ['id' => 'tone', 'type' => 'variant', 'label' => 'Tone', 'variantKey' => 'tone'],
                    ]],
                ],
            ]),
        );
    }

    private static function makeBeforeAfterSlider(): BlockDefinition
    {
        return new BlockDefinition(
            type: 'bky/before-after-slider',
            label: self::tr('Before After Slider'),
            category: 'basic',
            description: self::tr('Interactive image comparison slider.'),
            keywords: ['before', 'after', 'comparison'],
            icon: '⇄',
            schema: [
                'type' => 'object',
                'properties' => [
                    'beforeAttachmentId' => ['type' => 'integer', 'default' => 0],
                    'afterAttachmentId' => ['type' => 'integer', 'default' => 0],
                    'startingPoint' => ['type' => 'integer', 'minimum' => 10, 'maximum' => 90, 'default' => 50],
                    'aspectRatio' => ['type' => 'string', 'enum' => ['square', 'video', 'wide'], 'default' => 'video'],
                ],
            ],
            variants: [
                'aspectRatio' => ['square' => 'aspect-square', 'video' => 'aspect-video', 'wide' => 'aspect-[21/9]'],
            ],
            renderer: new BeforeAfterRenderer(),
            editorConfig: self::localizeEditorConfig([
                'tabs' => [
                    ['id' => 'content', 'label' => 'Content', 'controls' => [
                        ['id' => 'beforeAttachmentId', 'type' => 'media', 'label' => 'Before Image', 'mediaType' => 'image'],
                        ['id' => 'afterAttachmentId', 'type' => 'media', 'label' => 'After Image', 'mediaType' => 'image'],
                        ['id' => 'startingPoint', 'type' => 'number', 'label' => 'Starting Position', 'min' => 10, 'max' => 90, 'step' => 1],
                    ]],
                    ['id' => 'layout', 'label' => 'Layout', 'controls' => [
                        ['id' => 'aspectRatio', 'type' => 'variant', 'label' => 'Aspect Ratio', 'variantKey' => 'aspectRatio'],
                    ]],
                ],
            ]),
        );
    }

    private static function makeCountdown(): BlockDefinition
    {
        return new BlockDefinition(
            type: 'bky/countdown',
            label: self::tr('Countdown'),
            category: 'basic',
            description: self::tr('Countdown timer to a target date and time.'),
            keywords: ['timer', 'launch', 'deadline'],
            icon: '⏱',
            schema: [
                'type' => 'object',
                'properties' => [
                    'targetDate' => ['type' => 'string', 'default' => '2030-01-01T00:00:00'],
                    'showLabels' => ['type' => 'boolean', 'default' => true],
                    'layout' => ['type' => 'string', 'enum' => ['grid', 'inline'], 'default' => 'grid'],
                    'tone' => ['type' => 'string', 'enum' => ['surface', 'accent', 'contrast'], 'default' => 'surface'],
                ],
            ],
            variants: [
                'layout' => ['grid' => 'grid grid-cols-2 gap-4 md:grid-cols-4', 'inline' => 'flex flex-wrap gap-4'],
                'tone' => ['surface' => 'border border-border-subtle bg-surface-base text-text-base', 'accent' => 'bg-accent-subtle text-text-base', 'contrast' => 'bg-text-base text-text-on-accent'],
            ],
            renderer: new CountdownRenderer(),
            editorConfig: self::localizeEditorConfig([
                'tabs' => [
                    ['id' => 'content', 'label' => 'Content', 'controls' => [
                        ['id' => 'targetDate', 'type' => 'text', 'label' => 'Target Date'],
                        ['id' => 'showLabels', 'type' => 'toggle', 'label' => 'Show Labels'],
                    ]],
                    ['id' => 'layout', 'label' => 'Layout', 'controls' => [
                        ['id' => 'layout', 'type' => 'variant', 'label' => 'Layout', 'variantKey' => 'layout'],
                    ]],
                    ['id' => 'style', 'label' => 'Style', 'controls' => [
                        ['id' => 'tone', 'type' => 'variant', 'label' => 'Tone', 'variantKey' => 'tone'],
                    ]],
                ],
            ]),
        );
    }

    private static function makeAnimatedHeadline(): BlockDefinition
    {
        return new BlockDefinition(
            type: 'bky/animated-headline',
            label: self::tr('Animated Headline'),
            category: 'basic',
            description: self::tr('Headline with rotating highlighted words.'),
            keywords: ['headline', 'text', 'animated'],
            icon: '✦',
            schema: [
                'type' => 'object',
                'properties' => [
                    'prefix' => ['type' => 'string', 'default' => 'Build'],
                    'words' => ['type' => 'string', 'default' => "faster\nsmarter\nwith Blocky"],
                    'suffix' => ['type' => 'string', 'default' => 'pages'],
                    'effect' => ['type' => 'string', 'enum' => ['rotate', 'slide', 'typing'], 'default' => 'rotate'],
                    'interval' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 10, 'default' => 3],
                    'align' => ['type' => 'string', 'enum' => ['start', 'center'], 'default' => 'start'],
                    'tone' => ['type' => 'string', 'enum' => ['base', 'accent', 'contrast'], 'default' => 'accent'],
                ],
            ],
            variants: [
                'align' => ['start' => 'items-start text-left', 'center' => 'items-center text-center'],
                'tone' => ['base' => 'text-text-base', 'accent' => 'text-accent-text', 'contrast' => 'text-text-on-accent'],
            ],
            renderer: new AnimatedHeadlineRenderer(),
            editorConfig: self::localizeEditorConfig([
                'tabs' => [
                    ['id' => 'content', 'label' => 'Content', 'controls' => [
                        ['id' => 'prefix', 'type' => 'text', 'label' => 'Prefix'],
                        ['id' => 'words', 'type' => 'richtext', 'label' => 'Words'],
                        ['id' => 'suffix', 'type' => 'text', 'label' => 'Suffix'],
                        ['id' => 'effect', 'type' => 'select', 'label' => 'Effect', 'options' => [['rotate', 'Rotate'], ['slide', 'Slide'], ['typing', 'Typing']]],
                        ['id' => 'interval', 'type' => 'number', 'label' => 'Interval (seconds)', 'min' => 1, 'max' => 10, 'step' => 1],
                    ]],
                    ['id' => 'layout', 'label' => 'Layout', 'controls' => [
                        ['id' => 'align', 'type' => 'variant', 'label' => 'Alignment', 'variantKey' => 'align'],
                    ]],
                    ['id' => 'style', 'label' => 'Style', 'controls' => [
                        ['id' => 'tone', 'type' => 'variant', 'label' => 'Tone', 'variantKey' => 'tone'],
                    ]],
                ],
            ]),
        );
    }

    private static function makeMarquee(): BlockDefinition
    {
        return new BlockDefinition(
            type: 'bky/marquee',
            label: self::tr('Marquee'),
            category: 'basic',
            description: self::tr('Scrolling text or labels with continuous motion.'),
            keywords: ['ticker', 'marquee', 'scroll'],
            icon: '⇢',
            schema: [
                'type' => 'object',
                'properties' => [
                    'items' => ['type' => 'string', 'default' => "Launch faster\nReusable sections\nWordPress native\nTailwind-first", 'listFields' => [ [ 'key' => 'text', 'label' => 'Text' ] ]],
                    'speed' => ['type' => 'integer', 'minimum' => 8, 'maximum' => 60, 'default' => 20],
                    'direction' => ['type' => 'string', 'enum' => ['left', 'right'], 'default' => 'left'],
                    'pauseOnHover' => ['type' => 'boolean', 'default' => true],
                    'tone' => ['type' => 'string', 'enum' => ['surface', 'accent', 'contrast'], 'default' => 'surface'],
                ],
            ],
            variants: [
                'direction' => ['left' => '', 'right' => '[animation-direction:reverse]'],
                'tone' => ['surface' => 'border border-border-subtle bg-surface-base text-text-base', 'accent' => 'bg-accent-subtle text-text-base', 'contrast' => 'bg-text-base text-text-on-accent'],
            ],
            renderer: new MarqueeRenderer(),
            editorConfig: self::localizeEditorConfig([
                'tabs' => [
                    ['id' => 'content', 'label' => 'Content', 'controls' => [
                        ['id' => 'items', 'type' => 'richtext', 'label' => 'Items'],
                        ['id' => 'speed', 'type' => 'number', 'label' => 'Speed (seconds)', 'min' => 8, 'max' => 60, 'step' => 1],
                        ['id' => 'pauseOnHover', 'type' => 'toggle', 'label' => 'Pause On Hover'],
                    ]],
                    ['id' => 'layout', 'label' => 'Layout', 'controls' => [
                        ['id' => 'direction', 'type' => 'variant', 'label' => 'Direction', 'variantKey' => 'direction'],
                    ]],
                    ['id' => 'style', 'label' => 'Style', 'controls' => [
                        ['id' => 'tone', 'type' => 'variant', 'label' => 'Tone', 'variantKey' => 'tone'],
                    ]],
                ],
            ]),
        );
    }

    private static function makeLottie(): BlockDefinition
    {
        return new BlockDefinition(
            type: 'bky/lottie',
            label: self::tr('Lottie'),
            category: 'basic',
            description: self::tr('Lottie JSON animation with playback controls.'),
            keywords: ['lottie', 'animation', 'json'],
            icon: '◌',
            schema: [
                'type' => 'object',
                'properties' => [
                    'url' => ['type' => 'string', 'default' => ''],
                    'poster' => ['type' => 'integer', 'default' => 0],
                    'autoplay' => ['type' => 'boolean', 'default' => true],
                    'loop' => ['type' => 'boolean', 'default' => true],
                    'speed' => ['type' => 'number', 'minimum' => 0.25, 'maximum' => 3, 'default' => 1],
                    'aspectRatio' => ['type' => 'string', 'enum' => ['square', 'video', 'wide'], 'default' => 'square'],
                ],
            ],
            variants: [
                'aspectRatio' => ['square' => 'aspect-square', 'video' => 'aspect-video', 'wide' => 'aspect-[21/9]'],
            ],
            renderer: new LottieRenderer(),
            editorConfig: self::localizeEditorConfig([
                'tabs' => [
                    ['id' => 'content', 'label' => 'Content', 'controls' => [
                        ['id' => 'url', 'type' => 'text', 'label' => 'Animation URL'],
                        ['id' => 'poster', 'type' => 'media', 'label' => 'Poster Image', 'mediaType' => 'image'],
                        ['id' => 'autoplay', 'type' => 'toggle', 'label' => 'Autoplay'],
                        ['id' => 'loop', 'type' => 'toggle', 'label' => 'Loop'],
                        ['id' => 'speed', 'type' => 'number', 'label' => 'Playback Speed', 'min' => 0.25, 'max' => 3, 'step' => 0.25],
                    ]],
                    ['id' => 'layout', 'label' => 'Layout', 'controls' => [
                        ['id' => 'aspectRatio', 'type' => 'variant', 'label' => 'Aspect Ratio', 'variantKey' => 'aspectRatio'],
                    ]],
                ],
            ]),
        );
    }

    private static function makeEmbedGoogleMaps(): BlockDefinition
    {
        return new BlockDefinition(
            type: 'bky/embed-google-maps',
            label: self::tr('Embed Google Maps'),
            category: 'basic',
            description: self::tr('Map embed using Google Maps query mode or OpenStreetMap coordinates.'),
            keywords: ['map', 'google', 'osm', 'embed'],
            icon: 'Map',
            schema: [
                'type' => 'object',
                'properties' => [
                    'provider' => ['type' => 'string', 'enum' => ['google', 'openstreetmap'], 'default' => 'google'],
                    'query' => ['type' => 'string', 'default' => 'Milan, Italy'],
                    'latitude' => ['type' => 'string', 'default' => ''],
                    'longitude' => ['type' => 'string', 'default' => ''],
                    'zoom' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 18, 'default' => 12],
                    'title' => ['type' => 'string', 'default' => 'Map embed'],
                    'allowFullscreen' => ['type' => 'boolean', 'default' => true],
                    'height' => ['type' => 'string', 'enum' => ['sm', 'base', 'lg'], 'default' => 'base'],
                    'aspectRatio' => ['type' => 'string', 'enum' => ['square', 'video', 'wide'], 'default' => 'wide'],
                ],
            ],
            variants: [
                'height' => ['sm' => 'min-h-64', 'base' => 'min-h-80', 'lg' => 'min-h-[30rem]'],
                'aspectRatio' => ['square' => 'aspect-square', 'video' => 'aspect-video', 'wide' => 'aspect-[21/9]'],
            ],
            renderer: new EmbedGoogleMapsRenderer(),
            editorConfig: self::localizeEditorConfig([
                'tabs' => [
                    ['id' => 'content', 'label' => 'Content', 'controls' => [
                        ['id' => 'provider', 'type' => 'select', 'label' => 'Provider', 'options' => [['google', 'Google Maps'], ['openstreetmap', 'OpenStreetMap']]],
                        ['id' => 'query', 'type' => 'text', 'label' => 'Location Query'],
                        ['id' => 'latitude', 'type' => 'text', 'label' => 'Latitude'],
                        ['id' => 'longitude', 'type' => 'text', 'label' => 'Longitude'],
                        ['id' => 'zoom', 'type' => 'number', 'label' => 'Zoom', 'min' => 1, 'max' => 18, 'step' => 1],
                        ['id' => 'title', 'type' => 'text', 'label' => 'Title'],
                        ['id' => 'allowFullscreen', 'type' => 'toggle', 'label' => 'Allow Fullscreen'],
                    ]],
                    ['id' => 'layout', 'label' => 'Layout', 'controls' => [
                        ['id' => 'height', 'type' => 'variant', 'label' => 'Height', 'variantKey' => 'height'],
                        ['id' => 'aspectRatio', 'type' => 'variant', 'label' => 'Aspect Ratio', 'variantKey' => 'aspectRatio'],
                    ]],
                ],
            ]),
        );
    }

    private static function makeEmbedIframe(): BlockDefinition
    {
        return new BlockDefinition(
            type: 'bky/embed-iframe',
            label: self::tr('Embed Iframe'),
            category: 'basic',
            description: self::tr('Generic iframe embed with sandbox and permission controls.'),
            keywords: ['iframe', 'embed', 'external'],
            icon: 'Frm',
            schema: [
                'type' => 'object',
                'properties' => [
                    'url' => ['type' => 'string', 'default' => ''],
                    'title' => ['type' => 'string', 'default' => 'Embedded iframe'],
                    'sandbox' => ['type' => 'string', 'default' => 'allow-scripts allow-same-origin allow-forms'],
                    'allow' => ['type' => 'string', 'default' => 'fullscreen; autoplay; clipboard-read; clipboard-write'],
                    'lazyLoad' => ['type' => 'boolean', 'default' => true],
                    'allowFullscreen' => ['type' => 'boolean', 'default' => true],
                    'height' => ['type' => 'string', 'enum' => ['sm', 'base', 'lg'], 'default' => 'base'],
                    'aspectRatio' => ['type' => 'string', 'enum' => ['square', 'video', 'wide'], 'default' => 'video'],
                ],
            ],
            variants: [
                'height' => ['sm' => 'min-h-64', 'base' => 'min-h-80', 'lg' => 'min-h-[30rem]'],
                'aspectRatio' => ['square' => 'aspect-square', 'video' => 'aspect-video', 'wide' => 'aspect-[21/9]'],
            ],
            renderer: new EmbedIframeRenderer(),
            editorConfig: self::localizeEditorConfig([
                'tabs' => [
                    ['id' => 'content', 'label' => 'Content', 'controls' => [
                        ['id' => 'url', 'type' => 'text', 'label' => 'URL'],
                        ['id' => 'title', 'type' => 'text', 'label' => 'Title'],
                        ['id' => 'sandbox', 'type' => 'text', 'label' => 'Sandbox'],
                        ['id' => 'allow', 'type' => 'text', 'label' => 'Allow'],
                        ['id' => 'lazyLoad', 'type' => 'toggle', 'label' => 'Lazy Load'],
                        ['id' => 'allowFullscreen', 'type' => 'toggle', 'label' => 'Allow Fullscreen'],
                    ]],
                    ['id' => 'layout', 'label' => 'Layout', 'controls' => [
                        ['id' => 'height', 'type' => 'variant', 'label' => 'Height', 'variantKey' => 'height'],
                        ['id' => 'aspectRatio', 'type' => 'variant', 'label' => 'Aspect Ratio', 'variantKey' => 'aspectRatio'],
                    ]],
                ],
            ]),
        );
    }

    private static function makeCodeHighlight(): BlockDefinition
    {
        return new BlockDefinition(
            type: 'bky/code-highlight',
            label: self::tr('Code Highlight'),
            category: 'basic',
            description: self::tr('Formatted code block with syntax highlighting and optional line numbers.'),
            keywords: ['code', 'snippet', 'highlight'],
            icon: '</>',
            schema: [
                'type' => 'object',
                'properties' => [
                    'code' => ['type' => 'string', 'default' => "const hello = 'Blocky';\nconsole.log(hello);"],
                    'language' => ['type' => 'string', 'enum' => ['plaintext', 'html', 'css', 'javascript', 'typescript', 'php', 'json', 'bash'], 'default' => 'javascript'],
                    'caption' => ['type' => 'string', 'default' => ''],
                    'showLineNumbers' => ['type' => 'boolean', 'default' => true],
                    'tone' => ['type' => 'string', 'enum' => ['surface', 'contrast'], 'default' => 'contrast'],
                ],
            ],
            variants: [
                'tone' => ['surface' => 'border border-border-subtle bg-surface-base text-text-base', 'contrast' => 'bg-slate-950 text-slate-100'],
            ],
            renderer: new CodeHighlightRenderer(),
            editorConfig: self::localizeEditorConfig([
                'tabs' => [
                    ['id' => 'content', 'label' => 'Content', 'controls' => [
                        ['id' => 'code', 'type' => 'richtext', 'label' => 'Code'],
                        ['id' => 'language', 'type' => 'select', 'label' => 'Language', 'options' => [['plaintext', 'Plain Text'], ['html', 'HTML'], ['css', 'CSS'], ['javascript', 'JavaScript'], ['typescript', 'TypeScript'], ['php', 'PHP'], ['json', 'JSON'], ['bash', 'Bash']]],
                        ['id' => 'caption', 'type' => 'text', 'label' => 'Caption'],
                        ['id' => 'showLineNumbers', 'type' => 'toggle', 'label' => 'Show Line Numbers'],
                    ]],
                    ['id' => 'style', 'label' => 'Style', 'controls' => [
                        ['id' => 'tone', 'type' => 'variant', 'label' => 'Tone', 'variantKey' => 'tone'],
                    ]],
                ],
            ]),
        );
    }

    private static function makeMegaMenu(): BlockDefinition
    {
        return new BlockDefinition(
            type: 'bky/mega-menu',
            label: self::tr('Mega Menu'),
            category: 'basic',
            description: self::tr('Top-level navigation with rich dropdown panels and column-based submenus.'),
            keywords: ['mega', 'menu', 'navigation', 'dropdown'],
            icon: '▤',
            schema: [
                'type' => 'object',
                'properties' => [
                    'menuLocation' => ['type' => 'string', 'default' => ''],
                    'items' => ['type' => 'array', 'default' => self::defaultMegaMenuItems(), 'items' => ['type' => 'object']],
                    'openOn' => ['type' => 'string', 'enum' => ['hover', 'click'], 'default' => 'hover'],
                    'columns' => ['type' => 'string', 'enum' => ['2', '3', '4'], 'default' => '3'],
                    'panelWidth' => ['type' => 'string', 'enum' => ['md', 'lg', 'xl', 'full'], 'default' => 'lg'],
                    'gap' => ['type' => 'string', 'enum' => ['sm', 'base', 'lg'], 'default' => 'base'],
                    'align' => ['type' => 'string', 'enum' => ['start', 'center', 'end'], 'default' => 'start'],
                ],
            ],
            variants: [
                'columns' => ['2' => 'grid-cols-1 md:grid-cols-2', '3' => 'grid-cols-1 md:grid-cols-2 lg:grid-cols-3', '4' => 'grid-cols-1 md:grid-cols-2 xl:grid-cols-4'],
                'panelWidth' => ['md' => 'w-[36rem] max-w-[calc(100vw-2rem)]', 'lg' => 'w-[48rem] max-w-[calc(100vw-2rem)]', 'xl' => 'w-[60rem] max-w-[calc(100vw-2rem)]', 'full' => 'w-[min(72rem,calc(100vw-2rem))]'],
                'gap' => ['sm' => 'gap-3', 'base' => 'gap-4', 'lg' => 'gap-6'],
                'align' => ['start' => 'justify-start', 'center' => 'justify-center', 'end' => 'justify-end'],
            ],
            renderer: new MegaMenuRenderer(),
            editorConfig: self::localizeEditorConfig([
                'tabs' => [
                    ['id' => 'content', 'label' => 'Content', 'controls' => [
                        ['id' => 'menuLocation', 'type' => 'text', 'label' => 'Menu Location'],
                        ['id' => 'items', 'type' => 'richtext', 'label' => 'Menu Structure'],
                        ['id' => 'openOn', 'type' => 'select', 'label' => 'Open On', 'options' => [['hover', 'Hover'], ['click', 'Click']]],
                    ]],
                    ['id' => 'layout', 'label' => 'Layout', 'controls' => [
                        ['id' => 'columns', 'type' => 'variant', 'label' => 'Dropdown Columns', 'variantKey' => 'columns'],
                        ['id' => 'panelWidth', 'type' => 'variant', 'label' => 'Panel Width', 'variantKey' => 'panelWidth'],
                        ['id' => 'gap', 'type' => 'variant', 'label' => 'Spacing', 'variantKey' => 'gap'],
                        ['id' => 'align', 'type' => 'variant', 'label' => 'Alignment', 'variantKey' => 'align'],
                    ]],
                ],
            ]),
        );
    }

    /** @return array<int, array<string, mixed>> */
    private static function defaultMegaMenuItems(): array
    {
        return [
            [
                'id' => 'products',
                'label' => 'Products',
                'href' => '/products',
                'description' => 'Explore the Blocky stack',
                'hideLabel' => false,
                'openInNewTab' => false,
                'useCustomContent' => false,
                'children' => [
                    [
                        'id' => 'builder',
                        'label' => 'Builder',
                        'href' => '/builder',
                        'description' => 'Compose full layouts visually',
                        'hideLabel' => false,
                        'openInNewTab' => false,
                        'useCustomContent' => false,
                        'children' => [],
                    ],
                    [
                        'id' => 'themes',
                        'label' => 'Themes',
                        'href' => '/themes',
                        'description' => 'Ship token-driven themes',
                        'hideLabel' => false,
                        'openInNewTab' => false,
                        'useCustomContent' => false,
                        'children' => [],
                    ],
                ],
            ],
            [
                'id' => 'resources',
                'label' => 'Resources',
                'href' => '/resources',
                'description' => 'Docs and examples',
                'hideLabel' => false,
                'openInNewTab' => false,
                'useCustomContent' => false,
                'children' => [
                    [
                        'id' => 'documentation',
                        'label' => 'Documentation',
                        'href' => '/docs',
                        'description' => 'Implementation guides',
                        'hideLabel' => false,
                        'openInNewTab' => false,
                        'useCustomContent' => false,
                        'children' => [],
                    ],
                    [
                        'id' => 'showcase',
                        'label' => 'Showcase',
                        'href' => '/showcase',
                        'description' => 'Real-world pages',
                        'hideLabel' => false,
                        'openInNewTab' => false,
                        'useCustomContent' => false,
                        'children' => [],
                    ],
                ],
            ],
        ];
    }

    private static function makeLoginForm(): BlockDefinition
    {
        return new BlockDefinition(
            type: 'bky/login-form',
            label: self::tr('Login Form'),
            category: 'basic',
            description: self::tr('Frontend login form with on-page validation feedback.'),
            keywords: ['login', 'auth', 'form'],
            icon: 'In',
            schema: [
                'type' => 'object',
                'properties' => [
                    'formId' => ['type' => 'string', 'default' => ''],
                    'redirectUrl' => ['type' => 'string', 'default' => ''],
                    'title' => ['type' => 'string', 'default' => 'Welcome back'],
                    'buttonLabel' => ['type' => 'string', 'default' => 'Sign in'],
                    'showRemember' => ['type' => 'boolean', 'default' => true],
                    'showLostPassword' => ['type' => 'boolean', 'default' => true],
                    'showRegisterLink' => ['type' => 'boolean', 'default' => true],
                    'gap' => ['type' => 'string', 'enum' => ['sm', 'base', 'lg'], 'default' => 'base'],
                    'padding' => ['type' => 'string', 'enum' => ['none', 'sm', 'base', 'lg'], 'default' => 'base'],
                    'background' => ['type' => 'string', 'enum' => ['transparent', 'surface', 'elevated'], 'default' => 'surface'],
                    'border' => ['type' => 'string', 'enum' => ['none', 'subtle', 'base'], 'default' => 'subtle'],
                    'radius' => ['type' => 'string', 'enum' => ['none', 'base', 'lg', 'xl'], 'default' => 'lg'],
                ],
            ],
            variants: [
                'gap' => ['sm' => 'gap-3', 'base' => 'gap-4', 'lg' => 'gap-6'],
                'padding' => ['none' => 'p-0', 'sm' => 'p-4', 'base' => 'p-6', 'lg' => 'p-8'],
                'background' => ['transparent' => '', 'surface' => 'bg-surface-base', 'elevated' => 'bg-surface-elevated'],
                'border' => ['none' => '', 'subtle' => 'border border-border-subtle', 'base' => 'border border-border-base'],
                'radius' => ['none' => 'rounded-none', 'base' => 'rounded-base', 'lg' => 'rounded-lg', 'xl' => 'rounded-xl'],
            ],
            renderer: new LoginFormRenderer(),
            editorConfig: self::localizeEditorConfig([
                'tabs' => [
                    ['id' => 'content', 'label' => 'Content', 'controls' => [
                        ['id' => 'formId', 'type' => 'text', 'label' => 'Form ID'],
                        ['id' => 'redirectUrl', 'type' => 'text', 'label' => 'Redirect URL'],
                        ['id' => 'title', 'type' => 'text', 'label' => 'Title'],
                        ['id' => 'buttonLabel', 'type' => 'text', 'label' => 'Button Label'],
                        ['id' => 'showRemember', 'type' => 'toggle', 'label' => 'Show Remember Me'],
                        ['id' => 'showLostPassword', 'type' => 'toggle', 'label' => 'Show Lost Password'],
                        ['id' => 'showRegisterLink', 'type' => 'toggle', 'label' => 'Show Register Link'],
                    ]],
                    ['id' => 'style', 'label' => 'Style', 'controls' => [
                        ['id' => 'gap', 'type' => 'variant', 'label' => 'Spacing', 'variantKey' => 'gap'],
                        ['id' => 'padding', 'type' => 'variant', 'label' => 'Padding', 'variantKey' => 'padding'],
                        ['id' => 'background', 'type' => 'variant', 'label' => 'Background', 'variantKey' => 'background'],
                        ['id' => 'border', 'type' => 'variant', 'label' => 'Border', 'variantKey' => 'border'],
                        ['id' => 'radius', 'type' => 'variant', 'label' => 'Radius', 'variantKey' => 'radius'],
                    ]],
                ],
            ]),
        );
    }

    private static function makeRegisterForm(): BlockDefinition
    {
        return new BlockDefinition(
            type: 'bky/register-form',
            label: self::tr('Register Form'),
            category: 'basic',
            description: self::tr('Frontend registration form with WordPress account creation.'),
            keywords: ['register', 'signup', 'auth', 'form'],
            icon: 'Up',
            schema: [
                'type' => 'object',
                'properties' => [
                    'formId' => ['type' => 'string', 'default' => ''],
                    'redirectUrl' => ['type' => 'string', 'default' => ''],
                    'title' => ['type' => 'string', 'default' => 'Create your account'],
                    'submitLabel' => ['type' => 'string', 'default' => 'Create account'],
                    'successMessage' => ['type' => 'string', 'default' => 'Your account has been created.'],
                    'loginAfterRegister' => ['type' => 'boolean', 'default' => false],
                    'gap' => ['type' => 'string', 'enum' => ['sm', 'base', 'lg'], 'default' => 'base'],
                    'padding' => ['type' => 'string', 'enum' => ['none', 'sm', 'base', 'lg'], 'default' => 'base'],
                    'background' => ['type' => 'string', 'enum' => ['transparent', 'surface', 'elevated'], 'default' => 'surface'],
                    'border' => ['type' => 'string', 'enum' => ['none', 'subtle', 'base'], 'default' => 'subtle'],
                    'radius' => ['type' => 'string', 'enum' => ['none', 'base', 'lg', 'xl'], 'default' => 'lg'],
                ],
            ],
            variants: [
                'gap' => ['sm' => 'gap-3', 'base' => 'gap-4', 'lg' => 'gap-6'],
                'padding' => ['none' => 'p-0', 'sm' => 'p-4', 'base' => 'p-6', 'lg' => 'p-8'],
                'background' => ['transparent' => '', 'surface' => 'bg-surface-base', 'elevated' => 'bg-surface-elevated'],
                'border' => ['none' => '', 'subtle' => 'border border-border-subtle', 'base' => 'border border-border-base'],
                'radius' => ['none' => 'rounded-none', 'base' => 'rounded-base', 'lg' => 'rounded-lg', 'xl' => 'rounded-xl'],
            ],
            renderer: new RegisterFormRenderer(),
            editorConfig: self::localizeEditorConfig([
                'tabs' => [
                    ['id' => 'content', 'label' => 'Content', 'controls' => [
                        ['id' => 'formId', 'type' => 'text', 'label' => 'Form ID'],
                        ['id' => 'redirectUrl', 'type' => 'text', 'label' => 'Redirect URL'],
                        ['id' => 'title', 'type' => 'text', 'label' => 'Title'],
                        ['id' => 'submitLabel', 'type' => 'text', 'label' => 'Submit Label'],
                        ['id' => 'successMessage', 'type' => 'text', 'label' => 'Success Message'],
                        ['id' => 'loginAfterRegister', 'type' => 'toggle', 'label' => 'Login After Register'],
                    ]],
                    ['id' => 'style', 'label' => 'Style', 'controls' => [
                        ['id' => 'gap', 'type' => 'variant', 'label' => 'Spacing', 'variantKey' => 'gap'],
                        ['id' => 'padding', 'type' => 'variant', 'label' => 'Padding', 'variantKey' => 'padding'],
                        ['id' => 'background', 'type' => 'variant', 'label' => 'Background', 'variantKey' => 'background'],
                        ['id' => 'border', 'type' => 'variant', 'label' => 'Border', 'variantKey' => 'border'],
                        ['id' => 'radius', 'type' => 'variant', 'label' => 'Radius', 'variantKey' => 'radius'],
                    ]],
                ],
            ]),
        );
    }

    private static function makeContactForm(): BlockDefinition
    {
        return new BlockDefinition(
            type: 'bky/contact-form',
            label: self::tr('Contact Form'),
            category: 'basic',
            description: self::tr('Ready-made contact form preset saved into Gennaker form submissions.'),
            keywords: ['contact', 'form', 'message'],
            icon: 'Mail',
            schema: [
                'type' => 'object',
                'properties' => [
                    'formId' => ['type' => 'string', 'default' => ''],
                    'title' => ['type' => 'string', 'default' => 'Let\'s talk'],
                    'successMessage' => ['type' => 'string', 'default' => 'Thanks, we received your message.'],
                    'nameLabel' => ['type' => 'string', 'default' => 'Name'],
                    'emailLabel' => ['type' => 'string', 'default' => 'Email'],
                    'subjectLabel' => ['type' => 'string', 'default' => 'Subject'],
                    'messageLabel' => ['type' => 'string', 'default' => 'Message'],
                    'submitLabel' => ['type' => 'string', 'default' => 'Send message'],
                    'gap' => ['type' => 'string', 'enum' => ['sm', 'base', 'lg'], 'default' => 'base'],
                    'padding' => ['type' => 'string', 'enum' => ['none', 'sm', 'base', 'lg'], 'default' => 'base'],
                    'background' => ['type' => 'string', 'enum' => ['transparent', 'surface', 'elevated'], 'default' => 'surface'],
                    'border' => ['type' => 'string', 'enum' => ['none', 'subtle', 'base'], 'default' => 'subtle'],
                    'radius' => ['type' => 'string', 'enum' => ['none', 'base', 'lg', 'xl'], 'default' => 'lg'],
                ],
            ],
            variants: [
                'gap' => ['sm' => 'gap-3', 'base' => 'gap-4', 'lg' => 'gap-6'],
                'padding' => ['none' => 'p-0', 'sm' => 'p-4', 'base' => 'p-6', 'lg' => 'p-8'],
                'background' => ['transparent' => '', 'surface' => 'bg-surface-base', 'elevated' => 'bg-surface-elevated'],
                'border' => ['none' => '', 'subtle' => 'border border-border-subtle', 'base' => 'border border-border-base'],
                'radius' => ['none' => 'rounded-none', 'base' => 'rounded-base', 'lg' => 'rounded-lg', 'xl' => 'rounded-xl'],
            ],
            renderer: new ContactFormRenderer(),
            editorConfig: self::localizeEditorConfig([
                'tabs' => [
                    ['id' => 'content', 'label' => 'Content', 'controls' => [
                        ['id' => 'formId', 'type' => 'text', 'label' => 'Form ID'],
                        ['id' => 'title', 'type' => 'text', 'label' => 'Title'],
                        ['id' => 'successMessage', 'type' => 'text', 'label' => 'Success Message'],
                        ['id' => 'nameLabel', 'type' => 'text', 'label' => 'Name Label'],
                        ['id' => 'emailLabel', 'type' => 'text', 'label' => 'Email Label'],
                        ['id' => 'subjectLabel', 'type' => 'text', 'label' => 'Subject Label'],
                        ['id' => 'messageLabel', 'type' => 'text', 'label' => 'Message Label'],
                        ['id' => 'submitLabel', 'type' => 'text', 'label' => 'Submit Label'],
                    ]],
                    ['id' => 'style', 'label' => 'Style', 'controls' => [
                        ['id' => 'gap', 'type' => 'variant', 'label' => 'Spacing', 'variantKey' => 'gap'],
                        ['id' => 'padding', 'type' => 'variant', 'label' => 'Padding', 'variantKey' => 'padding'],
                        ['id' => 'background', 'type' => 'variant', 'label' => 'Background', 'variantKey' => 'background'],
                        ['id' => 'border', 'type' => 'variant', 'label' => 'Border', 'variantKey' => 'border'],
                        ['id' => 'radius', 'type' => 'variant', 'label' => 'Radius', 'variantKey' => 'radius'],
                    ]],
                ],
            ]),
        );
    }

    private static function makeAuthorBox(): BlockDefinition
    {
        return new BlockDefinition(
            type: 'bky/author-box',
            label: self::tr('Author Box'),
            category: 'basic',
            description: self::tr('Current post author profile with avatar, bio, and archive link.'),
            keywords: ['author', 'profile', 'bio'],
            icon: 'Au',
            schema: [
                'type' => 'object',
                'properties' => [
                    'title' => ['type' => 'string', 'default' => 'About the author'],
                    'showAvatar' => ['type' => 'boolean', 'default' => true],
                    'showBio' => ['type' => 'boolean', 'default' => true],
                    'showArchiveLink' => ['type' => 'boolean', 'default' => true],
                    'avatarSize' => ['type' => 'string', 'enum' => ['sm', 'base', 'lg'], 'default' => 'base'],
                    'layout' => ['type' => 'string', 'enum' => ['row', 'stacked'], 'default' => 'row'],
                    'padding' => ['type' => 'string', 'enum' => ['none', 'sm', 'base', 'lg'], 'default' => 'base'],
                    'background' => ['type' => 'string', 'enum' => ['transparent', 'surface', 'elevated'], 'default' => 'surface'],
                    'border' => ['type' => 'string', 'enum' => ['none', 'subtle', 'base'], 'default' => 'subtle'],
                    'radius' => ['type' => 'string', 'enum' => ['none', 'base', 'lg', 'xl'], 'default' => 'lg'],
                ],
            ],
            variants: [
                'avatarSize' => ['sm' => 'h-14 w-14', 'base' => 'h-20 w-20', 'lg' => 'h-24 w-24'],
                'layout' => ['row' => 'flex-row items-start', 'stacked' => 'flex-col items-start'],
                'padding' => ['none' => 'p-0', 'sm' => 'p-4', 'base' => 'p-6', 'lg' => 'p-8'],
                'background' => ['transparent' => '', 'surface' => 'bg-surface-base', 'elevated' => 'bg-surface-elevated'],
                'border' => ['none' => '', 'subtle' => 'border border-border-subtle', 'base' => 'border border-border-base'],
                'radius' => ['none' => 'rounded-none', 'base' => 'rounded-base', 'lg' => 'rounded-lg', 'xl' => 'rounded-xl'],
            ],
            renderer: new AuthorBoxRenderer(),
            editorConfig: self::localizeEditorConfig([
                'tabs' => [
                    ['id' => 'content', 'label' => 'Content', 'controls' => [
                        ['id' => 'title', 'type' => 'text', 'label' => 'Title'],
                        ['id' => 'showAvatar', 'type' => 'toggle', 'label' => 'Show Avatar'],
                        ['id' => 'showBio', 'type' => 'toggle', 'label' => 'Show Bio'],
                        ['id' => 'showArchiveLink', 'type' => 'toggle', 'label' => 'Show Archive Link'],
                    ]],
                    ['id' => 'layout', 'label' => 'Layout', 'controls' => [
                        ['id' => 'avatarSize', 'type' => 'variant', 'label' => 'Avatar Size', 'variantKey' => 'avatarSize'],
                        ['id' => 'layout', 'type' => 'variant', 'label' => 'Layout', 'variantKey' => 'layout'],
                    ]],
                    ['id' => 'style', 'label' => 'Style', 'controls' => [
                        ['id' => 'padding', 'type' => 'variant', 'label' => 'Padding', 'variantKey' => 'padding'],
                        ['id' => 'background', 'type' => 'variant', 'label' => 'Background', 'variantKey' => 'background'],
                        ['id' => 'border', 'type' => 'variant', 'label' => 'Border', 'variantKey' => 'border'],
                        ['id' => 'radius', 'type' => 'variant', 'label' => 'Radius', 'variantKey' => 'radius'],
                    ]],
                ],
            ]),
        );
    }

    private static function makeComments(): BlockDefinition
    {
        return new BlockDefinition(
            type: 'bky/comments',
            label: self::tr('Comments'),
            category: 'basic',
            description: self::tr('Approved comments for the current post with simple list styling.'),
            keywords: ['comments', 'discussion'],
            icon: 'Cm',
            schema: [
                'type' => 'object',
                'properties' => [
                    'title' => ['type' => 'string', 'default' => 'Discussion'],
                    'perPage' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 20, 'default' => 6],
                    'showAvatar' => ['type' => 'boolean', 'default' => true],
                    'showDate' => ['type' => 'boolean', 'default' => true],
                    'order' => ['type' => 'string', 'enum' => ['asc', 'desc'], 'default' => 'asc'],
                ],
            ],
            variants: [],
            renderer: new CommentsRenderer(),
            editorConfig: self::localizeEditorConfig([
                'tabs' => [
                    ['id' => 'content', 'label' => 'Content', 'controls' => [
                        ['id' => 'title', 'type' => 'text', 'label' => 'Title'],
                        ['id' => 'perPage', 'type' => 'number', 'label' => 'Comments Per Page', 'min' => 1, 'max' => 20, 'step' => 1],
                        ['id' => 'showAvatar', 'type' => 'toggle', 'label' => 'Show Avatar'],
                        ['id' => 'showDate', 'type' => 'toggle', 'label' => 'Show Date'],
                        ['id' => 'order', 'type' => 'select', 'label' => 'Order', 'options' => [['asc', 'Oldest first'], ['desc', 'Newest first']]],
                    ]],
                ],
            ]),
        );
    }

    private static function makeCommentForm(): BlockDefinition
    {
        return new BlockDefinition(
            type: 'bky/comment-form',
            label: self::tr('Comment Form'),
            category: 'basic',
            description: self::tr('WordPress comment form for the current post.'),
            keywords: ['comment', 'reply', 'form'],
            icon: 'Cf',
            schema: [
                'type' => 'object',
                'properties' => [
                    'titleReply' => ['type' => 'string', 'default' => 'Leave a reply'],
                    'buttonLabel' => ['type' => 'string', 'default' => 'Post comment'],
                    'showNotes' => ['type' => 'boolean', 'default' => true],
                ],
            ],
            variants: [],
            renderer: new CommentFormRenderer(),
            editorConfig: self::localizeEditorConfig([
                'tabs' => [
                    ['id' => 'content', 'label' => 'Content', 'controls' => [
                        ['id' => 'titleReply', 'type' => 'text', 'label' => 'Title Reply'],
                        ['id' => 'buttonLabel', 'type' => 'text', 'label' => 'Button Label'],
                        ['id' => 'showNotes', 'type' => 'toggle', 'label' => 'Show Notes'],
                    ]],
                ],
            ]),
        );
    }

    private static function makePostNavigation(): BlockDefinition
    {
        return new BlockDefinition(
            type: 'bky/post-navigation',
            label: self::tr('Post Navigation'),
            category: 'basic',
            description: self::tr('Previous and next post links for singular post templates.'),
            keywords: ['post', 'navigation', 'previous', 'next'],
            icon: '↔',
            schema: [
                'type' => 'object',
                'properties' => [
                    'prevLabel' => ['type' => 'string', 'default' => 'Previous post'],
                    'nextLabel' => ['type' => 'string', 'default' => 'Next post'],
                    'layout' => ['type' => 'string', 'enum' => ['between', 'stacked'], 'default' => 'between'],
                    'showLabels' => ['type' => 'boolean', 'default' => true],
                ],
            ],
            variants: [
                'layout' => ['between' => 'flex-row justify-between items-start', 'stacked' => 'flex-col gap-4 items-start'],
            ],
            renderer: new PostNavigationRenderer(),
            editorConfig: self::localizeEditorConfig([
                'tabs' => [
                    ['id' => 'content', 'label' => 'Content', 'controls' => [
                        ['id' => 'prevLabel', 'type' => 'text', 'label' => 'Previous Label'],
                        ['id' => 'nextLabel', 'type' => 'text', 'label' => 'Next Label'],
                        ['id' => 'showLabels', 'type' => 'toggle', 'label' => 'Show Labels'],
                    ]],
                    ['id' => 'layout', 'label' => 'Layout', 'controls' => [
                        ['id' => 'layout', 'type' => 'variant', 'label' => 'Layout', 'variantKey' => 'layout'],
                    ]],
                ],
            ]),
        );
    }

    private static function makeSitemap(): BlockDefinition
    {
        return new BlockDefinition(
            type: 'bky/sitemap',
            label: self::tr('Sitemap'),
            category: 'basic',
            description: self::tr('Hierarchical sitemap for pages with optional recent posts.'),
            keywords: ['sitemap', 'pages', 'navigation'],
            icon: 'Map',
            schema: [
                'type' => 'object',
                'properties' => [
                    'title' => ['type' => 'string', 'default' => 'Sitemap'],
                    'includePages' => ['type' => 'boolean', 'default' => true],
                    'includePosts' => ['type' => 'boolean', 'default' => true],
                    'postsPerPage' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 20, 'default' => 6],
                    'showDescriptions' => ['type' => 'boolean', 'default' => false],
                    'orderBy' => ['type' => 'string', 'enum' => ['menu_order', 'title', 'date'], 'default' => 'menu_order'],
                ],
            ],
            variants: [],
            renderer: new SitemapRenderer(),
            editorConfig: self::localizeEditorConfig([
                'tabs' => [
                    ['id' => 'content', 'label' => 'Content', 'controls' => [
                        ['id' => 'title', 'type' => 'text', 'label' => 'Title'],
                        ['id' => 'includePages', 'type' => 'toggle', 'label' => 'Include Pages'],
                        ['id' => 'includePosts', 'type' => 'toggle', 'label' => 'Include Posts'],
                        ['id' => 'postsPerPage', 'type' => 'number', 'label' => 'Posts Per Page', 'min' => 1, 'max' => 20, 'step' => 1],
                        ['id' => 'showDescriptions', 'type' => 'toggle', 'label' => 'Show Descriptions'],
                        ['id' => 'orderBy', 'type' => 'select', 'label' => 'Order By', 'options' => [['menu_order', 'Menu Order'], ['title', 'Title'], ['date', 'Date']]],
                    ]],
                ],
            ]),
        );
    }

    private static function makeTableOfContents(): BlockDefinition
    {
        return new BlockDefinition(
            type: 'bky/table-of-contents',
            label: self::tr('Table of Contents'),
            category: 'basic',
            description: self::tr('Auto-generated heading index from the current Gennaker document.'),
            keywords: ['toc', 'contents', 'headings', 'anchor'],
            icon: 'ToC',
            schema: [
                'type' => 'object',
                'properties' => [
                    'title' => ['type' => 'string', 'default' => 'On this page'],
                    'minLevel' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 6, 'default' => 2],
                    'maxLevel' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 6, 'default' => 4],
                    'ordered' => ['type' => 'boolean', 'default' => false],
                ],
            ],
            variants: [],
            renderer: new TableOfContentsRenderer(),
            editorConfig: self::localizeEditorConfig([
                'tabs' => [
                    ['id' => 'content', 'label' => 'Content', 'controls' => [
                        ['id' => 'title', 'type' => 'text', 'label' => 'Title'],
                        ['id' => 'minLevel', 'type' => 'number', 'label' => 'Minimum Heading Level', 'min' => 1, 'max' => 6, 'step' => 1],
                        ['id' => 'maxLevel', 'type' => 'number', 'label' => 'Maximum Heading Level', 'min' => 1, 'max' => 6, 'step' => 1],
                        ['id' => 'ordered', 'type' => 'toggle', 'label' => 'Ordered List'],
                    ]],
                ],
            ]),
        );
    }

    private static function makeScrollProgress(): BlockDefinition
    {
        return new BlockDefinition(
            type: 'bky/scroll-progress',
            label: self::tr('Scroll Progress'),
            category: 'basic',
            description: self::tr('Fixed reading progress indicator that tracks the current page scroll position.'),
            keywords: ['scroll', 'progress', 'reading', 'indicator'],
            icon: 'Scp',
            schema: [
                'type' => 'object',
                'properties' => [
                    'position' => ['type' => 'string', 'enum' => ['top', 'bottom'], 'default' => 'top'],
                    'height' => ['type' => 'string', 'enum' => ['sm', 'base', 'lg'], 'default' => 'sm'],
                    'tone' => ['type' => 'string', 'enum' => ['accent', 'success', 'contrast'], 'default' => 'accent'],
                    'showTrack' => ['type' => 'boolean', 'default' => true],
                ],
            ],
            variants: [],
            renderer: new ScrollProgressRenderer(),
            editorConfig: self::localizeEditorConfig([
                'tabs' => [
                    ['id' => 'content', 'label' => 'Content', 'controls' => [
                        ['id' => 'position', 'type' => 'select', 'label' => 'Position', 'options' => [['top', 'Top'], ['bottom', 'Bottom']]],
                        ['id' => 'height', 'type' => 'select', 'label' => 'Height', 'options' => [['sm', 'Small'], ['base', 'Base'], ['lg', 'Large']]],
                        ['id' => 'tone', 'type' => 'select', 'label' => 'Tone', 'options' => [['accent', 'Accent'], ['success', 'Success'], ['contrast', 'Contrast']]],
                        ['id' => 'showTrack', 'type' => 'toggle', 'label' => 'Show Track'],
                    ]],
                ],
            ]),
        );
    }

    private static function makeStickyBar(): BlockDefinition
    {
        return new BlockDefinition(
            type: 'bky/sticky-bar',
            label: self::tr('Sticky Bar'),
            category: 'basic',
            description: self::tr('Fixed callout bar that can appear after visitors start scrolling.'),
            keywords: ['sticky', 'bar', 'announcement', 'cta'],
            icon: 'Sty',
            schema: [
                'type' => 'object',
                'properties' => [
                    'title' => ['type' => 'string', 'default' => 'Keep this page within reach'],
                    'text' => ['type' => 'string', 'default' => 'Pin an important CTA, promo, or status message while visitors scroll.'],
                    'buttonLabel' => ['type' => 'string', 'default' => 'Get started'],
                    'buttonUrl' => ['type' => 'string', 'default' => '#'],
                    'position' => ['type' => 'string', 'enum' => ['top', 'bottom'], 'default' => 'bottom'],
                    'showAfter' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 100, 'default' => 15],
                    'dismissible' => ['type' => 'boolean', 'default' => true],
                    'tone' => ['type' => 'string', 'enum' => ['surface', 'accent', 'contrast'], 'default' => 'surface'],
                ],
            ],
            variants: [],
            renderer: new StickyBarRenderer(),
            editorConfig: self::localizeEditorConfig([
                'tabs' => [
                    ['id' => 'content', 'label' => 'Content', 'controls' => [
                        ['id' => 'title', 'type' => 'text', 'label' => 'Title'],
                        ['id' => 'text', 'type' => 'richtext', 'label' => 'Text'],
                        ['id' => 'buttonLabel', 'type' => 'text', 'label' => 'Button Label'],
                        ['id' => 'buttonUrl', 'type' => 'text', 'label' => 'Button URL'],
                        ['id' => 'position', 'type' => 'select', 'label' => 'Position', 'options' => [['top', 'Top'], ['bottom', 'Bottom']]],
                        ['id' => 'showAfter', 'type' => 'number', 'label' => 'Show After', 'min' => 0, 'max' => 100, 'step' => 1],
                        ['id' => 'dismissible', 'type' => 'toggle', 'label' => 'Dismissible'],
                        ['id' => 'tone', 'type' => 'select', 'label' => 'Tone', 'options' => [['surface', 'Surface'], ['accent', 'Accent'], ['contrast', 'Contrast']]],
                    ]],
                ],
            ]),
        );
    }

    private static function makeBackToTop(): BlockDefinition
    {
        return new BlockDefinition(
            type: 'bky/back-to-top',
            label: self::tr('Back to Top'),
            category: 'basic',
            description: self::tr('Floating button that appears after scrolling and smoothly returns to the top of the page.'),
            keywords: ['back to top', 'scroll', 'floating button'],
            icon: 'Top',
            schema: [
                'type' => 'object',
                'properties' => [
                    'label' => ['type' => 'string', 'default' => 'Back to top'],
                    'showLabel' => ['type' => 'boolean', 'default' => false],
                    'position' => ['type' => 'string', 'enum' => ['left', 'right'], 'default' => 'right'],
                    'showAfter' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 100, 'default' => 20],
                    'size' => ['type' => 'string', 'enum' => ['sm', 'base', 'lg'], 'default' => 'base'],
                    'tone' => ['type' => 'string', 'enum' => ['accent', 'surface', 'contrast'], 'default' => 'accent'],
                ],
            ],
            variants: [],
            renderer: new BackToTopRenderer(),
            editorConfig: self::localizeEditorConfig([
                'tabs' => [
                    ['id' => 'content', 'label' => 'Content', 'controls' => [
                        ['id' => 'label', 'type' => 'text', 'label' => 'Label'],
                        ['id' => 'showLabel', 'type' => 'toggle', 'label' => 'Show Label'],
                        ['id' => 'position', 'type' => 'select', 'label' => 'Position', 'options' => [['left', 'Left'], ['right', 'Right']]],
                        ['id' => 'showAfter', 'type' => 'number', 'label' => 'Show After', 'min' => 0, 'max' => 100, 'step' => 1],
                        ['id' => 'size', 'type' => 'select', 'label' => 'Size', 'options' => [['sm', 'Small'], ['base', 'Base'], ['lg', 'Large']]],
                        ['id' => 'tone', 'type' => 'select', 'label' => 'Tone', 'options' => [['accent', 'Accent'], ['surface', 'Surface'], ['contrast', 'Contrast']]],
                    ]],
                ],
            ]),
        );
    }

    private static function makePopup(): BlockDefinition
    {
        return self::makeOverlayDefinition(
            type: 'bky/popup',
            label: 'Popup',
            description: 'Popup overlay with automatic triggers like load, delay, exit-intent, scroll, or click selector.',
            icon: 'Pup',
            variant: 'popup',
            defaults: ['placement' => 'center', 'size' => 'md', 'backdrop' => 'dim', 'dismissOutside' => true, 'focusTrap' => true, 'title' => 'Popup title', 'description' => 'Use this popup for announcements, lead capture, or offers.', 'openOn' => 'exit-intent'],
        );
    }

    private static function makeNotificationToast(): BlockDefinition
    {
        return self::makeOverlayDefinition(
            type: 'bky/notification-toast',
            label: 'Notification Toast',
            description: 'Non-blocking toast notification that can appear after a delay or be opened programmatically.',
            icon: 'Tst',
            variant: 'notification-toast',
            defaults: ['placement' => 'right', 'size' => 'sm', 'backdrop' => 'none', 'dismissOutside' => false, 'focusTrap' => false, 'title' => 'Saved', 'description' => 'Your changes were saved successfully.', 'openOn' => 'delay:1500'],
        );
    }

    private static function makeCookieBanner(): BlockDefinition
    {
        return new BlockDefinition(
            type: 'bky/cookie-banner',
            label: self::tr('Cookie Banner'),
            category: 'basic',
            description: self::tr('Consent banner preset that stores accept or reject decisions in a cookie.'),
            keywords: ['cookie', 'consent', 'banner', 'overlay'],
            icon: 'Ckb',
            schema: [
                'type' => 'object',
                'properties' => self::overlayProperties(['placement' => 'bottom', 'size' => 'xl', 'backdrop' => 'none', 'dismissOutside' => false, 'focusTrap' => false, 'title' => 'We use cookies', 'description' => 'Use cookies to improve the browsing experience and measure content performance.', 'openOn' => 'load']) + [
                    'cookieName' => ['type' => 'string', 'default' => 'blocky_cookie_consent'],
                    'acceptLabel' => ['type' => 'string', 'default' => 'Accept'],
                    'rejectLabel' => ['type' => 'string', 'default' => 'Reject'],
                    'cookieDurationDays' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 3650, 'default' => 180],
                ],
            ],
            variants: self::overlayVariants(),
            renderer: new OverlayRenderer('cookie-banner'),
            editorConfig: self::localizeEditorConfig([
                'hasSlots' => ['body', 'header', 'footer', 'close'],
                'isContainer' => true,
                'tabs' => [
                    ['id' => 'content', 'label' => 'Content', 'controls' => [
                        ['id' => 'overlayId', 'type' => 'text', 'label' => 'Overlay ID'],
                        ['id' => 'title', 'type' => 'text', 'label' => 'Title'],
                        ['id' => 'description', 'type' => 'richtext', 'label' => 'Description'],
                        ['id' => 'cookieName', 'type' => 'text', 'label' => 'Cookie Name'],
                        ['id' => 'acceptLabel', 'type' => 'text', 'label' => 'Accept Label'],
                        ['id' => 'rejectLabel', 'type' => 'text', 'label' => 'Reject Label'],
                        ['id' => 'cookieDurationDays', 'type' => 'number', 'label' => 'Cookie Duration Days', 'min' => 1, 'max' => 3650, 'step' => 1],
                        ['id' => 'openOn', 'type' => 'text', 'label' => 'Open On'],
                        ['id' => 'frequency', 'type' => 'select', 'label' => 'Frequency', 'options' => [['always', 'Always'], ['session', 'Once per Session'], ['day', 'Once per Day'], ['week', 'Once per Week'], ['once', 'Only Once']]],
                        ['id' => 'closeOn', 'type' => 'text', 'label' => 'Close On'],
                        ['id' => 'defaultOpen', 'type' => 'toggle', 'label' => 'Open by Default'],
                        ['id' => 'dismissEsc', 'type' => 'toggle', 'label' => 'ESC to Close'],
                        ['id' => 'dismissOutside', 'type' => 'toggle', 'label' => 'Outside Click Closes'],
                        ['id' => 'focusTrap', 'type' => 'toggle', 'label' => 'Trap Focus'],
                    ]],
                    ['id' => 'layout', 'label' => 'Layout', 'controls' => [
                        ['id' => 'placement', 'type' => 'variant', 'label' => 'Placement', 'variantKey' => 'placement'],
                        ['id' => 'size', 'type' => 'variant', 'label' => 'Size', 'variantKey' => 'size'],
                    ]],
                    ['id' => 'style', 'label' => 'Style', 'controls' => [
                        ['id' => 'backdrop', 'type' => 'variant', 'label' => 'Backdrop', 'variantKey' => 'backdrop'],
                    ]],
                ],
            ]),
        );
    }

    private static function makeLightbox(): BlockDefinition
    {
        return new BlockDefinition(
            type: 'bky/lightbox',
            label: self::tr('Lightbox'),
            category: 'basic',
            description: self::tr('Self-contained media lightbox with trigger and overlay preview.'),
            keywords: ['lightbox', 'media', 'image', 'video'],
            icon: 'Lbx',
            schema: [
                'type' => 'object',
                'properties' => [
                    'overlayId' => ['type' => 'string', 'default' => ''],
                    'triggerLabel' => ['type' => 'string', 'default' => 'Open media'],
                    'mediaUrl' => ['type' => 'string', 'default' => ''],
                    'mediaType' => ['type' => 'string', 'enum' => ['image', 'video', 'iframe'], 'default' => 'image'],
                    'caption' => ['type' => 'string', 'default' => ''],
                    'thumbnailUrl' => ['type' => 'string', 'default' => ''],
                    'aspectRatio' => ['type' => 'string', 'enum' => ['square', 'video', 'wide'], 'default' => 'video'],
                ],
            ],
            variants: [
                'aspectRatio' => ['square' => 'aspect-square', 'video' => 'aspect-video', 'wide' => 'aspect-[21/9]'],
            ],
            renderer: new LightboxRenderer(),
            editorConfig: self::localizeEditorConfig([
                'tabs' => [
                    ['id' => 'content', 'label' => 'Content', 'controls' => [
                        ['id' => 'overlayId', 'type' => 'text', 'label' => 'Overlay ID'],
                        ['id' => 'triggerLabel', 'type' => 'text', 'label' => 'Trigger Label'],
                        ['id' => 'mediaUrl', 'type' => 'text', 'label' => 'Media URL'],
                        ['id' => 'mediaType', 'type' => 'select', 'label' => 'Media Type', 'options' => [['image', 'Image'], ['video', 'Video'], ['iframe', 'Iframe']]],
                        ['id' => 'caption', 'type' => 'text', 'label' => 'Caption'],
                        ['id' => 'thumbnailUrl', 'type' => 'text', 'label' => 'Thumbnail URL'],
                    ]],
                    ['id' => 'layout', 'label' => 'Layout', 'controls' => [
                        ['id' => 'aspectRatio', 'type' => 'variant', 'label' => 'Aspect Ratio', 'variantKey' => 'aspectRatio'],
                    ]],
                ],
            ]),
        );
    }

    private static function makeCommandPalette(): BlockDefinition
    {
        return self::makeOverlayDefinition(
            type: 'bky/command-palette',
            label: 'Command Palette',
            description: 'Keyboard-first overlay preset that opens on Cmd/Ctrl+K and exposes searchable quick actions.',
            icon: 'Cmd',
            variant: 'command-palette',
            defaults: ['placement' => 'top', 'size' => 'lg', 'backdrop' => 'blur', 'dismissOutside' => true, 'focusTrap' => true, 'title' => 'Command palette', 'description' => 'Search common actions, routes, or documentation.', 'openOn' => 'shortcut:mod+k'],
        );
    }

    private static function makeTabs(): BlockDefinition
    {
        return new BlockDefinition(
            type: 'bky/tabs',
            label: self::tr('Tabs'),
            category: 'basic',
            description: self::tr('Tabbed content driven by title and content pairs.'),
            keywords: ['tabs', 'panels', 'switcher'],
            icon: '⊞',
            schema: [
                'type' => 'object',
                'properties' => [
                    'items' => ['type' => 'string', 'default' => "Tab 1|First tab content\nTab 2|Second tab content\nTab 3|Third tab content", 'listFields' => [ [ 'key' => 'title', 'label' => 'Tab title' ], [ 'key' => 'content', 'label' => 'Tab content' ] ]],
                    'activeIndex' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 10, 'default' => 0],
                ],
            ],
            variants: [],
            renderer: new TabsRenderer(),
            editorConfig: self::localizeEditorConfig([
                'tabs' => [
                    ['id' => 'content', 'label' => 'Content', 'controls' => [
                        ['id' => 'items', 'type' => 'richtext', 'label' => 'Items'],
                        ['id' => 'activeIndex', 'type' => 'number', 'label' => 'Active Index', 'min' => 0, 'max' => 10, 'step' => 1],
                    ]],
                ],
            ]),
        );
    }

    private static function makeAccordion(): BlockDefinition
    {
        return new BlockDefinition(
            type: 'bky/accordion',
            label: self::tr('Accordion'),
            category: 'basic',
            description: self::tr('FAQ-style accordion driven by title and content pairs.'),
            keywords: ['accordion', 'faq'],
            icon: '≣',
            schema: [
                'type' => 'object',
                'properties' => [
                    'items' => ['type' => 'string', 'default' => "Question 1|Answer one\nQuestion 2|Answer two", 'listFields' => [ [ 'key' => 'title', 'label' => 'Item title' ], [ 'key' => 'content', 'label' => 'Item content' ] ]],
                    'openFirst' => ['type' => 'boolean', 'default' => true],
                ],
            ],
            variants: [],
            renderer: new AccordionRenderer(),
            editorConfig: self::localizeEditorConfig([
                'tabs' => [
                    ['id' => 'content', 'label' => 'Content', 'controls' => [
                        ['id' => 'items', 'type' => 'richtext', 'label' => 'Items'],
                        ['id' => 'openFirst', 'type' => 'toggle', 'label' => 'Open First Item'],
                    ]],
                ],
            ]),
        );
    }

    private static function makeToggle(): BlockDefinition
    {
        return new BlockDefinition(
            type: 'bky/toggle',
            label: self::tr('Toggle'),
            category: 'basic',
            description: self::tr('Single collapsible panel with a title and content.'),
            keywords: ['toggle', 'collapse'],
            icon: '▾',
            schema: [
                'type' => 'object',
                'properties' => [
                    'title' => ['type' => 'string', 'default' => 'Toggle title'],
                    'content' => ['type' => 'string', 'default' => 'Toggle content'],
                    'open' => ['type' => 'boolean', 'default' => false],
                ],
            ],
            variants: [],
            renderer: new ToggleRenderer(),
            editorConfig: self::localizeEditorConfig([
                'tabs' => [
                    ['id' => 'content', 'label' => 'Content', 'controls' => [
                        ['id' => 'title', 'type' => 'text', 'label' => 'Title'],
                        ['id' => 'content', 'type' => 'richtext', 'label' => 'Content'],
                        ['id' => 'open', 'type' => 'toggle', 'label' => 'Open by Default'],
                    ]],
                ],
            ]),
        );
    }

    private static function makeForm(): BlockDefinition
    {
        return new BlockDefinition(
            type: 'bky/form',
            label: self::tr('Form'),
            category: 'basic',
            description: self::tr('Form container that groups Gennaker form fields and submit controls.'),
            keywords: ['form', 'fields', 'submit'],
            icon: 'Frm',
            schema: [
                'type' => 'object',
                'properties' => [
                    'formId' => ['type' => 'string', 'default' => ''],
                    'action' => ['type' => 'string', 'default' => ''],
                    'method' => ['type' => 'string', 'enum' => ['post', 'get'], 'default' => 'post'],
                    'name' => ['type' => 'string', 'default' => ''],
                    'successMessage' => ['type' => 'string', 'default' => 'Thanks, your submission has been saved.'],
                    'enctype' => ['type' => 'string', 'enum' => ['default', 'multipart'], 'default' => 'default'],
                    'noValidate' => ['type' => 'boolean', 'default' => false],
                    'gap' => ['type' => 'string', 'enum' => ['sm', 'base', 'lg'], 'default' => 'base'],
                    'align' => ['type' => 'string', 'enum' => ['start', 'center', 'end'], 'default' => 'start'],
                    'padding' => ['type' => 'string', 'enum' => ['none', 'sm', 'base', 'lg'], 'default' => 'none'],
                    'background' => ['type' => 'string', 'enum' => ['transparent', 'surface', 'elevated', 'accent'], 'default' => 'transparent'],
                    'border' => ['type' => 'string', 'enum' => ['none', 'subtle', 'base', 'strong'], 'default' => 'none'],
                    'radius' => ['type' => 'string', 'enum' => ['none', 'base', 'lg', 'xl'], 'default' => 'none'],
                    'shadow' => ['type' => 'string', 'enum' => ['none', 'sm', 'base', 'md', 'lg'], 'default' => 'none'],
                ],
            ],
            variants: [
                'gap' => ['sm' => 'gap-3', 'base' => 'gap-4', 'lg' => 'gap-6'],
                'align' => ['start' => 'justify-start', 'center' => 'justify-center', 'end' => 'justify-end'],
                'padding' => ['none' => 'p-0', 'sm' => 'p-4', 'base' => 'p-6', 'lg' => 'p-8'],
                'background' => ['transparent' => '', 'surface' => 'bg-surface-base', 'elevated' => 'bg-surface-elevated', 'accent' => 'bg-accent-subtle'],
                'border' => ['none' => '', 'subtle' => 'border border-border-subtle', 'base' => 'border border-border-base', 'strong' => 'border border-border-strong'],
                'radius' => ['none' => 'rounded-none', 'base' => 'rounded-base', 'lg' => 'rounded-lg', 'xl' => 'rounded-xl'],
                'shadow' => ['none' => 'shadow-none', 'sm' => 'shadow-sm', 'base' => 'shadow', 'md' => 'shadow-md', 'lg' => 'shadow-lg'],
            ],
            renderer: new FormRenderer(),
            editorConfig: self::localizeEditorConfig([
                'hasSlots' => ['default'],
                'isContainer' => true,
                'tabs' => [
                    ['id' => 'content', 'label' => 'Content', 'controls' => [
                        ['id' => 'formId', 'type' => 'text', 'label' => 'Form ID'],
                        ['id' => 'action', 'type' => 'text', 'label' => 'Action URL'],
                        ['id' => 'method', 'type' => 'select', 'label' => 'Method', 'options' => [['post', 'POST'], ['get', 'GET']]],
                        ['id' => 'name', 'type' => 'text', 'label' => 'Form Name'],
                        ['id' => 'successMessage', 'type' => 'text', 'label' => 'Success Message'],
                        ['id' => 'enctype', 'type' => 'select', 'label' => 'Encoding', 'options' => [['default', 'Default'], ['multipart', 'Multipart']],],
                        ['id' => 'noValidate', 'type' => 'toggle', 'label' => 'Disable Native Validation'],
                    ]],
                    ['id' => 'layout', 'label' => 'Layout', 'controls' => [
                        ['id' => 'gap', 'type' => 'variant', 'label' => 'Spacing', 'variantKey' => 'gap'],
                        ['id' => 'align', 'type' => 'variant', 'label' => 'Alignment', 'variantKey' => 'align'],
                        ['id' => 'padding', 'type' => 'variant', 'label' => 'Padding', 'variantKey' => 'padding'],
                    ]],
                    ['id' => 'style', 'label' => 'Style', 'controls' => [
                        ['id' => 'background', 'type' => 'variant', 'label' => 'Background', 'variantKey' => 'background'],
                        ['id' => 'border', 'type' => 'variant', 'label' => 'Border', 'variantKey' => 'border'],
                        ['id' => 'radius', 'type' => 'variant', 'label' => 'Radius', 'variantKey' => 'radius'],
                        ['id' => 'shadow', 'type' => 'variant', 'label' => 'Shadow', 'variantKey' => 'shadow'],
                    ]],
                ],
            ]),
        );
    }

    private static function makeFormFieldText(): BlockDefinition
    {
        return new BlockDefinition(
            type: 'bky/form-field-text',
            label: self::tr('Form Text Field'),
            category: 'basic',
            description: self::tr('Single-line text, email, phone, URL, or number field.'),
            keywords: ['form', 'input', 'text'],
            icon: 'Txt',
            schema: [
                'type' => 'object',
                'properties' => [
                    'label' => ['type' => 'string', 'default' => 'Your name'],
                    'name' => ['type' => 'string', 'default' => 'name'],
                    'placeholder' => ['type' => 'string', 'default' => 'Enter a value'],
                    'inputType' => ['type' => 'string', 'enum' => ['text', 'email', 'tel', 'url', 'number'], 'default' => 'text'],
                    'autocomplete' => ['type' => 'string', 'default' => ''],
                    'helpText' => ['type' => 'string', 'default' => ''],
                    'required' => ['type' => 'boolean', 'default' => false],
                    'defaultValue' => ['type' => 'string', 'default' => ''],
                    'width' => ['type' => 'string', 'enum' => ['full', 'narrow', 'compact'], 'default' => 'full'],
                ],
            ],
            variants: ['width' => self::formFieldWidthVariants()],
            renderer: new FormFieldRenderer('text'),
            editorConfig: self::localizeEditorConfig([
                'tabs' => [
                    ['id' => 'content', 'label' => 'Content', 'controls' => [
                        ['id' => 'label', 'type' => 'text', 'label' => 'Label'],
                        ['id' => 'name', 'type' => 'text', 'label' => 'Name'],
                        ['id' => 'placeholder', 'type' => 'text', 'label' => 'Placeholder'],
                        ['id' => 'inputType', 'type' => 'select', 'label' => 'Input Type', 'options' => [['text', 'Text'], ['email', 'Email'], ['tel', 'Phone'], ['url', 'URL'], ['number', 'Number']]],
                        ['id' => 'autocomplete', 'type' => 'text', 'label' => 'Autocomplete'],
                        ['id' => 'helpText', 'type' => 'richtext', 'label' => 'Help Text'],
                        ['id' => 'defaultValue', 'type' => 'text', 'label' => 'Default Value'],
                        ['id' => 'required', 'type' => 'toggle', 'label' => 'Required'],
                    ]],
                    ['id' => 'layout', 'label' => 'Layout', 'controls' => [self::formFieldWidthControl()]],
                ],
            ]),
        );
    }

    private static function makeFormFieldTextarea(): BlockDefinition
    {
        return new BlockDefinition(
            type: 'bky/form-field-textarea',
            label: self::tr('Form Textarea'),
            category: 'basic',
            description: self::tr('Multi-line text area for longer messages.'),
            keywords: ['form', 'textarea', 'message'],
            icon: 'Txa',
            schema: [
                'type' => 'object',
                'properties' => [
                    'label' => ['type' => 'string', 'default' => 'Message'],
                    'name' => ['type' => 'string', 'default' => 'message'],
                    'placeholder' => ['type' => 'string', 'default' => 'Write your message'],
                    'rows' => ['type' => 'integer', 'minimum' => 2, 'maximum' => 12, 'default' => 4],
                    'helpText' => ['type' => 'string', 'default' => ''],
                    'required' => ['type' => 'boolean', 'default' => false],
                    'defaultValue' => ['type' => 'string', 'default' => ''],
                    'width' => ['type' => 'string', 'enum' => ['full', 'narrow', 'compact'], 'default' => 'full'],
                ],
            ],
            variants: ['width' => self::formFieldWidthVariants()],
            renderer: new FormFieldRenderer('textarea'),
            editorConfig: self::localizeEditorConfig([
                'tabs' => [
                    ['id' => 'content', 'label' => 'Content', 'controls' => [
                        ['id' => 'label', 'type' => 'text', 'label' => 'Label'],
                        ['id' => 'name', 'type' => 'text', 'label' => 'Name'],
                        ['id' => 'placeholder', 'type' => 'text', 'label' => 'Placeholder'],
                        ['id' => 'rows', 'type' => 'number', 'label' => 'Rows', 'min' => 2, 'max' => 12, 'step' => 1],
                        ['id' => 'helpText', 'type' => 'richtext', 'label' => 'Help Text'],
                        ['id' => 'defaultValue', 'type' => 'richtext', 'label' => 'Default Value'],
                        ['id' => 'required', 'type' => 'toggle', 'label' => 'Required'],
                    ]],
                    ['id' => 'layout', 'label' => 'Layout', 'controls' => [self::formFieldWidthControl()]],
                ],
            ]),
        );
    }

    private static function makeFormFieldSelect(): BlockDefinition
    {
        return new BlockDefinition(
            type: 'bky/form-field-select',
            label: self::tr('Form Select'),
            category: 'basic',
            description: self::tr('Dropdown select driven by line-based options.'),
            keywords: ['form', 'select', 'dropdown'],
            icon: 'Sel',
            schema: [
                'type' => 'object',
                'properties' => [
                    'label' => ['type' => 'string', 'default' => 'Topic'],
                    'name' => ['type' => 'string', 'default' => 'topic'],
                    'options' => ['type' => 'string', 'default' => "Support|support\nSales|sales\nPartnerships|partnerships"],
                    'placeholder' => ['type' => 'string', 'default' => 'Choose an option'],
                    'helpText' => ['type' => 'string', 'default' => ''],
                    'required' => ['type' => 'boolean', 'default' => false],
                    'defaultValue' => ['type' => 'string', 'default' => ''],
                    'width' => ['type' => 'string', 'enum' => ['full', 'narrow', 'compact'], 'default' => 'full'],
                ],
            ],
            variants: ['width' => self::formFieldWidthVariants()],
            renderer: new FormFieldRenderer('select'),
            editorConfig: self::localizeEditorConfig([
                'tabs' => [
                    ['id' => 'content', 'label' => 'Content', 'controls' => [
                        ['id' => 'label', 'type' => 'text', 'label' => 'Label'],
                        ['id' => 'name', 'type' => 'text', 'label' => 'Name'],
                        ['id' => 'options', 'type' => 'richtext', 'label' => 'Options'],
                        ['id' => 'placeholder', 'type' => 'text', 'label' => 'Placeholder'],
                        ['id' => 'helpText', 'type' => 'richtext', 'label' => 'Help Text'],
                        ['id' => 'defaultValue', 'type' => 'text', 'label' => 'Default Value'],
                        ['id' => 'required', 'type' => 'toggle', 'label' => 'Required'],
                    ]],
                    ['id' => 'layout', 'label' => 'Layout', 'controls' => [self::formFieldWidthControl()]],
                ],
            ]),
        );
    }

    private static function makeFormFieldRadio(): BlockDefinition
    {
        return new BlockDefinition(
            type: 'bky/form-field-radio',
            label: self::tr('Form Radio Group'),
            category: 'basic',
            description: self::tr('Radio group driven by line-based options.'),
            keywords: ['form', 'radio', 'choices'],
            icon: 'Rad',
            schema: [
                'type' => 'object',
                'properties' => [
                    'label' => ['type' => 'string', 'default' => 'Preferred contact'],
                    'name' => ['type' => 'string', 'default' => 'contact_preference'],
                    'options' => ['type' => 'string', 'default' => "Email|email\nPhone|phone"],
                    'helpText' => ['type' => 'string', 'default' => ''],
                    'required' => ['type' => 'boolean', 'default' => false],
                    'defaultValue' => ['type' => 'string', 'default' => 'email'],
                    'layout' => ['type' => 'string', 'enum' => ['stacked', 'inline'], 'default' => 'stacked'],
                    'width' => ['type' => 'string', 'enum' => ['full', 'narrow', 'compact'], 'default' => 'full'],
                ],
            ],
            variants: ['width' => self::formFieldWidthVariants(), 'layout' => ['stacked' => 'flex-col', 'inline' => 'flex-row']],
            renderer: new FormFieldRenderer('radio'),
            editorConfig: self::localizeEditorConfig([
                'tabs' => [
                    ['id' => 'content', 'label' => 'Content', 'controls' => [
                        ['id' => 'label', 'type' => 'text', 'label' => 'Label'],
                        ['id' => 'name', 'type' => 'text', 'label' => 'Name'],
                        ['id' => 'options', 'type' => 'richtext', 'label' => 'Options'],
                        ['id' => 'helpText', 'type' => 'richtext', 'label' => 'Help Text'],
                        ['id' => 'defaultValue', 'type' => 'text', 'label' => 'Default Value'],
                        ['id' => 'required', 'type' => 'toggle', 'label' => 'Required'],
                    ]],
                    ['id' => 'layout', 'label' => 'Layout', 'controls' => [
                        ['id' => 'layout', 'type' => 'variant', 'label' => 'Choice Layout', 'variantKey' => 'layout'],
                        self::formFieldWidthControl(),
                    ]],
                ],
            ]),
        );
    }

    private static function makeFormFieldCheckbox(): BlockDefinition
    {
        return new BlockDefinition(
            type: 'bky/form-field-checkbox',
            label: self::tr('Form Checkbox Group'),
            category: 'basic',
            description: self::tr('Checkbox field or checkbox group driven by line-based options.'),
            keywords: ['form', 'checkbox', 'choices'],
            icon: 'Chk',
            schema: [
                'type' => 'object',
                'properties' => [
                    'label' => ['type' => 'string', 'default' => 'Interests'],
                    'name' => ['type' => 'string', 'default' => 'interests'],
                    'options' => ['type' => 'string', 'default' => "Design|design\nDevelopment|development\nStrategy|strategy"],
                    'helpText' => ['type' => 'string', 'default' => ''],
                    'required' => ['type' => 'boolean', 'default' => false],
                    'defaultValue' => ['type' => 'string', 'default' => ''],
                    'layout' => ['type' => 'string', 'enum' => ['stacked', 'inline'], 'default' => 'stacked'],
                    'width' => ['type' => 'string', 'enum' => ['full', 'narrow', 'compact'], 'default' => 'full'],
                ],
            ],
            variants: ['width' => self::formFieldWidthVariants(), 'layout' => ['stacked' => 'flex-col', 'inline' => 'flex-row']],
            renderer: new FormFieldRenderer('checkbox'),
            editorConfig: self::localizeEditorConfig([
                'tabs' => [
                    ['id' => 'content', 'label' => 'Content', 'controls' => [
                        ['id' => 'label', 'type' => 'text', 'label' => 'Label'],
                        ['id' => 'name', 'type' => 'text', 'label' => 'Name'],
                        ['id' => 'options', 'type' => 'richtext', 'label' => 'Options'],
                        ['id' => 'helpText', 'type' => 'richtext', 'label' => 'Help Text'],
                        ['id' => 'defaultValue', 'type' => 'text', 'label' => 'Default Values'],
                        ['id' => 'required', 'type' => 'toggle', 'label' => 'Required'],
                    ]],
                    ['id' => 'layout', 'label' => 'Layout', 'controls' => [
                        ['id' => 'layout', 'type' => 'variant', 'label' => 'Choice Layout', 'variantKey' => 'layout'],
                        self::formFieldWidthControl(),
                    ]],
                ],
            ]),
        );
    }

    private static function makeFormFieldDate(): BlockDefinition
    {
        return new BlockDefinition(
            type: 'bky/form-field-date',
            label: self::tr('Form Date Field'),
            category: 'basic',
            description: self::tr('Date, time, or datetime field.'),
            keywords: ['form', 'date', 'time'],
            icon: 'Dat',
            schema: [
                'type' => 'object',
                'properties' => [
                    'label' => ['type' => 'string', 'default' => 'Preferred date'],
                    'name' => ['type' => 'string', 'default' => 'preferred_date'],
                    'inputType' => ['type' => 'string', 'enum' => ['date', 'time', 'datetime-local'], 'default' => 'date'],
                    'min' => ['type' => 'string', 'default' => ''],
                    'max' => ['type' => 'string', 'default' => ''],
                    'helpText' => ['type' => 'string', 'default' => ''],
                    'required' => ['type' => 'boolean', 'default' => false],
                    'defaultValue' => ['type' => 'string', 'default' => ''],
                    'width' => ['type' => 'string', 'enum' => ['full', 'narrow', 'compact'], 'default' => 'full'],
                ],
            ],
            variants: ['width' => self::formFieldWidthVariants()],
            renderer: new FormFieldRenderer('date'),
            editorConfig: self::localizeEditorConfig([
                'tabs' => [
                    ['id' => 'content', 'label' => 'Content', 'controls' => [
                        ['id' => 'label', 'type' => 'text', 'label' => 'Label'],
                        ['id' => 'name', 'type' => 'text', 'label' => 'Name'],
                        ['id' => 'inputType', 'type' => 'select', 'label' => 'Input Type', 'options' => [['date', 'Date'], ['time', 'Time'], ['datetime-local', 'Datetime']]],
                        ['id' => 'min', 'type' => 'text', 'label' => 'Minimum'],
                        ['id' => 'max', 'type' => 'text', 'label' => 'Maximum'],
                        ['id' => 'helpText', 'type' => 'richtext', 'label' => 'Help Text'],
                        ['id' => 'defaultValue', 'type' => 'text', 'label' => 'Default Value'],
                        ['id' => 'required', 'type' => 'toggle', 'label' => 'Required'],
                    ]],
                    ['id' => 'layout', 'label' => 'Layout', 'controls' => [self::formFieldWidthControl()]],
                ],
            ]),
        );
    }

    private static function makeFormFieldFile(): BlockDefinition
    {
        return new BlockDefinition(
            type: 'bky/form-field-file',
            label: self::tr('Form File Field'),
            category: 'basic',
            description: self::tr('File upload field with optional multiple selection.'),
            keywords: ['form', 'file', 'upload'],
            icon: 'Fil',
            schema: [
                'type' => 'object',
                'properties' => [
                    'label' => ['type' => 'string', 'default' => 'Attachment'],
                    'name' => ['type' => 'string', 'default' => 'attachment'],
                    'accept' => ['type' => 'string', 'default' => ''],
                    'helpText' => ['type' => 'string', 'default' => ''],
                    'multiple' => ['type' => 'boolean', 'default' => false],
                    'required' => ['type' => 'boolean', 'default' => false],
                    'width' => ['type' => 'string', 'enum' => ['full', 'narrow', 'compact'], 'default' => 'full'],
                ],
            ],
            variants: ['width' => self::formFieldWidthVariants()],
            renderer: new FormFieldRenderer('file'),
            editorConfig: self::localizeEditorConfig([
                'tabs' => [
                    ['id' => 'content', 'label' => 'Content', 'controls' => [
                        ['id' => 'label', 'type' => 'text', 'label' => 'Label'],
                        ['id' => 'name', 'type' => 'text', 'label' => 'Name'],
                        ['id' => 'accept', 'type' => 'text', 'label' => 'Accepted Types'],
                        ['id' => 'helpText', 'type' => 'richtext', 'label' => 'Help Text'],
                        ['id' => 'multiple', 'type' => 'toggle', 'label' => 'Allow Multiple'],
                        ['id' => 'required', 'type' => 'toggle', 'label' => 'Required'],
                    ]],
                    ['id' => 'layout', 'label' => 'Layout', 'controls' => [self::formFieldWidthControl()]],
                ],
            ]),
        );
    }

    private static function makeFormFieldHidden(): BlockDefinition
    {
        return new BlockDefinition(
            type: 'bky/form-field-hidden',
            label: self::tr('Form Hidden Field'),
            category: 'basic',
            description: self::tr('Hidden value carried with the form submission.'),
            keywords: ['form', 'hidden'],
            icon: 'Hid',
            schema: [
                'type' => 'object',
                'properties' => [
                    'name' => ['type' => 'string', 'default' => 'source'],
                    'value' => ['type' => 'string', 'default' => 'builder'],
                ],
            ],
            variants: [],
            renderer: new FormFieldRenderer('hidden'),
            editorConfig: self::localizeEditorConfig([
                'tabs' => [
                    ['id' => 'content', 'label' => 'Content', 'controls' => [
                        ['id' => 'name', 'type' => 'text', 'label' => 'Name'],
                        ['id' => 'value', 'type' => 'text', 'label' => 'Value'],
                    ]],
                ],
            ]),
        );
    }

    private static function makeFormFieldHoneypot(): BlockDefinition
    {
        return new BlockDefinition(
            type: 'bky/form-field-honeypot',
            label: self::tr('Form Honeypot'),
            category: 'basic',
            description: self::tr('Simple antibot honeypot field kept off-screen in public rendering.'),
            keywords: ['form', 'honeypot', 'spam'],
            icon: 'Hon',
            schema: [
                'type' => 'object',
                'properties' => [
                    'name' => ['type' => 'string', 'default' => 'website'],
                    'label' => ['type' => 'string', 'default' => 'Leave this field blank'],
                ],
            ],
            variants: [],
            renderer: new FormFieldRenderer('honeypot'),
            editorConfig: self::localizeEditorConfig([
                'tabs' => [
                    ['id' => 'content', 'label' => 'Content', 'controls' => [
                        ['id' => 'name', 'type' => 'text', 'label' => 'Name'],
                        ['id' => 'label', 'type' => 'text', 'label' => 'Label'],
                    ]],
                ],
            ]),
        );
    }

    private static function makeFormSubmit(): BlockDefinition
    {
        return new BlockDefinition(
            type: 'bky/form-submit',
            label: self::tr('Form Submit'),
            category: 'basic',
            description: self::tr('Submit button for Gennaker forms.'),
            keywords: ['form', 'submit', 'button'],
            icon: 'Sub',
            schema: [
                'type' => 'object',
                'properties' => [
                    'label' => ['type' => 'string', 'default' => 'Send message'],
                    'variant' => ['type' => 'string', 'enum' => ['primary', 'secondary', 'ghost', 'outline'], 'default' => 'primary'],
                    'size' => ['type' => 'string', 'enum' => ['sm', 'base', 'lg'], 'default' => 'base'],
                    'align' => ['type' => 'string', 'enum' => ['start', 'center', 'end', 'stretch'], 'default' => 'start'],
                    'fullWidth' => ['type' => 'boolean', 'default' => false],
                    'radius' => ['type' => 'string', 'enum' => ['none', 'base', 'lg', 'full', 'button'], 'default' => 'button'],
                    'shadow' => ['type' => 'string', 'enum' => ['none', 'sm', 'base', 'md', 'lg'], 'default' => 'none'],
                ],
            ],
            variants: [
                'variant' => ['primary' => 'bg-accent-base text-text-on-accent', 'secondary' => 'bg-surface-elevated text-text-base border border-border-base', 'ghost' => 'bg-transparent text-accent-text', 'outline' => 'bg-transparent text-accent-base border border-accent-base'],
                'size' => ['sm' => 'px-3 py-1.5 text-sm', 'base' => 'px-4 py-2 text-base', 'lg' => 'px-6 py-3 text-lg'],
                'align' => ['start' => 'justify-start', 'center' => 'justify-center', 'end' => 'justify-end', 'stretch' => 'justify-start'],
                'radius' => ['none' => 'rounded-none', 'base' => 'rounded-base', 'lg' => 'rounded-lg', 'full' => 'rounded-full', 'button' => 'rounded-button'],
                'shadow' => ['none' => 'shadow-none', 'sm' => 'shadow-sm', 'base' => 'shadow', 'md' => 'shadow-md', 'lg' => 'shadow-lg'],
            ],
            renderer: new FormSubmitRenderer(),
            editorConfig: self::localizeEditorConfig([
                'tabs' => [
                    ['id' => 'content', 'label' => 'Content', 'controls' => [
                        ['id' => 'label', 'type' => 'text', 'label' => 'Label'],
                        ['id' => 'fullWidth', 'type' => 'toggle', 'label' => 'Full Width'],
                    ]],
                    ['id' => 'layout', 'label' => 'Layout', 'controls' => [
                        ['id' => 'align', 'type' => 'variant', 'label' => 'Alignment', 'variantKey' => 'align'],
                        ['id' => 'size', 'type' => 'variant', 'label' => 'Size', 'variantKey' => 'size'],
                    ]],
                    ['id' => 'style', 'label' => 'Style', 'controls' => [
                        ['id' => 'variant', 'type' => 'variant', 'label' => 'Style', 'variantKey' => 'variant'],
                        ['id' => 'radius', 'type' => 'variant', 'label' => 'Radius', 'variantKey' => 'radius'],
                        ['id' => 'shadow', 'type' => 'variant', 'label' => 'Shadow', 'variantKey' => 'shadow'],
                    ]],
                ],
            ]),
        );
    }

    private static function makeModal(): BlockDefinition
    {
        return self::makeOverlayDefinition(
            type: 'bky/modal',
            label: 'Modal',
            description: 'Centered dialog overlay with header, body, footer, and close slots.',
            icon: 'Mod',
            variant: 'modal',
            defaults: ['placement' => 'center', 'size' => 'md', 'backdrop' => 'dim', 'dismissOutside' => true, 'focusTrap' => true, 'title' => 'Modal title', 'description' => 'Add content to the modal body slot.'],
        );
    }

    private static function makeOffcanvas(): BlockDefinition
    {
        return self::makeOverlayDefinition(
            type: 'bky/offcanvas',
            label: 'Offcanvas',
            description: 'Side panel overlay that slides in from any edge.',
            icon: 'Off',
            variant: 'offcanvas',
            defaults: ['placement' => 'right', 'size' => 'md', 'backdrop' => 'dim', 'dismissOutside' => true, 'focusTrap' => true, 'title' => 'Offcanvas panel', 'description' => 'Add navigation, filters, or utility content.'],
        );
    }

    private static function makeDrawer(): BlockDefinition
    {
        return self::makeOverlayDefinition(
            type: 'bky/drawer',
            label: 'Drawer',
            description: 'Drawer-style overlay for compact mobile-first panels.',
            icon: 'Drw',
            variant: 'drawer',
            defaults: ['placement' => 'left', 'size' => 'sm', 'backdrop' => 'dim', 'dismissOutside' => true, 'focusTrap' => true, 'title' => 'Drawer', 'description' => 'Add compact actions, filters, or support content.'],
        );
    }

    private static function makePopover(): BlockDefinition
    {
        return self::makeOverlayDefinition(
            type: 'bky/popover',
            label: 'Popover',
            description: 'Anchored floating panel positioned next to its trigger.',
            icon: 'Pop',
            variant: 'popover',
            defaults: ['placement' => 'bottom', 'size' => 'sm', 'backdrop' => 'none', 'dismissOutside' => true, 'focusTrap' => false, 'title' => 'Popover', 'description' => 'Add short contextual content next to a trigger.'],
        );
    }

    private static function makeTooltip(): BlockDefinition
    {
        return self::makeOverlayDefinition(
            type: 'bky/tooltip',
            label: 'Tooltip',
            description: 'Anchored helper tooltip positioned relative to its trigger.',
            icon: 'Tip',
            variant: 'tooltip',
            defaults: ['placement' => 'top', 'size' => 'sm', 'backdrop' => 'none', 'dismissOutside' => true, 'focusTrap' => false, 'title' => 'Tooltip', 'description' => 'Short helper text for a nearby trigger.'],
        );
    }

    private static function makeDialogConfirm(): BlockDefinition
    {
        return new BlockDefinition(
            type: 'bky/dialog-confirm',
            label: self::tr('Dialog Confirm'),
            category: 'basic',
            description: self::tr('Confirmation dialog preset with default cancel and confirm actions.'),
            keywords: ['dialog', 'confirm', 'overlay'],
            icon: 'Cfm',
            schema: [
                'type' => 'object',
                'properties' => self::overlayProperties(['placement' => 'center', 'size' => 'sm', 'backdrop' => 'dim', 'dismissOutside' => false, 'focusTrap' => true, 'title' => 'Confirm action', 'description' => 'Review the action before confirming.', 'cancelLabel' => 'Cancel', 'confirmLabel' => 'Confirm']) + [
                    'cancelLabel' => ['type' => 'string', 'default' => 'Cancel'],
                    'confirmLabel' => ['type' => 'string', 'default' => 'Confirm'],
                ],
            ],
            variants: self::overlayVariants(),
            renderer: new OverlayRenderer('dialog-confirm'),
            editorConfig: self::localizeEditorConfig([
                'hasSlots' => ['body', 'header', 'footer', 'close'],
                'isContainer' => true,
                'tabs' => [
                    ['id' => 'content', 'label' => 'Content', 'controls' => [
                        ['id' => 'overlayId', 'type' => 'text', 'label' => 'Overlay ID'],
                        ['id' => 'title', 'type' => 'text', 'label' => 'Title'],
                        ['id' => 'description', 'type' => 'richtext', 'label' => 'Description'],
                        ['id' => 'cancelLabel', 'type' => 'text', 'label' => 'Cancel Label'],
                        ['id' => 'confirmLabel', 'type' => 'text', 'label' => 'Confirm Label'],
                        ['id' => 'openOn', 'type' => 'text', 'label' => 'Open On'],
                        ['id' => 'frequency', 'type' => 'select', 'label' => 'Frequency', 'options' => [['always', 'Always'], ['session', 'Once per Session'], ['day', 'Once per Day'], ['week', 'Once per Week'], ['once', 'Only Once']]],
                        ['id' => 'closeOn', 'type' => 'text', 'label' => 'Close On'],
                        ['id' => 'defaultOpen', 'type' => 'toggle', 'label' => 'Open by Default'],
                        ['id' => 'dismissEsc', 'type' => 'toggle', 'label' => 'ESC to Close'],
                        ['id' => 'dismissOutside', 'type' => 'toggle', 'label' => 'Outside Click Closes'],
                        ['id' => 'focusTrap', 'type' => 'toggle', 'label' => 'Trap Focus'],
                    ]],
                    ['id' => 'layout', 'label' => 'Layout', 'controls' => [
                        ['id' => 'placement', 'type' => 'variant', 'label' => 'Placement', 'variantKey' => 'placement'],
                        ['id' => 'size', 'type' => 'variant', 'label' => 'Size', 'variantKey' => 'size'],
                    ]],
                    ['id' => 'style', 'label' => 'Style', 'controls' => [
                        ['id' => 'backdrop', 'type' => 'variant', 'label' => 'Backdrop', 'variantKey' => 'backdrop'],
                    ]],
                ],
            ]),
        );
    }

    private static function makeModalTrigger(): BlockDefinition
    {
        return new BlockDefinition(
            type: 'bky/modal-trigger',
            label: self::tr('Modal Trigger'),
            category: 'basic',
            description: self::tr('Button-like trigger that opens, toggles, or closes an overlay by ID.'),
            keywords: ['overlay', 'trigger', 'button'],
            icon: 'Trg',
            schema: [
                'type' => 'object',
                'properties' => [
                    'label' => ['type' => 'string', 'default' => 'Open overlay'],
                    'targetOverlayId' => ['type' => 'string', 'default' => ''],
                    'action' => ['type' => 'string', 'enum' => ['overlay.open', 'overlay.toggle', 'overlay.close'], 'default' => 'overlay.open'],
                    'variant' => ['type' => 'string', 'enum' => ['primary', 'secondary', 'ghost', 'outline'], 'default' => 'primary'],
                    'size' => ['type' => 'string', 'enum' => ['sm', 'base', 'lg'], 'default' => 'base'],
                    'align' => ['type' => 'string', 'enum' => ['start', 'center', 'end', 'stretch'], 'default' => 'start'],
                    'fullWidth' => ['type' => 'boolean', 'default' => false],
                    'radius' => ['type' => 'string', 'enum' => ['none', 'base', 'lg', 'full', 'button'], 'default' => 'button'],
                    'shadow' => ['type' => 'string', 'enum' => ['none', 'sm', 'base', 'md', 'lg'], 'default' => 'none'],
                ],
            ],
            variants: [
                'variant' => ['primary' => 'bg-accent-base text-text-on-accent', 'secondary' => 'bg-surface-elevated text-text-base border border-border-base', 'ghost' => 'bg-transparent text-accent-text', 'outline' => 'bg-transparent text-accent-base border border-accent-base'],
                'size' => ['sm' => 'px-3 py-1.5 text-sm', 'base' => 'px-4 py-2 text-base', 'lg' => 'px-6 py-3 text-lg'],
                'align' => ['start' => 'justify-start', 'center' => 'justify-center', 'end' => 'justify-end', 'stretch' => 'justify-start'],
                'radius' => ['none' => 'rounded-none', 'base' => 'rounded-base', 'lg' => 'rounded-lg', 'full' => 'rounded-full', 'button' => 'rounded-button'],
                'shadow' => ['none' => 'shadow-none', 'sm' => 'shadow-sm', 'base' => 'shadow', 'md' => 'shadow-md', 'lg' => 'shadow-lg'],
            ],
            renderer: new ModalTriggerRenderer(),
            editorConfig: self::localizeEditorConfig([
                'tabs' => [
                    ['id' => 'content', 'label' => 'Content', 'controls' => [
                        ['id' => 'label', 'type' => 'text', 'label' => 'Label'],
                        ['id' => 'targetOverlayId', 'type' => 'text', 'label' => 'Target Overlay ID'],
                        ['id' => 'action', 'type' => 'select', 'label' => 'Action', 'options' => [['overlay.open', 'Open'], ['overlay.toggle', 'Toggle'], ['overlay.close', 'Close']]],
                        ['id' => 'fullWidth', 'type' => 'toggle', 'label' => 'Full Width'],
                    ]],
                    ['id' => 'layout', 'label' => 'Layout', 'controls' => [
                        ['id' => 'align', 'type' => 'variant', 'label' => 'Alignment', 'variantKey' => 'align'],
                        ['id' => 'size', 'type' => 'variant', 'label' => 'Size', 'variantKey' => 'size'],
                    ]],
                    ['id' => 'style', 'label' => 'Style', 'controls' => [
                        ['id' => 'variant', 'type' => 'variant', 'label' => 'Style', 'variantKey' => 'variant'],
                        ['id' => 'radius', 'type' => 'variant', 'label' => 'Radius', 'variantKey' => 'radius'],
                        ['id' => 'shadow', 'type' => 'variant', 'label' => 'Shadow', 'variantKey' => 'shadow'],
                    ]],
                ],
            ]),
        );
    }

    /** @return array<string, string> */
    private static function formFieldWidthVariants(): array
    {
        return [
            'full' => 'w-full',
            'narrow' => 'w-full max-w-xl',
            'compact' => 'w-full max-w-md',
        ];
    }

    /** @return array<string, string> */
    private static function formFieldWidthControl(): array
    {
        return [
            'id' => 'width',
            'type' => 'variant',
            'label' => 'Width',
            'variantKey' => 'width',
        ];
    }

    /** @param array<string, mixed> $defaults */
    private static function makeOverlayDefinition(string $type, string $label, string $description, string $icon, string $variant, array $defaults): BlockDefinition
    {
        return new BlockDefinition(
            type: $type,
            label: self::tr($label),
            category: 'basic',
            description: self::tr($description),
            keywords: ['overlay', $variant],
            icon: $icon,
            schema: [
                'type' => 'object',
                'properties' => self::overlayProperties($defaults),
            ],
            variants: self::overlayVariants(),
            renderer: new OverlayRenderer($variant),
            editorConfig: self::localizeEditorConfig([
                'hasSlots' => ['body', 'header', 'footer', 'close'],
                'isContainer' => true,
                'tabs' => [
                    ['id' => 'content', 'label' => 'Content', 'controls' => [
                        ['id' => 'overlayId', 'type' => 'text', 'label' => 'Overlay ID'],
                        ['id' => 'title', 'type' => 'text', 'label' => 'Title'],
                        ['id' => 'description', 'type' => 'richtext', 'label' => 'Description'],
                        ['id' => 'openOn', 'type' => 'text', 'label' => 'Open On'],
                        ['id' => 'frequency', 'type' => 'select', 'label' => 'Frequency', 'options' => [['always', 'Always'], ['session', 'Once per Session'], ['day', 'Once per Day'], ['week', 'Once per Week'], ['once', 'Only Once']]],
                        ['id' => 'closeOn', 'type' => 'text', 'label' => 'Close On'],
                        ['id' => 'defaultOpen', 'type' => 'toggle', 'label' => 'Open by Default'],
                        ['id' => 'dismissEsc', 'type' => 'toggle', 'label' => 'ESC to Close'],
                        ['id' => 'dismissOutside', 'type' => 'toggle', 'label' => 'Outside Click Closes'],
                        ['id' => 'focusTrap', 'type' => 'toggle', 'label' => 'Trap Focus'],
                    ]],
                    ['id' => 'layout', 'label' => 'Layout', 'controls' => [
                        ['id' => 'placement', 'type' => 'variant', 'label' => 'Placement', 'variantKey' => 'placement'],
                        ['id' => 'size', 'type' => 'variant', 'label' => 'Size', 'variantKey' => 'size'],
                    ]],
                    ['id' => 'style', 'label' => 'Style', 'controls' => [
                        ['id' => 'backdrop', 'type' => 'variant', 'label' => 'Backdrop', 'variantKey' => 'backdrop'],
                    ]],
                ],
            ]),
        );
    }

    /** @param array<string, mixed> $defaults
     *  @return array<string, mixed>
     */
    private static function overlayProperties(array $defaults): array
    {
        return [
            'overlayId' => ['type' => 'string', 'default' => ''],
            'title' => ['type' => 'string', 'default' => (string) ($defaults['title'] ?? '')],
            'description' => ['type' => 'string', 'default' => (string) ($defaults['description'] ?? '')],
            'placement' => ['type' => 'string', 'enum' => ['center', 'top', 'bottom', 'left', 'right'], 'default' => (string) ($defaults['placement'] ?? 'center')],
            'size' => ['type' => 'string', 'enum' => ['sm', 'md', 'lg', 'xl', 'full'], 'default' => (string) ($defaults['size'] ?? 'md')],
            'backdrop' => ['type' => 'string', 'enum' => ['none', 'dim', 'blur'], 'default' => (string) ($defaults['backdrop'] ?? 'dim')],
            'dismissEsc' => ['type' => 'boolean', 'default' => true],
            'dismissOutside' => ['type' => 'boolean', 'default' => (bool) ($defaults['dismissOutside'] ?? true)],
            'focusTrap' => ['type' => 'boolean', 'default' => (bool) ($defaults['focusTrap'] ?? true)],
            'openOn' => ['type' => 'string', 'default' => (string) ($defaults['openOn'] ?? '')],
            'frequency' => ['type' => 'string', 'enum' => ['always', 'session', 'day', 'week', 'once'], 'default' => (string) ($defaults['frequency'] ?? 'always')],
            'closeOn' => ['type' => 'string', 'default' => (string) ($defaults['closeOn'] ?? '')],
            'defaultOpen' => ['type' => 'boolean', 'default' => (bool) ($defaults['defaultOpen'] ?? false)],
        ];
    }

    /** @return array<string, array<string, string>> */
    private static function overlayVariants(): array
    {
        return [
            'placement' => ['center' => 'center', 'top' => 'top', 'bottom' => 'bottom', 'left' => 'left', 'right' => 'right'],
            'size' => ['sm' => 'sm', 'md' => 'md', 'lg' => 'lg', 'xl' => 'xl', 'full' => 'full'],
            'backdrop' => ['none' => 'none', 'dim' => 'dim', 'blur' => 'blur'],
        ];
    }

    private static function makeQuote(): BlockDefinition
    {
        return new BlockDefinition(
            type: 'bky/quote',
            schema: [
                'type'       => 'object',
                'properties' => [
                    'quote'    => ['type' => 'string', 'default' => 'A short quote or testimonial.'],
                    'citation' => ['type' => 'string', 'default' => ''],
                ],
            ],
            variants: [],
            renderer: new QuoteRenderer(),
            label: self::tr('Quote'),
            category: 'content',
            description: self::tr('A quote or testimonial block.'),
            keywords: ['testimonial', 'blockquote', 'review'],
            icon: 'Q',
        );
    }

    private static function makeVideo(): BlockDefinition
    {
        return new BlockDefinition(
            type: 'bky/video',
            schema: [
                'type'       => 'object',
                'properties' => [
                    'url'   => ['type' => 'string', 'default' => ''],
                    'title' => ['type' => 'string', 'default' => 'Embedded video'],
                ],
            ],
            variants: [],
            renderer: new VideoRenderer(),
            label: self::tr('Video'),
            category: 'media',
            description: self::tr('YouTube, Vimeo, or direct video URL.'),
            keywords: ['embed', 'youtube', 'vimeo', 'media'],
            icon: 'V',
            editorConfig: self::localizeEditorConfig([
                'tabs' => [
                    ['id' => 'content', 'label' => 'Content', 'controls' => [
                        ['id' => 'url',   'type' => 'media', 'label' => 'Video', 'mediaType' => 'video', 'mediaReturn' => 'url'],
                        ['id' => 'title', 'type' => 'text',  'label' => 'Title / Caption'],
                    ]],
                ],
            ]),
        );
    }

    private static function makeHtml(): BlockDefinition
    {
        return new BlockDefinition(
            type: 'bky/html',
            schema: [
                'type'       => 'object',
                'properties' => [
                    'html' => ['type' => 'string', 'default' => '<p>Custom HTML</p>'],
                ],
            ],
            variants: [],
            renderer: new HtmlRenderer(),
            label: self::tr('HTML'),
            category: 'advanced',
            description: self::tr('Sanitized custom HTML markup.'),
            keywords: ['code', 'markup', 'custom'],
            icon: '</>',
        );
    }

    private static function makeWpPostTitle(): BlockDefinition
    {
        return new BlockDefinition(
            type: 'bky/wp-post-title',
            label: self::tr('Post Title'),
            category: 'wordpress',
            description: self::tr('Displays the current post or page title.'),
            keywords: ['title', 'heading', 'post', 'page', 'wordpress'],
            icon: 'WP',
            schema: [
                'type'       => 'object',
                'properties' => [
                    'level' => ['type' => 'integer', 'enum' => [1,2,3,4,5,6], 'default' => 1],
                ],
            ],
            variants: [],
            renderer: new WpPostTitleRenderer(),
            editorConfig: self::localizeEditorConfig([
                'tabs' => [
                    ['id' => 'content', 'label' => 'Content', 'controls' => [
                        ['id' => 'level', 'type' => 'select', 'label' => 'Heading Level',
                         'options' => [[1,'H1'],[2,'H2'],[3,'H3'],[4,'H4'],[5,'H5'],[6,'H6']]],
                    ]],
                ],
            ]),
        );
    }

    private static function makeWpPostContent(): BlockDefinition
    {
        return new BlockDefinition(
            type: 'bky/wp-post-content',
            label: self::tr('Post Content'),
            category: 'wordpress',
            description: self::tr('Renders the main content of the current post.'),
            keywords: ['content', 'body', 'post', 'page', 'wordpress'],
            icon: 'WP',
            schema: ['type' => 'object', 'properties' => []],
            variants: [],
            renderer: new WpPostContentRenderer(),
        );
    }

    private static function makeWpFeaturedImage(): BlockDefinition
    {
        return new BlockDefinition(
            type: 'bky/wp-featured-image',
            label: self::tr('Featured Image'),
            category: 'wordpress',
            description: self::tr('Displays the featured image of the current post.'),
            keywords: ['image', 'thumbnail', 'featured', 'post', 'wordpress'],
            icon: 'WP',
            schema: [
                'type'       => 'object',
                'properties' => [
                    'size'    => ['type' => 'string', 'default' => 'large'],
                    'rounded' => ['type' => 'string', 'enum' => ['none','base','lg','full'], 'default' => 'none'],
                ],
            ],
            variants: [],
            renderer: new WpFeaturedImageRenderer(),
            editorConfig: self::localizeEditorConfig([
                'tabs' => [
                    ['id' => 'style', 'label' => 'Style', 'controls' => [
                        ['id' => 'size',    'type' => 'text',   'label' => 'Image Size'],
                        ['id' => 'rounded', 'type' => 'select', 'label' => 'Rounded Corners',
                         'options' => [['none','None'],['base','Base'],['lg','Large'],['full','Full']]],
                    ]],
                ],
            ]),
        );
    }

    private static function makeWpShortcode(): BlockDefinition
    {
        return new BlockDefinition(
            type: 'bky/wp-shortcode',
            label: self::tr('Shortcode'),
            category: 'wordpress',
            description: self::tr('Executes a WordPress shortcode and renders its output.'),
            keywords: ['shortcode', 'plugin', 'widget', 'wordpress'],
            icon: '[…]',
            schema: [
                'type'       => 'object',
                'properties' => [
                    'shortcode' => ['type' => 'string', 'default' => ''],
                ],
            ],
            variants: [],
            renderer: new WpShortcodeRenderer(),
            editorConfig: self::localizeEditorConfig([
                'tabs' => [
                    ['id' => 'content', 'label' => 'Content', 'controls' => [
                        ['id' => 'shortcode', 'type' => 'text', 'label' => 'Shortcode'],
                    ]],
                ],
            ]),
        );
    }

    private static function makeDataField(): BlockDefinition
    {
        /** @var array<array{0: string, 1: string}> $options */
        $options = [];
        foreach (DataFields::all() as $key => $meta) {
            $options[] = [$key, $meta['label']];
        }

        return new BlockDefinition(
            type: 'bky/data-field',
            label: self::tr('Data Field'),
            category: 'wordpress',
            description: self::tr('Displays one WP data value (title, excerpt, image, price, user bio...) of the current item, page or author.'),
            keywords: ['dynamic', 'data', 'field', 'loop', 'title', 'image', 'price'],
            icon: 'DF',
            schema: [
                'type' => 'object',
                'properties' => [
                    'field' => ['type' => 'string', 'default' => 'title'],
                    'fallback' => ['type' => 'string', 'default' => ''],
                ],
            ],
            variants: [],
            renderer: new DataFieldRenderer(),
            editorConfig: self::localizeEditorConfig([
                'tabs' => [
                    ['id' => 'content', 'label' => 'Content', 'controls' => [
                        ['id' => 'field', 'type' => 'select', 'label' => 'Field', 'options' => $options],
                        ['id' => 'fallback', 'type' => 'text', 'label' => 'Fallback text'],
                    ]],
                ],
            ]),
        );
    }

    private static function makeWpTemplatePart(): BlockDefinition
    {
        return new BlockDefinition(
            type: 'bky/wp-template-part',
            label: self::tr('Template Part'),
            category: 'wordpress',
            description: self::tr('Renders another Gennaker-built page inside the current layout for reusable headers, footers, sidebars, and sections.'),
            keywords: ['template', 'partial', 'header', 'footer', 'sidebar', 'wordpress'],
            icon: 'WP',
            schema: [
                'type'       => 'object',
                'properties' => [
                    'postId' => ['type' => 'integer', 'default' => 0],
                ],
            ],
            variants: [],
            renderer: new WpTemplatePartRenderer(),
            editorConfig: self::localizeEditorConfig([
                'tabs' => [
                    ['id' => 'content', 'label' => 'Content', 'controls' => [
                        ['id' => 'postId', 'type' => 'select', 'label' => 'Blocky Page'],
                    ]],
                ],
            ]),
        );
    }

    private static function makeThemeToggle(): BlockDefinition
    {
        return new BlockDefinition(
            type: 'bky/theme-toggle',
            label: self::tr('Theme Toggle'),
            category: 'wordpress',
            description: self::tr('Button that lets visitors switch between light and dark mode.'),
            keywords: ['theme', 'dark', 'light', 'mode', 'toggle', 'switch'],
            icon: '🎨',
            schema: [
                'type'       => 'object',
                'properties' => [
                    'label'   => ['type' => 'string', 'default' => 'Toggle Theme'],
                    'size'    => ['type' => 'string', 'enum' => ['sm','md','lg'], 'default' => 'md'],
                    'variant' => ['type' => 'string', 'enum' => ['outline','solid','ghost'], 'default' => 'outline'],
                ],
            ],
            variants: [],
            renderer: new ThemeToggleRenderer(),
            editorConfig: self::localizeEditorConfig([
                'tabs' => [
                    ['id' => 'content', 'label' => 'Content', 'controls' => [
                        ['id' => 'label',   'type' => 'text',   'label' => 'Button Label'],
                        ['id' => 'variant', 'type' => 'select', 'label' => 'Style',
                         'options' => [['outline','Outline'],['solid','Solid'],['ghost','Ghost']]],
                        ['id' => 'size',    'type' => 'select', 'label' => 'Size',
                         'options' => [['sm','Small'],['md','Medium'],['lg','Large']]],
                    ]],
                ],
            ]),
        );
    }

    private static function makeWpHook(): BlockDefinition
    {
        return new BlockDefinition(
            type: 'bky/wp-hook',
            label: self::tr('WP Hook'),
            category: 'wordpress',
            description: self::tr('Renders output from a WordPress action or filter hook.'),
            keywords: ['hook', 'action', 'filter', 'do_action', 'apply_filters', 'wordpress'],
            icon: '⚡',
            schema: [
                'type'       => 'object',
                'properties' => [
                    'hookName' => ['type' => 'string', 'default' => ''],
                    'type'     => ['type' => 'string', 'enum' => ['action','filter'], 'default' => 'action'],
                ],
            ],
            variants: [],
            renderer: new WpHookRenderer(),
            editorConfig: self::localizeEditorConfig([
                'tabs' => [
                    ['id' => 'content', 'label' => 'Content', 'controls' => [
                        ['id' => 'hookName', 'type' => 'text',   'label' => 'Hook Name'],
                        ['id' => 'type',     'type' => 'select', 'label' => 'Hook Type',
                         'options' => [['action','do_action'],['filter','apply_filters']]],
                    ]],
                ],
            ]),
        );
    }
}
