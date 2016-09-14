<?php
ini_set('display_errors', '1');
set_time_limit(1800); // half hour
require_once __DIR__ . '/../vendor/autoload.php';
umask(0000);

$app = new \Nathejk\Scan\Application(array('debug' => true, 'config' => $_ENV));
$app->run();
