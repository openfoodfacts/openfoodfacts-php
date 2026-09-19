<?php

namespace OpenFoodFactsTests\Integration;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use OpenFoodFacts\Api;
use OpenFoodFacts\Collection;
use OpenFoodFacts\Document;
use OpenFoodFacts\Document\FoodDocument;
use OpenFoodFacts\Exception\BadRequestException;
use OpenFoodFacts\Exception\MissingCredentialsException;
use OpenFoodFacts\Exception\ProductNotFoundException;
use OpenFoodFacts\FilesystemTrait;
use OpenFoodFactsTests\Helper;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class ApiFoodTest extends TestCase
{
    use FilesystemTrait;

    private const DEFAULT_BARCODE = '3057640385148';
    /** @var Api */
    protected $api;
    protected NullLogger $log;

    protected function setUp(): void
    {
        $this->log = new NullLogger();

        $this->api = new Api('Integration test', 'food', 'fr-en', $this->log);
        $testFolder       = 'tests/tmp';
        if (file_exists($testFolder)) {
            rmdir($testFolder);
        }
        mkdir($testFolder, 0755);

    }

    public function testApiNotFound(): void
    {
        $prd = Helper::getProductWithCache($this->api, self::DEFAULT_BARCODE);

        $this->assertInstanceOf(FoodDocument::class, $prd);
        $this->assertInstanceOf(Document::class, $prd);
        $this->assertTrue(isset($prd->product_name));
        $this->assertNotEmpty($prd->product_name);

        $this->expectException(ProductNotFoundException::class);
        $this->api->getProduct('305764038514800');
    }

    public function testApiBadRequest(): void
    {
        $prd = Helper::getProductWithCache($this->api, self::DEFAULT_BARCODE);

        $this->assertInstanceOf(FoodDocument::class, $prd);
        $this->assertInstanceOf(Document::class, $prd);
        $this->assertTrue(isset($prd->product_name));
        $this->assertNotEmpty($prd->product_name);

        $this->expectException(BadRequestException::class);
        $this->api->downloadData('tests/mongodb', 'nopeFile');
    }


    public function testApiCollection(): void
    {
        $collection = $this->api->getByFacets([]);
        $this->assertInstanceOf(Collection::class, $collection);
        $this->assertEquals(0, $collection->pageCount());

        // Stop here and mark this test as incomplete.
        $this->markTestIncomplete(
            'This test is bad -> we should not test against live APIs?',
        );

        $this->api->getByFacets(['trace' => 'egg', 'country' => 'france'], 3);

        $collection = $this->api->getByFacets(['trace' => 'eggs', 'country' => 'france'], 3);
        $this->assertInstanceOf(Collection::class, $collection);
        $this->assertEquals(Collection::defaultPageSize, $collection->pageCount());
        $this->assertEquals(3, $collection->getPage());
        $this->assertEquals(Collection::defaultPageSize * 2, $collection->getSkip());
        $this->assertEquals(Collection::defaultPageSize, $collection->getPageSize());
        $this->assertGreaterThan(1000, $collection->searchCount());

        foreach ($collection as $key => $doc) {
            if ($key > 1) {
                break;
            }
            $this->assertInstanceOf(FoodDocument::class, $doc);
        }
    }

    public function testApiAddProduct(): void
    {
        $handler = new MockHandler([
            new Response(200, [], '{"status":0,"status_verbose":"no user credentials"}'),
        ]);
        $this->api = new Api('Integration test', clientInterface: new Client([
            'handler' => HandlerStack::create($handler),
        ]));
        $this->api->activeTestMode();
        $postData = ['code' => self::DEFAULT_BARCODE, 'product_name' => 'Test product'];

        $this->expectException(MissingCredentialsException::class);
        $this->api->addNewProduct($postData);
    }

    public function testApiAddProductException(): void
    {
        $this->api->activeTestMode();

        $this->expectException(BadRequestException::class);
        $this->api->addNewProduct(['product_name' => 'Test product']);
    }

    public function testApiAddImageFieldNotValidException(): void
    {
        $this->api->activeTestMode();
        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('ImageField not valid!');
        $this->api->uploadImage(self::DEFAULT_BARCODE, 'fronts', 'nothing');
    }

    public function testApiAddImageImageNotFoundException(): void
    {
        $this->api->activeTestMode();
        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('Image not found');
        $this->api->uploadImage(self::DEFAULT_BARCODE, 'front', 'nothing');
    }

    public function testApiSearch(): void
    {
        $this->markTestIncomplete(
            'This test is bad -> we should not test against live APIs?',
        );
        $collection = $this->api->search('volvic', 3, 30);
        $this->assertInstanceOf(Collection::class, $collection);
        $this->assertEquals(30, $collection->pageCount());
        $this->assertGreaterThan(100, $collection->searchCount());
    }

    public function testFacets(): void
    {
        $this->markTestSkipped('Skipped due to intermittent issues at calling API. Replace with mocks?');

        $collection = $this->api->getIngredients();
        $this->assertInstanceOf(Collection::class, $collection);
        $this->assertEquals(Collection::defaultPageSize, $collection->pageCount());
        $this->assertEquals(Collection::defaultPageSize, $collection->getPageSize());
        $this->assertGreaterThan(70000, $collection->searchCount());


        $collection = $this->api->getPurchase_places();
        $this->assertInstanceOf(Collection::class, $collection);
        $collection = $this->api->getPackaging_codes();
        $this->assertInstanceOf(Collection::class, $collection);
        $collection = $this->api->getEntry_dates();
        $this->assertInstanceOf(Collection::class, $collection);
    }

    protected function tearDown(): void
    {
        $this->recursiveDeleteDirectory('tests/tmp');
    }
}
