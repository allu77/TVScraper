<?php

declare(strict_types=1);

namespace DataProviders\DB;

class DBContent
{
    private array $tvShows = [];


    public static function createFromJSON(string $jsonString): DBContent
    {
        $db = new DBContent();
        $json = json_decode($jsonString, true);

        $db->tvShows = array_map(function ($tvShow) {
            return TVShow::buildFromArray($tvShow);
        }, $json['tvShows']);

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
            return $season->episodes;
        }, $this->getSeasons()));
    }

    public function getSeasonScrapers(): array
    {
        return array_merge(...array_map(function ($season) {
            return $season->scrapers;
        }, $this->getSeasons()));
    }

    public function getFiles(): array
    {
        return array_merge(...array_map(function ($scraper) {
            return $scraper->getScrapedItems();
        }, $this->getSeasonScrapers()));
    }
}
