<?php

namespace SonarSoftware\Poller\DeviceMappers\Ubiquiti;

use Exception;
use SonarSoftware\Poller\DeviceMappers\BaseDeviceMapper;
use SonarSoftware\Poller\DeviceMappers\DeviceMapperInterface;
use SonarSoftware\Poller\Formatters\Formatter;
use SonarSoftware\Poller\Models\Device;
use SonarSoftware\Poller\Models\DeviceInterface;

class UbiquitiAirFiber extends BaseDeviceMapper implements DeviceMapperInterface
{
    /**
     * @return Device
     */
    public function mapDevice(): Device
    {
        $this->setSystemMetadataOnDevice();
        $arrayOfDeviceInterfacesIndexedByInterfaceIndex = $this->getInterfacesWithStandardMibData(false, false, true, false, false);
        $arrayOfDeviceInterfacesIndexedByInterfaceIndex = $this->getRemoteBackhaulMac($arrayOfDeviceInterfacesIndexedByInterfaceIndex);
        $arrayOfDeviceInterfacesIndexedByInterfaceIndex = $this->getWirelessCapacity($arrayOfDeviceInterfacesIndexedByInterfaceIndex);
        $this->device->setInterfaces($arrayOfDeviceInterfacesIndexedByInterfaceIndex);

        return $this->device;
    }

    /**
     * Get the capacity of the wireless interface
     * @param array $arrayOfDeviceInterfacesIndexedByInterfaceIndex
     * @return array
     */
    private function getWirelessCapacity(array $arrayOfDeviceInterfacesIndexedByInterfaceIndex):array
    {
        $keyToUse = 1;
        foreach ($arrayOfDeviceInterfacesIndexedByInterfaceIndex as $key => $deviceInterface)
        {
            if (strpos($deviceInterface->getDescription(),"air0") !== false)
            {
                $keyToUse = $key;
                break;
            }
        }

        try {
            $capacity = $this->snmp->get(".1.3.6.1.4.1.41112.1.3.2.1.5");
            $arrayOfDeviceInterfacesIndexedByInterfaceIndex[$keyToUse]->setSpeedMbps($this->cleanSnmpResult($capacity)/1000**2);
        }
        catch (Exception $e)
        {
            //
        }

        return $arrayOfDeviceInterfacesIndexedByInterfaceIndex;
    }

    /**
     * @param array $arrayOfDeviceInterfacesIndexedByInterfaceIndex
     * @return array
     */
    private function getRemoteBackhaulMac(array $arrayOfDeviceInterfacesIndexedByInterfaceIndex):array
    {
        $keyToUse = 1;
        foreach ($arrayOfDeviceInterfacesIndexedByInterfaceIndex as $key => $deviceInterface)
        {
            if (strpos($deviceInterface->getDescription(),"air0") !== false)
            {
                $keyToUse = $key;
                break;
            }
        }

        $existingMacs = $arrayOfDeviceInterfacesIndexedByInterfaceIndex[$keyToUse]->getConnectedMacs(DeviceInterface::LAYER1);

        try {
            $result = $this->snmp->walk("1.3.6.1.4.1.41112.1.3.2.1.45");
            foreach ($result as $datum)
            {
                try {
                    $mac = Formatter::formatMac($this->cleanSnmpResult($datum));
                    if ($this->validateMac($mac))
                    {
                        array_push($existingMacs,$mac);
                    }
                }
                catch (Exception $e)
                {
                    //
                }
            }

        }
        catch (Exception $e)
        {
            //
        }

        $arrayOfDeviceInterfacesIndexedByInterfaceIndex[$keyToUse]->setConnectedMacs($existingMacs,DeviceInterface::LAYER1);

        return $arrayOfDeviceInterfacesIndexedByInterfaceIndex;
    }
}