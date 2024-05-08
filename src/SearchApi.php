<?php

namespace OpenFoodFacts;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use OpenFoodFacts\Document\SearchDocument;
use OpenFoodFacts\Exception\InvalidParameterException;
use OpenFoodFacts\Exception\NotFoundException;
use OpenFoodFacts\Exception\ProductNotFoundException;
use OpenFoodFacts\Exception\UnknownException;
use OpenFoodFacts\Exception\ValidationException;
use OpenFoodFacts\Model\SearchResult;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Psr\SimpleCache\CacheInterface;
use Psr\SimpleCache\InvalidArgumentException;

class SearchApi
{
    private ?CacheInterface $cache;


    /**
     * the constructor of the function
     * @param string $userAgent this parameter define an user agent
     * @param LoggerInterface $logger this parameter define an logger
     * @param ClientInterface $httpClient
     * @param CacheInterface|null $cacheInterface
     */
    public function __construct(
        public readonly string $userAgent,
        public readonly LoggerInterface $logger = new NullLogger(),
        public readonly ClientInterface $httpClient = new Client(),
        ?CacheInterface $cacheInterface = null
    ) {
        $this->cache        = $cacheInterface;

    }


    /**
     * this function search an Document by barcode
     * @param string $identifier the barcode [\d]{13}
     * @return SearchDocument         A Document if found
     * @throws InvalidArgumentException
     * @throws ProductNotFoundException
     * @throws UnknownException
     * @throws ValidationException
     */
    public function getDocument(string $identifier): SearchDocument
    {
        $url = "https://search.openfoodfacts.org/document/$identifier";

        $cacheKey   = hash('sha256', $url);
        if (!empty($this->cache) && $this->cache->has($cacheKey)) {
            return $this->cache->get($cacheKey);
        }

        try {
            $content = $this->request('get', $url);
        } catch (NotFoundException) {
            throw new ProductNotFoundException();
        }

        $document = new SearchDocument($content);

        if (!empty($this->cache)) {
            $this->cache->set($cacheKey, $document);
        }

        return $document;
    }


    /**
     * The new search function )
     * @param string|null $query The search query, it supports Lucene search query syntax (https://lucene.apache.org/core/3_6_0/queryparsersyntax.html). Words that are not recognized by the lucene query parser are searched as full text search.
     * Example: categories_tags:"en:beverages" strawberry brands:"casino" query use a filter clause for categories and brands and look for "strawberry" in multiple fields.
     * The query is optional, but sort_by value must then be provided.
     * @param string[] $langs list of languages we want to support during search. This list should include the user expected language, and additional languages (such as english for example).
     * This is currently used for language-specific subfields to choose in which subfields we're searching in.
     * If not provided, ['en'] is used.
     * @param int|null $pageSize Number of results to return per page.
     * @param int|null $page Page to request, starts at 1.
     * @param string[] $fields Fields to include in the response. All other fields will be ignored.
     * @param string|null $sortBy Field name to use to sort results, the field should exist and be sortable. If it is not provided, results are sorted by descending relevance score.
     * @param string|null $indexId Index ID to use for the search, if not provided, the default index is used. If there is only one index, this parameter is not needed.
     * @return SearchResult
     * @throws InvalidParameterException
     * @throws NotFoundException
     * @throws UnknownException
     * @throws ValidationException
     */
    public function search(string $query = null, array $langs = null, int $pageSize = null, int $page = null, array $fields = null, string $sortBy = null, string $indexId = null): SearchResult
    {
        if(empty($query) && empty($sortBy)) {
            throw new InvalidParameterException('query or sortBy must be provided');
        }
        /** @var string[] $parameters */
        $parameters = [];
        if(isset($query)) {
            $parameters['q'] = $query;
        }
        if(isset($langs)) {
            $parameters['langs'] = implode(',', $langs);
        }
        if(isset($pageSize)) {
            $parameters['page_size'] = $pageSize;
        }
        if(isset($page)) {
            $parameters['page'] = $page;
        }
        if(isset($fields)) {
            $parameters['fields'] = implode(',', $fields);
        }
        if(isset($sortBy)) {
            $parameters['sort_by'] = $sortBy;
        }
        if(isset($indexId)) {
            $parameters['index_id'] = $indexId;
        }

        $url = sprintf('https://search.openfoodfacts.org/search?%s', http_build_query($parameters));
        $content = $this->request('get', $url);

        return new SearchResult($content);
    }


    /**
     * The new search function )
     * @param string $query User autocomplete query.
     * @param string[] $taxonomyNames Name(s) of the taxonomy to search in
     * @param string|null $lang Language to search in.
     * @param int|null $size Number of results to return.
     * @param string|null $fuzziness Fuzziness level to use, default to no fuzziness.
     * @param string|null $indexId Index ID to use for the search, if not provided, the default index is used. If there is only one index, this parameter is not needed.
     * @return array not documented
     * @throws InvalidParameterException
     * @throws NotFoundException
     * @throws UnknownException
     * @throws ValidationException
     */
    public function autocomplete(string $query, array $taxonomyNames, string $lang = null, int $size = null, string $fuzziness = null, string $indexId = null): array
    {
        if(empty($query) || empty($taxonomyNames)) {
            throw new InvalidParameterException('query ans taxonomyNames must be provided');
        }
        /** @var string[] $parameters */
        $parameters = [
            'q' => $query,
            'taxonomy_names' => implode(',', $taxonomyNames),
        ];
        if(isset($lang)) {
            $parameters['lang'] = $lang;
        }
        if(isset($size)) {
            $parameters['size'] = $size;
        }
        if(isset($fuzziness)) {
            $parameters['fuzziness'] = $fuzziness;
        }
        if(isset($indexId)) {
            $parameters['index_id'] = $indexId;
        }

        $url = sprintf('https://search.openfoodfacts.org/autocomplete?%s', http_build_query($parameters));

        return $this->request('get', $url);
    }


    private function request(string $method, string $url): array
    {
        $response = $this->httpClient->request($method, $url, $this->getDefaultOptions());
        $content = json_decode($response->getBody()->getContents(), true);

        switch ($response->getStatusCode()) {
            case 200:
                return $content;
            case 404:
                throw new NotFoundException();
            case 422:
                $this->logger->error('Validation error', ['http_content' => $content]);

                throw new ValidationException();
            default:
                $this->logger->error('We encounter an unknown http error', ['url' => $url,'http_content' => $content]);

                throw new UnknownException(sprintf('Search return an http error : %s', $response->getStatusCode()));
        }
    }

    /**
     * @return array
     */
    private function getDefaultOptions(): array
    {
        return [
            'headers' => [
                'User-Agent' => 'SDK PHP - ' . $this->userAgent,
            ]
        ];
    }
}
