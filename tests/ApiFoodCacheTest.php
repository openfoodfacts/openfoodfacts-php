<?php

namespace OpenFoodFactsTests;

use GuzzleHttp;
use OpenFoodFacts\Api;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Psr16Cache;

class ApiFoodCacheTest extends ApiFoodTest
{
    use FilesystemTrait;

    protected function setUp(): void
    {
        parent::setUp();
        self::cleanTestFolder();
        $psr6Cache = new FilesystemAdapter(sprintf('testrun_%u', rand(0, 1000)), 10, 'tests/tmp/cache');
        $cache     = new Psr16Cache($psr6Cache);

        $httpClient = new GuzzleHttp\Client([
//            "http_errors" => false, // MUST not use as it crashes error handling
            'Connection' => 'close',
            CURLOPT_FORBID_REUSE => true,
            CURLOPT_FRESH_CONNECT => true,
            'defaults' => [
                'headers' => [
                    CURLOPT_USERAGENT => 'OFF - PHP - SDK - Unit Test',
                ],
            ],
        ]);

        $api = new Api('SDK Unit test', 'food', 'fr-en', $this->log, $httpClient, $cache);
        $this->assertInstanceOf(Api::class, $api);
        $this->api = $api;
    }
}
