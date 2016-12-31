<?php

function parseED2KURI($uri) {
	$m = Array();
	if (preg_match('/^ed2k:\/\/\|file\|([^\|]+)\|(\d+)\|([^\|]+)\|([^\|]*\|)*\/(.*\/)?$/', $uri, $m)) {
		return Array(
			'fileName'	=> $m[1],
			'fileSize'	=> $m[2],
			'hash'		=> $m[3]
		);
	} else {
		return false;
	}
}

function parseEpisodeFileName($fileName) {
	$fileName = preg_replace('/%20/', ' ', $fileName);
	$m = Array();
	if (preg_match('/\b(\d+)x(\d+)\b/i', $fileName, $m) || preg_match('/\bs(\d+)[\.\s]?e(\d+)\b/i', $fileName, $m)) {
		$m[1] = preg_replace('/^0+/', '', $m[1]);
		$m[2] = preg_replace('/^0+/', '', $m[2]);
		return Array('season' => $m[1], 'episode' => $m[2]);
	} else {
		return false;
	}
}

function checkResolution($requiredRes, $candidateRes) {
	if ($requiredRes == 'any') return true;

	$res = 0;
	if (preg_match('/720p/', $candidateRes)) $res = 1;
	else if (preg_match('/1080i/', $candidateRes)) $res = 2;
	else if (preg_match('/1080p/', $candidateRes)) $res = 3;

	switch ($requiredRes) {
		case 'sd': return ($res == 0);
		case '720p': return ($res == 1);
		case '720p+': return ($res > 0);
		case '1080i': return ($res == 2);
		case '1080i+': return ($res > 1);
		case '1080p': return ($res == 3);
		default: return false;
	}
}

?>
