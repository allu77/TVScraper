<?php

date_default_timezone_set('Europe/Rome');

define('LOGGER_ERROR',	 0);
define('LOGGER_WARNING', 1);
define('LOGGER_INFO',    2);
define('LOGGER_DEBUG',   3);

class Logger {
	protected $logFileName;
	protected $logh;
	protected $severity;
	protected $lastErrorMsg;

	private static $severityDesc = Array( 
		LOGGER_ERROR	=> "ERROR  ", 
		LOGGER_WARNING	=> "WARNING", 
		LOGGER_INFO		=> "INFO   ", 
		LOGGER_DEBUG	=> "DEBUG  "
	);

	public function __construct($logFileName, $severity = LOGGER_DEBUG) {
		$this->logFileName = $logFileName;
		$this->severity = $severity;

	}

	protected function getLogh() {
		if (!$this->logh) {
			$logh = fopen($this->logFileName, 'a');
			if ($logh === false) {
				trigger_error("Can't open file $this->logFileName for writing", E_USER_WARNING);
				return false;
			}
			$this->logh = $logh;
		}
		return $this->logh;
	}

	public function log($msg, $severity = LOGGER_DEBUG) {
		if ($severity <= $this->severity) {
			$lh = $this->getLogh();
			if ($lh != false) {
				flock($lh, LOCK_EX);
				fwrite($lh, date('r') . ' ' . self::$severityDesc[$severity] . " $msg\n");
				flock($lh, LOCK_UN);
			}
		}
	}
	
	public function error($msg) {
		$this->log($msg, LOGGER_ERROR);
		$this->lastErrorMsg = $msg;
	}
	
	public function errmsg() {
		return $this->lastErrorMsg;
	}
}

