<?php
/**
 * Base controller for page loading.
 */

require_once './vendor/autoload.php';

define('SITE_BASE', str_replace('src', '', __DIR__));

// Make Application instance
$app = new \WingWifi\Application();
