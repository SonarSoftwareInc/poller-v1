<?php
use SonarSoftware\Poller\DeviceMappers\BaseDeviceMapper;
use SonarSoftware\Poller\DeviceMappers\DeviceMapperInterface;

class EtherwanSwitch extends BaseDeviceMapper implements DeviceMapperInterface
{
    public function mapDevice(): \SonarSoftware\Poller\Models\Device
    {
        $this->setSystemMetadataOnDevice();
        $this->device->setInterfaces([]);

        return $this->device;
    }
}