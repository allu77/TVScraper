<?php

define('BROWSER_TIMEOUT', 600);

define('CACHE_USE', true);
define('CACHE_DIR', 'cache');
define('CACHE_TTL', 3600);

define('COOKIES_DIR', 'cache');

define('DDU_LOGIN', 'YOUR USERNAME HERE');
define('DDU_PASSWORD', 'YOUR PASSWORD HERE');

define('LIB_FILE', 'lib/myShows.xml');
define('LOG_DIR', 'log');


require_once('SimpleBrowser.php');
SimpleBrowser::initCache(CACHE_USE, CACHE_TTL, CACHE_DIR);
SimpleBrowser::initCookies(TRUE, COOKIES_DIR);
SimpleBrowser::initTimeOut(BROWSER_TIMEOUT);
?>
