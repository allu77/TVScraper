var TVShow = {
	showOnlyPendingScapedSeasons:false
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

	if (showObj.lastEpisodeIndex != undefined) {
		showElem.find('.episodeCounterContainter').removeClass('hidden');
		showElem.find('.episodesWithFile').text(showObj.episodesWithFile);
		showElem.find('.airedEpisodesCount').text(showObj.airedEpisodesCount);
		showElem.find('.lastEpisodeIndex').text(showObj.lastEpisodeIndex);
	} else {
		showElem.find('.episodeCounterContainter').addClass('hidden');
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

	if (showObj.pendingScrapedSeasons != undefined && showObj.pendingScrapedSeasons == '1') {
		showElem.addClass('pendingScrapedSeasons');
	} else {
		showElem.removeClass('pendingScrapedSeasons');
	}
}

TVShow.add = function(showObj) {
	var newTVShowElement = $('#guiSkeleton .show').clone(true);
	TVShow.set(showObj, newTVShowElement);
	$('#showList').append(newTVShowElement);
	tvShowSort();
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
	$('.show').each(function() {
		if (! TVShow.showOnlyPendingScapedSeasons || $(this).hasClass('pendingScrapedSeasons')) {
			$(this).show();
		} else {
			$(this).hide();
		}
	});
}


$(".createTVShow").click(function(e) { TVShow.create(e); });
$(".editTVShow").click(function(e) { TVShow.edit(e, $(this).closest('.show')); });
$("#togglePendingScrapedSeasons").click(function(e) { 
		e.preventDefault(); 
		TVShow.showOnlyPendingScapedSeasons = ! TVShow.showOnlyPendingScapedSeasons;
		TVShow.filter();
});
