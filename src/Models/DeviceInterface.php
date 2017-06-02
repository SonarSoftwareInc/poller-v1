<?php

namespace SonarSoftware\Poller\Models;

use InvalidArgumentException;

class DeviceInterface
{
    protected $up;
    protected $metadata = [];
    protected $macAddress;
    protected $connectedMacs = [];

    /**
     * Convert this interface to an array
     * @return array
     */
    public function asArray():array
    {
        return [
            'up' => (bool)$this->up,
            'metadata' => $this->metadata,
            'mac_address' => $this->macAddress,
            'connected' => $this->connectedMacs,
        ];
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
        $macAddress = $this->formatMac($macAddress);
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
     */
    public function setConnectedMacs(array $connectedMacs)
    {
        $cleanedMacs = [];
        foreach ($connectedMacs as $connectedMac)
        {
            $connectedMac = $this->formatMac($connectedMac);
            if ($this->validateMac($connectedMac) === false)
            {
                throw new InvalidArgumentException($connectedMac . " is not a valid MAC address.");
            }
            array_push($cleanedMacs, $connectedMac);
        }
        $this->connectedMacs = $cleanedMacs;
    }

    /**
     * Returns an array of connected MAC addresses
     * @return array
     */
    public function getConnectedMacs():array
    {
        return $this->connectedMacs;
    }

    /**
     * @param $mac
     * @return bool
     */
    private function validateMac(string $mac):bool
    {
        return preg_match('/^([A-F0-9]{2}:){5}[A-F0-9]{2}$/', $mac) == 1;
    }

    /**
     * Format a randomly formatted MAC into a well formatted MAC
     * @param $mac
     * @return string
     */
    private function formatMac(string $mac):string
    {
        $cleanMac = str_replace(" ","",$mac);
        $cleanMac = str_replace("-","",$cleanMac);
        $cleanMac = str_replace(":","",$cleanMac);
        $cleanMac = strtoupper($cleanMac);
        $macSplit = str_split($cleanMac,2);
        return implode(":",$macSplit);
    }
}