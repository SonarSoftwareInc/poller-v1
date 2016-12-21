<?php

namespace SonarSoftware\Poller\Pollers;

use Dotenv\Dotenv;
use Monolog\Logger;
use RuntimeException;
use SNMP;
use SNMPException;
use SonarSoftware\Poller\Formatters\Formatter;
use SonarSoftware\Poller\Services\SonarLogger;

class SnmpPoller
{
    protected $snmpForks;
    protected $timeout;
    protected $log;
    protected $retries;
    protected $templates;

    /** Status constants */
    const GOOD = 2;
    const WARNING = 1;
    const DOWN = 0;

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
     * Collect SNMP data from hosts based on templates
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

                $this->pollDevices($chunks[$i],$fileUniquePrefix, $i);
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
        return $formatter->formatSnmpResultsFromSnmpClass($results, $work['hosts']);
    }

    /**
     * Set the status based on the SNMPException parameters
     * @param SNMPException $exception
     * @return array
     */
    private function updateStatusAfterException(SNMPException $exception):array
    {
        $return = [
            'status' => $this::GOOD,
            'status_reason' => trim($exception->getMessage()),
        ];

        switch ($exception->getCode())
        {
            case 8:
            case 16:
            case 32:
            case 64:
                $return['status'] = $this::WARNING;
                break;
            default:
                $return['status'] = $this::DOWN;
                break;
        }

        return $return;
    }

    /**
     * Child polling process
     * @param $chunks
     * @param $fileUniquePrefix
     * @param $counter
     */
    private function pollDevices($chunks, $fileUniquePrefix, $counter)
    {
        $handle = fopen("/tmp/$fileUniquePrefix" . "_sonar_$counter","w");
        if ($handle === false)
        {
            $this->log->log("Failed to open handle for /tmp/$fileUniquePrefix" . "_sonar_$counter",Logger::ERROR);
            throw new RuntimeException("Failed to open handle for /tmp/$fileUniquePrefix" . "_sonar_$counter");
        }

        $resultToWrite = [];

        foreach ($chunks as $host)
        {
            $resultToWrite[$host['ip']] = [
                'results' => [
                    'oids' => null,
                    'interfaces' => null,
                ],
                'status' => [
                    'status' => $this::GOOD,
                    'status_reason' => null,
                ],
                'time' => time(),
            ];

            $templateDetails = $this->templates[$host['template_id']];

            if (count($templateDetails['oids']) === 0 && $templateDetails['collect_interface_statistics'] == false)
            {
                continue;
            }

            $snmpVersion = isset($host['snmp_overrides']['snmp_version']) ? $host['snmp_overrides']['snmp_version'] : $templateDetails['snmp_version'];

            switch ($snmpVersion)
            {
                case 2:
                    $version = SNMP::VERSION_2C;
                    break;
                case 3:
                    $version = SNMP::VERSION_3;
                    break;
                default:
                    $version = SNMP::VERSION_1;
                    break;
            }

            $community = isset($host['snmp_overrides']['snmp_community']) ? $host['snmp_overrides']['snmp_community'] : $templateDetails['snmp_community'];

            //Regular GETs (this will bulk GET multiple OIDs)
            $snmp = new SNMP($version, $host['ip'], $community, $this->timeout, $this->retries);
            $snmp->valueretrieval = SNMP_VALUE_LIBRARY;
            $snmp->oid_output_format = SNMP_OID_OUTPUT_NUMERIC;
            $snmp->enum_print = true;
            $snmp->exceptions_enabled = SNMP::ERRNO_ANY;

            if ($version === SNMP::VERSION_3)
            {
                $snmp->setSecurity(
                    isset($host['snmp_overrides']['snmp3_sec_level']) ? $host['snmp_overrides']['snmp3_sec_level'] : $templateDetails['snmp3_sec_level'],
                    isset($host['snmp_overrides']['snmp3_auth_protocol']) ? $host['snmp_overrides']['snmp3_auth_protocol'] : $templateDetails['snmp3_auth_protocol'],
                    isset($host['snmp_overrides']['snmp3_auth_passphrase']) ? $host['snmp_overrides']['snmp3_auth_passphrase'] : $templateDetails['snmp3_auth_passphrase'],
                    isset($host['snmp_overrides']['snmp3_priv_protocol']) ? $host['snmp_overrides']['snmp3_priv_protocol'] : $templateDetails['snmp3_priv_protocol'],
                    isset($host['snmp_overrides']['snmp3_priv_passphrase']) ? $host['snmp_overrides']['snmp3_priv_passphrase'] : $templateDetails['snmp3_priv_passphrase'],
                    isset($host['snmp_overrides']['snmp3_context_name']) ? $host['snmp_overrides']['snmp3_context_name'] : $templateDetails['snmp3_context_name'],
                    isset($host['snmp_overrides']['snmp3_context_engine_id']) ? $host['snmp_overrides']['snmp3_context_engine_id'] : $templateDetails['snmp3_context_engine_id']
                );
            }

            try {
                if (count($templateDetails['oids']) > 0)
                {
                    $result = $snmp->get(array_values($templateDetails['oids']));
                    $resultToWrite[$host["ip"]]['results']['oids'] = json_decode(json_encode($result),true);
                }
            }
            catch (SNMPException $e)
            {
                $resultToWrite[$host['ip']]['status'] = $this->updateStatusAfterException($e);
            }

            //Interface statistics
            if ($templateDetails['collect_interface_statistics'] == true)
            {
                try {
                    $result = $snmp->walk("1.3.6.1.2.1.2.2.1");
                    $resultToWrite[$host['ip']]['results']['interfaces'] = json_decode(json_encode($result),true);
                }
                catch (SNMPException $e)
                {
                    $resultToWrite[$host['ip']]['status'] = $this->updateStatusAfterException($e);
                }
            }
        }

        fwrite($handle, json_encode($resultToWrite));
        fclose($handle);
    }
}