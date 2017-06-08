<?php

namespace SonarSoftware\Poller\DeviceMappers;

use SonarSoftware\Poller\Models\Device;

interface DeviceMapperInterface
{
    public function mapDevice():Device;
}