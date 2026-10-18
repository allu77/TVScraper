<?php
// Usage: php ddu-scrape.php <legacyDir> <fixtureDir> <workDir>
//
// Runs the legacy DDU code offline against a captured fixture set and prints JSON:
//   - parser output for each page (topic list + sanity warnings, or ed2k links)
//   - TVShowScraperDDU::runScraper in "all" mode (showOnlyNew=false, saveResults=false)
//   - TVShowScraperDDU::runScraper in "new" mode (showOnlyNew=true,  saveResults=true) with the resulting DB
//   - the same two runs with the PHP 7 equivalent of the scrapers (see loadPatchedClass)
//   - the PHP warnings/notices raised in each step
//
// Pages are served from the legacy file cache, pre-filled from the fixtures. The caller must make
// any real network access fail (e.g. via an unreachable proxy), so a missing fixture page is an error.
//
// fixtureDir/manifest.json:
// {
//   "scraper": { "type": "tvShow" | "season", "uri": "...", "autoAdd": 0, "notify": 0 },
//   "show":    { "title": "...", "alternateTitle": "...", "lang": "it", "nativeLang": "en", "res": "any" },
//   "seasons": [ { "n": 1, "status": "complete" } ],      // optional; for a season scraper the first one is its season
//   "pages":   [ { "url": "...", "file": "board-0.html", "kind": "board" | "topic" } ]
// }

[, $legacyDir, $fixtureDir, $workDir] = $argv;

const FIXTURE_USER = 'fixture-user';
const FIXTURE_PASSWORD = 'fixture-password';

// Errors that would stop legacy execution become exceptions; warnings and notices are recorded and execution continues.
$warnings = [];
set_error_handler(function ($severity, $message, $file, $line) use (&$warnings) {
	if ($severity & (E_USER_ERROR | E_RECOVERABLE_ERROR)) throw new ErrorException($message, 0, $severity, $file, $line);
	if (!($severity & (E_DEPRECATED | E_USER_DEPRECATED))) $warnings[] = basename($file) . ": $message"; // utf8_encode() is deprecated on PHP >= 8.2
	return true;
});

chdir($legacyDir);
require_once('TVShowScraperDBSQLite.php');
require_once('TVShowScraperDDU.php');

$manifest = json_decode(file_get_contents("$fixtureDir/manifest.json"), true, flags: JSON_THROW_ON_ERROR);

SimpleBrowser::initCache(true, 100 * 365 * 24 * 3600, $workDir);
SimpleBrowser::initCookies(true, $workDir);
SimpleBrowser::initTimeout(5);

// PHP 7 equivalent of the legacy scrapers. On PHP >= 8, sizeof() on a non-countable throws a TypeError, while PHP 7
// returned 1 with a warning. Two such calls break DDU scraping on PHP 8:
//   TVShowScraper::submitSeasonCandidates   sizeof($link['lang'] > 0)   (misplaced parenthesis, show scrapers)
//   TVShowScraperDDU::runScraperSeason      sizeof($page)               (string, season scrapers)
// The classes are built from the current legacy source with only those calls replaced by their PHP 7 value (1).
function loadPatchedClass($file, $replacements) {
	$src = file_get_contents($file);
	foreach ($replacements as $from => $to) {
		if (substr_count($src, $from) !== 1) throw new RuntimeException("Patch target not found exactly once in $file: $from");
		$src = str_replace($from, $to, $src);
	}
	eval('?>' . $src);
}
loadPatchedClass('TVShowScraper.php', [
	'abstract class TVShowScraper {' => 'abstract class Php7TVShowScraper {',
	"sizeof(\$link['lang'] > 0)" => '(1)',
]);
loadPatchedClass('TVShowScraperDDU.php', [
	'class TVShowScraperDDU extends TVShowScraper {' => 'class Php7TVShowScraperDDU extends Php7TVShowScraper {',
	'sizeof($page)' => '(1)',
]);

function fillCache($workDir, $manifest, $fixtureDir) {
	// Same naming as SimpleBrowser::getCacheFileName + DDUBrowser::getCacheFileNameOnly
	foreach ($manifest['pages'] as $page) {
		copy("$fixtureDir/{$page['file']}", "$workDir/SimpleBrowser_" . md5(FIXTURE_USER) . '_' . md5($page['url']));
	}
}

function buildDb($workDir, $manifest, $name) {
	$file = "$workDir/$name.db";
	$db = new TVShowScraperDBSQLite($file); // creates the schema from TVShowScraperDB.sql in cwd
	$show = $db->addTVShow($manifest['show']);
	$seasonIds = [];
	foreach ($manifest['seasons'] ?? [] as $s) {
		$seasonIds[] = $db->addSeason($show['id'], $s)['id'];
	}
	$s = $manifest['scraper'];
	$rootId = $s['type'] == 'season' ? $seasonIds[0] : $show['id'];
	$params = ['uri' => $s['uri'], 'source' => 'DDU', 'autoAdd' => $s['autoAdd'] ?? 0, 'notify' => $s['notify'] ?? 0];
	$scraper = $db->addScraper($rootId, $s['type'], $params);
	return [$db, $file, $scraper['id']];
}

function dumpDb($file) {
	$pdo = new PDO("sqlite:$file");
	$out = [];
	foreach (['seasons', 'episodes', 'files', 'scrapedSeasons', 'scrapers', 'seasonScrapers'] as $t) {
		$out[$t] = $pdo->query("SELECT * FROM $t ORDER BY 1, 2")->fetchAll(PDO::FETCH_ASSOC);
	}
	return $out;
}

function attempt(callable $fn) {
	global $warnings;
	$warnings = [];
	try {
		$out = ['ok' => $fn()];
	} catch (Throwable $e) {
		$out = ['error' => get_class($e) . ': ' . $e->getMessage()];
	}
	if ($warnings) $out['phpWarnings'] = $warnings;
	return $out;
}

$result = ['pages' => []];

foreach ($manifest['pages'] as $page) {
	$result['pages'][$page['file']] = attempt(function () use ($page, $fixtureDir) {
		$parser = new DDUParser();
		$parser->setPage(file_get_contents("$fixtureDir/{$page['file']}"));
		$parser->setBaseURL($page['url']);
		if ($page['kind'] == 'board') {
			return ['topics' => $parser->getTopicList(), 'warnings' => $parser->getSanityWarnings()];
		}
		return ['ed2kLinks' => $parser->getED2KLinksFromTopic(), 'warnings' => $parser->getSanityWarnings()];
	});
}

$runs = ['runAll' => [false, false], 'runNew' => [true, true]];
$classes = ['' => 'TVShowScraperDDU', 'Php7' => 'Php7TVShowScraperDDU'];

foreach ($classes as $suffix => $class) {
	foreach ($runs as $name => [$showOnlyNew, $saveResults]) {
		fillCache($workDir, $manifest, $fixtureDir);
		$result[$name . $suffix] = attempt(function () use ($workDir, $manifest, $class, $name, $suffix, $showOnlyNew, $saveResults) {
			[$db, $file, $scraperId] = buildDb($workDir, $manifest, $name . $suffix);
			$ddu = new $class($db, FIXTURE_USER, FIXTURE_PASSWORD);
			$res = $ddu->runScraper($scraperId, $showOnlyNew, $saveResults);
			return $saveResults ? ['result' => $res, 'db' => dumpDb($file)] : $res;
		});
	}
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
