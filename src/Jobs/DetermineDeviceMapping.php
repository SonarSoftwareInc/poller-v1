<?php

namespace SonarSoftware\Poller\Jobs;

use Carbon\Carbon;
use Dotenv\Dotenv;
use Exception;
use GuzzleHttp\Client;
use Monolog\Logger;
use SonarSoftware\Poller\Pollers\DeviceMappingPoller;
use SonarSoftware\Poller\Services\SonarLogger;
use SonarSoftware\Poller\Services\TemporaryVariables;

class DetermineDeviceMapping
{
    /**
     * Ping hosts and return to Sonar
     * @throws Exception
     */
    public function perform()
    {
        ini_set('memory_limit','1G');
        set_time_limit(7200);
        $logger = new SonarLogger();

        try {
            $dotenv = new Dotenv(dirname(__FILE__) . "/../../");
            $dotenv->load();


            $deviceMappingFrequency = getenv("DEVICE_MAPPING_FREQUENCY") ? (int)getenv("DEVICE_MAPPING_FREQUENCY") : 15;
            if ($deviceMappingFrequency < 1)
            {
                $deviceMappingFrequency = 15;
            }
            $now = new Carbon("UTC");
            $lastDeviceMappingRun = TemporaryVariables::get("Device Mapping Running");
            if ($lastDeviceMappingRun != null)
            {
                try {
                    $lastDeviceMappingRunCarbon = new Carbon($lastDeviceMappingRun, "UTC");
                    if ($now->diffInMinutes($lastDeviceMappingRunCarbon) < $deviceMappingFrequency)
                    {
                        if (getenv('DEBUG') == "true")
                        {
                            $logger->log("Last device mapping cycle was less than $deviceMappingFrequency minutes ago, aborting.",Logger::INFO);
                        }
                        return;
                    }
                }
                catch (Exception $e)
                {
                    $logger->log("Could not instantiate Carbon from $lastDeviceMappingRun", Logger::ERROR);
                }
            }

            if (getenv('DEBUG') == "true")
            {
                $logger->log("Starting device mapping cycle.",Logger::INFO);
            }

            TemporaryVariables::set("Device Mapping Running", $now->toDateTimeString());

            $startTime = time();

            try {
                $poller = new DeviceMappingPoller();
                $results = $poller->poll($this->args);
            }
            catch (Exception $e)
            {
                $logger->log("Caught an exception when attempting to retrieve device mapping.", Logger::ERROR);
                $logger->log($e->getMessage(), Logger::ERROR);
                return;
            }

            $timeTaken = time() - $startTime;

            try {
                $client = new Client();
                $client->post(getenv("SONAR_URI") . "/api/poller/device_mapping", [
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
            }
            catch (Exception $e)
            {
                $logger->log("Caught an exception when attempting to deliver device mapping data to Sonar.", Logger::ERROR);
                $logger->log($e->getMessage(), Logger::ERROR);
                return;
            }

            if (getenv('DEBUG') == "true")
            {
                $logger->log("Finished device mapping cycle.",Logger::INFO);
            }
        }
        catch (Exception $e)
        {
            $logger->log("Uncaught device mapping exception - {$e->getMessage()}.", Logger::ERROR);
        }
        finally
        {
            TemporaryVariables::set("Device Mapping Running",null);
        }
    }
}