<?php

declare(strict_types=1);

namespace DataProviders\DB;

class Episode extends GenericItem
{
    private ?File $bestFile = null;

    public static function buildFromArray(array $episode): Episode
    {
        $episodeObj = new Episode($episode['properties']);
        return $episodeObj;
    }

    /**
     * Get the value of bestFile
     */
    public function getBestFile(): ?File
    {
        return $this->bestFile;
    }

    /**
     * Set the value of bestFile
     *
     * @return  self
     */
    public function setBestFile($bestFile): Episode
    {
        $this->bestFile = $bestFile;

        return $this;
    }
}
