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

		$showAlt = isset($showData['alternateTitle']) ? strtolower($showData['alternateTitle']) : '';
		$showAlt = preg_replace('/\s+/', '\s+', $showAlt);
		$showAlt = preg_replace('/[!\?\.\']/', '', $showAlt);

		$this->log("Looking for $showTitle or $showAlt");

		$rows = preg_split('/\n\r?/', $page, -1, PREG_SPLIT_NO_EMPTY);
		
		foreach ($rows as $r) {
			$this->log("Evaluating $r");
			$uriData = parseED2KURI($r);
			if ($uriData === FALSE) {
				$this->log("Can't guess episode filename, skipping...");
			} else {
				$fileData = parseEpisodeFileName($uriData['fileName']);	
				$fileNameClean = preg_replace('/([\.\-_]|%20)/', ' ', $uriData['fileName']);
				if ($fileData === FALSE) {
					$this->log("Can't guess episode season, skipping...");
				} else if (preg_match("/$showTitle/i", $fileNameClean) || (strlen($showAlt) > 0 && preg_match("/$showAlt/i", $fileNameClean)))  {
					$n = $fileData['season'];
					$this->log("Adding as candidate for season $n...");

					$res[] = array(
						'n'	=> $n,
						'uri' => $uri
					);
				}
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

		$showAlt = isset($showData['alternateTitle']) ? strtolower($showData['alternateTitle']) : '';
		$showAlt = preg_replace('/\s+/', '\s+', $showAlt);
		$showAlt = preg_replace('/[!\?\.\']/', '', $showAlt);

		$this->log("Looking for $showTitle or $showAlt");
		
		$candidates = array();
		
		$rows = preg_split('/\n\r?/', $page, -1, PREG_SPLIT_NO_EMPTY);
		
		$candidates = array();
		$t = time();
		for ($j = 0; $j < sizeof($rows); $j++) {
			$this->log("Evaluating $r");
			$uriData = parseED2KURI($rows[$j]);
			if ($uriData === FALSE) {
				$this->log("Can't guess episode filename, skipping...");
			} else {
				$fileData = parseEpisodeFileName($uriData['fileName']);
				$fileNameClean = preg_replace('/([\.\-_]|%20)/', ' ', $uriData['fileName']);

				if (preg_match("/$showTitle/i", $fileNameClean) || (strlen($showAlt) > 0 && preg_match("/$showAlt/i", $fileNameClean)))  {
					$candidates[] = array('link' => $rows[$j], 'pubDate' => $t);
				}
			}
		}
		
		return $this->submitEpisodeCandidates($scraper, $candidates, $showOnlyNew, $saveResults);
		
	}
	
}




?>
