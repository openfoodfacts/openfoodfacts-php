<?php

namespace OpenFoodFactsTests;

use OpenFoodFacts\FilesystemTrait;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use OpenFoodFacts\Api;
use OpenFoodFacts\Collection;
use OpenFoodFacts\Document\FoodDocument;
use OpenFoodFacts\Document;
use OpenFoodFacts\Exception\ProductNotFoundException;
use OpenFoodFacts\Exception\BadRequestException;
use PHPUnit\Util\Exception;
use Psr\Log\NullLogger;

class ApiFoodTest extends TestCase
{
    use FilesystemTrait;

    /** @var Api */
    protected $api;
    /**
     * @var NullLogger|MockObject
     */
    protected $log;

    protected function setUp(): void
    {
        $this->log = $this->createMock(NullLogger::class);

        $this->api = new Api('food', 'fr-en', $this->log);
        $testFolder       = 'tests/tmp';
        if (file_exists($testFolder)) {
            rmdir($testFolder);
        }
        mkdir($testFolder, 0755);
    }

    public function testApiNotFound(): void
    {
        $prd = Helper::getProductWithCache($this->api, '3057640385148');

        $this->assertInstanceOf(FoodDocument::class, $prd);
        $this->assertInstanceOf(Document::class, $prd);
        $this->assertTrue(isset($prd->product_name));
        $this->assertNotEmpty($prd->product_name);

        $this->expectException(ProductNotFoundException::class);
        $this->api->getProduct('305764038514800');
    }

    public function testApiBadRequest(): void
    {
        $prd = Helper::getProductWithCache($this->api, '3057640385148');

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
        $this->assertEquals($collection->pageCount(), 0);

        // Check redirect
        $this->log
            ->expects($this->once())
            ->method('warning')
            ->with('OpenFoodFact - The url : https://fr-en.openfoodfacts.org/country/france/trace/egg/3.json has been redirect to https://fr-en.openfoodfacts.org/country/france/trace/eggs.json')
        ;

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
        $this->api->activeTestMode();
        $prd = Helper::getProductWithCache($this->api, '3057640385148');
        $this->assertInstanceOf(FoodDocument::class, $prd);
        $this->assertInstanceOf(Document::class, $prd);

        $postData = ['code' => $prd->code, 'product_name' => $prd->product_name];

        $this->assertTrue($this->api->addNewProduct($postData));
    }

    public function testApiAddProductException(): void
    {
        $this->api->activeTestMode();
        $prd = Helper::getProductWithCache($this->api, '3057640385148');

        $this->expectException(BadRequestException::class);
        $this->api->addNewProduct(['product_name' => $prd->product_name]);

        $result   = $this->api->addNewProduct(['code' => '', 'product_name' => $prd->product_name]);
        $this->assertTrue(is_string($result));
        $this->assertEquals($result, 'no code or invalid code');
    }

    public function testApiAddImageFieldNotValidException(): void
    {
        $this->api->activeTestMode();
        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('ImageField not valid!');
        $this->api->uploadImage('3057640385148', 'fronts', 'nothing');
    }

    public function testApiAddImageImageNotFoundException(): void
    {
        $this->api->activeTestMode();
        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('Image not found');
        $this->api->uploadImage('3057640385148', 'front', 'nothing');
    }

    // public function testApiAddRandomImage(): void
    // {
    //     $this->api->activeTestMode();
    //     $prd = Helper::getProductWithCache($this->api, '3057640385148');
    //     $this->assertInstanceOf(FoodDocument::class, $prd);
    //     $file1 = $this->createRandomImage();
    //     $this->assertTrue(file_exists($file1) && filesize($file1) > 0);

    //     $result = $this->api->uploadImage('3057640385148', 'front', $file1);
    //     $this->assertArrayHasKey('status', $result);
    //     if ($result['status'] === 'status ok') {
    //         $this->assertEquals($result['status'], 'status ok');
    //         $this->assertTrue(isset($result['imagefield']));
    //         $this->assertTrue(isset($result['image']));
    //         $this->assertTrue(isset($result['image']['imgid']));
    //     } else {
    //         $this->assertEquals($result['status'], 'status not ok');
    //         $this->assertArrayHasKey('imgid', $result);
    //         $this->assertArrayHasKey('debug', $result);
    //         $this->assertStringContainsString($result['debug'], 'product_id: 3057640385148 - user_id:  - imagefield: front_fr - we have already received an image with this file size: ');
    //         $this->assertArrayHasKey('error', $result);
    //         $this->assertSame($result['error'], 'This picture has already been sent.');

    //         $this->addWarning('Impossible to verify the upload image');
    //     }
    // }

    public function testApiSearch(): void
    {
        $collection = $this->api->search('volvic', 3, 30);
        $this->assertInstanceOf(Collection::class, $collection);
        $this->assertEquals(30, $collection->pageCount());
        $this->assertGreaterThan(100, $collection->searchCount());
    }


    public function testFacets(): void
    {
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

    // private function createRandomImage(): string
    // {
    //     //more entropy
    //     $width  = random_int(400, 500);
    //     $height = random_int(200, 300);

    //     $imageRes = imagecreatetruecolor($width, $height);
    //     for ($row = 0; $row <= $height; $row++) {
    //         for ($column = 0; $column <= $width; $column++) {
    //             /** @phpstan-ignore-next-line */
    //             $color = imagecolorallocate($imageRes, random_int(0, 255), random_int(0, 255), random_int(0, 255));
    //             /** @phpstan-ignore-next-line */
    //             imagesetpixel($imageRes, $column, $row, $color);
    //         }
    //     }
    //     $path = __DIR__.'/tmp/image_' . time() . '.png';
    //     /** @phpstan-ignore-next-line */
    //     if (imagepng($imageRes, $path)) {
    //         return $path;
    //     }

    //     throw new Exception('Error Processing Request', 1);
    // }

    protected function tearDown(): void
    {
        $this->recursiveDeleteDirectory('tests/tmp');
    }
}
