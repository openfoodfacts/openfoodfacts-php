<?php

declare(strict_types=1);

namespace OpenFoodFactsTests\Functional\Api;

use GuzzleHttp\ClientInterface;
use OpenFoodFacts\Api;
use OpenFoodFacts\Collection;
use OpenFoodFacts\Document\FoodDocument;
use OpenFoodFacts\Exception\ProductNotFoundException;
use OpenFoodFactsTests\Helper\MockHelper;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ApiClientTest extends TestCase
{
    /**
     * @param MockObject|ClientInterface $mockClient
     * @return Api
     */
    private function getInstanceClass($mockClient): Api
    {
        return new Api(
            'food',
            'world',
            null,
            $mockClient,
            null
        );
    }


    public function testGetProduct(): void
    {
        $guzzleMock = $this->createMock(ClientInterface::class);
        $guzzleMock->expects($this->once())
            ->method('request')
            ->willReturn(MockHelper::mockResponseFromFile('tests/Resources/api/v2/product/04963406.json'))
        ;

        $instance = $this->getInstanceClass($guzzleMock);
        $this->assertSame(FoodDocument::class, get_class($instance->getProduct('04963406')));
    }

    public function testGetProductNotFound(): void
    {
        $guzzleMock = $this->createMock(ClientInterface::class);
        $guzzleMock->expects($this->once())
            ->method('request')
            ->willReturn(MockHelper::mockResponseFromData(['code'=>'aCode','status'=>0,'status_verbose'=>'product not found'], 404))
        ;

        $this->expectException(ProductNotFoundException::class);
        $this->getInstanceClass($guzzleMock)->getProduct('aCode');
    }


    public function testGetProducts(): void
    {
        $guzzleMock = $this->createMock(ClientInterface::class);
        $guzzleMock->expects($this->once())
           ->method('request')
           ->willReturn(MockHelper::mockResponseFromFile('tests/Resources/api/v2/search.json'))
        ;

        $list = $this->getInstanceClass($guzzleMock)->getProducts(
            ['04963406','04963406'],
            ['_id','_keywords']
        );
        $this->assertEquals(Collection::class, get_class($list));

        foreach ($list as $document) {
            $this->assertEquals(FoodDocument::class, get_class($document));
        }
    }
}
