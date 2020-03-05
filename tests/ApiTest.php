<?php

use OpenFoodFacts\Api;
use PHPUnit\Framework\TestCase;

class ApiTest extends TestCase
{
    /**
     * @expectedException \OpenFoodFacts\Exception\BadRequestException
     * @expectedExceptionMessage not Available yet
     */
    public function testUploadImageMustThrowAnExceptionForInvalidApi()
    {
        $api = new Api('product');
        $api->uploadImage('unknown', 'foo', 'bar');
    }
}
