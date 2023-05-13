<?php

declare(strict_types=1);

namespace DataProviders\DB;

class DBContent
{
    private array $tvShows = [];


    public static function createFromJSON(string $jsonString): DBContent
    {
        $jsonArray = json_decode($jsonString, true);
        return self::createFromArray($jsonArray);
    }

    public static function createFromArray(array $dbContent): DBContent
    {
        $db = new DBContent();

        $db->tvShows = array_map(function ($tvShow) {
            return TVShow::buildFromArray($tvShow);
        }, $dbContent['tvShows']);

        $db->actualizeDates();
        $db->setBestFiles();

        return $db;
    }

    public function getTVShows(): array
    {
        return $this->tvShows;
    }

    public function getSeasons(): array
    {
        return array_merge(...array_map(function ($tvShow) {
            return $tvShow->getSeasons();
        }, $this->getTVShows()));
    }

    public function getTVShowScrapers(): array
    {
        return array_merge(...array_map(function ($tvShow) {
            return $tvShow->getTVShowScrapers();
        }, $this->getTVShows()));
    }

    public function getScrapedSeasons(): array
    {
        return array_merge(...array_map(function ($scraper) {
            return $scraper->getScrapedItems();
        }, $this->getTVShowScrapers()));
    }

    public function getEpisodes(): array
    {
        return array_merge(...array_map(function ($season) {
            return $season->getEpisodes();
        }, $this->getSeasons()));
    }

    public function getSeasonScrapers(): array
    {
        return array_merge(...array_map(function ($season) {
            return $season->getScrapers();
        }, $this->getSeasons()));
    }

    public function getFiles(): array
    {
        return array_merge(...array_map(function ($scraper) {
            return $scraper->getScrapedItems();
        }, $this->getSeasonScrapers()));
    }

    private function actualizeDates(): void
    {
        $now = time();
        foreach ($this->getTVShows() as $tvShow) $tvShow->actualizeDates($now);
        foreach ($this->getEpisodes() as $episode) $episode->actualizeDates($now);
        foreach ($this->getFiles() as $file) $file->actualizeDates($now);
    }

    private function setBestFiles(): void
    {
        foreach ($this->getFiles() as $file) {
            if ($file->isBestFile()) {
                $file->getParentItem()->setBestFile($file);
            }
        }
    }
}
