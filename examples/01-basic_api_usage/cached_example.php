<?php
include '../../vendor/autoload.php';
$logger = new \Monolog\Logger('test');
$httpClient = new \GuzzleHttp\Client();

$api = new \OpenFoodFacts\Api('food', 'world', $logger, $httpClient);
$product = $api->getProduct(rand(1, 50));
$e =1;