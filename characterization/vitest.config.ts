import { defineConfig } from 'vitest/config';

export default defineConfig({
  test: {
    root: 'characterization',
    include: ['**/*.test.ts'],
    testTimeout: 30_000,
  },
});
