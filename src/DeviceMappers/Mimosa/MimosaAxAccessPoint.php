<?php

namespace SonarSoftware\Poller\DeviceMappers\Mimosa;

use Exception;
use Psy\Formatter\Formatter;
use SonarSoftware\Poller\DeviceMappers\BaseDeviceMapper;
use SonarSoftware\Poller\DeviceMappers\DeviceMapperInterface;
use SonarSoftware\Poller\Models\Device;
use SonarSoftware\Poller\Models\DeviceInterface;

class MimosaAxAccessPoint extends BaseDeviceMapper implements DeviceMapperInterface
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
        $existingMacs = $arrayOfDeviceInterfacesIndexedByInterfaceIndex[0]->getConnectedMacs(DeviceInterface::LAYER1);
        $registeredStates = [];

        try {
            $result = $this->snmp->walk("1.3.6.1.4.1.43356.2.1.2.9.6.1.1.2");

            foreach ($result as $key => $datum)
            {
                $boom = explode(".",$key);
                try {
                    $mac = Formatter::formatMac($this->cleanSnmpResult($datum));
                    if ($this->validateMac($mac) && in_array($boom[count($boom)-1],$registeredStates))
                    {
                        array_push($existingMacs,$mac);
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

        $arrayOfDeviceInterfacesIndexedByInterfaceIndex[$keyToUse]->setConnectedMacs(array_unique($existingMacs),DeviceInterface::LAYER1);

        return $arrayOfDeviceInterfacesIndexedByInterfaceIndex;
    }
}