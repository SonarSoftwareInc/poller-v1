<?php

require_once(dirname(__FILE__) . "/../vendor/autoload.php");

use Dotenv\Dotenv;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use League\CLImate\CLImate;
use Monolog\Logger;
use SonarSoftware\Poller\Formatters\Formatter;
use SonarSoftware\Poller\Services\SonarLogger;
use SonarSoftware\Poller\Services\TemporaryVariables;

$client = new Client();
$climate = new CLImate();
$formatter = new Formatter();
$logger = new SonarLogger();

ini_set('memory_limit','2G');
set_time_limit(60);

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

if (TemporaryVariables::get("ICMP Polling Running") == 1 || TemporaryVariables::get("SNMP Polling Running") == 1)
{
    $climate->shout("There are still jobs pending! Aborting.");
    if (getenv('DEBUG') == true)
    {
        $logger->log("There are still jobs pending! Aborting.",Logger::ERROR);
    }
    return;
}

try {
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
        try {
            $message = json_decode($response->getBody()->getContents());
            $climate->shout("Failed to get work from Sonar - {$message->error->message}");
            if (getenv('DEBUG') == true)
            {
                $logger->log("Failed to get work from Sonar - {$message->error->message}",Logger::ERROR);
            }
        }
        catch (Exception $e)
        {
            $climate->shout("Failed to get work from Sonar - {$e->getMessage()}");
            if (getenv('DEBUG') == true)
            {
                $logger->log("Failed to get work from Sonar - {$e->getMessage()}",Logger::ERROR);
            }
        }
        return;
    }
    catch (Exception $e)
    {
        $climate->shout("Failed to get work from Sonar - {$e->getMessage()}");
        if (getenv('DEBUG') == true)
        {
            $logger->log("Failed to get work from Sonar - {$e->getMessage()}",Logger::ERROR);
        }
        return;
    }

    $climate->lightGreen("Obtained work from Sonar, queueing..");
    if (getenv('DEBUG') == true)
    {
        $logger->log("Obtained work from Sonar, queueing..",Logger::INFO);
    }

    $contents = json_decode($result->getBody()->getContents());

    $work = $formatter->formatIcmpHostsFromSonar($contents);
    TemporaryVariables::set("ICMP Polling Running",1);
    $token = Resque::enqueue('polling', 'SonarSoftware\Poller\Jobs\PerformIcmpPolling', $work, true);
    $climate->lightGreen("Enqueued ICMP job and got token $token");

    $work = $formatter->formatSnmpWork($contents);
    TemporaryVariables::set("SNMP Polling Running",1);
    $token = Resque::enqueue('polling', 'SonarSoftware\Poller\Jobs\PerformSnmpGets', $work, true);
    $climate->lightGreen("Enqueued SNMP gets job and got token $token");
}
catch (Exception $e)
{
    $climate->shout("Encountered an uncaught exception - {$e->getMessage()}");
    $logger->log("Encountered an uncaught exception - {$e->getMessage()}",Logger::ERROR);
}
