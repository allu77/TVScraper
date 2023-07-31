CREATE TABLE `episodes` (
        `id`    INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT UNIQUE,
        `season`      INTEGER NOT NULL,
        `n`     INTEGER NOT NULL,
        `airDate`       INTEGER,
        `title` TEXT,
        `bestFile`      INTEGER,
        `bestSticky`    INTEGER,
        FOREIGN KEY (season) REFERENCES seasons(id) ON DELETE CASCADE
);
CREATE TABLE `files` (
        `id`    INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT UNIQUE,
        `episode`     INTEGER NOT NULL,
        `scraper`     INTEGER NOT NULL,
        `uri`   TEXT NOT NULL,
        `pubDate`       INTEGER NOT NULL,
        `type`  TEXT NOT NULL,
        `discard`       INTEGER NOT NULL DEFAULT 0,
        FOREIGN KEY(episode) REFERENCES episodes(id) ON DELETE CASCADE,
        FOREIGN KEY(scraper) REFERENCES scrapers(id) ON DELETE RESTRICT
);
CREATE TABLE `scrapedSeasons` (
        `id`    INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT UNIQUE,
        `scraper`      INTEGER NOT NULL,
        `n`     INTEGER NOT NULL,
		`uri`     TEXT NOT NULL,
		hide INTEGER NOT NULL DEFAULT 0,
		tbn INTEGER, 
        FOREIGN KEY(scraper) REFERENCES scrapers(id) ON DELETE CASCADE
);
CREATE TABLE `scrapers` (
        `id`    INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT UNIQUE,
        `preference`    INTEGER,
        `delay` INTEGER NOT NULL DEFAULT 0,
        `uri`   TEXT NOT NULL,
        `source`        TEXT NOT NULL,
        `autoAdd`       INTEGER NOT NULL DEFAULT 0,
        `notify`        INTEGER NOT NULL DEFAULT 0
);
CREATE TABLE `seasonScrapers` (
        `season`      INTEGER NOT NULL,
        `scraper`     INTEGER NOT NULL,
        PRIMARY KEY(`season`,`scraper`),
        FOREIGN KEY(season) REFERENCES seasons(id) ON DELETE CASCADE,
        FOREIGN KEY(scraper) REFERENCES scrapers(id) ON DELETE CASCADE
);
CREATE TABLE `seasons` (
        `id`    INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT UNIQUE,
        `tvshow`      INTEGER NOT NULL,
        `n`     INTEGER NOT NULL, status text not null default "watched",
        FOREIGN KEY(tvshow) REFERENCES tvshows(id) ON DELETE CASCADE
);
CREATE TABLE `tvshowScrapers` (
        `tvshow`      INTEGER NOT NULL,
        `scraper`     INTEGER NOT NULL,
        PRIMARY KEY(`tvshow`,`scraper`),
        FOREIGN KEY(tvshow) REFERENCES tvshows(id) ON DELETE CASCADE,
        FOREIGN KEY(scraper) REFERENCES scrapers(id) ON DELETE CASCADE
);
CREATE TABLE "tvshows" (
        `id`    INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
        `title` TEXT NOT NULL,
        `alternateTitle`        TEXT,
        `lang`  TEXT NOT NULL,
        `nativeLang`    TEXT NOT NULL,
        `res`   TEXT NOT NULL
);
CREATE VIEW pastEpisodes as select * from episodes where airDate < strftime('%s', 'now');
CREATE VIEW futureEpisodes as select * from episodes where airDate >= strftime('%s', 'now');
CREATE VIEW missingEpisodes as
SELECT episodes.*
FROM episodes
LEFT OUTER JOIN files
ON episodes.id = files.episode
AND files.discard != 1
WHERE files.id is null
AND episodes.airDate is not null;
CREATE VIEW seasonStatsTotal as
select seasons.id as season, max(episodes.n) as lastEpisodeIndex, max(files.pubDate) as lastPubDate
from seasons
join episodes
on seasons.id = episodes.season
left outer join files
on episodes.id = files.episode
where seasons.status = "watched"
and episodes.n <> ""
group by seasons.id;
CREATE VIEW seasonStatsMissing as
select seasons.id as season,
COUNT(episodes.n) as missingCount,
MIN(episodes.n) as firstMissingIndex,
MAX(episodes.n) as latestMissingIndex
from seasons
join episodes
on seasons.id = episodes.season
left join files
on episodes.id = files.episode
where seasons.status = "watched"
and episodes.airDate < ((strftime('%s', 'now') / 86400) - 1) * 86400
and files.id is null
group by seasons.id;
CREATE VIEW seasonStatsPast as
select seasons.id as season,
MAX(episodes.airDate) as lastAirDate,
MAX(episodes.n) as lastAiredEpisodeIndex,
COUNT(episodes.n) as airedEpisodesCount
from seasons
join episodes
on seasons.id = episodes.season
where seasons.status = "watched"
and episodes.airDate < ((strftime('%s', 'now') / 86400) - 1) * 86400
group by seasons.id;
CREATE VIEW pendingScrapedSeasons as
select tvShowScrapers.tvShow, count(*) as pendingScrapedSeasons
from tvShowScrapers
join scrapedSeasons
on tvShowScrapers.scraper = scrapedSeasons.scraper
and hide = 0
group by tvShowScrapers.tvShow;
CREATE VIEW seasonStatsFiles AS
SELECT seasons.id, COUNT(*) as episodesWithFile
FROM seasons
JOIN episodes
ON seasons.id = episodes.season
WHERE seasons.status = "watched"
AND episodes.id IN(
	SELECT episode	
	FROM files
	WHERE discard <> 1 )
GROUP BY seasons.id;
CREATE VIEW seasonStatsFuture AS
SELECT seasons.id AS season,
MIN(episodes.airDate) AS nextAirDate
FROM seasons
JOIN episodes
ON seasons.id = episodes.season
WHERE seasons.status = "watched"
AND episodes.airDate >= ((strftime('%s', 'now') / 86400) - 1) * 86400
GROUP BY seasons.id;
CREATE VIEW tvShowsWithStats AS
select tvShows.id, tvShows.title, tvShows.alternateTitle, tvShows.lang, tvShows.nativeLang, tvshows.res,
max(seasonStatsPast.lastAirDate) as lastAirDate, max(seasonStatsPast.lastAiredEpisodeIndex) as lastAiredEpisodeIndex, sum(seasonStatsPast.airedEpisodesCount) as airedEpisodesCount,
sum(seasonStatsMissing.missingCount) as missingCount, min(seasonStatsMissing.firstMissingIndex) as firstMissingIndex, max(seasonStatsMissing.latestMissingIndex) as latestMissingIndex,
max(seasonStatsTotal.lastEpisodeIndex) as lastEpisodeIndex, max(seasonStatsTotal.lastPubDate) as lastPubDate,
max(pendingScrapedSeasons.pendingScrapedSeasons) as pendingScrapedSeasons,
sum(seasonStatsFiles.episodesWithFile) as episodesWithFile,
min(seasonStatsFuture.nextAirDate) as nextAirDate
from tvshows
left join seasons
on tvshows.id = seasons.tvshow
left join seasonStatsPast
on seasons.id = seasonStatsPast.season
left join seasonStatsMissing
on seasons.id = seasonStatsMissing.season
left join seasonStatsTotal
on seasons.id = seasonStatsTotal.season
left join pendingScrapedSeasons
on tvShows.id = pendingScrapedSeasons.tvShow
left join seasonStatsFiles
on seasons.id = seasonStatsFiles.id
left join seasonStatsFuture
on seasons.id = seasonStatsFuture.season
group by tvShows.id, tvShows.title, tvShows.alternateTitle, tvShows.lang, tvShows.nativeLang, tvshows.res;
CREATE VIEW bestScrapersFiles AS
SELECT outer.id, outer.episode, pubDate + delay as pubDate, outer.scraper
FROM files as outer
JOIN scrapers
ON outer.scraper = scrapers.id
WHERE scraper in (
SELECT scrapers.id
FROM files AS inner
JOIN scrapers
ON scraper = scrapers.id
WHERE inner.episode = outer.episode
AND pubDate < strftime('%s', 'now') - delay
AND inner.discard = 0
GROUP BY scrapers.id, preference
ORDER BY IFNULL(preference, -9999), min(inner.pubDate + scrapers.delay), scrapers.id
LIMIT 1
)
AND outer.discard = 0
;
CREATE VIEW scrapersWithParents AS
SELECT scrapers.*, seasonScrapers.season, tvShowScrapers.tvShow
FROM scrapers
LEFT JOIN seasonScrapers
ON scrapers.id = seasonScrapers.scraper
LEFT JOIN tvShowScrapers
ON scrapers.id = tvShowScrapers.scraper
;

