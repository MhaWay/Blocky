<?php
declare(strict_types=1);

namespace Blocky\Core\Blocks\Renderers;

use Blocky\Core\Blocks\Renderer\BlockRendererInterface;
use Blocky\Core\Blocks\Node;
use Blocky\Core\Support\HtmlString;
use Blocky\Core\Support\RenderContext;

final class AnimatedHeadlineRenderer implements BlockRendererInterface
{
    public function render(Node $node, RenderContext $ctx): HtmlString
    {
        $prefix = trim((string) ($node->props['prefix'] ?? ''));
        $suffix = trim((string) ($node->props['suffix'] ?? ''));
        $words = self::parseWords((string) ($node->props['words'] ?? ''));
        $effect = (string) ($node->props['effect'] ?? 'rotate');
        $interval = max(1000, min(10000, ((int) ($node->props['interval'] ?? 3)) * 1000));

        $classes = $ctx->resolveVariantClasses($node, [
            'align' => ['start' => 'items-start text-left', 'center' => 'items-center text-center'],
            'tone' => ['base' => 'text-text-base', 'accent' => 'text-accent-text', 'contrast' => 'text-text-on-accent'],
        ]);

        $currentWord = $words[0] ?? 'headline';
        $encodedWords = base64_encode(function_exists('wp_json_encode') ? (string) wp_json_encode($words) : (string) json_encode($words));

        return HtmlString::element(
            'div',
            $ctx->blockAttrs($node, [
                'data-bky-animated-headline-root' => 'bky-animated-headline-' . $node->id,
                'data-bky-animated-words' => $encodedWords,
                'data-bky-animated-effect' => $effect,
                'data-bky-animated-interval' => (string) $interval,
                'class' => trim('flex flex-col gap-2 ' . $classes),
            ]),
            '<h2 class="flex flex-wrap items-baseline gap-x-3 gap-y-1 text-4xl font-semibold leading-tight md:text-5xl">'
            . ($prefix !== '' ? '<span>' . esc_html($prefix) . '</span>' : '')
            . '<span data-bky-animated-word class="relative inline-flex min-h-[1.2em] overflow-hidden border-b border-current/20 pb-1 text-accent-base">' . esc_html($currentWord) . '</span>'
            . ($suffix !== '' ? '<span>' . esc_html($suffix) . '</span>' : '')
            . '</h2>'
            . self::script($ctx)
        );
    }

    /** @return string[] */
    private static function parseWords(string $raw): array
    {
        $words = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $raw) ?: [])));
        return $words !== [] ? $words : ['headline'];
    }

    private static function script(RenderContext $ctx): string
    {
        if ($ctx->isEditorMode()) {
            return '';
        }

        return '<script>(function(){var root=document.currentScript.closest("[data-bky-animated-headline-root]");if(!root)return;var target=root.querySelector("[data-bky-animated-word]");if(!target)return;var encoded=root.getAttribute("data-bky-animated-words")||"";var words=[];try{words=JSON.parse(atob(encoded));}catch(error){words=[];}if(!Array.isArray(words)){words=[];}words=words.map(function(item){return String(item||"").trim();}).filter(Boolean);if(words.length<2)return;var effect=root.getAttribute("data-bky-animated-effect")||"rotate";var interval=Number(root.getAttribute("data-bky-animated-interval")||"3000");var index=0;function renderWord(next){if(effect==="typing"){target.textContent="";var chars=next.split("");var cursor=0;var typing=window.setInterval(function(){target.textContent=chars.slice(0,cursor+1).join("");cursor+=1;if(cursor>=chars.length){window.clearInterval(typing);}},40);return;}target.style.transition="opacity 220ms ease, transform 220ms ease";target.style.opacity="0";target.style.transform=effect==="slide"?"translateY(35%)":"rotateX(-18deg)";window.setTimeout(function(){target.textContent=next;target.style.opacity="1";target.style.transform="translateY(0) rotateX(0deg)";},200);}window.setInterval(function(){index=(index+1)%words.length;renderWord(words[index]);},Math.max(1000,interval));})();</script>';
    }
}