<?php

namespace OpenFoodFactsTests;

use OpenFoodFacts\Api;
use OpenFoodFacts\Document;

class Helper
{
    public static function getProductWithCache(Api $api, string $barCode): Document
    {
        // Let the API's own cache account for the host and request parameters.
        return $api->getProduct($barCode);
    }
}
