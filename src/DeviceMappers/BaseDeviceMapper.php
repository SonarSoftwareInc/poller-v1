<?php

namespace SonarSoftware\Poller\DeviceMappers;

use SonarSoftware\Poller\Models\Device;

abstract class BaseDeviceMapper
{
    protected $snmp;
    protected $device;
    public function __construct(Device $device)
    {
        $this->snmp = $this->snmp = $device->getSnmpObject();
        $this->device = $device;
    }
}