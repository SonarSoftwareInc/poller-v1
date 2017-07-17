<?php

namespace SonarSoftware\Poller\Models;

use InvalidArgumentException;
use SonarSoftware\Poller\Formatters\Formatter;

class DeviceInterface
{
    private $up;
    private $metadata = [];
    private $macAddress;
    private $connectedMacsLayer1 = [];
    private $connectedMacsLayer2 = [];
    private $connectedMacsLayer3 = [];
    private $description;
    private $speedMbpsIn = null;
    private $speedMbpsOut = null;
    private $type = null;

    const LAYER1 = "l1";
    const LAYER2 = "l2";
    const LAYER3 = "l3";

    const IN = "in";
    const OUT = "out";

    /**
     * Convert this interface to an array
     * @return array
     */
    public function toArray():array
    {
        return [
            'up' => (bool)$this->up,
            'description' => $this->description,
            'metadata' => $this->metadata,
            'mac_address' => $this->macAddress,
            'connected_l1' => $this->connectedMacsLayer1,
            'connected_l2' => $this->connectedMacsLayer2,
            'connected_l3' => $this->connectedMacsLayer3,
            'speed_mbps' => $this->speedMbpsIn,
            'speed_mbps_in' => $this->speedMbpsIn,
            'speed_mbps_out' => $this->speedMbpsOut,
            'type' => $this->type,
        ];
    }

    /**
     * Set the interface type
     * @param string $type
     */
    public function setType(string $type)
    {
        $this->type = $type;
    }

    /**
     * @return string
     */
    public function getType():string
    {
        return $this->type;
    }

    /**
     * Set the name/description of the interface
     * @param string $description
     */
    public function setDescription(string $description)
    {
        $this->description = $description;
    }

    /**
     * Get the name/description of the interface
     * @return mixed
     */
    public function getDescription():string
    {
        return $this->description;
    }

    /**
     * Set the speed of the interface
     * @param int $speedMbpsIn
     * @param int|null $speedMbpsOut
     */
    public function setSpeedMbps(int $speedMbpsIn, int $speedMbpsOut = null)
    {
        if ($speedMbpsIn < 0)
        {
            throw new InvalidArgumentException("Speed cannot be less than zero.");
        }
        if ($speedMbpsOut && $speedMbpsOut < 0)
        {
            throw new InvalidArgumentException("Speed (out) cannot be less than zero.");
        }
        $this->speedMbpsIn = $speedMbpsIn;
        if ($speedMbpsOut)
        {
            $this->speedMbpsOut = $speedMbpsOut;
        }
    }

    /**
     * Get the speed of the interface
     * @param null $direction
     * @return int|mixed
     */
    public function getSpeedMbps($direction = null):int
    {
        if ($direction === null)
        {
            $direction = $this::IN;
        }

        switch ($direction)
        {
            case $this::IN:
                return (int)$this->speedMbpsIn;
                break;
            case $this::OUT:
                return (int)$this->speedMbpsOut;
                break;
            default:
                throw new InvalidArgumentException("Direction must be one of 'in' or 'out'.");
                break;
        }
    }

    /**
     * Set the status of the port (if it's up, this is true)
     * @param bool $status
     */
    public function setUp(bool $status)
    {
        $this->up = $status;
    }

    /**
     * Get up, get up, to get down
     * @return mixed
     */
    public function getUp():bool
    {
        return $this->up;
    }


    /**
     * The format of metadata provided can be anything, it's just an array of data to be displayed in the application.
     * @param array $metadata
     */
    public function setMetadata(array $metadata)
    {
        $this->metadata = $metadata;
    }

    public function getMetadata():array
    {
        return $this->metadata;
    }

    /**
     * Set the interface MAC
     * @param $macAddress
     */
    public function setMacAddress($macAddress)
    {
        $macAddress = Formatter::formatMac($macAddress);
        if ($this->validateMac($macAddress) === false)
        {
            throw new InvalidArgumentException($macAddress . " is not a valid MAC address.");
        }
        $this->macAddress = $macAddress;
    }

    /**
     * @return string
     */
    public function getMacAddress():string
    {
        return $this->macAddress;
    }

    /**
     * @param array $connectedMacs
     * @param string $layer - One of $this::LAYER1, $this::LAYER2, $this::LAYER3
     */
    public function setConnectedMacs(array $connectedMacs, string $layer)
    {
        if (!in_array($layer,[$this::LAYER1, $this::LAYER2, $this::LAYER3]))
        {
            throw new InvalidArgumentException("Layer must be one of the layer constants.");
        }

        $cleanedMacs = [];
        foreach ($connectedMacs as $connectedMac)
        {
            $connectedMac = Formatter::formatMac($connectedMac);
            if ($this->validateMac($connectedMac) === false)
            {
                throw new InvalidArgumentException($connectedMac . " is not a valid MAC address.");
            }
            array_push($cleanedMacs, $connectedMac);
        }

        switch ($layer)
        {
            case $this::LAYER1:
                $this->connectedMacsLayer1 = $cleanedMacs;
                break;
            case $this::LAYER2:
                $this->connectedMacsLayer2 = $cleanedMacs;
                break;
            case $this::LAYER3:
                $this->connectedMacsLayer3 = $cleanedMacs;
                break;
        }
    }

    /**
     * Returns an array of connected MAC addresses
     * @param string $layer
     * @return array
     */
    public function getConnectedMacs(string $layer):array
    {
        if (!in_array($layer,[$this::LAYER1, $this::LAYER2, $this::LAYER3]))
        {
            throw new InvalidArgumentException("Layer must be one of the layer constants.");
        }
        switch ($layer)
        {
            case $this::LAYER1:
                return $this->connectedMacsLayer1;
                break;
            case $this::LAYER2:
                return $this->connectedMacsLayer2;
                break;
            case $this::LAYER3:
                return $this->connectedMacsLayer3;
                break;
        }
    }

    /**
     * @param $mac
     * @return bool
     */
    private function validateMac(string $mac):bool
    {
        return preg_match('/^([A-F0-9]{2}:){5}[A-F0-9]{2}$/', $mac) == 1;
    }
}