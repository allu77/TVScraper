<?php

require_once('Logger.php');
require_once('TVShowUtils.php');

class TVShowScraperDBSQLite  {

	protected $db;
	protected $logger;

	
	public function setLogger($logger) {
		$this->logger = $logger;
	}
	
	public function setLogFile($logFile, $severity = LOGGER_DEBUG) {
		$this->logger = new Logger($logFile, $severity);
	}
	
	protected function log($msg, $severity = LOGGER_DEBUG) {
		if ($this->logger) $this->logger->log($msg, $severity);
	}
	
	protected function error($msg) {
		if ($this->logger) $this->logger->error($msg);
		return FALSE;
	}
	
	public function __construct($fileName) {
		if (file_exists($fileName)) {
			$this->db = new PDO("sqlite:$fileName", null, null, array());
			$this->db->exec("PRAGMA foreign_keys = 'ON'");
		} else {
			// CREATE new DB
			$this->db = new PDO("sqlite:$fileName", null, null, array());
			$this->db->exec("PRAGMA foreign_keys = 'ON'");
			$buildSql = file_get_contents("TVShowScraperDB.sql");
			$this->db->exec($buildSql);
		}
	}

	public function beginTransaction() {
		return $this->db->beginTransaction();
	}
	
	public function save($fileName = null) {
		return $this->db->inTransaction() ? $this->db->commit() : TRUE;
	}
	
	protected function addElementDB($table, $parentKey, $parentValue, $params) {

		$columnList = '';
		$placeholderList = '';

		foreach ($params as $k => $v) {
			$columnList .= "$k,";
			$placeholderList .= ":$k,";
		}

		if ($parentKey != null) {
			$columnList .= $parentKey;
			$placeholderList .= ":$parentKey";
		} else {
			$columnList = rtrim($columnList, ",");
			$placeholderList = rtrim($placeholderList, ",");
		}

		$query = "INSERT INTO $table($columnList) VALUES($placeholderList)";
		$this->log("Preparing query $query");
		$st = $this->db->prepare($query);
		if ($st == null) {
			return $this->error("Failed to prepare query: " . implode(', ', $this->db->errorInfo()));
		}
		if ($parentKey != null) {
			$this->log("Executing with params $parentValue, " . implode(', ', $params));
			$st->execute(array_merge(array($parentKey => $parentValue), $params));
		} else {
			$this->log("Executing with params " . implode(', ', $params));
			$st->execute($params);
		}
		if ($st->rowCount() == 1) {
			return array_merge(array('id' => $this->db->lastInsertId()), $params);
		}
		return $this->error("Failed to execute query: " . implode(', ', $this->db->errorInfo()));
	}

	protected function setElementDB($table, $key, $value, $params) {
		$query = "UPDATE $table SET ";
		$p = array( $key => $value );
		foreach ($params as $k => $v) {
			$query .= "$k = :$k,";
			$p[$k] = $v == '_REMOVE_' ? null : $v;
		}
		$query = rtrim($query, ",");
		$query .= " WHERE $key = :$key";

		$wasInTransaction = $this->db->inTransaction();
		if (! $wasInTransaction) $this->db->beginTransaction();

		$this->log("Preparing query $query");
		$st = $this->db->prepare($query);
		if ($st == null) {
			return $this->error("Failed to prepare query: " . implode(', ', $this->db->errorInfo()));
		}

		$this->log("Executing with params " . implode(', ', $params) . ", $value");
		$st->execute($p);

		if ($st->rowCount() != 1) {
			if (! $wasInTransaction) $this->db->rollBack();
			return $this->error("Set query affected ". $st->rowCount() ." rows, expected 1!");
		} else {
			if (! $wasInTransaction) $this->db->commit();
			return TRUE;
		}
	}

	protected function removeElementDB($table, $key, $value) {
		$query = "DELETE FROM $table WHERE $key = ?";

		$this->log("Preparing query $query");
		$st = $this->db->prepare($query);
		if ($st == null) {
			return $this->error("Failed to prepare query: " . implode(', ', $this->db->errorInfo()));
		}
		$this->log("Executing with param $value");
		$st->execute(array($value));

		return TRUE;
	}

	protected function getElementDB($q, $p) {
		$this->log("Prepary query $q");

		$st = $this->db->prepare($q);
		if ($st == null) {
			$this->error("Failed to prepare query: " . implode(', ', $this->db->errorInfo()));
			return FALSE;
		}

		$this->log("Executing with params " . implode(', ', $p));
		$st->execute($p);

		$res = array();

		while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
			foreach ($r as $k => $v) {
				if ($v == null) unset($r[$k]);
			}
			$res[] = $r;
		}

		return $res;
	}


	protected function validateParams($params, $validParams) {
		foreach ($params as $k => $v) {
			if (! isset($validParams[$k])) {
				$this->error("Unknown parameter $k");
				return FALSE;
			}
		}

		return TRUE;
	}
	
	
	// TVSHOW

	protected function validParamsTVShow() {
		return array(
			'title' => 1,
			'alternateTitle' => 1,
			'lang' => 1,
			'nativeLang' => 1,
			'res' => 1
		);
	}
	
	public function addTVShow($p) {
		return $this->validateParams($p, $this->validParamsTVShow()) ? $this->addElementDB('tvshows', null, null, $p) : FALSE;
	}
	
	public function removeTVShow($id) {
		$wasInTransaction = $this->db->inTransaction();
		if (!$wasInTransaction) $this->db->beginTransaction();

		$this->log("Removing any TVShow Scraper for show $id");
		if (! $this->removeElementDB('tvShowScrapers', 'tvShow', $id)) {
			$this->error("Failed removing TVShow Scrapers");
			if (!$wasInTransaction) $this->db->rollBack();
			return FALSE;
		}

		if (! $this->removeElementDB('tvshows', 'id', $id)) {
			if (!$wasInTransaction) $this->db->rollBack();
			return FALSE;
		}

		if (!$wasInTransaction) $this->db->commit();
		return TRUE;
	}
	
	public function setTVShow($id, $p) {
		if ( $this->validateParams($p, $this->validParamsTVShow()) && $this->setElementDB('tvshows', 'id', $id, $p) ) {
			$this->log("TVshow succesfully updated");
			return $this->getTVShow($id);
		} else {
			return FALSE;
		}
	}

	public function getTVShow($id = null) {

		$q = "SELECT * from tvShowsWithStats";
		if ($id != null) $q .= " WHERE id = :id";

		$this->log("Prepary query $q");

		$st = $this->db->prepare($q);
		if ($st == null) {
			$this->error("Failed to prepare query: " . implode(', ', $this->db->errorInfo()));
			return FALSE;
		}

		if ($id == null) {
			$this->log("Executing query");
			$st->execute();
		} else {
			$this->log("Executing query with param $id");
			$st->execute(array('id'=>$id));
		}

		$res = array();

		while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
			foreach ($r as $k => $v) {
				if ($v == null) unset($r[$k]);
			}
			if (!isset($r['episodesWithFile'])) $r['episodesWithFile'] = 0;
			$res[] = $r;
		}

		if ($id != null) {
			return count($res) == 1 ? $res[0] : $this->error("Found " . count($res) . " matches for show $id");
		} else {
			return $res;
		}
	}
	
	public function getAllTVShows() {
		return $this->getTVShow();
	}
	

	// SEASON
	
	protected function validParamsSeason() {
		return array(
			'n' => 1,
			'status' => 1
		);
	}

	public function addSeason($show, $p) {
		return $this->validateParams($p, $this->validParamsSeason()) ? $this->addElementDB('seasons', 'tvshow', $show, $p) : FALSE;
	}
	
	public function removeSeason($id) {
		$wasInTransaction = $this->db->inTransaction();
		if (!$wasInTransaction) $this->db->beginTransaction();

		$this->log("Removing any Season Scraper for season $id");
		if (! $this->removeElementDB('seasonScrapers', 'season', $id)) {
			$this->error("Failed removing Season Scrapers");
			if (!$wasInTransaction) $this->db->rollBack();
			return FALSE;
		}

		if (! $this->removeElementDB('seasons', 'id', $id)) {
			if (!$wasInTransaction) $this->db->rollBack();
			return FALSE;
		}

		if (!$wasInTransaction) $this->db->commit();
		return TRUE;
	}
	
	
	public function setSeason($id, $p) {
		if ( $this->validateParams($p, $this->validParamsSeason()) && $this->setElementDB('seasons', 'id', $id, $p) ) {
			$this->log("Season succesfully updated");
			return $this->getSeason($id);
		} else {
			return FALSE;
		}
	}
	
	public function getSeason($id) {
		$res = $this->getElementDB("SELECT * FROM seasons WHERE id = :id", array('id' => $id));
		return count($res) == 1 ? $res[0] : $this->error("Found " . count($res) . " matches for season $id");
	}
	
	public function getSeasonFromN($showId, $n) {
		$res = $this->getElementDB("SELECT * FROM seasons WHERE tvShow = :tvShow and n = :n", array('tvShow' => $showId, 'n' => $n));
		return count($res) == 1 ? $res[0] : $this->error("Found " . count($res) . " matches for tvShow $showId and n $n");
	}
	
	public function getTVShowSeasons($showId) {
		return $this->getElementDB("SELECT * FROM seasons WHERE tvShow = :tvShow", array('tvShow' => $showId));
	}
	
	
	public function getAllWatchedSeasons() {
		return $this->getElementDB("SELECT * FROM seasons WHERE status = 'watched'", array());
	}
	

	// EPISODE

	protected function validParamsEpisode() {
		return array(
			'bestSticky' => 1,
			'n' => 1,
			'airDate' => 1,
			'title' => 1,
			'bestFile' => 1
		);
	}
	
	public function addEpisode($seasonId, $p) {
		return $this->validateParams($p, $this->validParamsEpisode()) ? $this->addElementDB('episodes', 'season', $seasonId, $p) : FALSE;
	}
	
	public function removeEpisode($id) {
		return $this->removeElementDB('episodes', 'id', $id);
	}
	
	public function setEpisode($id, $p) {

		$wasInTransaction = $this->db->inTransaction();
		if (!$wasInTransaction) $this->db->beginTransaction();

		if (isset($p['bestSticky']) && ($p['bestSticky'] == '0' || $p['bestSticky'] == '_REMOVE_')) {
			if (!$this->resetEpisodeBestFile($id, TRUE)) {
				if (!$wasInTransaction) $this->db->rollBack();
				return FALSE;
			}
		}

		if ( $this->validateParams($p, $this->validParamsEpisode()) && $this->setElementDB('episodes', 'id', $id, $p) ) {
			$this->log("Episode succesfully updated");
			if (!$wasInTransaction) $this->db->commit();
			return $this->getEpisode($id);
		} else {
			return FALSE;
		}
	}

	public function resetEpisodeBestFile($id, $force = FALSE) {
		$this->log("Resetting bestFile for episode $id, force = $force");
		$episode = $this->getEpisode($id);
		if ($episode === FALSE) {
			$this->error("Could not find unique episode $id");
			return FALSE;
		}

		if ($force == TRUE || !isset($episode['bestSticky']) || !$episode['bestSticky']) {
			return $this->setEpisode($id, array( 'bestFile' => '_REMOVE_' ));
		}
		return TRUE;

	}
	
	public function getEpisode($id) {
		$res = $this->getElementDB("SELECT * FROM episodes WHERE id = :id", array('id' => $id));
		return count($res) == 1 ? $res[0] : $this->error("Found " . count($res) . " matches for episode $id");
	}

	public function getEpisodeFromIndex($showId, $season, $episode) {
		$res = $this->getElementDB("SELECT episodes.* FROM episodes JOIN seasons ON episodes.season = season.id WHERE show = :show AND season.n = :season AND n = :episode", array('show' => $showId, 'season' => $season, 'episode' => $episode));
		return count($res) == 1 ? $res[0] : $this->error("Found " . count($res) . " matches for show $showId, season $season, episode $episode");
	}

	public function getSeasonEpisodes($seasonId) {
		return $this->getElementDB("SELECT * FROM episodes WHERE season = :season ORDER BY n ASC", array('season' => $seasonId));
	}
	

	// SCRAPER


	protected function validParamsScraper() {
		return array(
			'preference'	=> 1,
			'delay'	=> 1,
			'uri'	=> 1,
			'source'	=> 1,
			'autoAdd'	=> 1,
			'notify'	=> 1
		);
	}
	
	public function addScraper($rootId, $type, $p) {

		if (! $this->validateParams($p, $this->validParamsScraper())) return FALSE;

		if (isset($p['preference']) && $p['preference'] == '') unset($p['preference']); // Empty non null preference causes issues with scraper sorting during getBestFile

		$wasInTransaction = $this->db->inTransaction();
		if (!$wasInTransaction) $this->db->beginTransaction();

		$newScraper = $this->addElementDB('scrapers', null, null, $p);

		if ($newScraper === FALSE) {
			if (!$wasInTransaction) $this->db->rollBack();
			return FALSE;
		}

		$newLink = FALSE;
		if ($type == "season") {
			$newLink = $this->addElementDB('seasonScrapers', 'season', $rootId, array('scraper' => $newScraper['id']));
		} else if ($type == "tvShow") {
			$newLink = $this->addElementDB('tvShowScrapers', 'tvShow', $rootId, array('scraper' => $newScraper['id']));
		} else {
			$this->error("Invalid scraper type $type");
		}

		if ($newLink === FALSE) {
			if (!$wasInTransaction) $this->db->rollBack();
			return FALSE;
		}

		if (!$wasInTransaction) $this->db->commit();
		$this->log("New $type scraper successfully created");

		$newScraper[$type] = $rootId;
		return $newScraper;
	}
	
	public function removeScraper($id) {
		return $this->removeElementDB('scrapers', 'id', $id);
	}
	
	public function setScraper($id, $p) {

		if (isset($p['preference']) && $p['preference'] == '') $p['preference'] = '_REMOVE_'; // Empty non null preference causes issues with scraper sorting during getBestFile

		$wasInTransaction = $this->db->inTransaction();
		if (!$wasInTransaction) $this->db->beginTransaction();

		if ( $this->validateParams($p, $this->validParamsScraper()) && $this->setElementDB('scrapers', 'id', $id, $p) ) {
			$this->log("Scraper succesfully updated");
			$scraper = $this->getScraper($id);

			if (isset($p['delay']) && isset($scraper['season'])) {
				if (! $this->resetBestFilesForSeason($scraper['season'])) {
					if (!$wasInTransaction) $this->db->rollBack();
					return FALSE;
				}
			}

			if (!$wasInTransaction) $this->db->commit();
			return $scraper;
		} else {
			if (!$wasInTransaction) $this->db->rollBack();
			return FALSE;
		}
	}


	// TODO: See if this can be optimized with SQL

	public function resetBestFilesForSeason($id) {
		$episodes = $this->getSeasonEpisodes($id);
		foreach ($episodes as $e) {
			if (isset($e['bestFile'])) {
				$this->resetEpisodeBestFile($e['id']);
			}
		}
	}
	
	public function getScraper($id) {
		$res = $this->getElementDB("SELECT * FROM scrapersWithParents WHERE id = :id", array('id' => $id));
		return count($res) == 1 ? $res[0] : $this->error("Found " . count($res) . " matches for season $id");
	}

	
	public function getSeasonScrapers($seasonId) {
		return $this->getElementDB("SELECT * FROM scrapersWithParents WHERE season = :season", array('season' => $seasonId));
	}
	
	public function getActiveScrapers() {
		return $this->getElementDB("SELECT scrapersWithParents.* FROM scrapersWithParents JOIN seasons on scrapersWithParents.season = seasons.id WHERE seasons.status = 'watched'", array());
	}
	
	public function getTVShowScrapers($showId) {
		return $this->getElementDB("SELECT * FROM scrapersWithParents WHERE tvShow = :tvShow", array('tvShow' => $showId));
	}
	
	// FILE
	
	public function addFile($showId, $p) {
		return $this->addElementDB("files", null, null, $p);
	}
	
	public function removeFile($id) {
		if ($this->removeElement("/tvscraper/tvshow/season/file[@id='$id']")) {
			return TRUE;
		} else {
			$this->error("Can't remove file $id");
			return FALSE;
		}
	}
	
	
	public function setFile($id, $p) {
		$file = $this->getElement("/tvscraper/tvshow/season/file[@id='$id']");
		if ($file === FALSE) {
			$this->error("Can't fine unique file $id");
			return FALSE;
		}

		$oldEpisodeId = strlen($file->getAttribute('episode')) > 0 ? $file->getAttribute('episode') : NULL;
		$newEpisodeId = NULL;
		$reset = FALSE;
			
		foreach ($p as $k => $v) {
			switch ($k) {
			case 'episode':
				$newEpisodeId = $v;
			case 'discard':
				$reset = TRUE;
				$this->setElementTextAttribute($file, $k, $v);
				break;
			case 'uri':
			case 'season':
			case 'scraper':
			case 'pubDate':
			case 'type':
				$this->setElementTextAttribute($file, $k, $v);
				break;
			default:
				$this->error("Unknown file parameter $k");
				return FALSE;
			}
		}

		if ($reset) {
			if ($oldEpisodeId != NULL) $this->resetEpisodeBestFile($oldEpisodeId);
			if ($newEpisodeId != NULL) $this->resetEpisodeBestFile($newEpisodeId);
		}
	
		return TRUE;
	}
	
	public function getFile($id) {
		$res = $this->getElementDB("SELECT * FROM files WHERE id = :id", array('id' => $id));
		if ($res === FALSE) return FALSE;
		if (count($res) != 1) return $this->error("Can't fine unique file $id");
		return $res[0];
	}
	

	public function getFilesForEpisode($id) {
		$x = $this->xPath->query("/tvscraper/tvshow/season/file[@episode='$id']");
		$res = array();
		for ($i = 0; $i < $x->length; $i++) {
			$res[] = $this->getFile($x->item($i)->getAttribute('id'));
		}
		return $res;
	}
	
	public function getFilesForSeason($id) {
		$x = $this->xPath->query("/tvscraper/tvshow/season/file[@season='$id']");
		$res = array();
		for ($i = 0; $i < $x->length; $i++) {
			$res[] = $x->item($i)->getAttribute('id');
		}
		return $res;
	}
	
	public function getFilesForScraper($id) {
		$x = $this->xPath->query("/tvscraper/tvshow/season/file[@scraper='$id']");
		$res = array();
		for ($i = 0; $i < $x->length; $i++) {
			$res[] = $x->item($i)->getAttribute('id');
		}
		return $res;
	}

	private static function sortByPreference($a, $b) {
			if (!isset($b['preference']) && !isset($a['preference'])) return 0;
			else if (!isset($a['preference'])) return -1;
			else if (!isset($b['preference'])) return 1;
			else return $a['preference'] - $b['preference'];
	}
	
	public function getBestFileForEpisode($id) {
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
		4 - If same pubDate, filed ID first (maybe not needed)
		5 - Take only the first row for each episode

		*/


		$q = "
		SELECT scrapers.id, preference
		FROM files 
		JOIN scrapers
		ON scraper = scrapers.id
		WHERE episode = :id
		AND pubDate < strftime('%s', 'now') - delay
		AND discard = 0
		GROUP BY scrapers.id, preference
		ORDER BY IFNULL(preference, -9999), min(pubDate + delay), scrapers.id
		LIMIT 1
		";

		$res = $this->getElementDB($q, array('id' => $id));
		if ($res === FALSE) return FALSE;
		if (count($res) == 0) {
			$this->log("No valid file for episode  $id");
			return null;
		}
		$scraperId = $res[0]['id'];

		$q = "
		SELECT files.*
		FROM files
		JOIN scrapers
		ON scraper = scrapers.id
		WHERE scraper = :scraper
		AND episode = :episode
		AND pubDate < strftime('%s', 'now') - delay
		AND discard = 0
		ORDER BY pubDate DESC, files.id DESC
		LIMIT 1
		";

		$res = $this->getElementDB($q, array('scraper' => $scraperId, 'episode' => $id));
		if ($res === FALSE) return FALSE;
		if (count($res) == 0) {
			$this->log("No valid file for episode  $id");
			return null;
		}

		$best = $res[0];

		if (!$this->setEpisode($id, array('bestFile' => $best['id']))) return FALSE;
		return $best;
	}	

	public function getAllWatchedBestFiles() {
		$q  = "SELECT episodes.id FROM episodes JOIN seasons ON episodes.season = seasons.id WHERE seasons.status = 'watched'";
		$list = $this->getElementDB($q, array());
		if ($list === FALSE) return FALSE;

		$res = array();
		for ($i = 0; count($list); $i++) {
			$file = $this->getBestFileForEpisode($list[$i]['id']);
			if ($file != NULL) $res[] = $file;
		}
		return $res;
	}
	
	public function getBestFilesForSeason($id) {
		$this->log("Checking best file for season $id");

		$res = array();
		$episodes = $this->getSeasonEpisodes($id);
		foreach ($episodes as $e) {
			$file = $this->getBestFileForEpisode($e['id']);
			if ($file != NULL) $res[] = $file;
		}
		return $res;
	}

	
	// SCRAPED SEASON
	
	public function addScrapedSeason($scraperId, $p) {
		return $this->addElementDB("scrapedSeasons", "scraper", $scraperId, $p);
	}
	
	public function removeScrapedSeason($id) {
		$this->log("Removing scraped season $id...");
		if ($this->removeElement("/tvscraper/tvshow/scraper/scrapedSeason[@id='$id']")) {
			return TRUE;
		} else {
			$this->error("Can't remove scraped season $id");
			return FALSE;
		}
	}
	
	public function setScrapedSeason($id, $p) {
		$this->log("Searching for scraped season $id");
		$scrapedSeason = $this->getElement("/tvscraper/tvshow/scraper/scrapedSeason[@id='$id']");
		if ($scrapedSeason === FALSE) {
			$this->error("Could not find unique scraped season $id");
			return FALSE;
		}
			
		foreach ($p as $k => $v) {
			switch ($k) {
				case 'uri':
				case 'n':
				case 'hide':
				case 'tbn':
					$this->setElementTextAttribute($scrapedSeason, $k, $v);
					break;
				default:
					$this->error("Unknown scraped season parameter $k");
					return FALSE;
			}
		}
	
		return TRUE;
	}
	
	public function getScrapedSeason($id) {
		$scrapedSeason = $this->getElement("/tvscraper/tvshow/scraper/scrapedSeason[@id='$id']");
		if ($scrapedSeason === FALSE) {
			$this->error("Could not find unique scraped season $id");
			return FALSE;
		}
	
		$res = $this->getElementAttributes($scrapedSeason);
		$res['scraper'] = $scrapedSeason->parentNode->getAttribute('id');
		$res['source'] = $scrapedSeason->parentNode->getAttribute('source');
	
		return $res;
	}

	public function getScrapedSeasons($showId) {
		$scrapedSeasons = $this->xPath->query("/tvscraper/tvshow[@id='$showId']/scraper/scrapedSeason");
		$res = array();

		for ($i = 0; $i < $scrapedSeasons->length; $i++) {
			$res[] = $this->getScrapedSeason($scrapedSeasons->item($i)->getAttribute('id'));
		}

		return $res;
	}
	
	public function getScrapedSeasonsTBN() {
		$scrapedSeasons = $this->xPath->query("/tvscraper/tvshow/scraper/scrapedSeason[@tbn='1']");
		$res = array();
	
		for ($i = 0; $i < $scrapedSeasons->length; $i++) {
			$res[] = $this->getScrapedSeason($scrapedSeasons->item($i)->getAttribute('id'));
		}
	
		return $res;
	}
	
	
	public function getScrapedSeasonFromUri($scraperId, $uri, $n = NULL) {
		$query = "/tvscraper/tvshow/scraper[@id='$scraperId']/scrapedSeason[@uri='$uri'".($n == NULL ? "" : " and @n='$n'") ."]";
		$scrapedSeason = $this->getElement($query);
		if ($scrapedSeason === FALSE) {
			// $this->error("Could not find unique scraped season with URI $uri for scraper $id");
			$this->log("Scraped season with URI $uri and scraperId $scraperId not found");
			return NULL;
		}
	
		return $this->getScrapedSeason($scrapedSeason->getAttribute('id'));
	}
	
	public function createSeasonScraperFromScraped($id) {
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
		
		if ($season == NULL) {
			$this->log("Season $n does not exist yet. Creating...");
			$season = $this->addSeason($scraper['tvshow'], array('n' => $scrapedSeason['n'], 'status' => 'watched'));
			if ($season === FALSE) return FALSE;
		}
		
		$this->log("Adding new scraper to season " . $season['id']);
		$scraperId = $this->addScraper($season['id'], array(
				'uri' => $scrapedSeason['uri'],
				'source' => $scraper['source']
		));

		if ($scraperId != FALSE) {
			$this->setScrapedSeason($id, array('hide' => '1'));
		}
		
		return $scraperId;
		
	}
	
	
	
}

?>
