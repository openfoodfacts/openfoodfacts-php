<?php

namespace OpenFoodFactsTests\Legacy;

use OpenFoodFacts\Api;
use OpenFoodFacts\Document;

class Helper
{
    public static function getProductWithCache(Api $api, string $barCode): Document
    {
        return $GLOBALS['cache-'.$api->getCurrentApi()][$barCode] ?? $GLOBALS['cache-'.$api->getCurrentApi()][$barCode]= $api->getProduct($barCode);
    }
}
