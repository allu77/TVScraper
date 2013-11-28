TVScraper
=========

TVScraper will monitor for you different possible web sources in order to receive scheduling of yout favourites TV Shows
and links for their episodes.

TVScraper is both a web and a shell application. Web server is not provided and so you need to have some minimal 
knowledge regarding configuration of a web server of your choice (e.g. apache or lighttpd).

Show schedules can be fetched from http://www.tvrage.com/ for US schedule and from http://it.wikipedia.org/ for Italian
schedule. Episode download link can be fectched from http://www.ddunlimited.net/, http://tvunderground.org.ru/, or from
an RSS or a TXT file.

## Step by step install ##

1.  Download the [master .zip](https://github.com/allu77/TVScraper/archive/master.zip)
2.  Unzip it in your docroot and rename the folder to whatever you want (e.g. TVScraper)
3.  Rename config-sample.php to config.php
4.  Open config.php with any editor and configure DDU\_LOGIN and DDU\_PASSWORD
5.  Make sure that cache, lib and log folder are writable by your http server (e.g. chgrp to www-data and chmod to g+w)
6.  Point your browser to the installed location (e.g. http://localhost/TVScraper/) and you're ready to go!

You could also consider installing svn or git and download sources from the repository.

## Configuring and using TVScraper ##

### Configuring TV Shows ###

You can add new TV Shows by clicking on the "plus" at the top of the page. Once a TV Show is created, 
you can have TVScraper search the web for new seasons. You do this by configuring one or more "TV Show Scraper". Click 
on the plus icon and configure the source you want to use. You can add more than one TV Show Scaper for each TV Show. 
Supported sources for TV Show Scapers are:

- Wikipedia.it (italian show schedule): use the URL of the TV Show page from wikipedia Italy (e.g. http://it.wikipedia.org/wiki/Big\_Bang\_Theory)
- TVRage.com (US show schedule): use the URL of the episode list API for the show (e.g. http://services.tvrage.com/feeds/episode\_list.php?sid=5098) 
- DDUnlinmited: use the URL of the messaging board where new season of the show will be posted
- TV Undeground: use the URL of the show page (e.g. http://tvunderground.org.ru/index.php?show=season&sid=2832)
- RSS: use the URL of a RSS containing links for new episodes
- TXT: use the URL of a text file containing links for new episodes

Once one or more TV Show Scrapers are configured, you can run them manually by clicking on the refresh icon on the right
or schedule the scrapers to run via command line (see later). 

One a new season is found, a new section title "Scraped Seasons" will be created under the TV Show section. The
Scraped Season is the URI of a source for information for a specific season of this TV Show. By clicking
on the "thumbs up" icon, TV Scraper will create a new season for you (if that season hadn't already been created) and
and the found source to the Season Scrapers for that season (see later).

If for any reason the scraped season is an invalid or useless link, you can hide hit by clicking on the thumbs down icon.

Note: if you click on the trash icon, the Scraped Season will be deleted from the database, but will re-appear if
scraped once more, while having it hidden with thumbs down button, will prevent this link to re-appear.

### Configuring Seasons ###

### Managing Episodes ###

### Automation via command line ###
