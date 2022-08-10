<?php

namespace OpenFoodFactsTests;

use GuzzleHttp;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use OpenFoodFacts\Exception\ProductNotFoundException;
use OpenFoodFacts\FilesystemTrait;
use OpenFoodFacts\Api;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Psr16Cache;

class ApiFoodCacheTest extends ApiFoodTest
{
    use FilesystemTrait;

    protected function setUp(): void
    {
        parent::setUp();
        @rmdir('tests/tmp');
        @mkdir('tests/tmp');
        @mkdir('tests/tmp/cache');
        $psr6Cache = new FilesystemAdapter(sprintf('testrun_%u', rand(0, 1000)), 10, 'tests/tmp/cache');
        $cache     = new Psr16Cache($psr6Cache);

        $httpClient = new GuzzleHttp\Client([
//            "http_errors" => false, // MUST not use as it crashes error handling
            'Connection' => 'close',
            CURLOPT_FORBID_REUSE => true,
            CURLOPT_FRESH_CONNECT => true,
            'defaults' => [
                'headers' => [
                    'CURLOPT_USERAGENT' => 'OFF - PHP - SDK - Unit Test',
                ],
            ],
        ]);

        $api = new Api('food', 'fr-en', $this->log, $httpClient, $cache);
        $this->assertInstanceOf(Api::class, $api);
        $this->api = $api;
    }

    public function testItDoesNotCachesProductNotFoundResponses(): void
    {
        $this->expectException(ProductNotFoundException::class);

        $barCode = "3760314500074";
        $psr6Cache = new FilesystemAdapter(sprintf('testrun_%u', rand(0, 1000)), 10, 'tests/tmp/cache');
        $cache     = new Psr16Cache($psr6Cache);
        
        /**
         * Mock Guzzle Client to avoid hitting the server for real.
         * 
         * @see https://docs.guzzlephp.org/en/stable/testing.html#mock-handler
         */
        $mock = new MockHandler([
            new Response(200, [], (string) json_encode([
                "code" => $barCode,
                "status" => 0,
                "status_verbose" => "product not found",
            ])),
        ]);

        $handlerStack = HandlerStack::create($mock);

        $httpClient = new GuzzleHttp\Client([
            'handler' => $handlerStack,
            'Connection' => 'close',
            CURLOPT_FORBID_REUSE => true,
            CURLOPT_FRESH_CONNECT => true,
            'defaults' => [
                'headers' => [
                    'CURLOPT_USERAGENT' => 'OFF - PHP - SDK - Unit Test',
                ],
            ],
        ]);

        $api = new Api("food", "fr-en", $this->log, $httpClient, $cache);
        $api->getProduct($barCode);
        $cacheKey = md5("https://fr-en.openfoodfacts.org/api/v0/product/$barCode.json");

        self::assertFalse($cache->has($cacheKey));
    }

    public function testItCachesProductFoundResponses(): void
    {
        $barCode = "737628064502";
        $psr6Cache = new FilesystemAdapter(sprintf('testrun_%u', rand(0, 1000)), 10, 'tests/tmp/cache');
        $cache     = new Psr16Cache($psr6Cache);
        
        /**
         * Mock Guzzle Client to avoid hitting the server for real.
         * 
         * @see https://docs.guzzlephp.org/en/stable/testing.html#mock-handler
         */
        $mock = new MockHandler([
            new Response(200, [], (string) file_get_contents(__DIR__ . "/sample/$barCode.json")),
        ]);

        $handlerStack = HandlerStack::create($mock);

        $httpClient = new GuzzleHttp\Client([
            'handler' => $handlerStack,
            'Connection' => 'close',
            CURLOPT_FORBID_REUSE => true,
            CURLOPT_FRESH_CONNECT => true,
            'defaults' => [
                'headers' => [
                    'CURLOPT_USERAGENT' => 'OFF - PHP - SDK - Unit Test',
                ],
            ],
        ]);

        $api = new Api("food", "fr-en", $this->log, $httpClient, $cache);
        $api->getProduct($barCode);
        $cacheKey = md5("https://fr-en.openfoodfacts.org/api/v0/product/$barCode.json");

        self::assertTrue($cache->has($cacheKey));
    }
}
