<?php

declare(strict_types=1);

namespace DataProviders\DB;

class File extends ScrapedItem
{
    private Season $season;
    private string $episodeN;
    private bool $isBestFile = false;
    private bool $isLatest = false;

    public static function buildFromArray(array $file): File
    {
        $fileObj = new File($file['properties']);
        $fileObj->episodeN = $file['episode'];
        if (array_key_exists('isBestFile', $file)) $fileObj->setBestFile(true);
        return $fileObj;
    }

    public function setBestFile(bool $isBestFile): void
    {
        $this->isBestFile = $isBestFile;
    }

    public function isBestFile(): bool
    {
        return $this->isBestFile;
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
