<?php

namespace WingWifi\Controllers;

use UniFi_API\Client;
use WingWifi\Application;

class UniFiController
{
    /**
     * UnifyClient object.
     * Connector to UniFi Controller
     *
     * @var  UniFiController
     */
    private static $uniFiClient = null;

    /**
     * Init method for UniFi client.
     *
     * @return  void
     *
     * @throws  \RuntimeException
     */
    public function __construct()
    {
        if (empty(Application::$config)) {
            throw new \RuntimeException('<p>Greška pri učitavanju konfiguracionog fajla!</p><p>Molimo Vas da kontaktirate korisničku podršku za dalje akcije.</p>');
        }

        if (empty(Application::$config->username)) {
            throw new \RuntimeException('<p>Konfiguracioni fajl ne sadrži korisničko ime!</p><p>Molimo Vas da kontaktirate korisničku podršku za dalje akcije.</p>');
        }

        if (empty(Application::$config->password)) {
            throw new \RuntimeException('<p>Konfiguracioni fajl ne sadrži korisničku lozinku!</p><p>Molimo Vas da kontaktirate korisničku podršku za dalje akcije.</p>');
        }

        if (empty(Application::$config->location)) {
            throw new \RuntimeException('<p>Konfiguracioni fajl ne sadrži lokaciju UniFi kontrolera!</p><p>Molimo Vas da kontaktirate korisničku podršku za dalje akcije.</p>');
        }

        // Set base site for the controller
        if (empty(Application::$config->site)) {
            Application::$config->site = '';
        }

        if (\is_null(self::$uniFiClient)) {
            self::$uniFiClient = new Client(Application::$config->username, Application::$config->password, Application::$config->location, Application::$config->site);
        } else {
            self::$uniFiClient->logout();
        }

        if (self::$uniFiClient->login() !== true) {
            throw new \RuntimeException('<p>Došlo je do problema pri povezivanju na UniFi kontroler!</p><p>Molimo Vas da kontaktirate korisničku podršku za dalje akcije.</p>');
        }
    }

    /**
     * Setter for UniFi Client site.
     *
     * @param   string  $site
     */
    public function setClientSite($site)
    {
        self::$uniFiClient->set_site($site);
    }

    /**
     * Getter method for the UniFi client.
     * Used for direct communication to UniFi controller.
     *
     * @return  Client  UniFi Client object.
     */
    public function getClient()
    {
        return self::$uniFiClient;
    }

    public function getVouchersList()
    {

    }

    public function generateVoucher()
    {

    }
}
