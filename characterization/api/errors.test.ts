import { afterAll, beforeAll, beforeEach, describe, it } from 'vitest';
import { expectGolden } from '../support/golden';
import { startLegacyServer, type LegacyServer, type RpcParams } from '../support/legacy-server';

// Error handling of the RPC envelope. runScraper is only exercised on paths that do not reach the network.
const cases: [name: string, action: string, params: RpcParams][] = [
  ['unknownAction', 'bogus', {}],
  ['missingParam', 'getTVShow', {}],
  ['runScraper-missingId', 'runScraper', {}],
  ['runScraper-unknownId', 'runScraper', { scraperId: 999 }],
];

describe('legacy api.php — errors', () => {
  let legacy: LegacyServer;

  beforeAll(async () => {
    legacy = await startLegacyServer();
  });
  afterAll(() => legacy?.stop());
  beforeEach(() => legacy.reset());

  it.each(cases)('%s', async (name, action, params) => {
    await expectGolden(`api/errors/${name}`, { request: { action, ...params }, response: await legacy.rpc(action, params) });
  });
});
