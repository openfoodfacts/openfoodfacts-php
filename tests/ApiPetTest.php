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

class ApiPetTest extends TestCase
{

    private $api;

    protected function setUp()
    {
        $log = new Logger('name');
        $log->pushHandler(new StreamHandler('log/test.log'));

        $this->api = new Api('pet','fr',$log);

        foreach(glob('tests/images/*') as $file){
            unlink($file);
        }
    }

    public function testApi()
    {

        $prd = $this->api->getProduct('7613035799738');

        $this->assertEquals(get_class($prd), Document::class);
        $this->assertTrue(isset($prd->product_name));
        $this->assertNotEmpty($prd->product_name);

    }

    public function testApiAddImage()
    {
        try {
            $this->api->uploadImage('7613035799738','fronts','nothing');
            $this->assertTrue(false);
        } catch (BadRequestException $e) {
            $this->assertEquals($e->getMessage(),'not Available yet');
        }

    }

    public function testApiSearch()
    {

        $collection = $this->api->search('chat',3,30);

        $this->assertEquals(get_class($collection), Collection::class);
        $this->assertEquals($collection->pageCount(), 30);
        $this->assertGreaterThan(100, $collection->searchCount());

    }

}
