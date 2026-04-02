import { defineConfig } from '@rsbuild/core';
import { pluginReact } from '@rsbuild/plugin-react';

export default defineConfig({
  plugins: [pluginReact()],
  output: {
    distPath: {
      root: 'dist',
    },
  },
  source: {
    entry: {
      index: './js/src/index.tsx',
    },
  },
});
