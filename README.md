# Sonar Poller

This poller is designed to collect network data and return it to your [Sonar](https://sonar.software) instance. You can deploy multiple pollers in a variety of locations to distribute polling, or to gather data from remote network locations.

## Requirements

The poller requires a 64bit x86 CPU. At least 2 cores and 4GB of RAM is recommended - assigning more cores will help with rapid polling to a degree. The poller is mostly built in PHP and has been tested on Ubuntu Linux 16.04.

## Installation & Setup

This poller should run on most versions of Linux, although the instructions here are tailored to Ubuntu 16.04 64bit. If in doubt, use Ubuntu 16.04 64bit!

**If you are not familiar with Linux, I strongly suggest using Ubuntu 16.04 64bit, as you will need to determine how to install the necessary packages detailed below yourself if you use another distribution.**

You will need to install the following packages to use this poller - you can use the command below to install them all on Ubuntu 16.04.

`sudo apt-get install php7.0-cli php-zip php-snmp php-sqlite3 php-bcmath php-mbstring php-dom git fping snmp redis-server monit ntp snmp-mibs-downloader`

If you are using another distribution, you will need the following packages:

* PHP 7.x with the CLI, ZIP, SNMP, SQLite3, BCMath, MBString, and DOM packages. **This poller will not work with any version of PHP prior to 7.x.**
* git
* FPing 3.x or higher
* net-snmp 5.7.x or higher (with the standard MIBs loaded.)
* redis-server
* monit

Once the packages are installed, clone the poller from this repository by typing `git clone https://github.com/SonarSoftware/poller.git`, followed by `cd poller` and then `sudo php install.php`.

Once the install is complete, navigate to your Sonar instance, go to Network > Monitoring > Pollers, and add a new entry for this poller. Select all the subnets you wish this poller to poll. After it is added, copy the API key shown in the poller list - you'll need it in a second.

Once the installer is finished, type `cp /opt/poller/.env.example /opt/poller/.env` and then `nano -w /opt/poller/.env`. Modify the `SONAR_URI` value to be the full path to your Sonar instance, including https://. Modify the `API_KEY` value to be the API key you copied from Sonar a moment ago.

Press `CTRL+X` to exit nano, and save the changes you made.

## Testing

You can test the pollers ability to obtain and deliver work by typing `php /opt/poller/bin/getWork.php`. If something is misconfigured, you will receive a message with details about the problem. If you see `Obtained work from Sonar, queueing..` then everything is configured correctly.

## Upgrading

You can upgrade by updating the repository and checking out the tag you wish to update to. You can also simplify this by using the `checkForUpgrades` script. To run this, type `sudo php /opt/poller/bin/checkForUpgrades.php`. You should do this fairly regularly to ensure your poller is up to date.

You can also automate updates by adding this file to cron. To do this, copy the automatic cron file by typing <code>sudo cp poller_upgrade /etc/cron.d</code>. This will force the system to check for poller updates every morning, and update if any are available.