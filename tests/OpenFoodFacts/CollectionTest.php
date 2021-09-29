<?php

namespace Tests\OpenFoodFacts;

use OpenFoodFacts\Collection;
use OpenFoodFacts\Document;
use PHPUnit\Framework\TestCase;

/**
 * Class CollectionTest
 */
class CollectionTest extends TestCase
{
    public function testConstructorMustPopulateListDocumentWithExistingDocuments(): void
    {
        $documents = [
            Document::createSpecificDocument('', []),
            Document::createSpecificDocument('', []),
        ];

        $collection = new Collection([
            'products'=> $documents,
            'count' => 2,
            'page' => 1,
            'skip' => false,
            'page_size' => 2,
        ], '');

        $this->assertCount(count($documents), $collection);
        $docs = [];
        foreach ($collection as $doc) {
            $docs[] = $doc;
        }
        $this->assertSame($docs, $documents);
    }
}
