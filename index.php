<?php

require_once './vendor/autoload.php';

error_reporting(0);
define('SITE_BASE', __DIR__);
define('SITE_URI',  empty($_SERVER['HTTPS']) ? 'http://' : 'https://' . $_SERVER['SERVER_NAME']);

// Make Application instance
$app         = new \WingWifi\Application();
$requestData = new \WingWifi\Utilities\RequestDataUtility();
echo $app->render($requestData);
