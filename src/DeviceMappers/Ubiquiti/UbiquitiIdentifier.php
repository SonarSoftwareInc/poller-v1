<?php

namespace SonarSoftware\Poller\DeviceMappers\Ubiquiti;


use Exception;
use SonarSoftware\Poller\Models\Device;

class UbiquitiIdentifier
{
    private $mapper = null;
    public function __construct(Device $device)
    {
        $snmp = $device->getSnmpObject();
        try {
            $result = $snmp->walk("1.3.6.1.4.1.41112.1.3.1.1.2");
            if (is_array($result) && count($result) > 0)
            {
                $this->mapper = new UbiquitiAirFiber($device);
            }
        }
        catch (Exception $e)
        {
            $this->mapper =  new UbiquitiAirMaxAccessPointMapper($device);
        }
        $this->mapper =  new UbiquitiAirMaxAccessPointMapper($device);
    }

    /**
     * @return null|UbiquitiAirMaxAccessPointMapper
     */
    public function getMapper()
    {
        return $this->mapper;
    }
}