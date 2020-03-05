<?php

use OpenFoodFacts\Collection;
use OpenFoodFacts\Document;
use PHPUnit\Framework\TestCase;

class CollectionTest extends TestCase
{
    public function testConstructorMustPopulateListDocumentWithExistingDocuments()
    {
        $collection = new Collection([
            'products'=> $documents = [
                Document::createSpecificDocument('', []),
                Document::createSpecificDocument('', []),
            ],
            'count' => 2,
            'page' => 1,
            'skip' => false,
            'page_size' => 2,
        ], '');

        $this->assertCount(\count($documents), $collection);
        $docs = [];
        foreach($collection as $doc) {
            $docs[] = $doc;
        }
        $this->assertSame($docs, $documents);
    }
}
