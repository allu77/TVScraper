<?php 
require_once('TVShowScraper.php');
require_once('DDUBrowser.php');
require_once('DDUParser.php');

require_once('TVShowUtils.php');

class TVShowScraperDDU extends TVShowScraper {
	protected $user;
	protected $pass;


	
	
	public function __construct($tvdb, $user, $pass) {
		$this->user = $user;
		$this->pass = $pass;
		
		parent::__construct($tvdb);
	}
	
	
	
	protected function runScraperTVShow($scraper, $showOnlyNew = false, $saveResults = false) {

		$res = array();

		$showData = $this->tvdb->getTVShow($scraper['tvshow']);

		$showTitle = strtolower($showData['title']);
		$showTitle = preg_replace('/\s+/', '\s+', $showTitle);
		$showTitle = preg_replace('/[!\?\.\']/', '', $showTitle);

		$this->log("Looking for $showTitle");

		for ($offset = 0; $offset < 100; $offset += 25) {	
			$uri = $scraper['uri'] . "&start=$offset";
			$this->log("Parsing DDU page $uri");
			
			$browser = new DDUBrowser();
			$browser->setLogger($this->logger);
			$browser->setLogin($this->user, $this->pass);
			$page = $browser->get($uri);
			
			$parser = new DDUParser();
			$parser->setLogger($this->logger);
			$parser->setPage($page);
			$parser->setBaseURL($uri);

			$topics = $parser->getTopicList();

			foreach ($topics as $t) {
				$m = array();
				$this->log("Evaluating " . $t['title']);
				if (preg_match('/(.*\S)\s*-\s*stagione\s*(\d+)/i', $t['title'], $m)) {
					$this->log("Found TV show title $m[1], season $m[2]");
					$n = $m[2];
					$candidateTitle = $m[1];
					$candidateTitle = preg_replace('/[!\?\.\']/', '', $m[1]);
					if (preg_match("/^$showTitle\s*$/i", $candidateTitle) || 
						preg_match("/^$showTitle\s*\(.*\)$\s*/i", $candidateTitle) || 
						preg_match("/^.*\(\s*$showTitle\s*\)\s*$/i", $candidateTitle) ) {

						$uri = $t['link'];
						$this->log("Title matches! $uri");

						$previouslyScraped = $this->tvdb->getScrapedSeasonFromUri($scraper['id'], $uri);

						$addNewSeasons = isset($scraper['autoAdd']) && $scraper['autoAdd'] == "1" ? TRUE : FALSE;

						if ((!$showOnlyNew) || $previouslyScraped == NULL) {
							if ($saveResults && $previouslyScraped == NULL) {
								$this->log("New season, adding...");
								$p = array(
										'uri' => $uri,
										'n' => $n
								);
								if (isset($scraper['notify']) && $scraper['notify'] == "1") $p['tbn'] = '1';
								$newId = $this->tvdb->addScrapedSeason($scraper['id'], $p);
								
								if ($addNewSeasons && $previouslyScraped == NULL && $n > 0) {
									$this->tvdb->createSeasonScraperFromScraped($newId);
								}
									
								
							}
							$res[] = Array(
								'n'		=> $n,
								'uri'	=> $uri
							);
						}
					}
				}
			}
		}

		return $res;

		//return $this->error("TVShow Scraper not defined for DDU");
	}
	
	protected function runScraperSeason($scraperData, $showOnlyNew = false, $saveResults = false) {
		
//		$seasonData = $this->tvdb->getSeason($scraperData['season']);
		
		$uri = $scraperData['uri'];
		
		
		
		$this->log("Fetching DDU page $uri");
		$browser = new DDUBrowser();
		$browser->setLogger($this->logger);
		$browser->setLogin($this->user, $this->pass);
		$page = $browser->get($uri);
		$this->log("Page is bytes ".sizeof($page)." long");
		
		$this->log("Initializing parser");
		$parser = new DDUParser();
		$parser->setLogger($this->logger);
		$parser->setPage($page);
		$parser->setBaseURL($uri);
		
		$this->log("Fetching ED2K links from topic page");
		$epList = $parser->getED2KLinksFromTopic();
		
		$candidates = array();
		$t = time();
		for ($j = 0; $j < sizeof($epList); $j++) {
			$candidates[] = array('link' => $epList[$j]['link'], 'pubDate' => $t);
		}
		
		return $this->submitEpisodeCandidates($scraperData, $candidates, $showOnlyNew, $saveResults);
		

		return $res;
	}
	
}




?>
