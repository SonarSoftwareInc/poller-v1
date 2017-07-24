<?php

namespace SonarSoftware\Poller\DeviceMappers\Ubiquiti;


use Exception;
use SonarSoftware\Poller\Models\Device;

class UbiquitiIdentifier
{
    public function __construct(Device $device)
    {
        $snmp = $device->getSnmpObject();
        try {
            $result = $snmp->walk("1.3.6.1.4.1.41112.1.3.1.1.2");
            if (is_array($result) && count($result) > 0)
            {
                return new UbiquitiAirFiber($device);
            }
        }
        catch (Exception $e)
        {
            return new UbiquitiAirMaxAccessPointMapper($device);
        }
        return new UbiquitiAirMaxAccessPointMapper($device);
    }
}