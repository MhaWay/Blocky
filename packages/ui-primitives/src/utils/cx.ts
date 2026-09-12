/**
 * Class name merger utility — similar to clsx but typed.
 * Filters out falsy values, deduplicates, and trims.
 */
export function cx(...classes: Array<string | false | null | undefined | 0>): string {
  return classes
    .filter((c): c is string => typeof c === 'string' && c.length > 0)
    .join(' ')
    .replace(/\s+/g, ' ')
    .trim();
}
