<?php

require_once('Logger.php');
require_once('MyCurl.php');

/*

define('CACHE_USE', true);
define('CACHE_DIR', '/var/cache/autodl/dduScraper');
define('CACHE_PREFIX', 'SimpleBrowser_');
define('CACHE_TTL', 3600);

define('COOKIES_USE', true);
define('COOKIES_DIR', '/var/cache/autodl/dduScraper');
define('COOKIES_FILE', 'SimpleBrowserCookies.txt');

*/

class SimpleBrowser {

	protected $useCookies;
	protected $cookiesFileName;
	protected $cookiesFileDir;
	protected $cookiesFile;

	protected $useCache;
	protected $cachePrefix;
	protected $cacheTTL;

	protected $logger;

	private static $CACHE_USE = TRUE;
	private static $CACHE_DIR = '/var/cache/autodl/dduScraper';
	private static $CACHE_PREFIX = 'SimpleBrowser_';
	private static $CACHE_TTL = 3600;

	private static $COOKIES_USE = TRUE;
	private static $COOKIES_DIR = '/var/cache/autodl/dduScraper';
	private static $COOKIES_FILE = 'SimpleBrowserCookies.txt';

	public static function initCache($useCache, $cacheTTL = 3600, $cacheDir = '/var/cache/autodl/dduScraper', $cachePrefix = 'SimpleBrowser_') {
		self::$CACHE_USE = $useCache;
		self::$CACHE_DIR = $cacheDir;
		self::$CACHE_PREFIX = $cachePrefix;
		self::$CACHE_TTL = $cacheTTL;
	}

	public static function initCookies($useCookies, $cookiesDir = '/var/cache/autodl/dduScraper', $cookiesFile = 'SimpleBrowserCookies.txt') {
		self::$COOKIES_USE = $useCookies;
		self::$COOKIES_DIR = $cookiesDir;
		self::$COOKIES_FILE = $cookiesFile;
	}

	public function __construct() {
		$this->useCache = self::$CACHE_USE;
		$this->cacheTTL = self::$CACHE_TTL;
		$this->setCacheDir(self::$CACHE_DIR);

		$this->useCookies = self::$COOKIES_USE;
		$this->setCookiesFileDir(self::$COOKIES_DIR);
		$this->setCookiesFile(self::$COOKIES_FILE);
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

	public function setCacheDir($dir) {
		$this->cachePrefix = $dir . '/' . self::$CACHE_PREFIX;
	}

	public function disableCache() {
		$this->useCache = false;
	}

	public function enableCache() {
		$this->useCache = true;
	}

	public function setCacheTTL($ttl) {
		$this->cacheTTL = $ttl;
	}

	protected function getCacheFileNameOnly($url) {
		return md5($url);
	}

	protected function getCacheFileName ($url) {
		return ($this->cachePrefix) . $this->getCacheFileNameOnly($url);
	}

	protected function setCache ($url, $page) {
		$filename = $this->getCacheFileName($url);
		$this->log("Setting cache $filename for URL $url");
		$fh = fopen($filename, 'w');
		if ($fh === false) {
			trigger_error("Cannot open file $filename for writing. Page won't be cached.", E_USER_WARNING);
		} else {
			flock($fh, LOCK_EX);
			fwrite($fh, $page);
			flock($fh, LOCK_UN);
			fclose($fh);
		}
	}

	public function resetCacheForURL ($url) {
		$filename = $this->getCacheFileName($url);
		$this->log("Clearing cache $filename for URL $url");
		if (file_exists($filename)) {
			if (! unlink($filename)) {
				trigger_error("Can't remove cache file $filename.", E_USER_ERROR);
				return false;
			} else {
				return true;
			}
		} else {
			$this->log("Cache file didn't exist");
		}
		return true;
	}

	protected function getPageFromCache ($url) {
		$filename = $this->getCacheFileName($url);
		$this->log("Checking cache $filename for URL $url");

		if (!file_exists($filename)) {
			$this->log("Cache file doesn't exist");
			return false;
		} else {
			$this->log("Cache file exists, checking staleness");
			$age = time() - filemtime($filename); 
			if ($age < $this->cacheTTL) {
				$this->log("Cache file is $age seconds old, still valid");
				$fh = fopen($filename, 'r');
				if ($fh === false) {
					trigger_error("Can't open cache file $filename for reading", E_USER_WARNING);
					return false;
				} else {
					flock($fh, LOCK_SH);
					$page = file_get_contents ($filename);
					flock($fh, LOCK_UN);
					fclose($fh);
					$this->log($this->getCacheFileNameOnly($url) . " cache hit", LOGGER_INFO);
					return $page;
				}
			} else {
				$this->log("Cache file is $age seconds old, it is stale");
				return false;
			}
		}
	}

	


	public function disableCookies() {
		$this->useCookies = false;
	}

	public function enableCookies() {
		$this->useCookies = true;
	}

	public function setCookiesFileDir($dir) {
		$this->cookiesFileDir = $dir;
		$this->cookiesFile = $dir . '/' . $this->cookiesFileName;
	}

	public function setCookiesFile($fileName) {
		$this->cookiesFileName = $fileName;
		$this->cookiesFile = $this->cookiesFileDir . '/' . $fileName;
	}

	protected function curlInit ($url) {
		$cr = new MyCurl();
		$cr->setLogger($this->logger);
		$cr->curlInit($url);
		$cr->setOption(MYCURLOPT_RETURNTRANSFER, true);
		if ($this->useCookies) {
			$cr->setOption(MYCURLOPT_COOKIEJAR, $this->cookiesFile);
			$cr->setOption(MYCURLOPT_COOKIEFILE, $this->cookiesFile);
		}

		return $cr;
	}

	protected function curlExec ($cr) {
		return $cr->curlExec();
	}

	protected function curlClose ($cr) {
		return $cr->curlClose();
	}

	protected function directGet ($url) {
		
		$cr = $this->curlInit($url);
		$this->log("GET $url");
		$page = $this->curlExec($cr);
		$this->log('Got ' . strlen($page) . ' bytes');
		$this->log($this->getCacheFileNameOnly($url) . " downloaded", LOGGER_INFO);
		$this->curlClose($cr);

		return $page;
	}

	protected function directPost ($url, Array $params) {

		$paramsCount = 0;
		$paramsString = '';

		foreach ($params as $key => $value) {
			$paramsCount++;
			$paramsString .= urlencode($key) . '=' . urlencode($value) . '&';
		}
		$paramsString = rtrim($paramsString, '&');
		
		$cr = $this->curlInit($url);
		$cr->setOption(MYCURLOPT_POST, $paramsCount);
		$cr->setOption(MYCURLOPT_POSTFIELDS, $paramsString);
		$this->log("POST $url PARAMS $paramsString");
		$page = $this->curlExec($cr);
		$this->log('Got ' . strlen($page) . ' bytes');
		$this->curlClose($cr);

		return $page;
	}

	public function get($url, $perGetUseCache = '_UNDEFINED_') {

		if ($perGetUseCache == '_UNDEFINED_') $perGetUseCache = $this->useCache;

		$page = $perGetUseCache ? $this->getPageFromCache($url) : false;

		if ($page === false) {
			$page = $this->directGet($url);
			if ($page && $perGetUseCache) {
				$this->setCache($url, $page);
			}
		}
		return $page;
	}

	public function post ($url, Array $params) {
		return $this->directPost ($url, $params); 
	}
}


?>
