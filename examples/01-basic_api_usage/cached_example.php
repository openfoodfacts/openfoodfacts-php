<?php
declare(strict_types=1);

/**
 * Basic usage: the same read as ../00_flat_request, through the wrapper.
 *
 * The three optional dependencies — PSR-3 logger, PSR-18 HTTP client, PSR-16 cache — are
 * injected in the constructor. Here the cache is a Symfony filesystem adapter, which is part of
 * the dev dependencies of this repository (`composer install` at the root pulls it in).
 *
 * Run: php examples/01-basic_api_usage/cached_example.php
 */

use OpenFoodFacts\Api;
use OpenFoodFacts\Exception\ProductNotFoundException;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Psr16Cache;

// __DIR__-relative, so the example runs from any working directory.
require_once __DIR__ . '/../../vendor/autoload.php';

$logger     = new \Psr\Log\NullLogger();
$httpClient = new \GuzzleHttp\Client();
// The PSR-6 cache object you want to use (a PSR-16 implementation can be passed directly).
$psr6Cache  = new FilesystemAdapter();
$psr16Cache = new Psr16Cache($psr6Cache);

// The user agent is mandatory: describe your application and give a contact point.
$api = new Api('Example app - CLI - 1.0 - https://example.org', 'food', 'world', $logger, $httpClient, $psr16Cache);

try {
    // Fetched through the versioned READ API (v3.6); only the listed fields are returned, and
    // the response is cached for an hour, so running this twice performs a single HTTP call.
    $product = $api->getProduct('3057640385148', ['product_name', 'nutriscore_grade']);

    echo $product->product_name, PHP_EOL;
    // Document::__get returns null for an absent field instead of raising a PHP warning.
    echo 'Nutri-Score: ', strtoupper((string)($product->nutriscore_grade ?? 'n/a')), PHP_EOL;
} catch (ProductNotFoundException) {
    echo 'No product found for this barcode.', PHP_EOL;
    exit(1);
}
