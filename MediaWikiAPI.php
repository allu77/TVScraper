<?php

require_once('Logger.php');
require_once('SimpleBrowser.php');

class MediaWikiAPI {

	protected $baseUrl;
	protected $logger;
	protected $browser;

	public function setLogger($logger) {
		$this->logger = $logger;
		if ($this->browser != NULL) $this->browser->setLogger($logger);
	}

	public function setLogFile($logFile, $severity = LOGGER_DEBUG) {
		$this->logger = new Logger($logFile, $severity);
		if ($this->browser != NULL) $browser->setLogFile($logFile, $severity);
	}

	protected function log($msg, $severity = LOGGER_DEBUG) {
		if ($this->logger) {
			$this->logger->log($msg, $severity);
		}
	}

	public function __construct($baseUrl) {
		$this->baseUrl = $baseUrl;
		$this->browser = new SimpleBrowser();
	}

	public function getPageIdByTitle($pageTitle) {
		$url = "$this->baseUrl?action=query&format=xml&prop=info&titles=$pageTitle";
		$this->log("Fetching URL $url"); 
		$xml = DOMDocument::loadXML($this->browser->get($url));
		$xPath = new DOMXPath($xml);
		$p = $xPath->query("//page");
		if ($p->length != 1) {
			return -($p->length);
		}
		$pageId = $p->item(0)->getAttribute("pageid");
		return $pageId ? $pageId : 0;
	}

	public function getLinksByPageId($pageId) {
		$url = "$this->baseUrl?action=query&format=xml&prop=links&pageids=$pageId&pllimit=max";
		$continue = "&continue=";
		$links = Array();
		while (strlen($continue) > 0) {
			$this->log("Fetching URL $url$continue"); 
			$xml = DOMDocument::loadXML($this->browser->get($url.$continue));
			$xPath = new DOMXPath($xml);
			$l = $xPath->query("//pl");
			for ($i = 0; $i < $l->length; $i++) {
				$links[] = $l->item($i)->getAttribute('title');	
			}
			$c = $xPath->query('//continue');
			$continue = '';
			$this->log("Result has " . $c->length . " continue elements.");
			if ($c->length == 1) {
				foreach($c->item(0)->attributes as $name => $node) {
					$continue .= "&$name=".$node->nodeValue;
				}
			}
		}
		return $links;
	}

	public function getContentByPageId($pageId) {
		$url = "$this->baseUrl?action=query&format=xml&prop=revisions&rvprop=content&rvlimit=1&pageids=$pageId";
		$this->log("Fetching URL $url"); 
		$xml = DOMDocument::loadXML($this->browser->get($url));
		$xPath = new DOMXPath($xml);
		$r = $xPath->query('//rev');
		if ($r->length == 1) {
			return $r->item(0)->textContent;
		} else {
			return null;
		}
	}
}

