<?php
require __DIR__ . '/../vendor/autoload.php';

header('Access-Control-Allow-Origin: http://client.dtl.name');

$predis = new Predis\Client();
$predis->set('stopPolling', time());
