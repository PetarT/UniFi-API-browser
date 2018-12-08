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
     * @var  Client
     */
    private static $uniFiClient = null;

    /**
     * Array of sites info.
     *
     * @var  array
     */
    private static $sites = null;

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

        if (empty(self::$sites)) {
            $this->getSitesList();
        }

        foreach (self::$sites as $s) {
            if ($s->name == $site) {
                $s->current = true;
            } else {
                $s->current = false;
            }
        }
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

    /**
     * Function for getting list of pending vouchers.
     *
     * @return  array  List of vouchers.
     */
    public function getVouchersList()
    {
        return self::$uniFiClient->stat_voucher();
    }

    /**
     * Function for generating vouchers.
     *
     * @param   int     $minutes        Number of minutes for how long will voucher be valid.
     * @param   int     $count          Number of vouchers to generate.
     * @param   int     $numberOfUsage  Number of voucher usage. 0 - onetime, 1 - multi usage
     * @param   string  $note           Note to display on voucher printing.
     * @param   int     $upSpeed        Upload speed limit.
     * @param   int     $downSpeed      Download speed limit.
     * @param   int     $totalMB        Total MBs to spend.
     *
     * @return  array|bool  False if action didn't complete, otherwise info about created vouchers.
     */
    public function generateVoucher($minutes = 60, $count = 1, $numberOfUsage = 0, $note = null, $upSpeed = null, $downSpeed = null, $totalMB = null)
    {
        return self::$uniFiClient->create_voucher($minutes, $count, $numberOfUsage, $note, $upSpeed, $downSpeed, $totalMB);
    }

    /**
     * Function for removing the voucher.
     *
     * @param   string  $id  Voucher id.
     *
     * @return  bool  True on success, false otherwise.
     */
    public function removeVoucher($id)
    {
        return self::$uniFiClient->revoke_voucher($id);
    }

    /**
     * Function for printing voucher.
     *
     * @param   string  $id  Voucher id.
     *
     * @return  void
     */
    public function printVoucher($id)
    {
        // TO-DO
    }

    /**
     * Function for getting list of UniFi sites.
     *
     * @return  array  Array of sites.
     */
    public function getSitesList()
    {
        $sites = self::$uniFiClient->list_sites();

        foreach ($sites as $site) {
            $site->href = SITE_URI . '/index.php?show=site&name=' . $site->name;
        }

        self::$sites = $sites;

        return $sites;
    }

    /**
     * Function for getting currently selected site info.
     *
     * @return  array  Info about site.
     */
    public function getSiteInfo()
    {
        foreach (self::$sites as $site) {
            if ($site->current == true) {
                return $site;
            }
        }
    }

    /**
     * Check if site exists in the list.
     *
     * @param   string  $site  Site name to lookup for.
     *
     * @return  bool  True if site exists, false otherwise.
     */
    public function siteExists($site)
    {
        foreach (self::$sites as $aSite) {
            if ($site == $aSite->name) {
                return true;
            }
        }

        return false;
    }
}
