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
    protected $log;

    /** Status constants */
    const GOOD = 2;
    const WARNING = 1;
    const DOWN = 0;

    public function __construct()
    {
        $dotenv = new Dotenv(dirname(__FILE__) . "/../../");
        $dotenv->load();
        $this->snmpForks = (int)getenv("SNMP_FORKS") > 0 ? (int)getenv("SNMP_FORKS") : 25;
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

        $chunks = array_chunk($work['hosts'],ceil(count($work['hosts'])/$this->snmpForks));

        $results = [];

        $fileUniquePrefix = uniqid(true);

        for ($i = 0; $i < count($chunks); $i++)
        {
            $pid = pcntl_fork();
            if (!$pid)
            {
                foreach ($chunks[$i] as $host)
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

                    $templateDetails = $work['templates'][$host['template_id']];

                    if (count($templateDetails['oids']) === 0 && $templateDetails['collect_interface_statistics'] == false)
                    {
                        continue;
                    }

                    switch ($templateDetails['snmp_version'])
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

                    //Regular GETs (this will bulk GET multiple OIDs)
                    $snmp = new SNMP($version, $host['ip'], $templateDetails['snmp_community'], 2000000, 0);
                    $snmp->valueretrieval = SNMP_VALUE_LIBRARY;
                    $snmp->oid_output_format = SNMP_OID_OUTPUT_NUMERIC;
                    $snmp->enum_print = true;
                    $snmp->exceptions_enabled = SNMP::ERRNO_ANY;

                    if ($version === SNMP::VERSION_3)
                    {
                        $snmp->setSecurity($templateDetails['snmp3_sec_level'],$templateDetails['snmp3_auth_protocol'],$templateDetails['snmp3_auth_passphrase'],$templateDetails['snmp3_priv_protocol'],$templateDetails['snmp3_priv_passphrase'],$templateDetails['snmp3_context_name'],$templateDetails['snmp3_context_engine_id']);
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

                    $handle = fopen("/tmp/$fileUniquePrefix" . "_sonar_$i","w");
                    if ($handle === false)
                    {
                        $this->log->log("Failed to open handle for /tmp/$fileUniquePrefix" . "_sonar_$i",Logger::ERROR);
                        throw new RuntimeException("Failed to open handle for /tmp/$fileUniquePrefix" . "_sonar_$i");
                    }

                    fwrite($handle, json_encode($resultToWrite));
                    fclose($handle);
                }

                exit($i);
            }
        }

        while (pcntl_waitpid(0, $status) != -1)
        {
            $status = pcntl_wexitstatus($status);
            $output = json_decode(file_get_contents("/tmp/$fileUniquePrefix" . "_sonar_$status"),true);
            if (is_array($output))
            {
                $results = array_merge($results,$output);
            }
            else
            {
                $this->log->log("When unserializing SNMP data, no array was returned as a result of unserialization.",Logger::ERROR);
            }

            unlink("/tmp/$fileUniquePrefix" . "_sonar_$status");
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
}