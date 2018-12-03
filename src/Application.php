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
     * Config object, data parsed from config.json
     * Contains all values for connection to UniFy Controller.
     *
     * @var  object  Config object.
     */
    public static $config = null;

    /**
     * @var  Controllers\UniFiController  UniFiController object.
     */
    private $uniFiController = null;

    /**
     * Application constructor. Inits basic data and loads configuration.
     * Also, it set ups session data and connection UniFi client.
     *
     * @param   bool  $newInstance  Setup new instance.
     *
     * @return  void
     */
    public function __construct($newInstance = false)
    {
        $this->initConfig();
        $this->initSession($newInstance);
        $this->uniFiController = new Controllers\UniFiController();
    }

    /**
     * Get Application response.
     *
     * @param   array   $requestData  Request data.
     * @param   string  $requestType  Request type.
     *
     * @return  string  Application response.
     */
    public function getResponse($requestData = array(), $requestType = 'html')
    {
        if ($requestType == 'html') {
            return '';
        } elseif ($requestType == 'ajax') {
            return $this->generateAjaxResponse();
        }

        return '';
    }

    /**
     * Getter method for UniFiController.
     *
     * @return  Controllers\UniFiController
     */
    public function getUnifyController()
    {
        return new Controllers\UniFiController();
    }

    /**
     * Function for generating application ajax response.
     *
     * @param   string  $html    HTML code for rerender.
     * @param   string  $status  Ajax response status.
     * @param   string  $msg     Ajax response message.
     *
     * @return  false|string  JSON string as ajax response.
     */
    private function generateAjaxResponse($html = '', $status = '', $msg = '')
    {
        $response = array (
            'html'    => $html,
            'status'  => $status,
            'message' => $msg
        );

        return \json_encode($response);
    }

    /**
     * Function for loading config data.
     * Contains username, password, site location and controller name
     * for UniFi connection.
     *
     * @return  void
     */
    private function initConfig()
    {
        $configStr    = \file_get_contents(SITE_BASE . '/config.json');
        self::$config = \json_decode($configStr);

        if (!isset($this->config->cookieTimeout)) {
            self::$config->cookieTimeout = 3600;
        }
    }

    /**
     * Method for purging SESSION data.
     *
     * @param   bool  $newInstance  Setup new instance.
     *
     * @return  void
     */
    private function initSession($newInstance = false)
    {
        if ($newInstance ||
            (isset($_SESSION['last_activity']) &&
                time() - $_SESSION['last_activity'] > self::$config->cookieTimeout
            )
        ) {
            session_unset();
            session_destroy();
            session_start();
        }

        $_SESSION['last_activity'] = time();
    }
}
