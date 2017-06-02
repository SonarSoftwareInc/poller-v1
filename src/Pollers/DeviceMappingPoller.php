<?php

namespace SonarSoftware\Poller\Pollers;

use Dotenv\Dotenv;
use Monolog\Logger;
use SonarSoftware\Poller\Formatters\Formatter;
use SonarSoftware\Poller\Services\SonarLogger;

class DeviceMappingPoller
{
    protected $snmpForks;
    protected $timeout;
    protected $log;
    protected $retries;
    protected $templates;

    /**
     * DeviceMappingPoller constructor.
     */
    public function __construct()
    {
        $dotenv = new Dotenv(dirname(__FILE__) . "/../../");
        $dotenv->load();
        $this->snmpForks = (int)getenv("SNMP_FORKS") > 0 ? (int)getenv("SNMP_FORKS") : 25;
        $this->timeout = (int)getenv("SNMP_TIMEOUT") > 0 ? (int)getenv("SNMP_TIMEOUT")*1000000 : 500000;
        $this->retries = (int)getenv("SNMP_RETRIES");
        $this->log = new SonarLogger();
    }

    /**
     * Poll a list of devices to determine their connections
     * @param array $work
     * @return array
     */
    public function poll(array $work):array
    {
        if (count($work['hosts']) === 0)
        {
            return [];
        }

        $this->templates = $work['templates'];

        $chunks = array_chunk($work['hosts'],ceil(count($work['hosts'])/$this->snmpForks));
        $results = [];
        $fileUniquePrefix = uniqid(true);

        $pids = [];

        for ($i = 0; $i < count($chunks); $i++)
        {
            $pid = pcntl_fork();
            if (!$pid)
            {
                //Don't parse empty workloads
                if (count($chunks[$i]) === 0)
                {
                    exit();
                }

                //TODO: Need to do the work here
                exit();
            }
            else
            {
                $pids[$pid] = $pid;
            }
        }


        while (count($pids) > 0)
        {
            foreach ($pids as $pid)
            {
                $res = pcntl_waitpid($pid, $status, WNOHANG);
                if ($res == -1 || $res > 0)
                {
                    unset($pids[$pid]);
                }
            }

            sleep(1);
        }

        $files = glob("/tmp/$fileUniquePrefix*");
        foreach ($files as $file)
        {
            $output = json_decode(file_get_contents($file),true);
            if (is_array($output))
            {
                $results = array_merge($results,$output);
                unlink($file);
            }
            else
            {
                $this->log->log("Couldn't open $file",Logger::INFO);
            }
        }

        $formatter = new Formatter();
        //TODO: Need to add a function here to format device mapping results
        return $formatter->formatSnmpResultsFromSnmpClass($results, $work['hosts']);
    }
}