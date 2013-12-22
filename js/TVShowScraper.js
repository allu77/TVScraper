TVShowScraper = {
};

TVShowScraper.toggle = function (tvShowScraperElem) {
	var toggleButton = tvShowScraperElem.find('.toggleTVShowScrapers .glyphicon');
	if (toggleButton.hasClass('glyphicon-collapse-down')) {
		toggleButton.removeClass('glyphicon-collapse-down');
		toggleButton.addClass('glyphicon-collapse-up');
	} else {
		toggleButton.removeClass('glyphicon-collapse-up');
		toggleButton.addClass('glyphicon-collapse-down');
	}
	tvShowScraperElem.find('.showScrapersContent').slideToggle();
}

$(".scrapersSectionTitle").click(function(e) { e.preventDefault(); TVShowScraper.toggle($(this).closest('.showScrapers'));});
$(".toggleTVShowScrapers").click(function(e) { e.preventDefault(); TVShowScraper.toggle($(this).closest('.showScrapers'));});

