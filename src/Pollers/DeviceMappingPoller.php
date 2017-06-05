<?php

namespace SonarSoftware\Poller\Pollers;

use Dotenv\Dotenv;
use Exception;
use Monolog\Logger;
use SNMP;
use SonarSoftware\Poller\DeviceMappers\GenericDeviceMapper;
use SonarSoftware\Poller\Formatters\Formatter;
use SonarSoftware\Poller\Models\Device;
use SonarSoftware\Poller\Services\SonarLogger;

class DeviceMappingPoller
{
    private $snmpForks;
    private $timeout;
    private $log;
    private $retries;
    private $templates;

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

                $myChunksWithDeviceType = $this->determineDeviceTypes($chunks[$i]);

                $childFile = fopen("/tmp/$fileUniquePrefix" . "_". $i,"w");
                $devices = [];

                foreach ($myChunksWithDeviceType as $hostWithDeviceType)
                {
                    try {
                        $device = new Device();
                        $device->setId($hostWithDeviceType['id']);
                        $device->setSnmpObject($this->buildSnmpObjectForHost($hostWithDeviceType));

                        //Additional 'case' statements can be added here to break out querying to a separate device mapper
                        switch ($hostWithDeviceType['type_query_result'])
                        {
                            case "1.3.6.1.4.1.161.19.250.256":
                                //PMP100/450
                                break;
                            case "1.3.6.1.4.1.17713.21":
                                //ePMP
                                break;
                            case "1.3.6.1.4.1.41112.1.4":
                                //Ubiquiti
                                break;
                            default:
                                $genericDeviceMapper = new GenericDeviceMapper($device);
                                $device = $genericDeviceMapper->mapDevice();
                                break;
                        }

                        array_push($devices, $device->toArray());
                    }
                    catch (Exception $e)
                    {
                        if (getenv('DEBUG') == true)
                        {
                            $this->log->log("Failed to get mappings from {$hostWithDeviceType['ip']}, got {$e->getMessage()}",Logger::ERROR);
                        }
                        continue;
                    }
                }

                fwrite($childFile,json_encode($devices));
                fclose($childFile);
                unset($devices);

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

            }
            unlink($file);
        }

        return $results;
    }

    /**
     * Determine the type of a device and return it to the caller for further processing
     * @param array $chunks
     * @return array
     */
    private function determineDeviceTypes(array $chunks)
    {
        $updatedChunks = [];
        foreach ($chunks as $host)
        {
            $snmpObject = $this->buildSnmpObjectForHost($host);
            try {
                $result = $snmpObject->get("1.3.6.1.2.1.1.2.0");
                $result = explode(":",$result);
                $host['type_query_result'] = trim(ltrim($result[1],"."));
                array_push($updatedChunks,$host);
            }
            catch (Exception $e)
            {
                if (getenv('DEBUG') == true)
                {
                    $this->log->log("Failed to get device type from {$host['ip']}, got {$e->getMessage()}",Logger::ERROR);
                }
                continue;
            }
        }

        return $updatedChunks;
    }

    /**
     * Build the SNMP object for a particular host
     * @param array $host
     * @return SNMP
     */
    private function buildSnmpObjectForHost(array $host):SNMP
    {
        $templateDetails = $this->templates[$host['template_id']];
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

        return $snmp;
    }
}