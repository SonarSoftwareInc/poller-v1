<?php

namespace SonarSoftware\Poller\Models;

use InvalidArgumentException;

class Device
{
    protected $interfaces = [];
    protected $id;

    public function toArray():array
    {
        $structure = [
            'id' => $this->id,
            'interfaces' => [],
        ];

        foreach ($this->interfaces as $interface)
        {
            array_push($structure['interfaces'],$interface->toArray());
        }

        return $structure;
    }

    /**
     * @return array
     */
    public function getInterfaces():array
    {
        return $this->interfaces;
    }

    /**
     * Must be an array of SonarSoftware\Pollers\Models\DeviceInterface objects
     * @param array $interfaces
     */
    public function setInterfaces(array $interfaces)
    {
        $properClass = new DeviceInterface();
        foreach ($interfaces as $interface)
        {
            if (!$interface instanceof $properClass)
            {
                throw new InvalidArgumentException("You must submit an array of SonarSoftware\Pollers\Models\DeviceInterface objects.");
            }
        }
        $this->interfaces = $interfaces;
    }

    /**
     * @param int $id
     */
    public function setId(int $id)
    {
        $this->id = $id;
    }

    /**
     * @return int
     */
    public function getId():int
    {
        return $this->id;
    }
}