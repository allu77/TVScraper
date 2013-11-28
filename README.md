TVScraper
=========

TVScraper will monitor for you different possible web sources in order to receive scheduling of yout favourites TV Shows
and links for their episodes.

TVScraper is both a web and a shell application. Web server is not provided and so you need to have some minimal 
knowledge regarding configuration of a web server of your choice (e.g. apache or lighttpd).

Show schedules can be fetched from <http://www.tvrage.com/> for US schedule and from <http://it.wikipedia.org/> for Italian
schedule. Episode download link can be fectched from <http://www.ddunlimited.net/>, <http://tvunderground.org.ru/>, or from
an RSS or a TXT file.

## Step by step install ##

1.  Download the [master .zip](https://github.com/allu77/TVScraper/archive/master.zip)
2.  Unzip it in your docroot and rename the folder to whatever you want (e.g. TVScraper)
3.  Rename config-sample.php to config.php
4.  Open config.php with any editor and configure DDU\_LOGIN and DDU\_PASSWORD
5.  Make sure that cache, lib and log folder are writable by your http server (e.g. chgrp to www-data and chmod to g+w)
6.  Point your browser to the installed location (e.g. <http://localhost/TVScraper/>) and you're ready to go!

You could also consider installing svn or git and download sources from the repository.

## Configuring and using TVScraper ##

### Configuring TV Shows ###

You can add new TV Shows by clicking on the "plus" at the top of the page. Click on the TV Show title to open 
or close TV Show details. Once a TV Show is created, 
you can have TVScraper search the web for new seasons. You do this by configuring one or more "TV Show Scraper". Click 
on the plus icon and configure the source you want to use. You can add more than one TV Show Scaper for each TV Show. 
Supported sources for TV Show Scapers are:

- Wikipedia.it (italian show schedule): use the URL of the TV Show page from wikipedia Italy (e.g. <http://it.wikipedia.org/wiki/Big_Bang_Theory>)
- TVRage.com (US show schedule): use the URL of the episode list API for the show (e.g. <http://services.tvrage.com/feeds/episode_list.php?sid=5098>) 
- DDUnlinmited: use the URL of the messaging board where new season of the show will be posted
- TV Undeground: use the URL of the show page (e.g. <http://tvunderground.org.ru/index.php?show=season&sid=2832>)
- RSS: use the URL of a RSS containing links for new episodes
- TXT: use the URL of a text file containing links for new episodes

Once one or more TV Show Scrapers are configured, you can run them manually by clicking on the refresh icon on the right
or schedule the scrapers to run via command line (see later). 

One a new season is found, a new section title "Scraped Seasons" will be created under the TV Show section. The
Scraped Season is the URI of a source for information for a specific season of this TV Show. By clicking
on the "thumbs up" icon, TV Scraper will create a new season for you (if that season hadn't already been created) and
and the found source to the Season Scrapers for that season (see later).

Note: if a TV Show includes at least one season in complete status (see below), only scraped seasons later that the
latest complete season will appear in this section.

If for any reason the scraped season is an invalid or useless link, you can hide it by clicking on the thumbs down icon.

Note: if you click on the trash icon, the Scraped Season will be deleted from the database, but will re-appear if
scraped once more, while having it hidden with thumbs down button, will prevent this link to re-appear.

### Configuring Seasons ###

You either add a new seasons using the TV Show Scraper feature, as described above, or manually by inserting the season
index in the text box and pressing the Add button. Once a new season has been created, you can change its status by 
clicking on the Edit link. Available statuses are:

- Watched: episode details are scraped using the season scrapers (see below) and results appear when episode links are
queried (see below)
- Complete: all episodes have already been downloaded. Season scrapers won't run anymore and results won't appear anymore 
when episode links are queried
- Ignored: Similar to complete status, but has no effect on TV Show scrapers.

Click on the season title to open or close details. You can, and by the way you should, add season scrapers to each
season you want to follow. Season scrapers are either created by the TV Show Scrapers, or, alternatively, you can add
a season scraper manually by selecting the scraper type from the drop down list, typing an appropriate URL and finally
clicking the Add button. Supported sources for season scrapers are:

- Wikipedia.it (italian show schedule): use the URL of the season page from wikipedia Italy (e.g. <http://it.wikipedia.org/wiki/Episodi_di_Big_Bang_Theory_(sesta_stagione)>)
- TVRage.com (US show schedule): use the URL of the episode list API for the show (e.g. <http://services.tvrage.com/feeds/episode_list.php?sid=5098>) . The season scraper will look only for episodes of this season.
- DDUnlinmited: use the URL of the first page of the thread where new episodes of this season will be posted
- TV Undeground: use the URL of the RSS of the season you want to download (e.g. <http://tvunderground.org.ru/rss.php?se_id=60938>)
- RSS: use the URL of a RSS containing links for new episodes. The scraper will parse the filename or the item title and look only
for episodes of this season.
- TXT: use the URL of a text file containing links for new episodes. The scraper will parse the filename and look only for episodes of this season.

Once one or more TV Show Scrapers are configured, you can run them manually by clicking on the refresh icon on the right
or schedule the scrapers to run via command line (see later). 



### Managing Episodes ###

### Automation via command line ###
