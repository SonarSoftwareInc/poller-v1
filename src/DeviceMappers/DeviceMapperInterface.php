<?php

namespace SonarSoftware\Poller\DeviceMappers;

use SonarSoftware\Poller\Models\Device;

interface DeviceMapperInterface
{
    public function __construct(Device $device);
    public function mapDevice():Device;
}