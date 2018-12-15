<?php

namespace WingWifi\Controllers;

use Mike42\Escpos\EscposImage;
use UniFi_API\Client;
use WingWifi\Application;
use WingWifi\Utilities\RequestDataUtility;
use Mike42\Escpos\PrintConnectors\NetworkPrintConnector;
use Mike42\Escpos\Printer;

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
     * Printout language.
     *
     * @var  array  Array of language objects.
     */
    private $language = array();

    /**
     * Number of chars which can be printed in one line by printer.
     */
    const LINE_SIZE = 56;

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
            throw new \RuntimeException("Greška pri učitavanju konfiguracionog fajla!\nMolimo Vas da kontaktirate korisničku podršku za dalje akcije.");
        }

        if (empty(Application::$config->username)) {
            throw new \RuntimeException("Konfiguracioni fajl ne sadrži korisničko ime!\nMolimo Vas da kontaktirate korisničku podršku za dalje akcije.");
        }

        if (empty(Application::$config->password)) {
            throw new \RuntimeException("Konfiguracioni fajl ne sadrži korisničku lozinku!\nMolimo Vas da kontaktirate korisničku podršku za dalje akcije.");
        }

        if (empty(Application::$config->location)) {
            throw new \RuntimeException("Konfiguracioni fajl ne sadrži lokaciju UniFi kontrolera!\nMolimo Vas da kontaktirate korisničku podršku za dalje akcije.");
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
            throw new \RuntimeException("Došlo je do problema pri povezivanju na UniFi kontroler!\nMolimo Vas da kontaktirate korisničku podršku za dalje akcije.");
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
     *
     * @throws  \Exception
     */
    public function getVouchersList()
    {
        $vouchers = self::$uniFiClient->stat_voucher();
        $date     = new \DateTime();

        foreach ($vouchers as $voucher) {
            $interval = new \DateInterval('PT' . $voucher->duration . 'M');
            $created  = new \DateTime();
            $created->setTimestamp($voucher->create_time);

            if ($created->add($interval) < $date) {
                $voucher->invalid = true;
            } else {
                $voucher->invalid = false;
            }
        }

        return $vouchers;
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
     * @param   RequestDataUtility  $requestData  Request data.
     * @param   object              $config       Config object.
     *
     * @return  bool  True on success, false otherwise.
     */
    public function printVoucher($requestData, $config)
    {
        try {
            if (!empty($requestData->code)) {
                $connector = new NetworkPrintConnector($config->printer_ip);
                $printer   = new Printer($connector);
                $lang      = empty($requestData->lang) ? 'en' : $requestData->lang;
                $logo      = EscposImage::load(SITE_BASE . '/assets/img/logo.png', false);
                $msg       = $this->generateMessage($lang);
                $accessMsg = $this->generateAccessData($requestData->code, $config->wireless_name, $lang);

                $printer->initialize();
                $printer->setFont(Printer::FONT_B);
                $printer->text($this->generateHorizontalLine());
                $printer->feed(1);
                $printer->graphics($logo);
                $printer->feed(1);
                $printer->text($msg);
                $printer->feed(1);
                $printer->setTextSize(2,2);
                $printer->setLineSpacing(70);
                $printer->text($accessMsg);
                $printer->feed(1);
                $printer->setTextSize(1,1);
                $printer->setLineSpacing();
                $printer->text($this->generateHorizontalLine());
                $printer->cut();
                $printer->pulse();
                $printer->close();
            } else {
                return false;
            }
        } catch (\Exception $e) {
            return false;
        }

        return true;
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

    /**
     * Load language strings.
     *
     * @param   string  $lang  Language code.
     *
     * @return  object  Language object strings.
     */
    private function getLanguage($lang = 'en')
    {
        if (!isset($this->language[$lang]) || empty($this->language[$lang])) {
            $this->language[$lang] = \json_decode(\file_get_contents(SITE_BASE . '/assets/lang/' . $lang . '.json'));
        }

        return $this->language[$lang];
    }

    /**
     * Function for generating printout message.
     *
     * @param   string  $lang  Language to use on generating the message.
     *
     * @return  string  String for printout.
     */
    private function generateMessage($lang = 'en')
    {
        $lang    = $this->getLanguage($lang);
        $message = '';

        if (!empty($lang)) {
            $message .= $lang->greetings . ",\n";
            $words    = \explode(' ', $lang->message);
            $line     = "";
            $i        = 0;

            if (!empty($words)) {
                foreach ($words as $word) {
                    if (\strlen($line) + \strlen($word) + 1 <= self::LINE_SIZE) {
                        $line .= ' ' . $word;

                        if ($i == \count($words) - 1) {
                            $message .= $line;
                        }
                    } else {
                        $line .= "\n";
                        $message .= $line;
                        $line = $word;
                    }

                    $i++;
                }
            }
        }

        return $message;
    }

    /**
     * Function for generating printout message.
     *
     * @param   string  $code  Voucher code, wireless password.
     * @param   string  $ssid  Wireless client name.
     * @param   string  $lang  Language to use on generating the message.
     *
     * @return  string  String for printout.
     */
    private function generateAccessData($code, $ssid = 'WingWifi', $lang = 'en')
    {
        $lang    = $this->getLanguage($lang);
        $message = '';

        if (!empty($code) && !empty($ssid) && !empty($lang)) {
            $message .= $lang->name . ": " . $ssid . "\n";
            $message .= $lang->password . ": " . $code;
        }

        return $message;
    }

    /**
     * Function for generating horizontal line for split up.
     *
     * @param   string  $lineChar  Line separation char.
     *
     * @return  string
     */
    private function generateHorizontalLine($lineChar = '=')
    {
        $line = "";

        for ($i = 0; $i < self::LINE_SIZE; $i++) {
            $line .= $lineChar;
        }

        return $line;
    }
}
