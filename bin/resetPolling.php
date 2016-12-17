<?php

use SonarSoftware\Poller\Services\TemporaryVariables;

require("/opt/poller/vendor/autoload.php");

TemporaryVariables::set("SNMP Polling Running",0);
TemporaryVariables::set("ICMP Polling Running",0);