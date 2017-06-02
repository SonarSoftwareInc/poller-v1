<?php

namespace SonarSoftware\Poller\Pollers;


class DeviceMappingPoller
{
    /**
     * Poll a list of devices to determine their connections
     * @param array $work
     * @return array
     */
    public function poll(array $work):array
    {
        $results = [];

        //First, we need to determine the type of each device.

        //Then we need to switch through and instantiate the correct mapper type for each, defaulting to GenericDeviceMapper

        //Take all the results and build them into something consistent for delivery back (write them all to file and then rebuild)

        return $results;
    }

    /**
     * Format a MAC in a variety of formats into a standardized, colon separated, upper case MAC
     * @param $mac
     * @return string
     */
    private function formatMac($mac)
    {
        $cleanMac = str_replace(" ","",$mac);
        $cleanMac = str_replace("-","",$cleanMac);
        $cleanMac = str_replace(":","",$cleanMac);
        $cleanMac = strtoupper($cleanMac);
        $macSplit = str_split($cleanMac,2);
        return implode(":",$macSplit);
    }
}