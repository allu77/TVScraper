<?php

define('DDUPARSER_WARN_TOPICS', 0);
define('DDUPARSER_WARN_LAYOUT', 1);

class DDUParser {

	protected $page;
	protected $baseURL;
	protected $baseURLInfo;
	protected $baseURLPathInfo;
	protected $topics;

	protected $board;
	protected $topic;

	protected $logger;

	protected $sanityWarnings;

	public function __construct() {
		$this->sanityWarning = array();
	}

	protected function sanityWarning($warnType, $warnMsg) {
		if (!isset($this->sanityWarning[$warnType])) {
			$this->sanityWarning[$warnType] = array();
		}
		$this->sanityWarning[$warnType][] = $warnMsg;
		$this->log($warnMsg, LOGGER_WARNING);
	}

	public function getSanityWarnings() {
		return $this->sanityWarning;
	}

	public function setLogger($logger) {
		$this->logger = $logger;
	}

	public function setLogFile($logFile, $severity = LOGGER_DEBUG) {
		$this->logger = new Logger($logFile, $severity);
	}

	protected function log($msg, $severity = LOGGER_DEBUG) {
		if ($this->logger) {
			$this->logger->log($msg, $severity);
		}
	}
	private static function compByPubDate ($a, $b) {
		return ($a['pubDate'] > $b['pubDate']) ? +1 : -1;
	}

	private function getXpath() {
		$dom = new DOMDocument();
		if (!@$dom->loadHTML($this->page)) {
			trigger_error('Cannot load HTML page', E_USER_ERROR);
		}
		$xpath = new DOMXPath($dom);
		if (! $xpath) {
			trigger_error('Cannot create XPATH handler', E_USER_ERROR);
		}

		return $xpath;
	}

	public function setPage($page) {
		$this->page = $page;
	}

	public function setBaseURL($url) {
		$this->baseURL = $url;
		$this->baseURLInfo = parse_url($url);

		if (!isset($this->baseURLInfo['user'])) {
			$this->baseURLInfo['user'] = '';
		}
		if (!isset($this->baseURLInfo['pass'])) {
			$this->baseURLInfo['pass'] = '';
		}
		if (!isset($this->baseURLInfo['path'])) {
			$this->baseURLInfo['path'] = '';
		}
		if (!isset($this->baseURLInfo['path'])) {
			$this->baseURLInfo['path'] = '';
		}

		$this->baseURLPathInfo = pathinfo($this->baseURLInfo['path']);

		if (!isset($this->baseURLPathInfo['dirname'])) {
			$this->baseURLPathInfo['dirname'] = '';
		}
	}

	private function getFullURL($url) {
		$urlInfo = parse_url($url);

		if (isset($urlInfo['host'])) {
			return $url;
		} else {

			$urlInfo['scheme'] = $this->baseURLInfo['scheme'];
			$urlInfo['host'] = $this->baseURLInfo['host'];
			$urlInfo['user'] = $this->baseURLInfo['user'];
			$urlInfo['pass'] = $this->baseURLInfo['pass'];
			$urlInfo['path'] = $this->baseURLPathInfo['dirname'] . '/' . $urlInfo['path'];
			
			$newURL = $urlInfo['scheme'] . '://';
			if ($urlInfo['user']) {
				$newURL .= $urlInfo['user'];
				if ($urlInfo['pass']) {
					$newURL .= ':' . $urlInfo['pass'];
				}
				$newURL .= '@';
			}
			$newURL .= $urlInfo['host'] . $urlInfo['path'];
			if (isset($urlInfo['query'])) {
				$newURL .= '?' . $urlInfo['query'];
			}
			if (isset($urlInfo['anchor'])) {
				$newURL .= '#' . $urlInfo['anchor'];
			}
			return $newURL;
					
		}
	}

	protected function getTopicsFromNodeList($xpath, $nodeList, $nonStickyType) {

		$topics = Array();
		$lastPubDate = 0;

		for ($i = 0; $i < $nodeList->length; $i++) {

			$p = Array();

			$topic = $nodeList->item($i);
			$liClass = $topic->getAttribute('class');
			$p['type'] = (stripos($liClass, 'sticky') == false) ? $nonStickyType : 'sticky';

			$dtList = $xpath->query('*//dt', $topic);
			if ($dtList->length > 0) {
				$dt = $dtList->item(0);
				$link = $xpath->query('*//a[contains(@class, "topictitle")]', $dt);

				if ($link->length > 0) {
					$href = trim($this->getFullURL($link->item(0)->getAttribute('href')));
					$text = trim($link->item(0)->textContent);

					$p['title'] = utf8_encode($text);
					$p['link'] = $href;
				}
				//TODO: This has changed
				$br = $xpath->query('br', $dt);
				if ($br->length > 0) {
					$desc = trim($br->item(0)->nextSibling->textContent);
					$p['description'] = utf8_encode($desc);
				} else {
					$p['description'] = '';
				}
				//TODO: This has changed
				$author = $xpath->query('span[contains(@class, "username-coloured")]', $dt);
				$matched = array();
				if (preg_match('/\S+\s+\S+\s+\d+,?\s+\d+\s+\d+:\d+\s+\S+/', $dt->lastChild->textContent, $matches)) {
					$date = $matches[0];
					$this->log("Matched date $date");
					$p['pubDate'] = strtotime($date);
					
					if (!$p['pubDate']) {
						$p['pubDate'] = 0;
						$this->sanityWarning(DDUPARSER_WARN_TOPICS, "Couldn't parse pulished date for topic '".$p['title']."'");
					}

					if ($p['type'] == 'regular') {
						if ($lastPubDate && $p['pubDate'] > $lastPubDate) {
							$this->sanityWarning(DDUPARSER_WARN_TOPICS, "Topics don't seem to be in descending order: last '" . date('r', $lastPubDate) . "' current '".date('r', $p['pubDate']) . "'");
						}
						$lastPubDate = $p['pubDate'];
					}
				} else {
					$p['pubDate'] = 0;
					$this->sanityWarning(DDUPARSER_WARN_TOPICS, "Couldn't find pulished date for topic '".$p['title']."'");
				}
			} 

			$topics[] = $p;
		}

		return $topics;
	}

	public function getTopicList() {

		if (isset($this->topics)) {
			return $this->topics;
		}

		$xpath = $this->getXpath();
		$topicDivs = $xpath->query('/html/body//div[contains(@class, "forumbg")]');

		if ($topicDivs->length == 0) {
			trigger_error('Cannot get topic DIVs', E_USER_ERROR);
		}

		$this->topics = Array();

		for ($i = 0; $i < $topicDivs->length; $i++) {
			$divClass = $topicDivs->item($i)->getAttribute('class');
			$nonStickyType = (stripos($divClass, 'announcement') == false) ? 'regular' : 'announcement';

			$this->log("Found topic DIV with class $divClass, setting non-sticky type to $nonStickyType");

			$topicList = $xpath->query('*//ul[contains(@class, "topics")]/li', $topicDivs->item($i));
			$newTopics = $this->getTopicsFromNodeList($xpath, $topicList, $nonStickyType);

			$this->log("Found " . sizeof($newTopics) . " topics in this DIV");
			if (sizeof($newTopics) == 0) {
				$this->sanityWarning(DDUPARSER_WARN_LAYOUT, "No topic found in DIV numer $i");
			}

			$this->topics = array_merge($this->topics, $newTopics);
		}

		usort($this->topics, array('DDUParser', 'compByPubDate'));

		for ($i = 0; $i < sizeof($this->topics) && $this->topics[$i]['type'] != 'regular'; $i++) {
			unset($this->topics[$i]);
		}
		$this->topics = array_values($this->topics);

		if (sizeof($this->topics) == 0) {
			$this->sanityWarning(DDUPARSER_WARN_TOPICS, "No regular topics found for this board");
		} elseif (time() - $this->topics[sizeof($this->topics) - 1]['pubDate'] > 30 *24* 60 * 60) {
			$this->sanityWarning(DDUPARSER_WARN_TOPICS, "Latest topic is older than 30 days");
		}

		return $this->topics;
	}

	public function getBoardDetails() {
		if (isset($this->board)) {
			return $this->board;
		}

		$this->board = Array();

		$xpath = $this->getXpath();
		$title = $xpath->query('/html/head/title');

		$this->board['title'] = trim($title->item(0)->textContent);
		$this->board['description'] = $this->board['title'];

		return $this->board;

	}

	public function getTopicListAsLi() {
		$this->getTopicList();
		for ($i = sizeof($this->topics) - 1; $i >= 0; $i--) {
			$topic = $this->topics[$i];
			print "<li>";
			print "<dt><a href='".$topic['link']."'>" . $topic['title'] . "</a></dt>";
			print "<dd>" . $topic['description'] . "</dd>";
			print "<dd>" . date('r', $topic['pubDate']) . "</dd>";
			print "<dd>" . $topic['type'] . "</dd>";

			print "</li>";

		}
	}

	public function getBoardFeed() {

		$this->getTopicList();
		$this->getBoardDetails();

		$pDom = new DOMDocument();

		$pRSS = $pDom->createElement('rss');
		$pRSS->setAttribute('version', 2.0);
		$pDom->appendChild($pRSS);

/*	
		$pChannel = $pDom->createElement('channel');
		$pRSS->appendChild($pChannel);

		$pTitle = $pDom->createElement('title', $this->board['title']);
		$pLink  = $pDom->createElement('link', $this->baseURL);
		$pDesc  = $pDom->createElement('description', $this->board['title']);
		$pLang  = $pDom->createElement('language', 'it');
		$pChannel->appendChild($pTitle);
		$pChannel->appendChild($pLink);
		$pChannel->appendChild($pDesc);
		$pChannel->appendChild($pLang);

		for ($i = sizeof($this->topics) - 1; $i >= 0; $i--) {
			$topic = $this->topics[$i];

			$pItem  = $pDom->createElement('item');
			$pTitle = $pDom->createElement('title', $topic['title']);
			$pDesc  = $pDom->createElement('description', $topic['description']);
			$pDate  = $pDom->createElement('pubDate', date('r', $topic['pubDate']));

			$pLink  = $pDom->createElement('link');
			$pURL   = $pDom->createCDATASection($topic['link']);
			$pLink->appendChild($pURL);

			$pItem->appendChild($pTitle);
			$pItem->appendChild($pLink);
			$pItem->appendChild($pDesc);
			$pItem->appendChild($pDate);

			$pChannel->appendChild($pItem);
		}
 */		

		$pChannel = $this->getChannelFromItemList($pDom, $this->topics, $this->board['title'],  $this->baseURL,  $this->board['title'], 'it');
		$pRSS->appendChild($pChannel);

		return @$pDom->saveXML();
	}


	public function getChannelFromItemList($pDom, $list, $title = NULL, $link = NULL, $description = NULL, $language = NULL) {

		$pChannel = $pDom->createElement('channel');
		if ($title) {
			$pTitle = $pDom->createElement('title', $title);
			$pChannel->appendChild($pTitle);
		}
		if ($link) {
			$pLink  = $pDom->createElement('link', $link);
			$pChannel->appendChild($pLink);
		}
		if ($description) {
			$pDesc  = $pDom->createElement('description', $description);
			$pChannel->appendChild($pDesc);
		}
		if ($language) {
			$pLang  = $pDom->createElement('language', $language);
			$pChannel->appendChild($pLang);
		}

		for ($i = sizeof($list) - 1; $i >= 0; $i--) {

			$pItem  = $pDom->createElement('item');

			if (isset($list[$i]['title'])) {
				$pTitle  = $pDom->createElement('title');
				$pTitleC = $pDom->createCDATASection($list[$i]['title']);
				$pTitle->appendChild($pTitleC);
				$pItem->appendChild($pTitle);
			}
			if (isset($list[$i]['description'])) {
				$pDesc  = $pDom->createElement('description');
				$pDescC = $pDom->createCDATASection($list[$i]['description']);
				$pDesc->appendChild($pDescC);
				$pItem->appendChild($pDesc);
			}
			if (isset($list[$i]['link'])) {
				$pLink  = $pDom->createElement('link');
				$pLinkC = $pDom->createCDATASection($list[$i]['link']);
				$pLink->appendChild($pLinkC);
				$pItem->appendChild($pLink);
			}

			if (isset($list[$i]['pubDate'])) {
				$pDate  = $pDom->createElement('pubDate', date('r', $list[$i]['pubDate']));
				$pItem->appendChild($pDate);
			}

			$pChannel->appendChild($pItem);
		}

		return $pChannel;

	}

	public function getED2KLinksFromTopic() {
		if (isset($this->links)) {
			return $this->links;
		}

		$this->links = array();

		$xpath = $this->getXpath();
/*		$postList = $xpath->query('/html/body//div[contains(@class, "postbody")]');

		$this->log("Found " . $postList->length . " posts in this topic");	
		if ($postList->length == 0) {
			$this->sanityWarning(DDUPARSER_WARN_LAYOUT, "Couldn't find any post in topic");
		} else {
			$firstPost = $postList->item(0);
			
			//TEMPORANEO
			$temp_doc = new DOMDocument('1.0', 'UTF-8');
			$temp_node = $temp_doc->importNode($firstPost, TRUE);
			$temp_doc->appendChild($temp_node);
			$this->log("POST XML:\n" . $temp_doc->saveHTML() . "\n");
	*/			
			
		//	$aList = $xpath->query('*/a[translate(substring(@href, 1, 4), "EDK", "edk") = "ed2k"]', $firstPost);
	//		$aList = $xpath->query('/html/body//a[translate(substring(@href, 1, 4), "EDK", "edk") = "ed2k"]');



	/*		for ($i = 0; $i < $aList->length; $i++) {
				$a = $aList->item($i);
				$this->log("Got HREF " . $a->getAttribute('href'));

				$this->links[] = array(
					'title'	=> $a->textContent,
					'link'	=> $a->getAttribute('href'),
				);
	}*/

			$matches = array();
			$regex = '/href\s*=\s*(["' . "'" .'])(ed2k:\/\/.+?)\1/';
			preg_match_all($regex, $this->page, $matches);

			if (sizeof($matches) == 0) {
				$this->sanityWarning(DDUPARSER_WARN_LAYOUT, "Couldn't find any ed2k link");
			} else {
				for ($i = 0; $i < sizeof($matches[2]); $i++) {
					$link = $matches[2][$i];
					$this->log("Got HREF " . $link);

					$this->links[] = array(
						'title'	=> $link,
						'link'	=> $link,
					);

				}
			}
		//}
		

		

		return $this->links;
	}

	public function getTopicDetails() {
		if (isset($this->topic)) {
			return $this->topic;
		}

		$this->topic = array();

		$xpath = $this->getXpath();
		$t = $xpath->query('/html/body//div[contains(@class, "postbody")]//h3[contains(@class, "first")]/a');
	
		if ($t->length == 0) {
			$this->sanityWarning(DDUPARSER_WARN_LAYOUT, "Couldn't find topic title");
		} else {
			$title = $t->item(0)->textContent;
			$this->log("Found topic detail: $title");
			$d = $xpath->query('/html/body//div[contains(@class, "postbody")]//h4');
			if ($d->length == 0) {
				$this->sanityWarning(DDUPARSER_WARN_LAYOUT, "Couldn't find topic description");
			} else {
				$description = $d->item(0)->textContent;
				$this->log("Found topic detail: $description");

				$this->topic['title'] = $title;
				$this->topic['description'] = $description;
			}
		}

		return $this->topic;

	}



	public function getED2KLinksAsChannel($pDom) {
		$this->getED2KLinksFromTopic();
		$this->getTopicDetails();
		return $this->getChannelFromItemList($pDom, $this->links, $this->topic['title'], htmlentities($this->baseURL), $this->topic['description']);
	}

}

?>
