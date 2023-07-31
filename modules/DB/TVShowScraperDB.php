<?php

require_once('Logger.php');
require_once('TVShowUtils.php');

abstract class TVShowScraperDB
{

	public const DBTYPE_SQLITE = 'TVShowScraperDBSQLite';
	public const DBTYPE_XML = 'TVShowScraperDBXML';

	protected $db;
	protected $logger;

	public static function getInstance($class, $params): TVShowScraperDB
	{
		require_once("$class.php");
		return new $class($params);
	}

	public function setLogger($logger)
	{
		$this->logger = $logger;
	}

	public function setLogFile($logFile, $severity = LOGGER_DEBUG)
	{
		$this->logger = new Logger($logFile, $severity);
	}

	protected function log($msg, $severity = LOGGER_DEBUG)
	{
		if ($this->logger) $this->logger->log($msg, $severity);
	}

	protected function error($msg)
	{
		if ($this->logger) $this->logger->error($msg);
		return FALSE;
	}

	abstract public function beginTransaction();
	abstract public function inTransaction();
	abstract public function rollBack();
	abstract public function commit();

	abstract public function save($fileName = null);

	abstract protected function addElement($elementStore, $parentKey, $keyValue, $params);
	abstract protected function setElement($elementStore, $elementKey, $keyValue, $params);
	abstract protected function removeElement($elementStore, $elementKey, $keyValue);
	abstract protected function getElementByKey($elementStore, $elementKey);
	abstract protected function getElementByParentKey($elementStore, $parentKey);
	abstract protected function getElementByAttribute($elementStore, $attribute, $value);


	protected function validateParams($params, $validParams)
	{
		foreach ($params as $k => $v) {
			if (!isset($validParams[$k])) {
				$this->error("Unknown parameter $k");
				return FALSE;
			}
		}

		return TRUE;
	}



	// TVSHOW

	protected function validParamsTVShow()
	{
		return array(
			'title' => 1,
			'alternateTitle' => 1,
			'lang' => 1,
			'nativeLang' => 1,
			'res' => 1
		);
	}

	public function addTVShow($p)
	{
		return $this->validateParams($p, $this->validParamsTVShow()) ? $this->addElement('tvshows', null, null, $p) : FALSE;
	}

	public function removeTVShow($id)
	{
		$wasInTransaction = $this->inTransaction();
		if (!$wasInTransaction) $this->beginTransaction();

		$this->log("Removing any TVShow Scraper for show $id");
		if (!$this->removeElement('tvShowScrapers', 'tvShow', $id)) {
			$this->error("Failed removing TVShow Scrapers");
			if (!$wasInTransaction) $this->rollBack();
			return FALSE;
		}

		if (!$this->removeElement('tvshows', 'id', $id)) {
			if (!$wasInTransaction) $this->rollBack();
			return FALSE;
		}

		if (!$wasInTransaction) $this->commit();
		return TRUE;
	}

	public function getTVShow($id = null)
	{
		$res = $this->getElementByKey('tvShows', $id);
		if ($res === FALSE) return FALSE;

		if ($id != null) {
			return count($res) == 1 ? $res[0] : $this->error("Found " . count($res) . " matches for show $id");
		} else {
			return $res;
		}
	}

	public function setTVShow($id, $p)
	{
		if ($this->validateParams($p, $this->validParamsTVShow()) && $this->setElement('tvShows', 'id', $id, $p)) {
			$this->log("TVShow succesfully updated");
			return $this->getTVShow($id);
		} else {
			return FALSE;
		}
	}

	public function getAllTVShows()
	{
		return $this->getTVShow();
	}


	// SEASON

	protected function validParamsSeason()
	{
		return array(
			'n' => 1,
			'status' => 1
		);
	}

	public function addSeason($show, $p)
	{
		return $this->validateParams($p, $this->validParamsSeason()) ? $this->addElement('seasons', 'tvShow', $show, $p) : FALSE;
	}


	public function removeSeason($id)
	{
		$wasInTransaction = $this->inTransaction();
		if (!$wasInTransaction) $this->beginTransaction();

		$this->log("Removing any Season Scraper for season $id");
		if (!$this->removeElement('seasonScrapers', 'season', $id)) {
			$this->error("Failed removing Season Scrapers");
			if (!$wasInTransaction) $this->rollBack();
			return FALSE;
		}

		if (!$this->removeElement('seasons', 'id', $id)) {
			if (!$wasInTransaction) $this->rollBack();
			return FALSE;
		}

		if (!$wasInTransaction) $this->commit();
		return TRUE;
	}

	public function getSeason($id)
	{
		$res = $this->getElementByKey('seasons', $id);
		if ($res === FALSE) return FALSE;
		return count($res) == 1 ? $res[0] : $this->error("Found " . count($res) . " matches for season $id");
	}

	public function getTVShowSeasons($showId)
	{
		return $this->getElementByParentKey('seasons', $showId);
	}


	public function getAllWatchedSeasons()
	{
		return $this->getElementByAttribute('seasons', 'status', 'watched');
	}

	public function getSeasonFromN($showId, $n)
	{
		$seasons = $this->getTVShowSeasons($showId);
		if ($seasons === FALSE) return FALSE;

		$seaonsByN = array_filter($seasons, function ($s) use ($n) {
			return $s['n'] == $n;
		});
		return count($seaonsByN) == 1 ? array_values($seaonsByN)[0] : null;
	}

	public function setSeason($id, $p)
	{
		if ($this->validateParams($p, $this->validParamsSeason()) && $this->setElement('seasons', 'id', $id, $p)) {
			$this->log("Season succesfully updated");
			return $this->getSeason($id);
		} else {
			return FALSE;
		}
	}

	public function resetBestFilesForSeason($id)
	{
		$episodes = $this->getSeasonEpisodes($id);
		foreach ($episodes as $e) {
			if (isset($e['bestFile'])) {
				if (!$this->resetEpisodeBestFile($e['id'])) return FALSE;
			}
		}
		return TRUE;
	}


	// EPISODE

	protected function validParamsEpisode()
	{
		return array(
			'bestSticky' => 1,
			'n' => 1,
			'airDate' => 1,
			'title' => 1,
			'bestFile' => 1
		);
	}

	public function addEpisode($seasonId, $p)
	{
		return $this->validateParams($p, $this->validParamsEpisode()) ? $this->addElement('episodes', 'season', $seasonId, $p) : FALSE;
	}

	public function removeEpisode($id)
	{
		return $this->removeElement('episodes', 'id', $id);
	}

	public function getEpisode($id)
	{
		$res = $this->getElementByKey('episodes', $id);
		if ($res === FALSE) return FALSE;
		return count($res) == 1 ? $res[0] : $this->error("Found " . count($res) . " matches for episode $id");
	}

	public function getSeasonEpisodes($seasonId)
	{
		return $this->getElementByParentKey('episodes', $seasonId);
	}

	public function getEpisodeFromIndex($showId, $season, $episode)
	{
		$res = $this->getSeasonFromN($showId, $season);
		if ($res === FALSE) return FALSE;
		$res = $this->getSeasonEpisodes($res['id']);
		if ($res === FALSE) return FALSE;

		$episodeByN = array_filter($res, function ($e) use ($episode) {
			return $e['n'] == $episode;
		});
		return count($episodeByN) == 1 ? array_values($episodeByN)[0] : null;
	}


	public function setEpisode($id, $p)
	{

		$wasInTransaction = $this->inTransaction();
		if (!$wasInTransaction) $this->beginTransaction();

		if (isset($p['bestSticky']) && ($p['bestSticky'] == '0' || $p['bestSticky'] == '_REMOVE_')) {
			if (!$this->resetEpisodeBestFile($id, TRUE)) {
				if (!$wasInTransaction) $this->rollBack();
				return FALSE;
			}
		}

		if ($this->validateParams($p, $this->validParamsEpisode()) && $this->setElement('episodes', 'id', $id, $p)) {
			$this->log("Episode succesfully updated");
			if (!$wasInTransaction) $this->commit();
			return $this->getEpisode($id);
		} else {
			return FALSE;
		}
	}

	public function resetEpisodeBestFile($id, $force = FALSE)
	{
		$this->log("Resetting bestFile for episode $id, force = $force");
		$episode = $this->getEpisode($id);
		if ($episode === FALSE) {
			$this->error("Could not find unique episode $id");
			return FALSE;
		}

		if ($force == TRUE || !isset($episode['bestSticky']) || !$episode['bestSticky']) {
			return $this->setEpisode($id, array('bestFile' => '_REMOVE_'));
		}
		return TRUE;
	}



	// SCRAPER


	protected function validParamsScraper()
	{
		return array(
			'preference'	=> 1,
			'delay'	=> 1,
			'uri'	=> 1,
			'source'	=> 1,
			'autoAdd'	=> 1,
			'notify'	=> 1
		);
	}

	public function addScraper($rootId, $type, $p)
	{

		if (!$this->validateParams($p, $this->validParamsScraper())) return FALSE;

		if (isset($p['preference']) && $p['preference'] == '') unset($p['preference']); // Empty non null preference causes issues with scraper sorting during getBestFile

		$wasInTransaction = $this->inTransaction();
		if (!$wasInTransaction) $this->beginTransaction();

		$newScraper = $this->addElement('scrapers', null, null, $p);

		if ($newScraper === FALSE) {
			if (!$wasInTransaction) $this->rollBack();
			return FALSE;
		}

		$newLink = FALSE;
		if ($type == "season") {
			$newLink = $this->addElement('seasonScrapers', 'season', $rootId, array('scraper' => $newScraper['id']));
		} else if ($type == "tvShow") {
			$newLink = $this->addElement('tvShowScrapers', 'tvShow', $rootId, array('scraper' => $newScraper['id']));
		} else {
			$this->error("Invalid scraper type $type");
		}

		if ($newLink === FALSE) {
			if (!$wasInTransaction) $this->rollBack();
			return FALSE;
		}

		if (!$wasInTransaction) $this->commit();
		$this->log("New $type scraper successfully created");

		$newScraper[$type] = $rootId;
		return $newScraper;
	}

	public function removeScraper($id)
	{
		return $this->removeElement('scrapers', 'id', $id);
	}

	public function getScraper($id)
	{
		$res = $this->getElementByKey('scrapers', $id);
		if ($res === FALSE) return FALSE;
		return count($res) == 1 ? $res[0] : $this->error("Found " . count($res) . " matches for scraper $id");
	}

	public function getSeasonScrapers($seasonId)
	{
		return $this->getElementByAttribute('scrapers', 'season', $seasonId);
	}

	public function getTVShowScrapers($showId)
	{
		return $this->getElementByAttribute('scrapers', 'tvShow', $showId);
	}

	public function getActiveScrapers()
	{
		return $this->getElementByAttribute('scrapers', 'active', true);
	}


	public function setScraper($id, $p)
	{

		if (isset($p['preference']) && $p['preference'] == '') $p['preference'] = '_REMOVE_'; // Empty non null preference causes issues with scraper sorting during getBestFile

		$wasInTransaction = $this->inTransaction();
		if (!$wasInTransaction) $this->beginTransaction();

		if ($this->validateParams($p, $this->validParamsScraper()) && $this->setElement('scrapers', 'id', $id, $p)) {
			$this->log("Scraper succesfully updated");
			$scraper = $this->getScraper($id);

			if ((isset($p['delay']) || isset($p['preference'])) && isset($scraper['season'])) {
				if (!$this->resetBestFilesForSeason($scraper['season'])) {
					if (!$wasInTransaction) $this->rollBack();
					return FALSE;
				}
			}

			if (!$wasInTransaction) $this->commit();
			return $scraper;
		} else {
			if (!$wasInTransaction) $this->rollBack();
			return FALSE;
		}
	}




	// SCRAPED SEASON

	protected function validParamsScrapedSeason()
	{
		return array(
			'uri'	=> 1,
			'n'		=> 1,
			'hide'	=> 1,
			'tbn'	=> 1
		);
	}

	public function addScrapedSeason($scraperId, $p)
	{
		return $this->validateParams($p, $this->validParamsScrapedSeason()) ? $this->addElement('scrapedSeasons', 'scraper', $scraperId, $p) : FALSE;
	}

	public function removeScrapedSeason($id)
	{
		$this->log("Removing scraped season $id...");
		return $this->removeElement('scrapedSeasons', 'id', $id);
	}


	public function getScrapedSeason($id)
	{
		$q = "SELECT scrapedSeasons.*, scrapers.source FROM scrapedSeasons JOIN scrapers ON scrapedSeasons.scraper = scrapers.id WHERE scrapedSeasons.id = :id";
		$res = $this->getElementByKey('scrapedSeasons', $id);
		if ($res === FALSE) return FALSE;
		else if (count($res) != 1) return $this->error("Can't find a single match for scrapedSeason $id");
		else return $res['0'];
	}


	public function getScrapedSeasons($showId)
	{
		return $this->getElementByAttribute('scrapedSeasons', 'tvShow', $showId);
	}

	public function getScrapedSeasonsTBN()
	{
		return $this->getElementByAttribute('scrapedSeasons', 'tbn', 1);
	}



	public function getScrapedSeasonFromUri($scraperId, $uri, $n = NULL)
	{

		$res = $this->getElementByAttribute('scrapedSeasons', 'uri', $uri);
		if ($res === FALSE) return FALSE;

		if ($n != null) {
			$res = array_filter($res, function ($scrapedSeason) use ($n) {
				return $scrapedSeason['n'] == $n;
			});
		}

		if (count($res) == 0) {
			$this->log("Scraped season with URI $uri and n $n not found");
			return NULL;
		} else if (count($res) == 1) {
			return $res['0'];
		} else {
			return $this->error("Multiple matches for URI $uri and n $n");
		}
	}

	public function setScrapedSeason($id, $p)
	{
		if ($this->validateParams($p, $this->validParamsScrapedSeason()) && $this->setElement('scrapedSeasons', 'id', $id, $p)) {
			$this->log("Scraped Season succesfully updated");
			return $this->getScrapedSeason($id);
		} else {
			return FALSE;
		}
	}

	public function createSeasonScraperFromScraped($id)
	{
		$scrapedSeason = $this->getScrapedSeason($id);
		if ($scrapedSeason === FALSE) {
			$this->error("Could not find unique scraped season $id");
			return FALSE;
		}

		$scraper = $this->getScraper($scrapedSeason['scraper']);
		if ($scraper === FALSE) {
			$this->error("Could not find unique scraper " . $scrapedSeason['scraper']);
			return FALSE;
		}

		$season = $this->getSeasonFromN($scraper['tvshow'], $scrapedSeason['n']);

		if ($season === FALSE) return FALSE;

		$wasInTransaction = $this->inTransaction();
		if (!$wasInTransaction) $this->beginTransaction();

		if ($season == NULL) {
			$this->log("Season " . $scrapedSeason['n'] . " does not exist yet. Creating...");
			$season = $this->addSeason($scraper['tvshow'], array('n' => $scrapedSeason['n'], 'status' => 'watched'));
			if ($season === FALSE) {
				if (!$wasInTransaction) $this->rollBack();
				return FALSE;
			}
		}

		$this->log("Adding new scraper to season " . $season['id']);
		$newScraper = $this->addScraper($season['id'], 'season', array(
			'uri' => $scrapedSeason['uri'],
			'source' => $scraper['source']
		));

		if ($newScraper === FALSE) {
			if (!$wasInTransaction) $this->rollBack();
			return FALSE;
		}

		if (!$this->setScrapedSeason($id, array('hide' => '1'))) {
			if (!$wasInTransaction) $this->rollBack();
			return FALSE;
		}

		if (!$wasInTransaction) $this->commit();
		return $newScraper['id'];
	}



	// FILE


	protected function validParamsFile()
	{
		return array(
			'discard'		=> 1,
			'uri'			=> 1,
			'pubDate'		=> 1,
			'type'			=> 1,
			'scraper'		=> 1
		);
	}

	public function addFile($episodeId, $p)
	{
		if (!$this->validateParams($p, $this->validParamsFile())) return FALSE;

		$wasInTransaction = $this->inTransaction();
		if (!$wasInTransaction) $this->beginTransaction();

		$newFile = $this->addElement('files', 'episode', $episodeId, $p);
		if ($newFile === FALSE) {
			if (!$wasInTransaction) $this->rollBack();
			return FALSE;
		}

		if (!$this->resetEpisodeBestFile($episodeId)) {
			if (!$wasInTransaction) $this->rollBack();
			return FALSE;
		}

		if (!$wasInTransaction) $this->commit();

		return $newFile;
	}

	public function removeFile($id)
	{
		return $this->removeElement('files', 'id', $id);
	}

	public function getFile($id)
	{
		$res = $this->getElementByKey('files', $id);
		if ($res === FALSE) return FALSE;
		if (count($res) != 1) return $this->error("Can't fine unique file $id");
		return $res[0];
	}

	public function getFilesForEpisode($id)
	{
		return $this->getElementByParentKey('files', $id);
	}

	public function getFilesForSeason($id)
	{
		return $this->getElementByAttribute('files', 'season', $id);
	}

	public function getFilesForScraper($id)
	{
		return $this->getElementByAttribute('files', 'scraper', $id);
	}

	public function getBestFileForEpisode($id)
	{
		$this->log("Checking best file for episode $id");

		$episode = $this->getEpisode($id);
		if ($episode === FALSE) return FALSE;

		if (isset($episode['bestFile'])) {
			$best = $this->getFile($episode['bestFile']);
			if ($best === FALSE) {
				// Something strange happened. Reset bestFile.
				$this->resetEpisodeBestFile($id, TRUE);
			} else {
				return $best;
			}
		}


		/* 

		1 - Get file list for episode (not discarded + already published after delayed is applied)
		2 - Order by scraper preference (null on top)
		3 - If same preference, older publsih date first
		4 - If same preference and pubDate, than file ID (unlikely but we need a tie breaker)

		*/

		$this->log("---------------- Starting bestFile selection ----------------");

		$scrapers = $this->getSeasonScrapers($episode['season']);
		if ($scrapers === FALSE) return FALSE;

		$scrapersIndexed = array();
		foreach ($scrapers as $scraper) {
			$scrapersIndexed[$scraper['id']] = $scraper;
			if (!isset($scraper['delay'])) $scraper['delay'] = 0;
		}

		$bestFile = null;
		$now = time();

		$files = $this->getFilesForEpisode($id);
		if ($files === FALSE) return FALSE;
		if (count($files) == 0) return null;


		usort($files, function ($a, $b) use ($scrapersIndexed) {
			return $a['pubDate'] + $scrapersIndexed[$a['scraper']]['delay'] - $b['pubDate'] - $scrapersIndexed[$b['scraper']]['delay'];
		});

		foreach ($files as $file) {
			if ($file['pubDate'] + $scrapersIndexed[$file['scraper']]['delay'] > $now) break; // We reached the end of published files
			if (isset($file['discard']) && $file['discard']) continue;

			if ($bestFile === null) {
				$this->log("First file found - $file[uri]");
				$bestFile = $file;
				continue;
			}

			if ($file['scraper'] == $bestFile['scraper']) {
				// Update from same scraper
				$this->log("Found new file from selected scraper - $file[uri]");
				$bestFile = $file;
				continue;
			}

			$hasSamePreference = true;
			$hasBetterPreference = false;

			if (isset($scrapersIndexed[$file['scraper']]['preference'])) {
				if (!isset($scrapersIndexed[$bestFile['scraper']]['preference'])) {
					$hasSamePreference = false;
					$hasBetterPreference = false;
				} else {
					$hasSamePreference = ($scrapersIndexed[$file['scraper']]['preference'] == $scrapersIndexed[$bestFile['scraper']]['preference']);
					$hasBetterPreference = ($scrapersIndexed[$file['scraper']]['preference'] < $scrapersIndexed[$bestFile['scraper']]['preference']);
				}
			} else if (isset($scrapersIndexed[$bestFile['scraper']]['preference'])) {
				$hasSamePreference = false;
				$hasBetterPreference = true;
			}

			$this->log("File $file[uri] preference check - hasSamePreference: $hasSamePreference - hasBetterPrefefence: $hasBetterPreference");

			if ($hasBetterPreference) {
				$this->log("Found new file with better preference - $file[uri]");
				$bestFile = $file;
				continue;
			}

			if ($hasSamePreference && $file['pubDate'] + $scrapersIndexed[$file['scraper']]['delay'] == $bestFile['pubDate'] + $scrapersIndexed[$bestFile['scraper']]['delay'] && $file['id'] > $bestFile['id']) {
				// Tie breaker in case of same preference and pubDate
				$this->log("Found new file with same preference and same date with earlier file ID - " - $file['uri']);
				$bestFile = $file;
			}
		}

		$this->log("---------------- bestFile selected " . ($bestFile ? $bestFile['uri'] : "NONE") . " ----------------");

		if ($bestFile !== null && !$this->setEpisode($id, array('bestFile' => $bestFile['id']))) return FALSE;
		return $bestFile;
	}

	public function getBestFilesForSeason($id)
	{
		$this->log("Checking best file for season $id");

		$res = array();
		$episodes = $this->getSeasonEpisodes($id);
		foreach ($episodes as $e) {
			$file = $this->getBestFileForEpisode($e['id']);
			if ($file != NULL) $res[] = $file;
		}
		return $res;
	}

	public function getAllWatchedBestFiles()
	{
		$list = $this->getElementByAttribute('episodes', 'seasonStatus', 'watched');
		if ($list === FALSE) return FALSE;

		$res = array();
		for ($i = 0; $i < count($list); $i++) {
			$file = $this->getBestFileForEpisode($list[$i]['id']);
			if ($file != NULL) $res[] = $file;
		}
		return $res;
	}


	public function setFile($id, $p)
	{

		if (!$this->validateParams($p, $this->validParamsFile())) return FALSE;

		$wasInTransaction = $this->inTransaction();
		if (!$wasInTransaction) $this->beginTransaction();

		if (isset($p['discard']) || isset($p['pubDate'])) {
			$oldFile = $this->getFile($id);
			if ($oldFile === FALSE) {
				if (!$wasInTransaction) $this->rollBack();
				return FALSE;
			}
			if (!$this->resetEpisodeBestFile($oldFile['episode'])) {
				if (!$wasInTransaction) $this->rollBack();
				return FALSE;
			}
		}
		if (!$this->setElement('files', 'id', $id, $p)) {
			if (!$wasInTransaction) $this->rollBack();
			return FALSE;
		} else {
			if (!$wasInTransaction) $this->commit();
			$this->log("File $id successfully updated");
			return $this->getFile($id);
		}
	}
}
