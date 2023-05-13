<?php

declare(strict_types=1);
require_once(__DIR__ . DIRECTORY_SEPARATOR . 'test-autoloader.php');
require_once(__DIR__ . '/../modules/DB/TVShowScraperDB.php'); // TODO: Implement autoloader for main project

use DataProviders\DB\DBContent as DBContent;
use DataProviders\DB\GenericItem;
use DataProviders\DB\TVShow as TVShow;

final class TVShowScraperDBSQLiteTest extends PHPUnit\Framework\TestCase
{

    protected static string $dbName;
    protected static TVShowScraperDBSQLite $tvDB;

    protected static DataProviders\DB\DBContent $dbContent;

    public static function setUpBeforeClass(): void
    {
        self::$dbName = __DIR__ . '/' . uniqid() . '.db';
        self::$dbContent = DBContent::createFromJSON(file_get_contents(__DIR__ . '/DataProviders/DB/test.json'));
    }

    public static function tearDownAfterClass(): void
    {
        // if (file_exists(self::$dbName)) unlink(self::$dbName);
    }


    public static function assertGetResult(GenericItem $expected, mixed $result, array $ignoreKeys = [], string $message = ''): void
    {
        self::assertIsArray($result, $message ?: "Unexpected result returned");
        $expectedProperties = $expected->getProperties();
        foreach ($expectedProperties as $key => $value) {
            self::assertArrayHasKey($key, $result, $message ?: "Result missing expected key: $key");
            self::assertEquals($value, $result[$key], $message ?: "Result value for proerty $key does not match expected one");
        }
        foreach ($result as $key => $value) {
            if (in_array($key, $ignoreKeys)) continue;
            if ($key == 'id') self::assertEquals($expected->getId(), $value, $message ?: "Result id doesn't match expected one");
            else self::assertArrayHasKey($key, $expectedProperties, $message ?: "Result contains unexpected key: $key");
        }
    }

    protected static function assertAddResult(GenericItem $added, mixed $result, array $ignoreKeys = [], string $message = ''): void
    {
        self::assertGetResult($added, $result, array_merge(['id'], $ignoreKeys), $message);
        self::assertArrayHasKey('id', $result, $message);
    }

    public function testCreateDBSQLite(): void
    {
        self::$tvDB = TVShowScraperDB::getInstance(TVShowScraperDB::DBTYPE_SQLITE, array('dbFileName' => self::$dbName));
        $this->assertInstanceOf(TVShowScraperDBSQLite::class, self::$tvDB);

        self::$tvDB->setLogFile('test.log');
    }

    /**
     * @depends testCreateDBSQLite
     */
    public function testAddTVShow(): void
    {
        foreach (self::$dbContent->getTVShows() as $tvShow) {
            $result = self::$tvDB->addTVShow($tvShow->getProperties());
            $this->assertAddResult($tvShow, $result);
            $tvShow->setId($result['id']);
        }
    }


    /**
     * @depends testAddTVShow
     */

    /*
    public function testSetTVShow(): void
    {
        $tvShow = self::$dbContent->getTVShows()[0];

        $result = self::$tvDB->setTVShow($tvShow->getId(), ['title' => 'New Title']);
        $this->assertSame('New Title', $result['title']);

        $result = self::$tvDB->setTVShow($tvShow->getId(), ['alternateTitle' => '_REMOVE_']);
        $this->assertArrayNotHasKey('alternateTitle', $result);

        $result = self::$tvDB->setTVShow($tvShow->getId(), ['title' => '_REMOVE_']);
        $this->assertFalse($result);
    }
    */

    /**
     * @depends testAddTVShow
     */
    public function testAddSeason(): void
    {
        foreach (self::$dbContent->getSeasons() as $season) {
            $result = self::$tvDB->addSeason($season->getParentId(), $season->getProperties());
            $this->assertAddResult($season, $result);
            $season->setId($result['id']);
        }
    }

    /**
     * @depends testAddTVShow
     */
    public function testAddTVShowScraper(): void
    {
        foreach (self::$dbContent->getTVShowScrapers() as $scraper) {
            $result = self::$tvDB->addScraper($scraper->getParentId(), 'tvShow', $scraper->getProperties());
            $this->assertAddResult($scraper, $result, ['tvShow']);
            $this->assertArrayHasKey('tvShow', $result);
            $this->assertSame($scraper->getParentId(), $result['tvShow']);
            $scraper->setId($result['id']);
        }
    }

    /**
     * @depends testAddTVShowScraper
     */
    public function testAddScrapedSeason(): void
    {
        foreach (self::$dbContent->getScrapedSeasons() as $scrapedSeason) {
            $result = self::$tvDB->addScrapedSeason($scrapedSeason->getScraperId(), $scrapedSeason->getProperties());
            $this->assertAddResult($scrapedSeason, $result);
            $scrapedSeason->setId($result['id']);
        }
    }

    /**
     * @depends testAddSeason
     */
    public function testAddEpisodes(): void
    {
        foreach (self::$dbContent->getEpisodes() as $episode) {
            $result = self::$tvDB->addEpisode($episode->getParentId(), $episode->getProperties());
            $this->assertAddResult($episode, $result);
            $episode->setId($result['id']);
        }
    }

    /**
     * @depends testAddSeason
     */
    public function testAddSeasonScrapers(): void
    {
        foreach (self::$dbContent->getSeasonScrapers() as $scraper) {
            $result = self::$tvDB->addScraper($scraper->getParentId(), 'season', $scraper->getProperties());
            $this->assertAddResult($scraper, $result, ['season']);
            $scraper->setId($result['id']);
        }
    }

    /** 
     * @depends testAddSeasonScrapers
     * @depends testAddEpisodes
     */
    public function testAddFiles(): void
    {
        foreach (self::$dbContent->getFiles() as $file) {
            $result = self::$tvDB->addFile($file->getParentId(), array_merge($file->getProperties(), ['scraper' => $file->getScraperId()]));
            $this->assertAddResult($file, $result, ['scraper']);
            $this->assertSame($file->getScraperId(), $result['scraper']);
            $file->setId($result['id']);
        }
    }

    public static function assertArrayKeyValueIfNotNull(string $key, mixed $value, array $result, string $message = ''): void
    {
        $message = $message ? $message . "\n" : "";
        if ($value === null) {
            self::assertArrayNotHasKey($key, $result, $message . "Array contains unexpected key: $key");
        } else {
            self::assertArrayHasKey($key, $result, $message . "Array missing expected key: $key");
            self::assertEquals($value, $result[$key], $message . "Array value for key $key does not match expected one");
        }
    }

    public static function assertGetTVShow(TVShow $expected, array $result, string $message = ''): void
    {
        $message = ($message ? $message . "\n" : "") . "Failed asserting TVShow " . $expected->getId() . " - " . $expected->getProperty('title');

        self::assertGetResult($expected, $result, [
            'missingCount',
            'lastAirDate',
            'lastAiredEpisodeIndex',
            'nextAirDate',
            'firstMissingIndex',
            'latestMissingIndex',
            'lastEpisodeIndex',
            'airedEpisodesCount',
            'lastPubDate',
            'episodesWithFile',
            'pendingScrapedSeasons',
        ], $message);

        self::assertArrayKeyValueIfNotNull('missingCount', $expected->getMissingCount(), $result, $message);
        self::assertArrayKeyValueIfNotNull('lastAirDate', $expected->getLastAirDate(), $result, $message);
        self::assertArrayKeyValueIfNotNull('lastAiredEpisodeIndex', $expected->getLastAiredEpisodeIndex(), $result, $message);
        self::assertArrayKeyValueIfNotNull('nextAirDate', $expected->getNextAirDate(), $result, $message);
        self::assertArrayKeyValueIfNotNull('firstMissingIndex', $expected->getFirstMissingIndex(), $result, $message);
        self::assertArrayKeyValueIfNotNull('latestMissingIndex', $expected->getLatestMissingIndex(), $result, $message);
        self::assertArrayKeyValueIfNotNull('lastEpisodeIndex', $expected->getLastEpisodeIndex(), $result, $message);
        self::assertArrayKeyValueIfNotNull('airedEpisodesCount', $expected->getAiredEpisodesCount(), $result, $message);
        self::assertArrayKeyValueIfNotNull('lastPubDate', $expected->getLastPubDate(), $result, $message);
        self::assertArrayKeyValueIfNotNull('episodesWithFile', $expected->getEpisodesWithFile(), $result, $message);

        self::assertArrayKeyValueIfNotNull('pendingScrapedSeasons', $expected->getPendingScrapedSeasons(), $result, $message);
    }

    /**
     * @depends testAddFiles
     */
    public function testGetTVShow(): void
    {
        foreach (self::$dbContent->getTVShows() as $tvShow) {
            $this->assertGetTVShow($tvShow, self::$tvDB->getTVShow($tvShow->getId()));
        }
    }

    /**
     * @depends testAddFiles
     */
    public function testGetAllTVShows(): void
    {
        $result = self::$tvDB->getAllTVShows();
        $this->assertIsArray($result);
        $this->assertSameSize(self::$dbContent->getTVShows(), $result);
    }


    /**
     * @depends testAddFiles
     */
    public function testGetSeason(): void
    {
        foreach (self::$dbContent->getSeasons() as $season) {
            $result = self::$tvDB->getSeason($season->getId());
            $this->assertGetResult($season, $result, ['tvshow']);
            $this->assertArrayHasKey('tvshow', $result);
            $this->assertSame($season->getParentId(), $result['tvshow']);
        }
    }

    /** 
     * @depends testAddFiles
     */
    public function testGetSeasonFromN(): void
    {
        foreach (self::$dbContent->getSeasons() as $season) {
            $result = self::$tvDB->getSeasonFromN($season->getParentId(), $season->getProperty('n'));
            $this->assertIsArray($result);
            $this->assertSame($season->getId(), $result['id']);
        }
    }

    /**
     * @depends testAddFiles
     */
    public function testGetTVShowSeasons(): void
    {
        foreach (self::$dbContent->getTVShows() as $tvShow) {
            $seasons = $tvShow->getSeasons();
            $seasonIds = array_map(function ($season) {
                return $season->getId();
            }, $seasons);
            $result = self::$tvDB->getTVShowSeasons($tvShow->getId());
            $this->assertIsArray($result);
            $this->assertSameSize($seasons, $result);
            foreach ($result as $seasonResult) {
                $this->assertContains($seasonResult['id'], $seasonIds);
            }
        }
    }

    /**
     * @depends testAddFiles
     */
    public function testGetAllWatchedSeasons(): void
    {
        $watchedSeasonsIds = array_map(
            function ($season) {
                return $season->getId();
            },
            array_filter(self::$dbContent->getSeasons(), function (
                $season
            ) {
                return $season->getProperty('status') == 'watched';
            })
        );
        $result = self::$tvDB->getAllWatchedSeasons();
        $this->assertIsArray($result);
        $this->assertSameSize($watchedSeasonsIds, $result);
        foreach ($result as $seasonResult) {
            $this->assertContains($seasonResult['id'], $watchedSeasonsIds);
        }
    }


    /**
     * @depends testAddFiles
     */

    public function testGetEpisode(): void
    {
        foreach (self::$dbContent->getEpisodes() as $episode) {
            $result = self::$tvDB->getEpisode($episode->getId());
            $this->assertGetResult($episode, $result, ['season']);
            $this->assertArrayHasKey('season', $result);
            $this->assertSame($episode->getParentId(), $result['season']);
        }
    }

    /**
     * @depends testAddFiles
     */
    public function testGetFile(): void
    {
        foreach (self::$dbContent->getFiles() as $file) {
            $result = self::$tvDB->getFile($file->getId());
            $this->assertGetResult($file, $result, ['episode', 'scraper', 'discard']);
            $this->assertArrayHasKey('episode', $result);
            $this->assertSame($file->getParentId(), $result['episode']);
            $this->assertArrayHasKey('scraper', $result);
            $this->assertSame($file->getScraperId(), $result['scraper']);
            // TODO: What to do with discard?
        }
    }



    /**
     * To be executed after testGetTVShow to avoid messing with stats.
     * - Create a new TVShow
     * - Create a new Season
     * - Add Episodes
     * - Add SeasonScrapers with different properties
     * - Add Files
     * - Remove Files
     * - Change SeasonScrapers properties
     * - Test results
     *      
     * @depends testGetTVShow
     */
    public function testBestFileSelection(): void
    {
        $db = self::$tvDB;

        // Preparing the environment

        $tvShow = $db->addTVShow([
            'title' => 'Best File Selection',
            'lang'  => 'eng',
            'nativeLang' => 'eng',
            'res' => 'any'
        ]);
        $this->assertNotFalse($tvShow);

        $season = $db->addSeason($tvShow['id'], [
            'n' => 1,
            'status' => 'watched'
        ]);
        $this->assertNotFalse($season);

        $episode = $db->addEpisode($season['id'], [
            'n' => 1
        ]);
        $this->assertNotFalse($episode);

        // Creating Scrapers

        $scraper_1 = $db->addScraper($season['id'], 'season', ['uri' => 'scraper-1', 'source' => 'txt']);
        $scraper_2 = $db->addScraper($season['id'], 'season', ['uri' => 'scraper-2', 'source' => 'txt']);

        $this->assertNotFalse($scraper_1);
        $this->assertNotFalse($scraper_2);


        /*
        scraper_1: file_1
        scraper_2:   |      file_2
                     |        |
            time: ---+--------+------now
                    -10      -8
        
        Excpected best: file_1
        */

        $file_1 = $db->addFile($episode['id'], ['scraper' => $scraper_1['id'], 'uri' => 'file-1', 'pubDate' => time() - 10000, 'type' => 'ed2k']);
        $file_2 = $db->addFile($episode['id'], ['scraper' => $scraper_2['id'], 'uri' => 'file-1', 'pubDate' => time() - 8000, 'type' => 'ed2k']);
        $this->assertNotFalse($file_1);
        $this->assertNotFalse($file_2);

        // Exepecting first file as best, because it was published first
        $bestFile = $db->getBestFileForEpisode($episode['id']);
        $this->assertNotFalse($bestFile);
        $this->assertEquals($file_1['id'], $bestFile['id']);

        /*
        Adding a new version of the file from scraper-1, this should be come the new best (new version of previous best)

        scraper_1: file_1            file_3
        scraper_2:   |      file_2     |
                     |        |        |
            time: ---+--------+--------+----now
                    -10      -8       -6  
        
        Excpected best: file_3
        */

        $file_3 = $db->addFile($episode['id'], ['scraper' => $scraper_1['id'], 'uri' => 'file-new', 'pubDate' => time() - 6000, 'type' => 'ed2k']);
        $this->assertNotFalse($file_3);
        $bestFile = $db->getBestFileForEpisode($episode['id']);
        $this->assertNotFalse($bestFile);
        $this->assertEquals($file_3['id'], $bestFile['id']);

        /*
        Moving file_2 to earlier date, this should be come the new best 

        scraper_1:          file_1    file_3
        scraper_2:  file_2    |        |
                     |        |        |
            time: ---+--------+--------+----now
                    -12      -10      -6
        
        Excpected best: file_2
        */
        $this->assertNotFalse($db->setFile($file_2['id'], ['pubDate' => time() - 12000]));
        $bestFile = $db->getBestFileForEpisode($episode['id']);
        $this->assertNotFalse($bestFile);
        $this->assertEquals($file_2['id'], $bestFile['id']);

        /* Now giving preference 100 to scraper_2 
        Expected best: file_3 (no precedence is better than a set precedence)
        */
        $result = $db->setScraper($scraper_2['id'], ['preference' =>  100]);
        $this->assertNotFalse($result);
        $bestFile = $db->getBestFileForEpisode($episode['id']);
        $this->assertEquals($file_3['id'], $bestFile['id']);

        /* Now giving preference 200 to scraper_1
        Expected best: file_2 (low precedence is better than a high precedence)
        */
        $result = $db->setScraper($scraper_1['id'], ['preference' =>  200]);
        $this->assertNotFalse($result);
        $bestFile = $db->getBestFileForEpisode($episode['id']);
        $this->assertEquals($file_2['id'], $bestFile['id']);

        /* Now Adding delay to scraper_2, moving file_2 to afer file_1
        Also setting same preference fo scraper_2 as scraper_1

        scraper_1: file_1             file_3
        scraper_2:   |      file_2     |
                     |        |        |
            time: ---+--------+--------+----now
                    -10      -8       -6
        
        Excpected best: file_3
        */
        $result = $db->setScraper($scraper_2['id'], ['delay' =>  4000, 'preference' => 200]);
        $this->assertNotFalse($result);
        $bestFile = $db->getBestFileForEpisode($episode['id']);
        $this->assertEquals($file_3['id'], $bestFile['id']);
    }
}
