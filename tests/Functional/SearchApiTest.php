<?php

namespace OpenFoodFactsTests\Functional;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response;
use OpenFoodFacts\Document\SearchDocument;
use OpenFoodFacts\Exception\InvalidParameterException;
use OpenFoodFacts\Exception\ProductNotFoundException;
use OpenFoodFacts\Exception\UnknownException;
use OpenFoodFacts\Exception\ValidationException;
use OpenFoodFacts\Model\AutocompleteResult;
use OpenFoodFacts\Model\SearchResult;
use OpenFoodFacts\SearchApi;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Psr\SimpleCache\CacheInterface;

class SearchApiTest extends TestCase
{
    private const USER_AGENT = 'Integration test';
    private const URL_DOCUMENT = 'https://search.openfoodfacts.org/document/3222475591327?index_id=index';
    private function getInstance(
        LoggerInterface $logger,
        ClientInterface $client,
        ?CacheInterface $cache
    ): SearchApi {
        return
            new SearchApi(
                self::USER_AGENT,
                $logger,
                $client,
                $cache
            );
    }

    /**
     * Check only ProductNotFoundException error
     */
    public function testGetDocumentNotFound(): void
    {
        $instance = $this->getInstance(
            new NullLogger(),
            $this->getClient(self::URL_DOCUMENT, new Response(404)),
            null
        );

        $this->expectException(ProductNotFoundException::class);
        $instance->getDocument('3222475591327', 'index');
    }


    /**
     * Check only 422 error and log
     */
    public function testGetDocumentValidationException(): void
    {
        $content = 'content';
        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects($this->once())
            ->method('error')
            ->with('Validation error', ['http_content' => $content])
        ;

        $instance = $this->getInstance(
            $logger,
            $this->getClient(self::URL_DOCUMENT, new Response(422, [], json_encode('content'))),
            null
        );

        $this->expectException(ValidationException::class);
        $instance->getDocument('3222475591327', 'index');
    }



    /**
     * Check only 500 error
     */
    public function testGetDocumentUnknownException(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects($this->once())
            ->method('error')
        ;

        $instance = $this->getInstance(
            $logger,
            $this->getClient(self::URL_DOCUMENT, new Response(500, [], json_encode('content'))),
            null
        );

        $this->expectException(UnknownException::class);
        $instance->getDocument('3222475591327', 'index');
    }


    /**
     * Check Document
     */
    public function testGetDocumentFull(): void
    {
        $response = [
            'last_indexed_datetime' => '2024-02-29T09:55:42.238198',
            'code' => '3222475591327',
            'product_name' => [
                'main' => 'Tartelettes pur beurre fraise'
            ],
        ];

        $cache = $this->createMock(CacheInterface::class);
        $cacheKey = hash('sha256', self::URL_DOCUMENT);
        $cache
            ->expects($this->once())
            ->method('has')
            ->with($cacheKey)
            ->willReturn(false)
        ;
        $cache
            ->expects($this->once())
            ->method('set')
            ->with($cacheKey, new SearchDocument($response));
        ;
        $instance = $this->getInstance(
            new NullLogger(),
            $this->getClient(self::URL_DOCUMENT, new Response(200, [], json_encode($response))),
            $cache
        );
        $result = $instance->getDocument('3222475591327', 'index');
        $this->assertEquals(SearchDocument::class, get_class($result));
    }

    /**
     * Check Search when parameters are missing
     */
    public function testSearchFunctionNotEnoughParameter(): void
    {
        $instance = $this->getInstance(
            new NullLogger(),
            $this->createMock(ClientInterface::class),
            null
        );
        $this->expectException(InvalidParameterException::class);
        $instance->search();
    }


    /**
     * Check Search when parameters are missing
     */
    public function testSearchFunctionFull(): void
    {
        $response = [
            'hits' => [
                ['code' => '000000000'],
                ['code' => '0000001360493'],
                //...
            ],
            //not documented
            'aggregations' => [],
            'page' => 1,
            'page_size' => 10,
            'page_count' => 1000,
            //not fully documented
            'debug' => [],
            'took' => 169,
            'timed_out' => false,
            'count' => 10000,
            'is_count_exact' => false,
            'warnings' => null,
        ];

        $url = 'https://search.openfoodfacts.org/search?q=tomato&langs=en%2Cfr&page_size=10&page=1&fields=code&sort_by=code&index_id=off';

        $instance = $this->getInstance(
            new NullLogger(),
            $this->getClient($url, new Response(200, [], json_encode($response))),
            null
        );
        $result = $instance->search('tomato', ['en', 'fr'], 10, 1, ['code'], 'code', 'off');

        $this->assertEquals(SearchResult::class, get_class($result));
    }


    public function testAutocompleteFull(): void
    {
        $response = [
            'took' => 3,
            'timed_out' => false,
            'options' => [
                ['id' => 'en:carre-d-eden', 'text' => 'Carre D\'eden', 'taxonomy_name' => 'brand'],
                ['id' => 'en:carrefour', 'text' => 'Carrefour', 'taxonomy_name' => 'brand'],
                //...
            ],
            // not documented
            'debug' => [],
        ];

        $url = 'https://search.openfoodfacts.org/autocomplete?q=carre&taxonomy_names=brand&lang=en&size=10&fuzziness=0&index_id=off';

        $instance = $this->getInstance(
            new NullLogger(),
            $this->getClient($url, new Response(200, [], json_encode($response))),
            null
        );
        $result = $instance->autocomplete('carre', ['brand'], 'en', 10, 0, 'off');
        $this->assertEquals(AutocompleteResult::class, get_class($result));
    }

    private function getClient(string $url, Response $response): ClientInterface
    {
        $client = $this->createMock(ClientInterface::class);
        $client
            ->expects($this->once())
            ->method('request')
            ->with('get', $url, ['headers' => ['User-Agent' => 'SDK PHP - ' . self::USER_AGENT]])
            ->willReturn($response);

        return $client;
    }
}
