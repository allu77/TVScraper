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
	$m = Array();
	if (preg_match('/\b(\d+)x(\d+)\b/i', $fileName, $m) || preg_match('/\bs(\d+)e(\d+)\b/i', $fileName, $m)) {
		$m[1] = preg_replace('/^0+/', '', $m[1]);
		$m[2] = preg_replace('/^0+/', '', $m[2]);
		return Array('season' => $m[1], 'episode' => $m[2]);
	} else {
		return false;
	}
}

?>
