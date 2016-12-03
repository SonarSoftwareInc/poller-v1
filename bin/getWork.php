<?php

require_once(dirname(__FILE__) . "/../vendor/autoload.php");

use Dotenv\Dotenv;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use League\CLImate\CLImate;
use SonarSoftware\Poller\Formatters\Formatter;

$client = new Client();
$climate = new CLImate();
$formatter = new Formatter();

try {
    $dotenv = new Dotenv(dirname(__FILE__) . "/../");
    $dotenv->load();
}
catch (Exception $e)
{
    $climate->shout("Could not find .env file at " . dirname(__FILE__) . "/../.env");
    return;
}

Resque::setBackend('localhost:6379');

if (Resque::size("polling") > 0)
{
    $climate->shout("There are still jobs pending! Aborting.");
    return;
}

ini_set('memory_limit','2G');
set_time_limit(60);

try {
    $result = $client->get(getenv("SONAR_URI") . "/api/poller", [
        'headers' => [
            'Content-Type' => 'application/json; charset=UTF8',
            'timeout' => 30,
        ],
        'json' => [
            'api_key' => getenv("API_KEY"),
            'version' => trim(file_get_contents(dirname(__FILE__) . "/../resources/version")),
        ]
    ]);
}
catch (ClientException $e)
{
    $response = $e->getResponse();
    $message = json_decode($response->getBody()->getContents());
    $climate->shout("Failed to get work from Sonar - {$message->error->message}");
    return;
}
catch (Exception $e)
{
    $climate->shout("Failed to get work from Sonar - {$e->getMessage()}");
    return;
}

$climate->lightGreen("Obtained work from Sonar, queueing..");

$contents = json_decode($result->getBody()->getContents());
$work = $formatter->formatIcmpHostsFromSonar($contents);

$token = Resque::enqueue('polling', 'SonarSoftware\Poller\Jobs\PerformIcmpPolling', $work, true);
$climate->lightGreen("Enqueued ICMP job and got token $token");

$work = $formatter->formatSnmpWork($contents);
$token = Resque::enqueue('polling', 'SonarSoftware\Poller\Jobs\PerformSnmpGets', $work, true);
$climate->lightGreen("Enqueued SNMP gets job and got token $token");
