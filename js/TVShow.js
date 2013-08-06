var TVShow = {};

TVShow.set = function(showObj, showElem) {
	if (showObj.id != undefined) showElem.attr('id', 'show' + showObj.id);
	if (showObj.title != undefined) showElem.find('.showTitle').text(showObj.title);
	if (showObj.lastAirDate != undefined) {
		showElem.find('.lastAirDate').text(showObj.lastAirDate);
		var d = new Date(Number(showObj.lastAirDate * 1000));
		showElem.find('.lastAirDateStr').text(d.getDate() + '/' + (d.getMonth() + 1) + "/" + d.getFullYear());
	}
	if (showObj.nextAirDate != undefined) {
		showElem.find('.nextAirDate').text(showObj.nextAirDate);
		var d = new Date(Number(showObj.nextAirDate * 1000));
		showElem.find('.nextAirDateStr').text(d.getDate() + '/' + (d.getMonth() + 1) + "/" + d.getFullYear());
	}
	if (showObj.lastPubDate != undefined) {
		showElem.find('.lastPubDate').text(showObj.lastPubDate);
		var d = new Date(Number(showObj.lastPubDate * 1000));
		showElem.find('.lastPubDateStr').text(d.getDate() + '/' + (d.getMonth() + 1) + "/" + d.getFullYear());
	}
	if (showObj.alternateTitle != undefined) {
		showElem.find('.alternateTitle').text(showObj.alternateTitle);
	}
	if (showObj.lang != undefined) {
		showElem.find('.language').text(showObj.lang);
	}
	if (showObj.nativeLang != undefined) {
		showElem.find('.nativeLanguage').text(showObj.nativeLang);
	}
	if (showObj.res != undefined) {
		showElem.find('.resolution').text(showObj.res);
	}
}

TVShow.add = function(showObj) {
	var newTVShowElement = $('#guiSkeleton .show').clone(true);
	TVShow.set(showObj, newTVShowElement);
	$('#showList').append(newTVShowElement);
	tvShowSort();
}

TVShow.showEditDialog = function(onClick, id, title, alternateTitle, language, nativeLanguage, res ) {
	if (TVShow.dialog == undefined) {
		TVShow.dialog = $("#showEditDialog").first();
	}

	var isNewDialog = (id == undefined);
	TVShow.dialog.find('#editTVShowTitle').val(isNewDialog ? "" : title);
	TVShow.dialog.find('#editTVShowAlternateTitle').val(isNewDialog ? "" : alternateTitle);
	TVShow.dialog.find('#editTVShowLanguage option').each(function() { this.selected = (this.value == (isNewDialog ? 'ita' : language)); });
	TVShow.dialog.find('#editTVShowNativeLanguage option').each(function() { this.selected = (this.value == (isNewDialog ? 'eng' : nativeLanguage)); });
	TVShow.dialog.find('#editTVShowResolution option').each(function() { this.selected = (this.value == (isNewDialog ? 'any' : res)); });

	
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
			TVShow.dialog.modal('hide');
		});
	});
};

TVShow.edit = function(e) {
	e.preventDefault();
	alert("NOT SUPPORTED YET");
}


$(".createTVShow").click(function(e) { TVShow.create(e); });
$(".editTVShow").click(function(e) { TVShow.edit(e); });
