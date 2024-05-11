<?php

namespace OpenFoodFactsTests\Integration;

use OpenFoodFacts\Document\SearchDocument;
use OpenFoodFacts\Model\AutocompleteResult;
use OpenFoodFacts\Model\SearchResult;
use OpenFoodFacts\SearchApi;
use PHPUnit\Framework\TestCase;

class SearchApiTest extends TestCase
{
    private function getInstance(): SearchApi
    {
        return new SearchApi('Integration test');
    }

    public function testSearchFunction(): void
    {
        $result = $this->getInstance()->search('chocolat');
        $this->assertEquals(SearchResult::class, get_class($result));
    }

    public function testGetDocumentFunction(): void
    {
        $result = $this->getInstance()->getDocument('3222475591327');
        $this->assertEquals(SearchDocument::class, get_class($result));
    }

    public function testAutocompleteFunction(): void
    {
        $result = $this->getInstance()->autocomplete('carre', ['brand']);
        $this->assertEquals(AutocompleteResult::class, get_class($result));
    }
}
