<?php

namespace SonarSoftware\Poller\DeviceMappers;

use Exception;
use SonarSoftware\Poller\Formatters\Formatter;
use SonarSoftware\Poller\Models\Device;

class UbiquitiAirMaxAccessPointMapper extends BaseDeviceMapper implements DeviceMapperInterface
{
    public function mapDevice(): Device
    {
        $this->setSystemMetadataOnDevice();
        $arrayOfDeviceInterfacesIndexedByInterfaceIndex = $this->getInterfacesWithStandardMibData(true, false);
        $arrayOfDeviceInterfacesIndexedByInterfaceIndex = $this->getConnectedRadios($arrayOfDeviceInterfacesIndexedByInterfaceIndex);
        $this->device->setInterfaces($arrayOfDeviceInterfacesIndexedByInterfaceIndex);

        return $this->device;
    }

    /**
     * @param array $arrayOfDeviceInterfacesIndexedByInterfaceIndex
     * @return array|mixed
     */
    private function getConnectedRadios(array $arrayOfDeviceInterfacesIndexedByInterfaceIndex):array
    {
        $keyToUse = 0;
        foreach ($arrayOfDeviceInterfacesIndexedByInterfaceIndex as $key => $deviceInterface)
        {
            if (strpos($deviceInterface->getDescription(),"wifi") !== false)
            {
                $keyToUse = $key;
                break;
            }
        }

        try {
            $result = $this->snmp->walk("1.3.6.1.4.1.41112.1.4.7.1.1");
            foreach ($result as $key => $datum)
            {
                $mac = Formatter::formatMac($this->cleanSnmpResult($datum));
                if ($this->validateMac($mac))
                {
                    array_push($arrayOfDeviceInterfacesIndexedByInterfaceIndex[$keyToUse]['connected_l2'],$mac);
                }
            }
        }
        catch (Exception $e)
        {
            //
        }
        return $arrayOfDeviceInterfacesIndexedByInterfaceIndex;
    }
}