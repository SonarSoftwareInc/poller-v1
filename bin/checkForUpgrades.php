<?php
use SonarSoftware\Poller\Services\TemporaryVariables;

require("/opt/poller/vendor/autoload.php");

$currentVersion = (string)file_get_contents("/opt/poller/resources/version");
echo "Checking for new upgrades newer than $currentVersion!\n";

$client = new GuzzleHttp\Client();
$res = $client->get("https://api.github.com/repos/sonarsoftware/poller/tags");
$body = json_decode($res->getBody()->getContents());
$latestVersion = $body[0]->name;

if (version_compare($currentVersion, $latestVersion) === -1)
{
    echo "There is a newer version, $latestVersion.\n";
    exec("(cd /opt/poller; git reset --hard origin/master)",$output,$returnVal);
    if ($returnVal !== 0)
    {
        echo "There was an error updating to master.\n";
        return;
    }
    exec("(cd /opt/poller; git pull)",$output,$returnVal);
    if ($returnVal !== 0)
    {
        echo "There was an error updating to master.\n";
        return;
    }
    exec("(cd /opt/poller; git checkout $latestVersion)",$output,$returnVal);
    if ($returnVal !== 0)
    {
        echo "There was an error checking out $latestVersion.\n";
        return;
    }

    exec("/usr/bin/monit restart defaultQueue");
    
    TemporaryVariables::set("SNMP Polling Running",0);
    TemporaryVariables::set("ICMP Polling Running",0);
}

echo "You are on the latest version.\n";