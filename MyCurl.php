<?php

require_once('Logger.php');

define('MYCURLOPT_COOKIEJAR',		'CURLOPT_COOKIEJAR');
define('MYCURLOPT_COOKIEFILE', 		'CURLOPT_COOKIEFILE');
define('MYCURLOPT_POST', 			'CURLOPT_POST');
define('MYCURLOPT_POSTFIELDS',		'CURLOPT_POSTFIELDS');

define('MYCURLOPT_RETURNTRANSFER',	'CURLOPT_RETURNTRANSFER');

//define('MYCURL_USEPHP', true);
//define('MYCURL_CMD', '/share/Apps/dduScraper/curl');


class MyCurl {

	protected $options;
	protected $url;
	protected $cr;
	
	protected $logger;

	protected static $usePHP = TRUE;
	protected static $curlCmd = '/usr/bin/curl';

	public static function init($usePHP, $curlCmd = '/usr/bin/curl') {
		self::$usePHP = $usePHP;
		self::$curlCmd = $curlCmd;
	}

	public function __construct() {
		$this->options = Array();
	}

	public function setLogger($logger) {
		$this->logger = $logger;
	}

	public function setLogFile($logFile, $severity = LOGGER_DEBUG) {
		$this->logger = new Logger($logFile, $severity);
	}

	protected function log($msg, $severity = LOGGER_DEBUG) {
		if ($this->logger) {
			$this->logger->log($msg, $severity);
		}
	}

	public function curlInit ($url) {
		if (self::$usePHP) {
			$cr = curl_init($url);
			if ($cr === false) {
				trigger_error("Can't initialize cURL object for url $url", E_USER_ERROR);
				return false;
			}
			curl_setopt($cr, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($cr, CURLOPT_FOLLOWLOCATION, true);
			$this->cr = $cr;
		}
		$this->url = $url;
		return true;
	}


	public function setOption ($opt, $value) {
		if (self::$usePHP) {
			curl_setopt($this->cr, constant($opt), $value);
		} else {
			$this->options[$opt] = $value;
		}
		return true;
	}

	public function curlExec () {
		if (self::$usePHP) {
			$page = curl_exec($this->cr);
			if ($page === false) {
				trigger_error("Can't execute cURL (". curl_error($this->cr) .")", E_USER_ERROR);
				return false;
			} else {
				$info = curl_getinfo($this->cr);
				if ($info['http_code'] != 200) {
					trigger_error("cURL query for url " . $info['url'] . " returned HTTP code " . $info['http_code'], E_USER_ERROR);
					return false;
				} else {
					return $page;
				}
			}
		} else {
			$optstr = ' -Ls';
			if (isset($this->options[MYCURLOPT_COOKIEJAR])) {
				$optstr .= ' --cookie-jar ' . $this->options[MYCURLOPT_COOKIEJAR];
			}
			if (isset($this->options[MYCURLOPT_COOKIEFILE])) {
				$optstr .= ' --cookie ' . $this->options[MYCURLOPT_COOKIEFILE];
			}
			if (isset($this->options[MYCURLOPT_POST]) && isset($this->options[MYCURLOPT_POSTFIELDS])) {
				$optstr .= ' --data "' . $this->options[MYCURLOPT_POSTFIELDS] .'"';
			}

			$cmdLine = self::$curlCmd . $optstr . ' "' . $this->url . '"';
			$output = Array();
			$this->log("Executing $cmdLine");
			$out = exec ($cmdLine, $output);
			$outStr = implode("\n", $output);


			if (isset($this->options[MYCURLOPT_RETURNTRANSFER])) {
				return $outStr;
			} else {
				print $outStr;
				return true;
			}
		}
	}

	public function curlClose () {
		if (self::$usePHP) {
			return curl_close($this->cr);
		}
	}

}


?>
