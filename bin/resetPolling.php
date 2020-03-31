<?php
require_once(dirname(__FILE__) . "/../vendor/autoload.php");
use SonarSoftware\Poller\Services\TemporaryVariables;

Resque::setBackend('localhost:6379');
TemporaryVariables::set("SNMP Polling Running",null);
TemporaryVariables::set("ICMP Polling Running",null);
//TODO remove all the crap that built up in the queue(mostly device mapping, but at times is also a ton of ICMP polling ) this doesn't work for some reason
//Resque::dequeue('polling');
