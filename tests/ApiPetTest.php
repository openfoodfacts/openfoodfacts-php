<?php

namespace OpenFoodFactsTests;

use OpenFoodFacts\Api;
use OpenFoodFacts\Collection;
use OpenFoodFacts\Document;
use OpenFoodFacts\Document\PetDocument;
use OpenFoodFacts\Exception\BadRequestException;
use OpenFoodFacts\FilesystemTrait;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class ApiPetTest extends TestCase
{
    use FilesystemTrait;

    private Api $api;

    protected function setUp(): void
    {
        $this->api = new Api('Integration test', 'pet', 'fr', $this->createMock(NullLogger::class));

        foreach (glob('tests/images/*') ?: [] as $file) {
            unlink($file);
        }
    }

    public function testApi(): void
    {
        $prd = Helper::getProductWithCache($this->api, '7613035799738');

        $this->assertInstanceOf(PetDocument::class, $prd);
        $this->assertInstanceOf(Document::class, $prd);
        $this->assertTrue(isset($prd->product_name));
        $this->assertNotEmpty($prd->product_name);
    }

    public function testApiAddImage(): void
    {
        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('not Available yet');
        $this->api->uploadImage('7613035799738', 'fronts', 'nothing');

        $this->markTestSkipped('not Available yet');
    }

    public function testApiSearch(): void
    {
        $collection = $this->api->search('chat', 3, 30);

        $this->assertInstanceOf(Collection::class, $collection);
        $this->assertEquals(30, $collection->pageCount());
        $this->assertGreaterThan(100, $collection->searchCount());
    }

    protected function tearDown(): void
    {
        $this->recursiveDeleteDirectory('tests/tmp');
    }
}
