<?php
use PHPUnit\Framework\TestCase;

use OpenFoodFacts\Api;
use OpenFoodFacts\Collection;
use OpenFoodFacts\Document\Product;
use OpenFoodFacts\Exception\{
    ProductNotFoundException,
    BadRequestException
};


use Monolog\Logger;
use Monolog\Handler\StreamHandler;

class ApiTest extends TestCase
{
    public function testApiFood()
    {
        $api = new Api('food','fr');
        $prd = $api->getProduct('3057640385148');

        $this->assertEquals(get_class($prd), Product::class);
        $this->assertTrue(isset($prd->product_name));
        $this->assertNotEmpty($prd->product_name);

        try {
          $api->getProduct('305764038514800');
          $this->assertTrue(false);
        } catch (ProductNotFoundException $e) {
            $this->assertTrue(true);
        }

    }

    public function testApiCollection()
    {
        $api = new Api('food','fr');

        $collection = $api->getByFacets([]);
        $this->assertEquals(get_class($collection), Collection::class);
        $this->assertEquals($collection->pageCount(), 0);

        $collection = $api->getByFacets(['trace'=>'egg','country'=>'france']);
        $this->assertEquals(get_class($collection), Collection::class);
        $this->assertEquals($collection->pageCount(), 20);

        $this->assertGreaterThan(1000, $collection->searchCount());
    }

    public function testApiAddProduct()
    {
        // create a log channel
        $log = new Logger('name');
        $log->pushHandler(new StreamHandler('log/test.log'));

        $api        = new Api('food','fr',$log);
        $prd        = $api->getProduct('3057640385148');
        $postData   = ['code'=>$prd->code,'product_name'=>$prd->product_name];
        $result     = $api->addNewProduct($postData);
        $this->assertTrue(is_bool($result));
        $this->assertTrue($result);


        $postData   = ['product_name'=>$prd->product_name];

        try {
            $result     = $api->addNewProduct($postData);
            $this->assertTrue(false);
        } catch (BadRequestException $e) {
            $this->assertTrue(true);
        }
        $postData   = ['code'=>'','product_name'=>$prd->product_name];
        $result     = $api->addNewProduct($postData);
        $this->assertTrue(is_string($result));
        $this->assertEquals($result, 'no code or invalid code');

    }
}
