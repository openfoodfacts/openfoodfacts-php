<?php

/** @noinspection ALL */

namespace OpenFoodFacts;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\TransferStats;
use OpenFoodFacts\Exception\BadRequestException;
use OpenFoodFacts\Exception\ProductNotFoundException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Psr\SimpleCache\CacheInterface;
use Psr\SimpleCache\InvalidArgumentException;

/**
 * this class provide [...]
 *
 * It a fork of the python OpenFoodFact rewrite on PHP 7.2
 * @method getIngredients() Collection
 * @method getPurchase_places() Collection
 * @method getPackaging_codes() Collection
 * @method getEntry_dates() Collection
 */
class Api
{
    /**
     * the httpClient for all http request
     * @var ClientInterface
     */
    private $httpClient;

    /**
     * this property store the current base of the url
     * @var string
     */
    private $geoUrl     = 'https://%s.openfoodfacts.org';

    /**
     * this property store the current API (it could be : food/beauty/pet )
     * @var string
     */
    private $currentAPI = '';

    /**
     * this property store the auth parameter (username and password)
     * @var array
     */
    private $auth       = null;

    /**
     * this property help you to log information
     * @var LoggerInterface
     */
    private $logger     = null;


    /**
     * @var CacheInterface|null
     */
    private $cache;

    /**
     * this constant defines the environments usable by the API
     * @var array
     */
    private const LIST_API = [
        'food'    => 'https://%s.openfoodfacts.org',
        'beauty'  => 'https://%s.openbeautyfacts.org',
        'pet'     => 'https://%s.openpetfoodfacts.org',
        'product' => 'https://%s.openproductsfacts.org',
    ];

    /**
     * This constant defines the facets usable by the API
     *
     * This variable is used to create the magic functions like "getIngredients" or "getBrands"
     * @var array
     */
    private const FACETS = [
        'additives',
        'allergens',
        'brands',
        'categories',
        'countries',
        'contributors',
        'code',
        'entry_dates',
        'ingredients',
        'label',
        'languages',
        'nutrition_grade',
        'packaging',
        'packaging_codes',
        'purchase_places',
        'photographer',
        'informer',
        'states',
        'stores',
        'traces',
    ];

    /**
     * This constant defines the extensions authorized for the downloading of the data
     * @var array
     */
    private const FILE_TYPE_MAP = [
        "mongodb"   => "openfoodfacts-mongodbdump.tar.gz",
        "csv"       => "en.openfoodfacts.org.products.csv",
        "rdf"       => "en.openfoodfacts.org.products.rdf"
    ];

    /**
     * the constructor of the function
     *
     * @param string $api the environment to search
     * @param string $geography this parameter represent the the country  code and the interface of the language
     * @param LoggerInterface $logger this parameter define an logger
     * @param ClientInterface|null $clientInterface
     * @param CacheInterface|null $cacheInterface
     */
    public function __construct(
        string $api = 'food',
        string $geography = 'world',
        LoggerInterface $logger = null,
        ClientInterface $clientInterface = null,
        CacheInterface $cacheInterface = null
    ) {
        $this->cache        = $cacheInterface;
        $this->logger       = $logger ?? new NullLogger();
        $this->httpClient   = $clientInterface ?? new Client();

        $this->geoUrl     = sprintf(self::LIST_API[$api], $geography);
        $this->currentAPI = $api;
    }

    /**
     * This function allows you to perform tests
     * The domain is correct and for testing purposes only
     */
    public function activeTestMode(): void
    {
        $this->geoUrl = 'https://world.openfoodfacts.net';
        $this->authentification('off', 'off');
    }

    public function getCurrentApi(): string
    {
        return $this->currentAPI;
    }

    /**
     * This function store the authentication parameter
     * @param  string $username
     * @param  string $password
     */
    public function authentification(string $username, string $password): void
    {
        $this->auth = [
            'user_id'   => $username,
            'password'  => $password
        ];
    }

    /**
     * It's a magic function, it works only for facets
     * @param string $name The name of the function
     * @param void $arguments not use yet (probably needed for ingredients)
     * @return Collection        The list of all documents found
     * @throws InvalidArgumentException
     * @throws BadRequestException
     * @example getIngredients()
     */
    public function __call(string $name, $arguments): Collection
    {
        //TODO : test with argument for ingredient
        if (strpos($name, 'get') === 0) {
            $facet = strtolower(substr($name, 3));
            //TODO: what about PSR-12, e.g.: getNutritionGrade() ?

            if (!in_array($facet, self::FACETS)) {
                throw new BadRequestException('Facet "' . $facet . '" not found');
            }

            if ($facet === "purchase_places") {
                $facet = "purchase-places";
            } elseif ($facet === "packaging_codes") {
                $facet = "packager-codes";
            } elseif ($facet === "entry_dates") {
                $facet = "entry-dates";
            }

            $url = $this->buildUrl(null, $facet, []);
            $result = $this->fetch($url);
            if ($facet !== 'ingredients') {
                $result = [
                    'products'  => $result['tags'],
                    'count'     => $result['count'],
                    'page'      => 1,
                    'skip'      => 0,
                    'page_size' => $result['count'],
                ];
            }
            return new Collection($result, $this->currentAPI);
        }

        throw new BadRequestException('Call to undefined method '.__CLASS__.'::'.$name.'()');
    }


    /**
     * this function search an Document by barcode
     * @param string $barcode the barcode [\d]{13}
     * @return Document         A Document if found
     * @throws InvalidArgumentException
     * @throws ProductNotFoundException
     * @throws BadRequestException
     */
    public function getProduct(string $barcode): Document
    {
        $url = $this->buildUrl('api', 'product', $barcode);

        $rawResult = $this->fetch($url);
        if ($rawResult['status'] === 0) {
            //TODO: maybe return null here? (just throw an exception if something really went wrong?
            throw new ProductNotFoundException("Product not found", 1);
        }

        return Document::createSpecificDocument($this->currentAPI, $rawResult['product']);
    }

    /**
     * This function return a Collection of Document search by facets
     * @param array $query list of facets with value
     * @param integer $page Number of the page
     * @return Collection     The list of all documents found
     * @throws InvalidArgumentException
     * @throws BadRequestException
     */
    public function getByFacets(array $query = [], int $page = 1): Collection
    {
        if (empty($query)) {
            return new Collection();
        }
        $search = [];
        ksort($query);
        foreach ($query as $key => $value) {
            $search[] = $key;
            $search[] = $value;
        }

        $url = $this->buildUrl(null, $search, $page);
        $result = $this->fetch($url);
        return new Collection($result, $this->currentAPI);
    }

    /**
     * this function help you to add a new product (or update ??)
     * @param array $postData The post data
     * @return bool|string bool if the product has been added or the error message
     * @throws BadRequestException
     * @throws InvalidArgumentException
     */
    public function addNewProduct(array $postData)
    {
        if (!isset($postData['code']) || !isset($postData['product_name'])) {
            throw new BadRequestException('code or product_name not found!');
        }

        $url = $this->buildUrl('cgi', 'product_jqm2.pl', []);
        $result = $this->fetchPost($url, $postData);

        if ($result['status_verbose'] === 'fields saved' && $result['status'] === 1) {
            return true;
        }
        return $result['status_verbose'];
    }

    /**
     * [uploadImage description]
     * @param string $code the barcode of the product
     * @param string $imageField th name of the image
     * @param string $imagePath the path of the image
     * @return array             the http post response (cast in array)
     * @throws BadRequestException
     * @throws InvalidArgumentException
     */
    public function uploadImage(string $code, string $imageField, string $imagePath)
    {
        //TODO : need test
        if ($this->currentAPI !== 'food') {
            throw new BadRequestException('not Available yet');
        }
        if (!in_array($imageField, ["front", "ingredients", "nutrition"])) {
            throw new BadRequestException('ImageField not valid!');
        }
        if (!file_exists($imagePath)) {
            throw new BadRequestException('Image not found');
        }


        $url = $this->buildUrl('cgi', 'product_image_upload.pl', []);
        $postData = [
            'code'                      => $code,
            'imagefield'                => $imageField,
            'imgupload_' . $imageField  => fopen($imagePath, 'r')
        ];

        try {
            return $this->fetchPost($url, $postData, true);
        } finally {
            if (is_resource($postData['imgupload_' . $imageField])) {
                fclose($postData['imgupload_' . $imageField]);
            }
        }
    }

    /**
     * A search function
     * @param string $search a search term (fulltext)
     * @param integer $page Number of the page
     * @param integer $pageSize The page size
     * @param string $sortBy the sort
     * @return Collection        The list of all documents found
     * @throws BadRequestException
     * @throws InvalidArgumentException
     */
    public function search(string $search, int $page = 1, int $pageSize = 20, string $sortBy = 'unique_scans')
    {
        $parameters = [
            'search_terms'  => $search,
            'page'          => $page,
            'page_size'     => $pageSize,
            'sort_by'       => $sortBy,
            'json'          => '1',
        ];

        $url = $this->buildUrl('cgi', 'search.pl', $parameters);
        $result = $this->fetch($url, false);
        return new Collection($result, $this->currentAPI);
    }

    /**
     * This function download all data from OpenFoodFact
     * @param string $filePath the location where you want to put the stream
     * @param string $fileType mongodb/csv/rdf
     * @return bool             return true when download is complete
     * @throws BadRequestException
     */
    public function downloadData(string $filePath, string $fileType = "mongodb")
    {
        if (!isset(self::FILE_TYPE_MAP[$fileType])) {
            $this->logger->warning(
                'OpenFoodFact - fetch - failed - File type not recognized!',
                ['fileType' => $fileType, 'availableTypes' => self::FILE_TYPE_MAP]
            );
            throw new BadRequestException('File type not recognized!');
        }

        $url        = $this->buildUrl('data', self::FILE_TYPE_MAP[$fileType]);
        try {
            $response = $this->httpClient->request('get', $url, ['sink' => $filePath]);
        } catch (GuzzleException $guzzleException) {
            $this->logger->warning(sprintf('OpenFoodFact - fetch - failed - GET : %s', $url), ['exception' => $guzzleException]);
            $exception = new BadRequestException($guzzleException->getMessage(), $guzzleException->getCode(), $guzzleException);

            throw $exception;
        }

        $this->logger->info('OpenFoodFact - fetch - GET : ' . $url . ' - ' . $response->getStatusCode());

        //TODO: validate response here (server may respond with 200 - OK but you might not get valid data as a response)

        return $response->getStatusCode() == 200;
    }


    /**
     * This private function do a http request
     * @param string $url the url to fetch
     * @param boolean $isJsonFile the request must be finish by '.json' ?
     * @return array               return the result of the request in array format
     * @throws InvalidArgumentException
     * @throws BadRequestException
     */
    private function fetch(string $url, bool $isJsonFile = true): array
    {
        $url        .= ($isJsonFile ? '.json' : '');
        $realUrl    = $url;
        $cacheKey   = md5($realUrl);

        if (!empty($this->cache) && $this->cache->has($cacheKey)) {
            $cachedResult = $this->cache->get($cacheKey);
            return $cachedResult;
        }

        $data = [
            'on_stats' => function (TransferStats $stats) use (&$realUrl) {
                // this function help to find redirection
                // On redirect we lost some parameters like page
                $realUrl= (string)$stats->getEffectiveUri();
            }
        ];
        if ($this->auth) {
            $data['auth'] = array_values($this->auth);
        }

        try {
            $response = $this->httpClient->request('get', $url, $data);
        } catch (GuzzleException $guzzleException) {
            $this->logger->warning(sprintf('OpenFoodFact - fetch - failed - GET : %s', $url), ['exception' => $guzzleException]);
            //TODO: What to do on a error? - return empty array?
            $exception = new BadRequestException($guzzleException->getMessage(), $guzzleException->getCode(), $guzzleException);

            throw $exception;
        }
        if ($realUrl !== $url) {
            $this->logger->warning('OpenFoodFact - The url : '. $url . ' has been redirect to ' . $realUrl);
        }
        $this->logger->info('OpenFoodFact - fetch - GET : ' . $url . ' - ' . $response->getStatusCode());

        $jsonResult = json_decode($response->getBody(), true);

        if (!empty($this->cache) && !empty($jsonResult)) {
            $this->cache->set($cacheKey, $jsonResult);
        }

        return $jsonResult;
    }

    /**
     * This function performs the same job of the "fetch" function except the call method and parameters
     * @param string $url The url to fetch
     * @param array $postData The post data
     * @param boolean $isMultipart The data is multipart ?
     * @return array               return the result of the request in array format
     * @throws InvalidArgumentException
     * @throws BadRequestException
     */
    private function fetchPost(string $url, array $postData, bool $isMultipart = false): array
    {
        $data = [];
        if ($this->auth) {
            $data['auth'] = array_values($this->auth);
        }
        if ($isMultipart) {
            foreach ($postData as $key => $value) {
                $data['multipart'][] = [
                    'name'      => $key,
                    'contents'  => $value
                ];
            }
        } else {
            $data['form_params'] = $postData;
        }

        $cacheKey = md5($url . json_encode($data));

        if (!empty($this->cache) && $this->cache->has($cacheKey)) {
            return $this->cache->get($cacheKey);
        }

        try {
            $response = $this->httpClient->request('post', $url, $data);
        } catch (GuzzleException $guzzleException) {
            $exception = new BadRequestException($guzzleException->getMessage(), $guzzleException->getCode(), $guzzleException);

            throw $exception;
        }

        $this->logger->info('OpenFoodFact - fetch - GET : ' . $url . ' - ' . $response->getStatusCode());

        $jsonResult = json_decode($response->getBody(), true);

        if (!empty($this->cache) && !empty($jsonResult)) {
            $this->cache->set($cacheKey, $jsonResult);
        }

        return $jsonResult;
    }

    /**
     * This private function generates an url according to the parameters
     * @param  string|null $service
     * @param  string|array|null $resourceType
     * @param  integer|string|array|null $parameters
     * @return string               the generated url
     */
    private function buildUrl(string $service = null, $resourceType = null, $parameters = null): string
    {
        $baseUrl = null;
        switch ($service) {
            case 'api':
                $baseUrl = implode('/', [
                  $this->geoUrl,
                  $service,
                  'v0',
                  $resourceType,
                  $parameters
                ]);
                break;
            case 'data':
                $baseUrl = implode('/', [
                  $this->geoUrl,
                  $service,
                  $resourceType
                ]);
                break;
            case 'cgi':
                $baseUrl = implode('/', [
                  $this->geoUrl,
                  $service,
                  $resourceType
                ]);
                $baseUrl .= '?' . (is_array($parameters) ? http_build_query($parameters) : $parameters);
                break;
            case null:
            default:
                if (is_array($resourceType)) {
                    $resourceType = implode('/', $resourceType);
                }
                if ($resourceType == 'ingredients') {
                    //need test
                    $resourceType = implode('/', ["state",  "ingredients-completed"]);
                    $parameters   = 1;
                }
                $baseUrl = implode('/', array_filter([
                    $this->geoUrl,
                    $resourceType,
                    $parameters
                ], function ($value) {
                    return !empty($value);
                }));
                break;
        }
        return $baseUrl;
    }
}
