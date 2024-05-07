<?php

namespace OpenFoodFactsTests\Integration;

use OpenFoodFacts\Document\SearchDocument;
use OpenFoodFacts\Model\SearchResult;
use OpenFoodFacts\SearchApi;
use PHPUnit\Framework\TestCase;

class SearchApiTest extends TestCase
{
    public function testSearchFunction(): void
    {
        $api = new SearchApi('Integration test');
        $result = $api->search('chocolat');
        $this->assertEquals(SearchResult::class, get_class($result));
    }

    public function testGetDocumentFunction(): void
    {
        $api = new SearchApi('Integration test');
        $result = $api->getDocument('3222475591327');
        $this->assertEquals(SearchDocument::class, get_class($result));
    }
}
