var TVShow = {
	showOnlyGoodNews:false,
	showOnlyBadNews:false
};

TVShow.set = function(showObj, showElem) {
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

TVShow.add = function(showObj) {
	var newTVShowElement = $('#guiSkeleton .show').clone(true);
	TVShow.set(showObj, newTVShowElement);
	$('#showList').append(newTVShowElement);
	TVShow.sort();
}

TVShow.showEditDialog = function(onClick, showObj ) {
	if (TVShow.dialog == undefined) {
		TVShow.dialog = $("#showEditDialog").first();
	}

	var isNewDialog = (showObj == undefined);
	TVShow.dialog.find('#editTVShowTitle').val(isNewDialog ? "" : showObj.title);
	TVShow.dialog.find('#editTVShowAlternateTitle').val(isNewDialog ? "" : showObj.alternateTitle);
	TVShow.dialog.find('#editTVShowLanguage option').each(function() { this.selected = (this.value == (isNewDialog ? 'ita' : showObj.lang)); });
	TVShow.dialog.find('#editTVShowNativeLanguage option').each(function() { this.selected = (this.value == (isNewDialog ? 'eng' : showObj.nativeLang)); });
	TVShow.dialog.find('#editTVShowResolution option').each(function() { this.selected = (this.value == (isNewDialog ? 'any' : showObj.res)); });

	
	TVShow.dialog.find('.submitButton').unbind('click');
	if (onClick != undefined) {
		TVShow.dialog.find('.submitButton').click(onClick);
	}
	TVShow.dialog.modal('show');
};

TVShow.create = function(e) {
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

TVShow.parse = function(showElement) {
	return {
		id:showElement.attr('id').substr(4),
		title:showElement.find('.showTitle').text(),
		alternateTitle:showElement.find('.alternateTitle').text(),
		lang:showElement.find('.language').text(),
		nativeLang:showElement.find('.nativeLanguage').text(),
		res:showElement.find('.resolution').text()
	};
}

TVShow.edit = function(e, showElement) {
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

	if (TVShow.showOnlyGoodNews) {
		$("#toggleGoodNews").closest('li').find('.glyphicon').addClass('glyphicon-ok');
	} else {
		$("#toggleGoodNews").closest('li').find('.glyphicon').removeClass('glyphicon-ok');
	}
	if (TVShow.showOnlyBadNews) {
		$("#toggleBadNews").closest('li').find('.glyphicon').addClass('glyphicon-ok');
	} else {
		$("#toggleBadNews").closest('li').find('.glyphicon').removeClass('glyphicon-ok');
	}

	$('.show').each(function() {

		if ((TVShow.showOnlyGoodNews && !$(this).find('.warningMsg').hasClass('label-success')) ||
		     TVShow.showOnlyBadNews && !$(this).find('.warningMsg').hasClass('label-danger')) {
			$(this).addClass('hidden');
		} else {
			$(this).removeClass('hidden');
		}
	});
}

TVShow.del = function(e, showElem) {
	e.preventDefault();
	showObj = TVShow.parse(showElem);
	confirmDialog("WARNING! You are going to completely remove TV Show '" + showObj.title + "'! This cannot be undone. Are you sure you want to go on?", function() { TVShow.remove(showElem); });
}

TVShow.remove = function(showElem) {
	showObj = TVShow.parse(showElem);
	runAPI(
		{ 
		action:"removeTVShow", 
		showId:showObj.id
		}, 
		function(data) {
			showElem.remove();
			confDialog.modal('hide');
		}
	);
}


TVShow.getAll = function () {
	runAPI(
		{ action:"getAllTVShows" },
		function(data) {
			for (var i = 0; i < data.result.length; i++) {
				TVShow.add(data.result[i]);
			}
		}
	);
}

TVShow.refresh = function(showEelement) {
	showObj = TVShow.parse(showElement);
	runAPI(
	{
		action:"getTVShow",
		showId:showObj.id
	},
	function(data) {
		TVShow.set(data.result, showEelement);
		TVShow.sort();
	});
}

TVShow.toggle = function (tvShowElem) {
	var toggleButton = tvShowElem.find('.toggleTVShow .glyphicon');
	var showObj = TVShow.parse(tvShowElem);
	if (toggleButton.hasClass('glyphicon-collapse-down')) {
		toggleButton.removeClass('glyphicon-collapse-down');
		toggleButton.addClass('glyphicon-collapse-up');
	} else {
		toggleButton.removeClass('glyphicon-collapse-up');
		toggleButton.addClass('glyphicon-collapse-down');
	}
	tvShowElem.find('.showContent').slideToggle();
	if (tvShowElem.find('.seasons').hasClass('notFetched')) {
		loadTVShowSeasons(showObj.id);
	}
	if (tvShowElem.find('.showScrapers .scraperList').hasClass('notFetched')) {
		loadTVShowScrapers(showObj.id);
	}
	if (tvShowElem.find('.showScrapers .scrapedSeasonsList').hasClass('notFetched')) {
		loadScrapedSeasons(showObj.id);
	}
}

$(".createTVShow").click(function(e) { TVShow.create(e); });
$(".editTVShow").click(function(e) { TVShow.edit(e, $(this).closest('.show')); });
$(".show .toggleTVShow").click(function(e) { e.preventDefault(); TVShow.toggle($(this).closest('.show'));});
$('.show .showTitle').click(function() { TVShow.toggle($(this).closest('.show'));});
$('.removeShow').click(function(e) { TVShow.del(e, $(this).closest('.show')); });

$("#toggleGoodNews").click(function(e) { 
	e.preventDefault(); 
	TVShow.showOnlyGoodNews = ! TVShow.showOnlyGoodNews; 
	if (TVShow.showOnlyGoodNews) {
		TVShow.showOnlyBadNews = false;
	}
	TVShow.filter(); 
});
$("#toggleBadNews").click(function(e) { 
	e.preventDefault(); 
	TVShow.showOnlyBadNews = ! TVShow.showOnlyBadNews; 
	if (TVShow.showOnlyBadNews) {
		TVShow.showOnlyGoodNews = false;
	}
	TVShow.filter(); 
});

$(document).ready(function() { TVShow.getAll(); } );
