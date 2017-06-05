<?php

namespace SonarSoftware\Poller\DeviceMappers;

use SonarSoftware\Poller\Formatters\Formatter;
use SonarSoftware\Poller\Models\Device;

abstract class BaseDeviceMapper
{
    protected $snmp;
    protected $device;
    public function __construct(Device $device)
    {
        $this->snmp = $this->snmp = $device->getSnmpObject();
        $this->device = $device;
    }

    /**
     * @return array
     */
    protected function buildInitialInterfaceArray():array
    {
        $result = $this->snmp->walk("1.3.6.1.2.1.31.1.1.1.1");
        $interfaces = [];
        foreach ($result as $key => $datum)
        {
            $boom = explode(".",$key);
            $interfaces[$boom[count($boom)-1]] = [
                'name' => $this->cleanSnmpResult($datum),
                'status' => null,
                'connected' => [],
                'ip_addresses' => [],
                'mac_address' => null,
            ];
        }
        return $interfaces;
    }

    /**
     * @param array $interfacesIndexedByInterfaceID
     * @return array
     */
    protected function getInterfaceMacAddresses(array $interfacesIndexedByInterfaceID):array
    {
        $result = $this->snmp->walk("1.3.6.1.2.1.2.2.1.6");
        foreach ($result as $key => $datum)
        {
            $key = ltrim($key,".");
            $boom = explode(".", $key);
            if (isset($interfacesIndexedByInterfaceID[$boom[count($boom)-1]]))
            {
                $mac = Formatter::formatMac($this->cleanSnmpResult($datum));
                if ($this->validateMac($mac))
                {
                    $interfacesIndexedByInterfaceID[$boom[count($boom)-1]]['mac_address'] = $mac;
                }
            }
        }

        return $interfacesIndexedByInterfaceID;
    }

    /**
     * @param array $interfacesIndexedByInterfaceID
     * @return array
     */
    protected function getArp(array $interfacesIndexedByInterfaceID):array
    {
        $result = $this->snmp->walk("1.3.6.1.2.1.4.22.1.2");
        foreach ($result as $key => $datum)
        {
            $key = ltrim($key,".");
            $boom = explode(".", $key);
            if (isset($interfacesIndexedByInterfaceID[$boom[count($boom)-5]]))
            {
                $mac = Formatter::formatMac($this->cleanSnmpResult($datum));
                if ($this->validateMac($mac))
                {
                    array_push($interfacesIndexedByInterfaceID[$boom[count($boom)-5]]['connected'],$mac);
                }
            }
        }

        return $interfacesIndexedByInterfaceID;
    }

    /**
     * @param array $interfacesIndexedByInterfaceID
     * @return array
     */
    protected function getBridgingTable(array $interfacesIndexedByInterfaceID):array
    {
        $mappings = [];

        $result = $this->snmp->walk("1.3.6.1.2.1.17.4.3.1.2");
        foreach ($result as $key => $datum)
        {
            $datum = $this->cleanSnmpResult($datum);
            if (!$datum)
            {
                continue;
            }
            $key = ltrim($key,".");
            $boom = explode(".",$key,12);
            $mappings[$boom[11]] = $datum;
        }

        $result = $this->snmp->walk("1.3.6.1.2.1.17.4.3.1.1");
        foreach ($result as $key => $datum)
        {
            $datum = $this->cleanSnmpResult($datum);
            if (!$datum)
            {
                continue;
            }
            $macAddress = Formatter::formatMac($datum);
            if ($this->validateMac($macAddress))
            {
                $key = ltrim($key,".");
                $boom = explode(".",$key,12);
                if (isset($mappings[$boom[11]]))
                {
                    if (isset($interfacesIndexedByInterfaceID[$mappings[$boom[11]]]))
                    {
                        array_push($interfacesIndexedByInterfaceID[$mappings[$boom[11]]]['connected'],$macAddress);
                    }
                }
            }
        }

        return $interfacesIndexedByInterfaceID;
    }

    /**
     * @param array $interfacesIndexedByInterfaceID
     * @return array
     */
    protected function getInterfaceStatus(array $interfacesIndexedByInterfaceID):array
    {
        $result = $this->snmp->walk("1.3.6.1.2.1.2.2.1.8");
        foreach ($result as $key => $datum)
        {
            $boom = explode(".",$key);
            if (isset($interfacesIndexedByInterfaceID[$boom[count($boom)-1]]))
            {
                $interfacesIndexedByInterfaceID[$boom[count($boom)-1]]['status'] = strpos($this->cleanSnmpResult($datum),"1") !== false ? true : false;
            }
        }
        return $interfacesIndexedByInterfaceID;
    }

    /**
     * @param array $interfacesIndexedByInterfaceID
     * @return array
     */
    protected function getIpv4Addresses(array $interfacesIndexedByInterfaceID):array
    {
        $result = $this->snmp->walk("1.3.6.1.2.1.4.20.1");
        $resultsToBeInserted = [];
        foreach ($result as $key => $datum)
        {
            $key = ltrim($key,".");
            $key = str_replace("1.3.6.1.2.1.4.20.1.","",$key);
            $boom = explode(".",$key,2);

            if (!isset($resultsToBeInserted[$boom[1]]))
            {
                $resultsToBeInserted[$boom[1]] = [
                    'ip' => null,
                    'index' => null,
                    'subnet' => null,
                ];
            }

            switch ($boom[0])
            {
                //If $boom[0] is 1, it's the IP. If 2, it's the interface index. If 3, it's the subnet mask.
                case 1:
                    $resultsToBeInserted[$boom[1]]['ip'] = $this->cleanSnmpResult($datum);
                    break;
                case 2:
                    $resultsToBeInserted[$boom[1]]['index'] = $this->cleanSnmpResult($datum);
                    break;
                case 3:
                    $resultsToBeInserted[$boom[1]]['subnet'] = $this->maskToCidr($this->cleanSnmpResult($datum));
                    break;
                default:
                    continue;
            }
        }

        foreach ($resultsToBeInserted as $resultToBeInserted)
        {
            if (isset($interfacesIndexedByInterfaceID[$resultToBeInserted['index']]))
            {
                array_push($interfacesIndexedByInterfaceID[$resultToBeInserted['index']]['ip_addresses'],$resultToBeInserted['ip'] . "/" . $resultToBeInserted['subnet']);
            }
        }

        return $interfacesIndexedByInterfaceID;
    }

    /**
     * Remove the prefix from an SNMP result
     * @param string $result
     * @return string
     */
    protected function cleanSnmpResult(string $result):string
    {
        $boom = explode(":",$result,2);
        return trim($boom[1]);
    }

    /**
     * @param string $mac
     * @return bool
     */
    protected function validateMac(string $mac):bool
    {
        return preg_match('/^([A-F0-9]{2}:){5}[A-F0-9]{2}$/', $mac) == 1;
    }

    /**
     * @param string $mask
     * @return int
     */
    private function maskToCidr(string $mask):int
    {
        $long = ip2long($mask);
        $base = ip2long('255.255.255.255');
        return 32-log(($long ^ $base)+1,2);
    }
}