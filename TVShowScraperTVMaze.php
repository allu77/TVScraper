<?php

require_once('TVShowScraper.php');
require_once('SimpleBrowser.php');

class TVShowScraperTVMaze extends TVShowScraper {

	protected function runScraperTVShow($scraper, $showOnlyNew = false, $saveResults = false) {
		$res = array();

		$uri = $scraper['uri'];
	
		$this->log("Parsing TV Maze page $uri");
		
		$browser = new SimpleBrowser();
		$browser->setLogger($this->logger);
		$page = $browser->get($uri);

		$json = json_decode($page, true);
		if (json_last_error() != JSON_ERROR_NONE) return $this->error('Cannot load JSON object');

		foreach ($json as $episode) {
			$res[] = array(
				'n'		=> $episode['season'],
				'uri'	=> $uri
			);
		}

		return $this->submitSeasonCandidates($scraper, $res, $showOnlyNew, $saveResults);

	}

	protected function runScraperSeason($scraper, $showOnlyNew = false, $saveResults = false) {

		$res = array();
		$seasonData = $this->tvdb->getSeason($scraper['season']);

		$browser = new SimpleBrowser();
		$browser->setLogger($this->logger);
		$page = $browser->get($scraper['uri']);
		
		$json = json_decode($page, true);
		if (json_last_error() != JSON_ERROR_NONE) return $this->error('Cannot load JSON object');

		foreach ($json as $episode) {
			$season = $episode['season'];
			$ep = $episode['number'];
			$airDate = strtotime($episode['airdate']);
		
			if ($season != $seasonData['n']) {
				$this->log("Season $season != " . $seasonData['n'] . " - Skipping");
				continue;
			}

			$retVal = !$showOnlyNew;

			$episodeDB = $this->tvdb->getEpisodeFromIndex($seasonData['tvshow'], $season, $ep);
			if ($episodeDB === FALSE) {
				if ($saveResults) {
					$this->log("Creating episode $ep");
					$episodeDB = $this->tvdb->getEpisode($this->tvdb->addEpisode($seasonData['id'], Array('n' => $ep)));
					$retVal = TRUE;
				}
				if ($showOnlyNew) {
					$retVal = TRUE;
				}
			}
			if ($episodeDB) {
				if (! isset($episodeDB['airDate']) || $episodeDB['airDate'] != $airDate) {
					if ($saveResults) {
						$this->tvdb->setEpisode($episodeDB['id'], Array('airDate' => $airDate));
						$retVal = TRUE;
					}
				}
			}	
			if ($retVal) {
				$res[] = Array('n' => $ep, 'airDate' => $airDate);
			}
			
		}

		return $res;
	}
}

?>
