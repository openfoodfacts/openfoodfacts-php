<?php

declare(strict_types=1);

namespace OpenFoodFacts\Api;

use GuzzleHttp\ClientInterface;
use OpenFoodFacts\Collection;
use OpenFoodFacts\Exception\BadRequestException;
use OpenFoodFacts\Models\Nutrients;
use Psr\SimpleCache\InvalidArgumentException;

trait CgiClient
{
    abstract protected function getHttpClient(): ClientInterface;
    abstract protected function requestJson(string $method, $uri, array $options = []): array;
    abstract protected function getHost(): string;
    abstract protected function getCurrentApi(): string;
    abstract protected function getAuthentication(): array;

    public function __construct(string $domain, ClientInterface $httpClient)
    {
        $this->baseUrl = $domain .'/cgi/';
        $this->httpClient = $httpClient;
    }

    public function buildUrl(string $endPoint, array $param = []): string
    {
        return sprintf('%s/cgi/%s', $this->getHost(), $endPoint) .'?'.http_build_query($param) ;
    }

    public function getNutrients(): array
    {
        $content = $this->requestJson('GET', $this->buildUrl('nutrients.pl'));

        return array_map(
            fn (array $nutrient) => Nutrients::createFromArray($nutrient),
            $content['nutrients'] ?? []
        );
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
    public function search(string $search, int $page = 1, int $pageSize = 20, string $sortBy = 'unique_scans'): Collection
    {

        //search client ?

        $parameters = [
            'search_terms'  => $search,
            'page'          => $page,
            'page_size'     => $pageSize,
            'sort_by'       => $sortBy,
            'json'          => '1',
        ];

        $url = $this->baseUrl.'search.pl';
        //$url = $this->buildUrl('cgi', 'search.pl', $parameters);
        //$result = $this->fetch($url, false);

        return new Collection([], '');
    }

    public function addProduct()
    {
        //URL: https://us.openfoodfacts.org/cgi/product_jqm2.pl?code=04963406&user_id=test&password=test&brands=Häagen-Dazs&labels=kosher
    }

    public function addPicture()
    {
        //URL: https://us.openfoodfacts.org/cgi/product_jqm2.pl?code=04963406&product_image_upload.pl/imgupload_front=cheeriosfrontphoto.jpg
    }

    public function cropPicture()
    {
        //URL: https://world.openfoodfacts.org/cgi/product_image_crop.pl?code=04963406&imgid=2&id=front_en&x1=0&y1=0&x2=145&y2=145
    }

    // not enough documented
    public function deselectingPicture()
    {
    }

    // not enough documented
    public function ocrPicture()
    {
    }


    // not enough documented
    public function rotatePicture()
    {
        //POST https://world.openfoodfacts.org/cgi/product_image_crop.pl?code=3266110700910&id=nutrition_fr&imgid=1&angle=90
    }
}
