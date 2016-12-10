<?php

namespace SonarSoftware\Poller\Services;

use Carbon\Carbon;
use Monolog\Logger;

class SonarLogger
{
    /**
     *
    DEBUG (100): Detailed debug information.
    INFO (200): Interesting events. Examples: User logs in, SQL logs.
    NOTICE (250): Normal but significant events.
    WARNING (300): Exceptional occurrences that are not errors. Examples: Use of deprecated APIs, poor use of an API, undesirable things that are not necessarily wrong.
    ERROR (400): Runtime errors that do not require immediate action but should typically be logged and monitored.
    CRITICAL (500): Critical conditions. Example: Application component unavailable, unexpected exception.
    ALERT (550): Action must be taken immediately. Example: Entire website down, database unavailable, etc. This should trigger the SMS alerts and wake you up.
    EMERGENCY (600): Emergency: system is unusable.
     */

    protected $log;
    public function __construct()
    {
        $this->log = new \Monolog\Logger('sonar_poller');
        $this->log->pushHandler(new \Monolog\Handler\StreamHandler("/var/log/sonar_poller.log"));
    }

    /**
     * Log a message to the Sonar log file
     * @param $message
     * @param $level
     */
    public function log($message, $level)
    {
        $now = Carbon::now("UTC");
        $message = "[" . $now->toIso8601String() . "] $message";

        switch ($level)
        {
            case Logger::DEBUG:
                $this->log->debug($message);
                break;
            case Logger::INFO:
                $this->log->info($message);
                break;
            case Logger::NOTICE:
                $this->log->notice($message);
                break;
            case Logger::WARNING:
                $this->log->warning($message);
                break;
            case Logger::ERROR:
                $this->log->error($message);
                break;
            case Logger::CRITICAL:
                $this->log->critical($message);
                break;
            case Logger::ALERT:
                $this->log->alert($message);
                break;
            case Logger::EMERGENCY:
                $this->log->emergency($message);
                break;
            default:
                throw new InvalidArgumentException("Level is invalid.");
                break;
        }
    }
}