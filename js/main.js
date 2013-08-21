var showProgress = false;

function runAPI(parameters, onSuccess, onKO, onFailure) {
	showProgress = true;
	var timeout = setTimeout(function() { progressDialog(true); }, 2000);
	$.post("api.php", parameters, function(data, timeout) {
		progressDialog(false, timeout);
		if (data.status == "ok") {
			onSuccess(data);
		} else {
			if (onKO != undefined) {
				onKO(data);
			} else {
				alert("ERROR: " + data.errmsg);
			}
		}
	}, 'json')
	.fail(function(timeout, jqXHR, textStatus, errorThrown) {
		progressDialog(false, timeout);
		if (onFailure != undefined) {
			onFailure();
		} else {
			alert("Unspecified error " + textStatus);
		}
	});
}

function progressDialog(show, timeout) {
	if (!show && timeout != undefined) {
		clearTimeout(timeout);
	}
	if (show && showProgress) {
		$('#progressDialog').modal('show');
	} else {
		showProgress = false;
		$('#progressDialog').modal('hide');
	}
}



/******************************************************
 TV Show 
 *****************************************************/

function tvShowSort() {
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
				else { return Number(textA) - Number(textB); };
			};
			break;
		case 'title':
			sorter = function(a,b) { return $(a).find('.showTitle').text().localeCompare($(b).find('.showTitle').text()); };
			break;
	}
	
	$('#showList .show').sortElements(sorter, function() { return $(this).get(0); });
}

function getAllTVShows() {
	runAPI(
		{ action:"getAllTVShows" },
		function(data) {
			for (var i = 0; i < data.result.length; i++) {
				TVShow.add(data.result[i]);
			}
		}
	);
}

function deleteTVShow(e, showElement) {
	e.preventDefault();
	var goOn = confirm("DELETE SHOW " + showElement.attr('id').substr(4) + "?");
	if (goOn) {
		runAPI(
			{ 
				action:"removeTVShow", 
				showId:showElement.attr('id').substr(4) 
			}, 
			function(data) {
				showElement.remove();
			}
		);
	}
}

function addNewShowSubmit(e) {
	e.preventDefault();
	var showTitle = $.trim($('#newShowTitle').val());
	runAPI(
		{ 
			action:"addTVShow", 
			title:showTitle 
		}, 
		function(data) {
			TVShow.add({
				id:data.result,
				title:showTitle
			});
		});
}

function toggleTVShow(tvShowElem) {
	tvShowElem.find('.showContent').slideToggle();
	if (tvShowElem.find('.seasons').hasClass('notFetched')) {
		loadTVShowSeasons(tvShowElem.attr('id').substr(4));
	}
	if (tvShowElem.find('.showScrapers .scraperList').hasClass('notFetched')) {
		loadTVShowScrapers(tvShowElem.attr('id').substr(4));
	}
	if (tvShowElem.find('.showScrapers .scrapedSeasonsList').hasClass('notFetched')) {
		loadScrapedSeasons(tvShowElem.attr('id').substr(4));
	}

}

function refreshTVShow(showEelement) {
	runAPI(
	{
		action:"getTVShow",
		showId:showEelement.attr('id').substr(4)
	},
	function(data) {
		TVShow.set(data.result, showEelement);
		tvShowSort();
	});
}



/******************************************************
 Season 
 *****************************************************/


function loadTVShowSeasons(showId) {
	$('#show' + showId + ' .seasons').removeClass('notFetched');

	runAPI(
		{
			action:"getTVShowSeasons",
			showId:showId
		},
		function(data) {
			for (var i = 0; i < data.result.length; i++) {
				addSeason(showId, data.result[i]);
			}
		}
		);
}

function addSeason(showId, seasonObj) {
	var newSeasonElem = $('#guiSkeleton .season').clone(true);
	setSeason(seasonObj, newSeasonElem);
	$('#show' + showId + ' .seasons').append(newSeasonElem);
	seasonSort(showId);
}

function setSeason(seasonObj, seasonElem) {
	if (seasonObj.id != undefined) seasonElem.attr('id', 'season' + seasonObj.id);
	if (seasonObj.n != undefined) seasonElem.find('.seasonN').text(seasonObj.n);
	if (seasonObj.status != undefined) seasonElem.find('.seasonStatus').text(seasonObj.status);
}

function seasonSort(showId) {
	$('#show' + showId + ' .seasonTitle').sortElements(function(a,b) { return $(a).text().localeCompare($(b).text()); }, function() { return $(this).closest('.season').get(0); });
}


function addNewSeasonSubmit(e, formElement) {
	e.preventDefault();
	var seasonN = formElement.children('input[name="newSeasonN"]').val();
	var seasonShowId = formElement.closest('.show').attr('id').substr(4);
	runAPI(
		{ 
			action:"addSeason", 
			showId:seasonShowId,
			n:seasonN,
			status:"watched"
		}, 
		function(data) {
			addSeason(seasonShowId, {
				id: data.result,
				n: seasonN,
				status: "watched"
			});

		});
}


function deleteSeason(e, seasonElement) {
	e.preventDefault();
	var goOn = confirm("DELETE SEASON " + seasonElement.attr('id').substr(6));
	if (goOn) {
		runAPI(
		{ 
			action:"removeSeason", 
			seasonId:seasonElement.attr('id').substr(6) 
		}, 
		function(data) {
			seasonElement.remove();
		});
	}
}

function toggleSeason(seasonElement) {
	var epList = seasonElement.find('.episodeList');
	if (epList.hasClass('notFetched')) {
		loadSeasonEpisodes(seasonElement.attr('id').substr(6));
	}
	seasonElement.find('.episodes').slideToggle();

	var scraperList = seasonElement.find('.scraperList');
	if (scraperList.hasClass('notFetched')) {
		loadSeasonScrapers(seasonElement.attr('id').substr(6));
	}
	seasonElement.find('.scrapers').slideToggle();
}

function refreshSeason(seasonElement) {
	runAPI(
	{
		action:"getSeason",
		seasonId:seasonElement.attr('id').substr(6)
	},
	function(dataSeason) {
		setSeason(dataSeason.result, seasonElement);
		refreshTVShow(seasonElement.closest('.show'));
	});
}

function editSeason(e, seasonElement) {
	e.preventDefault();
	seasonId = seasonElement.attr('id').substr(6);
	seasonN = seasonElement.find('.seasonN').first().text();
	seasonStatus = seasonElement.find('.seasonStatus').first().text();

	setEditSeasonDialog(seasonId, seasonN, seasonStatus, function (e) {
		e.preventDefault();
		runAPI(
		{
			action:"setSeason",
			seasonId:seasonId,
			n:$('#seasonEditDialog #editSeasonN').val(),
			status:$('#seasonEditDialog #editSeasonStatus').find(':selected').first().val()
		},
		function(data) {
			$('#seasonEditDialog').modal('hide');
			refreshSeason(seasonElement);
		}
		);
	});

	$('#seasonEditDialog').modal('show');
}

function setEditSeasonDialog(seasonId, seasonN, seasonStatus, onClick) {
	$('#seasonEditDialog #editSeasonId').val(seasonId == undefined ? "" : seasonId);
	$('#seasonEditDialog #editSeasonN').val(seasonN == undefined ? 1 : seasonN);
	$('#seasonEditDialog #editSeasonStatus option').each(function() { this.selected = (this.value == (seasonStatus == undefined ? "watched" : seasonStatus)); });
	$('#seasonEditDialog #editSeasonSave').unbind('click');
	if (onClick != undefined) {
		$('#seasonEditDialog #editSeasonSave').click(onClick);
	}
}




/******************************************************
 Scraper 
 *****************************************************/

function loadTVShowScrapers(showId) {
	$('#show' + showId + ' .showScrapers .scraperList').removeClass('notFetched');
	runAPI({
		action:"getTVShowScrapers",
		showId:showId
	}, function(data) {
		for (var i = 0; i < data.result.length; i++) {
			addTVShowScraper(showId, data.result[i]);
		}
	});
}

function loadSeasonScrapers(seasonId) {
	$('#season' + seasonId + ' .scraperList').removeClass('notFetched');
	runAPI({
		action:"getSeasonScrapers",
		seasonId:seasonId
	}, function(data) {
		for (var i = 0; i < data.result.length; i++) {
			addSeasonScraper(seasonId, data.result[i]);
		}
	});
}

function addTVShowScraper(showId, scraperObj) {
	var newScraperElem = $('#guiSkeleton .showScraper').clone(true);
	setTVShowScraper(scraperObj, newScraperElem);
	$('#show' + showId + ' .showScrapers .scraperList').append(newScraperElem);
	//seasonScraperSort(seasonId);
}


function addSeasonScraper(seasonId, scraperObj) {
	var newScraperElem = $('#guiSkeleton .seasonScraper').clone(true);
	setSeasonScraper(scraperObj, newScraperElem);
	$('#season' + seasonId + ' .scraperList').append(newScraperElem);
	seasonScraperSort(seasonId);
}

function setTVShowScraper(scraperObj, scraperElem) {
	if (scraperObj.id != undefined) scraperElem.attr('id', 'scraper' + scraperObj.id);
	if (scraperObj.source != undefined) scraperElem.find('.scraperSource').text(scraperObj.source);
	if (scraperObj.uri != undefined) {
		scraperElem.find('.scraperUriText').text(scraperObj.uri);
		scraperElem.find('.scraperUri').attr('href', scraperObj.uri);
	}
	scraperElem.find('.scraperAutoAdd').attr("checked", scraperObj.autoAdd == "1" ? true : false);
	scraperElem.find('.scraperNotify').attr("checked", scraperObj.notify == "1" ? true : false);
}

function setSeasonScraper(scraperObj, scraperElem) {
	if (scraperObj.id != undefined) scraperElem.attr('id', 'scraper' + scraperObj.id);
	scraperElem.find('.scraperPreference').text(scraperObj.preference != undefined ? scraperObj.preference : "");
	if (scraperObj.source != undefined) scraperElem.find('.scraperSource').text(scraperObj.source);
	if (scraperObj.delay != undefined) scraperElem.find('.scraperDelay').text(scraperObj.delay);
	if (scraperObj.uri != undefined) {
		scraperElem.find('.scraperUriText').text(scraperObj.uri);
		scraperElem.find('.scraperUri').attr('href', scraperObj.uri);
	}
}

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
}


function deleteScraper(e, scraperElement) {
	e.preventDefault();
	runAPI(
		{ 
			action:"removeScraper", 
			scraperId:scraperElement.attr('id').substr(7) 
		}, 
		function(data) {
			var seasonElement = scraperElement.closest('.season');
			scraperElement.remove();
			if (seasonElement != undefined && seasonElement.length != 0) {
				console.log("Refreshing"); 
				refreshEpisodeList(seasonElement);
				refreshSeason(seasonElement);
			}
		});
}

function runScraper(e, scraperElement) {
	e.preventDefault();
	runAPI(
		{ 
			action:"runScraper", 
			scraperId:scraperElement.attr('id').substr(7) 
		}, 
		function(data) {
			var seasonElement = scraperElement.closest('.season');
			if (seasonElement != undefined && seasonElement.length != 0) { 
				refreshEpisodeList(seasonElement);
				refreshSeason(seasonElement);
			} else {
				var showElement = scraperElement.closest('.show');
				refreshScrapedSeasons(showElement);
			}
		});
}

function refreshEpisodeList(seasonElement) {
	seasonElement.find('.episodeList .episode').remove();
	seasonElement.find('.episodeList').addClass('notFetched');
	loadSeasonEpisodes(seasonElement.attr('id').substr(6));
}

function addNewScraperSubmit(e, formElement) {
	e.preventDefault();
	var scraperSource = formElement.find('select[name="newScraperSource"] :selected').val();
	var scraperUri = formElement.find('input[name="newScraperURI"]').val();
	var scraperSeasonId = formElement.closest('.season').attr('id').substr(6);
	runAPI(
			{ 
				action:"addScraper", 
				rootId:scraperSeasonId,
				source:scraperSource,
				uri:scraperUri
			}, 
			function(data) {
				addSeasonScraper(scraperSeasonId, data.result);
			});
}



function refreshTVShowScraper(scraperElement) {
	runAPI(
	{
		action:"getScraper",
		scraperId:scraperElement.attr('id').substr(7)
	},
	function(dataScraper) {
		setTVShowScraper(dataScraper.result, scraperElement);
	});
}


function refreshSeasonScraper(scraperElement) {
	runAPI(
	{
		action:"getScraper",
		scraperId:scraperElement.attr('id').substr(7)
	},
	function(dataScraper) {
		setSeasonScraper(dataScraper.result, scraperElement);
		seasonScraperSort(scraperElement.closest('.season').attr('id').substr(6));
		refreshEpisodeList(scraperElement.closest('.season'));
	});
}


function addFormTVShowScraper(e, showElement) {
	e.preventDefault();
	var showId = showElement.attr('id').substr(4);

	setEditTVShowScraperDialog(function (e) {
		e.preventDefault();
		runAPI(
		{
			action:"addScraper",
			rootId:showId,
			uri:$('#showScraperEditDialog #editTVShowScraperURI').val(),
			source:$('#showScraperEditDialog #editTVShowScraperSource').find(':selected').first().val(),
			autoAdd:$('#showScraperEditDialog #editTVShowScraperAutoAdd').first().is(':checked') ? 1 : 0,
			notify:$('#showScraperEditDialog #editTVShowScraperNotify').first().is(':checked') ? 1 : 0
		},
		function(data) {
			$('#showScraperEditDialog').modal('hide');
			addTVShowScraper(showId, data.result);
		}
		);
	});

	$('#showScraperEditDialog').modal('show');

	
}

function editTVShowScraper(e, scraperElement) {
	e.preventDefault();
	scraperId = scraperElement.attr('id').substr(7);
	scraperSource = scraperElement.find('.scraperSource').first().text();
	scraperURI = scraperElement.find('.scraperUriText').first().text();
	scraperAutoAdd = scraperElement.find('.scraperAutoAdd').first().attr('checked');
	scraperNotify = scraperElement.find('.scraperNotify').first().attr('checked');
	
	setEditTVShowScraperDialog(function (e) {
		e.preventDefault();
		runAPI(
		{
			action:"setScraper",
			scraperId:scraperId,
			uri:$('#showScraperEditDialog #editTVShowScraperURI').val(),
			source:$('#showScraperEditDialog #editTVShowScraperSource').find(':selected').first().val(),
			autoAdd:$('#showScraperEditDialog #editTVShowScraperAutoAdd').first().is(':checked') ? 1 : 0,
			notify:$('#showScraperEditDialog #editTVShowScraperNotify').first().is(':checked') ? 1 : 0
		},
		function(data) {
			$('#showScraperEditDialog').modal('hide');
			refreshTVShowScraper(scraperElement);
		}
		);
	}, scraperId, scraperSource, scraperURI, scraperAutoAdd, scraperNotify);

	$('#showScraperEditDialog').modal('show');
}


function editSeasonScraper(e, scraperElement) {
	e.preventDefault();
	scraperId = scraperElement.attr('id').substr(7);
	scraperPreference = scraperElement.find('.scraperPreference').first().text();
	scraperSource = scraperElement.find('.scraperSource').first().text();
	scraperURI = scraperElement.find('.scraperUriText').first().text();
	scraperDelay = scraperElement.find('.scraperDelay').first().text();
	
	setEditSeasonScraperDialog(scraperId, scraperPreference, scraperSource, scraperURI, scraperDelay, function (e) {
		e.preventDefault();
		var pref = $('#seasonScraperEditDialog #editSeasonScraperPreference').val();

		runAPI(
		{
			action:"setScraper",
			scraperId:scraperId,
			preference:(pref != "" ? pref : "_REMOVE_"),
			uri:$('#seasonScraperEditDialog #editSeasonScraperURI').val(),
			delay:$('#seasonScraperEditDialog #editSeasonScraperDelay').val(),
			source:$('#seasonScraperEditDialog #editSeasonScraperSource').find(':selected').first().val()
		},
		function(data) {
			$('#seasonScraperEditDialog').modal('hide');
			refreshSeasonScraper(scraperElement);
		}
		);
	});

	$('#seasonScraperEditDialog').modal('show');
}



function setEditTVShowScraperDialog(onClick, scraperId, scraperSource, scraperURI, scraperAutoAdd, scraperNotify) {
	$('#showScraperEditDialog #editTVShowScraperId').val(scraperId == undefined ? "" : scraperId);
	$('#showScraperEditDialog #editTVShowScraperURI').val(scraperURI == undefined ? "" : scraperURI);
	$('#showScraperEditDialog #editTVShowScraperAutoAdd').attr('checked', scraperAutoAdd ? true : false);
	$('#showScraperEditDialog #editTVShowScraperNotify').attr('checked', scraperNotify ? true : false);
	
	$('#showScraperEditDialog #editTVShowScraperSource option').each(function() { this.selected = (this.value == (scraperSource == undefined ? "DDU" : scraperSource)); });

	$('#showScraperEditDialog #editTVShowScraperSave').unbind('click');
	if (onClick != undefined) {
		$('#showScraperEditDialog #editTVShowScraperSave').click(onClick);
	}
}


function setEditSeasonScraperDialog(scraperId, scraperPreference, scraperSource, scraperURI, scraperDelay, onClick) {

	$('#seasonScraperEditDialog #editSeasonScraperId').val(scraperId == undefined ? "" : scraperId);
	$('#seasonScraperEditDialog #editSeasonScraperPreference').val(scraperPreference == undefined ? "" : scraperPreference);
	$('#seasonScraperEditDialog #editSeasonScraperURI').val(scraperURI == undefined ? "" : scraperURI);
	$('#seasonScraperEditDialog #editSeasonScraperDelay').val(scraperDelay == undefined ? "" : scraperDelay);
	
	$('#seasonScraperEditDialog #editSeasonScraperSource option').each(function() { this.selected = (this.value == (scraperSource == undefined ? "DDU" : scraperSource)); });

	$('#seasonScraperEditDialog #editSeasonScraperSave').unbind('click');
	if (onClick != undefined) {
		$('#seasonScraperEditDialog #editSeasonScraperSave').click(onClick);
	}
}







/******************************************************
 Scraped Seasons 
 *****************************************************/

function refreshScrapedSeasons(showElement) {
	showElement.find('.scrapedSeason').remove();
	showElement.find('.scrapedSeasonsList').addClass('notFetched');

	loadScrapedSeasons(showElement.attr('id').substr(4));
}

function loadScrapedSeasons(showId) {
	$('#show' + showId + ' .showScrapers .scrapedSeasonsList').removeClass('notFetched');
	$('#show' + showId + ' .showScrapers .scrapedSeasonsSection').hide();
	$('#show' + showId + ' .showScrapers .hiddenScrapedSeasonsSection').hide();
	runAPI({
		action:"getScrapedSeasons",
		showId:showId
	}, function(data) {
		for (var i = 0; i < data.result.length; i++) {
			addScrapedSeason(showId, data.result[i]);
		}
	});
}

function addScrapedSeason(showId, scrapedSeasonObj) {
	var scrapedSeasonElem = $('#guiSkeleton .scrapedSeason').clone(true);
	setScrapedSeason(scrapedSeasonElem, scrapedSeasonObj);
	if (scrapedSeasonObj.hide == undefined || scrapedSeasonObj.hide == '0') {
		$('#show' + showId + ' .showScrapers .scrapedSeasonsList').append(scrapedSeasonElem);
		$('#show' + showId + ' .showScrapers .scrapedSeasonsSection').show();
	} else {
		$('#show' + showId + ' .showScrapers .hiddenScrapedSeasonsList').append(scrapedSeasonElem);
		if (showHiddenScrapedSeasons) {
			$('#show' + showId + ' .showScrapers .hiddenScrapedSeasonsSection').show();
		}
	}
}

function setScrapedSeason(scrapedSeasonElem, scrapedSeasonObj) {
	if (scrapedSeasonObj.id != undefined) scrapedSeasonElem.attr('id', scrapedSeasonObj.id);
	if (scrapedSeasonObj.n != undefined) scrapedSeasonElem.find('.scrapedSeasonN').text(scrapedSeasonObj.n);
	if (scrapedSeasonObj.source != undefined) scrapedSeasonElem.find('.scrapedSeasonSource').text(scrapedSeasonObj.source);
	if (scrapedSeasonObj.uri != undefined) {
		scrapedSeasonElem.find('.scrapedSeasonUri').attr('href', scrapedSeasonObj.uri);
		scrapedSeasonElem.find('.scrapedSeasonUriText').text(scrapedSeasonObj.uri);
	}
	if (scrapedSeasonObj.hide == undefined || scrapedSeasonObj.hide == '0') {
		scrapedSeasonElem.find('.toggleScrapedSeason i').attr('class', 'icon-thumbs-down');
	} else {
		scrapedSeasonElem.find('.toggleScrapedSeason i').attr('class', 'icon-thumbs-up');
	}
}

function addScrapedSeasonToSeasons(e, scrapedSeasonElem) {
	e.preventDefault();
	scrapedSeasonId = scrapedSeasonElem.attr('id');

	runAPI({
		action:"createSeasonScraperFromScraped",
		scrapedSeasonId:scrapedSeasonId
	}, function(data) {
		var showId = scrapedSeasonElem.closest('.show').attr('id').substr(4);
		$('#show' + showId + ' .scrapedSeason').remove();
		$('#show' + showId + ' .scrapedSeasonsList').addClass('notFetched');
		$('#show' + showId + ' .season').remove();
		$('#show' + showId + ' .seasons').addClass('notFetched');
		loadTVShowSeasons(showId);
		loadScrapedSeasons(showId);
	});
}


function toggleScrapedSeason(e, scrapedSeasonElem) {
	e.preventDefault();
	scrapedSeasonId = scrapedSeasonElem.attr('id');
	var hide = scrapedSeasonElem.closest('.hiddenScrapedSeasonsList').length == 0 ? '1' : '0';

	runAPI({
		action:"setScrapedSeason",
		scrapedSeasonId:scrapedSeasonId,
		hide:hide,
		tbn:"_REMOVE_"
	}, function(data) {
		//var container = scrapedSeasonElem.closest('.showScrapers').find((hide == '0' ? '.s' : '.hiddenS') +'crapedSeasonsList');
		//container.append(scrapedSeasonElem);

		var showId = scrapedSeasonElem.closest('.show').attr('id').substr(4);
		$('#show' + showId + ' .scrapedSeason').remove();
		loadScrapedSeasons(showId);
	});

}


function removeScrapedSeason(e, scrapedSeasonElem) {
	e.preventDefault();
	scrapedSeasonId = scrapedSeasonElem.attr('id');

	runAPI({
		action:"removeScrapedSeason",
		scrapedSeasonId:scrapedSeasonId,
	}, function(data) {
		var showId = scrapedSeasonElem.closest('.show').attr('id').substr(4);
		$('#show' + showId + ' .scrapedSeason').remove();
		loadScrapedSeasons(showId);
	});

}






function loadSeasonEpisodes(seasonId) {
		
		var epList = $('#season' + seasonId + ' .episodeList');
		epList.removeClass('notFetched');
		$.post("api.php",
			{
				action:"getSeasonEpisodes",
				seasonId:seasonId
			},
			function(data) {
				if (data.status == "ok") {
					var newEpisodeElement = $('#guiSkeleton .episode');
					for (var i = 0; i < data.result.length; i++) {
						var newEp = newEpisodeElement.clone(true);
						newEp.attr('id', 'episode' + data.result[i].id);
						newEp.find('.episodeN').text(data.result[i].n);
						if (data.result[i].airDate  != undefined) {
							var d = new Date(Number(data.result[i].airDate * 1000));
							newEp.find('.episodeAirDate').text(d.getDate() + '/' + (d.getMonth() + 1) + "/" + d.getFullYear());
						} else {
							newEp.find('.episodeAirDate').text("Unknown");
						}

						if (data.result[i].lastFilePubDate  != undefined) {
							var d = new Date(Number(data.result[i].lastFilePubDate * 1000));
							newEp.find('.episodeLastPubDate').text(d.getDate() + '/' + (d.getMonth() + 1) + "/" + d.getFullYear());
							newEp.find('.bestUriText').text("Loading...");

						} else {
							newEp.find('.episodeLastPubDate').text("Not yet");
						}

						epList.append(newEp);
					}

					runAPI( {
							action:"getBestFilesForSeason",
							seasonId:seasonId
							},
							function(data) {
								for (var j = 0; j < data.result.length; j++) {
									$('#episode' + data.result[j].episode + ' .bestUri').attr('href', data.result[j].uri);
									$('#episode' + data.result[j].episode + ' .bestUriText').text(data.result[j].uri);
								}
							});


				} else {
					// TODO: handle error
					alert('ERROR: ' + data.errmsg);
				}
			}, "json");



}


var showSortBy = 'nextAirDate';
var showHiddenScrapedSeasons = false;

$(document).ready(function() {
	//refreshShowList();

	//tvShowSort();
	$('.show .showTitle').click(function() { toggleTVShow($(this).closest('.show'));});
	//$('.season .seasonTitle').click(function() { $(this).closest('.season').find('.scrapers').slideToggle(); toggleSeason($(this).closest('.season').find('.episodes'));});
	$('.season .seasonTitle').click(function() { toggleSeason($(this).closest('.season'));});
	$('.seasonScraper .removeScraper').click(function(e) { deleteScraper(e, $(this).closest('.seasonScraper')); });
	$('.showScraper .removeScraper').click(function(e) { deleteScraper(e, $(this).closest('.showScraper')); });
	$('.scrapedSeason .addScrapedSeason').click(function(e) { addScrapedSeasonToSeasons(e, $(this).closest('.scrapedSeason')); });
	$('.scrapedSeason .toggleScrapedSeason').click(function(e) { toggleScrapedSeason(e, $(this).closest('.scrapedSeason')); });
	$('.scrapedSeason .removeScrapedSeason').click(function(e) { removeScrapedSeason(e, $(this).closest('.scrapedSeason')); });
	$('.seasonScraper .runScraper').click(function(e) { runScraper(e, $(this).closest('.seasonScraper')); });
	$('.showScraper .runScraper').click(function(e) { runScraper(e, $(this).closest('.showScraper')); });
	$('.removeShow').click(function(e) { deleteTVShow(e, $(this).closest('.show')); });
	$('.removeSeason').click(function(e) { deleteSeason(e, $(this).closest('.season')); });

	$('.episodeList').addClass('notFetched');
	$('.scraperList').addClass('notFetched');
	$('.scrapedSeasonsList').addClass('notFetched');
	$('.seasons').addClass('notFetched');

	
	//$('#addNewShowButton').click(function() {addNewShowSubmit();} );
	$('#addNewShowForm').submit(function(e) { addNewShowSubmit(e);} );
	$('form.addNewSeason').submit(function(e) { addNewSeasonSubmit(e, $(this));} );
	$('form.addNewScraper').submit(function(e) { addNewScraperSubmit(e, $(this));} );

	$('#sortByTitle').click(function(e) { e.preventDefault(); showSortBy = 'title'; tvShowSort(); });
	$('#sortByLastUpdate').click(function(e) { e.preventDefault(); showSortBy = 'lastPubDate'; tvShowSort(); });
	$('#sortByLastAirDate').click(function(e) { e.preventDefault(); showSortBy = 'lastAirDate'; tvShowSort(); });
	$('#sortByNextAirDate').click(function(e) { e.preventDefault(); showSortBy = 'nextAirDate'; tvShowSort(); });

	$('#toggleHiddenScrapedSeasons').click(function(e) { 
		e.preventDefault(); 
		showHiddenScrapedSeasons = ! showHiddenScrapedSeasons;
		if (!showHiddenScrapedSeasons) {
			$('.hiddenScrapedSeasonsSection').hide();
		} else {
			$('.hiddenScrapedSeasonsSection').each(function() {
				if ($(this).find('.scrapedSeason').length == 0) {
					$(this).hide();
				} else {
					$(this).show();
				}
			});
		}
	});

	$('.editSeason').click(function(e) { editSeason(e, $(this).closest('.season'));} );
	$('.seasonScraper .editScraper').click(function(e) { editSeasonScraper(e, $(this).closest('.seasonScraper'));} );
	$('.showScraper .editScraper').click(function(e) { editTVShowScraper(e, $(this).closest('.showScraper'));} );
	
	$('.addTVShowScraper').click(function(e) { addFormTVShowScraper(e, $(this).closest('.show'));});


	getAllTVShows();

});
