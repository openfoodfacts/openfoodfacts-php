<?php

use GuzzleHttp\Exception\ServerException;
use OpenFoodFacts\FilesystemTrait;
use PHPUnit\Framework\TestCase;

use OpenFoodFacts\Api;
use OpenFoodFacts\Collection;
use OpenFoodFacts\Document\FoodProduct;
use OpenFoodFacts\Document;
use OpenFoodFacts\Exception\{
    ProductNotFoundException,
    BadRequestException
};


use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Psr16Cache;
use Symfony\Component\Console\Logger\ConsoleLogger;

class ApiFoodCacheTest extends TestCase
{
    use FilesystemTrait;

    /**
     * @var Api
     */
    private $api;


    protected function setUp()
    {
        @rmdir('tests/tmp');
        @mkdir('tests/tmp');
        @mkdir('tests/tmp/cache');
        $log = new Logger('name');
        $log->pushHandler(new StreamHandler('log/test.log'));
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

        $api = new Api('food', 'fr-en', $log, $httpClient, $cache);
        $this->assertInstanceOf(Api::class, $api);
        $this->api = $api;

    }

    public function testApi(): void
    {

        $prd = $this->api->getProduct('3057640385148');

        $this->assertInstanceOf(FoodProduct::class, $prd);
        $this->assertInstanceOf(Document::class, $prd);

        $this->assertTrue(isset($prd->product_name));
        $this->assertNotEmpty($prd->product_name);

        try {
            $product = $this->api->getProduct('305764038514800');
            $this->assertTrue(false);
        } catch (ProductNotFoundException $e) {
            $this->assertTrue(true);
        }

        try {
            $result = $this->api->downloadData('tests/mongodb', 'nopeFile');
            $this->assertTrue(false);
        } catch (BadRequestException $e) {
            $this->assertEquals($e->getMessage(), 'File type not recognized!');
        }

        // $result = $this->api->downloadData('tests/tmp/mongodb');
        // $this->assertTrue(true);
    }

    public function testApiCollection(): void
    {

        $collection = $this->api->getByFacets([]);
        $this->assertInstanceOf(Collection::class, $collection);
        $this->assertEquals($collection->pageCount(), 0);

        try {
            $collection = $this->api->getByFacets(['trace' => 'egg', 'country' => 'france'], 3);
            $this->assertTrue(false);
        } catch (\PHPUnit\Framework\Error\Notice $e) {
            $this->assertEquals($e->getMessage(), 'OpenFoodFact - Your request has been redirect');
        }

        $collection = $this->api->getByFacets(['trace' => 'eggs', 'country' => 'france'], 3);
        $this->assertInstanceOf(Collection::class, $collection);
        $this->assertEquals($collection->pageCount(), 20);
        $this->assertEquals($collection->getPage(), 3);
        $this->assertEquals($collection->getSkip(), 40);
        $this->assertEquals($collection->getPageSize(), 20);
        $this->assertGreaterThan(1000, $collection->searchCount());

        foreach ($collection as $key => $doc) {
            if ($key > 1) {
                break;
            }

            $this->assertInstanceOf(FoodProduct::class, $doc);
            $this->assertInstanceOf(Document::class, $doc);

        }

    }

    public function testApiSearch(): void
    {

        $collection = $this->api->search('volvic', 3, 30);
        $this->assertInstanceOf(Collection::class, $collection);
        $this->assertEquals($collection->pageCount(), 30);
        $this->assertGreaterThan(100, $collection->searchCount());

    }


    public function testFacets(): void
    {

        $collection = $this->api->getIngredients();
        $this->assertInstanceOf(Collection::class, $collection);
        $this->assertEquals($collection->pageCount(), 20);
        $this->assertEquals($collection->getPageSize(), 20);
        $this->assertGreaterThan(70000, $collection->searchCount());

        try {
            $collection = $this->api->getIngredient();
            $this->assertInstanceOf(Collection::class, $collection);
            $this->assertTrue(false);
        } catch (BadRequestException $e) {
            $this->assertEquals($e->getMessage(), 'Facet "ingredient" not found');
        }

        $collection = $this->api->getPurchase_places();
        $this->assertInstanceOf(Collection::class, $collection);
        $collection = $this->api->getPackaging_codes();
        $this->assertInstanceOf(Collection::class, $collection);
        $collection = $this->api->getEntry_dates();
        $this->assertInstanceOf(Collection::class, $collection);

        try {
            $collection = $this->api->getIngredient();
            $this->assertTrue(false);
        } catch (BadRequestException $e) {
            $this->assertEquals($e->getMessage(), 'Facet "ingredient" not found');
        }

        try {
            $collection = $this->api->nope();
        } catch (\Exception $e) {
            $this->assertTrue(true);
        }
    }

    protected function tearDown()
    {
        $this->recursiveDeleteDirectory('tests/tmp');
    }

}
