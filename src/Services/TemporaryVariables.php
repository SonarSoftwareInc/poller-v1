<?php

namespace SonarSoftware\Poller\Services;

use Predis\Client;

class TemporaryVariables
{
    /**
     * @param $key
     * @return string
     */
    public static function get($key)
    {
        $client = new Client();
        return $client->get($key);
    }

    /**
     * @param $key
     * @param $value
     * @return mixed
     */
    public static function set($key, $value)
    {
        $client = new Client();
        return $client->set($key, $value);
    }
}