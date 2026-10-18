import { afterAll, beforeAll, beforeEach, describe, it } from 'vitest';
import { diffDb } from '../support/db-diff';
import { expectGolden } from '../support/golden';
import { startLegacyServer, type LegacyServer, type RpcParams } from '../support/legacy-server';

// Mutating methods of legacy api.php: the response and the resulting DB changes are both recorded.
const cases: [name: string, action: string, params: RpcParams][] = [
  ['addTVShow', 'addTVShow', { title: 'Severance', lang: 'ita', nativeLang: 'eng', res: '1080p' }],
  ['addTVShow-unknownParam', 'addTVShow', { title: 'X', bogus: 1 }],
  ['addTVShow-missingTitle', 'addTVShow', { lang: 'ita' }],
  ['setTVShow', 'setTVShow', { showId: 2, alternateTitle: 'Dark (DE)', res: '1080p' }],
  ['removeTVShow', 'removeTVShow', { showId: 1 }],
  ['addSeason', 'addSeason', { showId: 3, n: 1, status: 'watched' }],
  ['setSeason', 'setSeason', { seasonId: 2, status: 'complete' }],
  ['removeSeason', 'removeSeason', { seasonId: 2 }],
  ['setEpisode', 'setEpisode', { episodeId: 4, title: 'Down', airDate: 948499200 }],
  ['setEpisode-unstick', 'setEpisode', { episodeId: 2, bestSticky: 0 }],
  ['removeEpisode', 'removeEpisode', { episodeId: 1 }],
  ['setFile-discard', 'setFile', { fileId: 5, discard: 1 }],
  ['removeFile', 'removeFile', { fileId: 2 }],
  ['addScraper-season', 'addScraper', { rootId: 3, type: 'season', source: 'RSS', uri: 'http://example.invalid/dark.rss', preference: '' }],
  ['addScraper-show', 'addScraper', { rootId: 3, type: 'tvShow', source: 'DDU', uri: 'https://ddunlimited.net/viewforum.php?f=3' }],
  ['addScraper-badType', 'addScraper', { rootId: 3, type: 'bogus', source: 'DDU', uri: 'x' }],
  ['setScraper-delay', 'setScraper', { scraperId: 3, delay: 0 }],
  ['setScraper-clearPreference', 'setScraper', { scraperId: 2, preference: '' }],
  ['removeScraper', 'removeScraper', { scraperId: 3 }],
  ['setScrapedSeason', 'setScrapedSeason', { scrapedSeasonId: 1, hide: 1, tbn: '_REMOVE_' }],
  ['removeScrapedSeason', 'removeScrapedSeason', { scrapedSeasonId: 1 }],
  ['createSeasonScraperFromScraped-newSeason', 'createSeasonScraperFromScraped', { scrapedSeasonId: 1 }],
  ['createSeasonScraperFromScraped-existingSeason', 'createSeasonScraperFromScraped', { scrapedSeasonId: 2 }],
];

describe('legacy api.php — write methods', () => {
  let legacy: LegacyServer;

  beforeAll(async () => {
    legacy = await startLegacyServer();
  });
  afterAll(() => legacy?.stop());
  beforeEach(() => legacy.reset());

  it.each(cases)('%s', async (name, action, params) => {
    const before = legacy.dump();
    const response = await legacy.rpc(action, params);
    await expectGolden(`api/write/${name}`, { request: { action, ...params }, response, dbDiff: diffDb(before, legacy.dump()) });
  });
});
