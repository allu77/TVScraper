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
		$showTitle = preg_replace('/([\\(\\)\\[\\]])/', '\\\\$1?', $showTitle);

		$alternateTitle = (isset($showData['alternateTitle']) && $showData['alternateTitle'] != '') ? strtolower($showData['alternateTitle']) :  strtolower($showData['title']);
		$alternateTitle = preg_replace('/\s+/', '\s+', $alternateTitle);
		$alternateTitle = preg_replace('/[!\?\.\']/', '', $alternateTitle);
		$alternateTitle = preg_replace('/([\\(\\)\\[\\]])/', '\\\\$1?', $alternateTitle);

		$this->log("Looking for $showTitle or $alternateTitle");

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
				$this->log("Evaluating " . $t['title'] . " / " . $t['description']);


				if (preg_match('/(.*\S)\s*-\s*stagione\s*(\d+)/i', $t['title'], $m)) {
					$this->log("Found TV show title $m[1], season $m[2]");


					$n = $m[2];
					$candidateTitle = $m[1];
					$candidateTitle = preg_replace('/[!\?\.\'\-]/', '', $m[1]);
					if (preg_match("/^$showTitle\s*$/i", $candidateTitle) || 
						preg_match("/^$showTitle\s*\(.*\)$\s*/i", $candidateTitle) || 
						preg_match("/^.*\(\s*$showTitle\s*\)\s*$/i", $candidateTitle) || 
						preg_match("/^$alternateTitle\s*$/i", $candidateTitle) || 
						preg_match("/^$alternateTitle\s*\(.*\)$\s*/i", $candidateTitle) || 
						preg_match("/^.*\(\s*$alternateTitle\s*\)\s*$/i", $candidateTitle) 
						) {

						$uri = $t['link'];
						$this->log("Title matches! $uri");

						$tags = explode(',', $t['description']);
						if (
							(
								(isset($showData['res']) && $showData['res'] != 'any' && $showData['res'] != '') || 
								(isset($showData['lang']) && $showData['lang'] != '')  
							)  
							&& sizeof($tags) > 4) {

							$this->log("Parsing tags " . $t['description']);
							
							$source = trim($tags[0]);
							$type = trim($tags[1]);
							
							$tags[2] = trim($tags[2]);
							$resolution = ($tags[2] == '720p' || $tags[2] == '1080i' || $tags[2] == '1080p') ? $tags[2] : 'sd';
							$tagIndex = $resolution == 'sd' ? 2 : 3;
	
							$videoCodec = trim($tags[$tagIndex++]);

							$lm = array();
							$langs = array();
							$subs = array();
							while (preg_match("/^\s*([A-Za-z0-9\/]+)\s+([A-Za-z\-]+)\s*?/", $tags[$tagIndex++], $lm)) {
								$this->log("Found audio or sub - $lm[1], $lm[2]");
								$foundLang = explode('-', $lm[2]);
								if ($lm[1] == 'SUB') {
									foreach($foundLang as $l) $subs[] = strtolower($l);
								} else {
									foreach($foundLang as $l) $langs[] = strtolower($l);
								}
							}

							$containter = trim($tags[$tagIndex]);
							$targetSize = $tagIndex > sizeof($tags) ? '' : trim($tags[$tagIndex + 1]);

							$this->log("S: $source, T: $type, R: $resolution, V: $videoCodec, L: ". join('/', $langs) .", S: ".join('/', $subs).", C: $containter, T: $targetSize");


							$res[] = array(
								'n'		=> $n,
								'uri'	=> $uri,
								'res'	=> $resolution,
								'lang'	=> $langs
							);
						} else {
							$this->log("Couldn't parse post tags");
							$res[] = array(
								'n'		=> $n,
								'uri'	=> $uri,
							);
						}
					}
				}
			}
		}

		return $this->submitSeasonCandidates($scraper, $res, $showOnlyNew, $saveResults);

	}
	
	protected function runScraperSeason($scraperData, $showOnlyNew = false, $saveResults = false) {
		
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
