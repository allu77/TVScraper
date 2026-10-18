<?php

define('OPT_BROWSER_TIMEOUT',   'BROWSER_TIMEOUT');
define('OPT_CACHE_USE',         'BROWSER_USE_CACHE');
define('OPT_CACHE_DIR',         'BROWSER_CACHE_DIR');
define('OPT_CACHE_TTL',         'BROWSER_CACHE_TTL');
define('OPT_COOKIES_DIR',       'BROWSER_COOKIES_DIR');

define('OPT_DDU_LOGIN',         'BROWSER_DDU_LOGIN');
define('OPT_DDU_PASSWORD',      'BROWSER_DDU_PASSWORD');

define('OPT_DB_FILE',           'SQLITE_FILE');

define('OPT_LOG_DIR',           'LOG_DIR');

class TVScraperConfig {
    
    public $options = array(
        OPT_BROWSER_TIMEOUT => 600,
        OPT_CACHE_USE => true,
        OPT_CACHE_DIR => 'cache',
        OPT_CACHE_TTL => 3600,
        OPT_COOKIES_DIR => 'cache',
        OPT_DB_FILE => 'lib/myShows.db',
        OPT_LOG_DIR => 'log'
    );
    
	public function __construct() {
	    // Set Default options
	}
	
	public function readFromRequire($fileName) {
	    require($fileName);
	    
	    if (defined('BROWSER_TIMEOUT')) $this->options[OPT_BROWSER_TIMEOUT] = BROWSER_TIMEOUT;
	    if (defined('CACHE_USE')) $this->options[OPT_CACHE_USE] = CACHE_USE;
	    if (defined('CACHE_DIR')) $this->options[OPT_CACHE_DIR] = CACHE_DIR;
	    if (defined('CACHE_TTL')) $this->options[OPT_CACHE_TTL] = CACHE_TTL;
	    if (defined('COOKIES_DIR')) $this->options[OPT_COOKIES_DIR] = COOKIES_DIR;
	    if (defined('DDU_LOGIN')) $this->options[OPT_DDU_LOGIN] = DDU_LOGIN;
	    if (defined('DDU_PASSWORD')) $this->options[OPT_DDU_PASSWORD] = DDU_PASSWORD;
	    if (defined('DB_FILE')) $this->options[OPT_DB_FILE] = DB_FILE;
	    if (defined('LOG_DIR')) $this->options[OPT_LOG_DIR] = LOG_DIR;
	}
	
	public function readFromEnv() {
	    foreach (getenv() as $e => $val) {
	        if (strpos($e, 'TVSCRAPER_') === 0) {
	            $this->options[substr($e, 10)] = $val;
	        }
	    }
	}
	
}
?>