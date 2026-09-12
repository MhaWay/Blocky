import { compile } from 'tailwindcss';
import tailwindBaseCss from 'tailwindcss/index.css?raw';
import blockyTokensCss from '../../../tokens/build/tokens.tailwind.css?raw';
import blockyThemeTokensCss from '../../../tokens/build/tokens.css?raw';
import blockyThemeDarkTokensCss from '../../../tokens/build/tokens.dark.css?raw';

let compilerPromise: Promise<Awaited<ReturnType<typeof compile>>> | null = null;

export const BLOCKY_THEME_TOKENS_CSS = blockyThemeTokensCss;
export const BLOCKY_THEME_DARK_TOKENS_CSS = blockyThemeDarkTokensCss;

function normalizedCandidates(input: string[]): string[] {
  return Array.from(new Set(input.filter(candidate => candidate.trim() !== ''))).sort();
}

export function extractClassCandidatesFromHtml(html: string): string[] {
  const matches = html.matchAll(/class\s*=\s*["']([^"']+)["']/g);
  const candidates = new Set<string>();

  for (const match of matches) {
    const classList = match[1] ?? '';
    for (const candidate of classList.split(/\s+/)) {
      if (candidate.trim() !== '') {
        candidates.add(candidate);
      }
    }
  }

  return Array.from(candidates).sort();
}

async function getCompiler(): Promise<Awaited<ReturnType<typeof compile>>> {
  if (compilerPromise === null) {
    const compilerCssFragments = window.BlockyBuilderConfig?.compilerCssFragments ?? [];
    const extraCss = compilerCssFragments.filter(fragment => fragment.trim() !== '').join('\n');

    compilerPromise = compile(`${tailwindBaseCss}\n${blockyTokensCss}\n${extraCss}`);
  }

  return compilerPromise;
}

export async function compileFrontendPageCss(candidates: string[]): Promise<string> {
  const nextCandidates = normalizedCandidates(candidates);
  if (nextCandidates.length === 0) {
    return '';
  }

  const compiler = await getCompiler();
  return compiler.build(nextCandidates);
}

export async function compileFrontendPageCssFromHtml(html: string): Promise<string> {
  return compileFrontendPageCss(extractClassCandidatesFromHtml(html));
}