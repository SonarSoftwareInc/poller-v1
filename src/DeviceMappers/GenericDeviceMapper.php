<?php

namespace SonarSoftware\Poller\DeviceMappers;

use SonarSoftware\Poller\Models\Device;
use SonarSoftware\Poller\Models\DeviceMappingResult;

class GenericDeviceMapper implements DeviceMapperInterface
{
    public function mapDevice(Device $device):DeviceMappingResult
    {

    }

    private function checkForArp(Device $device):Device
    {

    }

    private function checkBridgingTable(Device $device):Device
    {

    }

    private function getInterfaceNamesIndexedByInterfaceID(Device $device):array
    {

    }

    private function getInterfaceStatusIndexedByInterfaceID(Device $device):array
    {

    }
}