<?php

namespace SonarSoftware\Poller\DeviceMappers;

use Exception;
use SonarSoftware\Poller\Formatters\Formatter;
use SonarSoftware\Poller\Models\Device;
use SonarSoftware\Poller\Models\DeviceInterface;

class CambiumCanopyPMPAccessPointMapper extends BaseDeviceMapper implements DeviceMapperInterface
{
    public function mapDevice():Device
    {
        $this->setSystemMetadataOnDevice();
        $arrayOfDeviceInterfacesIndexedByInterfaceIndex = $this->getInterfacesWithStandardMibData(true, false);
        $arrayOfDeviceInterfacesIndexedByInterfaceIndex = $this->getConnectedSms($arrayOfDeviceInterfacesIndexedByInterfaceIndex);
        $this->device->setInterfaces($arrayOfDeviceInterfacesIndexedByInterfaceIndex);

        return $this->device;
    }

    /**
     * These SMs are always connected to the 2nd interface, which is the multipoint wireless interface
     * @param array $arrayOfDeviceInterfacesIndexedByInterfaceIndex
     * @return array|mixed
     */
    private function getConnectedSms(array $arrayOfDeviceInterfacesIndexedByInterfaceIndex):array
    {
        $keyToUse = 0;
        foreach ($arrayOfDeviceInterfacesIndexedByInterfaceIndex as $key => $deviceInterface)
        {
            if (strpos($deviceInterface->getDescription(),"MultiPoint") !== false)
            {
                $keyToUse = $key;
                break;
            }
        }

        try {
            $result = $this->snmp->walk("1.3.6.1.4.1.161.19.3.1.4.1.3");
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