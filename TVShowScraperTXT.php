<?php 

require_once('Logger.php');
require_once('SimpleBrowser.php');
require_once('TVShowScraper.php');

require_once('TVShowUtils.php');

class TVShowScraperTXT extends TVShowScraper {
	
	public function runScraperTVShow($scraper, $showOnlyNew = false, $saveResults = false) {
		$res = array();

		$uri = $scraper['uri'];
		
		$this->log("Parsing TXT $uri");
		
		$browser = new SimpleBrowser();
		$browser->setLogger($this->logger);
		$page = $browser->get($uri);

		$showData = $this->tvdb->getTVShow($scraper['tvshow']);

		$showTitle = strtolower($showData['title']);
		$showTitle = preg_replace('/\s+/', '\s+', $showTitle);
		$showTitle = preg_replace('/[!\?\.\']/', '', $showTitle);

		$this->log("Looking for $showTitle");

		$rows = preg_split('/\n\r?/', $page, -1, PREG_SPLIT_NO_EMPTY);
		
		foreach ($rows as $r) {
			$fileData = parseEpisodeFileName($r);	
			if ($fileData === FALSE) {
				$this->log("Can't guess episode season, skipping...");
			} else {
				$n = $fileData['season'];

				$res[] = array(
					'n'	=> $n,
					'uri' => $uri
				);
			}
		}
		return $this->submitSeasonCandidates($scraper, $res, $showOnlyNew, $saveResults);
	}

	public function runScraperSeason($scraper, $showOnlyNew = false, $saveResults = false) {
		
		$seasonData = $this->tvdb->getSeason($scraper['season']);
		$showData = $this->tvdb->getTVShow($seasonData['tvshow']);
		
		$uri = $scraper['uri'];
		
		$this->log("Parsing TXT $uri");
		
		$browser = new SimpleBrowser();
		$browser->setLogger($this->logger);
		$page = $browser->get($uri);

		$showTitle = strtolower($showData['title']);
		$showTitle = preg_replace('/\s+/', '\s+', $showTitle);
		$showTitle = preg_replace('/[!\?\.\']/', '', $showTitle);

		$this->log("Looking for $showTitle");
		
		$candidates = array();
		
		$rows = preg_split('/\n\r?/', $page, -1, PREG_SPLIT_NO_EMPTY);
		
		$candidates = array();
		$t = time();
		for ($j = 0; $j < sizeof($rows); $j++) {
			$candidates[] = array('link' => $rows[$j], 'pubDate' => $t);
		}
		
		return $this->submitEpisodeCandidates($scraper, $candidates, $showOnlyNew, $saveResults);
		
	}
	
}




?>
