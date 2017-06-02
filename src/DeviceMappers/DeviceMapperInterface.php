<?php

namespace SonarSoftware\Poller\DeviceMappers;

use SonarSoftware\Poller\Models\Device;
use SonarSoftware\Poller\Models\DeviceMappingResult;

interface DeviceMapperInterface
{
    public function mapDevice(Device $device):DeviceMappingResult;
}