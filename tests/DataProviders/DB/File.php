<?php

declare(strict_types=1);

namespace DataProviders\DB;

class File extends ScrapedItem
{
    private Season $season;
    private string $episodeN;

    public static function buildFromArray(array $file): File
    {
        $fileObj = new File($file['properties']);
        $fileObj->episodeN = $file['episode'];
        return $fileObj;
    }

    public function setParentSeason(Season $season): void
    {
        $this->season = $season;
    }

    public function getSeason(): Season
    {
        return $this->season;
    }

    public function getParentItem(): Episode
    {
        return $this->getSeason()->getEpisodeByN($this->episodeN);
    }
}
