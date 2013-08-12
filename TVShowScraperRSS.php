<?php 

require_once('Logger.php');
require_once('SimpleBrowser.php');
require_once('TVShowScraper.php');

require_once('TVShowUtils.php');

class TVShowScraperRSS extends TVShowScraper {
	
	public function runScraperTVShow($scraper, $showOnlyNew = false, $saveResults = false) {
		$res = array();

		$uri = $scraper['uri'];
		
		$this->log("Parsing RSS feed $uri");
		
		$browser = new SimpleBrowser();
		$browser->setLogger($this->logger);
		$page = $browser->get($uri);

		$showData = $this->tvdb->getTVShow($scraper['tvshow']);

		$showTitle = strtolower($showData['title']);
		$showTitle = preg_replace('/\s+/', '\s+', $showTitle);
		$showTitle = preg_replace('/[!\?\.\']/', '', $showTitle);

		$this->log("Looking for $showTitle");
		
		$dom = new DOMDocument();
		if (!@$dom->loadXML($page)) return $this->error('Cannot load HTML page');

		$xpath = new DOMXPath($dom);
		if (! $xpath) return $this->error('Cannot create XPATH handler');


		$x = $xpath->query('//item');
		
		for ($i = 0; $i < $x->length; $i++) {
			$this->log("Checking item $i...");
			$node = $x->item($i);
			
			$x2 = $xpath->query('.//title', $node);
			if ($x2->length != 1) return $this->error("Invalid RSS feed, no title element");
			$title = $x2->item(0)->textContent;

			$x2 = $xpath->query('.//description', $node);
			if ($x2->length != 1) return $this->error("Invalid RSS feed, no description element");
			$description = $x2->item(0)->textContent;

			$x2 = $xpath->query('.//pubDate', $node);
			if ($x2->length != 1) return $this->error("Invalid RSS feed, no pubDate element");
			$pubDateStr = $x2->item(0)->textContent;
			$pubDate = strtotime($pubDateStr);
			

			$fetchData = TRUE;
			//$m = array();

			if (! preg_match("/$showTitle/i", $title) && ! preg_match("/$showTitle/i", $description)) {
				$this->log("Neiter title nor description match, skipping...");
				$fetchData = FALSE;
			} else {
				$fileData = parseEpisodeFileName($title);	
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
		}
		return $this->submitSeasonCandidates($scraper, $res, $showOnlyNew, $saveResults);
	}

	protected function getPage($browser, $uri) {
		return $browser->get($uri);
	}

	protected function getDOM($page) {
		$dom = new DOMDocument();
		if (!@$dom->loadXML($page)) return $this->error('Cannot load feed');
		return $dom;
	}
	
	public function runScraperSeason($scraper, $showOnlyNew = false, $saveResults = false) {
		
		$seasonData = $this->tvdb->getSeason($scraper['season']);
		$showData = $this->tvdb->getTVShow($seasonData['tvshow']);
		
		$uri = $scraper['uri'];
		
		$this->log("Parsing RSS feed $uri");
		
		$browser = new SimpleBrowser();
		$browser->setLogger($this->logger);
		$page = $this->getPage($browser, $uri);

		$showTitle = strtolower($showData['title']);
		$showTitle = preg_replace('/\s+/', '\s+', $showTitle);
		$showTitle = preg_replace('/[!\?\.\']/', '', $showTitle);

		$this->log("Looking for $showTitle");
		
		$dom = $this->getDOM($page);
		if ($dom == NULL) return NULL;

		$xpath = new DOMXPath($dom);
		if (! $xpath) return $this->error('Cannot create XPATH handler');

		$candidates = array();
		
		$x = $xpath->query('//item');
		
		for ($i = 0; $i < $x->length; $i++) {
			$this->log("Checking item $i...");
			$node = $x->item($i);
			
			$x2 = $xpath->query('.//title', $node);
			if ($x2->length != 1) return $this->error("Invalid RSS feed, no title element");
			$title = $x2->item(0)->textContent;

			$x2 = $xpath->query('.//description', $node);
			if ($x2->length != 1) return $this->error("Invalid RSS feed, no description element");
			$description = $x2->item(0)->textContent;

			$x2 = $xpath->query('.//pubDate', $node);
			if ($x2->length != 1) return $this->error("Invalid RSS feed, no pubDate element");
			$pubDateStr = $x2->item(0)->textContent;
			$pubDate = strtotime($pubDateStr);
			
			if (! preg_match("/$showTitle/i", $title) && ! preg_match("/$showTitle/i", $description)) {
				$this->log("Neiter title nor description match, skipping...");
				continue;
			}

			$fetchData = TRUE;
			//$m = array();

			$fileData = parseEpisodeFileName($title);	

			#if ($showOnlyNew && preg_match('/\s+0*([^0]\d*)x0*([^0]\d*)\s+/', $title, $m)) {
			if ($showOnlyNew && $fileData != false) {
				//$s = $[1];
				//$ep = $m[2];
				$s = $fileData['season'];
				$ep = $fileData['episode'];
				$episode = $this->tvdb->getEpisodeFromIndex($seasonData['tvshow'], $s, $ep);
				
				if (! $episode) {
					$this->log("Episode " . $s . "x$ep not found, parsing description ...");
				} else {
					$this->log("Searching files for episode " . $episode['id']);
					$files = $this->tvdb->getFilesForEpisode($episode['id']);
					if ($files === FALSE) {
						return FALSE;
					}
					
					foreach ($files as $f) {
						$this->log("File scraper " . $f['scraper']);
						if (isset($f['scraper']) && $f['scraper'] == $scraper['id']) {
							$this->log("File found for this scraper, checking pubDate...");
							if (isset($f['pubDate']) && $f['pubDate'] >= $pubDate) {
								$this->log("Newer file found for this scraper (".($f['pubDate'])." >= $pubDate), skipping description parsing...");
								$fetchData = FALSE;
							} else {
								$this->log("Older file found for this scraper (".($f['pubDate'])." >= $pubDate), still candidate...");
							}
						}
					}
				}
			} else {
				$this->log("Can't guess episode n, keeping as candidate anyway...");
			}
			
			if ($fetchData) {

				$this->log("Looking for attachments...");
				$x3 = $xpath->query(".//enclosure[@type='application/x-bittorrent']", $node);
				for ($j = 0; $j < $x3->length; $j++) {
					$linkTorrent = $x3->item($j)->getAttribute('url');
					if (strlen($linkTorrent) > 0) {
						$this->log("Found torrent link $linkTorrent");
						$c = array(
								'pubDate'	=> $pubDate,
								'link'		=> $linkTorrent,
								'type'		=> 'torrent'
						);
						if ($fileData != false) {
							$c['season'] = $fileData['season'];
							$c['episode'] = $fileData['episode'];
						}
						
						$candidates[] = $c;
					}
				}

				$this->log("Looking for http links in description...");
				$regex = '/href\s*=\s*(["' . "'" .'])(http:\/\/.+?)\1/';
				$matches = array();
				preg_match_all($regex, $description, $matches);
				
				if (sizeof($matches) > 0) {
					for ($j = 0; $j < sizeof($matches[2]); $j++) {
						$link = $matches[2][$j];
				
						$this->log("$link is an http link. Fetching page...");

						$links = $this->parseHttpDescription($browser, $link);
						foreach ($links as $l) {
							$l['pubDate'] = $pubDate;
							$l['season'] = $fileData['season'];
							$l['episode'] = $fileData['episode'];
							$candidates[] = $l;
						}
					}
				}
			}
		}
		
		
		return $this->submitEpisodeCandidates($scraper, $candidates, $showOnlyNew, $saveResults);
		
	}

	protected function parseHttpDescription($browser, $url) {
		return array();
				
	}
	
}




?>
