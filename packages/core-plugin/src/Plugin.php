<?php
/**
 * @package Blocky\Core
 */

declare(strict_types=1);

namespace Blocky\Core;

defined( 'ABSPATH' ) || exit; // Protect against direct file access.

use Blocky\Core\Blocks\Registry;
use Blocky\Core\Blocks\Renderer\Pipeline;
use Blocky\Core\Compiler\PageCompiler;
use Blocky\Core\Compiler\SiteStylesheet;
use Blocky\Core\Tokens\ThemeEngine;
use Blocky\Core\Assets\AssetOrchestrator;
use Blocky\Core\Rest\RestRegistrar;

/**
 * Main plugin class — singleton bootstrap.
 */
final class Plugin
{
    private const FORM_SUBMISSION_POST_TYPE = 'bky_form_entry';
    private const FORMS_PAGE_SLUG = 'blocky-forms';

    private static ?self $instance = null;
    private bool $renderingBlockyContent = false;

    private Registry           $registry;
    private ThemeEngine        $themeEngine;
    private AssetOrchestrator  $assets;
    private PageCompiler       $pageCompiler;

    private function __construct() {}

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Boot the plugin — registers WP hooks.
     */
    public function boot(): void
    {
        $this->registry    = new Registry();
        $this->themeEngine = ThemeEngine::getInstance();
        $this->assets      = new AssetOrchestrator($this->themeEngine);
        $this->pageCompiler = new PageCompiler(new Pipeline($this->registry, $this->themeEngine));

        $this->registry->registerCoreBlocks();

        \add_action('init',             [$this, 'registerFormSubmissionPostType']);
        \add_action('init',             [$this->registry,   'registerBlockTypes']);
        \add_action('rest_api_init',    [new RestRegistrar($this->registry, $this->themeEngine, $this->pageCompiler), 'register']);
        \Blocky\Core\Support\ApiAuth::register();
        if (\defined('WP_CLI') && \WP_CLI) {
            require_once BLOCKY_CORE_DIR . 'src/Cli/KeyCommand.php';
            \WP_CLI::add_command('blocky key', \Blocky\Core\Cli\KeyCommand::class);
        }
        \add_action('wp_enqueue_scripts', [$this->assets, 'enqueue']);
        \add_action('save_post',        [$this, 'onSavePost'], 10, 2);
        \add_action('admin_menu',       [$this, 'registerAdminMenuTools'], 90);
        \add_action('admin_menu',       [$this, 'registerSetupPage'], 89);
        \add_action('admin_notices',      [$this, 'showSetupNotice']);
        \add_action('admin_post_blocky_clear_page_cache', [$this, 'handleClearPageCache']);
        \add_action('admin_post_blocky_rebuild_page_cache', [$this, 'handleRebuildPageCache']);
        \add_action('admin_post_blocky_rebuild_all_page_cache', [$this, 'handleRebuildAllPageCache']);
        \add_action('admin_post_blocky_allow_engine', [$this, 'handleAllowEngine']);
        \add_action('admin_post_blocky_frontend_form_submit', [$this, 'handleFrontendFormSubmission']);
        \add_action('admin_post_blocky_frontend_login_submit', [$this, 'handleFrontendLoginSubmission']);
        \add_action('admin_post_blocky_frontend_register_submit', [$this, 'handleFrontendRegisterSubmission']);
        \add_action('admin_post_blocky_trash_form_submission', [$this, 'handleTrashFormSubmission']);
        \add_action('admin_post_blocky_restore_form_submission', [$this, 'handleRestoreFormSubmission']);
        \add_action('admin_post_blocky_delete_form_submission', [$this, 'handleDeleteFormSubmission']);
        \add_action('admin_post_nopriv_blocky_frontend_form_submit', [$this, 'handleFrontendFormSubmission']);
        \add_action('admin_post_nopriv_blocky_frontend_login_submit', [$this, 'handleFrontendLoginSubmission']);
        \add_action('admin_post_nopriv_blocky_frontend_register_submit', [$this, 'handleFrontendRegisterSubmission']);
        \add_action('admin_notices',    [$this, 'renderAdminCacheNotice']);
        \add_action('admin_notices',    [$this, 'renderAdminFormsNotice']);
        \add_filter('the_content',      [$this, 'renderBlockyContent'], 9);

        // Site stylesheet pipeline (L3): debounced CLI rebuilds (docs/research/04).
        \add_action(SiteStylesheet::REBUILD_HOOK, static function (): void {
            SiteStylesheet::from_globals()->rebuild();
        });
        \add_action('blocky_compiler_page_cache_warmed', static function (): void {
            SiteStylesheet::schedule_rebuild();
        }, 10, 0);

        // Allow third-party blocks to register before init fires
        \do_action('blocky_register_blocks', $this->registry);

        // Allow themes/plugins to register theme variants — fires after_setup_theme
        // so the active theme's functions.php has already loaded and hooked in.
        \add_action('after_setup_theme', function (): void {
            \do_action('blocky_register_themes', $this->themeEngine);
        }, 20);
    }

    /**
     * Invalidate render cache when a post is saved.
     */
    public function onSavePost(int $postId, \WP_Post $post): void
    {
        if ($post->post_status === 'auto-draft') {
            return;
        }

        $document = \get_post_meta($postId, '_blocky_document', true);
        if (!\is_string($document) || $document === '') {
            $this->pageCompiler->clearCache($postId);
            return;
        }

        try {
            $this->pageCompiler->warmFrontendCache($postId, $document);
        } catch (\Throwable) {
            $this->pageCompiler->clearCache($postId);
        }
    }

    public function renderBlockyContent(string $content): string
    {
        if ($this->renderingBlockyContent || \is_admin() || !\is_singular() || !\is_main_query()) {
            return $content;
        }

        $postId = \get_the_ID();
        if (!\is_int($postId) || $postId <= 0) {
            return $content;
        }

        $document = \get_post_meta($postId, '_blocky_document', true);
        if (!\is_string($document) || $document === '') {
            return $content;
        }

        $hasFormStatus = isset($_GET['blocky_form']) && \sanitize_key((string) $_GET['blocky_form']) !== ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only view filter; no state change.

        $cachedHtml = $this->pageCompiler->cachedHtml($postId);
        if (!$hasFormStatus && $cachedHtml !== '') {
            return $cachedHtml;
        }

        try {
            $this->renderingBlockyContent = true;
            $pipeline = new Pipeline($this->registry, $this->themeEngine);
            return $pipeline->renderDocument($document, false, $postId)->toString();
        } catch (\Throwable) {
            return $content;
        } finally {
            $this->renderingBlockyContent = false;
        }
    }

    public function registerAdminMenuTools(): void
    {
        if (!\is_admin()) {
            return;
        }

        if (!\current_user_can('edit_posts')) {
            return;
        }

        \add_submenu_page(
            'blocky-builder',
            \__('GG Cache', 'blocky'),
            \__('Cache', 'blocky'),
            'edit_posts',
            'blocky-page-cache',
            [$this, 'renderCacheToolsPage']
        );

        \add_submenu_page(
            'blocky-builder',
            \__('GG Forms', 'blocky'),
            \__('Forms', 'blocky'),
            'edit_posts',
            self::FORMS_PAGE_SLUG,
            [$this, 'renderFormsToolsPage']
        );
    }

    /**
     * First-run setup page: explicit consent for the one-time compiler download.
     */
    public function registerSetupPage(): void
    {
        if (!\is_admin()) {
            return;
        }
        if (!\current_user_can('manage_options')) {
            return;
        }
        \add_submenu_page(
            'blocky-builder',
            \__('GG Setup', 'blocky'),
            \__('Setup', 'blocky'),
            'manage_options',
            'blocky-setup',
            [$this, 'renderSetupPage']
        );
    }

    public function handleAllowEngine(): void
    {
        if (!isset($_POST['blocky_allow_nonce']) || !\wp_verify_nonce(\sanitize_text_field(\wp_unslash($_POST['blocky_allow_nonce'])), 'blocky_allow_engine')) {
            \wp_die(\esc_html__('Security check failed.', 'blocky'));
        }
        if (!\current_user_can('manage_options')) {
            \wp_die(\esc_html__('Not permitted.', 'blocky'));
        }
        if (!empty($_POST['blocky_allow_engine'])) {
            \update_option('blocky_allow_engine_download', 'yes');
        }
        \wp_safe_redirect(\add_query_arg('allowed', '1', \admin_url('admin.php?page=blocky-setup')));
        exit;
    }

    public function renderSetupPage(): void
    {
        $allowed = 'yes' === \get_option('blocky_allow_engine_download', '');
        echo '<div class="wrap"><h1>' . \esc_html__('GG Setup', 'blocky') . '</h1>';
        if (isset($_GET['allowed'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only view filter; no state change.
            echo '<div class="notice notice-success"><p>' . \esc_html__('Compiler access allowed. GG will download it once, verify the checksum, and never phone home again.', 'blocky') . '</p></div>';
        }
        echo '<p>' . \esc_html__('GG compiles your stylesheets locally using the official, version-pinned Tailwind CSS command-line compiler (v4.3.3). One download, straight from the official Tailwind GitHub releases, verified against the published SHA-256 checksum, stored outside the web-served uploads directory and reused from cache. Nothing is sent back, and site visitors never contact any third-party server.', 'blocky') . '</p>';
        echo '<form method="post" action="' . \esc_url(\admin_url('admin-post.php')) . '">';
        \wp_nonce_field('blocky_allow_engine', 'blocky_allow_nonce');
        echo '<input type="hidden" name="action" value="blocky_allow_engine" />';
        echo '<p><label><input type="checkbox" name="blocky_allow_engine" value="1"' . ($allowed ? ' checked' : '') . ' /> ' . \esc_html__('I understand and allow GG to download the pinned Tailwind compiler once, from the official GitHub releases, with checksum verification.', 'blocky') . '</label></p>';
        echo '<p><button type="submit" class="button button-primary">' . \esc_html__('Save settings', 'blocky') . '</button></p>';
        echo '</form>';
        echo '<p><em>' . \esc_html__('Until this is confirmed, page saving keeps working; stylesheets use the bundled base vocabulary and interactive blocks keep working, but the static CSS compiler stays inactive.', 'blocky') . '</em></p>';
        echo '</div>';
    }

    public function showSetupNotice(): void
    {
        if ('yes' === \get_option('blocky_allow_engine_download', '')) {
            return;
        }
        if (!\current_user_can('manage_options')) {
            return;
        }
        $url = \esc_url(\admin_url('admin.php?page=blocky-setup'));
        echo '<div class="notice notice-info"><p>' . \esc_html__('One step left to unlock the full GG CSS compiler: review and confirm the one-time download of the pinned official Tailwind tool.', 'blocky') . ' <a href="' . \esc_url($url) . '">' . \esc_html__('Open GG Setup', 'blocky') . '</a></p></div>';
    }

    public function registerFormSubmissionPostType(): void
    {
        \register_post_type(self::FORM_SUBMISSION_POST_TYPE, [
            'labels' => [
                'name' => __('GG Form Submissions', 'blocky'),
                'singular_name' => __('GG Form Submission', 'blocky'),
            ],
            'public' => false,
            'show_ui' => false,
            'show_in_menu' => false,
            'exclude_from_search' => true,
            'publicly_queryable' => false,
            'supports' => ['title'],
            'map_meta_cap' => true,
        ]);
    }

    public function handleClearPageCache(): void
    {
        $postId = $this->validatedActionPostId('blocky_clear_page_cache');
        $this->pageCompiler->clearCache($postId);

        $this->redirectAfterCacheAction('cleared', $postId);
    }

    public function handleRebuildPageCache(): void
    {
        $postId = $this->validatedActionPostId('blocky_rebuild_page_cache');
        $document = \get_post_meta($postId, '_blocky_document', true);

        if (!\is_string($document) || $document === '') {
            $this->redirectAfterCacheAction('missing-document', $postId);
        }

        try {
            $this->pageCompiler->warmFrontendCache($postId, $document);
            $this->redirectAfterCacheAction('rebuilt', $postId);
        } catch (\Throwable) {
            $this->redirectAfterCacheAction('failed', $postId);
        }
    }

    public function handleRebuildAllPageCache(): void
    {
        $this->validateGlobalAdminAction('blocky_rebuild_all_page_cache');

        $rebuilt = 0;
        $failed = 0;

        foreach ($this->allBlockyPosts() as $post) {
            if (!\current_user_can('edit_post', $post->ID)) {
                continue;
            }

            $document = \get_post_meta($post->ID, '_blocky_document', true);
            if (!\is_string($document) || $document === '') {
                continue;
            }

            try {
                $this->pageCompiler->warmFrontendCache($post->ID, $document);
                $rebuilt++;
            } catch (\Throwable) {
                $failed++;
            }
        }

        $redirectUrl = \add_query_arg([
            'page' => 'blocky-page-cache',
            'blocky_cache' => 'rebuilt-all',
            'rebuilt_count' => $rebuilt,
            'failed_count' => $failed,
        ], \admin_url('admin.php'));

        \wp_safe_redirect($redirectUrl);
        exit;
    }

    public function handleFrontendFormSubmission(): void
    {
        $formId = isset($_POST['_blocky_form_id']) ? \sanitize_text_field((string) \wp_unslash($_POST['_blocky_form_id'])) : '';
        $postId = isset($_POST['_blocky_form_post_id']) ? (int) $_POST['_blocky_form_post_id'] : 0;
        $redirectUrl = $this->formRedirectUrl($postId);

        if (!isset($_POST['_blocky_form_submission']) || $formId === '' || $postId <= 0) {
            $this->redirectAfterFormAction('failed', $redirectUrl, $formId);
        }

        $nonce = isset($_POST['_blocky_form_nonce']) ? sanitize_text_field((string) \wp_unslash($_POST['_blocky_form_nonce'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified via wp_verify_nonce() below.
        if ($nonce === '' || !\wp_verify_nonce($nonce, 'blocky_frontend_form_' . $formId)) {
            $this->redirectAfterFormAction('invalid', $redirectUrl, $formId);
        }

        $payload = $this->sanitizeSubmissionPayload($_POST);
        if ($payload === []) {
            $this->redirectAfterFormAction('failed', $redirectUrl, $formId);
        }

        $page = \get_post($postId);
        $pageTitle = $page instanceof \WP_Post && $page->post_title !== '' ? $page->post_title : __('(no title)', 'blocky');
        $submissionTitle = sprintf(
            /* translators: 1: form id, 2: page title, 3: submission date */
            __('Submission %1$s on %2$s at %3$s', 'blocky'),
            $formId,
            $pageTitle,
            \wp_date('Y-m-d H:i:s')
        );

        $submissionId = \wp_insert_post([
            'post_type' => self::FORM_SUBMISSION_POST_TYPE,
            'post_status' => 'private',
            'post_title' => $submissionTitle,
        ], true);

        if ($submissionId instanceof \WP_Error) {
            $this->redirectAfterFormAction('failed', $redirectUrl, $formId);
        }

        \update_post_meta($submissionId, '_blocky_form_id', $formId);
        \update_post_meta($submissionId, '_blocky_form_post_id', $postId);
        \update_post_meta($submissionId, '_blocky_form_payload', $payload);
        \update_post_meta($submissionId, '_blocky_form_submitted_at', \current_time('mysql', true));
        \update_post_meta($submissionId, '_blocky_form_source_url', $redirectUrl);

        $this->redirectAfterFormAction('submitted', $redirectUrl, $formId);
    }

    public function handleFrontendLoginSubmission(): void
    {
        $formId = isset($_POST['_blocky_form_id']) ? \sanitize_text_field((string) \wp_unslash($_POST['_blocky_form_id'])) : '';
        $postId = isset($_POST['_blocky_form_post_id']) ? (int) $_POST['_blocky_form_post_id'] : 0;
        $redirectUrl = $this->formRedirectUrl($postId);

        if (!isset($_POST['_blocky_login_submission']) || $formId === '') {
            $this->redirectAfterLoginAction('failed', $redirectUrl, $formId);
        }

        $nonce = isset($_POST['_blocky_login_nonce']) ? sanitize_text_field((string) \wp_unslash($_POST['_blocky_login_nonce'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified via wp_verify_nonce() below.
        if ($nonce === '' || !\wp_verify_nonce($nonce, 'blocky_frontend_login_' . $formId)) {
            $this->redirectAfterLoginAction('failed', $redirectUrl, $formId);
        }

        $username = isset($_POST['log']) ? \sanitize_text_field((string) \wp_unslash($_POST['log'])) : '';
        $password = isset($_POST['pwd']) ? (string) \wp_unslash($_POST['pwd']) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- passwords pass raw to wp_authenticate()/wp_create_user(); sanitized at validation.
        if ($username === '' || $password === '') {
            $this->redirectAfterLoginAction('invalid', $redirectUrl, $formId);
        }

        $signOn = \wp_signon([
            'user_login' => $username,
            'user_password' => $password,
            'remember' => isset($_POST['rememberme']) && (string) $_POST['rememberme'] !== '',
        ], \is_ssl());

        if ($signOn instanceof \WP_Error) {
            $this->redirectAfterLoginAction('invalid', $redirectUrl, $formId);
        }

        $successRedirect = isset($_POST['_blocky_login_redirect']) ? \esc_url_raw((string) \wp_unslash($_POST['_blocky_login_redirect'])) : '';
        if ($successRedirect !== '') {
            \wp_safe_redirect($successRedirect);
            exit;
        }

        $this->redirectAfterLoginAction('signed-in', $redirectUrl, $formId);
    }

    public function handleFrontendRegisterSubmission(): void
    {
        $formId = isset($_POST['_blocky_form_id']) ? \sanitize_text_field((string) \wp_unslash($_POST['_blocky_form_id'])) : '';
        $postId = isset($_POST['_blocky_form_post_id']) ? (int) $_POST['_blocky_form_post_id'] : 0;
        $redirectUrl = $this->formRedirectUrl($postId);

        if (!isset($_POST['_blocky_register_submission']) || $formId === '') {
            $this->redirectAfterRegisterAction('failed', $redirectUrl, $formId);
        }

        if (!\get_option('users_can_register')) {
            $this->redirectAfterRegisterAction('disabled', $redirectUrl, $formId);
        }

        $nonce = isset($_POST['_blocky_register_nonce']) ? sanitize_text_field((string) \wp_unslash($_POST['_blocky_register_nonce'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified via wp_verify_nonce() below.
        if ($nonce === '' || !\wp_verify_nonce($nonce, 'blocky_frontend_register_' . $formId)) {
            $this->redirectAfterRegisterAction('failed', $redirectUrl, $formId);
        }

        $username = isset($_POST['user_login']) ? \sanitize_user((string) \wp_unslash($_POST['user_login']), true) : '';
        $email = isset($_POST['user_email']) ? \sanitize_email((string) \wp_unslash($_POST['user_email'])) : '';
        $password = isset($_POST['user_password']) ? (string) \wp_unslash($_POST['user_password']) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- passwords pass raw to wp_authenticate()/wp_create_user(); sanitized at validation.
        if ($username === '' || $email === '' || $password === '' || !\is_email($email)) {
            $this->redirectAfterRegisterAction('invalid', $redirectUrl, $formId);
        }

        if (\username_exists($username) || \email_exists($email)) {
            $this->redirectAfterRegisterAction('exists', $redirectUrl, $formId);
        }

        $userId = \wp_create_user($username, $password, $email);
        if ($userId instanceof \WP_Error) {
            $this->redirectAfterRegisterAction('failed', $redirectUrl, $formId);
        }

        if (isset($_POST['_blocky_register_autologin']) && (string) $_POST['_blocky_register_autologin'] === '1') {
            \wp_set_current_user($userId);
            \wp_set_auth_cookie($userId, true);
        }

        $successRedirect = isset($_POST['_blocky_register_redirect']) ? \esc_url_raw((string) \wp_unslash($_POST['_blocky_register_redirect'])) : '';
        if ($successRedirect !== '') {
            \wp_safe_redirect($successRedirect);
            exit;
        }

        $this->redirectAfterRegisterAction('registered', $redirectUrl, $formId);
    }

    public function handleTrashFormSubmission(): void
    {
        $submission = $this->validatedFormSubmission('blocky_trash_form_submission');

        if (!\wp_trash_post($submission->ID)) {
            $this->redirectAfterFormsAction('failed', 'submissions');
        }

        $this->redirectAfterFormsAction('trashed', 'submissions');
    }

    public function handleRestoreFormSubmission(): void
    {
        $submission = $this->validatedFormSubmission('blocky_restore_form_submission');

        if (!\wp_untrash_post($submission->ID)) {
            $this->redirectAfterFormsAction('failed', 'trash');
        }

        $restoredId = \wp_update_post([
            'ID' => $submission->ID,
            'post_status' => 'private',
        ], true);

        if ($restoredId instanceof \WP_Error) {
            $this->redirectAfterFormsAction('failed', 'trash');
        }

        $this->redirectAfterFormsAction('restored', 'submissions', $submission->ID);
    }

    public function handleDeleteFormSubmission(): void
    {
        $submission = $this->validatedFormSubmission('blocky_delete_form_submission');

        if (!\wp_delete_post($submission->ID, true)) {
            $this->redirectAfterFormsAction('failed', 'trash');
        }

        $this->redirectAfterFormsAction('deleted', 'trash');
    }

    public function renderAdminCacheNotice(): void
    {
        if (!\is_admin() || !isset($_GET['blocky_cache'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only view filter; no state change.
            return;
        }

        $result = \sanitize_key((string) $_GET['blocky_cache']); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only view filter; no state change.
        $message = match ($result) {
            'cleared'          => __('GG page cache cleared.', 'blocky'),
            'rebuilt'          => __('GG page cache rebuilt.', 'blocky'),
            'rebuilt-all'      => sprintf(
                /* translators: 1: rebuilt page count, 2: failed page count */
                __('GG cache rebuild complete. %1$d pages rebuilt, %2$d failures.', 'blocky'),
                isset($_GET['rebuilt_count']) ? (int) $_GET['rebuilt_count'] : 0, // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only view filter; no state change.
                isset($_GET['failed_count']) ? (int) $_GET['failed_count'] : 0 // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only view filter; no state change.
            ),
            'missing-document' => __('No GG document was found for this page.', 'blocky'),
            'failed'           => __('GG cache rebuild failed.', 'blocky'),
            default            => '',
        };

        if ($message === '') {
            return;
        }

        $className = \in_array($result, ['cleared', 'rebuilt', 'rebuilt-all'], true)
            ? 'notice notice-success is-dismissible'
            : 'notice notice-error is-dismissible';

        echo '<div class="' . \esc_attr($className) . '"><p>' . \esc_html($message) . '</p></div>';
    }

    public function renderAdminFormsNotice(): void
    {
        if (!\is_admin() || !isset($_GET['page']) || $_GET['page'] !== self::FORMS_PAGE_SLUG || !isset($_GET['blocky_forms'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only view filter; no state change.
            return;
        }

        $result = \sanitize_key((string) $_GET['blocky_forms']); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only view filter; no state change.
        $message = match ($result) {
            'trashed' => __('Submission moved to trash.', 'blocky'),
            'restored' => __('Submission restored.', 'blocky'),
            'deleted' => __('Submission deleted permanently.', 'blocky'),
            'failed' => __('The submission action could not be completed.', 'blocky'),
            default => '',
        };

        if ($message === '') {
            return;
        }

        $className = \in_array($result, ['trashed', 'restored', 'deleted'], true)
            ? 'notice notice-success is-dismissible'
            : 'notice notice-error is-dismissible';

        echo '<div class="' . \esc_attr($className) . '"><p>' . \esc_html($message) . '</p></div>';
    }

    public function renderCacheToolsPage(): void
    {
        if (!\current_user_can('edit_posts')) {
            \wp_die('Forbidden', 403);
        }

        $postId = $this->currentToolbarPostId();
        $recentPosts = $this->recentBlockyPosts();

        echo '<div class="wrap">';
        echo '<h1>' . \esc_html__('GG Cache', 'blocky') . '</h1>';
        echo '<p>' . \esc_html__('Rebuild or clear the compiled cache for GG pages.', 'blocky') . '</p>';

        $this->renderGlobalCacheToolsCard();

        if ($postId > 0) {
            $this->renderCacheToolsCard($postId);
        } else {
            echo '<div class="notice notice-info"><p>' . \esc_html__('Open a GG page in the Builder or choose one of the recent pages below.', 'blocky') . '</p></div>';
        }

        if ($recentPosts !== []) {
            echo '<h2>' . \esc_html__('Recent GG Pages', 'blocky') . '</h2>';
            echo '<table class="widefat striped"><thead><tr><th>' . \esc_html__('Title', 'blocky') . '</th><th>' . \esc_html__('Type', 'blocky') . '</th><th>' . \esc_html__('Actions', 'blocky') . '</th></tr></thead><tbody>';

            foreach ($recentPosts as $post) {
                $title = $post->post_title !== '' ? $post->post_title : __('(no title)', 'blocky');
                $cacheUrl = \esc_url(\admin_url('admin.php?page=blocky-page-cache&post_id=' . $post->ID));
                $builderUrl = \esc_url(\admin_url('admin.php?page=blocky-builder&post_id=' . $post->ID));

                echo '<tr>';
                echo '<td><strong>' . \esc_html($title) . '</strong></td>';
                echo '<td>' . \esc_html((string) $post->post_type) . '</td>';
                echo '<td>';
                echo '<a class="button button-secondary" href="' . \esc_url($cacheUrl) . '">' . \esc_html__('Open Cache', 'blocky') . '</a> ';
                echo '<a class="button" href="' . \esc_url($builderUrl) . '">' . \esc_html__('Open Builder', 'blocky') . '</a>';
                echo '</td>';
                echo '</tr>';
            }

            echo '</tbody></table>';
        }

        echo '</div>';
    }

    public function renderFormsToolsPage(): void
    {
        if (!\current_user_can('edit_posts')) {
            \wp_die('Forbidden', 403);
        }

        $selectedTab = $this->currentFormsTab();
        $forms = $this->discoveredBlockyForms();
        $submissionCounts = $this->submissionCountsByFormId();
        $lastSubmissionDates = $this->lastSubmissionDateByFormId();
        $activeSubmissions = $this->recentFormSubmissions();
        $trashedSubmissions = $this->trashedFormSubmissions();
        $listedSubmissions = $selectedTab === 'trash' ? $trashedSubmissions : $activeSubmissions;
        $selectedSubmission = $this->selectedFormSubmission($selectedTab);

        echo '<div class="wrap">';
        echo '<h1>' . \esc_html__('GG Forms', 'blocky') . '</h1>';
        echo '<p>' . \esc_html__('Built-in POST forms are stored as private GG submissions and listed here for review.', 'blocky') . '</p>';

        $this->renderFormsTabs(count($activeSubmissions), count($trashedSubmissions), $selectedTab);

        if ($selectedSubmission instanceof \WP_Post) {
            $this->renderSubmissionDetailCard($selectedSubmission, $selectedTab);
        }

        if ($selectedTab !== 'trash' && $forms === []) {
            echo '<div class="notice notice-info"><p>' . \esc_html__('No GG forms were found yet. Add a Form block to a GG page to start collecting submissions.', 'blocky') . '</p></div>';
        } elseif ($selectedTab !== 'trash') {
            echo '<h2>' . \esc_html__('Discovered Forms', 'blocky') . '</h2>';
            echo '<table class="widefat striped"><thead><tr>';
            echo '<th>' . \esc_html__('Form', 'blocky') . '</th>';
            echo '<th>' . \esc_html__('Page', 'blocky') . '</th>';
            echo '<th>' . \esc_html__('Mode', 'blocky') . '</th>';
            echo '<th>' . \esc_html__('Submissions', 'blocky') . '</th>';
            echo '<th>' . \esc_html__('Last Submission', 'blocky') . '</th>';
            echo '<th>' . \esc_html__('Actions', 'blocky') . '</th>';
            echo '</tr></thead><tbody>';

            foreach ($forms as $form) {
                $builderUrl = \esc_url(\admin_url('admin.php?page=blocky-builder&post_id=' . $form['post_id']));
                $count = $submissionCounts[$form['form_id']] ?? 0;
                $lastSubmitted = $lastSubmissionDates[$form['form_id']] ?? '—';

                echo '<tr>';
                echo '<td><strong>' . \esc_html($form['label']) . '</strong><br><code>' . \esc_html($form['form_id']) . '</code></td>';
                echo '<td><strong>' . \esc_html($form['post_title']) . '</strong><br><span style="color:#646970;">#' . \esc_html((string) $form['post_id']) . '</span></td>';
                echo '<td>' . \esc_html($form['mode']) . '</td>';
                echo '<td>' . \esc_html((string) $count) . '</td>';
                echo '<td>' . \esc_html($lastSubmitted) . '</td>';
                echo '<td>';
                echo '<a class="button" href="' . \esc_url($builderUrl) . '">' . \esc_html__('Open Builder', 'blocky') . '</a>';
                echo '</td>';
                echo '</tr>';
            }

            echo '</tbody></table>';
        }

        echo '<h2 style="margin-top:2rem;">' . \esc_html($selectedTab === 'trash' ? __('Trashed Submissions', 'blocky') : __('Recent Submissions', 'blocky')) . '</h2>';

        if ($listedSubmissions === []) {
            echo '<div class="notice notice-info"><p>' . \esc_html($selectedTab === 'trash'
                ? __('The forms trash is empty.', 'blocky')
                : __('No GG form submissions have been stored yet.', 'blocky')) . '</p></div>';
        } else {
            echo '<table class="widefat striped"><thead><tr>';
            echo '<th>' . \esc_html__('Submitted', 'blocky') . '</th>';
            echo '<th>' . \esc_html__('Form', 'blocky') . '</th>';
            echo '<th>' . \esc_html__('Page', 'blocky') . '</th>';
            echo '<th>' . \esc_html__('Preview', 'blocky') . '</th>';
            echo '<th>' . \esc_html__('Actions', 'blocky') . '</th>';
            echo '</tr></thead><tbody>';

            foreach ($listedSubmissions as $submission) {
                $submittedAt = (string) \get_post_meta($submission->ID, '_blocky_form_submitted_at', true);
                $formId = (string) \get_post_meta($submission->ID, '_blocky_form_id', true);
                $sourcePostId = (int) \get_post_meta($submission->ID, '_blocky_form_post_id', true);
                $payload = \get_post_meta($submission->ID, '_blocky_form_payload', true);
                $sourcePost = $sourcePostId > 0 ? \get_post($sourcePostId) : null;
                $sourceTitle = $sourcePost instanceof \WP_Post && $sourcePost->post_title !== '' ? $sourcePost->post_title : __('(no title)', 'blocky');
                $detailUrl = \esc_url($this->formSubmissionAdminUrl($submission->ID, $selectedTab));

                echo '<tr>';
                echo '<td>' . \esc_html($submittedAt !== '' ? $submittedAt : (string) $submission->post_date_gmt) . '</td>';
                echo '<td><code>' . \esc_html($formId) . '</code></td>';
                echo '<td>' . \esc_html($sourceTitle) . '</td>';
                echo '<td>' . \wp_kses_post($this->submissionPayloadPreview($payload)) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- helper returns pre-escaped markup.
                echo '<td>';
                echo '<a class="button button-secondary" href="' . \esc_url($detailUrl) . '">' . \esc_html__('View Submission', 'blocky') . '</a> ';
                echo $selectedTab === 'trash'
                    ? \wp_kses_post($this->restoreSubmissionButton($submission->ID))
                    : \wp_kses_post($this->trashSubmissionButton($submission->ID));
                if ($selectedTab === 'trash') {
                    echo ' ' . \wp_kses_post($this->deleteSubmissionButton($submission->ID)); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- helper returns pre-escaped markup.
                }
                echo '</td>';
                echo '</tr>';
            }

            echo '</tbody></table>';
        }

        echo '</div>';
    }

    /**
     * Get the block registry.
     */
    public function getRegistry(): Registry
    {
        return $this->registry;
    }

    /**
     * Get the theme engine.
     */
    public function getThemeEngine(): ThemeEngine
    {
        return $this->themeEngine;
    }

    /**
     * Plugin activation hook.
     */
    public static function onActivate(): void
    {
        \flush_rewrite_rules();
        \Blocky\Core\Support\ApiKeyStore::install();
        \Blocky\Core\Support\ApiAudit::install();
        SiteStylesheet::schedule_rebuild();
    }

    /**
     * Plugin deactivation hook.
     */
    public static function onDeactivate(): void
    {
        \flush_rewrite_rules();
    }

    private function currentToolbarPostId(): int
    {
        if (\is_admin()) {
            $builderPostId = isset($_GET['post_id']) ? (int) $_GET['post_id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only view filter; no state change.
            if ($builderPostId > 0) {
                return $builderPostId;
            }

            $editorPostId = isset($_GET['post']) ? (int) $_GET['post'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only view filter; no state change.
            if ($editorPostId > 0) {
                return $editorPostId;
            }
        }

        $queriedPostId = \get_queried_object_id();
        return \is_int($queriedPostId) ? $queriedPostId : 0;
    }

    private function renderGlobalCacheToolsCard(): void
    {
        $totalPages = count($this->allBlockyPosts());
        $rebuildAllUrl = \esc_url($this->globalActionUrl('blocky_rebuild_all_page_cache', 'blocky_rebuild_all_page_cache'));

        echo '<div class="card" style="max-width: 760px; margin-bottom: 16px;">';
        echo '<h2 style="margin-top:0;">' . \esc_html__('Global Cache', 'blocky') . '</h2>';
        echo '<p>' . \esc_html(sprintf(
            /* translators: %d: number of Blocky pages */
            __('Rebuild the compiled frontend cache for all %d GG pages.', 'blocky'),
            $totalPages
        )) . '</p>';
        echo '<p><a class="button button-primary" href="' . \esc_url($rebuildAllUrl) . '">' . \esc_html__('Rebuild All GG Cache', 'blocky') . '</a></p>';
        echo '</div>';
    }

    private function renderCacheToolsCard(int $postId): void
    {
        if (!\current_user_can('edit_post', $postId)) {
            echo '<div class="notice notice-error"><p>' . \esc_html__('You do not have permission to manage this page.', 'blocky') . '</p></div>';
            return;
        }

        $document = \get_post_meta($postId, '_blocky_document', true);
        if (!\is_string($document) || $document === '') {
            echo '<div class="notice notice-warning"><p>' . \esc_html__('This page does not contain a GG document.', 'blocky') . '</p></div>';
            return;
        }

        $post = \get_post($postId);
        $title = $post instanceof \WP_Post && $post->post_title !== '' ? $post->post_title : __('(no title)', 'blocky');
        $rebuildUrl = \esc_url($this->cacheActionUrl('blocky_rebuild_page_cache', 'blocky_rebuild_page_cache', $postId));
        $clearUrl = \esc_url($this->cacheActionUrl('blocky_clear_page_cache', 'blocky_clear_page_cache', $postId));
        $builderUrl = \esc_url(\admin_url('admin.php?page=blocky-builder&post_id=' . $postId));

        echo '<div class="card" style="max-width: 760px;">';
        echo '<h2 style="margin-top:0;">' . \esc_html__('Current Page', 'blocky') . '</h2>';
        echo '<p><strong>' . \esc_html($title) . '</strong> <span style="color:#646970;">#' . \esc_html((string) $postId) . '</span></p>';
        echo '<p>';
        echo '<a class="button button-primary" href="' . \esc_url($rebuildUrl) . '">' . \esc_html__('Rebuild Page Cache', 'blocky') . '</a> ';
        echo '<a class="button button-secondary" href="' . \esc_url($clearUrl) . '">' . \esc_html__('Clear Page Cache', 'blocky') . '</a> ';
        echo '<a class="button" href="' . \esc_url($builderUrl) . '">' . \esc_html__('Open Builder', 'blocky') . '</a>';
        echo '</p>';
        echo '</div>';
    }

    /**
     * @return \WP_Post[]
     */
    private function recentBlockyPosts(): array
    {
        return $this->queryBlockyPosts(10);
    }

    /**
     * @return \WP_Post[]
     */
    private function allBlockyPosts(): array
    {
        return $this->queryBlockyPosts(-1);
    }

    /**
     * @return \WP_Post[]
     */
    private function queryBlockyPosts(int $postsPerPage): array
    {
        $query = new \WP_Query([
            'post_type'              => \get_post_types(['public' => true], 'names'),
            'post_status'            => ['publish', 'draft', 'pending', 'future', 'private'],
            'posts_per_page'         => $postsPerPage,
            'orderby'                => 'modified',
            'order'                  => 'DESC',
            'meta_query'             => [[ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- bounded admin listings over meta index.
                'key'     => '_blocky_document',
                'compare' => 'EXISTS',
            ]],
            'no_found_rows'          => true,
            'ignore_sticky_posts'    => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        ]);

        return array_values(array_filter(
            $query->posts,
            static fn($post): bool => $post instanceof \WP_Post && \current_user_can('edit_post', $post->ID)
        ));
    }

    private function cacheActionUrl(string $action, string $nonceAction, int $postId): string
    {
        return \wp_nonce_url(
            \add_query_arg([
                'action'  => $action,
                'post_id' => $postId,
            ], \admin_url('admin-post.php')),
            $nonceAction
        );
    }

    private function globalActionUrl(string $action, string $nonceAction): string
    {
        return \wp_nonce_url(
            \add_query_arg([
                'action' => $action,
            ], \admin_url('admin-post.php')),
            $nonceAction
        );
    }

    private function validatedActionPostId(string $nonceAction): int
    {
        if (!\current_user_can('edit_posts')) {
            \wp_die('Forbidden', 403);
        }

        \check_admin_referer($nonceAction);

        $postId = isset($_GET['post_id']) ? (int) $_GET['post_id'] : 0;
        if ($postId <= 0 || !\current_user_can('edit_post', $postId)) {
            \wp_die('Forbidden', 403);
        }

        return $postId;
    }

    private function validateGlobalAdminAction(string $nonceAction): void
    {
        if (!\current_user_can('edit_posts')) {
            \wp_die('Forbidden', 403);
        }

        \check_admin_referer($nonceAction);
    }

    private function redirectAfterCacheAction(string $result, int $postId): never
    {
        $redirectUrl = \wp_get_referer();

        if (!\is_string($redirectUrl) || $redirectUrl === '') {
            $redirectUrl = \admin_url('admin.php?page=blocky-builder&post_id=' . $postId);
        }

        \wp_safe_redirect(\add_query_arg([
            'blocky_cache' => $result,
            'blocky_post'  => $postId,
        ], $redirectUrl));
        exit;
    }

    private function formRedirectUrl(int $postId): string
    {
        if ($postId > 0) {
            $permalink = \get_permalink($postId);
            if (\is_string($permalink) && $permalink !== '') {
                return $permalink;
            }
        }

        $referer = \wp_get_referer();
        if (\is_string($referer) && $referer !== '') {
            return $referer;
        }

        return \home_url('/');
    }

    /**
     * @param array<string, mixed> $source
     * @return array<string, mixed>
     */
    private function sanitizeSubmissionPayload(array $source): array
    {
        $payload = [];

        foreach ($source as $key => $value) {
            if (!\is_string($key) || $key === '' || str_starts_with($key, '_blocky_') || $key === 'action' || $key === '_wp_http_referer') {
                continue;
            }

            $sanitizedKey = \sanitize_key($key);
            if ($sanitizedKey === '') {
                continue;
            }

            $sanitizedValue = $this->sanitizeSubmissionValue($value);
            if ($sanitizedValue === null || $sanitizedValue === [] || $sanitizedValue === '') {
                continue;
            }

            $payload[$sanitizedKey] = $sanitizedValue;
        }

        return $payload;
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    private function sanitizeSubmissionValue(mixed $value): mixed
    {
        if (\is_array($value)) {
            $sanitized = [];
            foreach ($value as $entry) {
                $clean = $this->sanitizeSubmissionValue($entry);
                if ($clean !== null && $clean !== '' && $clean !== []) {
                    $sanitized[] = $clean;
                }
            }

            return $sanitized;
        }

        if (!\is_scalar($value)) {
            return null;
        }

        return \sanitize_textarea_field((string) \wp_unslash($value));
    }

    private function redirectAfterFormAction(string $result, string $redirectUrl, string $formId): never
    {
        \wp_safe_redirect(\add_query_arg([
            'blocky_form' => $result,
            'blocky_form_id' => $formId,
        ], $redirectUrl));
        exit;
    }

    private function redirectAfterLoginAction(string $result, string $redirectUrl, string $formId): never
    {
        \wp_safe_redirect(\add_query_arg([
            'blocky_login' => $result,
            'blocky_form_id' => $formId,
        ], $redirectUrl));
        exit;
    }

    private function redirectAfterRegisterAction(string $result, string $redirectUrl, string $formId): never
    {
        \wp_safe_redirect(\add_query_arg([
            'blocky_register' => $result,
            'blocky_form_id' => $formId,
        ], $redirectUrl));
        exit;
    }

    /**
     * @return array<int, array{form_id: string, label: string, mode: string, post_id: int, post_title: string, permalink: string}>
     */
    private function discoveredBlockyForms(): array
    {
        $forms = [];

        foreach ($this->allBlockyPosts() as $post) {
            $document = \get_post_meta($post->ID, '_blocky_document', true);
            if (!\is_string($document) || $document === '') {
                continue;
            }

            $decoded = \json_decode($document, true);
            if (!\is_array($decoded) || !isset($decoded['nodes']) || !\is_array($decoded['nodes'])) {
                continue;
            }

            $permalink = \get_permalink($post->ID);

            foreach ($decoded['nodes'] as $nodeId => $node) {
                if (!\is_array($node) || !in_array(($node['type'] ?? ''), ['bky/form', 'bky/contact-form'], true)) {
                    continue;
                }

                $props = isset($node['props']) && \is_array($node['props']) ? $node['props'] : [];
                $configuredFormId = trim((string) ($props['formId'] ?? ''));
                $type = (string) ($node['type'] ?? '');
                $defaultPrefix = $type === 'bky/contact-form' ? 'contact' : 'form';
                $formId = $configuredFormId !== '' ? $configuredFormId : $defaultPrefix . '-' . $post->ID . '-' . $nodeId;
                $name = trim((string) ($props['name'] ?? $props['title'] ?? ''));
                $action = trim((string) ($props['action'] ?? ''));

                $forms[] = [
                    'form_id' => $formId,
                    'label' => $name !== '' ? $name : $formId,
                    'mode' => $action === '' ? __('Built-in storage', 'blocky') : __('Custom action', 'blocky'),
                    'post_id' => $post->ID,
                    'post_title' => $post->post_title !== '' ? $post->post_title : __('(no title)', 'blocky'),
                    'permalink' => \is_string($permalink) ? $permalink : '',
                ];
            }
        }

        return $forms;
    }

    /**
     * @return array<string, int>
     */
    private function submissionCountsByFormId(): array
    {
        $counts = [];

        foreach ($this->allFormSubmissions() as $submission) {
            $formId = (string) \get_post_meta($submission->ID, '_blocky_form_id', true);
            if ($formId === '') {
                continue;
            }

            $counts[$formId] = ($counts[$formId] ?? 0) + 1;
        }

        return $counts;
    }

    /**
     * @return array<string, string>
     */
    private function lastSubmissionDateByFormId(): array
    {
        $dates = [];

        foreach ($this->allFormSubmissions() as $submission) {
            $formId = (string) \get_post_meta($submission->ID, '_blocky_form_id', true);
            $submittedAt = (string) \get_post_meta($submission->ID, '_blocky_form_submitted_at', true);

            if ($formId === '' || $submittedAt === '' || isset($dates[$formId])) {
                continue;
            }

            $dates[$formId] = $submittedAt;
        }

        return $dates;
    }

    /**
     * @return \WP_Post[]
     */
    private function recentFormSubmissions(): array
    {
        return $this->queryFormSubmissions(20);
    }

    /**
     * @return \WP_Post[]
     */
    private function allFormSubmissions(): array
    {
        return $this->queryFormSubmissions(-1);
    }

    /**
     * @return \WP_Post[]
     */
    private function queryFormSubmissions(int $postsPerPage): array
    {
        return $this->queryFormSubmissionsByStatus($postsPerPage, $this->activeFormSubmissionStatuses());
    }

    /**
     * @return \WP_Post[]
     */
    private function trashedFormSubmissions(): array
    {
        return $this->queryFormSubmissionsByStatus(20, 'trash');
    }

    /**
     * @return \WP_Post[]
     */
    private function queryFormSubmissionsByStatus(int $postsPerPage, string|array $postStatus): array
    {
        $query = new \WP_Query([
            'post_type' => self::FORM_SUBMISSION_POST_TYPE,
            'post_status' => $postStatus,
            'posts_per_page' => $postsPerPage,
            'orderby' => 'date',
            'order' => 'DESC',
            'no_found_rows' => true,
            'update_post_meta_cache' => true,
            'update_post_term_cache' => false,
        ]);

        return array_values(array_filter(
            $query->posts,
            static fn($post): bool => $post instanceof \WP_Post
        ));
    }

    private function selectedFormSubmission(string $selectedTab): ?\WP_Post
    {
        $submissionId = isset($_GET['submission_id']) ? (int) $_GET['submission_id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only view filter; no state change.
        if ($submissionId <= 0) {
            return null;
        }

        $submission = \get_post($submissionId);
        if (!$submission instanceof \WP_Post || $submission->post_type !== self::FORM_SUBMISSION_POST_TYPE) {
            return null;
        }

        if ($selectedTab === 'trash') {
            if ($submission->post_status !== 'trash') {
                return null;
            }
        } elseif (!\in_array($submission->post_status, $this->activeFormSubmissionStatuses(), true)) {
            return null;
        }

        return $submission;
    }

    /**
     * @return string[]
     */
    private function activeFormSubmissionStatuses(): array
    {
        return ['private', 'draft'];
    }

    private function renderSubmissionDetailCard(\WP_Post $submission, string $selectedTab): void
    {
        $submittedAt = (string) \get_post_meta($submission->ID, '_blocky_form_submitted_at', true);
        $formId = (string) \get_post_meta($submission->ID, '_blocky_form_id', true);
        $sourcePostId = (int) \get_post_meta($submission->ID, '_blocky_form_post_id', true);
        $payload = \get_post_meta($submission->ID, '_blocky_form_payload', true);
        $sourcePost = $sourcePostId > 0 ? \get_post($sourcePostId) : null;
        $sourceTitle = $sourcePost instanceof \WP_Post && $sourcePost->post_title !== '' ? $sourcePost->post_title : __('(no title)', 'blocky');
        $builderUrl = $sourcePostId > 0 ? \esc_url(\admin_url('admin.php?page=blocky-builder&post_id=' . $sourcePostId)) : '';
        $backUrl = \esc_url($this->formsPageUrl($selectedTab));

        echo '<div class="card" style="max-width: 960px; margin: 16px 0;">';
        echo '<h2 style="margin-top:0;">' . \esc_html__('Selected Submission', 'blocky') . '</h2>';
        echo '<p><strong>' . \esc_html($sourceTitle) . '</strong> <span style="color:#646970;">' . \esc_html__('Page', 'blocky') . '</span></p>';
        echo '<p><strong>' . \esc_html__('Form ID:', 'blocky') . '</strong> <code>' . \esc_html($formId) . '</code></p>';
        echo '<p><strong>' . \esc_html__('Submitted:', 'blocky') . '</strong> ' . \esc_html($submittedAt !== '' ? $submittedAt : (string) $submission->post_date_gmt) . '</p>';
        echo '<div style="margin-top:16px;">' . \wp_kses_post($this->formatSubmissionPayload($payload)) . '</div>';
        echo '<p style="margin-top:16px;">';
        echo '<a class="button button-secondary" href="' . \esc_url($backUrl) . '">' . \esc_html__('Back to All Submissions', 'blocky') . '</a> ';
        if ($builderUrl !== '') {
            echo '<a class="button" href="' . \esc_url($builderUrl) . '">' . \esc_html__('Open Builder', 'blocky') . '</a>';
        }
        echo ' ';
        echo $selectedTab === 'trash'
            ? \wp_kses_post($this->restoreSubmissionButton($submission->ID))
            : \wp_kses_post($this->trashSubmissionButton($submission->ID));
        if ($selectedTab === 'trash') {
            echo ' ' . \wp_kses_post($this->deleteSubmissionButton($submission->ID)); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- helper returns pre-escaped markup.
        }
        echo '</p>';
        echo '</div>';
    }

    private function formSubmissionAdminUrl(int $submissionId, string $tab = 'submissions'): string
    {
        return \add_query_arg([
            'page' => self::FORMS_PAGE_SLUG,
            'tab' => $tab,
            'submission_id' => $submissionId,
        ], \admin_url('admin.php'));
    }

    private function currentFormsTab(): string
    {
        return (isset($_GET['tab']) && \sanitize_key((string) $_GET['tab']) === 'trash') ? 'trash' : 'submissions'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only view filter; no state change.
    }

    private function renderFormsTabs(int $activeCount, int $trashCount, string $selectedTab): void
    {
        $submissionsUrl = \esc_url($this->formsPageUrl('submissions'));
        $trashUrl = \esc_url($this->formsPageUrl('trash'));

        echo '<nav class="nav-tab-wrapper" style="margin-top:16px;">';
        /* translators: %d: number of submissions. */
        echo '<a class="nav-tab ' . ($selectedTab === 'submissions' ? 'nav-tab-active' : '') . '" href="' . \esc_url($submissionsUrl) . '">' . \esc_html(sprintf(__('Submissions (%d)', 'blocky'), $activeCount)) . '</a>';
        /* translators: %d: number of submissions. */
        echo '<a class="nav-tab ' . ($selectedTab === 'trash' ? 'nav-tab-active' : '') . '" href="' . \esc_url($trashUrl) . '">' . \esc_html(sprintf(__('Trash (%d)', 'blocky'), $trashCount)) . '</a>';
        echo '</nav>';
    }

    private function formsPageUrl(string $tab = 'submissions', ?string $result = null, ?int $submissionId = null): string
    {
        $args = [
            'page' => self::FORMS_PAGE_SLUG,
        ];

        if ($tab === 'trash') {
            $args['tab'] = 'trash';
        }

        if ($result !== null && $result !== '') {
            $args['blocky_forms'] = $result;
        }

        if ($submissionId !== null && $submissionId > 0) {
            $args['submission_id'] = $submissionId;
        }

        return \add_query_arg($args, \admin_url('admin.php'));
    }

    private function formSubmissionActionUrl(string $action, string $nonceAction, int $submissionId, string $tab): string
    {
        return \wp_nonce_url(\add_query_arg([
            'action' => $action,
            'submission_id' => $submissionId,
            'tab' => $tab,
        ], \admin_url('admin-post.php')), $nonceAction);
    }

    private function trashSubmissionButton(int $submissionId): string
    {
        $url = \esc_url($this->formSubmissionActionUrl('blocky_trash_form_submission', 'blocky_trash_form_submission', $submissionId, 'submissions'));
        return '<a class="button" href="' . $url . '">' . \esc_html__('Move to Trash', 'blocky') . '</a>';
    }

    private function restoreSubmissionButton(int $submissionId): string
    {
        $url = \esc_url($this->formSubmissionActionUrl('blocky_restore_form_submission', 'blocky_restore_form_submission', $submissionId, 'trash'));
        return '<a class="button" href="' . $url . '">' . \esc_html__('Restore', 'blocky') . '</a>';
    }

    private function deleteSubmissionButton(int $submissionId): string
    {
        $url = \esc_url($this->formSubmissionActionUrl('blocky_delete_form_submission', 'blocky_delete_form_submission', $submissionId, 'trash'));
        return '<a class="button button-link-delete" href="' . $url . '">' . \esc_html__('Delete Permanently', 'blocky') . '</a>';
    }

    private function validatedFormSubmission(string $nonceAction): \WP_Post
    {
        if (!\current_user_can('edit_posts')) {
            \wp_die('Forbidden', 403);
        }

        \check_admin_referer($nonceAction);

        $submissionId = isset($_GET['submission_id']) ? (int) $_GET['submission_id'] : 0;
        $submission = $submissionId > 0 ? \get_post($submissionId) : null;
        if (!$submission instanceof \WP_Post || $submission->post_type !== self::FORM_SUBMISSION_POST_TYPE || !\current_user_can('delete_post', $submission->ID)) {
            \wp_die('Forbidden', 403);
        }

        return $submission;
    }

    private function redirectAfterFormsAction(string $result, string $tab, ?int $submissionId = null): never
    {
        \wp_safe_redirect($this->formsPageUrl($tab, $result, $submissionId));
        exit;
    }

    /**
     * @param mixed $payload
     */
    private function formatSubmissionPayload(mixed $payload): string
    {
        if (!\is_array($payload) || $payload === []) {
            return '<span style="color:#646970;">' . \esc_html__('No saved values', 'blocky') . '</span>';
        }

        $rows = [];
        foreach ($payload as $key => $value) {
            $rows[] = '<tr><th style="text-align:left; width:220px; padding:10px 12px;">' . \esc_html((string) $key) . '</th><td style="padding:10px 12px;">' . \esc_html($this->flattenSubmissionValue($value)) . '</td></tr>';
        }

        return '<table class="widefat striped"><tbody>' . implode('', $rows) . '</tbody></table>';
    }

    /**
     * @param mixed $payload
     */
    private function submissionPayloadPreview(mixed $payload): string
    {
        if (!\is_array($payload) || $payload === []) {
            return '<span style="color:#646970;">' . \esc_html__('No saved values', 'blocky') . '</span>';
        }

        $parts = [];
        $index = 0;
        foreach ($payload as $key => $value) {
            $parts[] = '<div><strong>' . \esc_html((string) $key) . ':</strong> ' . \esc_html($this->flattenSubmissionValue($value)) . '</div>';
            $index++;
            if ($index >= 2) {
                break;
            }
        }

        if (count($payload) > 2) {
            $parts[] = '<div style="color:#646970;">' . \esc_html__('Open the submission to view all fields.', 'blocky') . '</div>';
        }

        return implode('', $parts);
    }

    /**
     * @param mixed $value
     */
    private function flattenSubmissionValue(mixed $value): string
    {
        if (\is_array($value)) {
            return implode(', ', array_map([$this, 'flattenSubmissionValue'], $value));
        }

        if (!\is_scalar($value)) {
            return '';
        }

        return (string) $value;
    }
}
