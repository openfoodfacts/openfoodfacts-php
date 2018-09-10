<?php

namespace OpenFoodFacts;

use OpenFoodFacts\Document\Product;
use OpenFoodFacts\Collection;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use GuzzleHttp\TransferStats;

class Api
{

    private $httpClient;

    private $geoUrl     = 'https://%s.openfoodfacts.org';
    private $currentAPI = '';
    private $geography  = 'world';
    private $service    = '';
    private $auth       = null;

    private $cache      = null;
    private $logger     = null;

    private const LISTAPI = [
      'food'    => 'https://%s.openfoodfacts.org',
      'beauty'  => 'https://%s.openbeautyfacts.org',
      'pet'     => 'https://%s.openpetfoodfacts.org'
    ];
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

    private const FILE_TYPE_MAP = [
        "mongodb"   => "openfoodfacts-mongodbdump.tar.gz",
        "csv"       => "en.openfoodfacts.org.products.csv",
        "rdf"       => "en.openfoodfacts.org.products.rdf"
    ];


    public function __construct(string $api = 'food', string $geography = 'world', LoggerInterface $logger = null)
    {
        $this->httpClient   = new \GuzzleHttp\Client();
        $this->logger       = $logger ?? new NullLogger();

        //TODO : throw Exception if not found

        $this->geoUrl     = sprintf(self::LISTAPI[$api], $geography);
        $this->geography  = $geography;
        $this->currentAPI = $api;
    }

    public function activeTestMode()
    {
        $this->geoUrl = 'https://world.openfoodfacts.net';
        $this->authentification('off', 'off');
    }

    public function authentification(string $username, string $password)
    {
        $this->auth = [
            'user_id'   => $username,
            'password'  => $password
        ];
    }

    public function __call(string $name, $arguments)
    {
        if (strpos($name, 'get') === 0) {
            $facet = strtolower(substr($name, 3));

            if (!in_array($facet, self::FACETS)) {
                throw new Exception\BadRequestException('Facet "' . $facet . '" not found');
            }

            if ($facet == "purchase_places") {
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
            return new Collection($result);
        }

        throw new \Exception('Call to undefined method '.__CLASS__.'::'.$name.'()');
    }

    public function getProduct(string $barcode) : Product
    {
        $url = $this->buildUrl('api', 'product', $barcode);

        $rawResult = $this->fetch($url);
        if ($rawResult['status'] === 0) {
            throw new Exception\ProductNotFoundException("Product not found", 1);
        }
        return new Product($rawResult['product']);
    }

    public function getByFacets(array $query = [], int $page = 1)
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
        return new Collection($result);
    }


    public function addNewProduct(array $postData)
    {
        if (!isset($postData['code']) || !isset($postData['product_name'])) {
            throw new Exception\BadRequestException('code or product_name not found!');
        }

        $url = $this->buildUrl('cgi', 'product_jqm2.pl', []);
        $result = $this->fetchPost($url, $postData);

        if ($result['status_verbose'] === 'fields saved' && $result['status'] === 1) {
            return true;
        }
        return $result['status_verbose'];
    }

    public function uploadImage(string $code, string $imagefield, string $img_path)
    {
        //TODO : need test
        if ($this->currentAPI !== 'food') {
            throw new Exception\BadRequestException('not Available yet');
        }
        if (!in_array($imagefield, ["front", "ingredients", "nutrition"])) {
            throw new Exception\BadRequestException('Imagefield not valid!');
        }
        if (!file_exists($img_path)) {
            throw new Exception\BadRequestException('Image not found');
        }


        $url = $this->buildUrl('cgi', 'product_image_upload.pl', []);
        $postData = [
            'code'                      => $code,
            'imagefield'                => $imagefield,
            'imgupload_' . $imagefield  => fopen($img_path, 'r')
        ];
        return $this->fetchPost($url, $postData, true);
    }

    public function search(string $search, int $page = 1, int $pageSize = 20, string $sortBy = 'unique_scans')
    {
        $parameters = [
            'search_terms'  => $search,
            'page'          => $page,
            'page_size'     => $pageSize,
            'sort_by'       => $sortBy,
            'json'          => '1'
        ];

        $url = $this->buildUrl('cgi', 'search.pl', $parameters);
        $result = $this->fetch($url, false);
        return new Collection($result);
    }


    public function downloadData(string $file, string $fileType = "mongodb")
    {

        if (!isset(self::FILE_TYPE_MAP[$fileType])) {
            throw new Exception\BadRequestException('File type not recognized!');
        }
        $url        = $this->buildUrl('data', self::FILE_TYPE_MAP[$fileType], []);
        $response   = $this->httpClient->get($url, ['sink' => $file]);

        $this->logger->info('OpenFoodFact - fetch - GET : ' . $url . ' - ' . $response->getStatusCode());
        return $response->getStatusCode() == 200;
    }

    private function fetch(string $url = null, bool $isJsonFile = true) : array
    {

        $url .= ($isJsonFile? '.json' : '');

        $data = [
            'on_stats' => function (TransferStats $stats) use (&$realUrl) {
                $realUrl= (string)$stats->getEffectiveUri();
            }
        ];
        if ($this->auth) {
            $data['auth'] = array_values($this->auth);
        }

        $realUrl = $url;

        $response = $this->httpClient->get($url, $data);

        if ($realUrl !== $url) {
            $this->logger->warning('OpenFoodFact - The url : '. $url . ' has been redirect to ' . $realUrl);
            trigger_error('OpenFoodFact - Your request has been redirect');
        }
        $this->logger->info('OpenFoodFact - fetch - GET : ' . $url . ' - ' . $response->getStatusCode());

        return json_decode($response->getBody(), true);
    }
    private function fetchPost(string $url = null, array $postData, bool $isMultipart = false) : array
    {
        $data = [];
        if ($this->auth) {
            $data['auth'] = array_values($this->auth);
        }
        if ($isMultipart) {
            //TODO :need test
            foreach ($postData as $key => $value) {
                $data['multipart'][] = [
                    'name'      => $key,
                    'contents'  => $value
                ];
            }
        } else {
            $data['form_params'] = $postData;
        }

        $response = $this->httpClient->post($url, $data);

        $this->logger->info('OpenFoodFact - fetch - GET : ' . $url . ' - ' . $response->getStatusCode());

        return json_decode($response->getBody(), true);
    }

    private function buildUrl(string $service = null, $resourceType = null, $parameters = null) : string
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
                //need test
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
                $baseUrl .= '?' . http_build_query($parameters);
                break;
            case null:
            default:
                if (is_array($resourceType)) {
                    $resourceType = implode('/', $resourceType);
                }
                if ($resourceType == 'ingredients') {
                    //need test
                    $resourceType = implode('/', ["state",  "complete", $resourceType]);
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
