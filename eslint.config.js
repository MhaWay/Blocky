// @ts-check
import js from '@eslint/js';
import ts from 'typescript-eslint';
import blockyConfig from './tools/eslint-config/index.js';

export default ts.config(
  js.configs.recommended,
  ...ts.configs.strictTypeChecked,
  ...ts.configs.stylisticTypeChecked,
  blockyConfig,
  {
    ignores: [
      '**/dist/**',
      '**/build/**',
      '**/vendor/**',
      '**/node_modules/**',
      '**/*.config.js',
      '**/*.config.ts',
      'packages/tokens/build/**',
    ],
  }
);
