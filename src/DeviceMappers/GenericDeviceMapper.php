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
        $interfacesIndexedByInterfaceID = $this->buildInitialInterfaceArray();

        try {
            $interfacesIndexedByInterfaceID = $this->getInterfaceMacAddresses($interfacesIndexedByInterfaceID);
        }
        catch (Exception $e)
        {
            //
        }
        try {
            $interfacesIndexedByInterfaceID = $this->getInterfaceStatus($interfacesIndexedByInterfaceID);
        }
        catch (Exception $e)
        {
            //
        }
        try {
            $interfacesIndexedByInterfaceID = $this->getArp($interfacesIndexedByInterfaceID);
        }
        catch (Exception $e)
        {
            //
        }
        try {
            $interfacesIndexedByInterfaceID = $this->getBridgingTable($interfacesIndexedByInterfaceID);
        }
        catch (Exception $e)
        {
            //
        }
        try {
            $interfacesIndexedByInterfaceID = $this->getIpv4Addresses($interfacesIndexedByInterfaceID);
        }
        catch (Exception $e)
        {
            //
        }

        $deviceInterfaces = [];
        foreach ($interfacesIndexedByInterfaceID as $interface)
        {
            $deviceInterface = new DeviceInterface();
            $deviceInterface->setUp($interface['status']);
            $deviceInterface->setDescription($interface['name']);
            $deviceInterface->setMetadata([
                'ip_addresses' => $interface['ip_addresses'],
            ]);
            if ($this->validateMac($interface['mac_address']))
            {
                $deviceInterface->setMacAddress($interface['mac_address']);
            }
            $deviceInterface->setConnectedMacs(array_unique($interface['connected']));
            array_push($deviceInterfaces, $deviceInterface);
        }

        $this->device->setInterfaces($deviceInterfaces);

        return $this->device;
    }
}