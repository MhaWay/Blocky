<?php
declare(strict_types=1);

namespace Blocky\Core\Compiler;

defined( 'ABSPATH' ) || exit; // Protect against direct file access.

use Blocky\Core\Blocks\Renderer\Pipeline;

final class PageCompiler
{
    public const HTML_CACHE_META_KEY = '_blocky_render_cache';
    public const CSS_CACHE_META_KEY = '_blocky_css_cache';
    public const CSS_CANDIDATES_META_KEY = '_blocky_css_candidates';
    public const CSS_FILE_META_KEY = '_blocky_css_file';
    public const COMPILE_HASH_META_KEY = '_blocky_compile_hash';

    public function __construct(
        private readonly Pipeline $pipeline,
    ) {}

    /**
     * @return array{html: string, hash: string, document: string, postId: int, classCandidates: string[]}
     */
    public function compileDocument(int $postId, string $document, bool $editorMode = false): array
    {
        $html = $this->pipeline->renderDocument($document, $editorMode, $postId)->toString();
        $result = [
            'html'            => $html,
            'hash'            => $this->documentHash($document),
            'document'        => $document,
            'postId'          => $postId,
            'classCandidates' => $this->extractClassCandidates($html),
        ];

        /** @var array{html: string, hash: string, document: string, postId: int, classCandidates: string[]} $result */
        $result = \apply_filters('blocky_compiler_page_result', $result, $postId, $editorMode);

        return $result;
    }

    /**
     * @return array{html: string, hash: string, document: string, postId: int, classCandidates: string[]}
     */
    public function warmFrontendCache(int $postId, string $document): array
    {
        $compiled = $this->compileDocument($postId, $document, false);

        \update_post_meta($postId, self::HTML_CACHE_META_KEY, $compiled['html']);
        \update_post_meta($postId, self::COMPILE_HASH_META_KEY, $compiled['hash']);
        \update_post_meta($postId, self::CSS_CANDIDATES_META_KEY, \wp_json_encode($compiled['classCandidates']));

        /**
         * Allow third-party block providers to persist additional compile artifacts
         * such as page-scoped CSS, asset manifests, or block-specific metadata.
         */
        \do_action('blocky_compiler_page_cache_warmed', $postId, $compiled);

        return $compiled;
    }

    /**
     * Persist browser-compiled per-page CSS (docs/research/04, L3).
     *
     * The builder compiles each page's stylesheet in the admin browser at save
     * time and POSTs it here; we store it as a hashed static file and keep the
     * raw string as a fallback for hosts where the filesystem write fails.
     */
    public function cacheCompiledCss(int $postId, string $css): void
    {
        $previous = (string) \get_post_meta($postId, self::CSS_FILE_META_KEY, true);

        if ($css === '') {
            \delete_post_meta($postId, self::CSS_CACHE_META_KEY);
            \delete_post_meta($postId, self::CSS_FILE_META_KEY);
            $this->deleteCachedCssFile($previous);
            return;
        }

        $url = $this->writeCachedCssFile($postId, $css);
        if ($url !== '') {
            \update_post_meta($postId, self::CSS_FILE_META_KEY, $url);
            if ($previous !== '' && $previous !== $url) {
                $this->deleteCachedCssFile($previous);
            }
        } else {
            \delete_post_meta($postId, self::CSS_FILE_META_KEY);
        }

        \update_post_meta($postId, self::CSS_CACHE_META_KEY, $css);
    }

    private function writeCachedCssFile(int $postId, string $css): string
    {
        $uploads = \wp_upload_dir();
        if (false !== $uploads['error']) {
            return '';
        }

        $dir = trailingslashit($uploads['basedir']) . 'blocky';
        if (!\wp_mkdir_p($dir)) {
            return '';
        }

        $name = 'page-' . $postId . '-' . substr(md5($css), 0, 10) . '.css';
        if (\file_put_contents($dir . '/' . $name, $css) === false) {
            return '';
        }

        return trailingslashit($uploads['baseurl']) . 'blocky/' . $name;
    }

    private function deleteCachedCssFile(string $url): void
    {
        $uploads = \wp_upload_dir();
        if (false !== $uploads['error']) {
            return;
        }
        $baseDir = trailingslashit($uploads['basedir']) . 'blocky/';
        $baseUrl = trailingslashit($uploads['baseurl']) . 'blocky/';
        if (!str_starts_with($url, $baseUrl)) {
            return;
        }

        $name = basename(substr($url, strlen($baseUrl)));
        $path = $baseDir . $name;
        if ($name !== '' && \is_file($path)) {
            \wp_delete_file($path);
        }
    }

    /**
     * @return string[]
     */
    public function cachedCandidates(int $postId): array
    {
        $raw = \get_post_meta($postId, self::CSS_CANDIDATES_META_KEY, true);

        if (!\is_string($raw) || $raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (!\is_array($decoded)) {
            return [];
        }

        return array_values(array_filter($decoded, static fn(mixed $value): bool => \is_string($value) && $value !== ''));
    }

    public function clearCache(int $postId): void
    {
        \delete_post_meta($postId, self::HTML_CACHE_META_KEY);
        \delete_post_meta($postId, self::CSS_CACHE_META_KEY);
        \delete_post_meta($postId, self::CSS_CANDIDATES_META_KEY);
        \delete_post_meta($postId, self::COMPILE_HASH_META_KEY);

        $cssUrl = (string) \get_post_meta($postId, self::CSS_FILE_META_KEY, true);
        \delete_post_meta($postId, self::CSS_FILE_META_KEY);
        $this->deleteCachedCssFile($cssUrl);

        \do_action('blocky_compiler_page_cache_cleared', $postId);
    }

    public function cachedHtml(int $postId): string
    {
        $cachedHtml = \get_post_meta($postId, self::HTML_CACHE_META_KEY, true);
        return \is_string($cachedHtml) ? $cachedHtml : '';
    }

    private function documentHash(string $document): string
    {
        $version = \defined('BLOCKY_CORE_VERSION') ? (string) \constant('BLOCKY_CORE_VERSION') : '';
        return md5($document . $version);
    }

    /**
     * Freshness check for the frontend HTML cache: a page is fresh only when its
     * stored compile hash matches the current document AND the current engine build
     * (so an upgrade that changes renderer output rebuilds the cache on first view).
     */
    public function cacheIsFresh(int $postId, string $document): bool
    {
        $stored = \get_post_meta($postId, self::COMPILE_HASH_META_KEY, true);
        return \is_string($stored) && $stored !== '' && \hash_equals($stored, $this->documentHash($document));
    }

    /**
     * @return string[]
     */
    private function extractClassCandidates(string $html): array
    {
        if ($html === '') {
            return [];
        }

        preg_match_all('/class\s*=\s*["\']([^"\']+)["\']/', $html, $matches);
        $classes = [];

        foreach ((array) $matches[1] as $classList) {
            foreach (preg_split('/\s+/', trim((string) $classList)) ?: [] as $className) {
                if ($className === '') {
                    continue;
                }

                $classes[$className] = true;
            }
        }

        $candidates = array_keys($classes);

        /** @var string[] $candidates */
        $candidates = \apply_filters('blocky_compiler_page_class_candidates', $candidates, $html);

        sort($candidates);

        return $candidates;
    }
}