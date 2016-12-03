<?php

class SnmpDetails
{
    /**
     * SnmpDetails constructor.
     * @param array $data
     */
    public function __construct(array $data)
    {
        $this->setData($data);
    }

    private $host;
    public function getHost():string
    {
        return $this->host;
    }

    /**
     * @param string $host
     * @throws InvalidArgumentException
     */
    public function setHost(string $host)
    {
        if (!filter_var($host, FILTER_VALIDATE_IP))
        {
            throw new InvalidArgumentException($host . " is not a valid IPv4/IPv6 address.");
        }
        $this->host = $host;
    }

    private $snmpVersion;
    public function getSnmpVersion():int
    {
        return $this->snmpVersion;
    }

    /**
     * @param int $version
     * @throws InvalidArgumentException
     */
    public function setSnmpVersion(int $version)
    {
        if (!in_array($version,[SNMP::VERSION_1, SNMP::VERSION_2C, SNMP::VERSION_3]))
        {
            throw new InvalidArgumentException("SNMP version must be one of " . implode(", ",[SNMP::VERSION_1, SNMP::VERSION_2C, SNMP::VERSION_3]));
        }
        $this->snmpVersion = $version;
    }

    /**
     * Construct the object from input data
     * @param array $data
     */
    private function setData(array $data)
    {
        $this->checkKeyExistence($data);
        //Valid IPv4/IPv6 address
        $this->setHost($data['host']);
        //One of SNMP::VERSION_1, SNMP::VERSION_2C, SNMP::VERSION_3
        $this->setSnmpVersion($data['snmpVersion']);
    }

    /**
     * Check the input array for keys
     * @param array $data
     */
    private function checkKeyExistence(array $data)
    {
        if (!array_key_exists("host",$data))
        {
            throw new InvalidArgumentException("'host' key missing.");
        }

        if (!array_key_exists("snmpVersion",$data))
        {
            throw new InvalidArgumentException("'snmpVersion' key missing.");
        }
    }
}