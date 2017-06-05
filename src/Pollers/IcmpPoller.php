<?php

namespace SonarSoftware\Poller\Pollers;

use Dotenv\Dotenv;
use Monolog\Logger;
use SonarSoftware\Poller\Formatters\Formatter;
use SonarSoftware\Poller\Services\SonarLogger;

class IcmpPoller
{
    private $icmpForks;
    private $timeout;
    private $log;
    private $pathToFping = "/usr/bin/fping";

    /** Status constants */
    const GOOD = 2;
    const WARNING = 1;
    const DOWN = 0;

    public function __construct()
    {
        $dotenv = new Dotenv(dirname(__FILE__) . "/../../");
        $dotenv->load();
        $this->icmpForks = (int)getenv("ICMP_FORKS") > 0 ? (int)getenv("ICMP_FORKS") : 10;
        $this->timeout = (int)getenv("ICMP_TIMEOUT") > 0 ? (int)getenv("ICMP_TIMEOUT")*1000 : 2000;
        $this->log = new SonarLogger();
        if (getenv("PATH_TO_FPING"))
        {
            $this->pathToFping = getenv("PATH_TO_FPING");
        }
    }

    /**
     * Ping an array of IPv4/IPv6 hosts
     * @param array $hosts
     * @return array
     */
    public function poll(array $hosts):array
    {
        if (count($hosts) === 0)
        {
            return [];
        }

        $chunks = array_chunk($hosts,ceil(count($hosts)/$this->icmpForks));

        $results = [];
        $socketHolder = [];

        for ($i = 0; $i < count($chunks); $i++)
        {
            socket_create_pair(AF_UNIX, SOCK_STREAM, 0, $sockets);
            $socketHolder[$i] = $sockets;
            $pid = pcntl_fork();
            if (!$pid)
            {
                socket_close($sockets[1]);
                usleep(rand(250000,5000000));
                exec("{$this->pathToFping} -A -b 12 -C 10 -D -p 500 -q -r 0 -t " . escapeshellarg($this->timeout) . " -B 1.0 -i 10 " . implode(" ",$chunks[$i]) . " 2>&1",$result,$returnVar);
                socket_write($sockets[0], serialize($result));
                socket_close($sockets[0]);
                exit($i);
            }

        }

        while (pcntl_waitpid(0, $status) != -1) {
            $status = pcntl_wexitstatus($status);
            $singleSocket = $socketHolder[$status];
            socket_close($singleSocket[0]);
            $output = unserialize(socket_read($singleSocket[1],10000000));
            socket_close($singleSocket[1]);
            if (is_array($output))
            {
                $results = array_merge($results,$output);
            }
            else
            {
                $this->log->log("When unserializing ICMP data, no array was returned as a result of unserialization.",Logger::ERROR);
            }
        }

        $formatter = new Formatter();
        return $formatter->formatPingResultsFromFping($results, $hosts);
    }
}