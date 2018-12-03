<?php

require_once './vendor/autoload.php';

define('SITE_BASE', str_replace('src', '', __DIR__));

// Make Application instance
$app         = new \WingWifi\Application();
$requestData = new \WingWifi\Utilities\RequestDataUtility();
echo $app->render($requestData);
