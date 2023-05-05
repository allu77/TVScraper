<?php

declare(strict_types=1);
require_once(__DIR__ . DIRECTORY_SEPARATOR . 'test-autoloader.php');
require_once(__DIR__ . '/../modules/DB/TVShowScraperDB.php'); // TODO: Implement autoloader for main project

use DataProviders\DB\DBContent as DBContent;
use DataProviders\DB\GenericItem as DBContentItem;

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

    protected static function assertAddResult(DBContentItem $added, array $result, array $ignoreKeys = ['id'], string $message = ''): void
    {
        self::assertIsArray($result, $message);
        self::assertArrayHasKey('id', $result, $message);
        foreach ($ignoreKeys as $ignoreKey) {
            unset($result[$ignoreKey]);
        }

        self::assertEqualsCanonicalizing($added->getProperties(), $result, $message);
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
            $this->assertAddResult($scraper, $result, ['id', 'tvShow']);
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
            $this->assertAddResult($scraper, $result, ['id', 'season']);
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
            $this->assertAddResult($file, $result, ['id', 'scraper']);
            $this->assertSame($file->getScraperId(), $result['scraper']);
            $file->setId($result['id']);
        }
    }
}
