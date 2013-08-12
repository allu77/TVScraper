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
				$sid = $m[1];

				$n = '';
				if (preg_match('/(stagione|season)\s+(\d+)/i', $title, $m)) {
					$n = $m[2];
				}

				$resolution = NULL;
				if (preg_match('/\(([^\)]+)\)\s*$/', $title, $m)) {
					$this->log("Candidate resolution = " . $m[1]);
					$resolution = $m[1];
				}

				foreach (array('rss.php', 'rsst.php') as $q) {
					$res[] = array(
						'uri' => "http://tvunderground.org.ru/$q?se_id=$sid",
						'n' => $n,
						'res' => $resolution,
						'lang' => array($lang)
					);
				}
			}
		}

		return $this->submitSeasonCandidates($scraper, $res, $showOnlyNew, $saveResults);
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

	protected function getPage($browser, $uri) {
		$page = $browser->get($uri);

		// FIX for buggy empty feed
		if (!preg_match("/<channel\s*\\/>/", $page) && !preg_match("/<channel[^>]*>.*<\\/channel>*/s", $page)) {
			$this->log("Malformed feed from TVU. Replacing with empty feed.");
			return "<?xml version=\"1.0\" encoding=\"utf-8\"?><rss version=\"2.0\"><channel/></rss>";
		} else {
			return $page;
		}
	}
}

?>
