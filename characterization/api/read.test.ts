import { afterAll, beforeAll, beforeEach, describe, it } from 'vitest';
import { diffDb } from '../support/db-diff';
import { expectGolden } from '../support/golden';
import { startLegacyServer, type LegacyServer, type RpcParams } from '../support/legacy-server';

// Read methods of legacy api.php. Some of them write (best-file caching), so the DB diff is recorded too.
const cases: [name: string, action: string, params: RpcParams][] = [
  ['getAllTVShows', 'getAllTVShows', {}],
  ['getTVShow', 'getTVShow', { showId: 1 }],
  ['getTVShow-noSeasons', 'getTVShow', { showId: 3 }],
  ['getTVShow-unknown', 'getTVShow', { showId: 999 }],
  ['getTVShowSeasons', 'getTVShowSeasons', { showId: 1 }],
  ['getSeason', 'getSeason', { seasonId: 2 }],
  ['getSeason-unknown', 'getSeason', { seasonId: 999 }],
  ['getAllWatchedSeasons', 'getAllWatchedSeasons', {}],
  ['getSeasonEpisodes', 'getSeasonEpisodes', { seasonId: 2 }],
  ['getEpisode', 'getEpisode', { episodeId: 2 }],
  ['getEpisode-unknown', 'getEpisode', { episodeId: 999 }],
  ['getFilesForEpisode', 'getFilesForEpisode', { episodeId: 2 }],
  ['getFile', 'getFile', { fileId: 1 }],
  ['getFile-unknown', 'getFile', { fileId: 999 }],
  ['getTVShowScrapers', 'getTVShowScrapers', { showId: 1 }],
  ['getSeasonScrapers', 'getSeasonScrapers', { seasonId: 2 }],
  ['getScraper-show', 'getScraper', { scraperId: 1 }],
  ['getScraper-season', 'getScraper', { scraperId: 2 }],
  ['getScraper-unknown', 'getScraper', { scraperId: 999 }],
  ['getActiveScrapers', 'getActiveScrapers', {}],
  ['getScrapedSeasons', 'getScrapedSeasons', { showId: 1 }],
  ['getScrapedSeasonsTBN', 'getScrapedSeasonsTBN', {}],
  ['getBestFileForEpisode-preferenceAndDelay', 'getBestFileForEpisode', { episodeId: 1 }],
  ['getBestFileForEpisode-sticky', 'getBestFileForEpisode', { episodeId: 2 }],
  ['getBestFileForEpisode-notYetPublished', 'getBestFileForEpisode', { episodeId: 3 }],
  ['getBestFileForEpisode-noFiles', 'getBestFileForEpisode', { episodeId: 4 }],
  ['getBestFilesForSeason', 'getBestFilesForSeason', { seasonId: 2 }],
];

describe('legacy api.php — read methods', () => {
  let legacy: LegacyServer;

  beforeAll(async () => {
    legacy = await startLegacyServer();
  });
  afterAll(() => legacy?.stop());
  beforeEach(() => legacy.reset());

  it.each(cases)('%s', async (name, action, params) => {
    const before = legacy.dump();
    const response = await legacy.rpc(action, params);
    await expectGolden(`api/read/${name}`, { request: { action, ...params }, response, dbDiff: diffDb(before, legacy.dump()) });
  });
});
