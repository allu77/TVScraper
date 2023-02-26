<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once(__DIR__ . '/../modules/DB/TVShowScraperDB.php');

final class TVShowScraperDBSQLiteTest extends TestCase
{

    protected static string $dbName;
    protected static TVShowScraperDB $tvDB;
    protected static array $referenceTVShowIds = [];

    protected function genericProvider($fileName)
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

    public function tvShowsProvider()
    {
        return $this->genericProvider('tvshows-add.csv');
    }
    public function tvShowsProviderSet()
    {
        return $this->genericProvider('tvshows-set.csv');
    }
    public static function tvShowRefereceId()
    {
        return array_values(self::$referenceTVShowIds)[0];
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
    }



    /**
     * @depends testCreateDBSQLite
     * @dataProvider tvShowsProvider
     */
    public function testAddTVShow($label, $expect, array $params): void
    {
        $result = self::$tvDB->addTVShow($params);
        if ($expect == 'KO') $this->assertNotTrue($result);
        else if ($expect == 'OK') {
            $this->assertArrayHasKey('id', $result);
            self::$referenceTVShowIds[$label] = $result['id'];
        }
    }

    /**
     * @depends testAddTVShow
     */
    public function testGetTVShow()
    {
        foreach (self::$referenceTVShowIds as $id) {
            $result = self::$tvDB->getTVShow($id);
            $this->assertNotFalse($result);
        }
    }

    /**
     * @depends testAddTVShow
     */
    public function testGetAllTVShows()
    {
        $result = self::$tvDB->getAllTVShows();
        $this->assertEquals(count($result), count(self::$referenceTVShowIds));
    }

    /**
     * @depends testAddTVShow
     * @dataProvider tvShowsProviderSet
     */
    public function testSetTVShow($label, $expect, array $params)
    {
        $id = self::tvShowRefereceId();

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
    public function testSetTVShowInvalidId()
    {
        $this->assertFalse(self::$tvDB->setTVShow('_INVALID', ['title' => 'test']));
    }

    /**
     * @depends testSetTVShow
     */
    public function testRemoveTvShow()
    {
        $this->assertTrue(self::$tvDB->removeTVShow(self::tvShowRefereceId()));
        $this->assertEquals(count(self::$tvDB->getAllTVShows()), count(self::$referenceTVShowIds) - 1);
    }
}
