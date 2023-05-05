<?php

declare(strict_types=1);

namespace DataProviders\DB;

class Season extends GenericItem
{
    public array $episodes;
    public array $scrapers;

    public function __construct(array $properties, array $episodes = [], $scrapers = [])
    {
        parent::__construct($properties);

        foreach ($episodes as $episode) {
            $this->attachEpisode($episode);
        }

        foreach ($scrapers as $scraper) {
            $this->attachScraper($scraper);
        }
    }

    public function attachEpisode(Episode $episode)
    {
        $episode->setParentItem($this);
        $this->episodes[] = $episode;
    }

    public function attachScraper(SeasonScraper $scraper)
    {
        $scraper->setParentSeason($this);
        $this->scrapers[] = $scraper;
    }

    public function getEpisodeByN(mixed $n): Episode
    {
        foreach ($this->episodes as $episode) {
            if ($episode->getProperty('n') == $n) return $episode;
        }
        return null;
    }

    public static function buildFromArray(array $season): Season
    {
        return new Season(
            $season['properties'],
            array_map(function ($episode) {
                return Episode::buildFromArray($episode);
            }, $season['episodes']),
            array_map(function ($scraper) {
                return SeasonScraper::buildFromArray($scraper);
            }, $season['scrapers'])
        );
    }
}
