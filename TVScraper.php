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


$tv = new TVShowScraperDB(LIB_FILE);
$tv->setLogger($logger);

$shows = array();

/*
$shows = $tv->getAllTVShows();

for ($i = 0; $i < sizeof($shows); $i++) {


	if (isset($shows[$i]['lastAirDate'])) $shows[$i]['lastAirDateStr'] = date('d/m/Y', $shows[$i]['lastAirDate']);
	if (isset($shows[$i]['nextAirDate'])) $shows[$i]['nextAirDateStr'] = date('d/m/Y', $shows[$i]['nextAirDate']);

	$shows[$i]['seasons'] = array();
	$seasons = $tv->getTVShowSeasons($shows[$i]['id']);
	foreach ($seasons as $season) {
		$season['scrapers'] = array();

		$scrapers = $tv->getSeasonScrapers($season['id']);

		foreach ($scrapers as $scraper) {
			$season['scrapers'][] = $tv->getScraper($scraper);
		}

		if (isset($season['lastFilePubDate']) && (! isset($shows[$i]['lastFilePubDate']) || $shows[$i]['lastFilePubDate'] < $season['lastFilePubDate'])) {
			$shows[$i]['lastFilePubDate'] = $season['lastFilePubDate'];
			$shows[$i]['lastFilePubDateStr'] = date('d/m/Y', $season['lastFilePubDate']);
		}


		$shows[$i]['seasons'][] = $season;
	}
	ob_start();
	var_dump($shows[$i]);
	$d = ob_get_clean();
	$logger->log($d);

}
ob_start();
var_dump($shows);
$d = ob_get_clean();
$logger->log($d);
*/

if (isset($_GET['action']) && $_GET['action'] == 'latest') {
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
	usort($res, sortByPubDate);
	$resCount = 0;
	foreach ($res as $r) {
		if (isset($_GET['max']) && ++$resCount > $_GET['max']) break;
		echo $r['uri'] . "\n";
	}
	
	
	
} else {
	echo $twig->render('index-bootstrap.html', array('shows' => $shows));
}

?>
