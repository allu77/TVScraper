<?php

abstract class TVShowScraper {
	protected $user;
	protected $pass;
	protected $tvdb;

	protected $logger;

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
	
	protected function error($msg) {
		if ($this->logger) $this->logger->error($msg);
		return FALSE;
	}
	
	public function __construct($tvdb) {
		$this->tvdb = $tvdb;
	}
	

	public function runScraper($scraperId, $showOnlyNew = false, $saveResults = false) {
		$scraper = $this->tvdb->getScraper($scraperId);
		if (isset($scraper['season'])) {
			return $this->runScraperSeason($scraper, $showOnlyNew, $saveResults);
		} else if (isset($scraper['tvshow'])) {
			return $this->runScraperTVShow($scraper, $showOnlyNew, $saveResults);
		}
	}
	
	protected function submitEpisodeCandidates($scraperData, $candidateLinks, $showOnlyNew = false, $saveResults = false) {
		$seasonData = $this->tvdb->getSeason($scraperData['season']);
		$res = array();

	
		
		for ($j = 0; $j < sizeof($candidateLinks); $j++) {
			$link = $candidateLinks[$j]['link'];

			$this->log("Evaluating candidate $j: $link");

			$fileNameParts = FALSE;

			if (isset($candidateLinks[$j]['type']) && $candidateLinks[$j]['type'] == 'torrent') {
				if (isset($candidateLinks[$j]['episode']) && isset($candidateLinks[$j]['season'])) {
					$fileNameParts['season'] = $candidateLinks[$j]['season'];
					$fileNameParts['episode'] = $candidateLinks[$j]['episode'];
				}
			} else {
				$linkParts = parseED2KURI($link);
				if ($linkParts === FALSE) {
					$this->log("Invalid link $link found");
				} else {
					$fileNameParts = parseEpisodeFileName($linkParts['fileName']);
				}
			}
			

			if ($fileNameParts === FALSE) {
				$this->log("Couldn't guess season and episode from $link. Skipping.");
			} else {
				if ($seasonData['n'] != $fileNameParts['season']) {
					$this->log("Season index different than expected (expected " . $seasonData['n'] . ", got " . $fileNameParts['season']);
				} else {
					if (! $showOnlyNew) {
						$res[] = $link;
					} else {
						$episode = $this->tvdb->getEpisodeFromIndex($seasonData['tvshow'], $fileNameParts['season'], $fileNameParts['episode']);
						if ($episode == FALSE && $saveResults) {
							$this->log("Creating new episode");
							$episodeId = $this->tvdb->addEpisode($scraperData['season'], Array('n'=>$fileNameParts['episode']));
							$episode = $this->tvdb->getEpisode($episodeId);
						}
	
						$addFile = TRUE;
						if ($episode != FALSE) {
							$candidates = $this->tvdb->getFilesForEpisode($episode['id']);
							foreach ($candidates as $fileData) {
								if ($fileData['uri'] == $link && isset($fileData['scraper']) && $fileData['scraper'] == $scraperData['id']) {
									$this->log("A file with the same URI and scraper already exists. Skipping...");
									$addFile = FALSE;
									break;
								}
							}
						}
						if ($addFile) {
							if ($saveResults) {
								$this->log("Creating new file");
								$this->tvdb->addFile($seasonData['tvshow'], Array(
										'uri'		=> $link,
										'season'	=> $scraperData['season'],
										'episode'	=> $episode['id'],
										'scraper'	=> $scraperData['id'],
										'pubDate'	=> $candidateLinks[$j]['pubDate'],
										'type'		=> isset($candidateLinks[$j]['type']) ? $candidateLinks[$j]['type'] : 'ed2k'
								));
							}
							$res[] = $link;
						}
					}
				}
			}
		}
		
		return $res;
		
	}
	
	abstract protected function runScraperSeason($scraper, $showOnlyNew = false, $saveResults = false);
	abstract protected function runScraperTVShow($scraper, $showOnlyNew = false, $saveResults = false);
	
	
	
}

?>
