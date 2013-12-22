var SeasonScraper = {
};

SeasonScraper.showEditDialog = function(onClick, seasonScraperObj) {
	if (SeasonScraper.dialog == undefined) {
		SeasonScraper.dialog = $("#seasonScraperEditDialog").first();
	}

	var isNewDialog = (seasonScraperObj == undefined);
	SeasonScraper.dialog.find('#editSeasonScraperPreference').val(isNewDialog ? "" : seasonScraperObj.preference);
	SeasonScraper.dialog.find('#editSeasonScraperURI').val(isNewDialog ? "" : seasonScraperObj.uri);
	SeasonScraper.dialog.find('#editSeasonScraperDelay').val(isNewDialog ? "" : seasonScraperObj.delay);
	SeasonScraper.dialog.find('#editSeasonScraperSource option').each(function() { this.selected = (this.value == (isNewDialog ? 'DDU' : seasonScraperObj.source)); });
	
	SeasonScraper.dialog.find('.submitButton').unbind('click');
	if (onClick != undefined) {
		SeasonScraper.dialog.find('.submitButton').click(onClick);
	}
	SeasonScraper.dialog.modal('show');
};

SeasonScraper.create = function(seasonElem, e) {
	e.preventDefault();
	SeasonScraper.showEditDialog(function(e) {
		seasonObj = Season.parse(seasonElem);

		runAPI({
			action:"addScraper",
			rootId:seasonObj.id,
			preference:SeasonScraper.dialog.find('#editSeasonScraperPreference').val(),
			delay:SeasonScraper.dialog.find('#editSeasonScraperDelay').val(),
			uri:SeasonScraper.dialog.find('#editSeasonScraperURI').val(),
			source:SeasonScraper.dialog.find('#editSeasonScraperSource').find(':selected').first().val()
		}, function(data) {
			SeasonScraper.add(data.result);
			SeasonScraper.sort(seasonElem);
			SeasonScraper.dialog.modal('hide');
		});
	});
};

SeasonScraper.add = function(seasonScraperObj) {
	var newSeasonScraperElem = $('#guiSkeleton .seasonScraper').clone(true);
	SeasonScraper.set(seasonScraperObj, newSeasonScraperElem);
	$('#season' + seasonScraperObj.season + ' .scraperList').append(newSeasonScraperElem);
}

SeasonScraper.set = function(seasonScraperObj, seasonScraperElem) {
	if (seasonScraperObj.id != undefined) seasonScraperElem.attr('id', 'scraper' + seasonScraperObj.id);
	seasonScraperElem.find('.scraperPreference').text(seasonScraperObj.preference != undefined ? seasonScraperObj.preference : "");
	if (seasonScraperObj.source != undefined) seasonScraperElem.find('.scraperSource').text(seasonScraperObj.source);
	if (seasonScraperObj.n != undefined) seasonElem.find('.seasonN').text(seasonScraperObj.n);
	if (seasonScraperObj.delay != undefined) seasonScraperElem.find('.scraperDelay').text(seasonScraperObj.delay);
	if (seasonScraperObj.uri != undefined) {
		seasonScraperElem.find('.scraperUriText').text(seasonScraperObj.uri.trunc(100));
		seasonScraperElem.find('.scraperUri').attr('href', seasonScraperObj.uri);
	}
}

SeasonScraper.sort = function(seasonElem) {
	seasonElem.find('.scraperPreference').sortElements(function(a,b) { 
		var aTxt = $(a).text(), bTxt = $(b).text();
		if (aTxt == '' && bTxt == '') return 0;
		else if (aTxt == '') return -1;
		else if (bTxt == '') return 1;
		else return (aTxt - bTxt);
	}, function() { 
		return $(this).closest('.seasonScraper').get(0); 
	});
	seasonElem.find('.seasonScraper').each(function(index) {
		$(this).find('.scraperOrder').text('(' + (index + 1) + ')');
	});
}
/*
function seasonScraperSort(seasonId) {
	$('#season' + seasonId + ' .scraperPreference').sortElements(function(a,b) { 
		var aTxt = $(a).text(), bTxt = $(b).text();
		if (aTxt == '' && bTxt == '') return 0;
		else if (aTxt == '') return -1;
		else if (bTxt == '') return 1;
		else return (aTxt - bTxt);
	}, function() { 
		return $(this).closest('.seasonScraper').get(0); 
	});
	$('#season' + seasonId + ' .seasonScraper').each(function(index) {
		$(this).find('.scraperOrder').text('(' + (index + 1) + ')');
	});
}

*/


/*

Season.set = function(showObj, showElem) {
	if (showObj.id != undefined) showElem.attr('id', 'show' + showObj.id);
	if (showObj.title != undefined) showElem.find('.showTitle').text(showObj.title);
	if (showObj.lastAirDate != undefined) {
		showElem.find('.lastAirDate').text(showObj.lastAirDate);
		var d = new Date(Number(showObj.lastAirDate * 1000));
		showElem.find('.lastAirDateStr').text(d.getDate() + '/' + (d.getMonth() + 1) + "/" + d.getFullYear());
		showElem.find('.lastAirDateContainer').removeClass('hidden');
	} else {
		showElem.find('.lastAirDateContainer').addClass('hidden');
	}
	if (showObj.nextAirDate != undefined) {
		showElem.find('.nextAirDate').text(showObj.nextAirDate);
		var d = new Date(Number(showObj.nextAirDate * 1000));
		showElem.find('.nextAirDateStr').text(d.getDate() + '/' + (d.getMonth() + 1) + "/" + d.getFullYear());
		showElem.find('.nextAirDateContainer').removeClass('hidden');
	} else {
		showElem.find('.nextAirDateContainer').addClass('hidden');
	}

	if (showObj.lastPubDate != undefined) {
		showElem.find('.lastPubDate').text(showObj.lastPubDate);
		var d = new Date(Number(showObj.lastPubDate * 1000));
		showElem.find('.lastPubDateStr').text(d.getDate() + '/' + (d.getMonth() + 1) + "/" + d.getFullYear());
		showElem.find('.lastPubDateContainer').removeClass('hidden');
	} else {
		showElem.find('.lastPubDateContainer').addClass('hidden');
	}
	if (showObj.alternateTitle != undefined) {
		showElem.find('.alternateTitle').text(showObj.alternateTitle);
	}
	if (showObj.alternateTitle != undefined && showObj.alternateTitle != "") {
		showElem.find('.alternateTitleContainer').removeClass('hidden');
	} else {
		showElem.find('.alternateTitleContainer').addClass('hidden');
	}
	if (showObj.lang != undefined) {
		showElem.find('.language').text(showObj.lang);
		showElem.find('.languageContainer img').attr('class', 'flag flag-' + showObj.lang);
	}
	if (showObj.nativeLang != undefined) {
		showElem.find('.nativeLanguage').text(showObj.nativeLang);
		showElem.find('.nativeLanguageContainer img').attr('class', 'flag flag-' + showObj.nativeLang);
	}
	if (showObj.res != undefined) {
		showElem.find('.resolution').text(showObj.res);
	}
	if (showObj.res != undefined && showObj.res != "any") {
		showElem.find('.resolutionContainer').removeClass('hidden');
	} else {
		showElem.find('.resolutionContainer').addClass('hidden');
	}

	var warningMsg;
	var warningClass = "label-default";


	if (showObj.pendingScrapedSeasons != undefined && showObj.pendingScrapedSeasons == '1') {
		warningMsg = "New season scraper found!";
		warningClass = "label-success";
	}
	if (showObj.lastEpisodeIndex != undefined) {
		showElem.find('.episodeCounterContainer').removeClass('hidden');
		showElem.find('.episodesWithFile').text(showObj.episodesWithFile);
		showElem.find('.airedEpisodesCount').text(showObj.airedEpisodesCount);
		showElem.find('.lastEpisodeIndex').text(showObj.lastEpisodeIndex);
		if (!warningMsg) {
			if (showObj.episodesWithFile < showObj.airedEpisodesCount) {
				if (showObj.episodesWithFile == showObj.airedEpisodesCount - 1 && showObj.lastAiredEpisodeIndex  == showObj.latestMissingIndex) {
					warningMsg = "File not found yet for latest episode";
				} else {
					warningMsg = "File not found yet for " + (showObj.airedEpisodesCount - showObj.episodesWithFile) + " episode(s)";
					warningClass = "label-danger";
				}
			} else if (showObj.episodesWithFile == showObj.airedEpisodesCount && showObj.airedEpisodesCount == showObj.lastEpisodeIndex) {
				warningMsg = "Season could be set to complete";
				warningClass = "label-success";

			} else if (showObj.airedEpisodesCount == 0 && showObj.nextAirDate != undefined) {
				warningMsg = "New season is coming!";
				warningClass = "label-success";
			} else if (showObj.lastAirDate != undefined && showObj.nextAirDate != undefined && showObj.nextAirDate - showObj.lastAirDate > 612000) { // 604800 (one week) + 7200 (two hours) to handle DST changes
				warningMsg = "Show is returning after hiatus";
				warningClass = "label-success";
			}

		}
	} else {
		showElem.find('.episodeCounterContainer').addClass('hidden');
	}

	if (warningMsg) {
		showElem.find('.warningContainer').removeClass('hidden');
		showElem.find('.warningMsg').text(warningMsg);
	} else {
		showElem.find('.warningContainer').addClass('hidden');
	}
	//Out of if block to reset good/bad class when no warning are shown
	showElem.find('.warningMsg').attr('class', 'warningMsg label ' + warningClass);

}

Season.add = function(showObj) {
	var newTVShowElement = $('#guiSkeleton .show').clone(true);
	TVShow.set(showObj, newTVShowElement);
	$('#showList').append(newTVShowElement);
	TVShow.sort();
}


Season.create = function(e) {
	e.preventDefault();
	TVShow.showEditDialog(function(e) {
		runAPI({
			action:"addTVShow",
			title:TVShow.dialog.find('#editTVShowTitle').val(),
			alternateTitle:TVShow.dialog.find('#editTVShowAlternateTitle').val(),
			lang:TVShow.dialog.find('#editTVShowLanguage').find(':selected').first().val(),
			nativeLang:TVShow.dialog.find('#editTVShowNativeLanguage').find(':selected').first().val(),
			res:TVShow.dialog.find('#editTVShowResolution').find(':selected').first().val()
		}, function(data) {
			TVShow.add(data.result);
			TVShow.sort();
			TVShow.dialog.modal('hide');
		});
	});
};

Season.parse = function(showElement) {
	return {
		id:showElement.attr('id').substr(4),
		title:showElement.find('.showTitle').text(),
		alternateTitle:showElement.find('.alternateTitle').text(),
		lang:showElement.find('.language').text(),
		nativeLang:showElement.find('.nativeLanguage').text(),
		res:showElement.find('.resolution').text()
	};
}

Season.edit = function(e, showElement) {
	e.preventDefault();
	showObj = TVShow.parse(showElement);
	TVShow.showEditDialog(function(e) {
		runAPI({
			action:"setTVShow",
			showId:showObj.id,
			title:TVShow.dialog.find('#editTVShowTitle').val(),
			alternateTitle:TVShow.dialog.find('#editTVShowAlternateTitle').val(),
			lang:TVShow.dialog.find('#editTVShowLanguage').find(':selected').first().val(),
			nativeLang:TVShow.dialog.find('#editTVShowNativeLanguage').find(':selected').first().val(),
			res:TVShow.dialog.find('#editTVShowResolution').find(':selected').first().val()
		}, function(data) {
			TVShow.set(data.result, showElement);
			TVShow.sort();
			TVShow.dialog.modal('hide');
		});
	}, showObj);
}

TVShow.sort = function() {
	var sorter;
	switch (showSortBy) {
		case 'lastPubDate':
			sorter = function(a,b) { return Number($(b).find('.lastPubDate').text()) - Number($(a).find('.lastPubDate').text()); };
			break;
		case 'lastAirDate':
			sorter = function(a,b) { return Number($(b).find('.lastAirDate').text()) - Number($(a).find('.lastAirDate').text()); };
			break;
		case 'nextAirDate':
			sorter = function(a,b) { 
				var textA = $(a).find('.nextAirDate').text();
				var textB = $(b).find('.nextAirDate').text();

				if (textA == "" && textB == "") { return 0; }
				else if (textA == "") { return 1; }
				else if (textB == "") { return -1; }
				else { return Number(textA) - Number(textB) };
			}
			break;
		case 'title':
			sorter = function(a,b) { return $(a).find('.showTitle').text().localeCompare($(b).find('.showTitle').text()); };
			break;
	}
	
	$('#showList .show').sortElements(sorter, function() { return $(this).get(0); });
}

TVShow.filter = function() {
	$('.show').each(function() {

		if ((TVShow.showOnlyGoodNews && !$(this).find('.warningMsg').hasClass('label-success')) ||
		     TVShow.showOnlyBadNews && !$(this).find('.warningMsg').hasClass('label-danger')) {
			$(this).addClass('hidden');
		} else {
			$(this).removeClass('hidden');
		}
	});
}


$(".editTVShow").click(function(e) { TVShow.edit(e, $(this).closest('.show')); });
$(".toggleTVShow").click(function() { toggleTVShow($(this).closest('.show'));});

$("#toggleGoodNews").click(function(e) { e.preventDefault(); TVShow.showOnlyGoodNews = ! TVShow.showOnlyGoodNews; TVShow.filter(); });
$("#toggleBadNews").click(function(e) { e.preventDefault(); TVShow.showOnlyBadNews = ! TVShow.showOnlyBadNews; TVShow.filter(); });

*/

$(".createSeasonScraper").click(function(e) { SeasonScraper.create($(this).closest('.season'), e); });
