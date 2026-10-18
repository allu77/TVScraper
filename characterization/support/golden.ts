import { expect } from 'vitest';

/**
 * Compares a value with its golden master in `__golden__/<name>.json` next to the test file.
 * Missing golden files are created on first run; update intentionally with `vitest -u`.
 */
export async function expectGolden(name: string, value: unknown): Promise<void> {
  await expect(JSON.stringify(value, null, 2) + '\n').toMatchFileSnapshot(`../__golden__/${name}.json`);
}
