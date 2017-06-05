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
     */
    public function __construct(Device $device)
    {
        parent::__construct($device);
    }

    /**
     * @return Device
     */
    public function mapDevice():Device
    {
        $deviceInterfaces = $this->getInterfacesWithStandardMibData();
        $this->device->setInterfaces($deviceInterfaces);

        return $this->device;
    }
}