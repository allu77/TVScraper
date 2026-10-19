// Node CLI around probe.ts: runs the DDU spike and saves redacted fixture sets for characterization/ddu.
//
//   DDU_USERNAME=... DDU_PASSWORD=... node tools/ddu-probe/cli.ts \
//     --board 'https://ddunlimited.net/viewforum.php?f=NNN' --title 'Show title' [--alt-title '...'] [--res any] [--lang ita] \
//     [--topic 'https://ddunlimited.net/viewtopic.php?t=NNN' --season N] \
//     [--name case-name] [--report-only]
//
// Without --report-only, pages are written to characterization/fixtures/ddu/<name>-board and <name>-topic.
// Review the saved HTML before committing it: redaction covers the username, session ids, form tokens and
// e-mail addresses, nothing else.

import { mkdirSync, writeFileSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { parseArgs } from 'node:util';
import { probe, redact } from './probe.ts';

const FIXTURES = resolve(dirname(fileURLToPath(import.meta.url)), '../../characterization/fixtures/ddu');

const { values: args } = parseArgs({
  options: {
    board: { type: 'string' },
    topic: { type: 'string' },
    title: { type: 'string' },
    'alt-title': { type: 'string' },
    res: { type: 'string', default: 'any' },
    lang: { type: 'string', default: 'ita' },
    season: { type: 'string' },
    name: { type: 'string', default: 'real' },
    'report-only': { type: 'boolean', default: false },
  },
});

const username = process.env.DDU_USERNAME ?? process.env.TVSCRAPER_BROWSER_DDU_LOGIN;
const password = process.env.DDU_PASSWORD ?? process.env.TVSCRAPER_BROWSER_DDU_PASSWORD;
if (!username || !password) {
  console.error('Set DDU_USERNAME and DDU_PASSWORD');
  process.exit(2);
}
if (!args.board && !args.topic) {
  console.error('Pass --board and/or --topic');
  process.exit(2);
}
if (args.board && !args.title) {
  console.error('--board needs --title (the show the board is scraped for)');
  process.exit(2);
}
if (args.topic && !args.season) {
  console.error('--topic needs --season (the season the topic belongs to)');
  process.exit(2);
}

// Same pagination as TVShowScraperDDU::runScraperTVShow.
const boardUrls = args.board ? [0, 25, 50, 75].map((o) => `${args.board}&start=${o}`) : [];
const topicUrls = args.topic ? [args.topic] : [];

const { report, pages, lastPage } = await probe({ username, password, urls: [...boardUrls, ...topicUrls] });
console.log(JSON.stringify(report, null, 2));

if (!report.ok && lastPage !== undefined) {
  // Kept locally (git-ignored) to check by eye what the site actually answered.
  const file = resolve('ddu-probe-last-response.html');
  writeFileSync(file, redact(lastPage, username));
  console.error(`Last response saved to ${file}`);
}

if (!args['report-only'] && report.ok) {
  const show = { title: args.title ?? 'Unknown', ...(args['alt-title'] ? { alternateTitle: args['alt-title'] } : {}), lang: args.lang, nativeLang: 'eng', res: args.res };

  const writeCase = (dir: string, manifest: object, files: [url: string, file: string][]) => {
    mkdirSync(dir, { recursive: true });
    for (const [url, file] of files) writeFileSync(join(dir, file), redact(pages.get(url)!, username));
    writeFileSync(join(dir, 'manifest.json'), JSON.stringify(manifest, null, 2) + '\n');
    console.error(`Saved ${dir} — review the HTML before committing`);
  };

  if (args.board) {
    const files = boardUrls.map((u, i): [string, string] => [u, `board-${i * 25}.html`]);
    writeCase(join(FIXTURES, `${args.name}-board`), {
      capturedAt: new Date().toISOString(),
      scraper: { type: 'tvShow', uri: args.board, autoAdd: 0, notify: 1 },
      show,
      seasons: [],
      pages: files.map(([url, file]) => ({ url, file, kind: 'board' })),
    }, files);
  }
  if (args.topic) {
    writeCase(join(FIXTURES, `${args.name}-topic`), {
      capturedAt: new Date().toISOString(),
      scraper: { type: 'season', uri: args.topic },
      show,
      seasons: [{ n: Number(args.season), status: 'watched' }],
      pages: [{ url: args.topic, file: 'topic.html', kind: 'topic' }],
    }, [[args.topic, 'topic.html']]);
  }
}

process.exit(report.ok ? 0 : 1);
