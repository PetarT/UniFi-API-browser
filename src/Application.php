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
     * @var  \Twig_Environment  Twig renderer object.
     */
    private $twig = null;

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
        try {
            $this->initConfig();
            $this->initSession($newInstance);
            $this->initTwig();
            $this->uniFiController = new Controllers\UniFiController();
        } catch (\Exception $e) {
            $this->render(
                new Utilities\RequestDataUtility(
                    array(
                        'show' => 'error',
                        'msg'  => $e->getMessage()
                    )
                )
            );
        }
    }

    /**
     * Render output.
     *
     * @param   Utilities\RequestDataUtility   $requestData  Request data.
     *
     * @return  string  Application output.
     */
    public function render($requestData)
    {
        try {
            if (isset($requestData->type) && $requestData->type == 'ajax') {
                return $this->generateAjaxResponse();
            } else {
                if (!isset($requestData->show) || empty($requestData->show)) {
                    $template = $this->twig->load('sites_list.twig');
                } else {
                    switch ($requestData->show) {
                        case 'site':
                            $template = $this->twig->load('site_page.twig');
                            break;
                        case 'error':
                            $template = $this->twig->load('error.twig');
                            break;
                        case 'sites':
                        default:
                            $template = $this->twig->load('sites_list.twig');
                            break;
                    }
                }

                return $template->render();
            }
        } catch (\Exception $e) {
            echo $e->getMessage();
        }
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

        if (!empty(self::$config->username)) {
            self::$config->username = \trim(self::$config->username);
        }

        if (!empty(self::$config->location)) {
            self::$config->location = \rtrim(\trim(self::$config->location), '/');
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
            \session_unset();
            \session_destroy();
            \session_start();
        }

        $_SESSION['last_activity'] = \time();
    }

    /**
     * Method for loading Twig.
     *
     * @return  void
     */
    private function initTwig()
    {
        $loader = new \Twig_Loader_Filesystem(SITE_BASE . '/views');
        $twig   = new \Twig_Environment($loader, array(
            'cache' => SITE_BASE . '/cache',
        ));

        $this->twig = $twig;
    }
}
