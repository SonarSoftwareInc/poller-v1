<?php

namespace SonarSoftware\Poller\DeviceMappers;

use Exception;
use SonarSoftware\Poller\Formatters\Formatter;
use SonarSoftware\Poller\Models\Device;
use SonarSoftware\Poller\Models\DeviceInterface;

class GenericDeviceMapper extends BaseDeviceMapper implements DeviceMapperInterface
{
    /**
     * GenericDeviceMapper constructor.
     * @param Device $device
     * @param bool $isNetworkHost
     */
    private $isNetworkHost = true;

    public function __construct(Device $device, $isNetworkHost = true)
    {
        parent::__construct($device);
        $this->isNetworkHost = $isNetworkHost;
    }

    /**
     * @return Device
     */
    public function mapDevice():Device
    {
        $this->setSystemMetadataOnDevice();
        if ($this->isNetworkHost === true) {
            $deviceInterfaces = $this->getInterfacesWithStandardMibData();
        } else {
            $deviceInterfaces = $this->getInterfacesWithStandardMibData(
                false,
                false,
                false,
                false,
                true,
                true
            );
        }
        $this->device->setInterfaces($deviceInterfaces);

        return $this->device;
    }
}
