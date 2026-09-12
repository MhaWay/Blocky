/** @type {import('eslint').Linter.Config} */
const blockyConfig = {
  rules: {
    // ── Token enforcement ─────────────────────────────────────────────────────
    // Disallow raw Tailwind colour utilities (must use token utilities instead)
    'no-restricted-syntax': [
      'warn',
      {
        selector:
          'JSXAttribute[name.name="className"] Literal[value=/\\bbg-(red|blue|green|yellow|purple|pink|indigo|gray|slate|zinc|neutral|stone|orange|amber|lime|emerald|teal|cyan|sky|violet|fuchsia|rose)-/]',
        message:
          'Use token-based utilities (bg-surface, bg-accent, etc.) instead of raw Tailwind colour classes.',
      },
    ],
    // ── General quality ───────────────────────────────────────────────────────
    '@typescript-eslint/no-explicit-any': 'error',
    '@typescript-eslint/consistent-type-imports': ['error', { prefer: 'type-imports' }],
    '@typescript-eslint/no-unused-vars': ['error', { argsIgnorePattern: '^_' }],
    '@typescript-eslint/prefer-nullish-coalescing': 'error',
    '@typescript-eslint/no-non-null-assertion': 'error',
  },
};

export default blockyConfig;
