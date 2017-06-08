<?php

namespace SonarSoftware\Poller\DeviceMappers;

use Exception;
use Leth\IPAddress\IP\Address;
use Leth\IPAddress\IP\NetworkAddress;
use Monolog\Logger;
use SonarSoftware\Poller\Formatters\Formatter;
use SonarSoftware\Poller\Models\Device;
use SonarSoftware\Poller\Models\DeviceInterface;
use SonarSoftware\Poller\Services\SonarLogger;

abstract class BaseDeviceMapper
{
    protected $snmp;
    protected $device;
    protected $log;

    public function __construct(Device $device)
    {
        $this->snmp = $this->snmp = $device->getSnmpObject();
        $this->device = $device;
        $this->log = new SonarLogger();
    }

    /**
     * Set system metadata on the device object
     */
    protected function setSystemMetadataOnDevice()
    {
        $metadata = [];
        try {
            $result = $this->snmp->walk("1.3.6.1.2.1.1");
            foreach ($result as $key => $datum)
            {
                switch (ltrim($key,"."))
                {
                    case "1.3.6.1.2.1.1.1.0":
                        //Descr
                        $metadata['description'] = $this->cleanSnmpResult($datum);
                        break;
                    case "1.3.6.1.2.1.1.3.0":
                        //Uptime
                        $metadata['uptime'] = $this->cleanSnmpResult($datum);
                        break;
                    case "1.3.6.1.2.1.1.4.0":
                        //Contact
                        $metadata['contact'] = $this->cleanSnmpResult($datum);
                        break;
                    case "1.3.6.1.2.1.1.5.0":
                        //Name
                        $metadata['name'] = $this->cleanSnmpResult($datum);
                        break;
                    case "1.3.6.1.2.1.1.6.0":
                        //Location
                        $metadata['location'] = $this->cleanSnmpResult($datum);
                        break;
                    default:
                        break;
                }
            }
        }
        catch (Exception $e)
        {
            //
        }
        $this->device->setMetadata($metadata);
    }

    /**
     * @param bool $getArp - Read the ARP table
     * @param bool $getBridgingTable - Read the bridging table
     * @param bool $getIpv4 - Get IPv4 addresses
     * @param bool $getIpv6 - Get IPv6 addresses
     * @param bool $getSpeeds - Get interface speeds
     * @return array
     */
    protected function getInterfacesWithStandardMibData(
        $getArp = true,
        $getBridgingTable = true,
        $getIpv4 = true,
        $getIpv6 = true,
        $getSpeeds = true
    ):array
    {
        $interfacesIndexedByInterfaceID = $this->buildInitialInterfaceArray();
        try {
            $interfacesIndexedByInterfaceID = $this->getInterfaceMacAddresses($interfacesIndexedByInterfaceID);
        }
        catch (Exception $e)
        {
            if (getenv("DEBUG") == "true")
            {
                $this->log->log("Failed to get MAC addresses for {$this->device->getSnmpObject()->info['hostname']} - {$e->getMessage()}",Logger::ERROR);
            }
        }
        try {
            $interfacesIndexedByInterfaceID = $this->getInterfaceStatus($interfacesIndexedByInterfaceID);
        }
        catch (Exception $e)
        {
            if (getenv("DEBUG") == "true")
            {
                $this->log->log("Failed to get interface status for {$this->device->getSnmpObject()->info['hostname']} - {$e->getMessage()}",Logger::ERROR);
            }
        }
        if ($getArp === true)
        {
            try {
                $interfacesIndexedByInterfaceID = $this->getArp($interfacesIndexedByInterfaceID);
            }
            catch (Exception $e)
            {
                if (getenv("DEBUG") == "true")
                {
                    $this->log->log("Failed to get ARP for {$this->device->getSnmpObject()->info['hostname']} - {$e->getMessage()}",Logger::ERROR);
                }
            }
        }

        if ($getIpv4 === true)
        {
            try {
                $interfacesIndexedByInterfaceID = $this->getIpv4Addresses($interfacesIndexedByInterfaceID);
            }
            catch (Exception $e)
            {
                if (getenv("DEBUG") == "true")
                {
                    $this->log->log("Failed to get IPv4 addresses for {$this->device->getSnmpObject()->info['hostname']} - {$e->getMessage()}",Logger::ERROR);
                }
            }
        }

        if ($getIpv6 === true)
        {
            try {
                $interfacesIndexedByInterfaceID = $this->getIpv6Addresses($interfacesIndexedByInterfaceID);
            }
            catch (Exception $e)
            {
                if (getenv("DEBUG") == "true")
                {
                    $this->log->log("Failed to get IPv6 addresses for {$this->device->getSnmpObject()->info['hostname']} - {$e->getMessage()}",Logger::ERROR);
                }
            }
        }

        if ($getBridgingTable === true)
        {
            try {
                $interfacesIndexedByInterfaceID = $this->getBridgingTable($interfacesIndexedByInterfaceID);
            }
            catch (Exception $e)
            {
                if (getenv("DEBUG") == "true")
                {
                    $this->log->log("Failed to get bridging table for {$this->device->getSnmpObject()->info['hostname']} - {$e->getMessage()}",Logger::ERROR);
                }
            }
        }

        if ($getSpeeds === true)
        {
            try {
                $interfacesIndexedByInterfaceID = $this->getInterfaceSpeeds($interfacesIndexedByInterfaceID);
            }
            catch (Exception $e)
            {
                if (getenv("DEBUG") == "true")
                {
                    $this->log->log("Failed to get speeds for {$this->device->getSnmpObject()->info['hostname']} - {$e->getMessage()}",Logger::ERROR);
                }
            }
        }

        return $this->buildDeviceInterfaceObjectsFromResults($interfacesIndexedByInterfaceID);
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
     * @return array
     */
    private function buildInitialInterfaceArray():array
    {
        $result = $this->snmp->walk("1.3.6.1.2.1.2.2.1.2");
        $interfaces = [];
        foreach ($result as $key => $datum)
        {
            $boom = explode(".",$key);
            $interfaces[$boom[count($boom)-1]] = [
                'name' => $this->cleanSnmpResult($datum),
                'status' => null,
                'connected_l2' => [],
                'connected_l3' => [],
                'ip_addresses' => [],
                'mac_address' => null,
                'speed_mbps' => null,
            ];
        }
        return $interfaces;
    }

    /**
     * @param array $interfacesIndexedByInterfaceID
     * @return array
     */
    private function getInterfaceSpeeds(array $interfacesIndexedByInterfaceID):array
    {
        $result = $this->snmp->walk("1.3.6.1.2.1.2.2.1.5");
        foreach ($result as $key => $datum)
        {
            $key = ltrim($key,".");
            $boom = explode(".", $key);
            if (isset($interfacesIndexedByInterfaceID[$boom[count($boom)-1]]))
            {
                $speed = $this->cleanSnmpResult($datum);
                if (is_numeric($speed) && $speed > 0)
                {
                    $interfacesIndexedByInterfaceID[$boom[count($boom)-1]]['speed_mbps'] = ceil($speed/1000**2);
                }
            }
        }

        return $interfacesIndexedByInterfaceID;
    }

    /**
     * @param array $interfacesIndexedByInterfaceID
     * @return array
     */
    private function getInterfaceMacAddresses(array $interfacesIndexedByInterfaceID):array
    {
        $result = $this->snmp->walk("1.3.6.1.2.1.2.2.1.6");
        foreach ($result as $key => $datum)
        {
            $key = ltrim($key,".");
            $boom = explode(".", $key);
            if (isset($interfacesIndexedByInterfaceID[$boom[count($boom)-1]]))
            {
                try {
                    $mac = Formatter::formatMac($this->cleanSnmpResult($datum));
                    if ($this->validateMac($mac))
                    {
                        $interfacesIndexedByInterfaceID[$boom[count($boom)-1]]['mac_address'] = $mac;
                    }
                }
                catch (Exception $e)
                {
                    continue;
                }
            }
        }

        return $interfacesIndexedByInterfaceID;
    }

    /**
     * @param array $interfacesIndexedByInterfaceID
     * @return array
     */
    private function getArp(array $interfacesIndexedByInterfaceID):array
    {
        $result = $this->snmp->walk("1.3.6.1.2.1.4.22.1.2");
        foreach ($result as $key => $datum)
        {
            $key = ltrim($key,".");
            $boom = explode(".", $key);
            if (isset($interfacesIndexedByInterfaceID[$boom[count($boom)-5]]))
            {
                try {
                    $mac = Formatter::formatMac($this->cleanSnmpResult($datum));
                    if ($this->validateMac($mac))
                    {
                        array_push($interfacesIndexedByInterfaceID[$boom[count($boom)-5]]['connected_l3'],$mac);
                    }
                }
                catch (Exception $e)
                {
                    continue;
                }
            }
        }

        return $interfacesIndexedByInterfaceID;
    }

    /**
     * @param array $interfacesIndexedByInterfaceID
     * @return array
     */
    private function getBridgingTable(array $interfacesIndexedByInterfaceID):array
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
            try {
                $macAddress = Formatter::formatMac($datum);
                if ($this->validateMac($macAddress))
                {
                    $key = ltrim($key,".");
                    $boom = explode(".",$key,12);
                    if (isset($mappings[$boom[11]]))
                    {
                        if (isset($interfacesIndexedByInterfaceID[$mappings[$boom[11]]]))
                        {
                            array_push($interfacesIndexedByInterfaceID[$mappings[$boom[11]]]['connected_l2'],$macAddress);
                        }
                    }
                }
            }
            catch (Exception $e)
            {
                continue;
            }
        }

        return $interfacesIndexedByInterfaceID;
    }

    /**
     * @param array $interfacesIndexedByInterfaceID
     * @return array
     */
    private function getInterfaceStatus(array $interfacesIndexedByInterfaceID):array
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
    private function getIpv4Addresses(array $interfacesIndexedByInterfaceID):array
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
     * @param array $interfacesIndexedByInterfaceID
     * @return array
     */
    private function getIpv6Addresses(array $interfacesIndexedByInterfaceID):array
    {
        $result = $this->snmp->walk("1.3.6.1.2.1.55.1.8");
        $resultsToBeInserted = [];

        foreach ($result as $key => $datum)
        {
            $key = ltrim($key,".");
            $key = str_replace("1.3.6.1.2.1.55.1.8.1.","",$key);
            $boom = explode(".",$key,3);

            if (!isset($resultsToBeInserted[$boom[1]]))
            {
                $resultsToBeInserted[$boom[1]] = [
                    'ip' => null,
                    'index' => $boom[1],
                    'subnet' => null,
                ];
            }

            switch ($boom[0])
            {
                //If $boom[0] is 1, it's the IP. If 2, it's the prefix.
                case 1:
                    $address = Address::factory($this->cleanSnmpResult($datum));
                    $resultsToBeInserted[$boom[1]]['ip'] = $address->__toString();
                    break;
                case 2:
                    $resultsToBeInserted[$boom[1]]['subnet'] = preg_replace("/[^0-9]/", "", $this->cleanSnmpResult($datum));
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
     * @param string $mask
     * @return int
     */
    private function maskToCidr(string $mask):int
    {
        $long = ip2long($mask);
        $base = ip2long('255.255.255.255');
        return 32-log(($long ^ $base)+1,2);
    }

    /**
     * @param array $interfacesIndexedByInterfaceID
     * @return array of DeviceInterface
     */
    private function buildDeviceInterfaceObjectsFromResults(array $interfacesIndexedByInterfaceID):array
    {
        $arrayOfDeviceInterfacesIndexedByInterfaceIndex = [];
        foreach ($interfacesIndexedByInterfaceID as $interfaceIndex => $interface)
        {
            $deviceInterface = new DeviceInterface();
            $deviceInterface->setUp($interface['status']);
            $deviceInterface->setDescription($interface['name']);
            $deviceInterface->setMetadata([
                'ip_addresses' => $interface['ip_addresses'],
            ]);
            if ($interface['mac_address'] !== null)
            {
                if ($this->validateMac($interface['mac_address']))
                {
                    $deviceInterface->setMacAddress($interface['mac_address']);
                }
            }

            $deviceInterface->setConnectedMacs(array_unique($interface['connected_l2']),DeviceInterface::LAYER2);
            $deviceInterface->setConnectedMacs(array_unique($interface['connected_l3']),DeviceInterface::LAYER3);
            $deviceInterface->setSpeedMbps($interfaceIndex['speed_mbps']);
            $arrayOfDeviceInterfacesIndexedByInterfaceIndex[$interfaceIndex] = $deviceInterface;
        }
        return $arrayOfDeviceInterfacesIndexedByInterfaceIndex;
    }
}