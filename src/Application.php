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
            echo $this->render(
                new Utilities\RequestDataUtility(
                    array(
                        'show' => 'error',
                        'msg'  => $e->getMessage()
                    )
                )
            );
            exit;
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
            $attr = array('home' => SITE_URI);

            if (isset($requestData->type) && $requestData->type == 'ajax') {
                $response = true;
                $msg      = '';

                if (isset($requestData->action)) {
                    if (!empty($requestData->site)) {
                        $this->uniFiController->setClientSite($requestData->site);
                    }

                    if ($requestData->action == 'createVoucher') {
                        if (isset($requestData->time) && !empty($requestData->time)) {
                            $status = $this->uniFiController->generateVoucher($requestData->time);
                        } else {
                            $status = $this->uniFiController->generateVoucher();
                        }

                        if ($status == false) {
                            $response = false;
                            $msg      = 'Error creating the voucher!';
                        } else {
                            $msg = 'Voucher successfully created!';
                        }
                    } elseif($requestData->action == 'removeVoucher') {
                        if (isset($requestData->id) && !empty($requestData->id)) {
                            $status = $this->uniFiController->removeVoucher($requestData->id);

                            if ($status == false) {
                                $response = false;
                                $msg      = 'Error removing the voucher!';
                            } else {
                                $msg = 'Voucher successfully removed';
                            }
                        }
                    } elseif ($requestData->action == 'printVoucher') {
                        $status = $this->uniFiController->printVoucher($requestData, self::$config);

                        if ($status == false) {
                            $response = false;
                            $msg      = 'Error printing the voucher!';
                        } else {
                            $msg = 'Voucher successfully printed';
                        }
                    }
                }

                return $this->generateAjaxResponse('', $response, $msg);
            } else {
                if (!isset($requestData->show)) {
                    $requestData->show = '';
                }

                switch ($requestData->show) {
                    case 'site':
                        $template = $this->twig->load('site_page.twig');

                        if (!empty($requestData->name)) {
                            $this->uniFiController->setClientSite($requestData->name);
                            $attr['site']     = $this->uniFiController->getSiteInfo();
                            $attr['vouchers'] = $this->uniFiController->getVouchersList();
                        }

                        break;
                    case 'error':
                        $template = $this->twig->load('error.twig');

                        if (!empty($requestData->msg)) {
                            $attr['errorMsg'] = $requestData->msg;
                        }

                        break;
                    case 'sites':
                    default:
                        $attr['sitesList'] = $this->uniFiController->getSitesList();
                        $template          = $this->twig->load('sites_list.twig');

                        if (!empty(self::$config->site) && $this->uniFiController->siteExists(self::$config->site)) {
                            \header('Location: ' . SITE_URI . '/index.php?show=site&name=' . self::$config->site);
                            exit();
                        }

                        break;
                }

                return $template->render($attr);
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
                \time() - $_SESSION['last_activity'] > self::$config->cookieTimeout
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
            'cache' => SITE_BASE . '/cache'
        ));

        $this->twig = $twig;
    }
}
