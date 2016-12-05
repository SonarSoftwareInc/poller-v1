<?php

namespace SonarSoftware\Poller\Pollers;

use Exception;
use RuntimeException;
use SNMP;
use SNMPException;
use SonarSoftware\Poller\Formatters\Formatter;

class Poller
{
    private $icmpForks = 150;
    private $snmpForks = 150;

    /** Status constants */
    const GOOD = 2;
    const WARNING = 1;
    const DOWN = 0;

    /**
     * Ping an array of IPv4/IPv6 hosts
     * @param array $hosts
     * @return array
     * @throws RuntimeException
     */
    public function ping(array $hosts):array
    {
        if (count($hosts) === 0)
        {
            return [];
        }

        $chunks = array_chunk($hosts,ceil(count($hosts)/$this->icmpForks));

        $results = [];
        $socketHolder = [];

        for ($i = 0; $i < count($chunks); $i++)
        {
            socket_create_pair(AF_UNIX, SOCK_STREAM, 0, $sockets);
            $socketHolder[$i] = $sockets;
            $pid = pcntl_fork();
            if (!$pid)
            {
                socket_close($sockets[1]);
                usleep(rand(250000,5000000));
                exec("/usr/bin/fping -A -b 12 -C 10 -D -p 500 -q -r 0 -t 2000 -B 1.0 -i 10 " . implode(" ",$chunks[$i]) . " 2>&1",$result,$returnVar);
                socket_write($sockets[0], serialize($result));
                socket_close($sockets[0]);
                exit($i);
            }

        }

        while (pcntl_waitpid(0, $status) != -1) {
            $status = pcntl_wexitstatus($status);
            $singleSocket = $socketHolder[$status];
            socket_close($singleSocket[0]);
            $output = unserialize(socket_read($singleSocket[1],10000000));
            socket_close($singleSocket[1]);
            if (is_array($output))
            {
                $results = array_merge($results,$output);
            }
        }

        $formatter = new Formatter();
        return $formatter->formatPingResultsFromFping($results, $hosts);
    }

    /**
     * Collect SNMP data from hosts based on templates
     * @param array $work
     * @return array
     */
    public function snmp(array $work):array
    {
        if (count($work['hosts']) === 0)
        {
            return [];
        }

        $chunks = array_chunk($work['hosts'],ceil(count($work['hosts'])/$this->snmpForks));

        $results = [];
        $socketHolder = [];

        for ($i = 0; $i < count($chunks); $i++)
        {
            socket_create_pair(AF_UNIX, SOCK_STREAM, 0, $sockets);
            $socketHolder[$i] = $sockets;
            $pid = pcntl_fork();
            if (!$pid)
            {
                socket_close($sockets[1]);
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
                    $snmp->valueretrieval = SNMP_VALUE_OBJECT | SNMP_VALUE_PLAIN;
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
                            $snmp->valueretrieval = SNMP_VALUE_PLAIN;
                            $result = $snmp->walk("1.3.6.1.2.1.2.2.1");
                            $resultToWrite[$host['ip']]['results']['interfaces'] = json_decode(json_encode($result),true);
                        }
                        catch (SNMPException $e)
                        {
                            $resultToWrite[$host['ip']]['status'] = $this->updateStatusAfterException($e);
                        }
                    }

                    socket_write($sockets[0], serialize($resultToWrite));
                }

                socket_close($sockets[0]);
                exit($i);
            }
        }

        while (pcntl_waitpid(0, $status) != -1)
        {
            $status = pcntl_wexitstatus($status);
            $singleSocket = $socketHolder[$status];
            socket_close($singleSocket[0]);
            $output = unserialize(socket_read($singleSocket[1],100000000));
            socket_close($singleSocket[1]);
            if (is_array($output))
            {
                $results = array_merge($results,$output);
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
}