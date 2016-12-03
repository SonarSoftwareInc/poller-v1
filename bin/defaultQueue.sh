#!/bin/bash
QUEUE=polling APP_INCLUDE=/opt/poller/vendor/autoload.php /usr/bin/php /opt/poller/vendor/chrisboulton/php-resque/resque.php &
echo $! > /home/sonarpoller/sonar_defaultQueue.pid
