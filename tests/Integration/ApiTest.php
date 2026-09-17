<?php

namespace OpenFoodFactsTests\Integration;

use OpenFoodFacts\Api;
use OpenFoodFacts\Exception\InvalidParameterException;
use PHPUnit\Framework\TestCase;

class ApiTest extends TestCase
{
    public function testUploadImageMustThrowAnExceptionForInvalidBarcode(): void
    {
        $this->expectException(InvalidParameterException::class);
        $api = new Api('Integration test', 'product');
        $api->uploadImage('unknown', 'foo', 'bar');
    }
}
