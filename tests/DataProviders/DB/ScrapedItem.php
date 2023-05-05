<?php

declare(strict_types=1);

namespace DataProviders\DB;

class ScrapedItem extends GenericItem
{
    private Scraper $scraper;

    public function getScraperId(): string
    {
        return $this->getScraper()->getId();
    }

    public function getScraper(): Scraper
    {
        return $this->scraper;
    }

    public function setScraper(Scraper $scraper): void
    {
        $this->scraper = $scraper;
    }

    public function getParentItem(): GenericItem
    {
        return $this->scraper->getParentItem();
    }
}
