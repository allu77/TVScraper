<?php

declare(strict_types=1);

namespace DataProviders\DB;

class TVShowScraper extends Scraper
{
    public static function buildFromArray(array $tvShowScraper): TVShowScraper
    {
        return new TVShowScraper(
            $tvShowScraper['properties'],
            array_map(function ($scrapedSeason) {
                return ScrapedSeason::buildFromArray($scrapedSeason);
            }, $tvShowScraper['scrapedSeasons'])
        );
    }
}
