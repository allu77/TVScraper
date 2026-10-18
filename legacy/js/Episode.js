var Episode = {
};

Episode.removeBest = function(e, episodeElement) {
	e.preventDefault(); 
	fileId = episodeElement.find('.bestFileId').text();
	if (fileId.length > 0) {
		runAPI({
			action:"removeFile",
			fileId:fileId
		}, function(data) {
			refreshEpisodeList(episodeElement.closest('.season'));
		});

	}
}

Episode.discardBest = function(e, episodeElement) {
	e.preventDefault(); 
	fileId = episodeElement.find('.bestFileId').text();
	if (fileId.length > 0) {
		runAPI({
			action:"setFile",
			fileId:fileId,
			discard:"1"
		}, function(data) {
			refreshEpisodeList(episodeElement.closest('.season'));
		});

	}
}

Episode.lockBest = function(e, episodeElement, lock) {
	e.preventDefault(); 
	fileId = episodeElement.find('.bestFileId').text();
	episodeId = episodeElement.attr('id').substr(7);
	if (fileId.length > 0) {
		runAPI({
			action:"setEpisode",
			episodeId:episodeId,
			bestSticky:(lock ? '1' : '_REMOVE_')
		}, function(data) {
			refreshEpisodeList(episodeElement.closest('.season'));
		});

	}
}

$(".removeFile").click(function(e) { Episode.removeBest(e, $(this).closest('.episode')); });
$(".lockFile").click(function(e) { Episode.lockBest(e, $(this).closest('.episode'), true); });
$(".unlockFile").click(function(e) { Episode.lockBest(e, $(this).closest('.episode'), false); });
$(".discardFile").click(function(e) { Episode.discardBest(e, $(this).closest('.episode')); });
