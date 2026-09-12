/** @type {import('stylelint').Config} */
export default {
  extends: ['stylelint-config-standard', 'stylelint-config-tailwindcss'],
  rules: {
    // Allow Tailwind directives
    'at-rule-no-unknown': [
      true,
      {
        ignoreAtRules: ['theme', 'source', 'utility', 'variant', 'layer', 'tailwind', 'apply'],
      },
    ],
    // Enforce --bky- prefix for custom properties
    'custom-property-pattern': '^bky-.+',
    // No inline colours — use tokens
    'color-no-invalid-hex': true,
  },
};
