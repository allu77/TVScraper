<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once(__DIR__ . '/../modules/DB/TVShowScraperDB.php');

final class TVShowScraperDBSQLiteTest extends TestCase
{

    protected static string $dbName;
    protected static TVShowScraperDBSQLite $tvDB;
    protected static array $referenceTVShowIds = [];
    protected static array $referenceSeasonIds = [];


    protected function genericProvider(string $fileName): array
    {

        $file = fopen(__DIR__ . '/data-providers/' . $fileName, 'r');

        $data = [];
        while ($row = fgetcsv($file)) {
            $data[$row[0]] = [$row[0], $row[1], []];
            for ($i = 2; $i < count($row); $i += 2) {
                $data[$row[0]][2][$row[$i]] = $row[$i + 1];
            }
        }
        fclose($file);

        return $data;
    }

    public function tvShowsProvider(): array
    {
        return $this->genericProvider('tvshows-add.csv');
    }
    public function tvShowsProviderSet(): array
    {
        return $this->genericProvider('tvshows-set.csv');
    }
    public static function tvShowReferenceId(): string
    {
        return array_values(self::$referenceTVShowIds)[0];
    }

    public function seasonsProvider(): array
    {
        return $this->genericProvider('seasons-add.csv');
    }

    public function seasonsProviderSet(): array
    {
        return $this->genericProvider('seasons-set.csv');
    }
    public static function seasonReferenceId(): string
    {
        return array_values(self::$referenceSeasonIds)[0];
    }

    public static function setUpBeforeClass(): void
    {
        self::$dbName = __DIR__ . '/' . uniqid() . '.db';
    }

    public static function tearDownAfterClass(): void
    {
        if (file_exists(self::$dbName)) unlink(self::$dbName);
    }

    public function testCreateDBSQLite(): void
    {
        self::$tvDB = TVShowScraperDB::getInstance(TVShowScraperDB::DBTYPE_SQLITE, array('dbFileName' => self::$dbName));
        $this->assertInstanceOf(TVShowScraperDBSQLite::class, self::$tvDB);

        // self::$tvDB->setLogFile('test.log');
    }

    /**
     * @depends testCreateDBSQLite
     * @dataProvider tvShowsProvider
     */
    public function testAddTVShow(string $label, string $expect, array $params): void
    {
        $result = self::$tvDB->addTVShow($params);
        if ($expect == 'KO') $this->assertNotTrue($result, "addTVShow was expected to fail and it didn't");
        else if ($expect == 'OK') {
            $this->assertNotFalse($result, "addTVShow returned an error");
            $this->assertArrayHasKey('id', $result, "addTVShow returned an array without id");
            self::$referenceTVShowIds[$label] = $result['id'];
        }
    }

    /**
     * @depends testAddTVShow
     */
    public function testGetTVShow(): void
    {
        foreach (self::$referenceTVShowIds as $id) {
            $result = self::$tvDB->getTVShow($id);
            $this->assertNotFalse($result, "getTVShow returned an error");
            $this->assertArrayHasKey('episodesWithFile', $result, 'getTVShow missing expected key episodesWithFile');
            $this->assertSame(0, $result['episodesWithFile'], 'getTVShow was exptected to return 0 files at this stage');
        }
    }

    // TODO: getTVShow  -> once files are added - need to check episodesWithFile

    /**
     * @depends testAddTVShow
     */
    public function testGetAllTVShows(): void
    {
        $result = self::$tvDB->getAllTVShows();
        $this->assertEquals(count($result), count(self::$referenceTVShowIds));
    }

    /**
     * @depends testAddTVShow
     * @dataProvider tvShowsProviderSet
     */
    public function testSetTVShow(string $label, string $expect, array $params): void
    {
        $id = self::tvShowReferenceId();

        $result = self::$tvDB->setTVShow($id, $params);
        if ($expect === 'KO') {
            $this->assertFalse($result);
        } else if ($expect === 'OK') {
            $this->assertEquals($result['id'], $id);
            foreach ($params as $p => $v) {
                if ($v == '_REMOVE_') {
                    $this->assertFalse(array_key_exists($p, $result));
                } else {
                    $this->assertEquals($result[$p], $v);
                }
            }
        }
    }

    /**
     * @depends testCreateDBSQLite
     */
    public function testSetTVShowInvalidId(): void
    {
        $this->assertFalse(self::$tvDB->setTVShow('_INVALID', ['title' => 'test']));
    }

    /**
     * @depends testGetSeason
     */
    public function testRemoveTvShow(): void
    {
        $this->assertTrue(self::$tvDB->removeTVShow(self::tvShowReferenceId()));
        $this->assertEquals(count(self::$tvDB->getAllTVShows()), count(self::$referenceTVShowIds) - 1);
    }

    /**
     * @depends testSetTVShow
     * @dataProvider seasonsProvider
     */
    public function testAddSeason(string $label, string $expect, array $params): void
    {
        $result = self::$tvDB->addSeason(self::tvShowReferenceId(), $params);
        if ($expect == 'KO') $this->assertNotTrue($result);
        else if ($expect == 'OK') {
            $this->assertArrayHasKey('id', $result);
            self::$referenceSeasonIds[$label] = $result['id'];
        }
    }

    /**
     * @depends testAddSeason
     * @dataProvider seasonsProviderSet
     */
    public function testSetSeason(string $label, string $expect, array $params): void
    {
        $id = self::seasonReferenceId();
        $result = self::$tvDB->setSeason($id, $params);

        if ($expect === 'KO') {
            $this->assertFalse($result);
        } else if ($expect === 'OK') {
            $this->assertEquals($result['id'], $id);
            foreach ($params as $p => $v) {
                if ($v == '_REMOVE_') {
                    $this->assertFalse(array_key_exists($p, $result));
                } else {
                    $this->assertEquals($result[$p], $v);
                }
            }
        }
    }

    /*
	public function getSeason($id)
	public function getSeasonFromN($showId, $n)
	public function getTVShowSeasons($showId)
	public function getAllWatchedSeasons()
    */

    /**
     * @depends testAddSeason
     */
    public function testGetSeason(): void
    {
        $id = self::seasonReferenceId();
        $season = self::$tvDB->getSeason($id);
        $this->assertNotFalse($season, "getSeason failed on reference id $id");

        $seasonFromN = self::$tvDB->getSeasonFromN($season['tvshow'], $season['n']);
        $this->assertEqualsCanonicalizing($season, $seasonFromN, "getSeason and getSeasonFromN return different values");
    }
}
