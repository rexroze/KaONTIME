import js from '@eslint/js'
import vue from 'eslint-plugin-vue'
import tseslint from 'typescript-eslint'

export default [
  { ignores: ['.quasar/**', 'dist/**', 'node_modules/**'] },
  { files: ['scripts/**/*.mjs'], languageOptions: { globals: { URL: 'readonly', document: 'readonly', innerWidth: 'readonly', console: 'readonly' } } },
  js.configs.recommended,
  ...tseslint.configs.recommended,
  ...vue.configs['flat/recommended'],
  {
    files: ['**/*.{js,ts,vue}'],
    languageOptions: { parserOptions: { parser: tseslint.parser } },
    rules: {
      'no-undef': 'off',
      'vue/multi-word-component-names': 'off',
      'vue/no-v-model-argument': 'off',
      'vue/html-self-closing': ['error', { html: { void: 'always' } }]
    }
  }
]
