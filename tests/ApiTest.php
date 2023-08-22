<?php

namespace OpenFoodFactsTests;

use OpenFoodFacts\Api;
use OpenFoodFacts\Exception\BadRequestException;
use PHPUnit\Framework\TestCase;

class ApiTest extends TestCase
{
    public function testUploadImageMustThrowAnExceptionForInvalidApi(): void
    {
        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('not Available yet');
        $api = new Api('SDK Unit test', 'product');
        $api->uploadImage('unknown', 'foo', 'bar');
    }
}
