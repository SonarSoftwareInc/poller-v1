<?php

namespace SonarSoftware\Poller\DeviceMappers;

use SonarSoftware\Poller\Models\Device;

class GenericDeviceMapper extends BaseDeviceMapper implements DeviceMapperInterface
{
    public function __construct(Device $device)
    {
        parent::__construct($device);
    }

    public function mapDevice():Device
    {
        return $this->device;
    }

    private function checkForArp():Device
    {

    }

    private function checkBridgingTable():Device
    {

    }

    private function getInterfaceNamesIndexedByInterfaceID():array
    {
    }

    private function getInterfaceStatusIndexedByInterfaceID():array
    {

    }
}