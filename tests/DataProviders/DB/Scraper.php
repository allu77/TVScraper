<?php

declare(strict_types=1);

namespace DataProviders\DB;

use DataProviders\DB\ScrapedItem as ScrapedSeason;
use DataProviders\DB\ScrapedItem as File;

class Scraper extends GenericItem
{
    private array $scrapedItems = [];

    public function __construct(array $properties, array $scrapedItems = [])
    {
        parent::__construct($properties);

        foreach ($scrapedItems as $scrapedItem) {
            $this->attachScrapedItem($scrapedItem);
        }
        if (!array_key_exists('delay', $properties)) $this->setProperty('delay', 0);
        if (!array_key_exists('autoAdd', $properties)) $this->setProperty('autoAdd', 0);
        if (!array_key_exists('notify', $properties)) $this->setProperty('notify', 0);
    }

    public function attachScrapedItem(ScrapedItem $scrapedItem)
    {
        $scrapedItem->setScraper($this);
        $this->scrapedItems[] = $scrapedItem;
    }

    public function getScrapedItems(): array
    {
        return $this->scrapedItems;
    }
}
