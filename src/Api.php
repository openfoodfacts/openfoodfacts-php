<?php

/** @noinspection ALL */

namespace OpenFoodFacts;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\TransferStats;
use OpenFoodFacts\Exception\BadRequestException;
use OpenFoodFacts\Exception\InvalidBarcodeException;
use OpenFoodFacts\Exception\InvalidParameterException;
use OpenFoodFacts\Exception\MissingCredentialsException;
use OpenFoodFacts\Exception\ProductNotFoundException;
use OpenFoodFacts\Exception\ProductUpdateException;
use OpenFoodFacts\Exception\UnknownException;
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
     */
    private ClientInterface $httpClient;

    /**
     * this property store the current base of the url
     */
    private string $geoUrl     = 'https://%s.openfoodfacts.org';


    /**
     * This property store the current location for http call
     *
     * This property could be world for all product or you can specify le country code (cc) and
     * language of the interface (lc). If you want filter on french product you can set fr as country code.
     * We strongly recommend to use english as language of the interface
     *
     * @example fr-en
     * @link https://en.wiki.openfoodfacts.org/API/Read#Country_code_.28cc.29_and_Language_of_the_interface_.28lc.29
     * @var string
     */
    public string $geography  = 'world';

    /**
     * this property store the Open Food Facts account credentials
     * (user_id and password), sent in the body of WRITE requests
     */
    private ?array $auth       = null;

    /**
     * HTTP Basic auth [username, password] protecting the host itself
     * (only used by the staging server enabled via activeTestMode());
     * distinct from the account credentials stored in $auth
     */
    private ?array $httpAuth   = null;

    /**
     * this property help you to log information
     */
    private LoggerInterface $logger;

    private ?CacheInterface $cache;

    /**
     * The version of the Open Food Facts API (and thus of the product schema) that
     * this SDK requests. Pinning a full version (e.g. "3.6" = product schema 1004)
     * guarantees a stable response structure even when the server schema evolves.
     * @link https://openfoodfacts.github.io/openfoodfacts-server/api/ref-api-and-product-schema-change-log/
     */
    public const API_VERSION = '3.6';

    /** Maximum source image size accepted by this SDK (10 MiB, before base64). */
    public const MAX_IMAGE_SIZE = 10 * 1024 * 1024;

    /**
     * Default lifetime (in seconds) of cached API responses
     */
    private const CACHE_TTL = 3600;

    /**
     * this constant defines the environments usable by the API
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
        'mongodb'   => 'openfoodfacts-mongodbdump.tar.gz',
        'csv'       => 'en.openfoodfacts.org.products.csv',
        'rdf'       => 'en.openfoodfacts.org.products.rdf'
    ];

    /**
     * the constructor of the function
     *
     * @param string $currentAPI the environment to search
     * @param string $geography this parameter represent the the country  code and the interface of the language
     * @param LoggerInterface $logger this parameter define an logger
     * @param ClientInterface|null $clientInterface
     * @param CacheInterface|null $cacheInterface
     */
    public function __construct(
        public readonly string $userAgent,
        private readonly string $currentAPI = 'food',
        string $geography = 'world',
        ?LoggerInterface $logger = null,
        ?ClientInterface $clientInterface = null,
        ?CacheInterface $cacheInterface = null
    ) {
        if (!isset(self::LIST_API[$currentAPI])) {
            throw new InvalidParameterException(sprintf('Unknown API flavor "%s"', $currentAPI));
        }

        $this->cache        = $cacheInterface;
        $this->logger       = $logger ?? new NullLogger();
        $this->httpClient   = $clientInterface ?? new Client();

        $this->geography  = $geography;
        $this->geoUrl     = sprintf(self::LIST_API[$currentAPI], $geography);
    }

    /**
     * This function allows you to perform tests
     * The domain is correct and for testing purposes only
     */
    public function activeTestMode(): void
    {
        // Keep the selected flavor and geography; repeated calls are harmless.
        $this->geoUrl   = substr($this->geoUrl, 0, -4) . '.net';
        // "off"/"off" is the HTTP Basic gate protecting the staging host, NOT an
        // account: call authentification() with a real (staging) account to write
        $this->httpAuth = ['off', 'off'];
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

            if ($facet === 'purchase_places') {
                $facet = 'purchase-places';
            } elseif ($facet === 'packaging_codes') {
                $facet = 'packager-codes';
            } elseif ($facet === 'entry_dates') {
                $facet = 'entry-dates';
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


    /** @throws InvalidBarcodeException */
    private static function assertValidBarcode(string $barcode): void
    {
        if ($barcode === '' || !ctype_digit($barcode)) {
            throw new InvalidBarcodeException($barcode);
        }
    }

    /**
     * this function search an Document by barcode, using the versioned v3 READ API
     * @param string $barcode a non-empty barcode containing only ASCII digits (leading zeros are preserved)
     * @param array<int, string>|null $fields list of fields to include in the response
     *                                        (special values: "all", "none", "raw", "knowledge_panels").
     *                                        null returns all fields
     * @param string|null $lc 2-letter language code used to localize some returned fields
     * @param string|null $cc 2-letter country code
     * @param string|null $tagsLc 2-letter language code used to localize taxonomy tags
     * @param string|null $productType requested product type (food, beauty, petfood, product or "all").
     *                                 With "all", the server redirects to the flavor matching the
     *                                 product (redirect followed transparently); without it, a product
     *                                 stored on another flavor is reported as not found
     * @return Document         A Document if found
     * @throws InvalidArgumentException
     * @throws InvalidBarcodeException
     * @throws ProductNotFoundException
     * @throws BadRequestException
     * @throws UnknownException
     */
    public function getProduct(string $barcode, ?array $fields = null, ?string $lc = null, ?string $cc = null, ?string $tagsLc = null, ?string $productType = null): Document
    {
        self::assertValidBarcode($barcode);

        $query = array_filter([
            'fields'       => $fields !== null ? implode(',', $fields) : null,
            'lc'           => $lc,
            'cc'           => $cc,
            'tags_lc'      => $tagsLc,
            'product_type' => $productType,
        ], static fn ($value) => $value !== null);

        $url = sprintf('%s/api/v%s/product/%s', $this->geoUrl, self::API_VERSION, rawurlencode($barcode));

        $response = $this->fetchV3('get', $url, ['query' => $query], true);
        $result = $response['body'];

        if (($result['status'] ?? '') === 'failure' || !isset($result['product']) || !is_array($result['product'])) {
            throw new ProductNotFoundException('Product not found', 1);
        }

        return Document::createSpecificDocument($response['api'], $result['product']);
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
     * Create or update a product through the structured v3 WRITE API (PATCH).
     *
     * The v3 WRITE API accepts structured JSON data instead of the flattened
     * key/value pairs of the legacy cgi API. Currently supported fields are the
     * language specific fields (product_name, ingredients text, ...), tags fields
     * (categories, labels, ...), packaging fields (packagings, packagings_add,
     * packagings_complete) and the selection of uploaded images.
     *
     * @param string $barcode the barcode of the product to create or update
     * @param array $productData the structured product data (content of the "product" body field)
     * @param array<int, string>|null $fields fields to return in the response ("updated" by default)
     * @param string|null $lc 2-letter language code
     * @param string|null $cc 2-letter country code
     * @param string|null $tagsLc 2-letter language code for taxonomy tags
     * @return array the v3 response envelope (status, result, errors, warnings, product)
     * @throws BadRequestException
     * @throws InvalidBarcodeException
     * @throws MissingCredentialsException
     * @throws ProductNotFoundException
     * @throws ProductUpdateException when the write failed or was only partially applied
     * @throws UnknownException
     */
    public function updateProduct(string $barcode, array $productData, ?array $fields = null, ?string $lc = null, ?string $cc = null, ?string $tagsLc = null): array
    {
        self::assertValidBarcode($barcode);
        if (null === $this->auth) {
            throw new MissingCredentialsException('The v3 WRITE API requires credentials: call authentification() first');
        }

        $body = array_filter([
            'lc'      => $lc,
            'cc'      => $cc,
            'tags_lc' => $tagsLc,
            'fields'  => $fields !== null ? implode(',', $fields) : null,
        ], static fn ($value) => $value !== null);
        $body['user_id']  = $this->auth['user_id'];
        $body['password'] = $this->auth['password'];
        $body['product']  = $productData;

        $url = sprintf('%s/api/v%s/product/%s', $this->geoUrl, self::API_VERSION, rawurlencode($barcode));

        $result = $this->fetchV3('patch', $url, ['json' => $body])['body'];

        $this->assertWriteSucceeded($result, 'Product update');

        return $result;
    }

    /**
     * this function help you to add a new product (or update ??)
     * @deprecated use updateProduct() (structured v3 WRITE API) instead;
     *             this method relies on the legacy cgi/product_jqm2.pl endpoint
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

        // the legacy cgi API expects the account credentials as form parameters;
        // explicit values already present in $postData take precedence
        $postData = array_merge($this->auth ?? [], $postData);

        $url = $this->buildUrl('cgi', 'product_jqm2.pl', []);
        $result = $this->fetchPost($url, $postData);

        if ($result['status_verbose'] === 'fields saved' && $result['status'] === 1) {
            return true;
        }

        if ($result['status_verbose'] === 'no user credentials') {
            throw new MissingCredentialsException('no user credentials');
        }

        return $result['status_verbose'];
    }

    /**
     * Upload an image for a product through the v3 images API and optionally
     * select it for a specific information field and language.
     *
     * The image is sent base64 encoded in a JSON body (endpoint introduced by
     * API v3.3: POST /api/v3/product/[barcode]/images). If the product does not
     * exist, it will be created.
     *
     * @param string $code the barcode of the product
     * @param string $imageField the information shown on the image (front, ingredients, nutrition, packaging),
     *                           used to select the uploaded image; pass an empty string to only upload
     * @param string $imagePath the path of the image (JPEG, PNG, GIF or HEIC), at most MAX_IMAGE_SIZE bytes
     * @param string $imageLc 2-letter code of the language shown on the image, used for the selection
     * @return array             the v3 response envelope (status, result, errors, warnings, product)
     * @throws BadRequestException
     * @throws InvalidBarcodeException
     * @throws InvalidParameterException
     * @throws MissingCredentialsException
     * @throws ProductNotFoundException
     * @throws ProductUpdateException when the upload failed or was only partially applied
     * @throws UnknownException
     */
    public function uploadImage(string $code, string $imageField, string $imagePath, string $imageLc = 'en'): array
    {
        self::assertValidBarcode($code);
        if ($imageField !== '' && !in_array($imageField, ['front', 'ingredients', 'nutrition', 'packaging'], true)) {
            throw new BadRequestException('ImageField not valid!');
        }
        if (!file_exists($imagePath)) {
            throw new BadRequestException('Image not found');
        }
        if (!is_file($imagePath) || !is_readable($imagePath)) {
            throw new InvalidParameterException('Image must be a readable regular file');
        }
        if (null === $this->auth) {
            throw new MissingCredentialsException('The v3 images API requires credentials: call authentification() first');
        }

        // Bound the read even if the file grows after the size check.
        if (filesize($imagePath) > self::MAX_IMAGE_SIZE) {
            throw new InvalidParameterException('Image exceeds the SDK limit of 10 MiB');
        }
        $imageContent = @file_get_contents($imagePath, false, null, 0, self::MAX_IMAGE_SIZE + 1);
        if ($imageContent === false) {
            throw new BadRequestException('Image not readable');
        }
        if (strlen($imageContent) > self::MAX_IMAGE_SIZE) {
            throw new InvalidParameterException('Image exceeds the SDK limit of 10 MiB');
        }
        if ($imageContent === '') {
            throw new InvalidParameterException('Image is empty');
        }

        $body = [
            'user_id'           => $this->auth['user_id'],
            'password'          => $this->auth['password'],
            'image_data_base64' => base64_encode($imageContent),
        ];
        if ($imageField !== '') {
            $body['selected'] = [
                $imageField => [
                    $imageLc => new \stdClass(),
                ],
            ];
        }

        $url = sprintf('%s/api/v%s/product/%s/images', $this->geoUrl, self::API_VERSION, rawurlencode($code));

        $result = $this->fetchV3('post', $url, ['json' => $body])['body'];

        $this->assertWriteSucceeded($result, 'Image upload');

        return $result;
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
    public function downloadData(string $filePath, string $fileType = 'mongodb')
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
            $response = $this->httpClient->request(
                'get',
                $url,
                [
                    'sink' => $filePath,
                    'headers' => $this->getDefaultHeaders()
                ]
            );
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
        $cacheKey   = hash('sha256', $realUrl);

        if (!empty($this->cache) && $this->cache->has($cacheKey)) {
            /** @var array $cachedResult */
            $cachedResult = $this->cache->get($cacheKey);

            return $cachedResult;
        }
        $data = $this->getDefaultOptions();

        $data['on_stats'] = function (TransferStats $stats) use (&$realUrl) {
            // this function help to find redirection
            // On redirect we lost some parameters like page
            $realUrl = (string)$stats->getEffectiveUri();
        };

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

        $jsonResult = json_decode((string) $response->getBody(), true);
        if (!is_array($jsonResult)) {
            throw new BadRequestException(sprintf('OpenFoodFact - the API returned a non-JSON response (HTTP %d)', $response->getStatusCode()));
        }

        if (!empty($this->cache) && !empty($jsonResult)) {
            $this->cache->set($cacheKey, $jsonResult, self::CACHE_TTL);
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
        $data = $this->getDefaultOptions();

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

        try {
            $response = $this->httpClient->request('post', $url, $data);
        } catch (GuzzleException $guzzleException) {
            $exception = new BadRequestException($guzzleException->getMessage(), $guzzleException->getCode(), $guzzleException);

            throw $exception;
        }

        $this->logger->info('OpenFoodFact - fetch - POST : ' . $url . ' - ' . $response->getStatusCode());

        $jsonResult = json_decode((string) $response->getBody(), true);
        if (!is_array($jsonResult)) {
            throw new BadRequestException(sprintf('OpenFoodFact - the API returned a non-JSON response (HTTP %d)', $response->getStatusCode()));
        }

        return $jsonResult;
    }

    /**
     * Perform a request against a versioned v3 endpoint and decode the common
     * v3 response envelope (status, result, errors, warnings).
     *
     * HTTP errors are handled here: a 404 throws ProductNotFoundException (the
     * v3 READ API returns a 404 status code when the product does not exist),
     * other client/server errors throw BadRequestException. Redirects (302 to
     * the server matching the product type) are followed transparently.
     *
     * @param string $method the http method (get, post, patch)
     * @param string $url the versioned v3 url
     * @param array $options additional Guzzle options (query, json, ...)
     * @param bool $useCache whether the response may be served from/stored in the cache (READ only)
     * @return array{body: array, api: string} the decoded envelope and the flavor of the final response
     * @throws BadRequestException
     * @throws InvalidArgumentException
     * @throws ProductNotFoundException
     * @throws UnknownException
     */
    private function fetchV3(string $method, string $url, array $options = [], bool $useCache = false): array
    {
        // Old cache entries contain only the body and cannot identify redirected products.
        $cacheKey = hash('sha256', 'v3-response:2:' . $method . ' ' . $url . '?' . http_build_query($options['query'] ?? []));
        if ($useCache && !empty($this->cache) && $this->cache->has($cacheKey)) {
            /** @var array{body: array, api: string} $cachedResult */
            $cachedResult = $this->cache->get($cacheKey);

            return $cachedResult;
        }

        $options                = array_merge($this->getDefaultOptions(), $options);
        $options['http_errors'] = false;
        // "strict" keeps the request method on 301/302 redirects: without it,
        // Guzzle downgrades a redirected PATCH/POST to a body-less GET, and a
        // write would silently be lost while still reporting success
        $options['allow_redirects'] = [
            'max'             => 5,
            'strict'          => true,
            'referer'         => false,
            'track_redirects' => true,
        ];

        try {
            $response = $this->httpClient->request($method, $url, $options);
        } catch (GuzzleException $guzzleException) {
            $this->logger->warning(sprintf('OpenFoodFact - fetchV3 - failed - %s : %s', strtoupper($method), $url), ['exception' => $guzzleException]);

            throw new BadRequestException($guzzleException->getMessage(), $guzzleException->getCode(), $guzzleException);
        }

        $statusCode = $response->getStatusCode();
        $this->logger->info(sprintf('OpenFoodFact - fetchV3 - %s : %s - %d', strtoupper($method), $url, $statusCode));

        try {
            $decoded = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $jsonException) {
            if ($statusCode === 404) {
                throw new ProductNotFoundException('Product not found', 1);
            }

            throw new UnknownException(
                sprintf('OpenFoodFact - the API returned a non-JSON response (HTTP %d)', $statusCode),
                $statusCode,
                $jsonException
            );
        }
        if (!is_array($decoded)) {
            throw new UnknownException(sprintf('OpenFoodFact - the API returned an unexpected JSON payload (HTTP %d)', $statusCode));
        }

        if ($statusCode === 404) {
            throw new ProductNotFoundException('Product not found', 1);
        }
        if ($statusCode >= 400) {
            throw new BadRequestException(sprintf(
                'OpenFoodFact - the API returned an error (HTTP %d): %s',
                $statusCode,
                $this->formatV3Messages($decoded, 'errors')
            ), $statusCode);
        }

        $result = [
            'body' => $decoded,
            'api' => $this->resolveResponseApi($response->getHeader('X-Guzzle-Redirect-History')),
        ];
        if ($useCache && !empty($this->cache)) {
            $this->cache->set($cacheKey, $result, self::CACHE_TTL);
        }

        return $result;
    }

    /** @param array<int, string> $redirectHistory */
    private function resolveResponseApi(array $redirectHistory): string
    {
        $finalUrl = end($redirectHistory);
        if ($finalUrl === false) {
            return $this->currentAPI;
        }

        $host = strtolower((string) parse_url($finalUrl, PHP_URL_HOST));
        foreach (self::LIST_API as $api => $baseUrl) {
            // Keep the leading dot to match a whole domain, including any geography.
            $domain = (string) parse_url(sprintf($baseUrl, ''), PHP_URL_HOST);
            if (str_ends_with($host, $domain) || str_ends_with($host, substr($domain, 0, -4) . '.net')) {
                return $api;
            }
        }

        return $this->currentAPI;
    }

    /**
     * Validate the status of a v3 WRITE response envelope.
     *
     * "failure" and "success_with_errors" (a partially rejected write) both
     * throw; "success_with_warnings" is logged and returns normally.
     *
     * @param array $result the decoded v3 response envelope
     * @param string $operation human readable operation name for messages
     * @throws ProductUpdateException
     */
    private function assertWriteSucceeded(array $result, string $operation): void
    {
        $status = $result['status'] ?? '';

        if ($status === 'failure' || $status === 'success_with_errors') {
            throw new ProductUpdateException(sprintf(
                '%s %s: %s',
                $operation,
                $status === 'failure' ? 'failed' : 'partially failed (some fields were rejected)',
                $this->formatV3Messages($result, 'errors')
            ), $result);
        }

        if ($status === 'success_with_warnings') {
            $this->logger->warning(sprintf(
                'OpenFoodFact - %s succeeded with warnings: %s',
                $operation,
                $this->formatV3Messages($result, 'warnings')
            ));
        }
    }

    /**
     * Build a readable message from the errors or warnings of a v3 response envelope
     * @param array $result the decoded v3 response envelope
     * @param string $key "errors" or "warnings"
     * @return string
     */
    private function formatV3Messages(array $result, string $key = 'errors'): string
    {
        $messages = [];
        foreach ((array) ($result[$key] ?? []) as $error) {
            if (!is_array($error)) {
                continue;
            }
            $message  = is_array($error['message'] ?? null) ? $error['message'] : [];
            $field    = is_array($error['field'] ?? null) ? $error['field'] : [];
            $sentence = $message['name'] ?? $message['id'] ?? null;
            if (is_string($sentence)) {
                $messages[] = $sentence . (isset($field['id']) && is_string($field['id']) ? sprintf(' (field: %s)', $field['id']) : '');
            }
        }
        if ($messages === []) {
            $resultInfo = is_array($result['result'] ?? null) ? $result['result'] : [];
            $fallback   = $resultInfo['name'] ?? $resultInfo['id'] ?? null;

            return is_string($fallback) ? $fallback : 'unknown error';
        }

        return implode('; ', $messages);
    }

    /**
     * This private function generates an url according to the parameters
     * @param  string|null $service
     * @param  string|array|null $resourceType
     * @param  int|string|array|null $parameters
     * @return string               the generated url
     */
    private function buildUrl(?string $service = null, $resourceType = null, $parameters = null): string
    {
        $baseUrl = null;
        switch ($service) {
            case 'data':
                /** @phpstan-ignore-next-line */
                $baseUrl = implode('/', [
                    $this->geoUrl,
                    $service,
                    $resourceType
                ]);

                break;
            case 'cgi':
                /** @phpstan-ignore-next-line */
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
                    $resourceType = implode('/', ['state',  'ingredients-completed']);
                    $parameters   = 1;
                }
                $baseUrl = implode('/', array_filter([
                    $this->geoUrl,
                    $resourceType,
                    is_array($parameters) ? '' : $parameters
                ], function ($value) {
                    return !empty($value);
                }));

                break;
        }

        return $baseUrl;
    }

    /**
     * @return array
     */
    private function getDefaultOptions(): array
    {
        $data = [
            'headers' => $this->getDefaultHeaders(),
        ];
        if ($this->httpAuth) {
            $data['auth'] = $this->httpAuth;
        }

        return $data;
    }

    /**
     * @return array
     */
    private function getDefaultHeaders(): array
    {
        //Force the use of user agent on each http client no matter they come
        return [
            'User-Agent' => 'SDK PHP - ' . $this->userAgent,
        ];
    }


}
