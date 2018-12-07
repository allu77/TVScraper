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
- TVMaze.com (US show schedule): use the URL of the episode list API for the show (e.g. <http://api.tvmaze.com/shows/7/episodes>) 
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
- TVRage.com (US show schedule): use the URL of the episode list API for the show (e.g. <http://services.tvrage.com/feeds/episode_list.php?sid=5098>). The season scraper will look only for episodes of this season.
- TVMaze.com (US show schedule): use the URL of the episode list API for the show (e.g. <http://api.tvmaze.com/shows/7/episodes>). The season scraper will look only for episodes of this season.
- DDUnlinmited: use the URL of the first page of the thread where new episodes of this season will be posted
- TV Undeground: use the URL of the RSS of the season you want to download (e.g. <http://tvunderground.org.ru/rss.php?se_id=60938>)
- RSS: use the URL of a RSS containing links for new episodes. The scraper will parse the filename or the item title and look only
for episodes of this season.
- TXT: use the URL of a text file containing links for new episodes. The scraper will parse the filename and look only for episodes of this season.

Once one or more Season Scrapers are configured, you can run them manually by clicking on the refresh icon on the right
or schedule the scrapers to run via command line (see later). If you configure more than one Season Scraper, TVScraper will
merge results according to your preferences and appearing order. You can tune scraper results by changing preference and
delay parameters for each season scraper.

- Scrapers are results evaulated in preference order. Empty preference has highest priority, followed by preference value 
in increasing order. If two scrapers with different priorities provide link for the same episode, the highest priority one
wins.
- If two scrapers with same priority provide link for the same episode, the oldest link wins. An exception to this rule 
is applied when a single scraper provides two different links for the same episode. In this case, the scraper which pulished
the first link before wins.
- If a delay is configured for a Season Scraper, that delay will be applied to scraped links, so that they won't be considered
until that delay (in seconds) expires.

### Managing Episodes ###

You will find air dates and episode links in the Espisodes section. If you are using more than one season scraper, you can
fine tune the results by clicking on the icons on the right of each episode. 

- Trash icon will remove this link from the database. Warning: this is meant to be used to overcome temporary bad result 
received from scraper, as the same link could re-appear if the scraper finds it again. 
- OK sign (v) will "lock" a scraper result, meaning that it will always be considered as the best one for that episode, and
the standard priority rules for scrapers results won't be applied anymore. Click on the sign again to unlock the result and
let stanard priority logic apply.
- The ban sign will mark a result as invalid. Warning: that result won't ever come up anymore.

### Automation via command line ###

Using the `tvjobs` shell script, some action can be perfomed using command line. Using this script, you can automate 
your scraping activity. `curl` is required to be installed and in `PATH`. The following syntax is supported:

    tvjobs [OPTIONS] <ACTION> ...

`tvjobs` works using the same APIs used by the web interfaces, so ou need to provide `tvjobs` some basic information
in order to reache the APIs. This is done using the following options:

    -b HTTP_BASE_URL
    -u HTTP_USER
    -p HTTP_PASSWORD

- `HTTP_BASE_URL` is the URL used to reach the web GUI (e.g. <http://localhost/TVScraper/>).
- `HTTP_USER` and `HTTP_USER` are needed only if your webserver is configured to require authentication to reach
TVScraper (only HTTP digest authentication is supported).

You may either provide these parameters via options each time you run `tvjobs` or alternatively you can 
configure these parameters in one of the two following files

    /etc/tvscraper
    ~/.tvscraperrc

Configuration file syntax is the following

    HTTP_BASE_URL=http://localhost/TVScraper/
	HTTP_USER=YOUR_USER_HERE
	HTTP_PASSWORD=YOUR_PASSWORD_HERE

The following ACTIONs are currently supported

- `update`: runs all the TV Show scrapers and all the Season scrapers for seasons in watched status
- `notify`: sends an e-mail if a TV Show scraper found a new season scaper candidate (only for TV Show scrapers with notify option enabled)
- `cleanup`: in order to reduce response time of the web GUI and APIs, you need to keep your database as small as possible. This
action remove all the complete seasons except the last one. On top of this, the scraped seasons referring to complete seasons will be removed
- `get-best`: prints to stdout the list of seleted links for all episodes in watched seasons
- `find-missing`: finds a prints to stdout the list of aired episodes missing a valid link

Some of these actions support additional options. Run `tvjobs` without any actions for a detailed list.

## Migrate DB to SQLite ##

Previous versions of TVScraper were saving data in an XML file. This was requiring TVScraper to lock the file before every operation, resulting
in poor performance and serial operations. In order to start using SQLite, you first need to set the DB_FILE constant in config.php
migrate your data from the XML file. Data can then be migrated by using the migrate.php script.
