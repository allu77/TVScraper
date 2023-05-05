<?php

declare(strict_types=1);

namespace DataProviders\DB;

require_once(__DIR__ . '/test-autoloader.php');


final class DataProviderDBTest extends \PHPUnit\Framework\TestCase
{
    static DBContent $dbContent;


    protected static function assertAllInstanceOf(string $expected, array $actuals, string $message = ''): void
    {
        foreach ($actuals as $actual) {
            self::assertInstanceOf($expected, $actual, $message);
        }
    }

    protected static function assertDBGet(int $expectedCount, string $expectedClass, mixed $actual, string $message = ''): void
    {
        self::assertIsArray($actual, $message);
        self::assertCount($expectedCount, $actual, $message);
        self::assertAllInstanceOf($expectedClass, $actual, $message);
    }

    protected static function assertParentInstanceOf(string $expected, GenericItem $actual, string $message = ''): void
    {
        self::assertInstanceOf($expected, $actual->getParentItem(), $message);
    }

    protected static function assertAllParentInstanceOf(string $expected, array $actuals, string $message = ''): void
    {
        foreach ($actuals as $actual) {
            self::assertParentInstanceOf($expected, $actual, $message);
        }
    }


    public static function setUpBeforeClass(): void
    {
        self::$dbContent = DBContent::createFromJSON(file_get_contents(__DIR__ . '/DataProviders/DB/test.json'));
    }

    public function testGetTVShows(): void
    {
        $this->assertDBGet(1, 'DataProviders\DB\TVShow', self::$dbContent->getTVShows());
    }

    public function testGetSeasons(): void
    {
        $seasons = self::$dbContent->getSeasons();
        $this->assertDBGet(1, 'DataProviders\DB\Season', $seasons);
        $this->assertAllParentInstanceOf('DataProviders\DB\TVShow', $seasons);
    }

    public function testGetEpisodes(): void
    {
        $episodes = self::$dbContent->getEpisodes();
        $this->assertDBGet(1, 'DataProviders\DB\Episode', $episodes);
        $this->assertAllParentInstanceOf('DataProviders\DB\Season', $episodes);
    }

    public function testGetSeasonScrapers(): void
    {
        $scrapers = self::$dbContent->getSeasonScrapers();
        $this->assertDBGet(1, 'DataProviders\DB\SeasonScraper', $scrapers);
        $this->assertAllParentInstanceOf('DataProviders\DB\Season', $scrapers);
    }

    public function testGetFiles(): void
    {
        $files = self::$dbContent->getFiles();
        $this->assertDBGet(1, 'DataProviders\DB\File', $files);
        $this->assertAllParentInstanceOf('DataProviders\DB\Episode', $files);
    }

    public function testGetTVShowScrapers(): void
    {
        $scrapers = self::$dbContent->getTVShowScrapers();
        $this->assertDBGet(1, 'DataProviders\DB\TVShowScraper', $scrapers);
        $this->assertAllParentInstanceOf('DataProviders\DB\TVShow', $scrapers);
    }

    public function testGetScrapedSeasons(): void
    {
        $scrapedSeasons = self::$dbContent->getScrapedSeasons();
        $this->assertDBGet(1, 'DataProviders\DB\ScrapedSeason', $scrapedSeasons);
        $this->assertAllParentInstanceOf('DataProviders\DB\TVShow', $scrapedSeasons);
    }
}
