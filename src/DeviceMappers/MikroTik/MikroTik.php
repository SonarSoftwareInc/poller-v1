<?php

namespace SonarSoftware\Poller\DeviceMappers\MikroTik;

use Exception;
use SonarSoftware\Poller\DeviceMappers\BaseDeviceMapper;
use SonarSoftware\Poller\DeviceMappers\DeviceMapperInterface;
use SonarSoftware\Poller\Formatters\Formatter;
use SonarSoftware\Poller\Models\Device;
use SonarSoftware\Poller\Models\DeviceInterface;

class MikroTik extends BaseDeviceMapper implements DeviceMapperInterface
{
    public function mapDevice():Device
    {
        $this->setSystemMetadataOnDevice();
        $arrayOfDeviceInterfacesIndexedByInterfaceIndex = $this->getInterfacesWithStandardMibData();
        $arrayOfDeviceInterfacesIndexedByInterfaceIndex = $this->getWirelessClients($arrayOfDeviceInterfacesIndexedByInterfaceIndex);
        $this->device->setInterfaces($arrayOfDeviceInterfacesIndexedByInterfaceIndex);

        return $this->device;
    }

    /**
     * @param array $arrayOfDeviceInterfacesIndexedByInterfaceIndex
     * @return array|mixed
     */
    private function getWirelessClients(array $arrayOfDeviceInterfacesIndexedByInterfaceIndex):array
    {
        try {
            $result = $this->snmp->walk("1.3.6.1.4.1.14988.1.1.1.2.1.1");
            foreach ($result as $key => $datum)
            {
                $boom = explode(".",$key);
                $interfaceIndex = $boom[count($boom)-1];
                try {
                    $mac = Formatter::formatMac($this->cleanSnmpResult($datum));
                    if ($this->validateMac($mac))
                    {
                        if(isset($arrayOfDeviceInterfacesIndexedByInterfaceIndex[$interfaceIndex]))
                        {
                            $existingMacs = $arrayOfDeviceInterfacesIndexedByInterfaceIndex[$interfaceIndex]->getConnectedMacs(DeviceInterface::LAYER1);
                            array_push($existingMacs,$mac);
                            $arrayOfDeviceInterfacesIndexedByInterfaceIndex[$interfaceIndex]->setConnectedMacs($existingMacs,DeviceInterface::LAYER1);
                        }
                    }
                }
                catch (Exception $e)
                {
                    continue;
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