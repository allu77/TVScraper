<?php

require_once('TVShowScraper.php');
require_once('SimpleBrowser.php');

class TVShowScraperTVRage extends TVShowScraper {

	protected function runScraperTVShow($scraper, $showOnlyNew = false, $saveResults = false) {
		$res = array();

		$uri = $scraper['uri'];
	
		$this->log("Parsing TV Rage page $uri");
		
		$browser = new SimpleBrowser();
		$browser->setLogger($this->logger);
		$page = $browser->get($uri);

		$xml = new DOMDocument();
		if (! @$xml->loadXML($page)) return $this->error('Cannot load XML page');

		$xpath = new DOMXPath($xml);
		if (! $xpath) return $this->error('Cannot create XPATH handler');

		$s = $xpath->query("/Show/Episodelist/Season");
		for ($i = 0; $i < $s->length; $i++) {
			$res[] = array(
				'n'		=> $s->item($i)->getAttribute('no'),
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
		
		$xml = new DOMDocument();
		if (! @$xml->loadXML($page)) return $this->error('Cannot load XML page');

		$xpath = new DOMXPath($xml);
		if (! $xpath) return $this->error('Cannot create XPATH handler');

		$s = $xpath->query("/Show/Episodelist/Season[@no='".$seasonData['n']."']/episode");
		for ($i = 0; $i < $s->length; $i++) {
			$epQ = $xpath->query("./seasonnum", $s->item($i));
			$airDateQ = $xpath->query("./airdate", $s->item($i));
			if ($epQ->length == 0 || $airDateQ->length == 0) continue;
			$m = array();
			if (preg_match('/^0*([1-9]\d*)$/', $epQ->item(0)->nodeValue, $m)) {
				$ep = $m[1];


				$retVal = !$showOnlyNew;

				$episodeDB = $this->tvdb->getEpisodeFromIndex($seasonData['tvshow'], $seasonData['n'], $ep);
				if ($episodeDB === FALSE) {
					if ($saveResults) {
						$this->log("Creating episode $ep");
						$episodeDB = $this->tvdb->addEpisode($seasonData['id'], Array('n' => $ep));
						$retVal = TRUE;
					}
					if ($showOnlyNew) {
						$retVal = TRUE;
					}
				}

				if (preg_match('/^(\d+-\d+-\d+)$/', $airDateQ->item(0)->nodeValue, $m)) {
					$airDate = strtotime($m[1]);

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

			}

		}

		return $res;
	}
}

?>
