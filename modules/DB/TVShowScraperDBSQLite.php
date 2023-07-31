<?php

namespace modules\DB;

require_once('Logger.php');
require_once('TVShowUtils.php');
require_once('TVShowScraperDB.php');

use \PDO;

class TVShowScraperDBSQLite extends TVShowScraperDB
{

	public function __construct($params)
	{
		$fileName = $params['dbFileName'];
		if (file_exists($fileName)) {
			$this->db = new PDO("sqlite:$fileName", null, null, array());
		} else {
			// CREATE new DB
			$this->db = new PDO("sqlite:$fileName", null, null, array());
			$buildSql = file_get_contents(__DIR__ . "/TVShowScraperDBSQLite.sql");
			$this->db->exec($buildSql);
		}
		$this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT);
		$this->db->exec("PRAGMA foreign_keys = 'ON'");
	}

	public function beginTransaction()
	{
		$this->log("Beginning transaction");
		return $this->db->beginTransaction();
	}

	public function inTransaction()
	{
		return $this->db->inTransaction();
	}


	public function rollBack()
	{
		$this->log("Rolling back transaction");
		return $this->db->rollBack();
	}

	public function commit()
	{
		$this->log("Committing transaction");
		return $this->db->commit();
	}


	public function save($fileName = null)
	{
		return $this->inTransaction() ? $this->commit() : TRUE;
	}

	protected function addElement($table, $parentKey, $parentValue, $params)
	{
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
			if (!$st->execute(array_merge(array($parentKey => $parentValue), $params))) return $this->error("Failed to execute query: " . implode(', ', $st->errorInfo()));
		} else {
			$this->log("Executing with params " . implode(', ', $params));
			if (!$st->execute($params)) return $this->error("Failed to execute query: " . implode(', ', $st->errorInfo()));
		}
		if ($st->rowCount() == 1) {
			return array_merge(array('id' => $this->db->lastInsertId()), $params);
		}
		return $this->error("Failed to execute query: " . implode(', ', $st->errorInfo()));
	}

	protected function setElementDB($table, $key, $value, $params)
	{
		return $this->setElement($table, $key, $value, $params);
	}

	protected function setElement($table, $key, $value, $params)
	{
		$query = "UPDATE $table SET ";
		$p = array($key => $value);
		foreach ($params as $k => $v) {
			$query .= "$k = :$k,";
			$p[$k] = $v == '_REMOVE_' ? null : $v;
		}
		$query = rtrim($query, ",");
		$query .= " WHERE $key = :$key";

		$wasInTransaction = $this->inTransaction();
		if (!$wasInTransaction) $this->beginTransaction();

		$this->log("Preparing query $query");
		$st = $this->db->prepare($query);
		if ($st == null) {
			if (!$wasInTransaction) $this->rollBack();
			return $this->error("Failed to prepare query: " . implode(', ', $this->db->errorInfo()));
		}

		$this->log("Executing with params " . implode(', ', $params) . ", $value");
		if (!$st->execute($p)) {
			if (!$wasInTransaction) $this->rollBack();
			return $this->error("Failed to execute query: " . implode(', ', $st->errorInfo()));
		}
		if ($st->rowCount() != 1) {
			if (!$wasInTransaction) $this->rollBack();
			return $this->error("Set query affected " . $st->rowCount() . " rows, expected 1!");
		} else {
			if (!$wasInTransaction) $this->commit();
			return TRUE;
		}
	}

	protected function removeElement($table, $key, $value)
	{
		$query = "DELETE FROM $table WHERE $key = ?";

		$this->log("Preparing query $query");
		$st = $this->db->prepare($query);
		if ($st == null) {
			return $this->error("Failed to prepare query: " . implode(', ', $this->db->errorInfo()));
		}
		$this->log("Executing with param $value");
		if (!$st->execute(array($value))) return $this->error("Failed to execute query: " . implode(', ', $st->errorInfo()));

		return TRUE;
	}

	protected function getElementByKey($table, $key = null)
	{
		return $this->getElementByAttribute($table, 'id', $key);
	}

	protected function getElementByParentKey($table, $parentKey)
	{
		switch ($table) {
			case 'seasons':
				return $this->getElementByAttribute($table, 'tvShow', $parentKey);
			case 'episodes':
				return $this->getElementByAttribute($table, 'season', $parentKey);
			case 'files':
				return $this->getElementByAttribute($table, 'episode', $parentKey);
			default:
				return $this->error("getElementByParentKey not implemented for $table");
		}
	}


	protected function getElementByAttribute($table, $column, $value = null)
	{
		$q = null;
		$p = array();

		switch ($table) {
			case 'tvShows':
				$table = 'tvShowsWithStats';
				break;
			case 'scrapers':
				$table = 'scrapersWithParents';
				if ($column == 'active' && $value) {
					$q = "SELECT scrapersWithParents.* FROM scrapersWithParents LEFT JOIN seasons on scrapersWithParents.season = seasons.id WHERE seasons.status = 'watched' OR scrapersWithParents.tvShow IS NOT NULL";
				}
				break;
			case 'files':
				if ($column == 'season') {
					$q = "SELECT files.* FROM files JOIN episodes ON episode = episodes.id WHERE season = :season";
					$p['season'] = $value;
				}
				break;
			case 'scrapedSeasons':
				if ($column == 'tvShow') {
					$q = "
					SELECT scrapedSeasons.*, scrapers.source 
					FROM scrapedSeasons 
					JOIN scrapers 
					ON scrapedSeasons.scraper = scrapers.id 
					JOIN tvshowScrapers
					ON tvshowScrapers.scraper = scrapers.id
					WHERE tvShow = :id
					";
					$p['id'] = $value;
				} else {
					$q = "SELECT scrapedSeasons.*, scrapers.source FROM scrapedSeasons JOIN scrapers ON scrapedSeasons.scraper = scrapers.id ";
					if ($value !== null) {
						$q .= "WHERE scrapedSeasons.$column = :$column";
						$p[$column] = $value;
					}
				}
				break;
			case 'episodes':
				if ($column == 'seasonStatus') {
					$q = "SELECT episodes.id FROM episodes JOIN seasons ON episodes.season = seasons.id WHERE seasons.status = :seasonStatus";
					$p[$column] = $value;
				}
				break;
		}

		if ($q === null) {
			$q = "SELECT * FROM $table ";
			if ($value !== null) {
				$q .= "WHERE $column = :value";
				$p['value'] = $value;
			}
		}

		$res = $this->getElementDB($q, $p);
		if ($res === FALSE) return FALSE;

		if ($table == 'tvShowsWithStats') {
			for ($i = 0; $i < count($res); $i++) {
				if (!isset($res[$i]['episodesWithFile'])) $res[$i]['episodesWithFile'] = 0;
			}
		}
		return $res;
	}

	protected function getElementDB($q, $p)
	{
		$this->log("Prepary query $q");

		$st = $this->db->prepare($q);
		if ($st == null) {
			$this->error("Failed to prepare query: " . implode(', ', $this->db->errorInfo()));
			return FALSE;
		}

		$this->log("Executing with params " . implode(', ', $p));
		if (!$st->execute($p)) return $this->error("Failed to execute query: " . implode(', ', $st->errorInfo()));

		$res = array();

		while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
			foreach ($r as $k => $v) {
				if ($v == null) unset($r[$k]);
			}
			$res[] = $r;
		}

		return $res;
	}

	/*
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
		4 - If same pubDate, filed ID first (maybe not needed)
		5 - Take only the first row for each episode

		*/
	/*

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

		$this->log("Selected best scraper $scraperId for episode $id");

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

		$this->log("Selected best file " . $best['id'] . " for episode $id");

		if (!$this->setEpisode($id, array('bestFile' => $best['id']))) return FALSE;
		return $best;
	}
*/
}
