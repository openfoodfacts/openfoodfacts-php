# Features

## Function Name: __construct

**Description:** namespace OpenFoodFacts; use GuzzleHttp\Client; use GuzzleHttp\ClientInterface; use GuzzleHttp\Exception\GuzzleException; use GuzzleHttp\TransferStats; use OpenFoodFacts\Exception\BadRequestException; use OpenFoodFacts\Exception\ProductNotFoundException; use Psr\Log\LoggerInterface; use Psr\Log\NullLogger; use Psr\SimpleCache\CacheInterface; use Psr\SimpleCache\InvalidArgumentException; / this class provide [...]  It a fork of the python OpenFoodFact rewrite on PHP 7.2 / class Api { / the httpClient for all http request / private ClientInterface $httpClient; / this property store the current base of the url / private string $geoUrl     = 'https://%s.openfoodfacts.org'; / This property store the current location for http call  This property could be world for all product or you can specify le country code (cc) and language of the interface (lc). If you want filter on french product you can set fr as country code. We strongly recommend to use english as language of the interface  / public string $geography  = 'world'; / this property store the auth parameter (username and password) / private ?array $auth       = null; / this property help you to log information / private LoggerInterface $logger; private ?CacheInterface $cache; / this constant defines the environments usable by the API / private const LIST_API = [ 'food'    => 'https://%s.openfoodfacts.org', 'beauty'  => 'https://%s.openbeautyfacts.org', 'pet'     => 'https://%s.openpetfoodfacts.org', 'product' => 'https://%s.openproductsfacts.org', ]; / This constant defines the facets usable by the API  This variable is used to create the magic functions like "getIngredients" or "getBrands" / private const FACETS = [ 'additives', 'allergens', 'brands', 'categories', 'countries', 'contributors', 'code', 'entry_dates', 'ingredients', 'label', 'languages', 'nutrition_grade', 'packaging', 'packaging_codes', 'purchase_places', 'photographer', 'informer', 'states', 'stores', 'traces', ]; / This constant defines the extensions authorized for the downloading of the data / private const FILE_TYPE_MAP = [ 'mongodb'   => 'openfoodfacts-mongodbdump.tar.gz', 'csv'       => 'en.openfoodfacts.org.products.csv', 'rdf'       => 'en.openfoodfacts.org.products.rdf' ]; / the constructor of the function

**Function Details:**

@noinspection ALL */

@method getIngredients() Collection

@method getPurchase_places() Collection

@method getPackaging_codes() Collection

@method getEntry_dates() Collection

@example fr-en

@link https://en.wiki.openfoodfacts.org/API/Read#Country_code_.28cc.29_and_Language_of_the_interface_.28lc.29

@var string

@var array

@param string $currentAPI the environment to search

@param string $geography this parameter represent the the country  code and the interface of the language

@param LoggerInterface $logger this parameter define an logger

@param ClientInterface|null $clientInterface

@param CacheInterface|null $cacheInterface


```php
public function __construct(
        public readonly string $userAgent,
        private readonly string $currentAPI = 'food',
        string $geography = 'world',
        ?LoggerInterface $logger = null,
        ?ClientInterface $clientInterface = null,
        ?CacheInterface $cacheInterface = null
    ) {
        $this->cache        = $cacheInterface;
        $this->logger       = $logger ?? new NullLogger();
        $this->httpClient   = $clientInterface ?? new Client();

        $this->geoUrl     = sprintf(self::LIST_API[$currentAPI], $geography);
    }
```

<hr>

## Function Name: activeTestMode

**Description:** This function allows you to perform tests The domain is correct and for testing purposes only

**Function Details:**


```php

```

<hr>

## Function Name: getProduct

**Description:** this function search an Document by barcode

**Function Details:**

@param string $barcode the barcode [\d]{13}

@return Document         A Document if found

@throws InvalidArgumentException

@throws ProductNotFoundException

@throws BadRequestException


```php

```

<hr>

## Function Name: getByFacets

**Description:** This function return a Collection of Document search by facets

**Function Details:**

@param array $query list of facets with value

@param integer $page Number of the page

@return Collection     The list of all documents found

@throws InvalidArgumentException

@throws BadRequestException


```php

```

<hr>

## Function Name: addNewProduct

**Description:** this function help you to add a new product (or update ??)

**Function Details:**

@param array $postData The post data

@return bool|string bool if the product has been added or the error message

@throws BadRequestException

@throws InvalidArgumentException


```php
public function addNewProduct(array $postData)
    {
        if (!isset($postData['code']) || !isset($postData['product_name'])) {
            throw new BadRequestException('code or product_name not found!');
        }
```

<hr>

## Function Name: uploadImage

**Description:** [uploadImage description]

**Function Details:**

@param string $code the barcode of the product

@param string $imageField th name of the image

@param string $imagePath the path of the image

@return array             the http post response (cast in array)

@throws BadRequestException

@throws InvalidArgumentException


```php
public function uploadImage(string $code, string $imageField, string $imagePath)
    {
        //TODO : need test
        if ($this->currentAPI !== 'food') {
            throw new BadRequestException('not Available yet');
        }
```

<hr>

## Function Name: search

**Description:** A search function

**Function Details:**

@param string $search a search term (fulltext)

@param integer $page Number of the page

@param integer $pageSize The page size

@param string $sortBy the sort

@return Collection        The list of all documents found

@throws BadRequestException

@throws InvalidArgumentException


```php
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
```

<hr>

## Function Name: downloadData

**Description:** This function download all data from OpenFoodFact

**Function Details:**

@param string $filePath the location where you want to put the stream

@param string $fileType mongodb/csv/rdf

@return bool             return true when download is complete

@throws BadRequestException


```php
public function downloadData(string $filePath, string $fileType = 'mongodb')
    {
        if (!isset(self::FILE_TYPE_MAP[$fileType])) {
            $this->logger->warning(
                'OpenFoodFact - fetch - failed - File type not recognized!',
                ['fileType' => $fileType, 'availableTypes' => self::FILE_TYPE_MAP]
            );

            throw new BadRequestException('File type not recognized!');
        }
```

<hr>

## Function Name: fetch

**Description:** This private function do a http request

**Function Details:**

@param string $url the url to fetch

@param boolean $isJsonFile the request must be finish by '.json' ?

@return array               return the result of the request in array format

@throws InvalidArgumentException

@throws BadRequestException


```php

```

<hr>

## Function Name: fetchPost

**Description:** $cachedResult = $this->cache->get($cacheKey); return $cachedResult; } $data = $this->getDefaultOptions(); $data['on_stats'] = function (TransferStats $stats) use (&$realUrl) { // this function help to find redirection // On redirect we lost some parameters like page $realUrl = (string)$stats->getEffectiveUri(); }; try { $response = $this->httpClient->request('get', $url, $data); } catch (GuzzleException $guzzleException) { $this->logger->warning(sprintf('OpenFoodFact - fetch - failed - GET : %s', $url), ['exception' => $guzzleException]); //TODO: What to do on a error? - return empty array? $exception = new BadRequestException($guzzleException->getMessage(), $guzzleException->getCode(), $guzzleException); throw $exception; } if ($realUrl !== $url) { $this->logger->warning('OpenFoodFact - The url : '. $url . ' has been redirect to ' . $realUrl); } $this->logger->info('OpenFoodFact - fetch - GET : ' . $url . ' - ' . $response->getStatusCode()); /** @var array $jsonResult */ $jsonResult = json_decode($response->getBody(), true); if (!empty($this->cache) && !empty($jsonResult)) { $this->cache->set($cacheKey, $jsonResult); } return $jsonResult; } / This function performs the same job of the "fetch" function except the call method and parameters

**Function Details:**

@var array $cachedResult */

@param string $url The url to fetch

@param array $postData The post data

@param boolean $isMultipart The data is multipart ?

@return array               return the result of the request in array format

@throws InvalidArgumentException

@throws BadRequestException


```php

```

<hr>

## Function Name: buildUrl

**Description:** This private function generates an url according to the parameters

**Function Details:**

@param  string|null $service

@param  string|array|null $resourceType

@param  int|string|array|null $parameters

@return string               the generated url


```php

```

<hr>

## Function Name: getDefaultOptions

**Description:** $baseUrl = implode('/', [ $this->geoUrl, $service ?? '', 'v0', $resourceType, $parameters ]); break; case 'data': /** @phpstan-ignore-next-line */ $baseUrl = implode('/', [ $this->geoUrl, $service, $resourceType ]); break; case 'cgi': /** @phpstan-ignore-next-line */ $baseUrl = implode('/', [ $this->geoUrl, $service, $resourceType ]); $baseUrl .= '?' . (is_array($parameters) ? http_build_query($parameters) : $parameters); break; case null: default: if (is_array($resourceType)) { $resourceType = implode('/', $resourceType); } if ($resourceType == 'ingredients') { //need test $resourceType = implode('/', ['state',  'ingredients-completed']); $parameters   = 1; } $baseUrl = implode('/', array_filter([ $this->geoUrl, $resourceType, is_array($parameters) ? '' : $parameters ], function ($value) { return !empty($value); })); break; } return $baseUrl; } /

**Function Details:**

@phpstan-ignore-next-line */

@return array


```php

```

<hr>

## Function Name: __construct

**Description:** / class Collection implements \Iterator { public const defaultPageSize = 24; /** @var array<int, Document> */ private array $listDocuments  = []; private int $count          = 0; private int$page           = 0; private int$skip           = 0; private int $pageSize       = 0; / initialization of the collection

**Function Details:**

@phpstan-implements \Iterator<number, Document>

@param array|null $data the raw data

@param string|null $api  this information help to type the collection  (not use yet)


```php
public function __construct(?array $data = null, ?string $api = null)
    {
        $data = $data ?? [
            'products'  => [],
            'count'     => 0,
            'page'      => 0,
            'skip'      => 0,
            'page_size' => 0,
        ];
        $this->listDocuments = [];

        if (!empty($data['products'])) {
            $currentApi = '';
            if (null !== $api) {
                $currentApi = $api;
            }
```

<hr>

## Function Name: __construct

**Description:** In mongoDB all element are object, it not possible to define property. All property of the mongodb entity are store in one property of this class and the magic call try to access to it / class Document { use RecursiveSortingTrait; / the whole data / private array $data; / Initialization the document and specify from which API it was extract

**Function Details:**

@property string $code

@property string $product_name

@param array $data the whole data


```php
public function __construct(array $data)
    {
        $this->recursiveSortArray($data);
        $this->data = $data;
    }
```

<hr>

## Function Name: __get

**Description:** 

**Function Details:**

@inheritDoc

@return mixed


```php
public function __get(string $name)
    {
        return $this->data[$name];
    }
```

<hr>

## Function Name: __isset

**Description:** 

**Function Details:**

@inheritDoc


```php

```

<hr>

## Function Name: isAssoc

**Description:** Trait RecursiveSortingTrait / trait RecursiveSortingTrait { /

**Function Details:**

@param array $arr

@return bool


```php

```

<hr>

## Function Name: __construct

**Description:** the constructor of the function

**Function Details:**

@param string $userAgent this parameter define an user agent

@param LoggerInterface $logger this parameter define an logger

@param ClientInterface $httpClient

@param CacheInterface|null $cacheInterface


```php

```

<hr>

## Function Name: getDocument

**Description:** Function to find a document by him identifier

**Function Details:**

@param string $identifier

@param string|null $indexId Index ID to use for the search, if not provided, the default index is used. If there is only one index, this parameter is not needed.

@return SearchDocument A Document if found

@throws InvalidArgumentException

@throws ProductNotFoundException

@throws UnknownException

@throws ValidationException

@throws GuzzleException


```php

```

<hr>

## Function Name: search

**Description:** Function to search a list of products  Example: categories_tags:"en:beverages" strawberry brands:"casino" query use a filter clause for categories and brands and look for "strawberry" in multiple fields. The query is optional, but sort_by value must then be provided. This is currently used for language-specific subfields to choose in which subfields we're searching in. If not provided, ['en'] is used.

**Function Details:**

@param string|null $query The search query, it supports Lucene search query syntax (https://lucene.apache.org/core/3_6_0/queryparsersyntax.html). Words that are not recognized by the lucene query parser are searched as full text search.

@param string[] $langs list of languages we want to support during search. This list should include the user expected language, and additional languages (such as english for example).

@param int|null $pageSize Number of results to return per page.

@param int|null $page Page to request, starts at 1.

@param string[] $fields Fields to include in the response. All other fields will be ignored.

@param string|null $sortBy Field name to use to sort results, the field should exist and be sortable. If it is not provided, results are sorted by descending relevance score.

@param string|null $indexId Index ID to use for the search, if not provided, the default index is used. If there is only one index, this parameter is not needed.

@return SearchResult

@throws InvalidParameterException

@throws NotFoundException

@throws UnknownException

@throws ValidationException

@throws GuzzleException


```php

```

<hr>

## Function Name: autocomplete

**Description:** $parameters = []; if(isset($query)) { $parameters['q'] = $query; } if(isset($langs)) { $parameters['langs'] = implode(',', $langs); } if(isset($pageSize)) { $parameters['page_size'] = $pageSize; } if(isset($page)) { $parameters['page'] = $page; } if(isset($fields)) { $parameters['fields'] = implode(',', $fields); } if(isset($sortBy)) { $parameters['sort_by'] = $sortBy; } if(isset($indexId)) { $parameters['index_id'] = $indexId; } $url = sprintf('https://search.openfoodfacts.org/search?%s', http_build_query($parameters)); $content = $this->request('get', $url); return new SearchResult($content); } / Autocomplete function to search for a term in a taxonomy

**Function Details:**

@var string[] $parameters */

@param string $query User autocomplete query.

@param string[] $taxonomyNames Name(s) of the taxonomy to search in.

@param string|null $lang Language to search in, default to en.

@param int|null $size Number of results to return.

@param int|null $fuzziness Fuzziness level to use, default to no fuzziness.

@param string|null $indexId Index ID to use for the search, if not provided, the default index is used. If there is only one index, this parameter is not needed.

@return AutocompleteResult

@throws InvalidParameterException

@throws NotFoundException

@throws UnknownException

@throws ValidationException

@throws GuzzleException


```php

```

<hr>

## Function Name: request

**Description:** $parameters = [ 'q' => $query, 'taxonomy_names' => implode(',', $taxonomyNames), ]; if(isset($lang)) { $parameters['lang'] = $lang; } if(isset($size)) { $parameters['size'] = $size; } if(isset($fuzziness)) { $parameters['fuzziness'] = $fuzziness; } if(isset($indexId)) { $parameters['index_id'] = $indexId; } $url = sprintf('https://search.openfoodfacts.org/autocomplete?%s', http_build_query($parameters)); return new AutocompleteResult($this->request('get', $url)); } /

**Function Details:**

@var string[] $parameters */

@throws ValidationException

@throws NotFoundException

@throws UnknownException

@throws GuzzleException


```php

```

<hr>

## Function Name: __construct

**Description:** public array $listDocuments; public readonly int $count; /** @var bool if false, the value is just an approximation*/ public readonly bool $isCountExact; public readonly int $page; public readonly int $pageSize; public readonly int $pageCount; public readonly array $debug; public readonly ?array $warning; /** @var int time it took in ms  */ public readonly int $took; /** @var bool partial content if true ? */ public readonly bool $timedOut; public readonly array $aggregations; / initialization of the collection

**Function Details:**

@var array<int, SearchDocument> */

@param array $content the raw data


```php
public function __construct(array $content)
    {
        $this->count = $content['count'] ?? 0;
        $this->isCountExact = $content['is_count_exact'] ?? false;
        $this->page = $content['page'] ?? 0;
        $this->pageSize = $content['page_size'] ?? 0;
        $this->pageCount = $content['page_count'] ?? 0;
        $this->aggregations = $content['aggregations'] ?? null;

        $this->debug = $content['debug'] ?? [];
        $this->took = $content['took'] ?? 0;
        $this->timedOut = $content['timed_out'] ?? false;
        $this->warning = $content['warnings'] ?? null;

        $this->listDocuments = array_map(
            fn (array $item) => new SearchDocument($item),
            $content['hits'] ?? []
        );
    }
```

<hr>

