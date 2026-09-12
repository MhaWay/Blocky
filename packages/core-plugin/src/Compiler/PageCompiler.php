<?php
declare(strict_types=1);

namespace Blocky\Core\Compiler;

use Blocky\Core\Blocks\Renderer\Pipeline;

final class PageCompiler
{
    public const HTML_CACHE_META_KEY = '_blocky_render_cache';
    public const CSS_CACHE_META_KEY = '_blocky_css_cache';
    public const CSS_CANDIDATES_META_KEY = '_blocky_css_candidates';
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
        $result = \apply_filters('blocky/compiler/page_result', $result, $postId, $editorMode);

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
        \do_action('blocky/compiler/page_cache_warmed', $postId, $compiled);

        return $compiled;
    }

    public function cacheCompiledCss(int $postId, string $css): void
    {
        if ($css === '') {
            \delete_post_meta($postId, self::CSS_CACHE_META_KEY);
            return;
        }

        \update_post_meta($postId, self::CSS_CACHE_META_KEY, $css);
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

        \do_action('blocky/compiler/page_cache_cleared', $postId);
    }

    public function cachedHtml(int $postId): string
    {
        $cachedHtml = \get_post_meta($postId, self::HTML_CACHE_META_KEY, true);
        return \is_string($cachedHtml) ? $cachedHtml : '';
    }

    private function documentHash(string $document): string
    {
        return md5($document);
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
        $candidates = \apply_filters('blocky/compiler/page_class_candidates', $candidates, $html);

        sort($candidates);

        return $candidates;
    }
}