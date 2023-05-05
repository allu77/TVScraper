<?php

declare(strict_types=1);

namespace DataProviders\DB;

class TVShow extends GenericItem
{
    private array $seasons;
    private array $scrapers;

    public function __construct(array $properties, array $seasons = [], $scrapers = [])
    {
        parent::__construct($properties);

        foreach ($seasons as $season) {
            $this->attachSeason($season);
        }

        foreach ($scrapers as $scraper) {
            $this->attachScraper($scraper);
        }
    }

    private function attachSeason(Season $season)
    {
        $season->setParentItem($this);
        $this->seasons[] = $season;
    }

    private function attachScraper(TVShowScraper $scraper)
    {
        $scraper->setParentItem($this);
        $this->scrapers[] = $scraper;
    }

    public function getSeasons()
    {
        return $this->seasons;
    }

    public function getTVShowScrapers()
    {
        return $this->scrapers;
    }

    public static function buildFromArray(array $tvShow): TVShow
    {
        return new TVShow(
            $tvShow['properties'],
            array_map(function ($season) {
                return Season::buildFromArray($season);
            }, $tvShow['seasons']),
            array_map(function ($scraper) {
                return TVShowScraper::buildFromArray($scraper);
            }, $tvShow['scrapers'])
        );
    }
}
