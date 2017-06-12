<?php
use SonarSoftware\Poller\DeviceMappers\BaseDeviceMapper;
use SonarSoftware\Poller\DeviceMappers\DeviceMapperInterface;
use SonarSoftware\Poller\Formatters\Formatter;
use SonarSoftware\Poller\Models\DeviceInterface;
use SonarSoftware\Poller\Models\Device;

/**
 * Created by PhpStorm.
 * User: Simon
 * Date: 6/12/2017
 * Time: 9:23 AM
 */
class MimosaBxBackhaul extends BaseDeviceMapper implements DeviceMapperInterface
{
    /**
     * @return Device
     */
    public function mapDevice():Device
    {
        $this->setSystemMetadataOnDevice();
        $arrayOfDeviceInterfacesIndexedByInterfaceIndex = $this->getInterfacesWithStandardMibData(false, false, true, false, true);
        $arrayOfDeviceInterfacesIndexedByInterfaceIndex = $this->getRemoteBackhaulMac($arrayOfDeviceInterfacesIndexedByInterfaceIndex);
        $this->device->setInterfaces($arrayOfDeviceInterfacesIndexedByInterfaceIndex);

        return $this->device;
    }

    /**
     * @param array $arrayOfDeviceInterfacesIndexedByInterfaceIndex
     * @return array
     */
    private function getRemoteBackhaulMac(array $arrayOfDeviceInterfacesIndexedByInterfaceIndex):array
    {
        $keyToUse = 5;
        foreach ($arrayOfDeviceInterfacesIndexedByInterfaceIndex as $key => $deviceInterface)
        {
            if (strpos($deviceInterface->getDescription(),"wlan") !== false)
            {
                $keyToUse = $key;
                break;
            }
        }
        $existingMacs = $arrayOfDeviceInterfacesIndexedByInterfaceIndex[$keyToUse]->getConnectedMacs(DeviceInterface::LAYER1);

        try {
            //This is a pretty lame workaround, but it's the only way we can try to get the far end until firmware upgrades are provided.
            $result = $this->snmp->walk("1.3.6.1.2.1.4.22.1.2.5");
            foreach ($result as $datum)
            {
                $mac = Formatter::formatMac($this->cleanSnmpResult($datum));
                if ($this->validateMac($mac))
                {
                    array_push($existingMacs,$mac);
                    break;
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