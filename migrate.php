<?
require_once('TVShowScraperDB.php');
require_once('TVShowScraperDBSQLite.php');
require_once('Logger.php');

ini_set('display_errors', 1);

$logger = new Logger('log/migrate.log', LOGGER_DEBUG);

$db = new TVShowScraperDBSQLite('lib/tvscraper.test.db');
$db->setLogger($logger);
$db->beginTransaction();

$xml = new TVShowScraperDB('/var/lib/tvscraper/myShows.xml');
$xml->setLogger($logger);


$tvShows = $xml->getAllTVShows();
foreach ($tvShows as $tvShow) {
	print "TV Show: " . $tvShow['title'] . "... ";

	$dbTVShow = $db->addTVShow(array(
		'title' => $tvShow['title'],
		'alternateTitle' => $tvShow['alternateTitle'],
		'lang' => $tvShow['lang'],
		'nativeLang' => $tvShow['nativeLang'],
		'res' => $tvShow['res']
	));
	if ($dbTVShow === FALSE) die($logger->errmsg());
	print "Done!\n";

	$scrapersMap = array();
	$scrapers = $xml->getTVShowScrapers($tvShow['id']);
	print "\n\tTV Show Scrapers:\n";
	foreach ($scrapers as $scraper) {
		print "\t" . $scraper['source'] . " - " . $scraper['uri'] . "... ";
		$scraperId = $scraper['id'];
		unset($scraper['id']);
		unset($scraper['tvshow']);
		$dbScraper = $db->addScraper($dbTVShow['id'], 'tvShow', $scraper);
		if ($dbScraper === FALSE) die($logger->errmsg());
		print "Done!\n";
		$scrapersMap[$scraperId] = $dbScraper['id'];
	}

	$scrapedSeasons = $xml->getScrapedSeasons($tvShow['id']);
	print "\n\tScraped Seasons:\n";
	foreach ($scrapedSeasons as $scrapedSeason) {
		if (! isset($scrapersMap[$scrapedSeason['scraper']])) die("Unknows scraper for scrapedSeason\n");
		$dbScapedSeasonId = $scrapersMap[$scrapedSeason['scraper']];
		print "\t" . $scrapedSeason['n'] . ". ". $scrapedSeason['source'] . " - " . $scrapedSeason['uri'] . "... ";
		unset($scrapedSeason['id']);
		unset($scrapedSeason['scraper']);
		unset($scrapedSeason['source']);
		$dbScrapedSeason = $db->addScrapedSeason($dbScapedSeasonId, $scrapedSeason);
		if ($dbScrapedSeason === FALSE) die($logger->errmsg());
		print "Done!\n";
	}

	$seasons = $xml->getTVShowSeasons($tvShow['id']);
	print "\n\tSeasons:\n";
	foreach ($seasons as $season) {
		print "\t" . $season['n'] . ". ". $season['status'] . "... ";
		$dbSeason = $db->addSeason($dbTVShow['id'], array(
			'n'			=> $season['n'],
			'status'	=> $season['status']
		));
		if ($dbSeason === FALSE) die($logger->errmsg());
		print "Done!\n";

		$scrapersMap = array();
		print "\t\tSeason Scrapers:\n";
		$scrapers = $xml->getSeasonScrapers($season['id']);
		foreach ($scrapers as $scraper) {
			print "\t\t" . $scraper['source'] . " - " . $scraper['uri'] . "... ";

			$scraperId = $scraper['id'];
			unset($scraper['id']);
			unset($scraper['season']);
			$dbScraper = $db->addScraper($dbTVShow['id'], 'season', $scraper);
			if ($dbScraper === FALSE) die($logger->errmsg());
			print "Done!\n";
			$scrapersMap[$scraperId] = $dbScraper['id'];
		}

		$stickyFiles = array();
		$episodesMap = array();

		print "\t\tEpisodes:\n";
		$episodes = $xml->getSeasonEpisodes($season['id']);
		foreach ($episodes as $episode) {
			print "\t\t" . $episode['n'] . ". " . (isset($episode['bestSticky']) ? "has bestSticky" : "" ) . "... ";
			$xmlStickyFile = isset($episode['bestSticky']) ? $episode['bestFile'] : null ;
			$xmlEpisodeId = $episode['id'];

			unset($episode['bestSticky']);
			unset($episode['bestFile']);
			unset($episode['season']);
			unset($episode['id']);

			$dbEpisode = $db->addEpisode($dbSeason['id'], $episode);
			if ($dbEpisode === FALSE) die($logger->errmsg());
			print "Done!\n";

			$episodesMap[$xmlEpisodeId] = $dbEpisode['id'];

			if ($xmlStickyFile != null) {
				$stickyFiles[$xmlStickyFile] = $dbEpisode['id'];
			} else {
				$xml->resetEpisodeBestFile($xmlEpisodeId);
			}

		}

		$filesMap = array();

		print "\t\tFiles:\n";
		$fileIds = $xml->getFilesForSeason($season['id']);
		foreach ($fileIds as $fileId) {
			$file = $xml->getFile($fileId);
			print "\t\t" . $file['uri'] . "... ";

			if (!isset($file['scraper']) || ! isset($scrapersMap[$file['scraper']])) {
				print "Orphan, skipping.\n";
				continue;
			}

			$xmlFileId = $file['id'];
			$xmlEpisodeId = $file['episode'];

			$file['episode'] = $episodesMap[$file['episode']];
			$file['scraper'] = $scrapersMap[$file['scraper']];
			if (!isset($file['type'])) $file['type'] = 'ed2k';

			unset($file['id']);
			unset($file['season']);
			unset($file['episode']);

			$dbFile = $db->addFile($episodesMap[$xmlEpisodeId], $file);
			if ($dbFile === FALSE) die($logger->errmsg());
			print "Done!\n";

			if (isset($stickyFiles[$xmlFileId])) {
				print "\t\t\tThis file sticks to an episode... ";

				if (! $db->setEpisode($stickyFiles[$xmlFileId], array(
					'bestFile'		=> $dbFile['id'],
					'bestSticky'	=> 1
					))) {
					die ($logger->errmsg());
				}

				print " Done!\n";
			}

			$filesMap[$xmlFileId] = $dbFile['id'];
		}

		print "\t\t\tChecking that best files match:\n";

		foreach ($episodesMap as $xmlEpisodeId => $dbEpisodeId) {
			$xmlBestFile = $xml->getBestFileForEpisode($xmlEpisodeId);
			$dbBestFile = $db->getBestFileForEpisode($dbEpisodeId);

			print "\t\t\tChecking episode id $dbEpisodeId... ";

			if (($xmlBestFile == null && $dbBestFile == null) || ($filesMap[$xmlBestFile['id']] == $dbBestFile['id'])) {
				print "Matches!\n";
			}  else {
				print "NO MATCH!!\n";
				var_dump($xml->getFile($xmlBestFile['id']));
				var_dump($db->getFile($dbBestFile['id']));

				print "\n\n\nSaving anyway, chec resuts!\n";
				$db->save();
				die();
			}
		}

	}
}

$db->save();

