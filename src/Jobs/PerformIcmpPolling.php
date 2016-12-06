<?php

namespace SonarSoftware\Poller\Jobs;

use Dotenv\Dotenv;
use GuzzleHttp\Client;
use SonarSoftware\Poller\Pollers\Poller;

class PerformIcmpPolling
{
    /**
     * Ping hosts and return to Sonar
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function perform()
    {
        ini_set('memory_limit','1G');
        set_time_limit(7200);

        $dotenv = new Dotenv(dirname(__FILE__) . "/../../");
        $dotenv->load();

        $poller = new Poller();

        $startTime = time();

        $results = $poller->ping($this->args);

        $timeTaken = time() - $startTime;

        $client = new Client();
        $result = $client->post(getenv("SONAR_URI") . "/api/poller/icmp", [
            'headers' => [
                'Content-Type' => 'application/json; charset=UTF8',
                'timeout' => 120,
            ],
            'json' => [
                'results' => $results,
                'api_key' => getenv("API_KEY"),
                'version' => trim(file_get_contents(dirname(__FILE__) . "/../../resources/version")),
                'time' => $timeTaken,
            ]
        ]);

        return $result;
    }
}