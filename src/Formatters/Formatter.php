<?php

namespace SonarSoftware\Poller\Formatters;

use stdClass;

class Formatter
{
    //Types of hosts
    const NETWORK_SITES = "network_sites";
    const ACCOUNTS = "accounts";

    /**
     * Format the work from Sonar for SNMP queries into a usable format
     * @param stdClass $work
     * @return array
     */
    public function formatSnmpWork(stdClass $work):array
    {
        $formattedWork = [
            'templates' => [],
            'hosts' => [],
        ];
        foreach ($work->data->monitoring_templates as $templateID => $template)
        {
            if (count($template->oids) === 0 && $template->collect_interface_statistics == false)
            {
                continue;
            }
            $formattedWork['templates'][$templateID] = $template;
        }

        foreach ($work->data->hosts as $hostID => $hostDetails)
        {
            if (isset($formattedWork['templates'][$hostDetails->monitoring_template_id]))
            {
                $formattedWork['hosts'][$hostID] = [
                    'ip' => $hostDetails->ip,
                    'template_id' => $hostDetails->monitoring_template_id,
                    'snmp_overrides' => $hostDetails->snmp_overrides,
                ];
            }
        }

        return $formattedWork;
    }

    /**
     * Format the work from Sonar in order to determine device mapping.
     * @param stdClass $work
     * @return array
     */
    public function formatDeviceMappingWork(stdClass $work):array
    {
        $formattedWork = [
            'templates' => [],
            'hosts' => [],
        ];

        foreach ($work->data->monitoring_templates as $templateID => $template)
        {
            $formattedWork['templates'][$templateID] = $template;
        }

        foreach ($work->data->hosts as $hostID => $hostDetails)
        {
            if ($hostDetails->type != $this::NETWORK_SITES)
            {
                continue;
            }

            if (isset($formattedWork['templates'][$hostDetails->monitoring_template_id]))
            {
                $formattedWork['hosts'][$hostID] = [
                    'ip' => $hostDetails->ip,
                    'template_id' => $hostDetails->monitoring_template_id,
                    'snmp_overrides' => $hostDetails->snmp_overrides,
                    'id' => $hostID,
                ];
            }
        }

        return $formattedWork;
    }

    /**
     * Format ICMP hosts into a usable format
     * @param stdClass $work
     * @return array
     */
    public function formatIcmpHostsFromSonar(stdClass $work):array
    {
        $probeIcmpStatus = [];
        $icmpHosts = [];

        foreach ($work->data->monitoring_templates as $monitoringTemplateID => $configuration)
        {
            $probeIcmpStatus[$monitoringTemplateID] = (boolean)$configuration->icmp;
        }

        foreach ($work->data->hosts as $id => $details)
        {
            if ($probeIcmpStatus[$details->monitoring_template_id] === true)
            {
                $icmpHosts[$id] = $details->ip;
            }
        }


        $icmpHosts = array_unique($icmpHosts);
        
        return $icmpHosts;
    }

    /**
     * Take the data from fping and format it into a standard result
     * @param array $results
     * @param array $originalHosts
     * @return array
     */
    public function formatPingResultsFromFping(array $results, array $originalHosts):array
    {
        $formattedResults = [];

        //We need the original IDs from the hosts array to map up
        $ipsToIDs = [];
        foreach ($originalHosts as $id => $ip)
        {
            $ipsToIDs[$ip] = $id;
        }

        if (count($results) > 0)
        {
            $boom = explode(" ",$results[0]);
            //-2 here because we don't care about the first two results which are the host and a colon
            $middleIndex = floor((count($boom)-2)/2);

            foreach ($results as $result)
            {
                $boom = preg_split('/\s+/', $result);
                $host = $boom[0];

                unset($boom[0]);
                unset($boom[1]);
                sort($boom);

                $lossCount = count(array_filter($boom, function($val)
                {
                    return strpos($val, '-') === 0;
                }));

                $formattedResults[$ipsToIDs[$host]] = [
                    'host' => $host,
                    'loss_percentage' => round(($lossCount / (count($boom)))*100,2),
                    'low' => (float)round($boom[0],2),
                    'high' => (float)round($boom[count($boom)-1],2),
                    'median' => $this->calculateMedian($boom, $middleIndex),
                ];
            }
        }

        return $formattedResults;
    }

    /**
     * Format the SNMP results to be fed back to Sonar
     * @param array $results
     * @param array $originalHosts
     * @return array
     */
    public function formatSnmpResultsFromSnmpClass(array $results, array $originalHosts):array
    {
        $formattedResults = [];

        //We need the original IDs from the hosts array to map up
        $ipsToIDs = [];
        foreach ($originalHosts as $id => $details)
        {
            $ipsToIDs[$details['ip']] = $id;
        }

        if (count($results) > 0)
        {
            foreach ($results as $ip => $snmpResults)
            {
                $formattedResults[$ipsToIDs[$ip]] = $snmpResults;
            }
        }

        return $formattedResults;
    }

    /**
     * Calculate the median value
     * @param array $data
     * @param int $middleIndex
     * @return float
     */
    private function calculateMedian(array $data, int $middleIndex):float
    {
        $median = $data[$middleIndex];
        if (count($data) % 2 === 0)
        {
            $median = ($median + $data[$middleIndex - 1]) / 2;
        }
        return (float)round($median,2);
    }

    /**
     * Format a MAC in a standard format
     * @param string $mac
     * @return string
     */
    public static function formatMac(string $mac):string
    {
        //Sometimes, MACs are provided in a format where they are colon separated, but missing leading zeroes.
        if (strpos($mac,":") !== false)
        {
            $fixedMac = [];
            $boom = explode(":",$mac);
            foreach ($boom as $shard)
            {
                if (strlen($shard) == 1)
                {
                    $shard = "0" . $shard;
                }
                array_push($fixedMac,$shard);
            }
            $mac = implode(":",$fixedMac);
        }
        $cleanMac = str_replace(" ","",$mac);
        $cleanMac = str_replace("-","",$cleanMac);
        $cleanMac = str_replace(":","",$cleanMac);
        $cleanMac = strtoupper($cleanMac);
        $macSplit = str_split($cleanMac,2);
        return implode(":",$macSplit);
    }
}