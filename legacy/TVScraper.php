<?php
require_once('config.php');
require_once('TVShowScraperDB.php');


function sortByPubDate($a, $b) { return $b['pubDate'] - $a['pubDate']; }

$log_file = LOG_DIR . '/index.log';
$log_level = LOGGER_DEBUG;


$logger = new Logger($log_file, $log_level);
$logger->log("------ START -----");
ob_start();
var_dump($_POST);
$d = ob_get_clean();
$logger->log($d);


$fp = fopen(LIB_FILE, 'r');
flock($fp, LOCK_EX);
$tv = new TVShowScraperDB(LIB_FILE);
$tv->setLogger($logger);

$shows = array();

if (isset($_GET['action']) && $_GET['action'] == 'latest') {
	/*
	$res = Array();
	$seasons = $tv->getAllWatchedSeasons();
	foreach ($seasons as $season) {
		$files = $tv->getBestFilesForSeason($season['id']);
		foreach ($files as $file) {
			if (isset($_GET['laterthan']) && $file['pubDate'] <= $_GET['laterthan']) continue;

			if (!(isset($_GET['torrent']) && $_GET['torrent'] == 1) && isset($file['type']) && $file['type'] == 'torrent') continue;
			if (!(isset($_GET['ed2k']) && $_GET['ed2k'] == 1) && (!isset($file['type']) || $file['type'] == 'ed2k')) continue;
			$res[] = $file;
		}
	}
	*/

	$res = Array();
	$files = $tv->getAllWatchedBestFiles();
	foreach ($files as $file) {
		if (isset($_GET['laterthan']) && $file['pubDate'] <= $_GET['laterthan']) continue;

		if (!(isset($_GET['torrent']) && $_GET['torrent'] == 1) && isset($file['type']) && $file['type'] == 'torrent') continue;
		if (!(isset($_GET['ed2k']) && $_GET['ed2k'] == 1) && (!isset($file['type']) || $file['type'] == 'ed2k')) continue;
		$res[] = $file;
	}
	usort($res, sortByPubDate);
	$resCount = 0;
	foreach ($res as $r) {
		if (isset($_GET['max']) && ++$resCount > $_GET['max']) break;
		echo $r['uri'] . "\n";
	}
}


$tv->save(LIB_FILE);

flock($fp, LOCK_UN);
fclose($fp);	

?>
