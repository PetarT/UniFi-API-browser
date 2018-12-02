<?php

namespace WingWifi;

/**
 * Class Application
 *
 * @package WingWifi
 */
class Application
{
    /**
     * @var null
     */
    private $config = null;

    /**
     * Application constructor. Inits basic data and loads configuration.
     * Also, it set ups session data and connection UniFi client.
     *
     * @return  void
     */
    public function __construct()
    {
        $this->initConfig();
        $this->initSession();
    }

    /**
     * Function for loading config data.
     *
     * @return  void
     */
    private function initConfig()
    {
        $configStr    = \file_get_contents('./data/config.json');
        $this->config = \json_decode($configStr);
    }
}
