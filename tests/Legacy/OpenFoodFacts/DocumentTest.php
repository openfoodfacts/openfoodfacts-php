<?php

namespace OpenFoodFactsTests\Legacy\OpenFoodFacts;

use OpenFoodFacts\Document;
use PHPUnit\Framework\TestCase;

/**
 * Class DocumentTest
 */
class DocumentTest extends TestCase
{
    public function testCreateSpecificDocumentMustCreatedADocumentFromEmptyIdentifier(): void
    {
        $this->assertInstanceOf(Document::class, Document::documentFactory('', []));
    }

    public function testCreateSpecificDocumentMustCreatedADocumentFromUnknownIdentifier(): void
    {
        $this->assertInstanceOf(Document::class, Document::documentFactory('unknown', []));
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
            $this->assertInstanceOf($className, Document::documentFactory($type, []));
        }
    }

    public function testGetDataMustReturnTheOriginalEmptyArray(): void
    {
        $doc = Document::documentFactory('', $data = []);

        $this->assertSame($data, $doc->getData());
    }

    public function testGetDataMustReturnTheNumericKeysArraySorted(): void
    {
        $doc = Document::documentFactory(
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
        $doc = Document::documentFactory(
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
        $doc = Document::documentFactory(
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
        $doc = Document::documentFactory(
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
