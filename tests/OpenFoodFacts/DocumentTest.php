<?php

use OpenFoodFacts\Document;
use PHPUnit\Framework\TestCase;

class DocumentTest extends TestCase
{
    public function testCreateSpecificDocumentMustCreatedADocumentFromEmptyIdentifier()
    {
        $this->assertInstanceOf(Document::class, Document::createSpecificDocument('', []));
    }

    public function testCreateSpecificDocumentMustCreatedADocumentFromUnknownIdentifier()
    {
        $this->assertInstanceOf(Document::class, Document::createSpecificDocument('unknown', []));
    }

    public function testGetDataMustReturnTheOriginalEmptyArray()
    {
        $doc = Document::createSpecificDocument('', $data = []);

        $this->assertSame($data, $doc->getData());
    }

    public function testGetDataMustReturnTheNumericKeysArraySorted()
    {
        $doc = Document::createSpecificDocument('', [
            1 => 'b',
            2 => 'c',
            0 => 'a',
        ]);

        $this->assertSame([
            0 =>  'a',
            1 =>  'b',
            2 =>  'c',
        ], $doc->getData());
    }

    public function testGetDataMustReturnTheStringKeysArraySorted()
    {
        $doc = Document::createSpecificDocument('', [
            'b' => 1,
            'c' => 2,
            'a' => 0,
        ]);

        $this->assertSame([
            'a' => 0,
            'b' => 1,
            'c' => 2,
        ], $doc->getData());
    }

    public function testGetDataMustReturnTheSameArray()
    {
        $doc = Document::createSpecificDocument('', $data = [
            0 => 0,
            1 => 1,
            2 => 2,
        ]);

        $this->assertSame($data, $doc->getData());
    }
}
