<?php

namespace OpenFoodFactsTests\Unit\OpenFoodFacts;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use OpenFoodFacts\Api;
use OpenFoodFacts\Document\BeautyDocument;
use OpenFoodFacts\Document\FoodDocument;
use OpenFoodFacts\Document\PetDocument;
use OpenFoodFacts\Document\ProductDocument;
use OpenFoodFacts\Exception\BadRequestException;
use OpenFoodFacts\Exception\InvalidBarcodeException;
use OpenFoodFacts\Exception\InvalidParameterException;
use OpenFoodFacts\Exception\MissingCredentialsException;
use OpenFoodFacts\Exception\ProductNotFoundException;
use OpenFoodFacts\Exception\ProductUpdateException;
use OpenFoodFacts\Exception\UnknownException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\SimpleCache\CacheInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Psr16Cache;

class ApiV3Test extends TestCase
{
    /** @var callable(): array<int, array{request: RequestInterface}> */
    private $readHistory;

    private function createApi(MockHandler $mockHandler, string $currentAPI = 'food', ?CacheInterface $cache = null): Api
    {
        $history      = [];
        $handlerStack = HandlerStack::create($mockHandler);
        $handlerStack->push(Middleware::history($history));
        $this->readHistory = static function () use (&$history): array {
            // the history middleware only appends to the container, it never
            // replaces it, so $history always stays an array in practice
            return is_array($history) ? $history : [];
        };

        return new Api('Unit test', $currentAPI, 'world', null, new Client(['handler' => $handlerStack]), $cache);
    }

    /**
     * The requests recorded by the Guzzle history middleware
     * @return array<int, array{request: RequestInterface}>
     */
    private function history(): array
    {
        return ($this->readHistory)();
    }

    private static function successEnvelope(array $product): string
    {
        return (string) json_encode([
            'status'   => 'success',
            'result'   => ['id' => 'product_found', 'name' => 'Product found'],
            'errors'   => [],
            'warnings' => [],
            'code'     => $product['code'] ?? '',
            'product'  => $product,
        ]);
    }

    public function testGetProductRequestsV3VersionedUrl(): void
    {
        $mockHandler = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], self::successEnvelope([
                'code'         => '3057640385148',
                'product_name' => 'Volvic',
            ])),
        ]);
        $api = $this->createApi($mockHandler);

        $product = $api->getProduct('3057640385148', ['product_name', 'tags_sources'], 'fr');

        $this->assertInstanceOf(FoodDocument::class, $product);
        $this->assertSame('Volvic', $product->product_name);

        $request = $this->history()[0]['request'];
        $this->assertSame('GET', $request->getMethod());
        $this->assertSame(
            '/api/v' . Api::API_VERSION . '/product/3057640385148',
            $request->getUri()->getPath()
        );
        $this->assertSame('fields=product_name%2Ctags_sources&lc=fr', $request->getUri()->getQuery());
        $this->assertSame('world.openfoodfacts.org', $request->getUri()->getHost());
    }

    public function testGetProductReturnsFlavorSpecificDocument(): void
    {
        $mockHandler = new MockHandler([
            new Response(200, [], self::successEnvelope(['code' => '123', 'product_name' => 'thing'])),
        ]);
        $api = $this->createApi($mockHandler, 'product');

        $document = $api->getProduct('123');
        $this->assertInstanceOf(ProductDocument::class, $document);
    }

    public static function redirectedFlavors(): iterable
    {
        foreach (['org', 'net'] as $tld) {
            foreach ([
                'openfoodfacts' => FoodDocument::class,
                'openbeautyfacts' => BeautyDocument::class,
                'openpetfoodfacts' => PetDocument::class,
                'openproductsfacts' => ProductDocument::class,
            ] as $domain => $documentClass) {
                yield $domain . '.' . $tld => [$domain, $documentClass, $tld];
            }
        }
    }

    #[DataProvider('redirectedFlavors')]
    public function testGetProductUsesRedirectedFlavorWithoutChangingClient(string $domain, string $documentClass, string $tld): void
    {
        $originalApi = $domain === 'openfoodfacts' ? 'beauty' : 'food';
        $targetHost = 'fr-en.' . $domain . '.' . $tld;
        $productData = ['code' => '123', 'product_name' => 'Redirected product'];
        $api = $this->createApi(new MockHandler([
            new Response(302, ['Location' => 'https://' . $targetHost . '/api/v' . Api::API_VERSION . '/product/123']),
            new Response(200, [], self::successEnvelope($productData)),
            new Response(200, [], self::successEnvelope(['code' => '456'])),
        ]), $originalApi);
        if ($tld === 'net') {
            $api->activeTestMode();
        }

        $document = $api->getProduct('123', ['product_name'], null, null, null, 'all');

        $this->assertSame($documentClass, $document::class);
        $this->assertSame($productData, $document->getData());
        $this->assertSame($originalApi, $api->getCurrentApi());
        $this->assertSame($targetHost, $this->history()[1]['request']->getUri()->getHost());
        $nextDocument = $api->getProduct('456');
        $this->assertSame($originalApi === 'food' ? FoodDocument::class : BeautyDocument::class, $nextDocument::class);
        $this->assertCount(3, $this->history());
        $this->assertSame(
            $this->history()[0]['request']->getUri()->getHost(),
            $this->history()[2]['request']->getUri()->getHost()
        );
    }

    public function testGetProductUsesFinalRedirectInsteadOfIntermediateFlavor(): void
    {
        $api = $this->createApi(new MockHandler([
            new Response(302, ['Location' => 'https://world.openbeautyfacts.org/api/v' . Api::API_VERSION . '/product/123']),
            new Response(302, ['Location' => 'https://world.openpetfoodfacts.org/api/v' . Api::API_VERSION . '/product/123']),
            new Response(200, [], self::successEnvelope(['code' => '123'])),
        ]));

        $this->assertInstanceOf(PetDocument::class, $api->getProduct('123', null, null, null, null, 'all'));
        $this->assertCount(3, $this->history());
    }

    public static function unrecognizedRedirectHosts(): iterable
    {
        yield ['example.org'];
        yield ['world.openbeautyfacts.org.example.org'];
        yield ['worldopenbeautyfacts.org'];
    }

    #[DataProvider('unrecognizedRedirectHosts')]
    public function testGetProductKeepsConfiguredFlavorForUnrecognizedRedirectHost(string $host): void
    {
        $api = $this->createApi(new MockHandler([
            new Response(302, ['Location' => 'https://' . $host . '/api/v' . Api::API_VERSION . '/product/123']),
            new Response(200, [], self::successEnvelope(['code' => '123'])),
        ]), 'product');

        $this->assertInstanceOf(ProductDocument::class, $api->getProduct('123', null, null, null, null, 'all'));
    }

    public function testGetProductCachesRedirectedFlavorAcrossClients(): void
    {
        $cache = new Psr16Cache(new ArrayAdapter());
        $productData = ['code' => '123', 'product_name' => 'Shampoo'];
        $api = $this->createApi(new MockHandler([
            new Response(302, ['Location' => 'https://world.openbeautyfacts.org/api/v' . Api::API_VERSION . '/product/123']),
            new Response(200, [], self::successEnvelope($productData)),
        ]), 'food', $cache);

        $this->assertInstanceOf(BeautyDocument::class, $api->getProduct('123', null, null, null, null, 'all'));
        $this->assertCount(2, $this->history());

        $cachedApi = $this->createApi(new MockHandler([]), 'food', $cache);
        $document = $cachedApi->getProduct('123', null, null, null, null, 'all');

        $this->assertInstanceOf(BeautyDocument::class, $document);
        $this->assertSame($productData, $document->getData());
        $this->assertSame('food', $cachedApi->getCurrentApi());
        $this->assertSame([], $this->history());
    }

    public function testGetProductRefreshesOldCacheEntriesWithoutResolvedFlavor(): void
    {
        $cache = new Psr16Cache(new ArrayAdapter());
        $oldCacheKey = hash('sha256', 'get https://world.openfoodfacts.org/api/v' . Api::API_VERSION . '/product/123?product_type=all');
        $cache->set($oldCacheKey, json_decode(self::successEnvelope(['code' => '123']), true));
        $api = $this->createApi(new MockHandler([
            new Response(302, ['Location' => 'https://world.openbeautyfacts.org/api/v' . Api::API_VERSION . '/product/123']),
            new Response(200, [], self::successEnvelope(['code' => '123'])),
        ]), 'food', $cache);

        $this->assertInstanceOf(BeautyDocument::class, $api->getProduct('123', null, null, null, null, 'all'));
        $this->assertCount(2, $this->history());
    }

    public function testGetProductThrowsOnNotFound(): void
    {
        $mockHandler = new MockHandler([
            new Response(404, ['Content-Type' => 'application/json'], (string) json_encode([
                'status' => 'failure',
                'result' => ['id' => 'product_not_found', 'name' => 'Product not found'],
                'errors' => [],
            ])),
        ]);
        $api = $this->createApi($mockHandler);

        $this->expectException(ProductNotFoundException::class);
        $api->getProduct('3057640385140');
    }

    public function testGetProductThrowsOnNotFoundWithoutJsonBody(): void
    {
        $mockHandler = new MockHandler([new Response(404, [], 'Not found')]);
        $api = $this->createApi($mockHandler);

        $this->expectException(ProductNotFoundException::class);
        $api->getProduct('3057640385140');
    }

    public static function invalidBarcodes(): iterable
    {
        foreach (['', 'foo/../bar', '123abc', ' 123', '123 ', '12-34', '12.34', '+123', "123\n", '１２３', '١٢٣'] as $barcode) {
            foreach (['getProduct', 'updateProduct', 'uploadImage'] as $method) {
                yield [$method, $barcode];
            }
        }
    }

    #[DataProvider('invalidBarcodes')]
    public function testInvalidBarcodeIsRejectedBeforeAnyRequest(string $method, string $barcode): void
    {
        $api = $this->createApi(new MockHandler([]));

        try {
            match ($method) {
                'getProduct' => $api->getProduct($barcode),
                'updateProduct' => $api->updateProduct($barcode, []),
                'uploadImage' => $api->uploadImage($barcode, 'front', __FILE__),
                default => $this->fail('Unknown API method: ' . $method),
            };
            $this->fail('Expected an invalid barcode exception');
        } catch (InvalidBarcodeException $exception) {
            $this->assertSame($barcode, $exception->getBarcode());
            $this->assertInstanceOf(InvalidParameterException::class, $exception);
            $this->assertInstanceOf(BadRequestException::class, $exception);
            $this->assertSame($barcode === ''
                ? 'Barcode is invalid: it must not be empty'
                : sprintf('Barcode "%s" is invalid: it must only contain digits', $barcode), $exception->getMessage());
            $this->assertSame([], $this->history());
        }
    }

    public function testGetProductPreservesLeadingZeros(): void
    {
        $barcode = '0001234567890';
        $api = $this->createApi(new MockHandler([
            new Response(200, [], self::successEnvelope(['code' => $barcode])),
        ]));

        $api->getProduct($barcode);

        $this->assertSame('/api/v' . Api::API_VERSION . '/product/' . $barcode, $this->history()[0]['request']->getUri()->getPath());
    }

    public function testGetProductThrowsOnNonJsonResponse(): void
    {
        $mockHandler = new MockHandler([new Response(200, [], '<html>maintenance</html>')]);
        $api = $this->createApi($mockHandler);

        $this->expectException(UnknownException::class);
        $api->getProduct('3057640385148');
    }

    public function testUpdateProductRequiresCredentials(): void
    {
        $api = $this->createApi(new MockHandler([]));

        $this->expectException(MissingCredentialsException::class);
        $api->updateProduct('3057640385148', ['product_name_fr' => 'Eau de Volvic']);
    }

    public function testUpdateProductSendsStructuredPatchBody(): void
    {
        $mockHandler = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], self::successEnvelope([
                'product_name_fr' => 'Eau de Volvic',
            ])),
        ]);
        $api = $this->createApi($mockHandler);
        $api->authentification('user', 'secret');

        $result = $api->updateProduct(
            '3057640385148',
            ['product_name_fr' => 'Eau de Volvic', 'categories_tags' => ['en:waters']],
            ['product_name'],
            'fr'
        );

        $this->assertSame(json_decode(self::successEnvelope(['product_name_fr' => 'Eau de Volvic']), true), $result);

        $request = $this->history()[0]['request'];
        $this->assertSame('PATCH', $request->getMethod());
        $this->assertSame(
            '/api/v' . Api::API_VERSION . '/product/3057640385148',
            $request->getUri()->getPath()
        );

        $body = json_decode((string) $request->getBody(), true);
        $this->assertSame('user', $body['user_id']);
        $this->assertSame('secret', $body['password']);
        $this->assertSame('fr', $body['lc']);
        $this->assertSame('product_name', $body['fields']);
        $this->assertSame(['en:waters'], $body['product']['categories_tags']);
    }

    public function testUpdateProductThrowsWithReadableErrorsOnFailure(): void
    {
        $mockHandler = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], (string) json_encode([
                'status' => 'failure',
                'result' => ['id' => 'product_not_updated', 'name' => 'Product not updated'],
                'errors' => [
                    [
                        'message' => ['id' => 'invalid_user_id_and_password', 'name' => 'Invalid user id and password'],
                        'field'   => ['id' => 'user_id'],
                        'impact'  => ['id' => 'failure'],
                    ],
                ],
            ])),
        ]);
        $api = $this->createApi($mockHandler);
        $api->authentification('user', 'wrong');

        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('Invalid user id and password (field: user_id)');
        $api->updateProduct('3057640385148', ['product_name_fr' => 'Eau de Volvic']);
    }

    public function testUploadImageSendsBase64PayloadAndSelection(): void
    {
        $imagePath = tempnam(sys_get_temp_dir(), 'off');
        file_put_contents($imagePath, 'fake-image-bytes');

        $mockHandler = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], self::successEnvelope(['images' => []])),
        ]);
        $api = $this->createApi($mockHandler);
        $api->authentification('user', 'secret');

        try {
            $result = $api->uploadImage('3057640385148', 'front', $imagePath, 'fr');
        } finally {
            unlink($imagePath);
        }

        $this->assertSame(json_decode(self::successEnvelope(['images' => []]), true), $result);

        $request = $this->history()[0]['request'];
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame(
            '/api/v' . Api::API_VERSION . '/product/3057640385148/images',
            $request->getUri()->getPath()
        );

        $body = json_decode((string) $request->getBody(), true);
        $this->assertSame(base64_encode('fake-image-bytes'), $body['image_data_base64']);
        $this->assertSame([], $body['selected']['front']['fr']);
    }

    public function testUploadImageRejectsInvalidImageField(): void
    {
        $api = $this->createApi(new MockHandler([]));
        $api->authentification('user', 'secret');

        $this->expectException(BadRequestException::class);
        $api->uploadImage('3057640385148', 'barcode-photo', __FILE__);
    }

    public function testUploadImageRejectsOversizedFileBeforeSendingRequest(): void
    {
        $imagePath = tempnam(sys_get_temp_dir(), 'off');
        $file = fopen($imagePath, 'wb');
        if ($file === false) {
            $this->fail('Cannot open temporary image');
        }
        ftruncate($file, Api::MAX_IMAGE_SIZE + 1);
        fclose($file);
        $api = $this->createApi(new MockHandler([]));
        $api->authentification('user', 'secret');

        try {
            $this->expectException(InvalidParameterException::class);
            $this->expectExceptionMessage('10 MiB');
            $api->uploadImage('123', 'front', $imagePath);
        } finally {
            unlink($imagePath);
            $this->assertCount(0, $this->history());
        }
    }

    public function testUploadImageAcceptsFileAtSizeLimit(): void
    {
        $imagePath = tempnam(sys_get_temp_dir(), 'off');
        $file = fopen($imagePath, 'wb');
        if ($file === false) {
            $this->fail('Cannot open temporary image');
        }
        ftruncate($file, Api::MAX_IMAGE_SIZE);
        fclose($file);
        $api = $this->createApi(new MockHandler([
            new Response(200, [], self::successEnvelope([])),
        ]));
        $api->authentification('user', 'secret');

        try {
            $this->assertSame('success', $api->uploadImage('123', '', $imagePath)['status']);
        } finally {
            unlink($imagePath);
        }
        $body = json_decode((string) $this->history()[0]['request']->getBody(), true);
        $this->assertSame((int) (4 * ceil(Api::MAX_IMAGE_SIZE / 3)), strlen($body['image_data_base64']));
        $this->assertArrayNotHasKey('selected', $body);
    }

    public function testUploadImageRejectsEmptyFile(): void
    {
        $imagePath = tempnam(sys_get_temp_dir(), 'off');
        $api = $this->createApi(new MockHandler([]));
        $api->authentification('user', 'secret');

        try {
            $this->expectException(InvalidParameterException::class);
            $this->expectExceptionMessage('Image is empty');
            $api->uploadImage('123', 'front', $imagePath);
        } finally {
            unlink($imagePath);
        }
    }

    public function testUploadImageRejectsDirectory(): void
    {
        $api = $this->createApi(new MockHandler([]));
        $api->authentification('user', 'secret');
        $this->expectException(InvalidParameterException::class);
        $this->expectExceptionMessage('regular file');
        $api->uploadImage('123', 'front', __DIR__);
    }

    public function testPatchKeepsMethodAndBodyAcrossRedirects(): void
    {
        $mockHandler = new MockHandler([
            new Response(302, ['Location' => 'https://world.openfoodfacts.org/api/v' . Api::API_VERSION . '/product/3057640385148']),
            new Response(200, ['Content-Type' => 'application/json'], self::successEnvelope([])),
        ]);
        $api = $this->createApi($mockHandler);
        $api->authentification('user', 'secret');

        $api->updateProduct('3057640385148', ['product_name_fr' => 'Eau de Volvic']);

        $this->assertCount(2, $this->history());
        $redirectedRequest = $this->history()[1]['request'];
        $this->assertSame('PATCH', $redirectedRequest->getMethod(), 'the redirected request must NOT be downgraded to GET');

        $body = json_decode((string) $redirectedRequest->getBody(), true);
        $this->assertSame('Eau de Volvic', $body['product']['product_name_fr'], 'the redirected request must keep its body');
    }

    public function testUpdateProductThrowsOnPartialFailure(): void
    {
        $mockHandler = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], (string) json_encode([
                'status' => 'success_with_errors',
                'result' => ['id' => 'product_updated', 'name' => 'Product updated'],
                'errors' => [
                    [
                        'message' => ['id' => 'invalid_field_value', 'name' => 'Invalid field value'],
                        'field'   => ['id' => 'packagings'],
                        'impact'  => ['id' => 'field_ignored'],
                    ],
                ],
                'product' => ['product_name_fr' => 'Eau de Volvic'],
            ])),
        ]);
        $api = $this->createApi($mockHandler);
        $api->authentification('user', 'secret');

        try {
            $api->updateProduct('3057640385148', ['product_name_fr' => 'Eau de Volvic', 'packagings' => 'bad']);
            $this->fail('a partially rejected write must throw');
        } catch (ProductUpdateException $productUpdateException) {
            $this->assertStringContainsString('partially failed', $productUpdateException->getMessage());
            $this->assertStringContainsString('Invalid field value (field: packagings)', $productUpdateException->getMessage());
            // the envelope stays available: part of the data was saved anyway
            $this->assertSame('success_with_errors', $productUpdateException->getResponse()['status']);
        }
    }

    public function testUpdateProductReturnsOnSuccessWithWarnings(): void
    {
        $mockHandler = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], (string) json_encode([
                'status'   => 'success_with_warnings',
                'result'   => ['id' => 'product_updated'],
                'errors'   => [],
                'warnings' => [['message' => ['id' => 'unexpected_value', 'name' => 'Unexpected value']]],
            ])),
        ]);
        $api = $this->createApi($mockHandler);
        $api->authentification('user', 'secret');

        $result = $api->updateProduct('3057640385148', ['product_name_fr' => 'Eau de Volvic']);
        $this->assertSame('success_with_warnings', $result['status']);
    }

    public function testGetProductSendsProductTypeForCrossFlavorRedirects(): void
    {
        $mockHandler = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], self::successEnvelope(['code' => '123'])),
        ]);
        $api = $this->createApi($mockHandler);

        $api->getProduct('123', null, null, null, null, 'all');

        $this->assertSame('product_type=all', $this->history()[0]['request']->getUri()->getQuery());
    }

    public function testTestModeUsesBasicGateSeparatedFromAccountCredentials(): void
    {
        $mockHandler = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], self::successEnvelope([])),
        ]);
        $api = $this->createApi($mockHandler);
        $api->activeTestMode();
        $api->authentification('realuser', 'realpass');

        $api->updateProduct('3057640385148', ['product_name_fr' => 'Eau de Volvic']);

        $request = $this->history()[0]['request'];
        $this->assertSame('world.openfoodfacts.net', $request->getUri()->getHost());
        $this->assertSame('Basic ' . base64_encode('off:off'), $request->getHeaderLine('Authorization'), 'the staging HTTP Basic gate must stay off/off');

        $body = json_decode((string) $request->getBody(), true);
        $this->assertSame('realuser', $body['user_id'], 'account credentials must come from authentification(), not from the staging gate');
        $this->assertSame('realpass', $body['password']);
    }

    public function testUpdateProductInTestModeWithoutAccountCredentialsIsRejectedLocally(): void
    {
        $api = $this->createApi(new MockHandler([]));
        $api->activeTestMode();

        $this->expectException(MissingCredentialsException::class);
        $api->updateProduct('3057640385148', ['product_name_fr' => 'Eau de Volvic']);
    }
}
