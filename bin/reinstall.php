<?php
require_once("vendor/autoload.php");

$climate = new League\CLImate\CLImate;

if (trim(shell_exec("whoami")) != "root")
{
    $climate->shout("Please run this installer as root.");
    return;
}

$climate->white("Installing to /opt/poller... ");
try {
    if (!file_exists("/opt/poller"))
    {
        execCommand("/bin/mkdir /opt/poller");
    }
    execCommand("/bin/cp -R " . dirname(__FILE__) ."/../. /opt/poller/");
    execCommand("/bin/chown -R sonarpoller:sonarpoller /opt/poller/.");
}
catch (RuntimeException $e)
{
    $climate->shout("FAILED!");
    return;
}
