<?php

namespace OpenFoodFactsTests\Unit\OpenFoodFacts;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use OpenFoodFacts\Api;
use OpenFoodFacts\Exception\InvalidParameterException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Psr16Cache;

class ApiEnvironmentTest extends TestCase
{
    public static function flavors(): array
    {
        return [
            ['food', 'openfoodfacts'],
            ['beauty', 'openbeautyfacts'],
            ['pet', 'openpetfoodfacts'],
            ['product', 'openproductsfacts'],
        ];
    }

    #[DataProvider('flavors')]
    public function testTestModePreservesFlavorAndGeography(string $flavor, string $domain): void
    {
        $history = [];
        $stack = HandlerStack::create(new MockHandler([
            new Response(200, [], '{"status":"success","product":{"code":"123"}}'),
            new Response(200, [], '{"status":"success"}'),
        ]));
        $stack->push(Middleware::history($history));
        $api = new Api('Unit test', $flavor, 'fr-en', null, new Client(['handler' => $stack]));
        $api->getProduct('123');
        $api->activeTestMode();
        $api->activeTestMode();
        $api->authentification('user', 'password');
        $api->updateProduct('123', ['product_name' => 'Test']);

        $this->assertSame('fr-en', $api->geography);
        $this->assertSame($flavor, $api->getCurrentApi());
        $this->assertSame('fr-en.' . $domain . '.org', $history[0]['request']->getUri()->getHost());
        $this->assertFalse($history[0]['request']->hasHeader('Authorization'));
        $this->assertSame('fr-en.' . $domain . '.net', $history[1]['request']->getUri()->getHost());
        $this->assertSame('Basic ' . base64_encode('off:off'), $history[1]['request']->getHeaderLine('Authorization'));
    }

    public function testUnknownFlavorIsRejected(): void
    {
        $this->expectException(InvalidParameterException::class);
        new Api('Unit test', 'unknown'); // NOSONAR: Construction is expected to throw; no object can be used.
    }

    public static function readMethods(): array
    {
        return [
            ['getProduct', ['123']],
            ['search', ['water']],
            ['getByFacets', [['brand' => 'volvic']]],
            ['getBrands', []],
        ];
    }

    #[DataProvider('readMethods')]
    public function testSharedCacheSeparatesEnvironmentsAndFlavors(string $method, array $arguments): void
    {
        $mock = new MockHandler();
        foreach (['production', 'staging', 'beauty'] as $name) {
            $product = ['code' => '123', 'product_name' => $name, 'name' => $name];
            $mock->append(new Response(200, [], (string) json_encode([
                'status' => 'success', 'product' => $product,
                'products' => [$product], 'tags' => [$product], 'count' => 1,
            ])));
        }
        $history = [];
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($history));
        $client = new Client(['handler' => $stack]);
        $cache = new Psr16Cache(new ArrayAdapter());
        $api = new Api('Unit test', 'food', 'world', null, $client, $cache);
        $production = $api->$method(...$arguments);
        $this->assertEquals($production, $api->$method(...$arguments));
        $api->activeTestMode();
        $staging = $api->$method(...$arguments);
        $this->assertNotEquals($production, $staging);
        $this->assertEquals($staging, $api->$method(...$arguments));

        $other = new Api('Unit test', 'beauty', 'world', null, $client, $cache);
        $this->assertNotEquals($production, $other->$method(...$arguments));
        $productionApi = new Api('Unit test', 'food', 'world', null, $client, $cache);
        $this->assertEquals($production, $productionApi->$method(...$arguments));
        $this->assertIsArray($history);
        $this->assertCount(3, $history);
    }
}
