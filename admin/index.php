<?php
/**
 * UniFi API browser
 *
 * This tool is for browsing data that is exposed through Ubiquiti's UniFi Controller API,
 * and is developed with PHP, JavaScript and the Bootstrap CSS framework.
 *
 * Please keep the following in mind:
 * - not all data collections/API endpoints are supported (yet), see the list of
 *   the currently supported data collections/API endpoints in the README.md file
 * - this tool currently supports versions 4.x and 5.x of the UniFi Controller software
 * ------------------------------------------------------------------------------------
 *
 * Copyright (c) 2017, Art of WiFi
 *
 * This source file is subject to the MIT license that is bundled
 * with this package in the file LICENSE.md
 *
 */
define('API_BROWSER_VERSION', '1.0.34');
define('API_CLASS_VERSION', get_client_version());

/**
 * check whether the required PHP curl module is available
 * - if yes, collect cURL version details for the info modal
 * - if not, stop and display an error message
 */
if (function_exists('curl_version')) {
    $curl_info       = curl_version();
    $curl_version    = $curl_info['version'];
    $openssl_version = $curl_info['ssl_version'];
} else {
    exit('The <b>PHP curl</b> module is not installed! Please correct this before proceeding!<br>');
    $curl_version    = 'unavailable';
    $openssl_version = 'unavailable';
}

/**
 * in order to use the PHP $_SESSION array for temporary storage of variables, session_start() is required
 */
session_start();

if (!isset($_SESSION['admin_logged_in']) || empty($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] != true) {
    header('Location: login.php');
    exit;
}

/**
 * check whether user has requested to clear (force expiry) the PHP session
 * - this feature can be useful when login errors occur, mostly after upgrades or credential changes
 */
if (isset($_GET['reset_session']) && $_GET['reset_session'] == true) {
    $_SESSION = [];
    session_unset();
    session_destroy();
    session_start();
    header("Location: " . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

/**
 * starting timing of the session here
 */
$time_start = microtime(true);

/**
 * declare variables which are required later on together with their default values
 */
$show_login         = false;
$controller_id      = '';
$action             = '';
$site_id            = '';
$site_name          = '';
$selection          = '';
$data               = '';
$objects_count      = '';
$alert_message      = '';
$output_format      = 'json';
$controlleruser     = '';
$controllerpassword = '';
$controllerurl      = '';
$controllername     = 'Controller';
$cookietimeout      = '3600';
$theme              = 'bootstrap';
$debug              = false;

/**
 * load the optional configuration file if readable
 * - allows override of several of the previously declared variables
 */
if (is_file('../config.json') && is_readable('../config.json')) {
    $config = json_decode(file_get_contents('../config.json'));

    if (!empty($config)) {
        $controlleruser     = $config->username;
        $controllerpassword = $config->password;
        $controllerurl      = $config->location;
        $controllername     = $config->site;
    } else {
        $alert_message = '<div class="alert alert-info" role="alert">Greška pri povezivanju! Konfiguracioni fajl nije učitan! ' .
            '<i class="fa fa-arrow-circle-up"></i></div>';
    }
}

/**
 * load the UniFi API client and Kint classes using composer autoloader
 */
require('../vendor/autoload.php');

/**
 * set relevant Kint options
 * more info on Kint usage: http://kint-php.github.io/kint/
 */
Kint::$display_called_from = false;

/**
 * determine whether we have reached the cookie timeout, if so, refresh the PHP session
 * else, update last activity time stamp
 */
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $cookietimeout)) {
    /**
     * last activity was longer than $cookietimeout seconds ago
     */
    session_unset();
    session_destroy();
    if ($debug) {
        error_log('UniFi API browser INFO: session cookie timed out');
    }
}

$_SESSION['last_activity'] = time();

/**
 * process the GET variables and store them in the $_SESSION array
 * if a GET variable is not set, get the values from the $_SESSION array (if available)
 *
 * process in this order:
 * - controller_id
 * only process this after controller_id is set:
 * - site_id
 * only process these after site_id is set:
 * - action
 * - output_format
 */
if (isset($_GET['controller_id'])) {
    /**
     * user has requested a controller switch
     */
    if (!isset($controllers)) {
        header("Location: " . strtok($_SERVER['REQUEST_URI'], '?') . "?reset_session=true");
        exit;
    }
    $controller                = $controllers[$_GET['controller_id']];
    $controller_id             = $_GET['controller_id'];
    $_SESSION['controller']    = $controller;
    $_SESSION['controller_id'] = $_GET['controller_id'];

    /**
     * clear the variables from the $_SESSION array that are associated with the previous controller session
     */
    unset($_SESSION['site_id']);
    unset($_SESSION['site_name']);
    unset($_SESSION['sites']);
    unset($_SESSION['action']);
    unset($_SESSION['detected_controller_version']);
    unset($_SESSION['unificookie']);
} else {
    if (isset($_SESSION['controller']) && isset($controllers)) {
        $controller    = $_SESSION['controller'];
        $controller_id = $_SESSION['controller_id'];
    } else {
        if (!isset($controllers)) {
            /**
             * pre-load $controller array with $_SESSION['controllers'] if present
             * then load configured single site credentials
             */
            $controller = [];
            if (isset($_SESSION['controller'])) {
                $controller = $_SESSION['controller'];
            }
            if (!isset($controller['user']) || !isset($controller['password']) || !isset($controller['url'])) {
                $_SESSION['controller'] = [
                    'user'     => $controlleruser,
                    'password' => $controllerpassword,
                    'url'      => $controllerurl,
                    'name'     => $controllername
                ];
                $controller = $_SESSION['controller'];
            }
        }
    }

    if (isset($_GET['site_id'])) {
        $site_id               = $_GET['site_id'];
        $_SESSION['site_id']   = $site_id;
        $site_name             = $_GET['site_name'];
        $_SESSION['site_name'] = $site_name;
    } else {
        if (isset($_SESSION['site_id'])) {
            $site_id   = $_SESSION['site_id'];
            $site_name = $_SESSION['site_name'];
        }
    }
}

/**
 * load login form data, if present, and save to credential variables
 */
if (isset($_POST['controller_user']) && !empty($_POST['controller_user'])) {
    $controller['user'] = $_POST['controller_user'];
}

if (isset($_POST['controller_password']) && !empty($_POST['controller_password'])) {
    $controller['password'] = $_POST['controller_password'];
}

if (isset($_POST['controller_url']) && !empty($_POST['controller_url'])) {
    $controller['url'] = $_POST['controller_url'];
}

if (isset($controller)) {
    $_SESSION['controller'] = $controller;
}

/**
 * get requested theme or use the theme stored in the $_SESSION array
 */
if (isset($_GET['theme'])) {
    $theme             = $_GET['theme'];
    $_SESSION['theme'] = $theme;
    $theme_changed     = true;
} else {
    if (isset($_SESSION['theme'])) {
        $theme = $_SESSION['theme'];
    }

    $theme_changed = false;
}

/**
 * get requested output_format or use the output_format stored in the $_SESSION array
 */
if (isset($_GET['output_format'])) {
    $output_format             = $_GET['output_format'];
    $_SESSION['output_format'] = $output_format;
} else {
    if (isset($_SESSION['output_format'])) {
        $output_format = $_SESSION['output_format'];
    }
}

/**
 * get requested action or use the action stored in the $_SESSION array
 */
if (isset($_GET['action'])) {
    $action             = $_GET['action'];
    $_SESSION['action'] = $action;
} else {
    if (isset($_SESSION['action'])) {
        $action = $_SESSION['action'];
    }
}

/**
 * display info message when no controller, site or data collection is selected
 * placed here so they can be overwritten by more "severe" error messages later on
 */
if ($action === '') {
    $alert_message = '<div class="alert alert-info" role="alert">Izaberite kolekciju podataka iz padajućeg menija ' .
                     '<i class="fa fa-arrow-circle-up"></i></div>';
}

if ($site_id === '') {
    $alert_message = '<div class="alert alert-info" role="alert">Izaberi čvorište iz padajućeg menija ' .
                     '<i class="fa fa-arrow-circle-up"></i></div>';
}

if (!isset($controller['name']) && isset($controllers)) {
    $alert_message = '<div class="alert alert-info" role="alert">Izaberite kontroler iz padajućeg menija ' .
                     '<i class="fa fa-arrow-circle-up"></i></div>';
} else {
    if (!isset($_SESSION['unificookie']) && (empty($controller['user']) || empty($controller['password']) || empty($controller['url']))) {
        $show_login    = true;
        $alert_message = '<div class="alert alert-info" role="alert">Prijavi se na ';
        if (!empty($controller['url'])) {
            $alert_message .= '<a href="' . $controller['url'] . '">';
        }

        $alert_message .= $controller['name'];
        if (!empty($controller['url'])) {
            $alert_message .= '</a>';
        }

        if (!empty($controller['user'])) {
            $alert_message .= ' sa korisničkim imenom ' . $controller['user'];
        }

        $alert_message .= ' <i class="fa fa-sign-in"></i></div>';
    }
}


/**
 * do this when a controller has been selected and was stored in the $_SESSION array and login isn't needed
 */
if (isset($_SESSION['controller']) && $show_login !== true) {
    /**
     * create a new instance of the API client class and log in to the UniFi controller
     * - if an error occurs during the login process, an alert is displayed on the page
     */
    $unifidata      = new UniFi_API\Client(trim($controller['user']), $controller['password'], rtrim(trim($controller['url']), '/'), $site_id);
    $set_debug_mode = $unifidata->set_debug($debug);
    $loginresults   = $unifidata->login();

    if ($loginresults === 400) {
        $alert_message = '<div class="alert alert-danger" role="alert">HTTP response status: 400' .
                         '<br>Neuspešna prijava! ' .
                         '<a href="?reset_session=true">Probaj opet?</a></div>';

        /**
         * to prevent unwanted errors we assign empty values to the following variables
         */
        $sites                       = [];
        $detected_controller_version = 'undetected';
    } else {
        /**
         * remember authentication cookie to the controller.
         */
        $_SESSION['unificookie'] = $unifidata->get_cookie();

        /**
         * get the list of sites managed by the UniFi controller (if not already stored in the $_SESSION array)
         */
        if (!isset($_SESSION['sites']) || empty($_SESSION['sites'])) {
            $sites = $unifidata->list_sites();
            if (is_array($sites)) {
                $_SESSION['sites'] = $sites;
            } else {
                $sites = [];

                $alert_message = '<div class="alert alert-danger" role="alert">Nema čvorišta za prikaz' .
                                 '<br>Dešava se usled neuspešne prijave. Proverite logove servera ili ' .
                                 '<a href="?reset_session=true">pokušaj opet</a>.</div>';
            }

        } else {
            $sites = $_SESSION['sites'];
        }

        /**
         * get the version of the UniFi controller (if not already stored in the $_SESSION array or when 'undetected')
         */
        if (!isset($_SESSION['detected_controller_version']) || $_SESSION['detected_controller_version'] === 'undetected') {
            $site_info = $unifidata->stat_sysinfo();

            if (isset($site_info[0]->version)) {
                $detected_controller_version             = $site_info[0]->version;
                $_SESSION['detected_controller_version'] = $detected_controller_version;
            } else {
                $detected_controller_version             = 'undetected';
                $_SESSION['detected_controller_version'] = 'undetected';
            }

        } else {
            $detected_controller_version = $_SESSION['detected_controller_version'];
        }
    }
}

/**
 * execute timing of controller login
 */
$time_1           = microtime(true);
$time_after_login = $time_1 - $time_start;

if (isset($unifidata)) {
    /**
     * array containing attributes to fetch for the gateway stats, overriding
     * the default attributes
     */
    $gateway_stats_attribs = [
        'time',
        'mem',
        'cpu',
        'loadavg_5',
        'lan-rx_errors',
        'lan-tx_errors',
        'lan-rx_bytes',
        'lan-tx_bytes',
        'lan-rx_packets',
        'lan-tx_packets',
        'lan-rx_dropped',
        'lan-tx_dropped'
    ];

    /**
     * select the required call to the UniFi Controller API based on the selected action
     */
    switch ($action) {
        case 'list_clients':
            $selection = 'Lista online klijenata';
            $data      = $unifidata->list_clients();
            break;
        case 'stat_allusers':
            $selection = 'Statistika svih korisnika';
            $data      = $unifidata->stat_allusers();
            break;
        case 'stat_auths':
            $selection = 'Statistika aktvnih autorizacija';
            $data      = $unifidata->stat_auths();
            break;
        case 'list_guests':
            $selection = 'Lista gost korisnika';
            $data      = $unifidata->list_guests();
            break;
        case 'list_usergroups':
            $selection = 'Lista korisničkih grupa';
            $data      = $unifidata->list_usergroups();
            break;
        case 'stat_5minutes_site':
            $selection = 'Petominutna statistika čvorišta';
            $data      = $unifidata->stat_5minutes_site();
            break;
        case 'stat_hourly_site':
            $selection = 'Satovna statistika čvorišta';
            $data      = $unifidata->stat_hourly_site();
            break;
        case 'stat_daily_site':
            $selection = 'Dnevna statistika čvorišta';
            $data      = $unifidata->stat_daily_site();
            break;
        case 'stat_5minutes_aps':
            $selection = 'Petominutna statistika AP-ova';
            $data      = $unifidata->stat_5minutes_aps();
            break;
        case 'stat_hourly_aps':
            $selection = 'Satovna statistika AP-ova';
            $data      = $unifidata->stat_hourly_aps();
            break;
        case 'stat_daily_aps':
            $selection = 'Dnevna statistika AP-ova';
            $data      = $unifidata->stat_daily_aps();
            break;
        case 'stat_5minutes_gateway':
            $selection = 'Petominutna statistika gateway izlaza';
            $data      = $unifidata->stat_5minutes_gateway(null, null, $gateway_stats_attribs);
            break;
        case 'stat_hourly_gateway':
            $selection = 'Satovna statistika gateway izlaza';
            $data      = $unifidata->stat_hourly_gateway(null, null, $gateway_stats_attribs);
            break;
        case 'stat_daily_gateway':
            $selection = 'Dnevna statistika gateway izlaza';
            $data      = $unifidata->stat_daily_gateway(null, null, $gateway_stats_attribs);
            break;
        case 'stat_sysinfo':
            $selection = 'Informacije sistema';
            $data      = $unifidata->stat_sysinfo();
            break;
        case 'list_devices':
            $selection = 'Lista uređaja';
            $data      = $unifidata->list_devices();
            break;
        case 'list_tags':
            $selection = 'Lista tagova';
            $data      = $unifidata->list_tags();
            break;
        case 'list_wlan_groups':
            $selection = 'Lista WLan grupa';
            $data      = $unifidata->list_wlan_groups();
            break;
        case 'stat_sessions':
            $selection = 'Statistika sesija';
            $data      = $unifidata->stat_sessions();
            break;
        case 'list_users':
            $selection = 'Lista korisnika';
            $data      = $unifidata->list_users();
            break;
        case 'list_rogueaps':
            $selection = 'Lista "rogue" AP-ova';
            $data      = $unifidata->list_rogueaps();
            break;
        case 'list_known_rogueaps':
            $selection = 'list poznatih "rogue" AP-ova';
            $data      = $unifidata->list_known_rogueaps();
            break;
        case 'list_events':
            $selection = 'Lista događaja';
            $data      = $unifidata->list_events();
            break;
        case 'list_alarms':
            $selection = 'Lista alarma';
            $data      = $unifidata->list_alarms();
            break;
        case 'list_firewallgroups':
            $selection = 'Lista firewall grupa';
            $data      = $unifidata->list_firewallgroups();
            break;
        case 'count_alarms':
            $selection = 'Ukupan broj alarma';
            $data      = $unifidata->count_alarms();
            break;
        case 'count_active_alarms':
            $selection = 'Ukupan broj aktivnih alarma';
            $data      = $unifidata->count_alarms(false);
            break;
        case 'list_wlanconf':
            $selection = 'Lista WLan konfiguracija';
            $data      = $unifidata->list_wlanconf();
            break;
        case 'list_health':
            $selection = 'Metrika "zdravlja" sistema';
            $data      = $unifidata->list_health();
            break;
        case 'list_5minutes_dashboard':
            $selection = 'Petominutna metrika čvorišta';
            $data      = $unifidata->list_dashboard(true);
            break;
        case 'list_hourly_dashboard':
            $selection = 'Satovna metrika čvorišta';
            $data      = $unifidata->list_dashboard();
            break;
        case 'list_settings':
            $selection = 'Lista čvorišnih podešavanja';
            $data      = $unifidata->list_settings();
            break;
        case 'list_sites':
            $selection = 'Lista dostupnih čvorišta';
            $data      = $sites;
            break;
        case 'list_extension':
            $selection = 'Lista VoIP ekstenzija';
            $data      = $unifidata->list_extension();
            break;
        case 'list_portconf':
            $selection = 'Lista konfiguracija portova';
            $data      = $unifidata->list_portconf();
            break;
        case 'list_networkconf':
            $selection = 'Lista mreženih podešavanja';
            $data      = $unifidata->list_networkconf();
            break;
        case 'list_dynamicdns':
            $selection = 'Podešavanja Dynamic DNS-a';
            $data      = $unifidata->list_dynamicdns();
            break;
        case 'list_current_channels':
            $selection = 'Trenutni kanali';
            $data      = $unifidata->list_current_channels();
            break;
        case 'list_portforwarding':
            $selection = 'Lista pravila forward-ovanih portova';
            $data      = $unifidata->list_portforwarding();
            break;
        case 'list_portforward_stats':
            $selection = 'Statistika forward-ovanih portova';
            $data      = $unifidata->list_portforward_stats();
            break;
        case 'list_dpi_stats':
            $selection = 'Statistika DPI';
            $data      = $unifidata->list_dpi_stats();
            break;
        case 'stat_voucher':
            $selection = 'Lista HotSpot vaučera';
            $data      = $unifidata->stat_voucher();
            break;
        case 'stat_payment':
            $selection = 'Lista HotSpot plaćanja';
            $data      = $unifidata->stat_payment();
            break;
        case 'list_hotspotop':
            $selection = 'Lista HotSpot operatora';
            $data      = $unifidata->list_hotspotop();
            break;
        case 'list_self':
            $selection = 'O ulogovanom korisniku';
            $data      = $unifidata->list_self();
            break;
        case 'stat_sites':
            $selection = 'Statistika svih čvorišta';
            $data      = $unifidata->stat_sites();
            break;
        case 'list_admins':
            $selection = 'Lista administratora';
            $data      = $unifidata->list_admins();
            break;
        case 'list_radius_accounts':
            $selection = 'Lista radius naloga';
            $data      = $unifidata->list_radius_accounts();
            break;
        case 'list_radius_profiles':
            $selection = 'Lista radius profila';
            $data      = $unifidata->list_radius_profiles();
            break;
        case 'list_country_codes':
            $selection = 'Lista kodova zemalja';
            $data      = $unifidata->list_country_codes();
            break;
        case 'list_backups':
            $selection = 'Lista automatskih backup-ova';
            $data      = $unifidata->list_backups();
            break;
        case 'stat_ips_events':
            $selection = 'Lista IPS/IDS događaja';
            $data      = $unifidata->stat_ips_events();
            break;
        default:
            break;
    }
}

/**
 * count the number of objects collected from the UniFi controller
 */
if ($action != '' && !empty($data)) {
    $objects_count = count($data);
}

/**
 * execute timing of data collection from UniFi controller
 */
$time_2          = microtime(true);
$time_after_load = $time_2 - $time_start;

/**
 * calculate all the timings/percentages
 */
$time_end    = microtime(true);
$time_total  = $time_end - $time_start;
$login_perc  = ($time_after_login / $time_total) * 100;
$load_perc   = (($time_after_load - $time_after_login) / $time_total) * 100;
$remain_perc = 100 - $login_perc - $load_perc;

/**
 * shared functions
 */

/**
 * function to print the output
 * switch depending on the selected $output_format
 */
function print_output($output_format, $data)
{
    switch ($output_format) {
        case 'json':
            echo json_encode($data, JSON_PRETTY_PRINT);
            break;
        case 'json_color':
            echo json_encode($data);
            break;
        case 'php_array':
            print_r($data);
            break;
        case 'php_array_kint':
            +d($data);
            break;
        case 'php_var_dump':
            var_dump($data);
            break;
        case 'php_var_export':
            var_export($data);
            break;
        default:
            echo json_encode($data, JSON_PRETTY_PRINT);
            break;
    }
}

/**
 * function to sort the sites collection alpabetically by description
 */
function sites_sort($site_a, $site_b)
{
    return strcmp($site_a->desc, $site_b->desc);
}

/**
 * function which returns the version of the included API client class by
 * extracting it from the composer.lock file
 */
function get_client_version()
{
    if (is_readable('composer.lock')) {
        $composer_lock = file_get_contents('composer.lock');
        $json_decoded = json_decode($composer_lock, true);

        if (isset($json_decoded['packages'])) {
            foreach ($json_decoded['packages'] as $package) {
                if ($package['name'] === 'art-of-wifi/unifi-api-client') {
                    return substr($package['version'], 1);
                }
            }
        }
    }

    return 'unknown';
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta charset="utf-8">
    <title>UniFi API browser</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <!-- latest compiled and minified versions of Bootstrap, Font-awesome and Highlight.js CSS, loaded from CDN -->
    <link rel="stylesheet" href="../assets/admin/css/font-awesome.min.css">

    <!-- load the default Bootstrap CSS file from CDN -->
    <link rel="stylesheet" href="../assets/admin/css/bootstrap.min.css">

    <!-- placeholder to dynamically load the appropriate Bootswatch CSS file from CDN -->
    <link rel="stylesheet" href="../assets/admin/css/bootstrap.slate.min.css">

    <!-- load the jsonview CSS file from CDN -->
    <link rel="stylesheet" href="../assets/admin/css/jquery.jsonview.min.css">

    <!-- define favicon  -->
    <link rel="shortcut icon" href="favicon.ico" type="image/x-icon">
    <link rel="icon" sizes="16x16" href="favicon.ico" type="image/x-icon" >

    <!-- custom CSS styling -->
    <style>
        body {
            padding-top: 70px;
        }

        .scrollable-menu {
            height: auto;
            max-height: 80vh;
            overflow-x: hidden;
            overflow-y: auto;
        }

        #output_panel_loading {
            color: rgba(0,0,0,.4);
        }

        #output {
            display: none;
            position:relative;
        }

        .back-to-top {
            cursor: pointer;
            position: fixed;
            bottom: 20px;
            right: 20px;
            display:none;
        }

        #copy_to_clipboard_button {
             position: absolute;
             top: 0;
             right: 0;
        }

        #toggle_buttons {
             display: none;
        }
    </style>
    <!-- /custom CSS styling -->
</head>
<body>
<!-- top navbar -->
<nav id="navbar" class="navbar navbar-default navbar-fixed-top">
    <div class="container-fluid">
        <div class="navbar-header">
            <button class="navbar-toggle collapsed" type="button" data-toggle="collapse" data-target="#navbar-main">
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
            </button>
            <a class="navbar-brand hidden-sm hidden-md" href="index.php"><i class="fa fa-hotel fa-fw fa-lg" aria-hidden="true"></i> WingWifi admin panel</a>
        </div>
        <div id="navbar-main" class="collapse navbar-collapse">
            <ul class="nav navbar-nav navbar-left">
                <!-- controllers dropdown, only show when multiple controllers have been configured -->
                <?php if (isset($controllers)) { ?>
                    <li id="site-menu" class="dropdown">
                        <a id="controller-menu" href="#" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-haspopup="true" aria-expanded="false">
                            <?php
                            /**
                             * here we display the UniFi controller name, if selected, else just label it
                             */
                            if (isset($controller['name'])) {
                                echo $controller['name'];
                            } else {
                                echo 'Controllers';
                            }
                            ?>
                            <span class="caret"></span>
                        </a>
                        <ul class="dropdown-menu scrollable-menu" id="controllerslist">
                            <li class="dropdown-header">Select a controller</li>
                            <li role="separator" class="divider"></li>
                            <?php
                            /**
                             * here we loop through the configured UniFi controllers
                             */
                            foreach ($controllers as $key => $value) {
                                echo '<li id="controller_' . $key . '"><a href="?controller_id=' . $key . '">' . $value['name'] . '</a></li>' . "\n";
                            }
                            ?>
                         </ul>
                    </li>
                <?php } ?>
                <!-- /controllers dropdown -->
                <!-- sites dropdown, only show when a controller has been selected -->
                <?php if ($show_login === false && isset($controller['name'])) { ?>
                    <li id="site-menu" class="dropdown">
                        <a id="site-menu" href="#" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-haspopup="true" aria-expanded="false">

                            <?php
                            /**
                             * here we display the site name, if selected, else just label it
                             */
                            if (!empty($site_name)) {
                                echo $site_name;
                            } else {
                                echo 'Čvorišta';
                            }
                            ?>
                            <span class="caret"></span>
                        </a>
                        <ul class="dropdown-menu scrollable-menu" id="siteslist">
                            <li class="dropdown-header">Izaberi čvorište</li>
                            <li role="separator" class="divider"></li>
                            <?php
                            /**
                             * here we loop through the available sites, after we've sorted the sites collection
                             */
                            usort($sites, "sites_sort");

                            foreach ($sites as $site) {
                                $link_row = '<li id="' . $site->name . '"><a href="?site_id=' .
                                            urlencode($site->name) . '&site_name=' . urlencode($site->desc) .
                                            '">' . $site->desc . '</a></li>' . "\n";

                                echo $link_row;
                            }
                            ?>
                         </ul>
                    </li>
                <?php } ?>
                <!-- /sites dropdown -->
                <!-- data collection dropdowns, only show when a site_id is selected -->
                <?php if ($site_id && isset($_SESSION['unificookie'])) { ?>
                    <li id="output-menu" class="dropdown">
                        <a id="output-menu" href="#" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-haspopup="true" aria-expanded="false">
                            Način prikaza
                            <span class="caret"></span>
                        </a>
                        <ul class="dropdown-menu scrollable-menu" id="outputselection">
                            <li class="dropdown-header">Izaberite način prikaza</li>
                            <li role="separator" class="divider"></li>
                            <li id="json"><a href="?output_format=json">JSON prikaz (standardno)</a></li>
                            <li role="separator" class="divider"></li>
                            <li id="php_array"><a href="?output_format=php_array">PHP niz</a></li>
                            <li id="php_var_dump"><a href="?output_format=php_var_dump">PHP var_dump funkcija</a></li>
                            <li id="php_var_export"><a href="?output_format=php_var_export">PHP var_export funkcija</a></li>
                            <li role="separator" class="divider"></li>
                            <li class="dropdown-header">Prilično spor način prikaza sa većim brojem podataka</li>
                            <li id="json_color"><a href="?output_format=json_color">JSON označeno</a></li>
                            <li id="php_array_kint"><a href="?output_format=php_array_kint">PHP niz sa Kint dodatkom</a></li>
                        </ul>
                    </li>
                    <li id="user-menu" class="dropdown">
                        <a id="user-menu" href="#" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-haspopup="true" aria-expanded="false">
                            Klijenti
                            <span class="caret"></span>
                        </a>
                        <ul class="dropdown-menu scrollable-menu">
                            <li class="dropdown-header">Izaberi kolekciju podataka</li>
                            <li role="separator" class="divider"></li>
                            <li id="list_clients"><a href="?action=list_clients">Listaj online klijente</a></li>
                            <li id="list_guests"><a href="?action=list_guests">Listaj goste</a></li>
                            <li id="list_users"><a href="?action=list_users">Listaj korisnike</a></li>
                            <li id="list_usergroups"><a href="?action=list_usergroups">Listaj korisničke grupe</a></li>
                            <li role="separator" class="divider"></li>
                            <li id="stat_allusers"><a href="?action=stat_allusers">Statistika svih korisnika</a></li>
                            <li id="stat_auths"><a href="?action=stat_auths">Statistika autorizacija</a></li>
                            <li id="stat_sessions"><a href="?action=stat_sessions">Statistika sesija</a></li>
                        </ul>
                    </li>
                    <li id="ap-menu" class="dropdown">
                        <a id="ap-menu" href="#" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-haspopup="true" aria-expanded="false">
                            Uređaji
                            <span class="caret"></span>
                        </a>
                        <ul class="dropdown-menu scrollable-menu">
                            <li class="dropdown-header">Izaberi kolekciju podataka</li>
                            <li role="separator" class="divider"></li>
                            <li id="list_devices"><a href="?action=list_devices">Lista uređaja</a></li>
                            <li id="list_wlan_groups"><a href="?action=list_wlan_groups">Lista WLan grupa</a></li>
                            <li id="list_rogueaps"><a href="?action=list_rogueaps">Lista pojedinačnih Access Point-ova</a></li>
                            <li id="list_known_rogueaps"><a href="?action=list_known_rogueaps">Lista poznatih pojedinačnih Access Point-ova</a></li>
                            <?php if ($detected_controller_version != 'undetected' && version_compare($detected_controller_version, '5.5.0') >= 0) { ?>
                                <!-- list tags, only to be displayed when we have detected a capable controller version -->
                                <li role="separator" class="divider"></li>
                                <li id="list_tags"><a href="?action=list_tags">Lista tagova</a></li>
                            <?php } ?>
                        </ul>
                    </li>
                    <li id="stats-menu" class="dropdown">
                        <a id="stats-menu" href="#" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-haspopup="true" aria-expanded="false">
                            Statistike
                            <span class="caret"></span>
                        </a>
                        <ul class="dropdown-menu scrollable-menu">
                            <li class="dropdown-header">Izaberi kolekciju podataka</li>
                            <li role="separator" class="divider"></li>
                            <li id="stat_5minutes_site"><a href="?action=stat_5minutes_site">Petominutna statistika čvorišta</a></li>
                            <li id="stat_hourly_site"><a href="?action=stat_hourly_site">Satovna statistika čvorišta</a></li>
                            <li id="stat_daily_site"><a href="?action=stat_daily_site">Dnevna statistika čvorišta</a></li>
                            <?php if ($detected_controller_version != 'undetected' && version_compare($detected_controller_version, '5.2.9') >= 0) { ?>
                                <!-- all sites stats, only to be displayed when we have detected a capable controller version -->
                                <li id="stat_sites"><a href="?action=stat_sites">Celokupna statistika čvorišta</a></li>
                            <?php } ?>
                            <!-- /all sites stats -->
                            <!-- access point stats -->
                            <li role="separator" class="divider"></li>
                            <li id="stat_5minutes_aps"><a href="?action=stat_5minutes_aps">Petominutna statistika Access Point-ova</a></li>
                            <li id="stat_hourly_aps"><a href="?action=stat_hourly_aps">Satovna statistika Access Point-ova</a></li>
                            <li id="stat_daily_aps"><a href="?action=stat_daily_aps">Dnevna statistika Access Point-ova</a></li>
                            <!-- /access point stats -->
                            <?php if ($detected_controller_version != 'undetected' && version_compare($detected_controller_version, '5.8.0') >= 0) { ?>
                                <!-- gateway stats, only to be displayed when we have detected a capable controller version -->
                                <li role="separator" class="divider"></li>
                                <li class="dropdown-header">USG required:</li>
                                <li id="stat_5minutes_gateway"><a href="?action=stat_5minutes_gateway">Petominutna statistika Gateway izlaza</a></li>
                                <li id="stat_hourly_gateway"><a href="?action=stat_hourly_gateway">Satovna statistika Gateway izlaza</a></li>
                                <li id="stat_daily_gateway"><a href="?action=stat_daily_gateway">Dnevna statistika Gateway izlaza</a></li>
                                <!-- /gateway stats -->
                            <?php } ?>
                            <li role="separator" class="divider"></li>
                            <?php if ($detected_controller_version != 'undetected' && version_compare($detected_controller_version, '4.9.1') >= 0) { ?>
                                <!-- site dashboard metrics, only to be displayed when we have detected a capable controller version -->
                                <li id="list_5minutes_dashboard"><a href="?action=list_5minutes_dashboard">Petominutna metrika čvorišta</a></li>
                                <li id="list_hourly_dashboard"><a href="?action=list_hourly_dashboard">Satovna metrika čvorišta</a></li>
                                <li role="separator" class="divider"></li>
                            <?php } ?>
                            <!-- /site dashboard metrics -->
                            <li id="list_health"><a href="?action=list_health">"Zdravlje" čvorišta</a></li>
                            <li id="list_portforward_stats"><a href="?action=list_portforward_stats">Port forwarding statistika</a></li>
                            <li id="list_dpi_stats"><a href="?action=list_dpi_stats">DPI statistika</a></li>
                        </ul>
                    </li>
                    <li id="hotspot-menu" class="dropdown">
                        <a id="msg-menu" href="#" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-haspopup="true" aria-expanded="false">
                            Hotspot
                            <span class="caret"></span>
                        </a>
                        <ul class="dropdown-menu scrollable-menu">
                            <li class="dropdown-header">Izaberi kolekciju podataka</li>
                            <li role="separator" class="divider"></li>
                            <li id="stat_voucher"><a href="?action=stat_voucher">Statistika vaučera</a></li>
                            <li id="stat_payment"><a href="?action=stat_payment">Statistika plaćanja</a></li>
                            <li role="separator" class="divider"></li>
                            <li id="list_hotspotop"><a href="?action=list_hotspotop">Lista HotSpot operatora</a></li>
                        </ul>
                    </li>
                    <li id="config-menu" class="dropdown">
                        <a id="config-menu" href="#" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-haspopup="true" aria-expanded="false">
                            Konfiguracija
                            <span class="caret"></span>
                        </a>
                        <ul class="dropdown-menu scrollable-menu">
                            <li class="dropdown-header">Izaberi kolekciju podataka</li>
                            <li role="separator" class="divider"></li>
                            <li id="list_sites"><a href="?action=list_sites">Lista čvorišta na ovom kontroleru</a></li>
                            <li id="stat_sysinfo"><a href="?action=stat_sysinfo">Informacije sistema</a></li>
                            <li id="list_self"><a href="?action=list_self">O ulogovanom korisniku</a></li>
                            <li role="separator" class="divider"></li>
                            <li id="list_settings"><a href="?action=list_settings">Lista podešavanja čvorišta</a></li>
                            <li id="list_admins"><a href="?action=list_admins">Lista administratora na trenutnom čvorištu</a></li>
                            <li role="separator" class="divider"></li>
                            <li id="list_wlanconf"><a href="?action=list_wlanconf">Lista WLan konfiguracija</a></li>
                            <li id="list_current_channels"><a href="?action=list_current_channels">Lista trenutnih kanala</a></li>
                            <li role="separator" class="divider"></li>
                            <li id="list_extension"><a href="?action=list_extension">Lista VoIP ekstenzija</a></li>
                            <li role="separator" class="divider"></li>
                            <li id="list_networkconf"><a href="?action=list_networkconf">Lista mrežnih podešavanja</a></li>
                            <li id="list_portconf"><a href="?action=list_portconf">Lista konfiguracija portova</a></li>
                            <li id="list_portforwarding"><a href="?action=list_portforwarding">Lista pravila forward-ovanih portova</a></li>
                            <li id="list_firewallgroups"><a href="?action=list_firewallgroups">Lista firewall grupa</a></li>
                            <li id="list_dynamicdns"><a href="?action=list_dynamicdns">Podešavanja Dynamic DNS-a</a></li>
                            <li role="separator" class="divider"></li>
                            <li id="list_country_codes"><a href="?action=list_country_codes">Lista kodova država</a></li>
                            <li role="separator" class="divider"></li>
                            <li id="list_backups"><a href="?action=list_backups">Lista automatskih backup-ova</a></li>
                            <?php if ($detected_controller_version != 'undetected' && version_compare($detected_controller_version, '5.5.19') >= 0) { ?>
                                <!-- Radius-related collections, only to be displayed when we have detected a capable controller version -->
                                <li role="separator" class="divider"></li>
                                <li id="list_radius_profiles"><a href="?action=list_radius_profiles">Lista Radius profila</a></li>
                                <li id="list_radius_accounts"><a href="?action=list_radius_accounts">Lista Radius naloga</a></li>
                            <?php } ?>
                        </ul>
                    </li>
                    <li id="msg-menu" class="dropdown">
                        <a id="msg-menu" href="#" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-haspopup="true" aria-expanded="false">
                            Poruke
                            <span class="caret"></span>
                        </a>
                        <ul class="dropdown-menu scrollable-menu">
                            <li class="dropdown-header">Izaberi kolekciju podataka</li>
                            <li role="separator" class="divider"></li>
                            <li id="list_alarms"><a href="?action=list_alarms">Lista alarma</a></li>
                            <li id="count_alarms"><a href="?action=count_alarms">Ukupan broj alarma</a></li>
                            <li id="count_active_alarms"><a href="?action=count_active_alarms">Ukupan broj aktivnih alarma</a></li>
                            <li role="separator" class="divider"></li>
                            <li id="list_events"><a href="?action=list_events">Lista događaja</a></li>
                            <?php if ($detected_controller_version != 'undetected' && version_compare($detected_controller_version, '5.9.10') >= 0) { ?>
                                <li role="separator" class="divider"></li>
                                <li id="stat_ips_events"><a href="?action=stat_ips_events">Lista IPS/IDS događaja</a></li>
                            <?php } ?>
                        </ul>
                    </li>
                <?php } ?>
                <!-- /data collection dropdowns -->
            </ul>
            <ul class="nav navbar-nav navbar-right">
                <li id="theme-menu" class="dropdown">
                    <a id="theme-menu" href="#" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-haspopup="true" aria-expanded="false">
                        <i class="fa fa-bars fa-lg"></i>
                    </a>
                    <ul class="dropdown-menu scrollable-menu">
                        <li id="info" data-toggle="modal" data-target="#about_modal"><a href="#"><i class="fa fa-info-circle"></i> O sistemu</a></li>
                        <li role="separator" class="divider"></li>
                        <li id="reset_session" data-toggle="tooltip" data-container="body" data-placement="left"
                            data-original-title="U nekim slučajevima pomaže pri ponovnom učitavanju podataka">
                            <a href="?reset_session=true"><i class="fa fa-sign-out"></i> Odjavi se</a>
                        </li>
                    </ul>
                </li>
            </ul>
        </div><!-- /.nav-collapse -->
    </div><!-- /.container-fluid -->
</nav><!-- /top navbar -->
<div class="container-fluid">
    <div id="alert_placeholder" style="display: none"></div>
    <!-- login_form, only to be displayed when we have no controller config -->
    <div id="login_form" style="display: none">
        <div class="col-xs-offset-1 col-xs-10 col-sm-offset-3 col-sm-6 col-md-offset-3 col-md-6 col-lg-offset-4 col-lg-4">
            <div class="panel panel-default">
                <div class="panel-body">
                    <h3 align="center">UniFi Kontroler prijava</h3>
                    <br>
                    <form method="post">
                        <?php if (empty($controller['user'])) : ?>
                            <div class="form-group">
                                <label for="input_controller_user">Korisničko ime</label>
                                <input type="text" id="input_controller_user" name="controller_user" class="form-control" placeholder="Korisničko ime kontrolera">
                            </div>
                        <?php endif; ?>
                        <?php if (empty($controller['password'])) : ?>
                            <div class="form-group">
                                <label for="input_controller_password">Lozinka</label>
                                <input type="password" id="input_controller_password" name="controller_password" class="form-control" placeholder="Lozinka kontrolera">
                            </div>
                        <?php endif; ?>
                        <?php if (empty($controller['url'])) : ?>
                            <div class="form-group">
                                <label for="input_controller_url">URL</label>
                                <input type="text" id="input_controller_url" name="controller_url" class="form-control" placeholder="https://<controller FQDN or IP>:8443">
                            </div>
                        <?php endif; ?>
                        <input type="submit" name="login" class="btn btn-primary pull-right" value="Prijava">
                    </form>
                </div>
            </div>
        </div>
    </div>
    <!-- /login_form -->
    <!-- data-panel, only to be displayed once a controller has been configured and an action has been selected, while loading we display a temp div -->
    <?php if (isset($_SESSION['unificookie']) && $action) { ?>
    <div id="output_panel_loading" class="text-center">
        <br>
        <h2><i class="fa fa-spinner fa-spin fa-fw"></i></h2>
    </div>
    <div id="output_panel" class="panel panel-default" style="display: none">
        <div class="panel-heading">
            <!-- site info, only to be displayed when a site has been selected -->
            <?php if ($site_id) { ?>
                ID čvorišta: <span id="span_site_id" class="label label-primary"></span>
                Naziv čvorišta: <span id="span_site_name" class="label label-primary"></span>
            <?php } ?>
            <!-- /site info -->
            <!-- selection, only to be displayed when a selection has been made -->
            <?php if ($selection) { ?>
                Kolekcija podataka: <span id="span_selection" class="label label-primary"></span>
            <?php } ?>
            <!-- /selection -->
            Način prikaza: <span id="span_output_format" class="label label-primary"></span>
            <!-- objects_count, only to be displayed when we have results -->
            <?php if ($objects_count !== '') { ?>
                Broj prikazanih objekata: <span id="span_objects_count" class="badge"></span>
            <?php } ?>
            <!-- /objects_count -->
        </div>
        <div class="panel-body">
            <!--only display panel content when an action has been selected-->
            <?php if ($action !== '' && isset($_SESSION['unificookie'])) { ?>
                <!-- present the timing results using an HTML5 progress bar -->
                <span id="span_elapsed_time"></span>
                <br>
                <div class="progress">
                    <div id="timing_login_perc" class="progress-bar progress-bar-warning" role="progressbar" aria-valuemin="0" aria-valuemax="100" data-toggle="tooltip" data-placement="bottom"></div>
                    <div id="timing_load_perc" class="progress-bar progress-bar-success" role="progressbar" aria-valuemin="0" aria-valuemax="100" data-toggle="tooltip" data-placement="bottom"></div>
                    <div id="timing_remain_perc" class="progress-bar progress-bar-primary" role="progressbar" aria-valuemin="0" aria-valuemax="100" data-toggle="tooltip" data-placement="bottom"></div>
                </div>
                <div id="toggle_buttons">
                    <button id="toggle-btn" type="button" class="btn btn-primary btn-xs"><i id="i_toggle-btn" class="fa fa-minus" aria-hidden="true"></i> Proširi/Skupi</button>
                    <button id="toggle-level2-btn" type="button" class="btn btn-primary btn-xs"><i id="i_toggle-level2-btn" class="fa fa-minus" aria-hidden="true"></i> Proširi/Skupi drugi nivo</button>
                    <br><br>
                </div>
                <div id="output" class="js-copy-container">
                    <button id="copy_to_clipboard_button" class="btn btn-xs js-copy-trigger" data-original-title="Iskopiraj u privremenu memoriju" data-clipboard-target="#pre_output" data-toggle="tooltip" data-placement="top"><i class="fa fa-copy"></i></button>
                    <pre id="pre_output" class="js-copy-target"><?php print_output($output_format, $data) ?></pre>
                </div>
            <?php } ?>
        </div>
    </div>
    <?php } ?>
    <!-- /data-panel -->
</div>
<!-- back-to-top button element -->
<a id="back-to-top" href="#" class="btn btn-primary back-to-top" role="button" title="Back to top" data-toggle="tooltip" data-placement="left"><i class="fa fa-chevron-up" aria-hidden="true"></i></a>
<!-- /back-to-top button element -->
<!-- About modal -->
<div class="modal fade" id="about_modal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="myModalLabel"><i class="fa fa-info-circle"></i> Osnovne informacije sistema</h4>
            </div>
            <div class="modal-body">
                <dl class="dl-horizontal col-sm-offset-2">
                    <dt>Korisničko ime</dt>
                    <dd><span id="span_controller_user" class="label label-primary"></span></dd>
                    <dt>URL kontrolera</dt>
                    <dd><span id="span_controller_url" class="label label-primary"></span></dd>
                    <dt>Verzija kontrolera</dt>
                    <dd><span id="span_controller_version" class="label label-primary"></span></dd>
                </dl>
                <hr>
                <dl class="dl-horizontal col-sm-offset-2">
                    <dt>PHP verzija</dt>
                    <dd><span id="span_php_version" class="label label-primary"></span></dd>
                    <dt>PHP memory_limit</dt>
                    <dd><span id="span_memory_limit" class="label label-primary"></span></dd>
                    <dt>PHP memory used</dt>
                    <dd><span id="span_memory_used" class="label label-primary"></span></dd>
                    <dt>cURL verzija</dt>
                    <dd><span id="span_curl_version" class="label label-primary"></span></dd>
                    <dt>OpenSSL verzija</dt>
                    <dd><span id="span_openssl_version" class="label label-primary"></span></dd>
                    <dt>Operativni sistem</dt>
                    <dd><span id="span_os_version" class="label label-primary"></span></dd>
                </dl>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" data-dismiss="modal">Zatvori</button>
            </div>
        </div>
    </div>
</div>
<!-- latest compiled and minified JavaScript versions, loaded from CDN's, now including Source Integrity hashes, just in case... -->
<script src="../assets/admin/js/jquery-2.2.4.min.js"></script>
<script src="../assets/admin/js/bootstrap.min.js"></script>
<script src="../assets/admin/js/jquery.jsonview.min.js"></script>
<script src="../assets/admin/js/clipboard.min.js"></script>
<script>
/**
 * populate some global Javascript variables with PHP output for cleaner code
 */
var alert_message       = '<?php echo $alert_message ?>',
    show_login          = '<?php echo $show_login ?>',
    action              = '<?php echo $action ?>',
    site_id             = '<?php echo $site_id ?>',
    site_name           = '<?php echo htmlspecialchars($site_name) ?>',
    controller_id       = '<?php echo $controller_id ?>',
    output_format       = '<?php echo $output_format ?>',
    selection           = '<?php echo $selection ?>',
    objects_count       = '<?php echo $objects_count ?>',
    timing_login_perc   = '<?php echo $login_perc ?>',
    time_after_login    = '<?php echo $time_after_login ?>',
    timing_load_perc    = '<?php echo $load_perc ?>',
    time_for_load       = '<?php echo ($time_after_load - $time_after_login) ?>',
    timing_remain_perc  = '<?php echo $remain_perc ?>',
    timing_total_time   = '<?php echo $time_total ?>',
    php_version         = '<?php echo (phpversion()) ?>',
    memory_limit        = '<?php echo (ini_get('memory_limit')) ?>',
    memory_used         = '<?php echo round(memory_get_peak_usage(false) / 1024 / 1024, 2) . 'M' ?>',
    curl_version        = '<?php echo $curl_version ?>',
    openssl_version     = '<?php echo $openssl_version ?>',
    os_version          = '<?php echo (php_uname('s') . ' ' . php_uname('r')) ?>',
    api_browser_version = '<?php echo API_BROWSER_VERSION ?>',
    api_class_version   = '<?php echo API_CLASS_VERSION ?>',
    controller_user     = '<?php if (isset($controller['user'])) echo $controller['user'] ?>',
    controller_url      = '<?php if (isset($controller['url'])) echo $controller['url'] ?>',
    controller_version  = '<?php if (isset($detected_controller_version)) echo $detected_controller_version ?>';

$(document).ready(function () {
    /**
     * we hide the loading div and show the output panel
     */
    $('#output_panel_loading').hide();
    $('#output_panel').show();

    /**
     * if needed we display the login form
     */
    if (show_login == 1 || show_login == 'true') {
        $('#login_form').show();
    }

    /**
     * update dynamic elements in the DOM using some of the above variables
     */
    $('#alert_placeholder').html(alert_message);
    $('#alert_placeholder').fadeIn(1000);
    $('#span_site_id').html(site_id);
    $('#span_site_name').html(site_name);
    $('#span_output_format').html(output_format);
    $('#span_selection').html(selection);
    $('#span_objects_count').html(objects_count);
    $('#span_elapsed_time').html('totalno proteklo vreme: ' + timing_total_time + ' sekundi');

    $('#timing_login_perc').attr('aria-valuenow', timing_login_perc);
    $('#timing_login_perc').css('width', timing_login_perc + '%');
    $('#timing_login_perc').attr('data-original-title', time_after_login + ' sekundi');
    $('#timing_login_perc').html('vreme API prijave');
    $('#timing_load_perc').attr('aria-valuenow', timing_load_perc);
    $('#timing_load_perc').css('width', timing_load_perc + '%');
    $('#timing_load_perc').attr('data-original-title', time_for_load + ' sekundi');
    $('#timing_load_perc').html('vreme učitavanja podataka');
    $('#timing_remain_perc').attr('aria-valuenow', timing_remain_perc);
    $('#timing_remain_perc').css('width', timing_remain_perc + '%');
    $('#timing_remain_perc').attr('data-original-title', 'PHP overhead: ' + timing_remain_perc + '%');
    $('#timing_remain_perc').html('PHP overhead');

    $('#span_api_browser_version').html(api_browser_version);
    $('#span_api_class_version').html(api_class_version);
    $('#span_controller_user').html(controller_user);
    $('#span_controller_url').html(controller_url);
    $('#span_controller_version').html(controller_version);
    $('#span_php_version').html(php_version);
    $('#span_curl_version').html(curl_version);
    $('#span_openssl_version').html(openssl_version);
    $('#span_os_version').html(os_version);
    $('#span_memory_limit').html(memory_limit);
    $('#span_memory_used').html(memory_used);

    /**
     * highlight and mark the selected options in the dropdown menus for $controller_id, $action, $site_id, $theme and $output_format
     *
     * NOTE:
     * these actions are performed conditionally
     */
    (action != '') ? $('#' + action).addClass('active').find('a').append(' <i class="fa fa-check"></i>') : false;
    (site_id != '') ? $('#' + site_id).addClass('active').find('a').append(' <i class="fa fa-check"></i>') : false;
    (controller_id != '') ? $('#controller_' + controller_id).addClass('active').find('a').append(' <i class="fa fa-check"></i>') : false;

    /**
     * these two options have default values so no tests needed here
     */
    $('#' + output_format).addClass('active').find('a').append(' <i class="fa fa-check"></i>');

    /**
     * initialise the jquery-jsonview library, only when required
     */
    if (output_format == 'json_color') {
        $('#toggle_buttons').show();
        $('#pre_output').JSONView($('#pre_output').text());

        /**
         * the expand/collapse toggle buttons to control the json view
         */
        $('#toggle-btn').on('click', function () {
            $('#pre_output').JSONView('toggle');
            $('#i_toggle-btn').toggleClass('fa-plus').toggleClass('fa-minus');
            $(this).blur();
        });

        $('#toggle-level2-btn').on('click', function () {
            $('#pre_output').JSONView('toggle', 2);
            $('#i_toggle-level2-btn').toggleClass('fa-plus').toggleClass('fa-minus');
            $(this).blur();
        });
    }

    /**
     * only now do we display the output
     */
    $('#output').show();

    /**
     * check latest version of API browser tool and inform user when it's more recent than the current,
     * but only when the "about" modal is opened
     */
    $('#about_modal').on('shown.bs.modal', function (e) {
        $.getJSON('https://api.github.com/repos/Art-of-WiFi/UniFi-API-browser/releases/latest', function (external) {
            if (api_browser_version != '' && typeof(external.tag_name) !== 'undefined') {
                if (api_browser_version < external.tag_name.substring(1)) {
                    $('#span_api_browser_update').html('an update is available: ' + external.tag_name.substring(1));
                    $('#span_api_browser_update').removeClass('label-success').addClass('label-warning');
                } else if (api_browser_version == external.tag_name.substring(1)) {
                    $('#span_api_browser_update').html('up to date');
                } else {
                    $('#span_api_browser_update').html('bleeding edge!');
                    $('#span_api_browser_update').removeClass('label-success').addClass('label-danger');
                }
            }
        }).fail(function (d, textStatus, error) {
            $('#span_api_browser_update').html('error checking updates');
            $('#span_api_browser_update').removeClass('label-success').addClass('label-danger');
            console.error('getJSON failed, status: ' + textStatus + ', error: ' + error);
        });;
    })

    /**
     * and reset the span again when the "about" modal is closed
     */
    $('#about_modal').on('hidden.bs.modal', function (e) {
        $('#span_api_browser_update').html('<i class="fa fa-spinner fa-spin fa-fw"></i> checking for updates</span>');
        $('#span_api_browser_update').removeClass('label-warning').removeClass('label-danger').addClass('label-success');
    })

    /**
     * initialize the "copy to clipboard" function, "borrowed" from the UserFrosting framework
     */
    if (typeof $.uf === 'undefined') {
        $.uf = {};
    }

    $.uf.copy = function (button) {
        var _this = this;

        var clipboard = new ClipboardJS(button, {
            text: function (trigger) {
                var el = $(trigger).closest('.js-copy-container').find('.js-copy-target');
                if (el.is(':input')) {
                    return el.val();
                } else {
                    return el.html();
                }
            }
        });

        clipboard.on('success', function (e) {
            setTooltip(e.trigger, 'Iskopirano!');
            hideTooltip(e.trigger);
        });

        clipboard.on('error', function (e) {
            setTooltip(e.trigger, 'Greška pri kopiranju!');
            hideTooltip(e.trigger);
            console.log('Copy to clipboard failed, most probably the selection is too large');
        });

        function setTooltip(btn, message) {
            $(btn)
            .attr('data-original-title', message)
            .tooltip('show');
        }

        function hideTooltip(btn) {
            setTimeout(function () {
                $(btn).tooltip('hide')
                .attr('data-original-title', 'Iskopiraj u privremenu memoriju');
            }, 1000);
        }

        /**
         * tooltip trigger
         */
        $(button).tooltip({
            trigger: 'hover'
        });
    };

    /**
     * link the copy button
     */
    $.uf.copy('.js-copy-trigger');

    /**
     * hide "copy to clipboard" button if the ClipboardJS function isn't supported or the output format isn't supported
     */
    var unsupported_formats = [
        'json',
        'php_array',
        'php_var_dump',
        'php_var_export'
    ];
    if (!ClipboardJS.isSupported() || $.inArray(output_format, unsupported_formats) === -1) {
        $('.js-copy-trigger').hide();
    }

    /**
     * manage display of the "back to top" button element
     */
    $(window).scroll(function () {
        if ($(this).scrollTop() > 50) {
            $('#back-to-top').fadeIn();
        } else {
            $('#back-to-top').fadeOut();
        }
    });

    /**
     * scroll body to 0px (top) on click on the "back to top" button
     */
    $('#back-to-top').click(function () {
        $('#back-to-top').tooltip('hide');
        $('body,html').animate({
            scrollTop: 0
        }, 500);
        return false;
    });

    $('#back-to-top').tooltip('show');

    /**
     * enable Bootstrap tooltips
     */
    $(function () {
        $('[data-toggle="tooltip"]').tooltip()
    })
});
</script>
</body>
</html>