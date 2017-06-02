<?php

use SonarSoftware\Poller\Services\TemporaryVariables;

require("/opt/poller/vendor/autoload.php");

TemporaryVariables::set("SNMP Polling Running",null);
TemporaryVariables::set("ICMP Polling Running",null);