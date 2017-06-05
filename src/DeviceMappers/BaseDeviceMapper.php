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
}