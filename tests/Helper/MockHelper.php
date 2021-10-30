<?php

declare(strict_types=1);

namespace OpenFoodFactsTests\Helper;

use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;

class MockHelper
{
    public static function mockResponseFromFile(string $path, int $statusCode = 200): ResponseInterface
    {
        return new Response(
            $statusCode,
            [],
            file_get_contents($path)
        );
    }

    public static function mockResponseFromData(array $data, int $statusCode = 200): ResponseInterface
    {
        return new Response(
            $statusCode,
            [],
            json_encode($data)
        );
    }
}
