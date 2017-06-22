<?php

namespace SonarSoftware\Poller\DeviceMappers\Etherwan;

use SonarSoftware\Poller\DeviceMappers\BaseDeviceMapper;
use SonarSoftware\Poller\DeviceMappers\DeviceMapperInterface;
use SonarSoftware\Poller\Formatters\Formatter;
use SonarSoftware\Poller\Models\Device;
use SonarSoftware\Poller\Models\DeviceInterface;

class EtherwanSwitch extends BaseDeviceMapper implements DeviceMapperInterface
{
    public function mapDevice():Device
    {
        $this->setSystemMetadataOnDevice();
        $arrayOfDeviceInterfacesIndexedByInterfaceIndex = $this->getInterfacesWithStandardMibData(true, false);
        $arrayOfDeviceInterfacesIndexedByInterfaceIndex = $this->getBridgingTable($arrayOfDeviceInterfacesIndexedByInterfaceIndex);
        $this->device->setInterfaces($arrayOfDeviceInterfacesIndexedByInterfaceIndex);
        return $this->device;
    }

    /**
     * Get the bridging table
     * @param array $arrayOfDeviceInterfacesIndexedByInterfaceIndex
     * @return array
     */
    private function getBridgingTable(array $arrayOfDeviceInterfacesIndexedByInterfaceIndex):array
    {
        $switchingDatabasePortNumbers = $this->snmp->walk(".1.3.6.1.4.1.2736.1.1.11.1.1.4");
        $mapping = [];
        foreach ($switchingDatabasePortNumbers as $key => $switchingDatabasePortNumber)
        {
            $boom = explode(".",$key);
            $mapping[$boom[count($boom)-1]] = $this->cleanSnmpResult($switchingDatabasePortNumber);
        }

        $macAddresses = $this->snmp->walk("1.3.6.1.4.1.2736.1.1.11.1.1.2");
        foreach ($macAddresses as $key => $macAddress)
        {
            $macAddress = Formatter::formatMac($this->cleanSnmpResult($macAddress));
            $boom = explode(".",$key);
            if (isset($mapping[$boom[count($boom)-1]]))
            {
                try {
                    $existingMacs = $arrayOfDeviceInterfacesIndexedByInterfaceIndex[$mapping[$boom[count($boom)-1]]]->getConnectedMacs(DeviceInterface::LAYER2);
                    array_push($existingMacs,$macAddress);
                    $arrayOfDeviceInterfacesIndexedByInterfaceIndex[$mapping[$boom[count($boom)-1]]]->setConnectedMacs($existingMacs,DeviceInterface::LAYER2);
                }
                catch (Exception $e)
                {
                    //
                }
            }
        }

        return $arrayOfDeviceInterfacesIndexedByInterfaceIndex;
    }
}