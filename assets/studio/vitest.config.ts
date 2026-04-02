import { defineConfig } from 'vitest/config';

export default defineConfig({
  test: {
    globals: true,
    environment: 'jsdom',
    setupFiles: [],
    include: ['js/src/**/*.{test,spec}.{ts,tsx}'],
    coverage: {
      provider: 'v8',
      reporter: ['text', 'html'],
      include: ['js/src/**/*.{ts,tsx}'],
      exclude: ['js/src/**/*.{test,spec}.{ts,tsx}', 'js/src/index.tsx'],
    },
  },
});
