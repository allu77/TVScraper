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

	// abstract protected function addElement($elementStore, $parentKey, $keyValue, $params);
	// abstract protected function setElement($elementStore, $elementKey, $keyValue, $params);
	// abstract protected function removeElement($elementStore, $elementKey, $keyValue);
	# abstract protected function getElement($elementStore, )

}
