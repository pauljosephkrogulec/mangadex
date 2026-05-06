import { dirname } from 'path';
import { fileURLToPath } from 'url';

// Import plugins - they are CommonJS modules, so we need to handle the default export
import eslintPluginNextImport from '@next/eslint-plugin-next';
import eslintPluginReactImport from 'eslint-plugin-react';
import eslintPluginReactHooksImport from 'eslint-plugin-react-hooks';
import eslintPluginImportImport from 'eslint-plugin-import';
import eslintPluginJsxA11yImport from 'eslint-plugin-jsx-a11y';
import typescriptEslintImport from 'typescript-eslint';
import globals from 'globals';

// Extract the actual plugin objects from the CommonJS default exports
const eslintPluginNext = eslintPluginNextImport.default || eslintPluginNextImport;
const eslintPluginReact = eslintPluginReactImport.default || eslintPluginReactImport;
const eslintPluginReactHooks = eslintPluginReactHooksImport.default || eslintPluginReactHooksImport;
const eslintPluginImport = eslintPluginImportImport.default || eslintPluginImportImport;
const eslintPluginJsxA11y = eslintPluginJsxA11yImport.default || eslintPluginJsxA11yImport;
const typescriptEslint = typescriptEslintImport.default || typescriptEslintImport;

const __filename = fileURLToPath(import.meta.url);
const __dirname = dirname(__filename);

const eslintConfig = [
  {
    name: 'next/core-web-vitals',
    files: ['**/*.{js,jsx,mjs,ts,tsx,mts,cts}'],
    plugins: {
      '@next/next': eslintPluginNext,
      react: eslintPluginReact,
      'react-hooks': eslintPluginReactHooks,
      import: eslintPluginImport,
      'jsx-a11y': eslintPluginJsxA11y,
    },
    languageOptions: {
      parser: typescriptEslint.parser,
      parserOptions: {
        requireConfigFile: false,
        sourceType: 'module',
        allowImportExportEverywhere: true,
        ecmaFeatures: {
          jsx: true,
        },
      },
      globals: {
        ...globals.browser,
        ...globals.node,
      },
    },
    settings: {
      react: {
        version: 'detect',
      },
      'import/parsers': {
        '@typescript-eslint/parser': ['.ts', '.mts', '.cts', '.tsx', '.d.ts'],
      },
      'import/resolver': {
        node: {
          extensions: ['.js', '.jsx', '.ts', '.tsx'],
        },
        typescript: {
          alwaysTryTypes: true,
        },
      },
    },
    rules: {
      // React recommended rules (avoiding circular reference from configs.recommended)
      'react/prop-types': 'off',
      'react/react-in-jsx-scope': 'off',
      'react/no-unknown-property': 'off',
      'react/no-unescaped-entities': 'warn',
      // React hooks recommended rules
      'react-hooks/rules-of-hooks': 'error',
      'react-hooks/exhaustive-deps': 'warn',
      // Next.js recommended rules
      '@next/next/no-html-link-for-pages': 'error',
      // Import plugin rules
      'import/no-anonymous-default-export': 'warn',
      // JSX a11y rules
      'jsx-a11y/alt-text': ['warn', { elements: ['img'], img: ['Image'] }],
      'jsx-a11y/aria-props': 'warn',
      'jsx-a11y/aria-proptypes': 'warn',
      'jsx-a11y/aria-unsupported-elements': 'warn',
      'jsx-a11y/role-has-required-aria-props': 'warn',
      'jsx-a11y/role-supports-aria-props': 'warn',
      'react/jsx-no-target-blank': 'off',
    },
  },
  {
    name: 'next/typescript',
    files: ['**/*.ts', '**/*.tsx'],
    plugins: {
      '@typescript-eslint': typescriptEslint.plugin || typescriptEslint,
    },
    languageOptions: {
      parser: typescriptEslint.parser,
      parserOptions: {
        sourceType: 'module',
      },
    },
  },
  {
    ignores: [
      '.next/**',
      'out/**',
      'build/**',
      'coverage/**',
      'next-env.d.ts',
    ],
  },
];

export default eslintConfig;
