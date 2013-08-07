<?php 

require_once('Logger.php');
require_once('SimpleBrowser.php');
require_once('TVShowScraperRSS.php');

require_once('TVShowUtils.php');

class TVShowScraperTVU extends TVShowScraperRSS {
	
	public function runScraperTVShow($scraper, $showOnlyNew = false, $saveResults = false) {


		$this->log("Parsing TVU page " . $scraper['uri']);
		
		$browser = new SimpleBrowser();
		$browser->setLogger($this->logger);
		$page = $browser->get($scraper['uri']);

		$dom = new DOMDocument();
		if (! @$dom->loadHTML($page)) return $this->error('Cannot load HTML page');

		$xpath = new DOMXPath($dom);
		if (! $xpath) return $this->error('Cannot create XPATH handler');

		$showData = $this->tvdb->getTVShow($scraper['tvshow']);
		$lang = isset($showData['lang']) ? $showData['lang'] : 'ita';


		$x = $xpath->query("//table[@class='seriestable']//text()[contains(.,'Dub')]/ancestor::td[descendant::img[contains(@alt,'$lang')]]/ancestor::table[@class='seriestable']//a[contains(@href, 'sid=')]");

		$res = array();

		for ($i = 0; $i < $x->length; $i++) {
			$href = $x->item($i)->getAttribute('href');
			$title = $x->item($i)->textContent;
			$this->log("Found season link $href, title $title");
			$m = array();
			if (preg_match('/[&\?]sid=(\d+)/', $href, $m)) {
				$uri = 'http://tvunderground.org.ru/rss.php?se_id=' . $m[1];
				$this->log("Candidate URI $uri");

				$n = '';
				if (preg_match('/(stagione|season)\s+(\d+)/i', $title, $m)) {
					$n = $m[2];
				}

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

		return array();
	}

	protected function parseHttpDescription($browser, $url) {

		if (preg_match('/\/torrent\.php\?/', $url)) return array(array('link' => $url, 'type' => 'torrent'));

		$res = array();

		$subPage = $browser->get($url);
			
		$regexED2K = '/href\s*=\s*(["' . "'" .'])(ed2k:\/\/.+?)\1/';
		$matchesED2K = array();
		preg_match_all($regexED2K, $subPage, $matchesED2K);
		if (sizeof($matchesED2K) > 0) {
			for ($k = 0; $k < sizeof($matchesED2K[2]); $k++) {
				$linkED2K = $matchesED2K[2][$k];
				$this->log("Got HREF " . $linkED2K);
				$res[] = array('link' => $linkED2K, 'type' => 'ed2k');
			}
		}

		return $res;
				
	}
}

?>
