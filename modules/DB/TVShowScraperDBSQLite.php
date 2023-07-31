<?php

namespace modules\DB;

require_once('Logger.php');
require_once('TVShowUtils.php');
require_once('TVShowScraperDB.php');

use modules\Logger\LoggerApplicationTrait;
use \PDO;

class TVShowScraperDBSQLite implements TVShowScraperDBInterface
{
	use LoggerApplicationTrait;

	private PDO $db;

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

	public function beginTransaction(): bool
	{
		$this->log("Beginning transaction");
		return $this->db->beginTransaction();
	}

	public function inTransaction(): bool
	{
		return $this->db->inTransaction();
	}


	public function rollBack(): bool
	{
		$this->log("Rolling back transaction");
		return $this->db->rollBack();
	}

	public function commit(): bool
	{
		$this->log("Committing transaction");
		return $this->db->commit();
	}


	public function save(): bool
	{
		return $this->inTransaction() ? $this->commit() : TRUE;
	}

	public function addElement(string $elementStore, ?string $parentKey = null, ?string $keyValue = null, array $params): array|bool
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

		$query = "INSERT INTO $elementStore($columnList) VALUES($placeholderList)";
		$this->log("Preparing query $query");
		$st = $this->db->prepare($query);
		if ($st == null) {
			return $this->error("Failed to prepare query: " . implode(', ', $this->db->errorInfo()));
		}
		if ($parentKey != null) {
			$this->log("Executing with params $keyValue, " . implode(', ', $params));
			if (!$st->execute(array_merge(array($parentKey => $keyValue), $params))) return $this->error("Failed to execute query: " . implode(', ', $st->errorInfo()));
		} else {
			$this->log("Executing with params " . implode(', ', $params));
			if (!$st->execute($params)) return $this->error("Failed to execute query: " . implode(', ', $st->errorInfo()));
		}
		if ($st->rowCount() == 1) {
			return array_merge(array('id' => $this->db->lastInsertId()), $params);
		}
		return $this->error("Failed to execute query: " . implode(', ', $st->errorInfo()));
	}

	public function setElement(string $elementStore, string $elementKey, string $keyValue, array $params): array|bool
	{
		$query = "UPDATE $elementStore SET ";
		$p = array($elementKey => $keyValue);
		foreach ($params as $k => $v) {
			$query .= "$k = :$k,";
			$p[$k] = $v == '_REMOVE_' ? null : $v;
		}
		$query = rtrim($query, ",");
		$query .= " WHERE $elementKey = :$elementKey";

		$wasInTransaction = $this->inTransaction();
		if (!$wasInTransaction) $this->beginTransaction();

		$this->log("Preparing query $query");
		$st = $this->db->prepare($query);
		if ($st == null) {
			if (!$wasInTransaction) $this->rollBack();
			return $this->error("Failed to prepare query: " . implode(', ', $this->db->errorInfo()));
		}

		$this->log("Executing with params " . implode(', ', $params) . ", $keyValue");
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

	public function removeElement(string $elementStore, string $elementKey, string $keyValue): array|bool
	{
		$query = "DELETE FROM $elementStore WHERE $elementKey = ?";

		$this->log("Preparing query $query");
		$st = $this->db->prepare($query);
		if ($st == null) {
			return $this->error("Failed to prepare query: " . implode(', ', $this->db->errorInfo()));
		}
		$this->log("Executing with param $keyValue");
		if (!$st->execute(array($keyValue))) return $this->error("Failed to execute query: " . implode(', ', $st->errorInfo()));

		return TRUE;
	}

	public function getElementByKey(string $elementStore, ?string $keyValue = null): array|bool
	{
		return $this->getElementByAttribute($elementStore, 'id', $keyValue);
	}

	public function getElementByParentKey(string $elementStore, string $parentKey): array|bool
	{
		switch ($elementStore) {
			case 'seasons':
				return $this->getElementByAttribute($elementStore, 'tvShow', $parentKey);
			case 'episodes':
				return $this->getElementByAttribute($elementStore, 'season', $parentKey);
			case 'files':
				return $this->getElementByAttribute($elementStore, 'episode', $parentKey);
			default:
				return $this->error("getElementByParentKey not implemented for $elementStore");
		}
	}


	public function getElementByAttribute(string $elementStore, ?string $attribute = null, ?string $value = null): array|bool
	{
		$q = null;
		$p = array();

		switch ($elementStore) {
			case 'tvShows':
				$elementStore = 'tvShowsWithStats';
				break;
			case 'scrapers':
				$elementStore = 'scrapersWithParents';
				if ($attribute == 'active' && $value) {
					$q = "SELECT scrapersWithParents.* FROM scrapersWithParents LEFT JOIN seasons on scrapersWithParents.season = seasons.id WHERE seasons.status = 'watched' OR scrapersWithParents.tvShow IS NOT NULL";
				}
				break;
			case 'files':
				if ($attribute == 'season') {
					$q = "SELECT files.* FROM files JOIN episodes ON episode = episodes.id WHERE season = :season";
					$p['season'] = $value;
				}
				break;
			case 'scrapedSeasons':
				if ($attribute == 'tvShow') {
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
						$q .= "WHERE scrapedSeasons.$attribute = :$attribute";
						$p[$attribute] = $value;
					}
				}
				break;
			case 'episodes':
				if ($attribute == 'seasonStatus') {
					$q = "SELECT episodes.id FROM episodes JOIN seasons ON episodes.season = seasons.id WHERE seasons.status = :seasonStatus";
					$p[$attribute] = $value;
				}
				break;
		}

		if ($q === null) {
			$q = "SELECT * FROM $elementStore ";
			if ($value !== null) {
				$q .= "WHERE $attribute = :value";
				$p['value'] = $value;
			}
		}

		$res = $this->getElementDB($q, $p);
		if ($res === FALSE) return FALSE;

		if ($elementStore == 'tvShowsWithStats') {
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
