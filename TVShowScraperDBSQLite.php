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
	
	public function save($fileName) {
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

		$this->log("Executing query with param $id");
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
	
	public function addScraper($rootId, $type, $p) {
		$q = "";
		if ($type == "season") {
			$q = "insert into seasonScrapers(seasonId, scraperId) values (?, ?)";
		} else if ($type == "tvShow") {
			$q = "insert into tvShowScrapers(tvShowId, scraperId) values (?, ?)";
		} else {
			$this->error("Invalid scraper type $type");
			return null;
		}
		
		$wasInTransaction = $this->db->inTransaction();
		if (!$wasInTransaction) $this->db->beginTransaction();

		$newScraper = $this->addElementDB('scrapers', null, null, $p);
		if ($newScraper == null) {
			if (!$wasInTransaction) $this->db->rollBack();
			return null;
		}

		$this->log("Preparing query $q");
		$st = $this->db->prepare($q);
		if ($st == null) {
			$this->error("Can't prepare query: " . implode(', ', $this->db->errorInfo()));
			if (!$wasInTransaction) $this->db->rollBack();
			return null;
		}
		$this->log("Executing with $rootId, ". $newScraper['id']);
		$st->execute(array($rootId, $newScraper['id']));
		if ($st->rowCount() != 1) {
			$this->error("Can't execute query: " . implode(', ', $this->db->errorInfo()));
			if (!$wasInTransaction) $this->db->rollBack();
			return null;
		}
		if (!$wasInTransaction) $this->db->commit();

		return $newScraper;
	}
	
	public function removeScraper($id) {
		$this->log("Removing scraper $id...");
		if ($this->removeElement("/tvscraper/tvshow//scraper[@id='$id']")) {
		    
            $q = $this->xPath->query("/tvscraper/tvshow/season/file[@scraper='$id']");
            for ($i = 0; $i < $q->length; $i++) {
                if ($this->resetEpisodeBestFile($q->item($i)->getAttribute('episode')) === FALSE) return FALSE;
            }
			return TRUE;
		} else {
			$this->error("Can't remove scraper $id");
			return FALSE;
		}
	}
	
	public function setScraper($id, $p) {
		$this->log("Searching for scraper $id");
		$scraper = $this->getElement("/tvscraper/tvshow//scraper[@id='$id']");
		if ($scraper === FALSE) {
			$this->error("Could not find unique scraper $id");
			return FALSE;
		}


		$resetBest = FALSE;	
		foreach ($p as $k => $v) {
			switch ($k) {
			case 'preference':
			case 'delay':
				$resetBest = TRUE;
			case 'uri':
			case 'source':
			case 'autoAdd':
			case 'notify':				
				$this->setElementTextAttribute($scraper, $k, $v);
				break;
			default:
				$this->error("Unknown scraper parameter $k");
				return FALSE;
			}
		}


		if ($resetBest && $scraper->parentNode->nodeName == 'season') {
			$this->resetBestFilesForSeason($scraper->parentNode->getAttribute('id'));
		}
	
		return TRUE;
	}

	public function resetBestFilesForSeason($id) {
		$episodes = $this->getSeasonEpisodes($id);
		foreach ($episodes as $e) {
			if (isset($e['bestFile'])) {
				$this->resetEpisodeBestFile($e['id']);
			}
		}
	}
	
	public function getScraper($id) {
		$scraper = $this->getElement("/tvscraper/tvshow/scraper[@id='$id']");
		if ($scraper === FALSE) {
			$scraper = $this->getElement("/tvscraper/tvshow/season/scraper[@id='$id']");
			if ($scraper === FALSE) {
				$this->error("Could not find unique scraper $id");
				return FALSE;
			}
		}
		
		$res = $this->getElementAttributes($scraper);
		$parent = $scraper->parentNode;
		$res[$parent->tagName] = $parent->getAttribute('id');
		
		return $res;
	}

	
/*	public function getScraperData($id) {
		$scraper = $this->getElement("/tvscraper/tvshow//scraper[@id='$id']");
		if ($scraper === FALSE) return FALSE;
		
		$scraperData = $this->getElement("/tvscraper/tvshow//scraper[@id='$id']/scraper-data");
		if ($scraperData === FALSE) {
			$newId = $this->addElement('scraper-data', "/tvscraper/tvshow//scraper[@id='$id']");
			if ($newId == NULL) return FALSE;
			$scraperData = $this->getElement("/tvscraper/tvshow//scraper[@id='$id']/scraper-data");
		}

		return $scraperData;
	}*/

	
	public function getSeasonScrapers($seasonId) {
		$this->log("Searching scrapers for season $seasonId");
		$x = $this->xPath->query("/tvscraper/tvshow/season[@id='$seasonId']/scraper");
		$res = array();
		for ($i = 0; $i < $x->length; $i++) {
			$this->log("Searching scraper " . $x->item($i)->getAttribute('id'));
			$res[] = $this->getScraper($x->item($i)->getAttribute('id'));
		}
		return $res;
	}
	
	public function getActiveScrapers() {
		$x = $this->xPath->query("/tvscraper/tvshow/scraper");
		$res = array();
		for ($i = 0; $i < $x->length; $i++) {
			$res[] = $this->getScraper($x->item($i)->getAttribute('id'));
		}
		$x = $this->xPath->query("/tvscraper/tvshow/season[@status='watched']/scraper");
		for ($i = 0; $i < $x->length; $i++) {
			$res[] = $this->getScraper($x->item($i)->getAttribute('id'));
		}
		return $res;
	}
	
	public function getTVShowScrapers($showId) {
		$this->log("Searching scrapers for TV show $showId");
		$x = $this->xPath->query("/tvscraper/tvshow[@id='$showId']/scraper");
		$res = array();
		for ($i = 0; $i < $x->length; $i++) {
			$this->log("Searching scraper " . $x->item($i)->getAttribute('id'));
			$res[] = $this->getScraper($x->item($i)->getAttribute('id'));
		}
		return $res;
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
		$file = $this->getElement("/tvscraper/tvshow/season/file[@id='$id']");
		if ($file === FALSE) {
			$this->error("Can't fine unique file $id");
			return FALSE;
		}
		
		$res = $this->getElementAttributes($file);
		return $res;
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

		$scrapers = $this->getSeasonScrapers($episode['season']);
		if ($scrapers === FALSE) return FALSE;

		usort($scrapers, array('self', 'sortByPreference'));

		$best = NULL;
		$bestDelay = 0;
		$lastPref = NULL;

		foreach ($scrapers as $s) {
			$this->log("Checking files for scraper " . $s['id']);

			if (isset($s['preference'])) {
				if ($lastPref != NULL && $lastPref < $s['preference'] && $best != NULL) {
					$this->log("File already found and scraper preference lower. End.");
					break;
				}
				else $lastPref = $s['preference'];
			}
			
			$sDelay = 0;
			$q = "/tvscraper/tvshow/season/file[@episode='$id' and @scraper='". $s['id']. "'";
		    if (isset($s['delay'])) {
				$sDelay = $s['delay'];
				$q .= " and @pubDate <= '" . (time() - $sDelay) . "'";
			}

			$q .= "]";
			
			$x = $this->xPath->query($q);

			for ($i = 0; $i < $x->length; $i++) {

				$file = $x->item($i);		

				if ($file->getAttribute('discard') == 1) continue;

				if (strlen($file->getAttribute('type')) == 0 || $file->getAttribute('type') == 'ed2k') {
					$linkData = parseED2KURI($file->getAttribute('uri'));
					if ($linkData === FALSE) {
						$this->log("Invalid file " . $file->getAttribute('id'));
						continue;
					} else if (preg_match('/\.srt$/', $linkData['fileName'])) {
						$this->log("File " . $file->getAttribute('id') . " is a subtitle, skipping...");
						continue;
					}
				}
				$episodeId = $file->getAttribute('episode');
						
				if ($best == NULL || $file->getAttribute('pubDate') + $sDelay < $best->getAttribute('pubDate') + $bestDelay) {
					$best = $file;
					$bestDelay = $sDelay;
					$this->log("Found elder file " . $file->getAttribute('id') . " for episode " . $file->getAttribute('episode'));
				} else {
					$this->log("Found more recent file " . $file->getAttribute('id') . " for episode " . $file->getAttribute('episode'));
					if ($best->getAttribute('scraper') == $file->getAttribute('scraper')) {
						$this->log("Files are from the same scaper. Keeping latest.");
						$best = $file;
					$bestDelay = $sDelay;
					} else {
						$this->log("Files are from different scaper. Keeping oldest.");
					}
				}
			}
		}
		
		// TODO: Check orphan files (files with no scraper or with removed scraper) ?
		if ($best === NULL) {
			return NULL;
		} else {
			$this->setEpisode($id, array('bestFile' => $best->getAttribute('id')));	
			return $this->getFile($best->getAttribute('id'));
		}
		
	}	

	public function getAllWatchedBestFiles() {
		$x = $this->xPath->query("/tvscraper/tvshow/season[@status='watched']/episode");
		$res = array();
		for ($i = 0; $i < $x->length; $i++) {
			$file = $this->getBestFileForEpisode($x->item($i)->getAttribute('id'));
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

		$season = $this->getSeason($id);
		if ($season === FALSE) return FALSE;

		$scrapers = $this->getSeasonScrapers($id);
		if ($scrapers === FALSE) return FALSE;

		/*
		usort($scrapers, function ($a, $b) {
			if (!isset($b['preference']) && !isset($a['preference'])) return 0;
			else if (!isset($a['preference'])) return -1;
			else if (!isset($b['preference'])) return 1;
			else return $a['preference'] - $b['preference'];
		});
		*/

		usort($scrapers, array('self', 'sortByPreference'));

		$best = array();
		$lastPref = array();

		foreach ($scrapers as $s) {
			$this->log("Checking files for scraper " . $s['id']);

			$q = "/tvscraper/tvshow/season/file[@season='$id' and @scraper='". $s['id']. "'";
			if (isset($s['delay'])) $q .= " and @pubDate <= '" . (time() - $s['delay']) . "'";
			$q .= "]";

			$this->log("Query for candidate files: $q");

			$x = $this->xPath->query($q);


			for ($i = 0; $i < $x->length; $i++) {
				// TODO how do we handle subtitiles??
			
				$file = $x->item($i);		
				if (strlen($file->getAttribute('type')) == 0 || $file->getAttribute('type') == 'ed2k') {
					$linkData = parseED2KURI($file->getAttribute('uri'));
					if ($linkData === FALSE) {
						$this->log("Invalid file " . $file->getAttribute('id'));
						continue;
					} else if (preg_match('/\.srt$/', $linkData['fileName'])) {
						$this->log("File " . $file->getAttribute('id') . " is a subtitle, skipping...");
						continue;
					}
				}
				$episodeId = $file->getAttribute('episode');

				if (isset($s['preference'])) {
					if (isset($lastPref[$episodeId]) && $lastPref[$episodeId] < $s['preference'] && isset($best[$episodeId])) {
						$this->log("File already found for this episode and scraper preference lower. Next.");
						continue;
					} else {
						$lastPref[$episodeId] = $s['preference'];
					}
				}
						
				if (! isset($best[$episodeId]) || $file->getAttribute('pubDate') < $best[$episodeId]->getAttribute('pubDate')) {
					$best[$episodeId] = $file;
					$this->log("Found elder file " . $file->getAttribute('id') . " for episode " . $file->getAttribute('episode'));
				} else {
					$this->log("Found more recent file " . $file->getAttribute('id') . " for episode " . $file->getAttribute('episode'));
				}
			}
		}


		// TODO: Check orphan files (files with no scraper or with removed scraper) ?
		
		$res = array();
		foreach ($best as $b) {
			// Checking for files from the same scraper published later (proper-repack)
			
			$fileId = $b->getAttribute('id');
			$episodeId = $b->getAttribute('episode');
			$scraperId = $b->getAttribute('scraper');
			$pubDate = $b->getAttribute('pubDate');
			$files = $this->getFilesForEpisode($episodeId);

			foreach ($files as $f) {
				if ($f['scraper'] == $scraperId && $f['pubDate'] > $pubDate) {
					$this->log("Found newer file " . $f['id'] . " from the same scraper as $fileId. Swapping files...");
					$pubDate = $f['pubDate'];
					$fileId = $f['id'];
				}
			}

			$res[] = $this->getFile($fileId);
		}
		return $res;
	}

	
	// SCRAPED SEASON
	
	public function addScrapedSeason($scraperId, $p) {
		return $this->addElementDB("scrapedSeasons", "scraperId", $scraperId, $p);
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
