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
			$ret = $this->runScraperSeason($scraper, $showOnlyNew, $saveResults);
			if ($ret != FALSE && sizeof($ret) > 0 && $saveResults) {
				$this->tvdb->resetBestFilesForSeason($scraper['season']);
			}
			return $ret;

		} else if (isset($scraper['tvshow'])) {
			return $this->runScraperTVShow($scraper, $showOnlyNew, $saveResults);
		}
	}

	protected function submitSeasonCandidates($scraperData, $candidateLinks, $showOnlyNew = false, $saveResults = false) {
		$showData = $this->tvdb->getTVShow($scraperData['tvshow']);
		$showSeasons = $this->tvdb->getTVShowSeasons($scraperData['tvshow']);
		$latestComplete = NULL;
		foreach ($showSeasons as $s) {
			if (isset($s['status']) && $s['status'] == 'complete' && ($latestComplete == NULL || $latestComplete < $s['n'])) {
				$latestComplete = $s['n'];
				$this->log("Found complete season $latestComplete");
			}
		}

		$res = array();
		
		foreach ($candidateLinks as $link) {
			$this->log("Evaulating link " . $link['uri']);

			# Removing heading 0s
			$newN = preg_replace("/^\s*0*([1-9]\d*)/", "\\1", $link['n']);
			$link['n'] = $newN;

			if (! $showOnlyNew) {
				$res[] = $link;
			} else {
				$keepCandidate = TRUE;
				if ($latestComplete != NULL && isset($link['n']) && $link['n'] != "") {
					if ($link['n'] <= $latestComplete) {
						$this->log("Season is older than latest complete. Skipping.");
						$keepCandidate = FALSE;
					}
				}


				if ($keepCandidate && isset($showData['res']) && $showData['res'] != 'any' && isset($link['res'])) {
					$this->log("Candidate resolution = " . $link['res']);
					if (! checkResolution($showData['res'], $link['res'])) {
						$this->log("Undesired resolution, skipping...");
						$keepCandidate = FALSE;
					} else {
						$this->log("Resolution looks good, still checking...");
					}
				}

				if ($keepCandidate && isset($showData['lang']) && isset($link['lang']) && sizeof($link['lang'] > 0)) {
					$this->log("Candidate languages = " . implode(', ', $link['lang']));
					if (!in_array($showData['lang'], $link['lang'])) {
						$this->log("Cannot find required language ".$showData['lang'].", skipping...");
						$keepCandidate = FALSE;
					} else {
						$this->log("Language looks good, still checking...");
					}
				}

				if ($keepCandidate) {
					$previouslyScraped = $this->tvdb->getScrapedSeasonFromUri($scraperData['id'], $link['uri'], isset($link['n']) ? $link['n'] : NULL);
					$keepCandidate = $previouslyScraped == NULL ? TRUE : FALSE;
				}

				if ($keepCandidate) {
					if ($saveResults) {
						$this->log("New season, adding...");
						$p = array(
								'uri' => $link['uri'],
								'n' => $link['n']
						);
						if (isset($scraperData['notify']) && $scraperData['notify'] == "1") $p['tbn'] = '1';
						$newId = $this->tvdb->addScrapedSeason($scraperData['id'], $p);
							
						if (isset($scraperData['autoAdd']) && $scraperData['autoAdd'] == "1"  && $n > 0) {
							$this->tvdb->createSeasonScraperFromScraped($newId);
						}
					}
					$res[] = $link;
				}
			}
		}

		return $res;
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
								$this->tvdb->addFile($episode['id'], Array(
										'uri'		=> $link,
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
