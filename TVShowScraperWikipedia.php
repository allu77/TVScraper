<?php

require_once('MediaWikiAPI.php');
require_once('TVShowScraper.php');

class TVShowScraperWikipedia extends TVShowScraper
{

	protected function runScraperTVShow($scraper, $showOnlyNew = false, $saveResults = false)
	{
		$m = array();
		$wiki = new MediaWikiAPI("http://it.wikipedia.org/w/api.php");
		$wiki->setLogger($this->logger);
		if (preg_match('/\/([^\/]+)$/', $scraper['uri'], $m)) {

			$res = array();

			$pageTitle = $m[1];
			$pageId = $wiki->getPageIdByTitle($pageTitle);
			$links = $wiki->getLinksByPageId($pageId);


			foreach ($links as $l) {
				$matches = array();
				if (preg_match('/episodi.d[ie].*[^a-z]([a-z]+).stagione/i', $l, $matches)) {
					$n = '';
					switch (strtolower($matches[1])) {
						case 'prima':
							$n = 1;
							break;
						case 'seconda':
							$n = 2;
							break;
						case 'terza':
							$n = 3;
							break;
						case 'quarta':
							$n = 4;
							break;
						case 'quinta':
							$n = 5;
							break;
						case 'sesta':
							$n = 6;
							break;
						case 'settima':
							$n = 7;
							break;
						case 'ottava':
							$n = 8;
							break;
						case 'nona':
							$n = 9;
							break;
						case 'decima':
							$n = 10;
							break;
						case 'undicesima':
							$n = 11;
							break;
						case 'dodicesima':
							$n = 12;
							break;
						case 'tredicesima':
							$n = 13;
							break;
						case 'quattordicesima':
							$n = 14;
							break;
						case 'quindicesima':
							$n = 15;
							break;
						case 'sedicesima':
							$n = 16;
							break;
						case 'diciassettesima':
							$n = 17;
							break;
						case 'diciottesima':
							$n = 18;
							break;
						case 'diciannovesima':
							$n = 19;
							break;
						case 'ventesima':
							$n = 20;
							break;
						default:
							$n = -1;
					}
					$title = $l;
					$uri = "http://it.wikipedia.org/wiki/" . rawurlencode($l);
					$this->log("Found season $n: $uri");

					$res[] = array(
						'n'		=> $n,
						'uri'	=> $uri
					);
				}
			}
			return $this->submitSeasonCandidates($scraper, $res, $showOnlyNew, $saveResults);
		} else {
			$this->error("Invalid URI");
			return FALSE;
		}
	}

	protected function runScraperSeason($scraper, $showOnlyNew = false, $saveResults = false)
	{
		$m = array();
		$wiki = new MediaWikiAPI("http://it.wikipedia.org/w/api.php");
		$wiki->setLogger($this->logger);

		if (preg_match('/\/([^\/]+)$/', $scraper['uri'], $m)) {
			$seasonData = $this->tvdb->getSeason($scraper['season']);
			$pageTitle = $m[1];
			$pageId = $wiki->getPageIdByTitle($pageTitle);

			$content = $wiki->getContentByPageId($pageId);
			$res = array();

			$tables = array();
			#if (preg_match_all('/\{\|[^\}]+\|\}/', $content, $tables)) {
			if (preg_match_all('/\{\|.*?\|\}/s', $content, $tables)) {
				$this->log("Found " . sizeof($tables[0]) . " tables");
				foreach ($tables[0] as $t) {

					$headerStart = strpos($t, "\n!");
					if ($headerStart === FALSE) {
						$this->log("Table has no header. Skipping...");
						continue;
					}

					$headerStop = strpos($t, "\n|-", $headerStart);
					if ($headerStop === FALSE) {
						$this->log("Table has no rows. Skipping...");
						continue;
					}

					$header = substr($t, $headerStart + 2, $headerStop - $headerStart - 2);
					$this->log("Table header $header");

					$hcols = array();
					$nIndex = FALSE;
					$airIndex = FALSE;

					$hlines = explode("\n!", $header);
					foreach ($hlines as $h) {
						$subh = explode("!!", $h);
						foreach ($subh as $hh) {
							$pipeIndex = strpos($hh, '|');
							if ($pipeIndex != FALSE) $hh = substr($hh, $pipeIndex + 1);

							$this->log("Column " . sizeof($hcols) . " title: $hh");
							$hcols[] = $hh;
							if (preg_match('/n[\.º°]/i', $hh)) {
								$nIndex = sizeof($hcols) - 1;
								$this->log("n index = $nIndex");
							} else if (preg_match('/prima\s+tv\s+italia/i', $hh) || preg_match('/pubblicazione\s+italia/i', $hh)) {
								$airIndex = sizeof($hcols) - 1;
								$this->log("air index = $airIndex");
							}
						}
					}

					if ($nIndex === FALSE || $airIndex === FALSE) {
						$this->log("Not a TV Show table. Skipping...");
						continue;
					}

					$body = substr($t, $headerStop + 3, strlen($t) - $headerStop - 5);
					$rows = explode("\n|-", $body);
					$table = array();

					for ($i = 0; $i < sizeof($rows); $i++) {
						#$this->log("Checking row $i");
						if (!isset($table[$i])) $table[$i] = array();
						$j = 0;
						$r = trim($rows[$i], " \t\n\r\0\x0B|");
						$rcols = explode("\n|", $r);
						foreach ($rcols as $subcol) {
							$col = explode("||", $subcol);
							foreach ($col as $c) {
								#$this->log("Checking cell $c");
								while (isset($table[$i][$j])) {
									#$this->log("Skipping column $j");
									$j++;
								}
								#$this->log("Column index is $j");
								$colspan = 1;
								$rowspan = 1;
								$m = array();
								if (preg_match("/rowspan\s*=\s*\"?\s?(\d+)/", $c, $m)) {
									$rowspan = $m[sizeof($m) - 1];
									$this->log("Cell has rowspan $rowspan");
								}
								if (preg_match("/colspan\s*=\s*\"?\s?(\d+)/", $c, $m)) {
									$colspan = $m[sizeof($m) - 1];
									$this->log("Cell has colspan $colspan");
								}
								#$this->log("c before cleanup: $c");
								$c = preg_replace("/rowspan\s*=\s*\"?\s?\d+/", "", $c);
								$c = preg_replace("/oolspan\s*=\s*\"?\s?\d+/", "", $c);
								#$this->log("c after cleanup: $c");
								for ($cs = 0; $cs < $colspan; $cs++) {
									for ($rs = 0; $rs < $rowspan; $rs++) {
										if (!isset($table[$i + $rs])) $table[$i + $rs] = array();
										#$this->log("Setting value to " . ($i + $rs) . "," . ($j + $cs));
										$table[$i + $rs][$j + $cs] = $c;
									}
								}
								$j += $colspan;
							}
						}
					}

					for ($i = 0; $i < sizeof($table); $i++) {
						$ep = $table[$i][$nIndex];
						$strDate = $table[$i][$airIndex];
						$date = null;

						$this->log("ep $ep - strDate $strDate");

						$ep = trim(preg_replace('/<ref[^>]*>.*<\/ref>/i', '', $ep));
						if (!preg_match('/^\d+$/', $ep)) {
							#$this->log("Not a valid episode index $ep, skipping row...");
							continue;
						}

						$strDate = trim(preg_replace('/<ref[^>]*>.*<\/ref>/i', '', $strDate));
						$strDate = preg_replace('/[^a-z0-9 ]+/i', '', $strDate);
						$strDate = preg_replace('/\s+/', ' ', $strDate);

						$this->log("Episode: $ep, prima TV Italia: $strDate");

						$retVal = !$showOnlyNew;

						$episodeDB = $this->tvdb->getEpisodeFromIndex($seasonData['tvshow'], $seasonData['n'], $ep);
						$this->log("episodeDB: " . var_export($episodeDB, TRUE));
						if ($episodeDB === FALSE) {
							if ($saveResults) {
								$this->log("Creating episode $ep");
								$episodeDB = $this->tvdb->addEpisode($seasonData['id'], array('n' => $ep));
								$retVal = TRUE;
							}
							if ($showOnlyNew) {
								$retVal = TRUE;
							}
						}
						if ($episodeDB && $strDate != FALSE && strlen($strDate) > 0) {
							$dateItems = array();
							if (preg_match('/(\d+)\s+([A-Za-z]+)\s?(\d\d\d\d)\b/', $strDate, $dateItems)) {
								switch (substr(strtolower($dateItems[2]), 0, 3)) {
									case 'gen':
										$dateItems[2] = 1;
										break;
									case 'feb':
										$dateItems[2] = 2;
										break;
									case 'mar':
										$dateItems[2] = 3;
										break;
									case 'apr':
										$dateItems[2] = 4;
										break;
									case 'mag':
										$dateItems[2] = 5;
										break;
									case 'giu':
										$dateItems[2] = 6;
										break;
									case 'lug':
										$dateItems[2] = 7;
										break;
									case 'ago':
										$dateItems[2] = 8;
										break;
									case 'set':
										$dateItems[2] = 9;
										break;
									case 'ott':
										$dateItems[2] = 10;
										break;
									case 'nov':
										$dateItems[2] = 11;
										break;
									case 'dic':
										$dateItems[2] = 12;
										break;
								}
								$date = strtotime("$dateItems[3]-$dateItems[2]-$dateItems[1]");

								$this->log("airDate ($dateItems[3]-$dateItems[2]-$dateItems[1]) in seconds: $date");

								if (!isset($episodeDB['airDate']) || $episodeDB['airDate'] != $date) {
									if ($saveResults) {
										$this->tvdb->setEpisode($episodeDB['id'], array('airDate' => $date));
										$retVal = TRUE;
									}
								}
							}
						}
						if ($retVal) {
							$res[] = array('n' => $ep, 'airDate' => $date);
						}
					}
				}
			}
			return $res;
		} else {
			return $this->error("Invalid URI");
		}
	}
}
