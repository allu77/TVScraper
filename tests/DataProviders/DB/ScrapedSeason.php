<?php

declare(strict_types=1);

namespace DataProviders\DB;

class ScrapedSeason extends ScrapedItem
{
    public static function buildFromArray(array $scrapedSeason)
    {
        return new ScrapedSeason($scrapedSeason['properties']);
    }
}
