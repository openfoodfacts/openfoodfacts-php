<?php
use PHPUnit\Framework\TestCase;

use OpenFoodFacts\Api;
use OpenFoodFacts\Collection;
use OpenFoodFacts\Document;
use OpenFoodFacts\Document\Product;
use OpenFoodFacts\Exception\{
    ProductNotFoundException,
    BadRequestException
};


use Monolog\Logger;
use Monolog\Handler\StreamHandler;

class ApiFoodTest extends TestCase
{

    private $api;

    protected function setUp()
    {
        $log = new Logger('name');
        $log->pushHandler(new StreamHandler('log/test.log'));

        $this->api = new Api('food','fr-en',$log);

        foreach(glob('tests/tmp/*') as $file){
            unlink($file);
        }
    }

    public function testApi() : void
    {

        $prd = $this->api->getProduct('3057640385148');

        $this->assertEquals(get_class($prd), Product::class);
        $this->assertTrue(isset($prd->product_name));
        $this->assertNotEmpty($prd->product_name);

        try {
          $this->api->getProduct('305764038514800');
          $this->assertTrue(false);
        } catch (ProductNotFoundException $e) {
            $this->assertTrue(true);
        }

        try {
            $result = $this->api->downloadData('tests/mongodb','nopeFile');
            $this->assertTrue(false);
        } catch (BadRequestException $e) {
            $this->assertEquals($e->getMessage(),'File type not recognized!');
        }

        $result = $this->api->downloadData('tests/tmp/mongodb');
        $this->assertTrue(true);
    }

    public function testApiCollection() : void
    {

        $collection = $this->api->getByFacets([]);
        $this->assertEquals(get_class($collection), Collection::class);
        $this->assertEquals($collection->pageCount(), 0);

        try {
            $collection = $this->api->getByFacets(['trace'=>'egg','country'=>'france'],3);
            $this->assertTrue(false);
        } catch (\PHPUnit\Framework\Error\Notice $e) {
            $this->assertEquals($e->getMessage(),'OpenFoodFact - Your request has been redirect');
        }

        $collection = $this->api->getByFacets(['trace'=>'eggs','country'=>'france'],3);
        $this->assertEquals(get_class($collection), Collection::class);
        $this->assertEquals($collection->pageCount(), 20);
        $this->assertEquals($collection->getPage(), 3);
        $this->assertEquals($collection->getSkip(), 40);
        $this->assertEquals($collection->getPageSize(), 20);
        $this->assertGreaterThan(1000, $collection->searchCount());

        foreach($collection as $key => $doc){
            if($key>1) break;
            $this->assertEquals(get_class($doc), Document::class);

        }

    }

    public function testApiAddProduct() : void
    {
        $this->api->activeTestMode();
        $prd        = $this->api->getProduct('3057640385148');

        $postData   = ['code'=>$prd->code,'product_name'=>$prd->product_name];

        $result     = $this->api->addNewProduct($postData);
        $this->assertTrue(is_bool($result));


        $postData   = ['product_name'=>$prd->product_name];

        try {
            $result     = $this->api->addNewProduct($postData);
            $this->assertTrue(false);
        } catch (BadRequestException $e) {
            $this->assertTrue(true);
        }
        $postData   = ['code'=>'','product_name'=>$prd->product_name];
        $result     = $this->api->addNewProduct($postData);
        $this->assertTrue(is_string($result));
        $this->assertEquals($result, 'no code or invalid code');

    }

    public function testApiAddImage() : void
    {

        $this->api->activeTestMode();
        $prd        = $this->api->getProduct('3057640385148');


        try {
            $this->api->uploadImage('3057640385148','fronts','nothing');
            $this->assertTrue(false);
        } catch (BadRequestException $e) {
            $this->assertEquals($e->getMessage(),'Imagefield not valid!');
        }
        try {
            $this->api->uploadImage('3057640385148','front','nothing');
            $this->assertTrue(false);
        } catch (BadRequestException $e) {
            $this->assertEquals($e->getMessage(),'Image not found');
        }
        $file1 = $this->createRandomImage();

        $result = $this->api->uploadImage('3057640385148','front',$file1);
        $this->assertEquals($result['status'], 'status ok');
        $this->assertTrue(isset($result['imagefield']));
        $this->assertTrue(isset($result['image']));
        $this->assertTrue(isset($result['image']['imgid']));


    }

    public function testApiSearch() : void
    {

        $collection = $this->api->search('volvic',3,30);

        $this->assertEquals(get_class($collection), Collection::class);
        $this->assertEquals($collection->pageCount(), 30);
        $this->assertGreaterThan(100, $collection->searchCount());


    }


    public function testFacets() : void
    {

        $collection = $this->api->getIngredients();
        $this->assertEquals(get_class($collection), Collection::class);
        $this->assertEquals($collection->pageCount(), 20);
        $this->assertEquals($collection->getPageSize(), 20);
        $this->assertGreaterThan(70000, $collection->searchCount());

        try {
            $collection = $this->api->getIngredient();
            $this->assertTrue(false);
        } catch (BadRequestException $e) {
            $this->assertEquals($e->getMessage(),'Facet "ingredient" not found');
        }

        $collection = $this->api->getPurchase_places();
        $this->assertEquals(get_class($collection), Collection::class);
        $collection = $this->api->getPackaging_codes();
        $this->assertEquals(get_class($collection), Collection::class);
        $collection = $this->api->getEntry_dates();
        $this->assertEquals(get_class($collection), Collection::class);

        try {
            $collection = $this->api->getIngredient();
            $this->assertTrue(false);
        } catch (BadRequestException $e) {
            $this->assertEquals($e->getMessage(),'Facet "ingredient" not found');
        }

        try {
            $collection = $this->api->nope();
        } catch (\Exception $e) {
            $this->assertTrue(true);
        }
    }


    private function createRandomImage() : string
    {

        $width = 400;
        $height = 200;

        $imageRes = imagecreatetruecolor($width, $height);
        for($row = 0; $row <= $height; $row++) {
            for($column = 0; $column <= $width; $column++) {
                $colour = imagecolorallocate ($imageRes, mt_rand(0,255) , mt_rand(0,255), mt_rand(0,255));
                imagesetpixel($imageRes,$column, $row, $colour);
            }
        }
        $path = 'tests/tmp/image_' . time() .'.jpg';
        if (imagejpeg($imageRes,$path)) {
            return $path;
        }
        throw new \Exception("Error Processing Request", 1);

    }



}
