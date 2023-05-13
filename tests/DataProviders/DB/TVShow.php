<?php

declare(strict_types=1);

namespace DataProviders\DB;

class TVShow extends GenericItem
{
    private array $seasons = [];
    private array $scrapers = [];

    private ?int $lastAirDate = null;
    private ?int $lastAiredEpisodeIndex = null;
    private ?int $nextAirDate = null;
    private ?int $missingCount = null;
    private ?int $firstMissingIndex = null;
    private ?int $latestMissingIndex = null;
    private ?int $lastEpisodeIndex = null;
    private ?int $airedEpisodesCount = null;
    private ?int $lastPubDate = null;
    private int $episodesWithFile = 0;
    private ?int $pendingScrapedSeasons = null;


    public function __construct(array $properties, array $seasons = [], $scrapers = [])
    {
        parent::__construct($properties);

        foreach ($seasons as $season) {
            $this->attachSeason($season);
        }

        foreach ($scrapers as $scraper) {
            $this->attachScraper($scraper);
        }
    }

    public static function buildFromArray(array $tvShow): TVShow
    {
        $tvShowObj = new TVShow(
            $tvShow['properties'],
            array_key_exists('seasons', $tvShow) ?
                array_map(function ($season) {
                    return Season::buildFromArray($season);
                }, $tvShow['seasons'])
                :
                [],
            array_key_exists('scrapers', $tvShow) ?
                array_map(function ($scraper) {
                    return TVShowScraper::buildFromArray($scraper);
                }, $tvShow['scrapers'])
                :
                []
        );

        if (array_key_exists('lastAirDate', $tvShow)) $tvShowObj->lastAirDate = intval($tvShow['lastAirDate']);
        if (array_key_exists('lastAiredEpisodeIndex', $tvShow)) $tvShowObj->lastAiredEpisodeIndex = intval($tvShow['lastAiredEpisodeIndex']);
        if (array_key_exists('nextAirDate', $tvShow)) $tvShowObj->nextAirDate = intval($tvShow['nextAirDate']);
        if (array_key_exists('missingCount', $tvShow)) $tvShowObj->missingCount = intval($tvShow['missingCount']);
        if (array_key_exists('firstMissingIndex', $tvShow)) $tvShowObj->firstMissingIndex = intval($tvShow['firstMissingIndex']);
        if (array_key_exists('latestMissingIndex', $tvShow)) $tvShowObj->latestMissingIndex = intval($tvShow['latestMissingIndex']);
        if (array_key_exists('lastEpisodeIndex', $tvShow)) $tvShowObj->lastEpisodeIndex = intval($tvShow['lastEpisodeIndex']);
        if (array_key_exists('airedEpisodesCount', $tvShow)) $tvShowObj->airedEpisodesCount = intval($tvShow['airedEpisodesCount']);
        if (array_key_exists('lastPubDate', $tvShow)) $tvShowObj->lastPubDate = intval($tvShow['lastPubDate']);
        if (array_key_exists('episodesWithFile', $tvShow)) $tvShowObj->episodesWithFile = intval($tvShow['episodesWithFile']);
        if (array_key_exists('pendingScrapedSeasons', $tvShow)) $tvShowObj->pendingScrapedSeasons = intval($tvShow['pendingScrapedSeasons']);

        return $tvShowObj;
    }

    private function attachSeason(Season $season)
    {
        $season->setParentItem($this);
        $this->seasons[] = $season;
    }

    private function attachScraper(TVShowScraper $scraper)
    {
        $scraper->setParentItem($this);
        $this->scrapers[] = $scraper;
    }

    public function getSeasons()
    {
        return $this->seasons;
    }

    public function getTVShowScrapers()
    {
        return $this->scrapers;
    }

    public function computeStats(): void
    {
        foreach ($this->seasons as $season) {
            $season->computeStats();
        }
    }

    /**
     * Overrides super actualizeDates - Converts lastPubDate, lastAirDate, nextAirDate from relative to absolute
     * WARNING can be called only once
     * @param ?int $now - time to actualized dates to, defaults to time() if not specified
     * @return GenericItem - self
     */

    public function actualizeDates(?int $now = null): GenericItem
    {
        if ($now === null) $now = time();
        if ($this->lastPubDate !== null) $this->lastPubDate = $now + $this->lastPubDate;
        if ($this->lastAirDate !== null) $this->lastAirDate = $now + $this->lastAirDate;
        if ($this->nextAirDate !== null) $this->nextAirDate = $now + $this->nextAirDate;

        parent::actualizeDates($now);
        return $this;
    }

    /**
     * Get the value of lastAirDate
     */
    public function getLastAirDate()
    {
        return $this->lastAirDate;
    }

    /**
     * Get the value of lastAiredEpisodeIndex
     */
    public function getLastAiredEpisodeIndex()
    {
        return $this->lastAiredEpisodeIndex;
    }

    /**
     * Get the value of missingCount
     */
    public function getMissingCount()
    {
        return $this->missingCount;
    }

    /**
     * Get the value of firstMissingIndex
     */
    public function getFirstMissingIndex()
    {
        return $this->firstMissingIndex;
    }

    /**
     * Get the value of latestMissingIndex
     */
    public function getLatestMissingIndex()
    {
        return $this->latestMissingIndex;
    }

    /**
     * Get the value of lastEpisodeIndex
     */
    public function getLastEpisodeIndex()
    {
        return $this->lastEpisodeIndex;
    }

    /**
     * Get the value of airedEpisodesCount
     */
    public function getAiredEpisodesCount()
    {
        return $this->airedEpisodesCount;
    }

    /**
     * Get the value of episodesWithFile
     */
    public function getEpisodesWithFile()
    {
        return $this->episodesWithFile;
    }

    /**
     * Get the value of pendingScrapedSeasons
     */
    public function getPendingScrapedSeasons()
    {
        return $this->pendingScrapedSeasons;
    }

    /**
     * Get the value of nextAirDate
     */
    public function getNextAirDate()
    {
        return $this->nextAirDate;
    }

    /**
     * Get the value of lastPubDate
     */
    public function getLastPubDate()
    {
        return $this->lastPubDate;
    }
}
