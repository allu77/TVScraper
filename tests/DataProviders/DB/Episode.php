<?php

declare(strict_types=1);

namespace DataProviders\DB;

class Episode extends GenericItem
{

    public static function buildFromArray(array $episode): Episode
    {
        return new Episode($episode['properties']);
    }
}
