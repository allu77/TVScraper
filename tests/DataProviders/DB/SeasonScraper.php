<?php

declare(strict_types=1);

namespace DataProviders\DB;

class SeasonScraper extends Scraper
{
    private Season $season;


    public function __construct(array $properties, array $files = [])
    {
        parent::__construct($properties);

        foreach ($files as $file) {
            $this->attachFile($file);
        }
    }

    public function attachFile(File $file): void
    {
        $this->attachScrapedItem($file);
    }

    public static function buildFromArray(array $seasonScraper): SeasonScraper
    {
        return new SeasonScraper(
            $seasonScraper['properties'],
            array_key_exists('files', $seasonScraper) ?
                array_map(function ($file) {
                    return File::buildFromArray($file);
                }, $seasonScraper['files'])
                :
                []
        );
    }

    public function setParentSeason(Season $season)
    {
        $this->season = $season;
        $this->setParentItem($season);
        foreach ($this->getScrapedItems() as $file) {
            $file->setParentSeason($season);
        }
    }

    public function getSeason(): Season
    {
        return $this->season;
    }
}
