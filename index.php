<?php

require_once './vendor/autoload.php';

error_reporting(0);
define('SITE_BASE', __DIR__);
define('SITE_URI', $_SERVER['HTTP_REFERER']);

// Make Application instance
$app         = new \WingWifi\Application();
$requestData = new \WingWifi\Utilities\RequestDataUtility();
echo $app->render($requestData);
