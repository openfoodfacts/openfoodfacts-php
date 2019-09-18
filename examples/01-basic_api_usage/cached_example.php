<?php
include '../../vendor/autoload.php';
$logger = new \Monolog\Logger('test');
$httpClient = new \GuzzleHttp\Client();
// the PSR-6 cache object that you want to use
$psr6Cache = new FilesystemAdapter();
$psr16Cache = new Psr16Cache($psr6Cache);
$api = new \OpenFoodFacts\Api('food', 'world', $logger, $httpClient, $psr16Cache);
$product = $api->getProduct(rand(1, 50));
$e =1;