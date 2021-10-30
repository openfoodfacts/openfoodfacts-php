<?php

declare(strict_types=1);

namespace OpenFoodFacts\Api;

use GuzzleHttp\ClientInterface;
use OpenFoodFacts\Collection;
use OpenFoodFacts\Document;
use OpenFoodFacts\Exception\ProductNotFoundException;

trait ApiClient
{
    abstract protected function getHttpClient(): ClientInterface;
    abstract protected function getHost(): string;
    abstract protected function getCurrentApi(): string;

    private string $version = 'v2';


    public function getProduct(string $barcode, ?array $fields = null, bool $throwExceptionIfNotFound = true): Document
    {
        $url = sprintf('%s/api/%s/product/%s', $this->getHost(), $this->version, $barcode) . '?' . http_build_query(['fields'=> $fields]);
        $response = $this->requestJson('GET', $url);

        if ($throwExceptionIfNotFound && $response['status'] === 0) {
            throw new ProductNotFoundException();
        }

        return Document::documentFactory($this->getCurrentApi(), $response['product']);
    }

    public function getProducts(array $barcodes, ?array $fields = null, int $page = 1, int $pageSize = 24)
    {
        $url = sprintf('%s/api/%s/search?code=%s', $this->getHost(), $this->version, implode(',', $barcodes));
        $url .='?' . http_build_query(['fields'=> $fields, 'page' => $page, 'page_size'=> $pageSize]);
        $collectionData = $this->requestJson('GET', $url);

        return new Collection($collectionData, $this->getCurrentApi());
    }



    //https://world.openfoodfacts.org/api/v2/attribute_groups_fr
    //https://fr.openfoodfacts.org/api/v2/preferences_fr
}
