import { execFileSync } from 'node:child_process';
import { existsSync, mkdtempSync, readdirSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { describe, it } from 'vitest';
import { expectGolden } from '../support/golden';
import { LEGACY_DIR, REPO_ROOT } from '../support/legacy-server';

// Golden masters of the legacy DDU code (parser + TVShowScraperDDU), run offline against fixture sets.
// Each directory in fixtures/ddu with a manifest.json is one case (see support/php/ddu-scrape.php).
// Real captures come from tools/ddu-probe; directories named synthetic-* are hand-written markup.

const FIXTURES = join(REPO_ROOT, 'characterization/fixtures/ddu');
const SCRIPT = join(REPO_ROOT, 'characterization/support/php/ddu-scrape.php');
const PHP = process.env.PHP_BIN ?? 'php';

const cases = existsSync(FIXTURES)
  ? readdirSync(FIXTURES).filter((d) => existsSync(join(FIXTURES, d, 'manifest.json')))
  : [];

/** Replaces values that depend on the time of the run (episode pubDate = time(), "older than 30 days" warning). */
function normalize(value: unknown, runStart: number): unknown {
  if (Array.isArray(value)) {
    return value
      .filter((v) => !(typeof v === 'string' && v === 'Latest topic is older than 30 days'))
      .map((v) => normalize(v, runStart));
  }
  if (value && typeof value === 'object') {
    return Object.fromEntries(
      Object.entries(value).map(([k, v]) => [
        k,
        k === 'pubDate' && typeof v === 'number' && v >= runStart ? '<run time>' : normalize(v, runStart),
      ]),
    );
  }
  return value;
}

describe('legacy DDU scraper — offline golden masters', () => {
  if (!cases.some((c) => !c.startsWith('synthetic-'))) {
    it.todo('real DDU captures: run tools/ddu-probe with DDU credentials');
  }

  it.each(cases)('%s', async (name) => {
    const workDir = mkdtempSync(join(tmpdir(), 'tvscraper-ddu-'));
    const runStart = Math.floor(Date.now() / 1000) - 5;
    try {
      const out = execFileSync(PHP, [SCRIPT, LEGACY_DIR, join(FIXTURES, name), workDir], {
        encoding: 'utf8',
        // Any page missing from the fixtures must fail instead of reaching the real site.
        env: { ...process.env, http_proxy: 'http://127.0.0.1:9', https_proxy: 'http://127.0.0.1:9', HTTPS_PROXY: 'http://127.0.0.1:9', HTTP_PROXY: 'http://127.0.0.1:9', no_proxy: '', NO_PROXY: '' },
      });
      await expectGolden(`ddu/${name}`, normalize(JSON.parse(out), runStart));
    } finally {
      rmSync(workDir, { recursive: true, force: true });
    }
  });
});
