-- Deterministic seed for the legacy golden-master tests.
-- Timestamps are either far in the past (2000) or far in the future (2100) so that
-- the time-dependent views (strftime('now')) give the same results whenever tests run.
--   946684800  = 2000-01-01   947289600 = 2000-01-08   4102444800 = 2100-01-01

INSERT INTO tvshows (id, title, alternateTitle, lang, nativeLang, res) VALUES
  (1, 'Breaking Bad', 'Breaking Bad (US)', 'ita', 'eng', '720p'),
  (2, 'Dark', NULL, 'ita', 'deu', 'any'),
  (3, 'Empty Show', NULL, 'eng', 'eng', 'any');

INSERT INTO seasons (id, tvshow, n, status) VALUES
  (1, 1, 1, 'complete'),
  (2, 1, 2, 'watched'),
  (3, 2, 1, 'watched'),
  (4, 2, 2, 'ignored');

INSERT INTO episodes (id, season, n, airDate, title, bestFile, bestSticky) VALUES
  (1, 2, 1, 946684800,  'Seven Thirty-Seven', NULL, NULL),
  (2, 2, 2, 947289600,  'Grilled',            4,    1),
  (3, 2, 3, 4102444800, 'Bit by a Dead Bee',  NULL, NULL),
  (4, 2, 4, NULL,       NULL,                 NULL, NULL),
  (5, 3, 1, 946684800,  'Secrets',            NULL, NULL),
  (6, 3, 2, 947289600,  'Lies',               NULL, NULL),
  (7, 1, 1, 946684800,  'Pilot',              NULL, NULL);

INSERT INTO scrapers (id, preference, delay, uri, source, autoAdd, notify) VALUES
  (1, NULL, 0,    'https://ddunlimited.net/viewforum.php?f=1', 'DDU',       1, 1),
  (2, 1,    0,    'https://ddunlimited.net/viewtopic.php?t=2', 'DDU',       0, 0),
  (3, NULL, 3600, 'http://example.invalid/feed.rss',           'RSS',       0, 0),
  (4, NULL, 0,    'http://it.wikipedia.org/wiki/Dark',         'wikipedia', 0, 0),
  (5, NULL, 0,    'http://api.tvmaze.com/shows/1/episodes',    'tvmaze',    0, 1),
  (6, NULL, 0,    'http://example.invalid/list.txt',           'txt',       0, 0),
  (7, NULL, 0,    'http://services.tvrage.com/x?sid=1',        'tvrage',    0, 0),
  (8, 2,    0,    'https://ddunlimited.net/viewtopic.php?t=8', 'DDU',       0, 0);

INSERT INTO tvshowScrapers (tvshow, scraper) VALUES
  (1, 1),
  (2, 5);

INSERT INTO seasonScrapers (season, scraper) VALUES
  (2, 2), (2, 3), (2, 7),
  (3, 4), (3, 8),
  (1, 6);

INSERT INTO files (id, episode, scraper, uri, pubDate, type, discard) VALUES
  (1, 1, 2, 'ed2k://|file|Breaking.Bad.2x01.720p.mkv|100|AAAA|/', 946771200,  'ed2k',    0),
  (2, 1, 3, 'magnet:?xt=urn:btih:bb2x01',                          946684800,  'torrent', 0),
  (3, 2, 2, 'ed2k://|file|Breaking.Bad.2x02.720p.mkv|100|BBBB|/', 947376000,  'ed2k',    1),
  (4, 2, 3, 'magnet:?xt=urn:btih:bb2x02a',                         947376000,  'torrent', 0),
  (5, 2, 3, 'magnet:?xt=urn:btih:bb2x02b',                         947462400,  'torrent', 0),
  (6, 3, 2, 'ed2k://|file|Breaking.Bad.2x03.720p.mkv|100|CCCC|/', 4102444800, 'ed2k',    0),
  (7, 5, 8, 'ed2k://|file|Dark.1x01.mkv|100|DDDD|/',               946771200,  'ed2k',    0),
  (8, 7, 6, 'ed2k://|file|Breaking.Bad.1x01.mkv|100|EEEE|/',       946771200,  'ed2k',    0);

INSERT INTO scrapedSeasons (id, scraper, n, uri, hide, tbn) VALUES
  (1, 1, 3, 'https://ddunlimited.net/viewtopic.php?t=30', 0, 1),
  (2, 1, 1, 'https://ddunlimited.net/viewtopic.php?t=10', 1, NULL),
  (3, 5, 3, 'http://api.tvmaze.com/shows/1/episodes',     0, NULL);
