<?php

require_once('Logger.php');
require_once('TVShowUtils.php');

class TVShowScraperDBSQLite extends TVShowScraperDB {

	public function __construct($params) {
		$fileName = $params['dbFileName'];
		if (file_exists($fileName)) {
			$this->db = new PDO("sqlite:$fileName", null, null, array());
		} else {
			// CREATE new DB
			$this->db = new PDO("sqlite:$fileName", null, null, array());
			$buildSql = file_get_contents(__DIR__ . "/TVShowScraperDBSQLite.sql");
			$this->db->exec($buildSql);
		}
		$this->db->exec("PRAGMA foreign_keys = 'ON'");
	}

	public function beginTransaction() {
		return $this->db->beginTransaction();
	}

	public function inTransaction() {
		return $this->db->inTransaction();
	}

	
	public function rollBack() {
		return $this->db->rollBack();
	}
	
	public function commit() {
		return $this->db->commit();
	}

	
	public function save($fileName = null) {
		return $this->db->inTransaction() ? $this->db->commit() : TRUE;
	}
	
	protected function addElement($elementStore, $parentKey, $keyValue, $params) {	
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

		$query = "INSERT INTO $elementStore($columnList) VALUES($placeholderList)";
		$this->log("Preparing query $query");
		$st = $this->db->prepare($query);
		if ($st == null) {
			return $this->error("Failed to prepare query: " . implode(', ', $this->db->errorInfo()));
		}
		if ($parentKey != null) {
			$this->log("Executing with params $parentValue, " . implode(', ', $params));
			if (! $st->execute(array_merge(array($parentKey => $keyValue), $params))) return $this->error("Failed to execute query: " . implode(', ', $this->db->errorInfo()));
		} else {
			$this->log("Executing with params " . implode(', ', $params));
			if (! $st->execute($params)) return $this->error("Failed to execute query: " . implode(', ', $this->db->errorInfo()));
		}
		if ($st->rowCount() == 1) {
			return array_merge(array('id' => $this->db->lastInsertId()), $params);
		}
		return $this->error("Failed to execute query: " . implode(', ', $this->db->errorInfo()));
	}

	protected function setElement($elementStore, $elementKey, $keyValue, $params) {
		$query = "UPDATE $elementStore SET ";
		$p = array( $elementKey => $keyValue );
		foreach ($params as $k => $v) {
			$query .= "$k = :$k,";
			$p[$k] = $v == '_REMOVE_' ? null : $v;
		}
		$query = rtrim($query, ",");
		$query .= " WHERE $elementKey = :$elementKey";

		$wasInTransaction = $this->inTransaction();
		if (! $wasInTransaction) $this->beginTransaction();

		$this->log("Preparing query $query");
		$st = $this->db->prepare($query);
		if ($st == null) {
			return $this->error("Failed to prepare query: " . implode(', ', $this->db->errorInfo()));
		}

		$this->log("Executing with params " . implode(', ', $params) . ", $keyValue");
		if (! $st->execute($p)) return $this->error("Failed to execute query: " . implode(', ', $this->db->errorInfo()));

		if ($st->rowCount() != 1) {
			if (! $wasInTransaction) $this->rollBack();
			return $this->error("Set query affected ". $st->rowCount() ." rows, expected 1!");
		} else {
			if (! $wasInTransaction) $this->commit();
			return TRUE;
		}
	}

	protected function removeElement($elementStore, $elementKey, $keyValue) {
		$query = "DELETE FROM $elementStore WHERE $elementKey = ?";

		$this->log("Preparing query $query");
		$st = $this->db->prepare($query);
		if ($st == null) {
			return $this->error("Failed to prepare query: " . implode(', ', $this->db->errorInfo()));
		}
		$this->log("Executing with param $keyValue");
		if (! $st->execute(array($keyValue))) return $this->error("Failed to execute query: " . implode(', ', $this->db->errorInfo()));

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
		if (! $st->execute($p)) return $this->error("Failed to execute query: " . implode(', ', $this->db->errorInfo()));

		$res = array();

		while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
			foreach ($r as $k => $v) {
				if ($v == null) unset($r[$k]);
			}
			$res[] = $r;
		}

		return $res;
	}


	// TVSHOW


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
			if (! $st->execute()) return $this->error("Failed to execute query: " . implode(', ', $this->db->errorInfo()));
		} else {
			$this->log("Executing query with param $id");
			if (! $st->execute(array('id'=>$id))) return $this->error("Failed to execute query: " . implode(', ', $this->db->errorInfo()));
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
		return $this->validateParams($p, $this->validParamsSeason()) ? $this->addElement('seasons', 'tvshow', $show, $p) : FALSE;
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
		if ( $this->validateParams($p, $this->validParamsSeason()) && $this->setElement('seasons', 'id', $id, $p) ) {
			$this->log("Season succesfully updated");
			return $this->getSeason($id);
		} else {
			return FALSE;
		}
	}
	
	public function getSeason($id) {
		$res = $this->getElementDB("SELECT * FROM seasons WHERE id = :id", array('id' => $id));
		if ($res === FALSE) return FALSE;
		return count($res) == 1 ? $res[0] : $this->error("Found " . count($res) . " matches for season $id");
	}
	
	public function getSeasonFromN($showId, $n) {
		$res = $this->getElementDB("SELECT * FROM seasons WHERE tvShow = :tvShow and n = :n", array('tvShow' => $showId, 'n' => $n));
		if ($res === FALSE) return FALSE;
		return count($res) == 1 ? $res[0] : null;
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
		return $this->validateParams($p, $this->validParamsEpisode()) ? $this->addElement('episodes', 'season', $seasonId, $p) : FALSE;
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

		if ( $this->validateParams($p, $this->validParamsEpisode()) && $this->setElement('episodes', 'id', $id, $p) ) {
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
		if ($res === FALSE) return FALSE;
		return count($res) == 1 ? $res[0] : $this->error("Found " . count($res) . " matches for episode $id");
	}

	public function getEpisodeFromIndex($showId, $season, $episode) {
		$res = $this->getElementDB("SELECT episodes.* FROM episodes JOIN seasons ON episodes.season = seasons.id WHERE tvshow = :show AND seasons.n = :season AND episodes.n = :episode", array('show' => $showId, 'season' => $season, 'episode' => $episode));
		if ($res === FALSE) return FALSE;
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

		$newScraper = $this->addElement('scrapers', null, null, $p);

		if ($newScraper === FALSE) {
			if (!$wasInTransaction) $this->db->rollBack();
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

		if ( $this->validateParams($p, $this->validParamsScraper()) && $this->setElement('scrapers', 'id', $id, $p) ) {
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
				if (!$this->resetEpisodeBestFile($e['id'])) return FALSE;
			}
		}
		return TRUE;
	}
	
	public function getScraper($id) {
		$res = $this->getElementDB("SELECT * FROM scrapersWithParents WHERE id = :id", array('id' => $id));
		if ($res === FALSE) return FALSE;
		return count($res) == 1 ? $res[0] : $this->error("Found " . count($res) . " matches for season $id");
	}

	
	public function getSeasonScrapers($seasonId) {
		return $this->getElementDB("SELECT * FROM scrapersWithParents WHERE season = :season", array('season' => $seasonId));
	}
	
	public function getActiveScrapers() {
		return $this->getElementDB("SELECT scrapersWithParents.* FROM scrapersWithParents LEFT JOIN seasons on scrapersWithParents.season = seasons.id WHERE seasons.status = 'watched' OR scrapersWithParents.tvShow IS NOT NULL", array());
	}
	
	public function getTVShowScrapers($showId) {
		return $this->getElementDB("SELECT * FROM scrapersWithParents WHERE tvShow = :tvShow", array('tvShow' => $showId));
	}
	
	// FILE


	protected function validParamsFile() {
		return array(
			'discard'		=> 1,
			'uri'			=> 1,
			'pubDate'		=> 1,
			'type'			=> 1,
			'scraper'		=> 1
		);
	}
	
	public function addFile($episodeId, $p) {
		return $this->validateParams($p, $this->validParamsFile()) ? $this->addElement('files', 'episode', $episodeId, $p) : FALSE;
	}
	
	public function removeFile($id) {
		return $this->removeElementDB('files', 'id', $id);
	}
	
	
	public function setFile($id, $p) {

		if (! $this->validateParams($p, $this->validParamsFile())) return FALSE;

		$wasInTransaction = $this->db->inTransaction();
		if (!$wasInTransaction) $this->db->beginTransaction();

		if (isset($p['discard'])) {
			$oldFile = $this->getFile($id);
			if ($oldFile === FALSE) {
				if (!$wasInTransaction) $this->db->rollBack();
				return FALSE;
			} 
			if (! $this->resetEpisodeBestFile($oldFile['episode'])) {
				if (!$wasInTransaction) $this->db->rollBack();
				return FALSE;
			}
		}
		if (!$this->setElement('files', 'id', $id, $p)) {
			if (!$wasInTransaction) $this->db->rollBack();
			return FALSE;
		} else {
			if (!$wasInTransaction) $this->db->rollBack();
			$this->log("File $id successfully updated");
			return TRUE;
		}
	}
	
	public function getFile($id) {
		$res = $this->getElementDB("SELECT * FROM files WHERE id = :id", array('id' => $id));
		if ($res === FALSE) return FALSE;
		if (count($res) != 1) return $this->error("Can't fine unique file $id");
		return $res[0];
	}
	

	public function getFilesForEpisode($id) {
		return $this->getElementDB("SELECT * FROM files WHERE episode = :episode", array('episode' => $id));
	}
	
	public function getFilesForSeason($id) {
		return $this->getElementDB("SELECT files.* FROM files JOIN episodes ON episode = episodes.id WHERE season = :season", array('season' => $id));
	}
	
	public function getFilesForScraper($id) {
		return $this->getElementDB("SELECT * FROM files WHERE scraper = :scraper", array('scraper' => $id));
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

	protected function validParamsScrapedSeason() {
		return array(
			'uri'	=> 1,
			'n'		=> 1,
			'hide'	=> 1,
			'tbn'	=> 1
		);
	}
	
	public function addScrapedSeason($scraperId, $p) {
		return $this->validateParams($p, $this->validParamsScrapedSeason()) ? $this->addElement('scrapedSeasons', 'scraper', $scraperId, $p) : FALSE;
	}
	
	public function removeScrapedSeason($id) {
		$this->log("Removing scraped season $id...");
		return $this->removeElementDB('scrapedSeasons', 'id', $id);
	}
	
	public function setScrapedSeason($id, $p) {
		return $this->validateParams($p, $this->validParamsScrapedSeason()) ? $this->setElement('scrapedSeasons', 'id', $id, $p) : FALSE;
	}
	
	public function getScrapedSeason($id) {
		$q = "SELECT scrapedSeasons.*, scrapers.source FROM scrapedSeasons JOIN scrapers ON scrapedSeasons.scraper = scrapers.id WHERE scrapedSeasons.id = :id";
		$res = $this->getElementDB($q, array('id' => $id));

		if ($res === FALSE) return FALSE;
		else if (count($res) != 1) return $this->error("Can't find a single match for scrapedSeason $id");
		else return $res['0'];
	}

	public function getScrapedSeasons($showId) {
		$q = "
		SELECT scrapedSeasons.*, scrapers.source 
		FROM scrapedSeasons 
		JOIN scrapers 
		ON scrapedSeasons.scraper = scrapers.id 
		JOIN tvshowScrapers
		ON tvshowScrapers.scraper = scrapers.id
		WHERE tvShow = :id
		";
		return $this->getElementDB($q, array('id' => $showId));
	}
	
	public function getScrapedSeasonsTBN() {
		$q = "SELECT scrapedSeasons.*, scrapers.source FROM scrapedSeasons JOIN scrapers ON scrapedSeasons.scraper = scrapers.id WHERE tbn = 1";
		return $this->getElementDB($q);
	}
	
	
	public function getScrapedSeasonFromUri($scraperId, $uri, $n = NULL) {
		$q = "SELECT scrapedSeasons.*, scrapers.source FROM scrapedSeasons JOIN scrapers ON scrapedSeasons.scraper = scrapers.id WHERE scrapedSeasons.uri = :uri";
		$p = array('uri' => $uri);

		if ($n != NULL) {
			$q .= " AND n = :n";
			$p['n'] = $n;
		}

		$res = $this->getElementDB($q, $p);

		if ($res === FALSE) return FALSE;
		else if (count($res) == 0) {
			$this->log("Scraped season with URI $uri and n $n not found");
			return NULL;
		} else if (count($res) == 1) {
			return $res['0'];
		} else {
			return $this->error("Multiple matched for URI $uri and n $n");
		}
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

		if ($season === FALSE) return FALSE;
		
		$wasInTransaction = $this->db->inTransaction();
		if (!$wasInTransaction) $this->db->beginTransaction();

		if ($season == NULL) {
			$this->log("Season $n does not exist yet. Creating...");
			$season = $this->addSeason($scraper['tvshow'], array('n' => $scrapedSeason['n'], 'status' => 'watched'));
			if ($season === FALSE) {
				if (!$wasInTransaction) $this->db->rollBack();
				return FALSE;
			}
		}
		
		$this->log("Adding new scraper to season " . $season['id']);
		$newScraper = $this->addScraper($season['id'], 'season', array(
				'uri' => $scrapedSeason['uri'],
				'source' => $scraper['source']
		));

		if ($newScraper === FALSE) {
			if (!$wasInTransaction) $this->db->rollBack();
			return FALSE;
		}

		if (!$this->setScrapedSeason($id, array('hide' => '1'))) {
			if (!$wasInTransaction) $this->db->rollBack();
			return FALSE;
		}

		if (!$wasInTransaction) $this->db->commit();
		return $newScraper['id'];
		
	}
	
	
	
}

?>
