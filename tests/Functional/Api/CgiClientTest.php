<?php

declare(strict_types=1);

namespace OpenFoodFactsTests\Functional\Api;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response;
use OpenFoodFacts\Api;
use OpenFoodFacts\Models\Nutrients;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

class CgiClientTest extends TestCase
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


    public function testGetNutrients(): void
    {
        $guzzleMock = $this->createMock(ClientInterface::class);
        $guzzleMock->expects($this->once())
            ->method('request')
            ->willReturn($this->mockResponse('tests/Resources/Cgi/nutrients.pl.json'));

        $instance = $this->getInstanceClass($guzzleMock);
        $list = array_filter(
            $instance->getNutrients(),
            fn ($nutrient) => !is_object($nutrient) || get_class($nutrient) !== Nutrients::class
        );
        $this->assertCount(0, $list);
    }



    private function mockResponse(string $path): ResponseInterface
    {
        return new Response(
            200,
            [],
            file_get_contents($path)
        );
    }
}
