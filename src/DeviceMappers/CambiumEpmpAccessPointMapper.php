<?php

namespace SonarSoftware\Poller\DeviceMappers;

use Exception;
use SonarSoftware\Poller\Formatters\Formatter;
use SonarSoftware\Poller\Models\Device;

class CambiumEpmpAccessPointMapper extends BaseDeviceMapper implements DeviceMapperInterface
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
     * This gets attached to the WLAN interface, which is always the second interface.
     * @param array $arrayOfDeviceInterfacesIndexedByInterfaceIndex
     * @return array|mixed
     */
    private function getConnectedRadios(array $arrayOfDeviceInterfacesIndexedByInterfaceIndex):array
    {
        try {
            $result = $this->snmp->walk("1.3.6.1.4.1.17713.21.1.2.30.1.1");
            foreach ($result as $key => $datum)
            {
                $mac = Formatter::formatMac($this->cleanSnmpResult($datum));
                if ($this->validateMac($mac))
                {
                    array_push($arrayOfDeviceInterfacesIndexedByInterfaceIndex[count($arrayOfDeviceInterfacesIndexedByInterfaceIndex)-1]['connected_l2'],$mac);
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