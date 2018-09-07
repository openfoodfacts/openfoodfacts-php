<?php

namespace OpenFoodFacts;

use OpenFoodFacts\Document\Product;
use OpenFoodFacts\Collection;
use Psr\Log\LoggerInterface;


class Api {

    private $httpClient;

    private $geoUrl     = 'https://%s.openfoodfacts.org';
    private $currentAPI = '';
    private $geography  = 'world';
    private $service    = '';

    private $cache      = null;
    private $logger     = null;

    private const listApi = [
      'food'    => 'https://%s.openfoodfacts.org',
      'beauty'  => 'https://%s.openbeautyfacts.org',
      'pet'     => 'https://%s.openpetfoodfacts.org'
    ];



    /*
    private $httpClient;
    private $httpClient;
    private $httpClient;
    private $httpClient;
    */

    public function __construct(string $api = 'food', string $geography = 'world', LoggerInterface $logger = null, \CacheInterface $cache = null) {
        $this->httpClient= new \GuzzleHttp\Client();
        //TODO : throw Exception if not found

        $this->geoUrl     = sprintf(self::listApi[$api],$geography);
        $this->geography  = $geography;
        $this->currentAPI = $api;


        $this->cache      = $cache;
        $this->logger     = $logger;

    }

    public function getProduct(string $barcode) : Product{
        $url = $this->buildUrl('api','product',$barcode);

        $rawResult = $this->fetch($url);
        if ( $rawResult['status'] === 0 ){
           throw new Exception\ProductNotFoundException("Product not found", 1);
        }
        return new Product($rawResult['product']);
    }

    public function getByFacets(array $query = [], int $page=1){
        if (empty($query)){
            return new Collection();
        }
        $search = [];
        ksort($query);
        foreach($query as $key => $value){
            $search[] = $key;
            $search[] = $value;
        }

        $url = $this->buildUrl(null,$search,$page);
        $result = $this->fetch($url);
        return new Collection($result);
    }


    public function addNewProduct(array $postData)
    {
        if (!isset($postData['code']) || !isset($postData['product_name'])) {
            throw new Exception\BadRequestException('code or product_name not found!');
        }

        $url = $this->buildUrl('cgi','product_jqm2.pl',[]);
        $result = $this->fetchPost($url,$postData);

        if ($result['status_verbose'] === 'fields saved' && $result['status'] === 1) {
            return true;
        }
        return $result['status_verbose'];
    }

    public function uploadImage(string $code,string $imagefield,string $img_path)
    {
        //TODO : need test
        if($this->currentAPI !== 'food'){
            throw new \Exception('not Available yet', 1);
        }
        if(!in_array($imagefield,["front", "ingredients", "nutrition"])) {
            throw new Exception\BadRequestException('Imagefield not valid!');
        }

        $url = $this->buildUrl('cgi','product_image_upload.pl',[]);
        $postData = [
            'code'                      => $code,
            'imagefield'                => $imagefield,
            'imgupload_' . $imagefield  => fopen($img_path, 'r')
        ];
        $result = $this->fetchPost($url,$postData,true);
        var_dump($result);
        throw new \Exception("No return AND no test", 1);

    }

    public function search(string $search,int $page=1,int $pageSize=20,string $sortBy = 'unique_scans')
    {
        $parameters = [
            'search_terms'  => $search,
            'page'          => $page,
            'page_size'     => $pageSize,
            'sort_by'       => $sortBy,
            'json'          => '1'
        ];

        $url = $this->buildUrl('cgi','search.pl',$parameters);
        $result = $this->fetch($url,false);
        return new Collection($result);

    }





    private function fetch(string $url = null , bool $isJsonFile = true) : array {
        $response = $this->httpClient->get($url . ($isJsonFile? '.json' : '') );

        if ($this->logger) {
            $this->logger->info('OpenFoodFact - fetch - GET : ' . $url . ' - ' . $response->getStatusCode());
        }
        return json_decode($response->getBody(),true);
    }
    private function fetchPost(string $url = null,array $postData, bool $isMultipart = false) : array {
        $data = [];
        if($isMultipart){
            foreach($postData as $key => $value){
                $data[] = [
                    'name'      => $key,
                    'contents'  => $value
                ];
            }
        }else{
            $data = ['form_params'=>$postData];
        }

        $response = $this->httpClient->post($url,$data);
        if ($this->logger) {
            $this->logger->info('OpenFoodFact - fetch - POST : ' . $url . ' - ' . $response->getStatusCode());
        }
        return json_decode($response->getBody(),true);
    }

    private function buildUrl(string $service = null , $resourceType = null,$parameters = null) : string {
        $baseUrl = null;
        switch($service){
            case 'api':
                $baseUrl = implode('/',[
                  $this->geoUrl,
                  $service,
                  'v0',
                  $resourceType,
                  $parameters
                ]);
                break;
            case 'data':
                //need test
                $baseUrl = implode('/',[
                  $this->geoUrl,
                  $service,
                  $resourceType
                ]);
                break;
            case 'cgi':
                $baseUrl = implode('/',[
                  $this->geoUrl,
                  $service,
                  $resourceType
                ]);
                $baseUrl .= '?' . http_build_query($parameters);
                break;
            case null:
                if(is_array($resourceType)){
                    $resourceType = implode('/',$resourceType);
                }
                if($resourceType == 'ingredients'){
                    //need test
                    $resourceType = implode(',',["state",  "complete", $resourceType]);
                    $parameters   = 1;
                }
                $baseUrl = implode('/',array_filter([
                    $this->geoUrl,
                    $resourceType,
                    $parameters
                ],function($value){return !empty($value);}));
                break;

            default:
                $listParameter = [
                    'service'       => $service,
                    'resourceType'  => $resourceType,
                    'parameters'    => $parameters
                ];
                $message = 'OpenFoodFact - Unable to generate the url, parameters : ' . print_r($listParameter,true);
                if ($this->logger) {
                    $this->logger->critical($message);
                }
                throw new \Exception("Error Processing Request", 1);
                break;

        }

        if ($this->logger) {
            $this->logger->info('OpenFoodFact - Generated Url : ' . $baseUrl);
        }

        return $baseUrl;

    }
}
