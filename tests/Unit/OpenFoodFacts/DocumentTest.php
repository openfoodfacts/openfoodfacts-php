<?php

namespace OpenFoodFactsTests\Unit\OpenFoodFacts;

use OpenFoodFacts\Document;
use PHPUnit\Framework\TestCase;

/**
 * Class DocumentTest
 */
class DocumentTest extends TestCase
{
    public function testCreateSpecificDocumentMustCreatedADocumentFromEmptyIdentifier(): void
    {
        $this->assertInstanceOf(Document::class, Document::createSpecificDocument('', []));
    }

    public function testCreateSpecificDocumentMustCreatedADocumentFromUnknownIdentifier(): void
    {
        $this->assertInstanceOf(Document::class, Document::createSpecificDocument('unknown', []));
    }

    public function testCreateSpecificDocumentFromKnownIdentifiers(): void
    {
        $types = [
            'food'    => Document\FoodDocument::class,
            'beauty'  => Document\BeautyDocument::class,
            'pet'     => Document\PetDocument::class,
            'product' => Document\ProductDocument::class,
        ];
        foreach ($types as $type => $className) {
            $this->assertInstanceOf($className, Document::createSpecificDocument($type, []));
        }
    }

    public function testGetDataMustReturnTheOriginalEmptyArray(): void
    {
        $doc = Document::createSpecificDocument('', $data = []);

        $this->assertSame($data, $doc->getData());
    }

    public function testGetDataMustReturnTheNumericKeysArraySorted(): void
    {
        $doc = Document::createSpecificDocument(
            '',
            [
                1 => 'b',
                2 => 'c',
                0 => 'a',
            ]
        );

        $this->assertSame(
            [
                0 => 'a',
                1 => 'b',
                2 => 'c',
            ],
            $doc->getData()
        );
    }

    public function testGetDataMustReturnTheStringKeysArraySorted(): void
    {
        $doc = Document::createSpecificDocument(
            '',
            [
                'b' => 1,
                'c' => 2,
                'a' => 0,
            ]
        );

        $this->assertSame(
            [
                'a' => 0,
                'b' => 1,
                'c' => 2,
            ],
            $doc->getData()
        );
    }

    public function testGetDataMustReturnMultilayerArraySorted(): void
    {
        $doc = Document::createSpecificDocument(
            '',
            [
                'b' => 1,
                'c' => 2,
                'a' => 0,
                'd' => [
                    'b' => 1,
                    'c' => 2,
                    'a' => [
                        2 => 'a',
                        1 => 'xyz',
                        0 => 'c',
                    ],
                ],
            ]
        );

        $expectedArray =
            [
                'a' => 0,
                'b' => 1,
                'c' => 2,
                'd' => [
                    'a' => [
                        0 => 'c',
                        1 => 'xyz',
                        2 => 'a',
                    ],
                    'b' => 1,
                    'c' => 2,
                ],
            ];

        $this->assertSame($expectedArray, $doc->getData());
    }

    public function testGetDataMustReturnTheSameArray(): void
    {
        $doc = Document::createSpecificDocument(
            '',
            $data = [
                0 => 0,
                1 => 1,
                2 => 2,
            ]
        );

        $this->assertSame($data, $doc->getData());
    }
}
